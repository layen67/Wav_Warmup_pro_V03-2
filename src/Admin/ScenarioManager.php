<?php
// src/Admin/ScenarioManager.php

declare(strict_types=1);

namespace PostalWarmup\Admin;

use PostalWarmup\Models\Scenario;

/**
 * Admin Logic for Scenarios.
 */
class ScenarioManager {

	public static function get_all(): array {
		return Scenario::get_all( false ); // All, including inactive
	}

	public static function get( int $id ): ?array {
		return Scenario::get( $id );
	}

	public static function save( array $post_data ): int {
		$data = [
			'name' => sanitize_text_field( $post_data['name'] ),
			'description' => sanitize_textarea_field( $post_data['description'] ?? '' ),
			'trigger_event' => sanitize_text_field( $post_data['trigger_event'] ?? 'reply' ),
			'reply_template_name' => sanitize_text_field( $post_data['reply_template_name'] ?? '' ),
			'migration_template' => sanitize_text_field( $post_data['migration_template'] ?? '' ),
			'loop_back_to_stage' => (int) ( $post_data['loop_back_to_stage'] ?? 0 ),
			'loop_max_cycles' => (int) ( $post_data['loop_max_cycles'] ?? 0 ),
			'require_reply_to_advance' => isset( $post_data['require_reply_to_advance'] ) ? 1 : 0,
			'reactivation_delay_days' => (int) ( $post_data['reactivation_delay_days'] ?? 7 ),
			'reactivation_template' => sanitize_text_field( $post_data['reactivation_template'] ?? '' ),
			'priority' => (int) ( $post_data['priority'] ?? 10 ),
			'active' => isset( $post_data['active'] ) ? 1 : 0,
		];

		// JSON fields need special handling to decode from POST if sent as string, or use as is
		if ( isset( $post_data['conditions'] ) ) {
			$data['conditions'] = is_string( $post_data['conditions'] ) ? json_decode( wp_unslash( $post_data['conditions'] ), true ) : $post_data['conditions'];
		}

		if ( isset( $post_data['steps'] ) ) {
			$data['steps'] = is_string( $post_data['steps'] ) ? json_decode( wp_unslash( $post_data['steps'] ), true ) : $post_data['steps'];
		}

		if ( isset( $post_data['allowed_server_ids'] ) ) {
			$data['allowed_server_ids'] = is_string( $post_data['allowed_server_ids'] ) ? json_decode( wp_unslash( $post_data['allowed_server_ids'] ), true ) : $post_data['allowed_server_ids'];
		}

		if ( ! empty( $post_data['id'] ) ) {
			$id = (int) $post_data['id'];
			Scenario::update( $id, $data );
			return $id;
		} else {
			return Scenario::create( $data );
		}
	}

	public static function delete( int $id ): bool {
		return Scenario::delete( $id );
	}
}
