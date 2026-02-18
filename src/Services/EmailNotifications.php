<?php

declare(strict_types=1);

namespace PostalWarmup\Services;

use PostalWarmup\Admin\Settings;



/**
 * Service de notifications email
 */
class EmailNotifications {

	public static function send_daily_report(): void {
		if ( ! Settings::get( 'notify_daily_report', false ) ) {
			return;
		}
		
		$email = Settings::get( 'notify_email' );
		if ( ! is_email( $email ) ) return;
		
		$stats = \PostalWarmup\Models\Stats::get_dashboard_stats();
		
		$subject = sprintf( '[Postal Warmup] Rapport Quotidien - %s', date( 'd/m/Y' ) );
		$message = sprintf(
			"Bonjour,\n\nVoici le résumé de l'activité d'hier :\n\n- Emails envoyés : %d\n- Taux de succès : %s%%\n- Serveurs actifs : %d\n\nCordialement,\nL'équipe Postal Warmup",
			$stats['sent_today'] ?? 0,
			$stats['success_rate'] ?? 0,
			$stats['active_servers'] ?? 0
		);
		
		wp_mail( $email, $subject, $message );
	}

	public static function send_alert( string $title, string $message ): void {
		if ( ! Settings::get( 'notify_on_error', true ) ) {
			return;
		}
		
		$email = Settings::get( 'notify_email' );
		if ( ! is_email( $email ) ) return;
		
		wp_mail( $email, '[Postal Warmup Alert] ' . $title, $message );
	}

	public static function send_stuck_queue_alert( int $delay ): void {
		self::send_alert(
			'File d\'attente bloquée',
			"Le système a détecté un taux d'échec anormalement élevé. La file d'attente a été mise en pause pour $delay minutes."
		);
	}
}
