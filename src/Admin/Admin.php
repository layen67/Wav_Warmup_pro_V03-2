<?php

namespace PostalWarmup\Admin;

use PostalWarmup\Models\Database;
use PostalWarmup\Admin\Settings;

declare(strict_types=1);

/**
 * The admin-specific functionality of the plugin.
 */
class Admin {

	private string $version;

	public function __construct( string $version ) {
		$this->version = $version;
	}

	public function enqueue_styles(): void {
		if ( ! $this->is_plugin_page() ) return;

		wp_enqueue_style( 'postal-warmup-admin', PW_PLUGIN_URL . 'admin/css/admin.css', [], $this->version, 'all' );
		
		// Inject dynamic CSS variables from Settings
		$primary = Settings::get( 'ui_color_primary', '#2271b1' );
		$success = Settings::get( 'ui_color_success', '#00a32a' );
		$warning = Settings::get( 'ui_color_warning', '#dba617' );
		$danger  = Settings::get( 'ui_color_danger', '#d63638' );
		$dark_mode = Settings::get( 'ui_dark_mode', 'auto' );
		$density = Settings::get( 'table_density', 'normal' );

		$custom_css = ":root {
			--pw-primary: $primary;
			--pw-success: $success;
			--pw-warning: $warning;
			--pw-danger: $danger;
			--pw-density: $density;
		}";

		if ( $dark_mode === 'always' ) {
			$custom_css .= " body { background: #1e1e1e; color: #ddd; } .pw-card { background: #2b2b2b; color: #fff; } ";
		}

		wp_add_inline_style( 'postal-warmup-admin', $custom_css );
	}

	public function enqueue_scripts(): void {
		if ( ! $this->is_plugin_page() ) return;

		wp_enqueue_script( 'postal-warmup-admin', PW_PLUGIN_URL . 'admin/js/admin.js', [ 'jquery' ], $this->version, false );
		
		// Chart.js for Dashboard
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'postal-warmup' ) {
			wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true );
		}

		// Templates Manager JS
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'postal-warmup-templates' ) {
			wp_enqueue_script( 'postal-warmup-templates', PW_PLUGIN_URL . 'admin/assets/js/templates-manager-v3.1.js', [ 'jquery', 'jquery-ui-draggable', 'jquery-ui-droppable' ], $this->version, true );
		}

		wp_localize_script( 'postal-warmup-admin', 'pwAdmin', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pw_admin_nonce' ),
			'confirm_delete' => __( 'Êtes-vous sûr de vouloir supprimer cet élément ?', 'postal-warmup' ),
			'refresh_rate' => (int) Settings::get( 'dashboard_refresh', 30 ) * 1000
		]);
	}

	public function add_admin_menu(): void {
		$cap = Settings::get( 'required_capability', 'manage_options' );

		add_menu_page(
			__( 'Postal Warmup', 'postal-warmup' ),
			__( 'Postal Warmup', 'postal-warmup' ),
			$cap,
			'postal-warmup',
			[ $this, 'display_dashboard' ],
			'dashicons-email-alt',
			26
		);

		add_submenu_page( 'postal-warmup', __( 'Tableau de bord', 'postal-warmup' ), __( 'Tableau de bord', 'postal-warmup' ), $cap, 'postal-warmup', [ $this, 'display_dashboard' ] );
		add_submenu_page( 'postal-warmup', __( 'Serveurs', 'postal-warmup' ), __( 'Serveurs', 'postal-warmup' ), $cap, 'postal-warmup-servers', [ $this, 'display_servers' ] );
		add_submenu_page( 'postal-warmup', __( 'File d\'attente', 'postal-warmup' ), __( 'File d\'attente', 'postal-warmup' ), $cap, 'postal-warmup-queue', [ $this, 'display_queue' ] );
		add_submenu_page( 'postal-warmup', __( 'Templates', 'postal-warmup' ), __( 'Templates', 'postal-warmup' ), $cap, 'postal-warmup-templates', [ $this, 'display_templates' ] );
		add_submenu_page( 'postal-warmup', __( 'Statistiques', 'postal-warmup' ), __( 'Statistiques', 'postal-warmup' ), $cap, 'postal-warmup-stats', [ $this, 'display_stats' ] );
		add_submenu_page( 'postal-warmup', __( 'Logs', 'postal-warmup' ), __( 'Logs', 'postal-warmup' ), $cap, 'postal-warmup-logs', [ $this, 'display_logs' ] );
		add_submenu_page( 'postal-warmup', __( 'Paramètres', 'postal-warmup' ), __( 'Paramètres', 'postal-warmup' ), $cap, 'postal-warmup-settings', [ $this, 'display_settings' ] );
		add_submenu_page( 'postal-warmup', __( 'Gestion ISP', 'postal-warmup' ), __( 'Gestion ISP', 'postal-warmup' ), $cap, 'postal-warmup-isps', [ $this, 'display_isps' ] );
		add_submenu_page( 'postal-warmup', __( 'Stratégies', 'postal-warmup' ), __( 'Stratégies', 'postal-warmup' ), $cap, 'postal-warmup-strategies', [ $this, 'display_strategies' ] );
	}

	public function display_dashboard(): void { require_once PW_ADMIN_DIR . 'partials/dashboard.php'; }
	public function display_servers(): void { require_once PW_ADMIN_DIR . 'partials/servers.php'; }
	public function display_queue(): void { require_once PW_ADMIN_DIR . 'partials/queue.php'; }
	public function display_templates(): void { require_once PW_ADMIN_DIR . 'partials/templates-v3.1.php'; }
	public function display_stats(): void { require_once PW_ADMIN_DIR . 'partials/stats.php'; }
	public function display_logs(): void { require_once PW_ADMIN_DIR . 'partials/logs.php'; }
	public function display_settings(): void { require_once PW_ADMIN_DIR . 'partials/settings.php'; }
	public function display_isps(): void { require_once PW_ADMIN_DIR . 'partials/isps.php'; }
	public function display_strategies(): void { require_once PW_ADMIN_DIR . 'partials/strategies.php'; }

	public function display_admin_notices(): void {
		if ( get_transient( 'pw_activation_notice' ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php _e( 'Postal Warmup Pro activé avec succès. Pensez à configurer vos serveurs.', 'postal-warmup' ); ?></p>
			</div>
			<?php
			delete_transient( 'pw_activation_notice' );
		}
	}

	private function is_plugin_page(): bool {
		return isset( $_GET['page'] ) && strpos( $_GET['page'], 'postal-warmup' ) !== false;
	}
}
