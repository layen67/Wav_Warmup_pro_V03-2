<?php

declare(strict_types=1);

namespace PostalWarmup\API;

use PostalWarmup\Admin\Settings;
use PostalWarmup\Services\Logger;



/**
 * Client API Postal (Requêtes sortantes)
 */
class Client {

	public static function request( int $server_id, string $endpoint, string $method = 'GET', array $data = [] ) {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_servers';

		$server = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $server_id ), ARRAY_A );
		
		if ( ! $server ) {
			return new \WP_Error( 'server_not_found', 'Serveur introuvable' );
		}

		// Decrypt API Key if not already handled by Database model (Database model handles it on get_server but raw query here?)
		// Database::get_server decodes. But here we use raw query.
		// Use Database::get_server to be consistent and safe.
		$server = \PostalWarmup\Models\Database::get_server( $server_id );
		if ( ! $server ) return new \WP_Error( 'server_not_found', 'Serveur introuvable' );

		$api_url = rtrim( $server['api_url'], '/' );
		$api_key = $server['api_key'];
		$url = $api_url . '/api/v1/' . $endpoint;

		$args = [
			'method'    => $method,
			'headers'   => [
				'X-Server-API-Key' => $api_key,
				'Content-Type'     => 'application/json'
			],
			'timeout'   => (int) Settings::get( 'api_timeout', 15 ),
			'sslverify' => true
		];

		if ( ! empty( $data ) ) {
			if ( $method === 'GET' ) {
				$url = add_query_arg( $data, $url );
			} else {
				$args['body'] = json_encode( $data );
			}
		}

		try {
			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				Logger::error( "API Error ($endpoint): " . $response->get_error_message() );
				return $response;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$json = json_decode( $body, true );

			if ( $code >= 200 && $code < 300 ) {
				return $json['data'] ?? $json; // Some endpoints return direct data, others wrapped
			}

			Logger::warning( "API Failed ($code): " . ($json['data']['message'] ?? $body) );
			return new \WP_Error( 'api_error', $json['data']['message'] ?? "Erreur HTTP $code" );

		} catch ( \Throwable $e ) {
			Logger::error( "API Exception: " . $e->getMessage() );
			return new \WP_Error( 'api_exception', $e->getMessage() );
		}
	}
}
