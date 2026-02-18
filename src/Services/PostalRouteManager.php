<?php

declare(strict_types=1);

namespace PostalWarmup\Services;

use PostalWarmup\Models\Database;
use PostalWarmup\API\Client;
use PostalWarmup\Admin\Settings;
use PostalWarmup\Services\Encryption;



class PostalRouteManager {

	public static function ensure_route_configured( array $server ): array {
		if ( empty( $server['api_key'] ) ) {
			return [ 'success' => false, 'message' => 'API Key missing' ];
		}

		// 1. HTTP Endpoint
		$endpoint_id = self::get_or_create_http_endpoint( $server );
		if ( ! $endpoint_id ) {
			return [ 'success' => false, 'message' => 'Failed to create HTTP Endpoint' ];
		}

		// 2. Route
		$route_id = self::get_or_create_route( $server, $endpoint_id );
		if ( ! $route_id ) {
			return [ 'success' => false, 'message' => 'Failed to create Route' ];
		}

		// 3. Update DB
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'postal_servers',
			[
				'postal_endpoint_id' => $endpoint_id,
				'postal_route_id' => $route_id,
				'incoming_configured' => 1,
				'incoming_last_check' => current_time( 'mysql' )
			],
			[ 'id' => $server['id'] ]
		);

		return [ 'success' => true, 'route_id' => $route_id ];
	}

	private static function get_or_create_http_endpoint( array $server ): string|false {
		// Check existing
		if ( ! empty( $server['postal_endpoint_id'] ) ) {
			// Verify existence? (Optional optimization)
			return $server['postal_endpoint_id'];
		}

		$url = self::get_webhook_url();
		$payload = [
			'name' => 'Postal Warmup Pro - WordPress',
			'url' => $url,
			'encoding' => 'JSON',
			'strip_replies' => true,
			'include_attachments' => false
		];

		$result = Client::request( (int)$server['id'], 'http_endpoints', 'POST', $payload );

		if ( ! is_wp_error( $result ) && isset( $result['id'] ) ) {
			return (string) $result['id'];
		}

		return false;
	}

	private static function get_or_create_route( array $server, string $endpoint_id ): string|false {
		if ( ! empty( $server['postal_route_id'] ) ) {
			return $server['postal_route_id'];
		}

		$payload = [
			'name' => 'WordPress Incoming',
			'endpoint_type' => 'HTTPEndpoint',
			'endpoint_id' => $endpoint_id,
			'matcher' => '*',
			'spam_mode' => 'Mark'
		];

		$result = Client::request( (int)$server['id'], 'routes', 'POST', $payload );

		if ( ! is_wp_error( $result ) && isset( $result['id'] ) ) {
			return (string) $result['id'];
		}

		return false;
	}

	public static function verify_route( array $server ): bool {
		if ( empty( $server['postal_route_id'] ) ) return false;

		$result = Client::request( (int)$server['id'], 'routes/' . $server['postal_route_id'] );

		$valid = ( ! is_wp_error( $result ) && isset( $result['id'] ) );

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'postal_servers',
			[ 'incoming_last_check' => current_time( 'mysql' ), 'incoming_configured' => $valid ? 1 : 0 ],
			[ 'id' => $server['id'] ]
		);

		return $valid;
	}

	public static function remove_route( array $server ): bool {
		if ( ! empty( $server['postal_route_id'] ) ) {
			Client::request( (int)$server['id'], 'routes/' . $server['postal_route_id'], 'DELETE' );
		}
		if ( ! empty( $server['postal_endpoint_id'] ) ) {
			Client::request( (int)$server['id'], 'http_endpoints/' . $server['postal_endpoint_id'], 'DELETE' );
		}

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'postal_servers',
			[ 'postal_route_id' => null, 'postal_endpoint_id' => null, 'incoming_configured' => 0 ],
			[ 'id' => $server['id'] ]
		);

		return true;
	}

	public static function get_webhook_url(): string {
		$url = get_rest_url( null, 'postal-warmup/v1/webhook' );
		$secret = get_option( 'pw_webhook_secret' );
		if ( $secret ) {
			$url = add_query_arg( 'token', $secret, $url );
		}
		return $url;
	}
}
