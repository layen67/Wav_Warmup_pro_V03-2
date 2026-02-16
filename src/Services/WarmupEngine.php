<?php

namespace PostalWarmup\Services;

use PostalWarmup\Models\Database;
use PostalWarmup\Models\Stats;
use PostalWarmup\Admin\Settings;

/**
 * Moteur décisionnel de warmup adaptatif
 */
class WarmupEngine {

	/**
	 * Exécute la logique de progression journalière pour tous les serveurs actifs.
	 * Cette méthode doit être appelée une fois par jour par le CRON.
	 */
	public static function process_daily_advancement() {
		// 1. Récupérer le mode de stratégie
		$mode = Settings::get( 'warmup_strategy_mode', 'smart' );

		// Si mode linéaire (legacy), on utilise l'ancienne logique simplifiée
		if ( $mode === 'linear' ) {
			global $wpdb;
			$table = $wpdb->prefix . 'postal_servers';
			// Only increment for active servers
			$wpdb->query( "UPDATE $table SET warmup_day = warmup_day + 1 WHERE active = 1" );

			// V3: Increment ISP specific warmup days and reset daily counters
			$table_isp = $wpdb->prefix . 'postal_server_isp_stats';
			$wpdb->query( "UPDATE $table_isp SET warmup_day = warmup_day + 1, sent_today = 0, delivered_today = 0, fails_today = 0" );
			return;
		}

		// 2. Mode Smart (Adaptatif)
		$servers = Database::get_servers( true ); // Only active servers

		if ( ! empty( $servers ) ) {
			foreach ( $servers as $server ) {
				self::process_server_advancement( $server );
			}
		}
	}

	/**
	 * Traite un serveur spécifique
	 */
	private static function process_server_advancement( $server ) {
		global $wpdb;
		$server_id = $server['id'];

		// Récupérer tous les ISP configurés dans le système
		$table_isps = $wpdb->prefix . 'postal_isps';
		$isps = $wpdb->get_results( "SELECT isp_key FROM $table_isps WHERE active = 1", ARRAY_A );

		$isp_days = [];
		$table_stats = $wpdb->prefix . 'postal_server_isp_stats';

		if ( empty( $isps ) ) return;

		foreach ( $isps as $isp ) {
			$key = $isp['isp_key'];

			// Récupérer les stats ISP pour ce serveur
			$stats = Stats::get_server_isp_stats( $server_id, $key );

			// Analyser la performance d'hier (qui est stockée dans sent_today avant le reset)
			$sent = (int) $stats->sent_today;
			$fails = (int) $stats->fails_today;

			// Calcul du quota théorique pour ce jour spécifique
			$current_day = (int) $stats->warmup_day;
			$quota = self::get_isp_quota( $server, $key, $current_day );

			$new_day = $current_day;
			$action = 'stagnate'; // default

			// === LOGIQUE DE DECISION ===

			// Calcul du Taux d'Erreur
			$error_rate = ($sent > 0) ? ($fails / $sent) * 100 : 0;

			// 1. Protection Critique (Frein d'urgence)
			// Si taux d'erreur > 3% (et volume significatif > 10 emails)
			if ( $sent > 10 && $error_rate > 3 ) {
				// Recul fort (Minimum Day 1)
				$new_day = max( 1, $current_day - 3 );
				$action = 'retreat_critical';
			}
			// 2. Progression (Avance)
			// Si on a rempli le quota à > 80% ET taux d'erreur < 1%
			elseif ( $quota > 0 && $sent >= ($quota * 0.8) && $error_rate < 1 ) {
				$new_day++;
				$action = 'advance';
			}
			// 3. Régression (Decay) par inactivité
			// Si on a envoyé 0 email alors qu'on est avancé (jour > 5)
			elseif ( $sent === 0 && $current_day > 5 ) {
				// On recule doucement pour ne pas reprendre trop fort
				$new_day = max( 1, $current_day - 1 );
				$action = 'decay';
			}
			// 4. Stagnation (Volume insuffisant ou petites erreurs)
			else {
				// On reste au même jour pour retenter demain
				$action = 'stagnate';
			}

			// Mise à jour en base (Composite Key Update)
			$wpdb->update(
				$table_stats,
				[
					'warmup_day' => $new_day,
					'sent_today' => 0,      // Reset pour le nouveau jour
					'delivered_today' => 0,
					'fails_today' => 0,
					'last_updated' => current_time( 'mysql' )
				],
				[
					'server_id' => $server_id,
					'isp_key'   => $key
				]
			);

			$isp_days[] = $new_day;

			// Log de la décision si changement ou erreur
			if ( Settings::get('enable_logging') && ($action !== 'stagnate' || $error_rate > 0) ) {
				// Utiliser Database::insert_log car Logger n'est peut-être pas dispo ici (static context)
				// Ou mieux, utiliser Logger si autoloadé
				Database::insert_log([
					'server_id' => $server_id,
					'level' => ($action === 'retreat_critical') ? 'warning' : 'info',
					'message' => "Warmup Engine ($key): Action=$action, Day $current_day -> $new_day (Sent: $sent/$quota, Err: " . round($error_rate, 1) . "%)"
				]);
			}
		}

		// Mise à jour du "Jour Global" du serveur (Moyenne arrondie)
		if ( ! empty( $isp_days ) ) {
			$avg_day = (int) round( array_sum( $isp_days ) / count( $isp_days ) );
			$table_servers = $wpdb->prefix . 'postal_servers';
			$wpdb->update(
				$table_servers,
				[ 'warmup_day' => $avg_day ],
				[ 'id' => $server_id ]
			);
		}
	}

	/**
	 * Calcule le quota théorique pour un ISP à un jour donné
	 */
	public static function get_isp_quota( $server, $isp_key, $day ) {
		// Ici on pourrait utiliser une stratégie spécifique par ISP (table postal_isps)
		// Pour l'instant on utilise les settings globaux ou ceux du serveur

		$settings = get_option('pw_warmup_settings', []);
		$start_vol = isset($settings['warmup_start']) ? (int)$settings['warmup_start'] : 10;
		// Use 'growth_rate' from settings or default 30%?
		// Settings class defines 'warmup_increase_percent' in defaults but 'growth_rate' in previous code?
		// Checking Settings.php defaults: 'warmup_increase_percent' => 20
		// Checking Stats.php logic: uses 'growth_rate'.
		// Need to unify. Let's check Settings.php again.

		$growth = isset($settings['warmup_increase_percent']) ? (int)$settings['warmup_increase_percent'] : 20;
		if (isset($settings['growth_rate'])) $growth = (int)$settings['growth_rate']; // Legacy override

		// Formula: Start * (1 + Growth/100)^(Day-1)
		if ($day < 1) $day = 1;

		return floor( $start_vol * pow( 1 + ($growth / 100), $day - 1 ) );
	}
}
