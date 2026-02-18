<?php

namespace PostalWarmup\API;

use PostalWarmup\Models\Database;
use PostalWarmup\Admin\Settings;
use PostalWarmup\Services\Logger;

declare(strict_types=1);

/**
 * Gestionnaire des Webhooks entrants (Postal -> Plugin)
 */
class WebhookHandler {

	public function register_routes(): void {
		register_rest_route( 'postal-warmup/v1', '/webhook', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_webhook' ],
			'permission_callback' => '__return_true', // Validation manuelle via signature
		] );
	}

	public function handle_webhook( \WP_REST_Request $request ) {
		// 1. Rate Limiting (IP-based)
		if ( $this->is_rate_limited() ) {
			return new \WP_REST_Response( [ 'message' => 'Rate limit exceeded' ], 429 );
		}

		// 2. IP Whitelisting
		$whitelist = Settings::get( 'webhook_ip_whitelist', '' );
		if ( ! empty( $whitelist ) ) {
			$ip = $_SERVER['REMOTE_ADDR'] ?? '';
			if ( ! $this->is_ip_allowed( $ip, $whitelist ) ) {
				Logger::warning( "Webhook: IP bloquée ($ip)" );
				return new \WP_REST_Response( [ 'message' => 'Forbidden' ], 403 );
			}
		}

		// 3. Signature Verification
		if ( Settings::get( 'webhook_strict_mode', true ) ) {
			$secret = get_option( 'pw_webhook_secret' );
			$token = $request->get_param( 'token' );

			// V3: Support Postal Signature Header if configured?
			// Postal sends X-Postal-Signature usually.
			// Currently we use ?token=SECRET in the URL for simplicity as per Settings page logic.

			if ( ! $secret || $token !== $secret ) {
				$action = Settings::get( 'webhook_invalid_signature_action', 'log' );
				if ( $action === 'log' || $action === 'notify' ) {
					Logger::warning( 'Webhook: Signature invalide.' );
				}
				return new \WP_REST_Response( [ 'message' => 'Unauthorized' ], 401 );
			}
		}

		$payload = $request->get_json_params();
		if ( empty( $payload ) ) {
			return new \WP_REST_Response( [ 'message' => 'Empty payload' ], 400 );
		}

		// 4. Processing
		if ( isset( $payload['event'] ) ) {
			// Single event
			$this->process_event( $payload );
		} elseif ( isset( $payload['payload'] ) ) {
			// Wrapped payload? Postal usually sends raw JSON or form-data.
			// Assuming JSON structure from Postal docs.
			// { "event": "MessageDeliveryFailed", "payload": { ... } }
			$this->process_event( $payload );
		} else {
			// Batch or unknown?
			Logger::info( 'Webhook: Format inconnu', $payload );
		}

		return new \WP_REST_Response( [ 'status' => 'processed' ], 200 );
	}

	private function process_event( array $data ): void {
		$event = $data['event'] ?? 'Unknown';
		$payload = $data['payload'] ?? [];
		
		// Map Postal events to our logic
		// Events: MessageSent, MessageDelivered, MessageDeliveryFailed, MessageBounced, MessageClicked, MessageOpened

		$message_id = $payload['message_id'] ?? null; // Postal ID
		$server_id = null; // Need to resolve from message_id if stored locally?

		// Problem: We store history by message_id, but how do we get server_id efficiently?
		// We need to query postal_stats_history or postal_logs?
		// V3 uses postal_stats_history which has index on message_id.

		if ( ! $message_id ) return;

		global $wpdb;
		$table = $wpdb->prefix . 'postal_stats_history';

		$history = $wpdb->get_row( $wpdb->prepare( "SELECT server_id, template_id FROM $table WHERE message_id = %s LIMIT 1", $message_id ), ARRAY_A );

		if ( ! $history ) {
			// Maybe logged in legacy logs?
			// For now, ignore unknown messages (could be from other apps using same Postal server)
			return;
		}

		$server_id = (int) $history['server_id'];
		$template_id = $history['template_id'] ? (int) $history['template_id'] : null;
		$template_name = null;
		
		if ( $template_id ) {
			$template_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}postal_templates WHERE id = %d", $template_id ) );
		}

		// Dispatch to specific handlers
		// Update detailed metrics
		$mapped_event = $this->map_event( $event );

		if ( $mapped_event ) {
			// 1. Update Metrics
			Database::update_detailed_metrics( $template_name, $server_id, $mapped_event );

			// 2. Log History
			Database::insert_stat_history([
				'server_id'   => $server_id,
				'template_id' => $template_id,
				'message_id'  => $message_id,
				'event_type'  => $mapped_event,
				'timestamp'   => current_time( 'mysql' ),
				'meta'        => json_encode( $payload )
			]);

			// 3. Update Real-time Status (Queue/Stats)
			if ( $mapped_event === 'bounced' || $mapped_event === 'failed' ) {
				Stats::record_stat( $server_id, false ); // Increment error count

				// Handle suppression / cleanup?
				// Logic moved to settings (bounce_handling_action)
				$action = Settings::get( 'bounce_handling_action', 'mark_failed' );
				if ( $action === 'notify' ) {
					// Trigger notification
				}
			}
		}
	}

	private function map_event( string $postal_event ): ?string {
		switch ( $postal_event ) {
			case 'MessageSent': return 'sent';
			case 'MessageDelivered': return 'delivered';
			case 'MessageDeliveryFailed': return 'failed';
			case 'MessageBounced': return 'bounced';
			case 'MessageOpened': return 'opened';
			case 'MessageClicked': return 'clicked';
			default: return null;
		}
	}

	private function is_rate_limited(): bool {
		$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$key = 'pw_webhook_limit_' . md5( $ip );
		$limit = (int) Settings::get( 'webhook_rate_limit_minute', 100 );

		$current = (int) get_transient( $key );
		if ( $current >= $limit ) {
			return true;
		}
		
		set_transient( $key, $current + 1, 60 );
		return false;
	}

	private function is_ip_allowed( string $ip, string $whitelist ): bool {
		$allowed_ips = array_map( 'trim', explode( "\n", $whitelist ) );
		foreach ( $allowed_ips as $allowed ) {
			if ( empty( $allowed ) ) continue;
			if ( strpos( $allowed, '/' ) !== false ) {
				// CIDR check
				if ( $this->cidr_match( $ip, $allowed ) ) return true;
			} else {
				if ( $ip === $allowed ) return true;
			}
		}
		return false;
	}

	private function cidr_match( $ip, $cidr ) {
		list( $subnet, $mask ) = explode( '/', $cidr );
		if ( ( ip2long( $ip ) & ~((1 << (32 - $mask)) - 1) ) == ip2long( $subnet ) ) {
			return true;
		}
		return false;
	}
}
