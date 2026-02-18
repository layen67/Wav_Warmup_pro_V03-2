<?php

declare(strict_types=1);

namespace PostalWarmup\Models;



class Scenario {

	public static function get_all( bool $active_only = false ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_scenarios';
		$where = $active_only ? "WHERE active = 1" : "";

		$results = $wpdb->get_results( "SELECT * FROM $table $where ORDER BY priority DESC", ARRAY_A );

		foreach ( $results as &$row ) {
			$row['steps'] = json_decode( $row['steps'], true );
			$row['conditions'] = json_decode( $row['conditions'], true );
			$row['allowed_server_ids'] = json_decode( $row['allowed_server_ids'], true );
		}

		return $results ?: [];
	}

	public static function get( int $id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_scenarios';

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );

		if ( $row ) {
			$row['steps'] = json_decode( $row['steps'], true );
			$row['conditions'] = json_decode( $row['conditions'], true );
			$row['allowed_server_ids'] = json_decode( $row['allowed_server_ids'], true );
		}

		return $row;
	}

	public static function save( array $data ): int|false {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_scenarios';

		$defaults = [
			'trigger_event' => 'reply',
			'active' => 1,
			'priority' => 10,
			'steps' => '[]',
			'conditions' => '[]',
			'allowed_server_ids' => '[]'
		];

		$data = array_merge( $defaults, $data );
		$data['updated_at'] = current_time( 'mysql' );

		if ( ! empty( $data['id'] ) ) {
			$wpdb->update( $table, $data, [ 'id' => $data['id'] ] );
			return (int) $data['id'];
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
		}
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $wpdb->prefix . 'postal_scenarios', [ 'id' => $id ] );
	}
}
