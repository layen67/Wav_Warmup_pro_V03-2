<?php

declare(strict_types=1);

namespace PostalWarmup\Core;

use PostalWarmup\Admin\Admin;
use PostalWarmup\Admin\AjaxHandler;
use PostalWarmup\Admin\Settings;
use PostalWarmup\Admin\WarmupSettings;
use PostalWarmup\API\WebhookHandler;
use PostalWarmup\API\Sender;
use PostalWarmup\Services\Logger;
use PostalWarmup\Services\ScenarioEngine;
use PostalWarmup\Services\PostalRouteManager;
use PostalWarmup\Models\Database;
use PostalWarmup\Admin\ScenarioManager;
use PostalWarmup\Admin\ReplyRuleManager;



/**
 * The core plugin class.
 */
class Plugin {

	protected Loader $loader;
	protected string $version;

	public function __construct() {
		$this->version = PW_VERSION;
		$this->loader = new Loader();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_api_hooks();
		$this->define_cron_hooks();
		$this->define_security_hooks();
	}

	private function define_security_hooks(): void {
		$this->loader->add_filter( 'nonce_life', $this, 'filter_nonce_life' );
	}

	public function filter_nonce_life( $seconds ) {
		$hours = (int) Settings::get( 'nonce_expiration', 12 );
		if ( $hours > 0 ) {
			return $hours * 3600;
		}
		return $seconds;
	}

	private function set_locale(): void {
		$plugin_i18n = new i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	private function define_admin_hooks(): void {
		$plugin_admin = new Admin( $this->version );
		$plugin_settings = new Settings();
		$warmup_settings = new WarmupSettings();
		$ajax_handler = new AjaxHandler();
		$scenario_manager = new ScenarioManager();
		$reply_rule_manager = new ReplyRuleManager();

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_init', $plugin_settings, 'register_settings' );
		$this->loader->add_action( 'admin_init', $warmup_settings, 'register_settings' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'display_admin_notices' );
		$this->loader->add_action( 'plugins_loaded', $this, 'check_upgrade' );

		// Register all AJAX hooks
		$ajax_actions = [
			'test_server', 'regenerate_secret', 'get_dashboard_data',
			'clear_logs', 'clear_cache', 'export_stats', 'get_all_templates',
			'save_template', 'toggle_favorite', 'get_template', 'get_template_stats', 'delete_template',
			'duplicate_template', 'move_template', 'update_template_status',
			'get_template_versions', 'restore_template_version', 'reorder_templates',
			'bulk_action_templates', 'export_template', 'import_templates',
			'save_category', 'delete_category', 'get_categories',
			'get_suppression_list', 'delete_suppression', 'get_server_health',
			'get_advanced_stats', 'get_stats_table', 'get_server_detail',
			'process_queue_manual', 'save_isp', 'delete_isp',
			'save_strategy', 'delete_strategy', 'render_preview',
			'test_webhook', 'run_domscan_audit',
			'export_settings', 'import_settings', 'reset_settings', 'purge_all_data',
			// New Lifecycle & Migration Actions
			'deactivate_server_with_pause', 'reactivate_server_with_resume',
			'get_server_conversation_stats', 'delete_server_with_options',
			'prepare_server_migration', 'execute_server_migration',
			'get_migration_status', 'rollback_migration', 'delete_server_post_migration',
			// Conversation Actions
			'force_advance_conversation', 'cancel_conversation',
			'get_conversations', 'get_conversation_detail',
			'pause_conversation', 'resume_conversation'
		];

		foreach ( $ajax_actions as $action ) {
			$this->loader->add_action( 'wp_ajax_pw_' . $action, $ajax_handler, 'ajax_' . $action );
		}

		// Scenario & Rules AJAX
		$this->loader->add_action( 'wp_ajax_pw_save_scenario', $scenario_manager, 'ajax_save_scenario' );
		$this->loader->add_action( 'wp_ajax_pw_delete_scenario', $scenario_manager, 'ajax_delete_scenario' );
		$this->loader->add_action( 'wp_ajax_pw_save_reply_rule', $reply_rule_manager, 'ajax_save_reply_rule' );
		$this->loader->add_action( 'wp_ajax_pw_delete_reply_rule', $reply_rule_manager, 'ajax_delete_reply_rule' );
	}

	private function define_api_hooks(): void {
		$webhook_handler = new WebhookHandler();
		$this->loader->add_action( 'rest_api_init', $webhook_handler, 'register_routes' );
		
		// Initialize Sender hooks (Action Scheduler)
		$this->loader->add_action( 'init', new Sender(), 'init' );

		// Initialize Mailto Shortcodes & Tracking
		$mailto = new \PostalWarmup\Services\Mailto();
		$mailto->init();

		// Initialize Advisor Listeners
		\PostalWarmup\Services\WarmupAdvisor::init();

		// Initialize Webhook Dispatcher
		\PostalWarmup\Services\WebhookDispatcher::init();

		// Server Lifecycle Hook (Auto-create Route)
		$this->loader->add_action( 'pw_new_server_created', function($server_id) {
			$server = Database::get_server( $server_id );
			if ( $server ) {
				PostalRouteManager::ensure_route_configured( $server );
			}
		}, 10, 1 );
	}

	/**
	 * Register class aliases for backward compatibility.
	 */
	private function register_aliases(): void {
		$aliases = [
			'PW_Database'         => 'PostalWarmup\\Models\\Database',
			'PW_Stats'            => 'PostalWarmup\\Models\\Stats',
			'PW_Logger'           => 'PostalWarmup\\Services\\Logger',
			'PW_Cache'            => 'PostalWarmup\\Services\\Cache',
			'PW_Template_Manager' => 'PostalWarmup\\Admin\\TemplateManager',
			'PW_Folder_Manager'   => 'PostalWarmup\\Admin\\TemplateManager',
			'PW_Template_Loader'  => 'PostalWarmup\\Services\\TemplateLoader',
			'PW_Template_Sync'    => 'PostalWarmup\\Services\\TemplateSync',
			'PW_Activator'        => 'PostalWarmup\\Core\\Activator',
		];

		foreach ( $aliases as $alias => $original ) {
			if ( ! class_exists( $alias ) && class_exists( $original ) ) {
				class_alias( $original, $alias );
			}
		}
	}

	private function define_cron_hooks(): void {
		$this->loader->add_action( 'pw_cleanup_old_logs', 'PostalWarmup\Services\Logger', 'cleanup_old_logs' );
		$this->loader->add_action( 'pw_daily_report', 'PostalWarmup\Services\EmailNotifications', 'send_daily_report' );
		$this->loader->add_action( 'pw_cleanup_old_stats', 'PostalWarmup\Models\Stats', 'cleanup_old_stats' );
		$this->loader->add_action( 'pw_daily_stats_aggregation', 'PostalWarmup\Models\Stats', 'aggregate_daily_stats' );
		$this->loader->add_action( 'pw_process_queue', 'PostalWarmup\Services\QueueManager', 'process_queue' );
		$this->loader->add_action( 'pw_warmup_daily_increment', 'PostalWarmup\Models\Stats', 'increment_warmup_day' );
		$this->loader->add_action( 'pw_cleanup_queue', 'PostalWarmup\Services\QueueManager', 'cleanup' );

		if ( get_option( 'pw_advisor_enabled', true ) ) {
			$this->loader->add_action( 'pw_advisor_check', 'PostalWarmup\Services\WarmupAdvisor', 'run' );
		}

		// New Scenario hooks
		$this->loader->add_action( 'pw_scenario_daily_check', 'PostalWarmup\Services\ScenarioEngine', 'daily_check' );
		$this->loader->add_action( 'pw_send_engagement_step', 'PostalWarmup\Services\ScenarioEngine', 'send_engagement_step', 10, 1 );
		$this->loader->add_action( 'pw_check_contact_silence', 'PostalWarmup\Services\ScenarioEngine', 'handle_contact_silence', 10, 1 );
	}

	public function check_upgrade(): void {
		try {
			if ( get_option( 'pw_version' ) !== PW_VERSION ) {
				Activator::activate();
				update_option( 'pw_version', PW_VERSION );
			}
		} catch ( \Throwable $e ) {
			error_log( 'Postal Warmup Upgrade Error: ' . $e->getMessage() );
		}
	}

	public function run(): void {
		$this->register_aliases();
		$this->loader->run();
	}
}
