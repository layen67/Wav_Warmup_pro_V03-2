<?php

declare(strict_types=1);

namespace PostalWarmup\Core;



/**
 * Fired during plugin deactivation.
 */
class Deactivator {

	public static function deactivate(): void {
		self::unschedule_cron_jobs();
		flush_rewrite_rules();
	}

	private static function unschedule_cron_jobs(): void {
		$crons = [
			'pw_process_queue',
			'pw_warmup_daily_increment',
			'pw_daily_report',
			'pw_cleanup_old_logs',
			'pw_cleanup_old_stats',
			'pw_daily_stats_aggregation',
			'pw_cleanup_queue',
			'pw_advisor_check'
		];

		foreach ( $crons as $cron ) {
			$timestamp = wp_next_scheduled( $cron );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $cron );
			}
		}
	}
}
