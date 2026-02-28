<?php

declare(strict_types=1);

namespace PostalWarmup\Services;

use PostalWarmup\Admin\Settings;



/**
 * Service de cache avec Transients API
 */
class Cache {

	public static function get( string $key ) {
		// Respect global setting
		if ( ! Settings::get( 'enable_transient_cache', true ) ) {
			return false;
		}
		
		return get_transient( 'pw_' . $key );
	}

	public static function set( string $key, $value, int $expiration = 0 ): bool {
		if ( ! Settings::get( 'enable_transient_cache', true ) ) {
			return false;
		}
		
		return set_transient( 'pw_' . $key, $value, $expiration );
	}

	public static function delete( string $key ): bool {
		return delete_transient( 'pw_' . $key );
	}

	public static function flush_group( string $group ): void {
		global $wpdb;
		$prefix = '_transient_pw_' . $group;
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", $prefix . '%' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", '_transient_timeout_pw_' . $group . '%' ) );
	}
}
