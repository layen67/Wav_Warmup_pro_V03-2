<?php

namespace PostalWarmup\API;

use PostalWarmup\Models\Database;
use PostalWarmup\Services\Logger;
use PostalWarmup\Core\TemplateEngine;
use PostalWarmup\Admin\Settings;

declare(strict_types=1);

/**
 * Classe d'envoi des emails via Postal
 */
class Sender {

	public function init(): void {
		add_action( 'pw_send_email_async', array( $this, 'process_queue' ), 10, 6 );
	}

	public static function send( string $to, string $domain, ?string $prefix = null, ?array $server = null ): array {
		if ( ! $server ) {
			$server = Database::get_server_by_domain( $domain );
			if ( ! $server ) {
				return [ 'error' => "Serveur introuvable pour le domaine : $domain" ];
			}
		}
		
		if ( $prefix === null ) {
			$prefix = 'support';
		}
		
		if ( function_exists( 'as_schedule_single_action' ) ) {
			$args = array(
				'to'          => $to,
				'domain'      => $domain,
				'prefix'      => $prefix,
				'server_id'   => (int)$server['id'],
				'retry_count' => 0,
				'handle_retry'=> false // Sender does not handle retry in async mode, QueueManager does? No, async here means fire and forget from e.g. manual test.
				// Wait, if manual test triggers this, we want result?
				// If scheduled, it runs later.
				// QueueManager calls process_queue directly (sync).
				// This method 'send' is used for immediate/test sends.
			);
			
			// If we schedule it, we can't return success immediately unless we just say 'queued'.
			// For tests, we might want sync.
			// Let's assume 'send' is for test/manual mainly. QueueManager uses 'process_queue'.

			// Wait, if QueueManager uses 'process_queue', then 'send' is just a wrapper?
			// QueueManager calls `new Sender()->process_queue(...)`.

			// So `send` is a static helper for other parts of the app.

			as_schedule_single_action( time(), 'pw_send_email_async', $args, 'postal-warmup' );
			
			Logger::info( "Email mis en file d'attente", [
				'to'     => $to,
				'domain' => $domain
			]);
			
			return [ 'success' => true, 'queued' => true ];
		} 
		
		// Fallback synchrone
		$sender = new self();
		return $sender->process_queue( $to, $domain, $prefix, (int)$server['id'], 0 );
	}

	public function process_queue( string $to, string $domain, string $prefix, int $server_id, int $retry_count = 0, bool $handle_retry = true ): array {
		
		$server = Database::get_server( $server_id );
		if ( ! $server ) {
			Logger::error( "Worker: Serveur introuvable ID $server_id" );
			return [ 'error' => 'Serveur introuvable' ];
		}

		$prepared = TemplateEngine::prepare_template( $prefix, $domain, $prefix, $to );
		$template_name = $prepared['name'];

		$from_email = $prefix . '@' . $domain;

		$from_name = $prepared['from_name'];
		if ( empty( $from_name ) ) {
			$default_name = (string) Settings::get( 'default_from_name', '' );
			if ( ! empty( $default_name ) ) {
				$from_name = $default_name;
			}
		}

		Logger::info( "Worker: Traitement envoi email", [
			'server_id'  => $server['id'],
			'email_from' => $from_email,
			'email_to'   => $to,
			'retry'      => $retry_count,
			'template'   => $template_name
		]);
		
		$payload = [
			'to'         => [ $to ],
			'from'       => "$from_name <$from_email>",
			'subject'    => $prepared['subject'],
			'plain_body' => $prepared['text'],
			'html_body'  => $prepared['html'],
			'headers'    => [ 
				'X-Warmup-Source'   => 'PostalWarmupPro-v' . PW_VERSION,
				'X-Warmup-Template' => $template_name,
				'Precedence'        => 'bulk',
				'Auto-Submitted'    => 'auto-generated',
				'List-Unsubscribe'  => "<mailto:unsubscribe@$domain?subject=unsubscribe>",
			]
		];

		$custom_headers = (string) Settings::get( 'custom_headers', '' );
		if ( ! empty( $custom_headers ) ) {
			$lines = explode( "\n", $custom_headers );
			foreach ( $lines as $line ) {
				$parts = explode( ':', $line, 2 );
				if ( count( $parts ) === 2 ) {
					$payload['headers'][ trim( $parts[0] ) ] = trim( $parts[1] );
				}
			}
		}

		if ( ! empty( $prepared['reply_to'] ) ) {
			$payload['reply_to'] = $prepared['reply_to'];
		}

		$global_tag = get_option( 'pw_global_tag', 'warmup' );
		if ( ! empty( $global_tag ) ) {
			$payload['tag'] = sanitize_text_field( $global_tag );
		}

		// Use apply_filters with correct types
		$payload = apply_filters( 'pw_email_payload', $payload, $prepared, [] );

		$result = self::send_request( $server, $payload, $retry_count + 1, $template_name );
		
		$response_time = isset( $result['response_time'] ) ? (float)$result['response_time'] : 0.0;

		if ( $result['success'] ) {
			$message_id = $result['response']['data']['message_id'] ?? null;
			Database::insert_stat_history( [
				'server_id'   => $server['id'],
				'template_id' => $prepared['id'] ?? null,
				'message_id'  => $message_id,
				'email_from'  => $from_email,
				'event_type'  => 'sent',
				'timestamp'   => current_time( 'mysql' ),
				'meta'        => json_encode( [ 'template_name' => $template_name ] )
			] );

			Database::increment_sent( $domain, true, $response_time );
			Database::record_stat( (int)$server['id'], true, $response_time );
			return $result;
		}
		
		Database::increment_sent( $domain, false, $response_time );
		Database::record_stat( (int)$server['id'], false, $response_time );
		
		return $result;
	}

	private static function send_request( array $server, array $payload, int $attempt, ?string $template_name = null ): array {
		$api_url = rtrim( $server['api_url'], '/' );
		$api_key = $server['api_key'];
		$url = $api_url . '/send/message';
		
		$start_time = microtime( true );
		
		try {
			$response = wp_remote_post( $url, [
				'headers' => [
					'Content-Type'     => 'application/json',
					'X-Server-API-Key' => $api_key
				],
				'body'      => json_encode( $payload ),
				'timeout'   => (int) Settings::get( 'api_timeout', 15 ),
				'sslverify' => true
			]);
		} catch ( \Throwable $e ) {
			return [ 'success' => false, 'error' => $e->getMessage(), 'response_time' => 0.0 ];
		}
		
		$response_time = microtime( true ) - $start_time;
		
		if ( is_wp_error( $response ) ) {
			Logger::error( "Erreur HTTP (tentative $attempt)", [
				'server_id'     => $server['id'],
				'error'         => $response->get_error_message(),
				'response_time' => round( $response_time, 3 )
			]);
			return [ 'success' => false, 'error' => $response->get_error_message(), 'response_time' => $response_time ];
		}
		
		$http_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		
		Logger::debug( "Réponse Postal", [
			'server_id'     => $server['id'],
			'http_code'     => $http_code,
			'response_time' => round( $response_time, 3 )
		]);
		
		if ( $http_code !== 200 ) {
			$error_msg = "HTTP $http_code";
			$json = json_decode( $body, true );
			if ( $json && isset( $json['data']['message'] ) ) {
				$error_msg .= ' - ' . $json['data']['message'];
			} elseif ( $json && isset( $json['message'] ) ) {
				$error_msg .= ' - ' . $json['message'];
			}
			
			Logger::error( "Erreur API Postal ($http_code)", [
				'server_id' => $server['id'],
				'response'  => $body
			]);

			return [ 'success' => false, 'error' => $error_msg, 'response_time' => $response_time ];
		}
		
		$data = json_decode( $body, true );
		if ( ! $data || ( isset( $data['status'] ) && $data['status'] !== 'success' ) ) {
			return [ 'success' => false, 'error' => $data['message'] ?? 'Réponse API invalide', 'response_time' => $response_time ];
		}
		
		$message_id = $data['data']['message_id'] ?? null;

		Logger::info( "Email envoyé avec succès", [
			'server_id'     => $server['id'],
			'email_to'      => $payload['to'][0] ?? '',
			'message_id'    => $message_id,
			'response_time' => round( $response_time, 3 ),
			'status'        => 'success',
			'template'      => $template_name
		]);
		
		return [ 'success' => true, 'response' => $data, 'response_time' => $response_time ];
	}

	public static function test_connection( int $server_id ): array {
		$server = Database::get_server( $server_id );
		if ( ! $server ) {
			return [ 'success' => false, 'message' => __( 'Serveur introuvable', 'postal-warmup' ) ];
		}
		
		$test_email = get_option( 'admin_email' );
		$test_payload = [
			'to'         => [ $test_email ],
			'from'       => "Test <test@{$server['domain']}>",
			'subject'    => 'Test Postal Warmup',
			'plain_body' => "Test OK.\nServeur : {$server['domain']}",
			'html_body'  => "<p>Test OK.</p><p><strong>Serveur :</strong> {$server['domain']}</p>"
		];
		
		$result = self::send_request( $server, $test_payload, 1 );
		
		if ( $result['success'] ) {
			return [ 'success' => true, 'message' => sprintf( __( 'Test réussi ! Email envoyé à %s', 'postal-warmup' ), $test_email ) ];
		}
		return [ 'success' => false, 'message' => sprintf( __( 'Test échoué : %s', 'postal-warmup' ), $result['error'] ) ];
	}
}
