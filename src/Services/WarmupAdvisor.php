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

	public static function init() {
		add_action( 'pw_advisor_recommendation', [ __CLASS__, 'handle_recommendation' ], 10, 2 );
	}

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

	/**
	 * Gère les recommandations émises par l'Advisor
	 */
	public static function handle_recommendation( $server_id, $data ) {
		$action = $data['action'] ?? '';
		$reason = $data['reason'] ?? '';
		$isp    = $data['isp'] ?? 'General';

		$server = Database::get_server( $server_id );
		if ( ! $server ) return;

		// 1. Envoyer une alerte (si activé)
		if ( get_option( 'pw_notify_on_error', true ) ) {
			$to = get_option( 'pw_notification_email', get_option( 'admin_email' ) );
			$subject = "[Warmup Alert] Action requise pour {$server['domain']}";
			$message = "L'Advisor a détecté un problème sur le serveur {$server['domain']} (ISP: $isp).\n\n";
			$message .= "Raison : $reason\n";
			$message .= "Action recommandée : $action\n\n";
			$message .= "Connectez-vous pour vérifier : " . admin_url( 'admin.php?page=postal-warmup' );

			wp_mail( $to, $subject, $message );
		}

		// 2. Appliquer l'action
		switch ( $action ) {
			case 'stop_immediate':
			case 'pause_24h':
				Logger::critical( "Advisor: Mise en pause automatique de {$server['domain']}", [ 'reason' => $reason ] );
				// Désactiver le serveur
				global $wpdb;
				$table = $wpdb->prefix . 'postal_servers';
				$wpdb->update( $table, [ 'active' => 0 ], [ 'id' => $server_id ] );
				break;

			case 'reduce_growth':
				Logger::warning( "Advisor: Réduction de croissance pour {$server['domain']}", [ 'reason' => $reason ] );
				// Reculer le jour de warmup de 1 ou 2 jours pour ralentir
				global $wpdb;
				$table = $wpdb->prefix . 'postal_servers';
				$current_day = (int) $server['warmup_day'];
				$new_day = max( 1, $current_day - 2 );
				$wpdb->update( $table, [ 'warmup_day' => $new_day ], [ 'id' => $server_id ] );
				break;
		}
	}
}
