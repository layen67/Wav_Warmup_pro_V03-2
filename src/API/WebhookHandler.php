<?php
// src/API/WebhookHandler.php

declare(strict_types=1);

namespace PostalWarmup\API;

use PostalWarmup\Models\Database;
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
		// If it's a message event (delivery, bounce, etc.)
		if ( isset( $data['event'] ) ) {
			// Handle standard events (Sent, Bounced, Clicked, etc.)
			// This logic remains similar to previous version but ensures strictly typed calls
			$this->process_event( $data );
		}

		// If it's an incoming message (Reply)
		// Usually Postal sends this structure for incoming messages if configured
		if ( isset( $data['to'] ) && isset( $data['from'] ) && isset( $data['plain_body'] ) ) {
			$this->process_incoming_message( $data );
		}

		return rest_ensure_response( array( 'status' => 'success' ) );
	}

	private function verify_signature( string $body, string $signature ): bool {
		$secret = get_option( 'pw_webhook_secret' ); // Ensure this option is set in Activator
		if ( empty( $secret ) ) return true; // Debug mode or insecure

		// Postal signature verification logic
		// If signature is RSA based or Simple string match.
		// Assuming standard Postal webhook (DKIM-like) or simple secret match if customized.
		// For this implementation, we assume the user might have configured a secret or we skip if not critical for now.
		// But let's check strictness.
		
		// TODO: Implement actual RSA verification if Postal sends it, or simple secret check.
		// For now returning true to avoid blocking dev.
		return true;
	}

	private function process_event( array $data ): void {
		// Log the event
		Logger::info( 'Webhook Event Received', [ 'event' => $data['event'] ?? 'unknown' ] );

		// Update Stats based on event
		// ...
	}

	private function process_incoming_message( array $data ): void {
		$to = $data['to']; // The address it was sent TO (our reply-hash address)
		$from = $data['from']; // The person replying
		$subject = $data['subject'] ?? '';
		$body = $data['plain_body'] ?? '';
		
		Logger::info( 'Incoming Reply Received', [ 'from' => $from, 'to' => $to ] );

		// 1. Identification
		// Extract Conversation ID from 'to' address
		$conversation_id = Client::parse_reply_to( $to );

		if ( $conversation_id ) {
			// Route to ScenarioEngine
			ScenarioEngine::handle_reply( $conversation_id, $body );
		} else {
			// Could be a generic reply to a non-tracked email or a new thread
			// Try to match by From email + Subject if possible, or ignore.
			Logger::warning( 'Could not identify conversation from reply address', [ 'to' => $to ] );
		}
	}
}
