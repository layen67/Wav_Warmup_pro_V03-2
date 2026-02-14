<?php

namespace PostalWarmup\Admin;

class Settings {

	private $option_name = 'pw_settings';

	// Default Settings Configuration
	private $defaults = [
		// General
		'global_tag' => 'warmup',
		'disable_ip_logging' => false,
		'enable_logging' => true,

		// Security
		'webhook_strict_mode' => true,
		'webhook_secret' => '',
		'domscan_api_key' => '',
		'webhook_rate_limit_minute' => 100,
		'webhook_rate_limit_hour' => 2000,
		'webhook_ip_whitelist' => '',
		'webhook_invalid_signature_action' => 'log', // reject, log, notify
		'nonce_expiration' => 12,
		'required_capability' => 'manage_options',
		'mask_api_keys_logs' => true,
		'mask_api_keys_ui' => true,
		'log_sensitive_data' => 'masked', // full, masked, none
		'auto_cleanup_debug_files' => true,

		// Queue
		'queue_batch_size' => 20,
		'queue_interval' => 5,
		'max_queue_workers' => 1,
		'queue_locking_enabled' => true,
		'queue_lock_timeout' => 60,
		'max_retries' => 3,
		'retry_strategy' => 'fixed', // fixed, exponential, linear
		'retry_delay_base' => 60,
		'retry_delay_max' => 900,
		'cron_method' => 'wp_cron',
		'queue_pause_threshold' => 50, // % failure rate
		'queue_resume_delay' => 30, // minutes

		// Warmup
		'warmup_mode' => 'linear',
		'warmup_start' => 10,
		'warmup_max' => 1000,
		'warmup_days' => 30,
		'warmup_increase_percent' => 20,
		'pause_bounce_rate' => 5,
		'pause_spam_rate' => 1,
		'pause_failure_rate' => 10,

		// Performance
		'enable_transient_cache' => true,
		'cache_backend' => 'auto', // auto, transient, redis
		'cache_ttl_server' => 300,
		'cache_ttl_stats' => 600,
		'cache_ttl_api' => 300,
		'auto_purge_queue_days' => 90,
		'auto_purge_logs_days' => 30,
		'db_purge_schedule' => 'daily', // daily, weekly
		'db_optimize_on_purge' => true,
		'log_max_file_size' => 10,
		'log_auto_purge_deactivation' => false,
		'api_timeout' => 15,
		'db_query_limit' => 500,
		'db_transactions' => true,
		'assets_load_optimization' => true,

		// Interface
		'dashboard_refresh' => 30,
		'default_sort_column' => 'sent_count',
		'default_sort_order' => 'DESC',
		'default_rows_per_page' => 25,
		'color_theme' => 'blue',
		'enable_animations' => true,

		// Notifications
		'notify_email' => '',
		'notify_on_error' => true,
		'notify_daily_report' => false,
		'notify_stuck_queue' => true,
		'notify_api_error' => true,

		// Advanced
		'log_mode' => 'file',
		'log_level' => 'warning',
		'encryption_method' => 'aes-256-cbc',
	];

	public function register_settings() {
		// Register the single array option
		register_setting(
			'postal-warmup-settings',
			$this->option_name,
			[ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ]
		);

		// Migration: If pw_settings is empty, try to fill from old options
		if ( false === get_option( $this->option_name ) ) {
			$this->migrate_old_options();
		}

		// Register Sections & Fields based on active Tab
		$this->register_all_sections();
	}

	private function migrate_old_options() {
		$new = $this->defaults;
		
		// Map old keys to new keys
		$map = [
			'pw_global_tag' => 'global_tag',
			'pw_enable_logging' => 'enable_logging',
			'pw_disable_ip_logging' => 'disable_ip_logging',
			'pw_queue_batch_size' => 'queue_batch_size',
			'pw_log_retention_days' => 'auto_purge_logs_days',
			'pw_queue_retention_days' => 'auto_purge_queue_days',
			'pw_stats_retention_days' => 'stats_retention_days',
			'pw_daily_limit' => 'daily_limit_global',
			'pw_rate_limit_per_hour' => 'hourly_limit_global',
			'pw_max_retries' => 'max_retries',
			'pw_notification_email' => 'notify_email',
			'pw_notify_on_error' => 'notify_on_error',
			'pw_daily_report' => 'notify_daily_report',
			'pw_log_mode' => 'log_mode',
		];

		foreach ( $map as $old => $new_key ) {
			$val = get_option( $old );
			if ( $val !== false ) {
				$new[$new_key] = $val;
			}
		}

		update_option( $this->option_name, $new );
	}

	public function sanitize_settings( $input ) {
		$output = get_option( $this->option_name, $this->defaults );
		if ( ! is_array( $output ) ) $output = $this->defaults;

		// We assume all boolean fields from the active tab MUST be present in $input if they are checked.
		// If they are missing, it means they were unchecked (for that tab).
		// Problem: We don't know which tab was submitted just from $input.
		// Solution: Check if at least one field from a tab is present, then assume that tab was submitted.
		// Or better: Use a hidden field 'pw_settings_tab' in the form.
		// But register_setting callback only gets the values.

		// Alternative: Iterate over defaults. If default is boolean AND we can infer we are saving settings (always true here),
		// we check if the key is missing. BUT we must be careful about partial updates.
		// WP Settings API sends the whole array for the option group usually? No, only fields on page.
		// Wait, 'pw_settings' is a single array option.
		// The form sends `pw_settings[key]`.
		// If I'm on Tab A, `pw_settings[field_B]` is NOT sent.
		// So `isset($input['field_B'])` is false. If I set it to false, I overwrite Tab B settings.
		// CRITICAL FIX: We need to know which fields were present on the screen.
		// Workaround: We will rely on type checking.
		// If a key is present, we update it.
		// For checkboxes, we need a hidden field for each checkbox or a hidden list of fields.
		// Standard WP way: Hidden input with same name before checkbox? No, array keys overwrite.
		// Let's implement the "hidden field with list of keys" approach in render logic, OR simpler:
		// Since we are rebuilding $output from existing options, we only update keys that are in $input?
		// NO, that's exactly the bug: unchecked checkboxes are NOT in $input.

		// Fix: In our form (partials/settings.php), we are using do_settings_sections.
		// We can add a hidden field `pw_settings[_submitted]` with a dummy value to verify submission? No.

		// Let's look at `admin/partials/settings.php`. We can add a hidden field there?
		// Actually, standard practice for array options with checkboxes is tricky.
		// We will modify `render_field` to include a hidden input for checkboxes with value '0'.
		// This way, if unchecked, '0' is sent. If checked, '1' overwrites '0'.

		foreach ( $this->defaults as $key => $default ) {
			if ( isset( $input[$key] ) ) {
				$type = gettype( $default );
				if ( $type === 'integer' ) {
					$output[$key] = absint( $input[$key] );
				} elseif ( $type === 'boolean' ) {
					$output[$key] = (bool) $input[$key];
				} else {
					$output[$key] = sanitize_text_field( $input[$key] );
				}
			}
		}

		return $output;
	}

	private function register_all_sections() {
		$tabs = $this->get_tabs_config();

		foreach ( $tabs as $tab_id => $tab ) {
			// Use a unique page slug for each tab
			$page_slug = 'postal-warmup-settings-' . $tab_id;

			add_settings_section(
				'pw_section_' . $tab_id,
				$tab['label'],
				[ $this, 'section_callback' ],
				$page_slug
			);

			foreach ( $tab['fields'] as $field_id => $field ) {
				add_settings_field(
					$field_id,
					$field['label'],
					[ $this, 'render_field' ],
					$page_slug,
					'pw_section_' . $tab_id,
					[
						'id' => $field_id,
						'type' => $field['type'],
						'options' => $field['options'] ?? [],
						'desc' => $field['desc'] ?? '',
						'label_for' => 'pw_settings[' . $field_id . ']' // Accessibility
					]
				);
			}
		}
	}

	public function get_tabs_config() {
		return [
			'general' => [
				'label' => __( 'Général', 'postal-warmup' ),
				'fields' => [
					'global_tag' => [ 'label' => __( 'Tag Global', 'postal-warmup' ), 'type' => 'text', 'desc' => __( 'Tag ajouté à tous les emails.', 'postal-warmup' ) ],
					'enable_logging' => [ 'label' => __( 'Activer les Logs', 'postal-warmup' ), 'type' => 'checkbox' ],
					'disable_ip_logging' => [ 'label' => __( 'Désactiver IP Log', 'postal-warmup' ), 'type' => 'checkbox', 'desc' => __( 'Conformité RGPD', 'postal-warmup' ) ],
				]
			],
			'security' => [
				'label' => __( 'Sécurité', 'postal-warmup' ),
				'fields' => [
					'webhook_strict_mode' => [ 'label' => __( 'Webhook Strict Mode', 'postal-warmup' ), 'type' => 'checkbox' ],
					'webhook_ip_whitelist' => [ 'label' => __( 'IP Whitelist (Webhooks)', 'postal-warmup' ), 'type' => 'textarea', 'desc' => __( 'Une IP/CIDR par ligne.', 'postal-warmup' ) ],
					'webhook_rate_limit_minute' => [ 'label' => __( 'Rate Limit (Minute)', 'postal-warmup' ), 'type' => 'number' ],
					'webhook_rate_limit_hour' => [ 'label' => __( 'Rate Limit (Heure)', 'postal-warmup' ), 'type' => 'number' ],
					'webhook_invalid_signature_action' => [
						'label' => __( 'Action Signature Invalide', 'postal-warmup' ),
						'type' => 'select',
						'options' => [ 'reject' => 'Rejeter (Silencieux)', 'log' => 'Logger', 'notify' => 'Notifier Admin' ]
					],
					'nonce_expiration' => [ 'label' => __( 'Expiration Nonce (h)', 'postal-warmup' ), 'type' => 'number' ],
					'mask_api_keys_ui' => [ 'label' => __( 'Masquer API Keys (UI)', 'postal-warmup' ), 'type' => 'checkbox' ],
					'required_capability' => [
						'label' => __( 'Permission Requise', 'postal-warmup' ),
						'type' => 'select',
						'options' => [ 'manage_options' => 'Admins', 'manage_postal_warmup' => 'Warmup Managers' ]
					],
					'auto_cleanup_debug_files' => [ 'label' => __( 'Auto-cleanup Debug Files', 'postal-warmup' ), 'type' => 'checkbox', 'desc' => __( 'Supprime les fichiers sensibles à l\'activation.', 'postal-warmup' ) ],
					'domscan_api_key' => [ 'label' => __( 'Clé API DomScan', 'postal-warmup' ), 'type' => 'text', 'desc' => __( 'Pour les audits de domaine.', 'postal-warmup' ) ],
				]
			],
			'queue' => [
				'label' => __( 'File d\'attente', 'postal-warmup' ),
				'fields' => [
					'queue_batch_size' => [ 'label' => __( 'Taille du lot', 'postal-warmup' ), 'type' => 'number' ],
					'queue_interval' => [ 'label' => __( 'Intervalle (min)', 'postal-warmup' ), 'type' => 'number' ],
					'queue_locking_enabled' => [ 'label' => __( 'Verrouillage Queue', 'postal-warmup' ), 'type' => 'checkbox' ],
					'queue_lock_timeout' => [ 'label' => __( 'Timeout Verrou (s)', 'postal-warmup' ), 'type' => 'number' ],
					'max_retries' => [ 'label' => __( 'Max Tentatives', 'postal-warmup' ), 'type' => 'number' ],
					'retry_strategy' => [
						'label' => __( 'Stratégie Retry', 'postal-warmup' ),
						'type' => 'select',
						'options' => [ 'fixed' => 'Fixe', 'exponential' => 'Exponentielle', 'linear' => 'Linéaire' ]
					],
					'queue_pause_threshold' => [ 'label' => __( 'Seuil Pause Auto (%)', 'postal-warmup' ), 'type' => 'number', 'desc' => __( 'Si > X% d\'échecs.', 'postal-warmup' ) ],
					'queue_resume_delay' => [ 'label' => __( 'Délai Reprise (min)', 'postal-warmup' ), 'type' => 'number' ],
				]
			],
			'performance' => [
				'label' => __( 'Performance', 'postal-warmup' ),
				'fields' => [
					'enable_transient_cache' => [ 'label' => __( 'Activer Cache', 'postal-warmup' ), 'type' => 'checkbox' ],
					'cache_backend' => [
						'label' => __( 'Backend Cache', 'postal-warmup' ),
						'type' => 'select',
						'options' => [ 'auto' => 'Auto', 'transient' => 'Transients WP', 'redis' => 'Redis/Memcached' ]
					],
					'cache_ttl_api' => [ 'label' => __( 'TTL Cache API (s)', 'postal-warmup' ), 'type' => 'number' ],
					'db_purge_schedule' => [
						'label' => __( 'Fréquence Nettoyage', 'postal-warmup' ),
						'type' => 'select',
						'options' => [ 'daily' => 'Quotidien', 'weekly' => 'Hebdomadaire' ]
					],
					'db_optimize_on_purge' => [ 'label' => __( 'Optimiser Tables', 'postal-warmup' ), 'type' => 'checkbox', 'desc' => __( 'Run OPTIMIZE TABLE after purge.', 'postal-warmup' ) ],
					'assets_load_optimization' => [ 'label' => __( 'Optimiser Assets', 'postal-warmup' ), 'type' => 'checkbox', 'desc' => __( 'Charger CSS/JS uniquement sur les pages du plugin.', 'postal-warmup' ) ],
					'db_query_limit' => [ 'label' => __( 'Limite Résultats DB', 'postal-warmup' ), 'type' => 'number', 'desc' => __( 'Max rows per query', 'postal-warmup' ) ],
					'api_timeout' => [ 'label' => __( 'API Timeout (sec)', 'postal-warmup' ), 'type' => 'number' ],
					'db_transactions' => [ 'label' => __( 'DB Transactions', 'postal-warmup' ), 'type' => 'checkbox', 'desc' => __( 'Recommandé pour la concurrence.', 'postal-warmup' ) ],
				]
			],
			'interface' => [
				'label' => __( 'Interface', 'postal-warmup' ),
				'fields' => [
					'default_sort_column' => [
						'label' => __( 'Tri par défaut', 'postal-warmup' ),
						'type' => 'select',
						'options' => [ 'id' => 'ID', 'domain' => 'Domaine', 'sent_count' => 'Envois', 'success_count' => 'Succès' ]
					],
					'default_sort_order' => [
						'label' => __( 'Ordre par défaut', 'postal-warmup' ),
						'type' => 'select',
						'options' => [ 'ASC' => 'Croissant', 'DESC' => 'Décroissant' ]
					],
					'dashboard_refresh' => [ 'label' => __( 'Rafraîchissement Dashboard (s)', 'postal-warmup' ), 'type' => 'number' ],
				]
			],
			'notifications' => [
				'label' => __( 'Notifications', 'postal-warmup' ),
				'fields' => [
					'notify_email' => [ 'label' => __( 'Email Notifications', 'postal-warmup' ), 'type' => 'email' ],
					'notify_on_error' => [ 'label' => __( 'Alerte Erreurs', 'postal-warmup' ), 'type' => 'checkbox' ],
				]
			],
		];
	}

	public function section_callback( $args ) {
		// echo '<p>Description for section</p>';
	}

	public function render_field( $args ) {
		$options = get_option( $this->option_name, $this->defaults );
		if(!is_array($options)) $options = $this->defaults;

		$id = $args['id'];
		$value = isset( $options[$id] ) ? $options[$id] : ( $this->defaults[$id] ?? '' );
		$name = $this->option_name . '[' . $id . ']';

		switch ( $args['type'] ) {
			case 'text':
			case 'email':
			case 'number':
				echo '<input type="' . $args['type'] . '" name="' . $name . '" value="' . esc_attr( $value ) . '" class="regular-text">';
				break;
			case 'textarea':
				echo '<textarea name="' . $name . '" rows="5" cols="50" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
				break;
			case 'checkbox':
				// Add hidden field to ensure unchecked value is sent (overwritten by checkbox if checked)
				echo '<input type="hidden" name="' . $name . '" value="0">';
				echo '<input type="checkbox" name="' . $name . '" value="1" ' . checked( $value, true, false ) . '>';
				break;
			case 'select':
				echo '<select name="' . $name . '">';
				foreach ( $args['options'] as $opt_val => $opt_label ) {
					echo '<option value="' . esc_attr( $opt_val ) . '" ' . selected( $value, $opt_val, false ) . '>' . esc_html( $opt_label ) . '</option>';
				}
				echo '</select>';
				break;
		}

		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	public static function get( $key, $default_override = null ) {
		$options = get_option( 'pw_settings' );

		if ( $options && isset( $options[$key] ) ) {
			return $options[$key];
		}

		// Fallbacks for critical values if DB is empty/corrupt
		$defaults = [
			'queue_batch_size' => 20,
			'db_query_limit' => 500,
			'default_sort_column' => 'sent_count',
			'default_sort_order' => 'DESC',
			'api_timeout' => 15,
		];

		return $default_override ?? ($defaults[$key] ?? null);
	}
}
