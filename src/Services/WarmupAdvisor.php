<?php

namespace PostalWarmup\Services;

use PostalWarmup\Models\Database;
use PostalWarmup\Models\Stats;
use PostalWarmup\Models\Strategy;
use PostalWarmup\Admin\ISPManager;

/**
 * Service de conseil et de surveillance du warmup
 * (Répond à l'exigence d'audit: pw_advisor_recommendation)
 */
class WarmupAdvisor {

	/**
	 * Exécute l'analyse et déclenche les recommandations
	 */
	public static function run() {
		// Log start only in debug mode to avoid spamming
		// Logger::debug( 'WarmupAdvisor: Début de l\'analyse...' );

		$servers = Database::get_servers();

		foreach ( $servers as $server ) {
			if ( empty($server['active']) ) continue;
			self::analyze_server( $server );
		}
	}

	private static function analyze_server( $server ) {
		$server_id = $server['id'];

		// 1. Récupérer les stats par ISP depuis la table de tracking V3
		global $wpdb;
		$table_isp = $wpdb->prefix . 'postal_server_isp_stats';

		// On récupère uniquement les ISPs qui ont eu de l'activité aujourd'hui ou qui sont trackés
		$isp_stats = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_isp WHERE server_id = %d", $server_id ), ARRAY_A );

		if ( empty( $isp_stats ) ) {
			return;
		}

		foreach ( $isp_stats as $stat ) {
			$isp_key = $stat['isp_key'];

			// Get Strategy for this ISP
			$strategy_id = null;
			$isp_config = ISPManager::get_by_key( $isp_key );
			if ( $isp_config && ! empty( $isp_config['strategy_id'] ) ) {
				$strategy_id = $isp_config['strategy_id'];
			}

			if ( ! $strategy_id ) continue;

			$strategy = Strategy::get( $strategy_id );
			if ( ! $strategy ) continue;

			// Prepare stats for Engine
			$engine_stats = [
				'sent_today' => (int) $stat['sent_today'],
				'fails_today' => (int) $stat['fails_today'],
			];

			// Check Rules
			$result = StrategyEngine::check_safety_rules( $strategy, $engine_stats );

			if ( isset( $result['allowed'] ) && $result['allowed'] === false ) {
				$action = $result['action'] ?? 'unknown';
				$reason = $result['reason'] ?? 'Safety rule violation';

				Logger::warning( "WarmupAdvisor: Recommendation pour Server {$server['domain']} (ISP: $isp_key) -> $action ($reason)" );

				// Hook audit
				do_action( 'pw_advisor_recommendation', $server_id, [
					'isp' => $isp_key,
					'action' => $action,
					'reason' => $reason,
					'timestamp' => current_time('mysql')
				] );
			}
		}
	}
}
