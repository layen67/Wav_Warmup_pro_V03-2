<?php

namespace PostalWarmup\Services;

use PostalWarmup\API\WebhookHandler;

declare(strict_types=1);

/**
 * Dispatcher pour simuler ou relayer les webhooks
 */
class WebhookDispatcher {

	public static function init(): void {
		// Hook for internal dispatch if needed
	}

	public static function send_test( string $url ): array|\WP_Error {
		$payload = [
			'event' => 'MessageDelivered',
			'timestamp' => time(),
			'payload' => [
				'message_id' => 'test-message-' . uniqid(),
				'recipient' => 'test@example.com',
				'status' => 'Delivered'
			]
		];

		return wp_remote_post( $url, [
			'body' => json_encode( $payload ),
			'headers' => [ 'Content-Type' => 'application/json' ],
			'timeout' => 5
		]);
	}
}
