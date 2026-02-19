<?php
// src/Admin/ReplyRuleManager.php

declare(strict_types=1);

namespace PostalWarmup\Admin;

use PostalWarmup\Models\ReplyTemplateRule;

/**
 * Admin Logic for Reply Rules.
 */
class ReplyRuleManager {

	public static function get_all(): array {
		return ReplyTemplateRule::get_all( false );
	}

	public static function get( int $id ): ?array {
		return ReplyTemplateRule::get( $id );
	}

	public static function save( array $post_data ): int {
		$data = [
			'name' => sanitize_text_field( $post_data['name'] ),
			'match_prefix' => sanitize_text_field( $post_data['match_prefix'] ?? '' ),
			'match_server_id' => (int) ( $post_data['match_server_id'] ?? 0 ),
			'match_subject_contains' => sanitize_text_field( $post_data['match_subject_contains'] ?? '' ),
			'match_body_contains' => sanitize_text_field( $post_data['match_body_contains'] ?? '' ),
			'response_template_name' => sanitize_text_field( $post_data['response_template_name'] ),
			'scenario_id' => (int) ( $post_data['scenario_id'] ?? 0 ),
			'priority' => (int) ( $post_data['priority'] ?? 10 ),
			'active' => isset( $post_data['active'] ) ? 1 : 0,
		];

		if ( ! empty( $post_data['id'] ) ) {
			$id = (int) $post_data['id'];
			ReplyTemplateRule::update( $id, $data );
			return $id;
		} else {
			return ReplyTemplateRule::create( $data );
		}
	}

	public static function delete( int $id ): bool {
		return ReplyTemplateRule::delete( $id );
	}
}
