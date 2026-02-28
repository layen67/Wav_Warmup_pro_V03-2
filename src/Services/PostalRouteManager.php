<?php
// src/Services/PostalRouteManager.php

declare(strict_types=1);

namespace PostalWarmup\Services;

use PostalWarmup\Models\Database;
use PostalWarmup\Admin\Settings;

/**
 * Manages Routes and Endpoints in Postal via API.
 */
class PostalRouteManager {

	/**
	 * Creates an endpoint in Postal pointing to this plugin's webhook.
	 */
	public static function ensure_webhook_endpoint( int $server_id ): array {
		$server = Database::get_server( $server_id );
		if ( ! $server ) return [ 'error' => 'Server not found' ];

		$webhook_url = get_rest_url( null, 'postal-warmup/v1/webhook' );

		// Check if endpoint exists
		// We need to list endpoints from Postal API
		// API: GET /api/v1/endpoints

		$endpoints = self::api_request( $server, 'GET', 'endpoints' );
		if ( isset( $endpoints['error'] ) ) return $endpoints;

		$existing_id = null;
		foreach ( $endpoints['data'] ?? [] as $ep ) {
			if ( $ep['url'] === $webhook_url ) {
				$existing_id = $ep['id'];
				break;
			}
		}

		if ( $existing_id ) {
			// Update just in case? Or return success
			return [ 'success' => true, 'endpoint_id' => $existing_id, 'message' => 'Endpoint already exists' ];
		}

		// Create Endpoint
		// API: POST /api/v1/endpoints
		$payload = [
			'url' => $webhook_url,
			'encoding' => 'json',
			'format' => 'full',
			'events' => [ 'MessageSent', 'MessageDelayed', 'MessageDeliveryFailed', 'MessageHeld', 'MessageBounced' ]
		];

		$create = self::api_request( $server, 'POST', 'endpoints', $payload );

		if ( isset( $create['data']['id'] ) ) {
			// Update server record locally
			global $wpdb;
			$wpdb->update( $wpdb->prefix . 'postal_servers', [
				'postal_endpoint_id' => $create['data']['id']
			], [ 'id' => $server_id ] );

			return [ 'success' => true, 'endpoint_id' => $create['data']['id'] ];
		}

		return [ 'error' => 'Failed to create endpoint' ];
	}

	/**
	 * Creates a route to forward incoming mail to the webhook (via HTTP endpoint).
	 * Usually we want to capture ALL incoming mail to handle replies.
	 */
	public static function ensure_route( int $server_id, string $endpoint_id ): array {
		$server = Database::get_server( $server_id );
		if ( ! $server ) return [ 'error' => 'Server not found' ];

		// List Routes
		$routes = self::api_request( $server, 'GET', 'routes' );
		if ( isset( $routes['error'] ) ) return $routes;

		// We look for a route that matches "reply-*" or similar, or a catch-all if desired.
		// Strategy: Create a route for "reply-*" to the endpoint.
		// Postal Route Format: name (prefix), domain (optional), endpoint_id, mode (Endpoint)

		$route_name = 'reply-*'; // Wildcard for reply-123-hash@...

		$existing_id = null;
		foreach ( $routes['data'] ?? [] as $route ) {
			if ( $route['name'] === $route_name && $route['endpoint_id'] == $endpoint_id ) {
				$existing_id = $route['id'];
				break;
			}
		}

		if ( $existing_id ) {
			return [ 'success' => true, 'route_id' => $existing_id, 'message' => 'Route already exists' ];
		}

		// Create Route
		$payload = [
			'name' => $route_name,
			'mode' => 'Endpoint',
			'endpoint_id' => $endpoint_id,
			'spam_mode' => 'Mark'
		];

		$create = self::api_request( $server, 'POST', 'routes', $payload );

		if ( isset( $create['data']['id'] ) ) {
			global $wpdb;
			$wpdb->update( $wpdb->prefix . 'postal_servers', [
				'postal_route_id' => $create['data']['id'],
				'incoming_configured' => 1,
				'incoming_last_check' => current_time( 'mysql' )
			], [ 'id' => $server_id ] );

			return [ 'success' => true, 'route_id' => $create['data']['id'] ];
		}

		return [ 'error' => 'Failed to create route' ];
	}

	private static function api_request( array $server, string $method, string $endpoint, array $data = [] ): array {
		$url = rtrim( $server['api_url'], '/' ) . '/' . $endpoint;
		$args = [
			'method' => $method,
			'headers' => [
				'X-Server-API-Key' => $server['api_key'],
				'Content-Type' => 'application/json'
			],
			'timeout' => 15
		];

		if ( ! empty( $data ) ) {
			$args['body'] = json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [ 'error' => $response->get_error_message() ];
		}

		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( ! $json || ( isset( $json['status'] ) && $json['status'] !== 'success' ) ) {
			return [ 'error' => $json['message'] ?? 'API Error' ];
		}

		return $json;
	}
}
