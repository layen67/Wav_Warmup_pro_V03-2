<?php
// src/Admin/Settings.php

declare(strict_types=1);

namespace PostalWarmup\Admin;

/**
 * Handles plugin settings.
 */
class Settings {

	private static array $options = [];

	public static function init(): void {
		// Register settings
		register_setting( 'postal_warmup_options', 'postal_warmup_options' );
	}

	/**
	 * Get an option value.
	 *
	 * @param string $key The option key (without prefix)
	 * @param mixed $default Default value
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		if ( empty( self::$options ) ) {
			self::$options = get_option( 'postal_warmup_options', [] ) ?: [];
		}

		// Legacy support: check single options if not in array
		if ( ! array_key_exists( $key, self::$options ) ) {
			$legacy = get_option( 'pw_' . $key );
			if ( $legacy !== false ) {
				return $legacy;
			}
			// Special handling for some keys that might be directly in wp_options without pw_ prefix (unlikely but safe)
			return $default;
		}

		return self::$options[ $key ];
	}

	/**
	 * Get all defaults.
	 */
	public static function get_defaults(): array {
		return [
			'enable_logging' => true,
			'log_retention_days' => 30,
			'max_daily_limit' => 500,
			'default_scenario_id' => 0, // 0 = None
			'reply_rate_percentage' => 30, // Default 30% reply rate simulation
			'min_reply_delay' => 60, // Minutes
			'max_reply_delay' => 1440, // Minutes
			'api_timeout' => 15,
			'auto_cleanup_debug_files' => true,
		];
	}

	public static function render_page(): void {
		// Render the settings page HTML
		// ... (Implementation handled in partials usually, this might just be the helper)
	}
}
