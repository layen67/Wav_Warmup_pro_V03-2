<?php

declare(strict_types=1);

namespace PostalWarmup\Admin;

use PostalWarmup\Models\Database;
use PostalWarmup\Models\Stats;
use PostalWarmup\Services\Logger;
use PostalWarmup\API\Sender;
use PostalWarmup\API\Client;
use PostalWarmup\Admin\TemplateManager;
use PostalWarmup\Admin\Settings;
use PostalWarmup\Admin\ISPManager;
use PostalWarmup\Admin\StrategyManager;
use PostalWarmup\Admin\ScenarioManager;
use PostalWarmup\Admin\ReplyRuleManager;

/**
 * Gestionnaire des requêtes AJAX
 */
class AjaxHandler {

	private function check_permission(): void {
		$cap = Settings::get( 'required_capability', 'manage_options' );
		if ( ! current_user_can( $cap ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ] );
			exit;
		}
	}

	public function ajax_test_server(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		
		try {
			$server_id = (int) $_POST['server_id'];
			$result = Sender::test_connection( $server_id );
			
			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result );
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'Exception lors du test serveur : ' . $e->getMessage() );
			wp_send_json_error( [ 'message' => 'Erreur critique : ' . $e->getMessage() ] );
		}
	}

	public function ajax_regenerate_secret(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		$secret = wp_generate_password( 64, false );
		update_option( 'pw_webhook_secret', $secret );
		wp_send_json_success( [ 'secret' => $secret, 'message' => __( 'Secret régénéré.', 'postal-warmup' ) ] );
	}

	public function ajax_get_dashboard_data(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		
		$days = isset( $_POST['days'] ) ? (int) $_POST['days'] : 7;
		
		$logs = Database::get_enriched_activity( 15 );
		$summary = Stats::get_dashboard_stats();
		$chart = Stats::get_global_stats( $days );
		$errors = Stats::get_recent_errors( 5 );
		
		wp_send_json_success( [ 
			'logs' => $logs, 
			'summary' => $summary, 
			'chart' => $chart,
			'errors' => $errors
		] );
	}

	public function ajax_clear_logs(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		Logger::clear_all_logs();
		wp_send_json_success( [ 'message' => __( 'Logs supprimés.', 'postal-warmup' ) ] );
	}

	public function ajax_get_all_templates(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		wp_send_json_success( [ 'templates' => TemplateManager::get_all_with_meta() ] );
	}

	public function ajax_save_template(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		$variants = isset($_POST['variants']) ? wp_unslash($_POST['variants']) : [];

		$name = sanitize_text_field( $_POST['name'] ?? '' );
		$data = [
			'subject'   => array_map( 'sanitize_text_field', $variants['subject'] ?? [] ),
			'text'      => array_map( 'sanitize_textarea_field', $variants['text'] ?? [] ),
			'html'      => $variants['html'] ?? [], // Already unslashed
			'from_name' => array_map( 'sanitize_text_field', $variants['from_name'] ?? [] ),
			'mailto_subject'   => array_map( 'sanitize_text_field', $variants['mailto_subject'] ?? [] ),
			'mailto_body'      => array_map( 'sanitize_textarea_field', $variants['mailto_body'] ?? [] ),
			'mailto_from_name' => array_map( 'sanitize_text_field', $variants['mailto_from_name'] ?? [] ),
			'default_label' => sanitize_text_field( $_POST['default_label'] ?? '' ),
		];
		$tags_input = isset( $_POST['tags'] ) ? $_POST['tags'] : '';
		$tags_array = is_array( $tags_input ) ? $tags_input : explode( ',', (string) $tags_input );

		$meta = [
			'id'        => (int) ( $_POST['id'] ?? 0 ),
			'folder_id' => (int) ( $_POST['folder_id'] ?? 0 ),
			'status'    => sanitize_text_field( $_POST['status'] ?? 'active' ),
			'tags'      => array_map( 'sanitize_text_field', $tags_array ),
			'timezone'  => sanitize_text_field( $_POST['timezone'] ?? '' )
		];
		
		$result = TemplateManager::save_template( $name, $data, $meta );
		if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		else wp_send_json_success( [ 'message' => 'Saved' ] );
	}

	public function ajax_delete_template(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		$result = TemplateManager::delete_template( sanitize_text_field( $_POST['name'] ) );
		if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		else wp_send_json_success();
	}

	public function ajax_duplicate_template(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		
		$name = sanitize_text_field( $_POST['name'] ?? '' );
		$new_name = sanitize_text_field( $_POST['new_name'] ?? '' );

		$result = TemplateManager::duplicate_template( $name, $new_name );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		} else {
			wp_send_json_success( [ 'id' => $result ] );
		}
	}
	
	public function ajax_get_template(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		$tpl = TemplateManager::get_template( sanitize_text_field( $_POST['name'] ) );
		if ( $tpl ) wp_send_json_success( $tpl );
		else wp_send_json_error( [ 'message' => 'Not found' ] );
	}

	public function ajax_get_template_stats(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		
		$name = sanitize_text_field( $_POST['template_name'] );
		$days = isset( $_POST['days'] ) ? (int) $_POST['days'] : 30;
		
		$stats = Stats::get_template_stats( $name, $days );
		
		wp_send_json_success( [ 'stats' => $stats ] );
	}

	public function ajax_save_category(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		$id = TemplateManager::save_category( 
			sanitize_text_field( $_POST['name'] ), 
			(int)$_POST['parent_id'], 
			sanitize_hex_color( $_POST['color'] ), 
			(int)$_POST['id'] 
		);
		wp_send_json_success( [ 'id' => $id ] );
	}

	public function ajax_delete_category(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		TemplateManager::delete_category( (int)$_POST['id'] );
		wp_send_json_success();
	}

	public function ajax_get_categories(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		wp_send_json_success( [ 'tree' => TemplateManager::get_folders_tree() ] );
	}

	public function ajax_toggle_favorite(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		TemplateManager::toggle_favorite( (int)$_POST['template_id'], filter_var( $_POST['favorite'], FILTER_VALIDATE_BOOLEAN ) );
		wp_send_json_success();
	}

	public function ajax_move_template(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		TemplateManager::move_template( (int)$_POST['template_id'], (int)$_POST['folder_id'] );
		wp_send_json_success();
	}

	public function ajax_update_template_status(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		TemplateManager::update_status( (int)$_POST['template_id'], sanitize_key( $_POST['status'] ) );
		wp_send_json_success();
	}

	public function ajax_get_template_versions(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		$versions = TemplateManager::get_versions( (int)$_POST['template_id'] );
		wp_send_json_success( [ 'versions' => $versions ] );
	}

	public function ajax_restore_template_version(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		$result = TemplateManager::restore_version( (int)$_POST['version_id'] );
		if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		else wp_send_json_success();
	}

	public function ajax_clear_cache(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_pw_%'" );
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_pw_%'" );
		
		wp_send_json_success( [ 'message' => __( 'Cache vidé.', 'postal-warmup' ) ] );
	}

	public function ajax_export_stats(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		global $wpdb;
		$stats_table = $wpdb->prefix . 'postal_stats';
		$servers_table = $wpdb->prefix . 'postal_servers';
		
		$results = $wpdb->get_results( "
			SELECT s.date, s.hour, s.sent_count, s.success_count, s.error_count, s.avg_response_time, sv.domain
			FROM $stats_table s
			LEFT JOIN $servers_table sv ON s.server_id = sv.id
			ORDER BY s.date DESC, s.hour DESC
		", ARRAY_A );

		$filename = 'postal-stats-' . date('Y-m-d') . '.csv';
		
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, [ 'Date', 'Hour', 'Server', 'Sent', 'Success', 'Error', 'Avg Latency (ms)' ] );
		
		foreach ( $results as $row ) {
			fputcsv( $output, $row );
		}
		
		fclose( $output );
		exit;
	}

	public function ajax_reorder_templates(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		wp_send_json_success();
	}

	public function ajax_bulk_action_templates(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		wp_send_json_success();
	}

	public function ajax_export_template(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		$name = sanitize_text_field( $_POST['name'] );
		$template = TemplateManager::get_template( $name );
		
		if ( ! $template ) wp_send_json_error( [ 'message' => 'Template not found' ] );
		
		$filename = $name . '.json';
		
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		
		echo json_encode( $template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public function ajax_import_templates(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		$file = $_FILES['file'] ?? ( $_FILES['import_file'] ?? null );

		if ( empty( $file ) ) wp_send_json_error( [ 'message' => 'No file uploaded' ] );
		
		$content = file_get_contents( $file['tmp_name'] );
		$data = json_decode( $content, true );
		
		if ( ! $data ) wp_send_json_error( [ 'message' => 'Invalid JSON' ] );

		$required = [ 'subject', 'text', 'html' ];
		foreach ( $required as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				wp_send_json_error( [ 'message' => "Missing field: $field" ] );
			}
		}

		if ( ! is_array( $data['subject'] ) || ! is_array( $data['text'] ) || ! is_array( $data['html'] ) ) {
			wp_send_json_error( [ 'message' => 'Invalid format: fields must be arrays' ] );
		}
		
		$name = sanitize_title( pathinfo( $file['name'], PATHINFO_FILENAME ) );
		$uncat = TemplateManager::ensure_uncategorized_folder();
		
		$meta = [
			'id' => 0,
			'folder_id' => $uncat,
			'status' => 'active',
			'tags' => []
		];
		
		global $wpdb;
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}postal_templates WHERE name = %s", $name ) );
		if ( $exists ) $name .= '-' . time();
		
		TemplateManager::save_template( $name, $data, $meta );
		wp_send_json_success( [ 'message' => 'Imported as ' . $name ] );
	}

	public function ajax_get_suppression_list(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		
		$server_id = (int) $_POST['server_id'];
		$result = Client::request( $server_id, 'suppressions' );
		
		if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		else wp_send_json_success( [ 'list' => $result ] );
	}

	public function ajax_delete_suppression(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		
		$server_id = (int) $_POST['server_id'];
		$email = sanitize_email( $_POST['email'] );
		
		$result = Client::request( $server_id, 'suppressions/delete', 'POST', [ 'email' => $email ] );
		
		if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		else wp_send_json_success();
	}

	public function ajax_get_server_health(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		
		$server_id = (int) $_POST['server_id'];
		$start = microtime( true );
		$result = Client::request( $server_id, 'messages', 'GET', [ 'count' => 1 ] );
		$duration = round( ( microtime( true ) - $start ) * 1000, 2 );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'status' => 'error', 'latency' => $duration, 'message' => $result->get_error_message() ] );
		} else {
			wp_send_json_success( [ 'status' => 'ok', 'latency' => $duration, 'message' => 'Connected' ] );
		}
	}

	public function ajax_get_advanced_stats(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		$days = isset( $_POST['days'] ) ? (int) $_POST['days'] : 30;

		$charts = Stats::get_advanced_charts_data( $days );
		$heatmap = Stats::get_heatmap_data( $days );

		wp_send_json_success( [ 'charts' => $charts, 'heatmap' => $heatmap ] );
	}

	public function ajax_get_stats_table(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		wp_send_json_error( [ 'message' => 'Endpoint deprecated. Use pw_get_server_detail.' ] );
	}

	public function ajax_get_server_detail(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		$server_id = (int) $_POST['server_id'];
		$days = isset( $_POST['days'] ) ? (int) $_POST['days'] : 30;
		
		if ( ! $server_id ) wp_send_json_error( [ 'message' => 'Missing server ID' ] );

		$stats = Stats::get_server_detail_breakdown( $server_id, $days );
		
		wp_send_json_success( [ 'stats' => $stats ] );
	}

	public function ajax_process_queue_manual(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		
		\PostalWarmup\Services\QueueManager::process_queue();
		
		wp_send_json_success( [ 'message' => 'File d\'attente traitée' ] );
	}

	public function ajax_save_isp(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		
		$result = ISPManager::save( $_POST );
		
		if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		else wp_send_json_success( [ 'id' => $result ] );
	}

	public function ajax_delete_isp(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		
		ISPManager::delete( (int)$_POST['id'] );
		wp_send_json_success();
	}

	public function ajax_save_strategy(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		
		$result = StrategyManager::save( $_POST );
		
		if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		else wp_send_json_success( [ 'id' => $result ] );
	}

	public function ajax_delete_strategy(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		
		StrategyManager::delete( (int)$_POST['id'] );
		wp_send_json_success();
	}

	public function ajax_test_webhook(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		$url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';

		if ( empty( $url ) ) wp_send_json_error( [ 'message' => 'Veuillez saisir une URL valide.' ] );

		$result = \PostalWarmup\Services\WebhookDispatcher::send_test( $url );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => 'Erreur : ' . $result->get_error_message() ] );
		}

		$code = wp_remote_retrieve_response_code( $result );
		$body = wp_remote_retrieve_body( $result );

		if ( $code >= 200 && $code < 300 ) {
			wp_send_json_success( [ 'message' => "Succès ! Le serveur a répondu HTTP $code." ] );
		} else {
			wp_send_json_error( [ 'message' => "Échec : Le serveur a répondu HTTP $code. ($body)" ] );
		}
	}

	public function ajax_run_domscan_audit(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		$domain = sanitize_text_field( $_POST['domain'] );

		if ( empty( $domain ) ) wp_send_json_error( [ 'message' => 'Domaine manquant' ] );

		$result = \PostalWarmup\Services\DomScanService::audit_domain( $domain );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		} else {
			wp_send_json_success( [ 'data' => $result ] );
		}
	}

	public function ajax_render_preview(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$context_type = sanitize_key( $_POST['context_type'] ?? 'male' );

		// Base context
		$context = [
			'email'        => 'jean.dupont@example.com',
			'domain'       => 'example.com',
			'local'        => 'jean.dupont',
			'date'         => current_time( 'd/m/Y' ),
			'time'         => current_time( 'H:i' ),
			'heure_fr'     => current_time( 'H\hi' ),
			'jour_semaine' => date_i18n( 'l' ),
			'mois'         => date_i18n( 'F' ),
			'civilite'     => ( (int) current_time( 'H' ) >= 18 || (int) current_time( 'H' ) < 5 ) ? 'Bonsoir' : 'Bonjour',
			'ref'          => 'REF-' . strtoupper( substr( md5( uniqid() ), 0, 8 ) ),
			'site_url'     => get_site_url(),
			'site_name'    => get_bloginfo( 'name' ),
			'admin_email'  => get_option( 'admin_email' ),
			'user_ip'      => '192.168.1.1',
			'user_agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
		];

		// Context simulation
		switch ( $context_type ) {
			case 'female':
				$context['email'] = 'marie.curie@example.com';
				$context['local'] = 'marie.curie';
				$context['prenom'] = 'Marie';
				$context['prénom'] = 'Marie';
				break;
			case 'company':
				$context['email'] = 'contact@societe.com';
				$context['local'] = 'contact';
				$context['prenom'] = 'L\'équipe';
				$context['prénom'] = 'L\'équipe';
				break;
			case 'male':
			default:
				$context['email'] = 'jean.dupont@example.com';
				$context['local'] = 'jean.dupont';
				$context['prenom'] = 'Jean';
				$context['prénom'] = 'Jean';
				break;
		}

		$rendered = \PostalWarmup\Core\TemplateEngine::render_string( $content, $context );

		wp_send_json_success( [ 'rendered' => $rendered ] );
	}

	public function ajax_export_settings(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		$settings = get_option( 'pw_settings', [] );
		$filename = 'pw-settings-' . date('Y-m-d') . '.json';

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo json_encode( $settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public function ajax_import_settings(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		$file = $_FILES['file'] ?? null;
		if ( empty( $file ) ) wp_send_json_error( [ 'message' => 'No file uploaded' ] );

		$content = file_get_contents( $file['tmp_name'] );
		$data = json_decode( $content, true );

		if ( ! is_array( $data ) ) wp_send_json_error( [ 'message' => 'Invalid JSON' ] );

		// Validate known keys to prevent garbage injection
		$settings_instance = new Settings();
		$sanitized = $settings_instance->sanitize_settings( $data );

		update_option( 'pw_settings', $sanitized );

		wp_send_json_success( [ 'message' => 'Settings imported successfully.' ] );
	}

	public function ajax_reset_settings(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		delete_option( 'pw_settings' );
		// Trigger migration to restore defaults
		$settings_instance = new Settings();
		$settings_instance->register_settings();

		wp_send_json_success( [ 'message' => 'Settings reset to defaults.' ] );
	}

	public function ajax_purge_all_data(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		global $wpdb;
		// Purge Logs, Queue, Stats
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}postal_logs" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}postal_queue" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}postal_stats" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}postal_stats_daily" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}postal_stats_history" );

		wp_send_json_success( [ 'message' => 'All data purged.' ] );
	}

	// --- Scenarios ---

	public function ajax_get_all_scenarios(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		wp_send_json_success( [ 'scenarios' => ScenarioManager::get_all() ] );
	}

	public function ajax_get_scenario(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		$scenario = ScenarioManager::get( (int) $_POST['id'] );
		if ( $scenario ) wp_send_json_success( $scenario );
		else wp_send_json_error( [ 'message' => 'Scenario not found' ] );
	}

	public function ajax_save_scenario(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		$id = ScenarioManager::save( $_POST );

		if ( $id ) wp_send_json_success( [ 'id' => $id, 'message' => 'Scenario saved.' ] );
		else wp_send_json_error( [ 'message' => 'Save failed.' ] );
	}

	public function ajax_delete_scenario(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		if ( ScenarioManager::delete( (int) $_POST['id'] ) ) {
			wp_send_json_success( [ 'message' => 'Scenario deleted.' ] );
		} else {
			wp_send_json_error( [ 'message' => 'Delete failed.' ] );
		}
	}

	// --- Reply Rules ---

	public function ajax_get_all_reply_rules(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		wp_send_json_success( [ 'rules' => ReplyRuleManager::get_all() ] );
	}

	public function ajax_get_reply_rule(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();
		$rule = ReplyRuleManager::get( (int) $_POST['id'] );
		if ( $rule ) wp_send_json_success( $rule );
		else wp_send_json_error( [ 'message' => 'Rule not found' ] );
	}

	public function ajax_save_reply_rule(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		$id = ReplyRuleManager::save( $_POST );

		if ( $id ) wp_send_json_success( [ 'id' => $id, 'message' => 'Rule saved.' ] );
		else wp_send_json_error( [ 'message' => 'Save failed.' ] );
	}

	public function ajax_delete_reply_rule(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		$this->check_permission();

		if ( ReplyRuleManager::delete( (int) $_POST['id'] ) ) {
			wp_send_json_success( [ 'message' => 'Rule deleted.' ] );
		} else {
			wp_send_json_error( [ 'message' => 'Delete failed.' ] );
		}
	}
}
