<?php

namespace PostalWarmup\Services;

use PostalWarmup\Admin\Settings;

declare(strict_types=1);

class DomScanService {

	public static function audit_domain( string $domain ): array|\WP_Error {
		$api_key = Settings::get( 'domscan_api_key' );

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'missing_key', 'Clé API DomScan manquante.' );
		}

		// Mock implementation as we don't have the real endpoint URL
		// Assuming standard structure
		$url = 'https://api.domscan.com/v1/audit/' . $domain;

		$response = wp_remote_get( $url, [
			'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
			'timeout' => 10
		]);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return $data ?: new \WP_Error( 'invalid_json', 'Réponse invalide' );
	}
}
