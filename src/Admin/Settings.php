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
		// Used for static calls if needed
	}

	/**
	 * Register settings with WordPress.
	 * Called by Plugin via admin_init hook.
	 */
	public function register_settings(): void {
		register_setting(
			'postal_warmup_options',
			'postal_warmup_options',
			[
				'sanitize_callback' => [ $this, 'sanitize_settings' ]
			]
		);
	}

	/**
	 * Sanitize and validate settings input.
	 *
	 * @param array $input The raw input array.
	 * @return array The sanitized array.
	 */
	public function sanitize_settings( $input ): array {
		if ( ! is_array( $input ) ) {
			return self::get_defaults();
		}

		$output = [];
		$defaults = self::get_defaults();

		foreach ( $defaults as $key => $default ) {
			if ( ! isset( $input[ $key ] ) ) {
				$output[ $key ] = $default;
				continue;
			}

			$value = $input[ $key ];

			switch ( $key ) {
				case 'enable_logging':
				case 'auto_cleanup_debug_files':
					$output[ $key ] = ! empty( $value );
					break;
				case 'log_retention_days':
				case 'max_daily_limit':
				case 'default_scenario_id':
				case 'reply_rate_percentage':
				case 'min_reply_delay':
				case 'max_reply_delay':
				case 'api_timeout':
					$output[ $key ] = absint( $value );
					break;
				default:
					$output[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		// Handle unknown keys gracefully or drop them
		// For now, let's keep only known keys for strictness
		return $output;
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

		// Check array first
		if ( array_key_exists( $key, self::$options ) ) {
			return self::$options[ $key ];
		}

		// Legacy support: check single options if not in array
		$legacy = get_option( 'pw_' . $key );
		if ( $legacy !== false ) {
			return $legacy;
		}

		// Fallback to default
		if ( $default !== null ) {
			return $default;
		}

		// Check internal defaults
		$defaults = self::get_defaults();
		return $defaults[ $key ] ?? null;
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
			'required_capability' => 'manage_options',
			'ui_color_primary' => '#2271b1',
			'ui_color_success' => '#00a32a',
			'ui_color_warning' => '#dba617',
			'ui_color_danger' => '#d63638',
			'ui_dark_mode' => 'auto',
			'table_density' => 'normal',
			'dashboard_refresh' => 30,
			'warmup_start' => 10,
			'warmup_max' => 1000,
			'warmup_mode' => 'linear',
			'warmup_increase_percent' => 20,
			'pause_bounce_rate' => 5,
			'pause_spam_rate' => 1,
			'stats_retention_days' => 90,
			'default_from_name' => '',
			'custom_headers' => '',
			'nonce_expiration' => 12,
		];
	}

	public static function render_page(): void {
		// Usually handled by partials/settings.php via Admin class
	}
}
