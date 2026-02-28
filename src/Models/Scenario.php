<?php
// src/Models/Scenario.php

declare(strict_types=1);

namespace PostalWarmup\Models;

/**
 * Handles database operations for Scenarios.
 */
class Scenario {

	private static string $table = 'postal_scenarios';

	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::$table;
	}

	public static function get( int $id ): ?array {
		global $wpdb;
		$table = self::get_table_name();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );

		if ( $row ) {
			$row['conditions'] = json_decode( $row['conditions'], true ) ?: [];
			$row['steps'] = json_decode( $row['steps'], true ) ?: [];
			$row['allowed_server_ids'] = json_decode( $row['allowed_server_ids'], true ) ?: [];
		}

		return $row;
	}

	public static function get_all( bool $active_only = false ): array {
		global $wpdb;
		$table = self::get_table_name();
		$sql = "SELECT * FROM $table";

		if ( $active_only ) {
			$sql .= " WHERE active = 1";
		}

		$sql .= " ORDER BY priority ASC, created_at DESC";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		foreach ( $results as &$row ) {
			$row['conditions'] = json_decode( $row['conditions'], true ) ?: [];
			$row['steps'] = json_decode( $row['steps'], true ) ?: [];
			$row['allowed_server_ids'] = json_decode( $row['allowed_server_ids'], true ) ?: [];
		}

		return $results;
	}

	public static function create( array $data ): int {
		global $wpdb;
		$table = self::get_table_name();

		$wpdb->insert( $table, [
			'name' => sanitize_text_field( $data['name'] ),
			'description' => sanitize_textarea_field( $data['description'] ?? '' ),
			'trigger_event' => sanitize_text_field( $data['trigger_event'] ?? 'reply' ),
			'conditions' => json_encode( $data['conditions'] ?? [] ),
			'steps' => json_encode( $data['steps'] ?? [] ),
			'reply_template_name' => sanitize_text_field( $data['reply_template_name'] ?? '' ),
			'migration_template' => sanitize_text_field( $data['migration_template'] ?? '' ),
			'loop_back_to_stage' => (int) ( $data['loop_back_to_stage'] ?? 0 ),
			'loop_max_cycles' => (int) ( $data['loop_max_cycles'] ?? 0 ),
			'require_reply_to_advance' => (int) ( $data['require_reply_to_advance'] ?? 1 ),
			'reactivation_delay_days' => (int) ( $data['reactivation_delay_days'] ?? 7 ),
			'reactivation_template' => sanitize_text_field( $data['reactivation_template'] ?? '' ),
			'allowed_server_ids' => isset( $data['allowed_server_ids'] ) ? json_encode( $data['allowed_server_ids'] ) : null,
			'priority' => (int) ( $data['priority'] ?? 10 ),
			'active' => (int) ( $data['active'] ?? 1 ),
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' )
		] );

		return $wpdb->insert_id;
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$table = self::get_table_name();

		$update_data = [];
		$format = [];

		// Allow partial updates
		$fields = [
			'name' => '%s',
			'description' => '%s',
			'trigger_event' => '%s',
			'conditions' => '%s', // json
			'steps' => '%s', // json
			'reply_template_name' => '%s',
			'migration_template' => '%s',
			'loop_back_to_stage' => '%d',
			'loop_max_cycles' => '%d',
			'require_reply_to_advance' => '%d',
			'reactivation_delay_days' => '%d',
			'reactivation_template' => '%s',
			'allowed_server_ids' => '%s', // json
			'priority' => '%d',
			'active' => '%d',
		];

		foreach ( $fields as $field => $fmt ) {
			if ( isset( $data[ $field ] ) ) {
				$val = $data[ $field ];
				if ( in_array( $field, ['conditions', 'steps', 'allowed_server_ids'] ) ) {
					$val = json_encode( $val );
				}
				$update_data[ $field ] = $val;
				$format[] = $fmt;
			}
		}

		if ( empty( $update_data ) ) return false;

		$update_data['updated_at'] = current_time( 'mysql' );
		$format[] = '%s';

		$updated = $wpdb->update( $table, $update_data, [ 'id' => $id ], $format, [ '%d' ] );
		return $updated !== false;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$table = self::get_table_name();
		return (bool) $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
	}
}
