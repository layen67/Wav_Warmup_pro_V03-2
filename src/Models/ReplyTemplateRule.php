<?php
// src/Models/ReplyTemplateRule.php

declare(strict_types=1);

namespace PostalWarmup\Models;

/**
 * Handles database operations for Reply Rules.
 */
class ReplyTemplateRule {

	private static string $table = 'postal_reply_template_rules';

	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::$table;
	}

	public static function get_all( bool $active_only = false ): array {
		global $wpdb;
		$table = self::get_table_name();
		$sql = "SELECT * FROM $table";

		if ( $active_only ) {
			$sql .= " WHERE active = 1";
		}

		$sql .= " ORDER BY priority ASC, created_at DESC";

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public static function get( int $id ): ?array {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );
	}

	public static function create( array $data ): int {
		global $wpdb;
		$table = self::get_table_name();

		$wpdb->insert( $table, [
			'name' => sanitize_text_field( $data['name'] ),
			'match_prefix' => !empty($data['match_prefix']) ? sanitize_text_field( $data['match_prefix'] ) : null,
			'match_server_id' => !empty($data['match_server_id']) ? (int) $data['match_server_id'] : null,
			'match_subject_contains' => !empty($data['match_subject_contains']) ? sanitize_text_field( $data['match_subject_contains'] ) : null,
			'match_body_contains' => !empty($data['match_body_contains']) ? sanitize_text_field( $data['match_body_contains'] ) : null,
			'response_template_name' => sanitize_text_field( $data['response_template_name'] ),
			'scenario_id' => !empty($data['scenario_id']) ? (int) $data['scenario_id'] : null,
			'priority' => (int) ( $data['priority'] ?? 10 ),
			'active' => (int) ( $data['active'] ?? 1 ),
			'created_at' => current_time( 'mysql' )
		] );

		return $wpdb->insert_id;
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$table = self::get_table_name();

		$update_data = [];
		$format = [];

		$fields = [
			'name' => '%s',
			'match_prefix' => '%s',
			'match_server_id' => '%d',
			'match_subject_contains' => '%s',
			'match_body_contains' => '%s',
			'response_template_name' => '%s',
			'scenario_id' => '%d',
			'priority' => '%d',
			'active' => '%d',
		];

		foreach ( $fields as $field => $fmt ) {
			if ( array_key_exists( $field, $data ) ) { // Use array_key_exists to allow null updates
				$update_data[ $field ] = $data[ $field ];
				$format[] = $fmt;
			}
		}

		if ( empty( $update_data ) ) return false;

		$updated = $wpdb->update( $table, $update_data, [ 'id' => $id ], $format, [ '%d' ] );
		return $updated !== false;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$table = self::get_table_name();
		return (bool) $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
	}

	/**
	 * Find the best matching rule for an incoming message.
	 */
	public static function match_rule( int $server_id, string $subject, string $body, string $prefix ): ?array {
		$rules = self::get_all( true ); // Active only, sorted by priority

		foreach ( $rules as $rule ) {
			// Check Server ID
			if ( ! empty( $rule['match_server_id'] ) && (int)$rule['match_server_id'] !== $server_id ) {
				continue;
			}

			// Check Prefix
			if ( ! empty( $rule['match_prefix'] ) && stripos( $prefix, $rule['match_prefix'] ) === false ) {
				continue;
			}

			// Check Subject
			if ( ! empty( $rule['match_subject_contains'] ) && stripos( $subject, $rule['match_subject_contains'] ) === false ) {
				continue;
			}

			// Check Body
			if ( ! empty( $rule['match_body_contains'] ) && stripos( $body, $rule['match_body_contains'] ) === false ) {
				continue;
			}

			// Match found!
			return $rule;
		}

		return null;
	}
}
