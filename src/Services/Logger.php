<?php

declare(strict_types=1);

namespace PostalWarmup\Services;

use PostalWarmup\Models\Database;
use PostalWarmup\Admin\Settings;



/**
 * Service de logging centralisé
 */
class Logger {

	public static function info( string $message, array $context = [] ): void {
		self::log( 'INFO', $message, $context );
	}

	public static function warning( string $message, array $context = [] ): void {
		self::log( 'WARNING', $message, $context );
	}

	public static function error( string $message, array $context = [] ): void {
		self::log( 'ERROR', $message, $context );
	}

	public static function critical( string $message, array $context = [] ): void {
		self::log( 'CRITICAL', $message, $context );
	}

	public static function debug( string $message, array $context = [] ): void {
		if ( Settings::get( 'debug_mode', false ) ) {
			self::log( 'DEBUG', $message, $context );
		}
	}

	public static function log( string $level, string $message, array $context = [] ): void {
		// Filter sensitive data
		$context = self::sanitize_context( $context );

		$mode = Settings::get( 'log_mode', 'file' );

		// 1. File Log
		if ( $mode === 'file' || $mode === 'both' || $mode === 'error_db' ) {
			// Ensure directory exists
			$log_dir = WP_CONTENT_DIR . '/uploads/postal-warmup-logs';
			if ( ! file_exists( $log_dir ) ) {
				wp_mkdir_p( $log_dir );
				// Protect directory
				file_put_contents( $log_dir . '/.htaccess', 'deny from all' );
				file_put_contents( $log_dir . '/index.php', '<?php // Silence is golden' );
			}
			
			$file = $log_dir . '/pw-' . date('Y-m-d') . '.log';
			$line = sprintf( "[%s] [%s] %s %s\n", date('H:i:s'), $level, $message, !empty($context) ? json_encode($context) : '' );
			
			// Append (file_put_contents handles locking if needed, usually atomic enough for logs)
			file_put_contents( $file, $line, FILE_APPEND );
		}

		// 2. DB Log
		if ( $mode === 'db' || $mode === 'both' || ( $mode === 'error_db' && in_array( $level, ['ERROR', 'CRITICAL'] ) ) ) {
			Database::insert_log([
				'server_id' => $context['server_id'] ?? 0,
				'level'     => $level,
				'message'   => $message,
				'context'   => $context, // insert_log handles json_encode
				'email_to'  => $context['email_to'] ?? null,
				'template_used' => $context['template'] ?? null,
				'response_time' => $context['response_time'] ?? null
			]);
		}
	}

	private static function sanitize_context( array $context ): array {
		if ( Settings::get( 'log_sensitive_data' ) === 'none' ) {
			return []; // Strip everything
		}
		
		// Mask known sensitive keys
		$sensitive_keys = ['api_key', 'password', 'token', 'secret'];
		foreach ( $context as $key => $value ) {
			if ( in_array( strtolower( $key ), $sensitive_keys ) ) {
				$context[$key] = '***MASKED***';
			} elseif ( is_array( $value ) ) {
				$context[$key] = self::sanitize_context( $value );
			}
		}
		
		return $context;
	}

	public static function cleanup_old_logs(): void {
		// File Cleanup
		$days = (int) Settings::get( 'auto_purge_logs_days', 30 );
		$log_dir = WP_CONTENT_DIR . '/uploads/postal-warmup-logs';
		
		if ( is_dir( $log_dir ) ) {
			$files = glob( $log_dir . '/pw-*.log' );
			$now = time();

			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					if ( $now - filemtime( $file ) >= $days * 24 * 60 * 60 ) {
						unlink( $file );
					}
				}
			}
		}

		// DB Cleanup (Logs table)
		global $wpdb;
		$table = $wpdb->prefix . 'postal_logs';
		$date_limit = date( 'Y-m-d H:i:s', strtotime( "-$days days" ) );
		
		// Use LIMIT to avoid locking heavy table
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE created_at < %s LIMIT 1000", $date_limit ) );
	}
	
	public static function clear_all_logs(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_logs';
		$wpdb->query( "TRUNCATE TABLE $table" );

		// Clear files too
		$log_dir = WP_CONTENT_DIR . '/uploads/postal-warmup-logs';
		if ( is_dir( $log_dir ) ) {
			$files = glob( $log_dir . '/pw-*.log' );
			foreach ( $files as $file ) {
				unlink( $file );
			}
		}
	}
}
