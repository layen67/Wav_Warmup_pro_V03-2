<?php

declare(strict_types=1);

namespace PostalWarmup\Services;

use PostalWarmup\Models\Stats;
use PostalWarmup\Services\EmailNotifications;
use PostalWarmup\Models\Database;
use PostalWarmup\Admin\Settings;



/**
 * Conseiller de Warmup (Analyse de santé)
 */
class WarmupAdvisor {

	public static function init(): void {
		add_action( 'pw_advisor_recommendation', [ __CLASS__, 'handle_recommendation' ], 10, 2 );
	}

	public static function run(): void {
		$servers = Database::get_servers( true ); // Active only

		foreach ( $servers as $server ) {
			self::analyze_server( $server );
		}
	}

	private static function analyze_server( array $server ): void {
		$stats = Stats::get_server_stats( (int)$server['id'], 1 ); // Last 24h
		if ( empty( $stats ) ) return;

		$today = $stats[0] ?? [];
		$sent = (int) ($today['total_sent'] ?? 0);
		$errors = (int) ($today['total_errors'] ?? 0);

		if ( $sent > 50 ) {
			$error_rate = ($errors / $sent) * 100;

			// Rule 1: Critical Failure Rate
			if ( $error_rate > 10 ) {
				do_action( 'pw_advisor_recommendation', $server, 'pause_critical' );
			}
			// Rule 2: High Failure Rate
			elseif ( $error_rate > 5 ) {
				do_action( 'pw_advisor_recommendation', $server, 'reduce_volume' );
			}
		}
	}

	public static function handle_recommendation( array $server, string $type ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_servers';
		$server_id = (int) $server['id'];

		switch ( $type ) {
			case 'pause_critical':
				// Auto-pause
				$wpdb->update( $table, [ 'active' => 0 ], [ 'id' => $server_id ] );
				Logger::critical( "Advisor: Serveur {$server['domain']} mis en pause (Taux d'erreur critique)", [ 'server_id' => $server_id ] );
				EmailNotifications::send_alert(
					"Serveur {$server['domain']} mis en pause",
					"Le Warmup Advisor a détecté un taux d'erreur critique (>10%). Le serveur a été désactivé par sécurité."
				);
				break;

			case 'reduce_volume':
				// Reduce warmup day
				$current_day = (int) $server['warmup_day'];
				$new_day = max( 1, $current_day - 2 );
				$wpdb->update( $table, [ 'warmup_day' => $new_day ], [ 'id' => $server_id ] );
				Logger::warning( "Advisor: Réduction volume pour {$server['domain']} (Day $current_day -> $new_day)", [ 'server_id' => $server_id ] );
				break;
		}
	}
}
