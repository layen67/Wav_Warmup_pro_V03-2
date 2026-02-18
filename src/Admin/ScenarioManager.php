<?php

declare(strict_types=1);

namespace PostalWarmup\Admin;

use PostalWarmup\Models\Scenario;



class ScenarioManager {

	public static function ajax_save_scenario(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );

		$data = [
			'id' => (int) ($_POST['id'] ?? 0),
			'name' => sanitize_text_field( $_POST['name'] ),
			'description' => sanitize_textarea_field( $_POST['description'] ),
			'steps' => wp_unslash( $_POST['steps'] ), // JSON string
			'trigger_event' => sanitize_key( $_POST['trigger_event'] ),
			'active' => isset( $_POST['active'] ) ? 1 : 0
		];

		$id = Scenario::save( $data );

		if ( $id ) wp_send_json_success( [ 'id' => $id ] );
		else wp_send_json_error( [ 'message' => 'Save failed' ] );
	}

	public static function ajax_delete_scenario(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );

		Scenario::delete( (int) $_POST['id'] );
		wp_send_json_success();
	}
}
