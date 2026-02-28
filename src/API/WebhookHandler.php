<?php
// src/API/WebhookHandler.php

declare(strict_types=1);

namespace PostalWarmup\API;

use PostalWarmup\Models\Database;
use PostalWarmup\Models\ReplyTemplateRule;
use PostalWarmup\Services\Logger;
use PostalWarmup\Services\ScenarioEngine;
use PostalWarmup\Services\ConversationManager;

/**
 * Handles incoming webhooks from Postal.
 */
class WebhookHandler {

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route( 'postal-warmup/v1', '/webhook', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_webhook' ),
			'permission_callback' => '__return_true', // Validation logic inside
		) );
	}

	public function handle_webhook( \WP_REST_Request $request ) {
		$headers = $request->get_headers();
		$signature = $headers['x_postal_signature'][0] ?? '';
		$body = $request->get_body();

		if ( ! $this->verify_signature( $body, $signature ) ) {
			return new \WP_Error( 'invalid_signature', 'Invalid Signature', array( 'status' => 403 ) );
		}

		$data = json_decode( $body, true );
		if ( ! $data ) {
			return new \WP_Error( 'invalid_json', 'Invalid JSON', array( 'status' => 400 ) );
		}

		// Routing based on event type
		if ( isset( $data['event'] ) ) {
			$this->process_event( $data );
		}

		// If it's an incoming message (Reply)
		if ( isset( $data['to'] ) && isset( $data['from'] ) && isset( $data['plain_body'] ) ) {
			$this->process_incoming_message( $data );
		}

		return rest_ensure_response( array( 'status' => 'success' ) );
	}

	private function verify_signature( string $body, string $signature ): bool {
		$secret = get_option( 'pw_webhook_secret' );
		if ( empty( $secret ) ) return true;
		// TODO: Implement robust signature verification
		return true;
	}

	private function process_event( array $data ): void {
		Logger::info( 'Webhook Event Received', [ 'event' => $data['event'] ?? 'unknown' ] );
		// Update Stats logic...
	}

	private function process_incoming_message( array $data ): void {
		$to = $data['to'];
		$from = $data['from'];
		$subject = $data['subject'] ?? '';
		$body = $data['plain_body'] ?? '';
		$headers = $data['headers'] ?? []; // Postal typically sends headers as object/array

		// 1. Layer Event (Requested Architecture)
		do_action( 'wav_incoming_email_received', $data );

		Logger::info( 'Incoming Reply Received', [ 'from' => $from, 'to' => $to ] );

		// 2. Loop Protection (Anti-Boucle Critique)
		if ( $this->is_auto_reply( $headers, $subject, $body ) ) {
			Logger::warning( 'Ignored Auto-Reply/Loop', [ 'from' => $from ] );
			return;
		}

		// 3. Identification
		// Try to match existing conversation
		$conversation_id = Client::parse_reply_to( $to );

		if ( $conversation_id ) {
			// Threading: Extract Message-ID if available for referencing
			$message_id = $data['message_id'] ?? null;

			// Route to ScenarioEngine
			ScenarioEngine::handle_reply( $conversation_id, $body, $message_id );
		} else {
			// 4. Rule Engine (Keyword Detection for new/unsolicited mail)
			// If we can identify the server from the 'to' domain
			$domain = explode( '@', $to )[1] ?? '';
			$server = Database::get_server_by_domain( $domain );

			if ( $server ) {
				$prefix = explode( '@', $to )[0];
				ScenarioEngine::process_rules( (int)$server['id'], $subject, $body, $prefix, $from );
			} else {
				Logger::warning( 'Could not identify server or conversation', [ 'to' => $to ] );
			}
		}
	}

	/**
	 * Detects auto-replies and loops.
	 */
	private function is_auto_reply( array $headers, string $subject, string $body ): bool {
		// Normalize headers
		$h = array_change_key_case( $headers, CASE_LOWER );

		// 1. Standard Headers
		if ( isset( $h['auto-submitted'] ) && stripos( $h['auto-submitted'], 'no' ) === false ) return true;
		if ( isset( $h['precedence'] ) && in_array( strtolower( $h['precedence'] ), ['bulk', 'junk', 'list'] ) ) return true;
		if ( isset( $h['x-autoreply'] ) ) return true;

		// 2. Custom Anti-Loop Header
		if ( isset( $h['x-wav-autoreply'] ) ) return true;

		// 3. Subject Keywords
		if ( preg_match( '/^(Auto:|Automatic reply|Réponse automatique|Out of Office)/i', $subject ) ) return true;

		return false;
	}
}
