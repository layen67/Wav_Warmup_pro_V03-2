<?php

namespace PostalWarmup\Admin;

use PostalWarmup\Models\ReplyTemplateRule;

declare(strict_types=1);

class ReplyRuleManager {

	public static function ajax_save_reply_rule(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );

		$data = [
			'id' => (int) ($_POST['id'] ?? 0),
			'name' => sanitize_text_field( $_POST['name'] ),
			'match_prefix' => sanitize_text_field( $_POST['match_prefix'] ),
			'response_template_name' => sanitize_text_field( $_POST['response_template_name'] ),
			'active' => isset( $_POST['active'] ) ? 1 : 0
		];

		$id = ReplyTemplateRule::save( $data );

		if ( $id ) wp_send_json_success( [ 'id' => $id ] );
		else wp_send_json_error( [ 'message' => 'Save failed' ] );
	}

	public static function ajax_delete_reply_rule(): void {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );

		ReplyTemplateRule::delete( (int) $_POST['id'] );
		wp_send_json_success();
	}
}
