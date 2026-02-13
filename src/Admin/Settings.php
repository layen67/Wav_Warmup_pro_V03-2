<?php

namespace PostalWarmup\Admin;

class Settings {
	public function register_settings() {
		// === Section General ===
		add_settings_section( 'pw_general_section', __( 'Options Générales', 'postal-warmup' ), array( $this, 'general_section_callback' ), 'postal-warmup-settings' );
		register_setting( 'postal-warmup-settings', 'pw_global_tag', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'warmup' ) );
		add_settings_field( 'pw_global_tag', __( 'Tag Postal Global', 'postal-warmup' ), array( $this, 'global_tag_field' ), 'postal-warmup-settings', 'pw_general_section' );

		register_setting( 'postal-warmup-settings', 'pw_disable_ip_logging', array( 'type' => 'boolean', 'default' => false ) );
		add_settings_field( 'pw_disable_ip_logging', __( 'Conformité RGPD', 'postal-warmup' ), array( $this, 'disable_ip_logging_field' ), 'postal-warmup-settings', 'pw_general_section' );

		// === Section Logs ===
		add_settings_section( 'pw_logs_section', __( 'Gestion des Logs', 'postal-warmup' ), array( $this, 'logs_section_callback' ), 'postal-warmup-settings' );
		register_setting( 'postal-warmup-settings', 'pw_enable_logging', array( 'type' => 'boolean', 'default' => true ) );
		register_setting( 'postal-warmup-settings', 'pw_log_mode', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key', 'default' => 'file' ) );
		add_settings_field( 'pw_enable_logging', __( 'Configuration des logs', 'postal-warmup' ), array( $this, 'enable_logging_field' ), 'postal-warmup-settings', 'pw_logs_section' );

		register_setting( 'postal-warmup-settings', 'pw_log_retention_days', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 30 ) );
		add_settings_field( 'pw_log_retention_days', __( 'Rétention des logs (jours)', 'postal-warmup' ), array( $this, 'log_retention_field' ), 'postal-warmup-settings', 'pw_logs_section' );

		register_setting( 'postal-warmup-settings', 'pw_queue_retention_days', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 7 ) );
		add_settings_field( 'pw_queue_retention_days', __( 'Rétention de la file d\'attente (jours)', 'postal-warmup' ), array( $this, 'queue_retention_field' ), 'postal-warmup-settings', 'pw_logs_section' );

		register_setting( 'postal-warmup-settings', 'pw_stats_retention_days', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 90 ) );
		add_settings_field( 'pw_stats_retention_days', __( 'Rétention des statistiques (jours)', 'postal-warmup' ), array( $this, 'stats_retention_field' ), 'postal-warmup-settings', 'pw_logs_section' );
		
		// === Section Statistiques ===
		add_settings_section( 'pw_stats_section', __( 'Statistiques', 'postal-warmup' ), array( $this, 'stats_section_callback' ), 'postal-warmup-settings' );
		register_setting( 'postal-warmup-settings', 'pw_stats_enabled', array( 'type' => 'boolean', 'default' => true ) );
		add_settings_field( 'pw_stats_enabled', __( 'Activer les statistiques', 'postal-warmup' ), array( $this, 'stats_enabled_field' ), 'postal-warmup-settings', 'pw_stats_section' );
		
		// === Section Limites (RESTORED) ===
		add_settings_section( 'pw_limits_section', __( 'Limites d\'envoi', 'postal-warmup' ), array( $this, 'limits_section_callback' ), 'postal-warmup-settings' );
		register_setting( 'postal-warmup-settings', 'pw_daily_limit', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0 ) );
		add_settings_field( 'pw_daily_limit', __( 'Limite quotidienne', 'postal-warmup' ), array( $this, 'daily_limit_field' ), 'postal-warmup-settings', 'pw_limits_section' );
		register_setting( 'postal-warmup-settings', 'pw_rate_limit_per_hour', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0 ) );
		add_settings_field( 'pw_rate_limit_per_hour', __( 'Limite horaire par serveur', 'postal-warmup' ), array( $this, 'rate_limit_field' ), 'postal-warmup-settings', 'pw_limits_section' );
		register_setting( 'postal-warmup-settings', 'pw_max_retries', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 3 ) );
		add_settings_field( 'pw_max_retries', __( 'Nombre de tentatives', 'postal-warmup' ), array( $this, 'max_retries_field' ), 'postal-warmup-settings', 'pw_limits_section' );

		// === Section Notifications (RESTORED) ===
		add_settings_section( 'pw_notifications_section', __( 'Notifications', 'postal-warmup' ), array( $this, 'notifications_section_callback' ), 'postal-warmup-settings' );
		register_setting( 'postal-warmup-settings', 'pw_notification_email', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => get_option( 'admin_email' ) ) );
		add_settings_field( 'pw_notification_email', __( 'Email de notification', 'postal-warmup' ), array( $this, 'notification_email_field' ), 'postal-warmup-settings', 'pw_notifications_section' );

		register_setting( 'postal-warmup-settings', 'pw_notify_on_error', array( 'type' => 'boolean', 'default' => true ) );
		add_settings_field( 'pw_notify_on_error', __( 'Alerter sur erreur', 'postal-warmup' ), array( $this, 'notify_on_error_field' ), 'postal-warmup-settings', 'pw_notifications_section' );

		register_setting( 'postal-warmup-settings', 'pw_daily_report', array( 'type' => 'boolean', 'default' => false ) );
		add_settings_field( 'pw_daily_report', __( 'Rapport quotidien', 'postal-warmup' ), array( $this, 'daily_report_field' ), 'postal-warmup-settings', 'pw_notifications_section' );

		// === Section Performance & Advisor ===
		add_settings_section( 'pw_performance_section', __( 'Performance & Advisor', 'postal-warmup' ), array( $this, 'performance_section_callback' ), 'postal-warmup-settings' );

		register_setting( 'postal-warmup-settings', 'pw_queue_batch_size', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 20 ) );
		add_settings_field( 'pw_queue_batch_size', __( 'Taille du lot (Queue)', 'postal-warmup' ), array( $this, 'queue_batch_size_field' ), 'postal-warmup-settings', 'pw_performance_section' );

		register_setting( 'postal-warmup-settings', 'pw_advisor_enabled', array( 'type' => 'boolean', 'default' => true ) );
		add_settings_field( 'pw_advisor_enabled', __( 'Activer l\'Advisor', 'postal-warmup' ), array( $this, 'advisor_enabled_field' ), 'postal-warmup-settings', 'pw_performance_section' );

		// === Section Webhooks ===
		add_settings_section( 'pw_webhooks_section', __( 'Webhooks', 'postal-warmup' ), array( $this, 'webhooks_section_callback' ), 'postal-warmup-settings' );

		register_setting( 'postal-warmup-settings', 'pw_webhook_enabled', array( 'type' => 'boolean', 'default' => false ) );
		add_settings_field( 'pw_webhook_enabled', __( 'Activer le Webhook', 'postal-warmup' ), array( $this, 'webhook_enabled_field' ), 'postal-warmup-settings', 'pw_webhooks_section' );

		register_setting( 'postal-warmup-settings', 'pw_webhook_url', array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => '' ) );
		add_settings_field( 'pw_webhook_url', __( 'URL du Webhook', 'postal-warmup' ), array( $this, 'webhook_url_field' ), 'postal-warmup-settings', 'pw_webhooks_section' );

		register_setting( 'postal-warmup-settings', 'pw_webhook_events', array( 'type' => 'array', 'sanitize_callback' => array( $this, 'sanitize_webhook_events' ), 'default' => [] ) );
		add_settings_field( 'pw_webhook_events', __( 'Événements', 'postal-warmup' ), array( $this, 'webhook_events_field' ), 'postal-warmup-settings', 'pw_webhooks_section' );
	}

	public function general_section_callback() { echo '<p>' . __( 'Configuration générale des envois.', 'postal-warmup' ) . '</p>'; }
	
	public function global_tag_field() {
		$value = get_option( 'pw_global_tag', 'warmup' );
		echo '<input type="text" name="pw_global_tag" value="' . esc_attr( $value ) . '" class="regular-text">';
		echo '<p class="description">' . __( 'Ce tag sera ajouté à tous les emails envoyés par le plugin pour faciliter le filtrage dans Postal.', 'postal-warmup' ) . '</p>';
	}

	public function disable_ip_logging_field() {
		$value = get_option( 'pw_disable_ip_logging', false );
		echo '<label><input type="checkbox" name="pw_disable_ip_logging" value="1" ' . checked( $value, true, false ) . '> ' . __( 'Désactiver le tracking des adresses IP (Mailto)', 'postal-warmup' ) . '</label>';
		echo '<p class="description">' . __( 'Si désactivé, les IPs ne seront pas stockées lors des clics sur les liens mailto. Sinon, elles seront anonymisées (dernier octet masqué).', 'postal-warmup' ) . '</p>';
	}

	public function logs_section_callback() { echo '<p>' . __( 'Gérez la conservation des logs.', 'postal-warmup' ) . '</p>'; }
	public function stats_section_callback() { echo '<p>' . __( 'Configuration des statistiques.', 'postal-warmup' ) . '</p>'; }
	public function limits_section_callback() { echo '<p>' . __( 'Définissez des limites pour éviter l\'abus.', 'postal-warmup' ) . '</p>'; }
	public function notifications_section_callback() { echo '<p>' . __( 'Configuration des alertes email.', 'postal-warmup' ) . '</p>'; }
	public function performance_section_callback() { echo '<p>' . __( 'Optimisation des performances et surveillance.', 'postal-warmup' ) . '</p>'; }

	public function enable_logging_field() {
		$enabled = get_option( 'pw_enable_logging', true );
		$mode = get_option( 'pw_log_mode', 'file' );
		
		echo '<label><input type="checkbox" name="pw_enable_logging" value="1" ' . checked( $enabled, true, false ) . '> ' . __( 'Activer les logs', 'postal-warmup' ) . '</label><br><br>';
		
		echo '<label for="pw_log_mode">' . __( 'Mode de stockage :', 'postal-warmup' ) . '</label><br>';
		echo '<select name="pw_log_mode" id="pw_log_mode">';
		echo '<option value="file" ' . selected( $mode, 'file', false ) . '>' . __( 'Fichier uniquement (Recommandé)', 'postal-warmup' ) . '</option>';
		echo '<option value="db" ' . selected( $mode, 'db', false ) . '>' . __( 'Base de données uniquement', 'postal-warmup' ) . '</option>';
		echo '<option value="both" ' . selected( $mode, 'both', false ) . '>' . __( 'Les deux (Debug)', 'postal-warmup' ) . '</option>';
		echo '<option value="error_db" ' . selected( $mode, 'error_db', false ) . '>' . __( 'Fichier + BDD (Erreurs seulement)', 'postal-warmup' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . __( 'Pour des performances optimales, utilisez "Fichier uniquement" ou "Fichier + BDD (Erreurs seulement)".', 'postal-warmup' ) . '</p>';
	}

	public function log_retention_field() {
		$value = get_option( 'pw_log_retention_days', 30 );
		echo '<input type="number" name="pw_log_retention_days" value="' . esc_attr( $value ) . '" class="small-text"> ' . __( 'jours', 'postal-warmup' );
	}

	public function queue_retention_field() {
		$value = get_option( 'pw_queue_retention_days', 7 );
		echo '<input type="number" name="pw_queue_retention_days" value="' . esc_attr( $value ) . '" class="small-text"> ' . __( 'jours (Messages envoyés/échoués uniquement)', 'postal-warmup' );
	}
	public function stats_retention_field() {
		$value = get_option( 'pw_stats_retention_days', 90 );
		echo '<input type="number" name="pw_stats_retention_days" value="' . esc_attr( $value ) . '" class="small-text"> ' . __( 'jours', 'postal-warmup' );
	}
	public function stats_enabled_field() {
		$value = get_option( 'pw_stats_enabled', true );
		echo '<input type="checkbox" name="pw_stats_enabled" value="1" ' . checked( $value, true, false ) . '> ' . __( 'Activer', 'postal-warmup' );
	}
	public function daily_limit_field() {
		$value = get_option( 'pw_daily_limit', 0 );
		echo '<input type="number" name="pw_daily_limit" value="' . esc_attr( $value ) . '" class="small-text"> ' . __( '0 = illimité', 'postal-warmup' );
	}
	public function rate_limit_field() {
		$value = get_option( 'pw_rate_limit_per_hour', 0 );
		echo '<input type="number" name="pw_rate_limit_per_hour" value="' . esc_attr( $value ) . '" class="small-text"> ' . __( '0 = illimité', 'postal-warmup' );
	}
	public function max_retries_field() {
		$value = get_option( 'pw_max_retries', 3 );
		echo '<input type="number" name="pw_max_retries" value="' . esc_attr( $value ) . '" class="small-text">';
	}
	public function notification_email_field() {
		$value = get_option( 'pw_notification_email', get_option( 'admin_email' ) );
		echo '<input type="email" name="pw_notification_email" value="' . esc_attr( $value ) . '" class="regular-text">';
	}
	public function notify_on_error_field() {
		$value = get_option( 'pw_notify_on_error', true );
		echo '<label><input type="checkbox" name="pw_notify_on_error" value="1" ' . checked( $value, true, false ) . '> ' . __( 'Recevoir une notification en cas d\'erreur critique', 'postal-warmup' ) . '</label>';
	}
	public function daily_report_field() {
		$value = get_option( 'pw_daily_report', false );
		echo '<label><input type="checkbox" name="pw_daily_report" value="1" ' . checked( $value, true, false ) . '> ' . __( 'Recevoir un rapport quotidien des envois', 'postal-warmup' ) . '</label>';
	}
	public function queue_batch_size_field() {
		$value = get_option( 'pw_queue_batch_size', 20 );
		echo '<input type="number" name="pw_queue_batch_size" value="' . esc_attr( $value ) . '" class="small-text"> ' . __( 'emails par minute (défaut: 20)', 'postal-warmup' );
	}
	public function advisor_enabled_field() {
		$value = get_option( 'pw_advisor_enabled', true );
		echo '<label><input type="checkbox" name="pw_advisor_enabled" value="1" ' . checked( $value, true, false ) . '> ' . __( 'Activer l\'analyse automatique (Conseiller Warmup)', 'postal-warmup' ) . '</label>';
	}

	// === Webhooks Callbacks ===

	public function webhooks_section_callback() { echo '<p>' . __( 'Configurez un webhook pour recevoir des notifications lors des événements d\'envoi.', 'postal-warmup' ) . '</p>'; }

	public function webhook_enabled_field() {
		$value = get_option( 'pw_webhook_enabled', false );
		echo '<label><input type="checkbox" name="pw_webhook_enabled" value="1" ' . checked( $value, true, false ) . '> ' . __( 'Activer l\'envoi de requêtes vers ce webhook', 'postal-warmup' ) . '</label>';
	}

	public function webhook_url_field() {
		$value = get_option( 'pw_webhook_url', '' );
		echo '<div style="display:flex; align-items:center;">';
		echo '<input type="url" name="pw_webhook_url" id="pw_webhook_url" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="https://exemple.com/webhook">';
		echo '<button type="button" class="button" id="pw-test-webhook-btn" style="margin-left:10px;">' . __( 'Tester l\'URL', 'postal-warmup' ) . '</button>';
		echo '</div>';
		echo '<p class="description">' . __( 'L\'URL qui recevra les requêtes POST avec un payload JSON.', 'postal-warmup' ) . '</p>';
		echo '<div id="pw-webhook-test-result" style="margin-top:5px; font-weight:bold;"></div>';
	}

	public function webhook_events_field() {
		$events = get_option( 'pw_webhook_events', [] );
		if ( ! is_array( $events ) ) $events = [];

		$available_events = [
			'MessageSent' => __( 'MessageSent (Succès d\'envoi)', 'postal-warmup' ),
			'MessageDeliveryFailed' => __( 'MessageDeliveryFailed (Échec d\'envoi)', 'postal-warmup' ),
			'MessageDelayed' => __( 'MessageDelayed (Retardé)', 'postal-warmup' ),
			'MessageHeld' => __( 'MessageHeld (Retenu)', 'postal-warmup' ),
			'MessageBounced' => __( 'MessageBounced (Rebond)', 'postal-warmup' ),
			'MessageLinkClicked' => __( 'MessageLinkClicked (Clic)', 'postal-warmup' ),
			'MessageLoaded' => __( 'MessageLoaded (Ouverture)', 'postal-warmup' ),
			'DomainDNSError' => __( 'DomainDNSError (Erreur DNS)', 'postal-warmup' ),
		];

		echo '<fieldset>';
		foreach ( $available_events as $key => $label ) {
			$checked = in_array( $key, $events ) ? 'checked="checked"' : '';
			echo '<label style="display:block; margin-bottom: 5px;">';
			echo '<input type="checkbox" name="pw_webhook_events[]" value="' . esc_attr( $key ) . '" ' . $checked . '> ' . esc_html( $label );
			echo '</label>';
		}
		echo '</fieldset>';
	}

	public function sanitize_webhook_events( $input ) {
		if ( ! is_array( $input ) ) return [];
		return array_map( 'sanitize_text_field', $input );
	}
}
