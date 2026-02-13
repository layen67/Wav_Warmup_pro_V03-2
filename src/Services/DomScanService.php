<?php

namespace PostalWarmup\Services;

class DomScanService {

    private static $api_base = 'https://api.domscan.net/v1'; // Hypothetical Endpoint

    /**
     * Lance un audit complet pour un domaine
     */
    public static function audit_domain( $domain ) {
        $api_key = get_option( 'pw_domscan_api_key' );

        if ( empty( $api_key ) ) {
            return new \WP_Error( 'missing_key', 'Clé API DomScan manquante.' );
        }

        // 1. Check Health (DNS, SSL, etc.)
        $health = self::request( '/domain/health', [ 'domain' => $domain ], $api_key );
        if ( is_wp_error( $health ) ) return $health;

        // 2. Check Blacklist
        $blacklist = self::request( '/email/blacklist', [ 'domain' => $domain ], $api_key );

        // 3. Check Reputation
        $reputation = self::request( '/domain/reputation', [ 'domain' => $domain ], $api_key );

        // Aggregate Results
        $result = [
            'timestamp' => current_time( 'mysql' ),
            'domain' => $domain,
            'health_score' => $health['data']['score'] ?? 0, // Mock structure
            'health_issues' => $health['data']['issues'] ?? [],
            'blacklist_count' => $blacklist['data']['listed_count'] ?? 0,
            'reputation_score' => $reputation['data']['trust_score'] ?? 0,
            'spf_valid' => $health['data']['spf']['valid'] ?? false,
            'dkim_valid' => $health['data']['dkim']['valid'] ?? false,
            'dmarc_valid' => $health['data']['dmarc']['valid'] ?? false,
        ];

        // Save to transient for display
        set_transient( 'pw_domscan_' . md5( $domain ), $result, 12 * HOUR_IN_SECONDS );

        return $result;
    }

    private static function request( $endpoint, $params, $api_key ) {
        $url = self::$api_base . $endpoint . '?' . http_build_query( $params );

        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Accept' => 'application/json'
            ],
            'timeout' => 15
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 ) {
            return new \WP_Error( 'api_error', 'Erreur DomScan (' . $code . '): ' . ( $data['message'] ?? 'Inconnue' ) );
        }

        return $data;
    }

    /**
     * Récupère le dernier audit en cache
     */
    public static function get_cached_audit( $domain ) {
        return get_transient( 'pw_domscan_' . md5( $domain ) );
    }
}
