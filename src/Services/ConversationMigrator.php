<?php
// src/Services/ConversationMigrator.php

declare(strict_types=1);

namespace PostalWarmup\Services;

use PostalWarmup\Models\Database;
use PostalWarmup\Core\Activator;

/**
 * Handles migration of conversations from one server to another.
 * e.g., Warmup -> Production
 */
class ConversationMigrator {

	public static function migrate( int $source_server_id, int $target_server_id ): array {
		global $wpdb;
		$table_conv = ConversationManager::get_table_name();

		// 1. Verify servers
		$source = Database::get_server( $source_server_id );
		$target = Database::get_server( $target_server_id );

		if ( ! $source || ! $target ) {
			return [ 'error' => 'Invalid source or target server' ];
		}

		// 2. Find eligible conversations
		// Only active ones
		$conversations = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM $table_conv WHERE server_id = %d AND status = 'active' AND migration_status = 'none'",
			$source_server_id
		), ARRAY_A );

		$count = 0;
		foreach ( $conversations as $conv ) {
			// Update conversation to point to new server
			// And mark as migrated
			$wpdb->update( $table_conv, [
				'server_id' => $target_server_id,
				'migration_status' => 'migrated',
				'migrated_to_server_id' => $target_server_id,
				'migrated_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' )
			], [ 'id' => $conv['id'] ] );

			$count++;
		}

		// 3. Mark source server as having a migration target (optional, for UI)
		$wpdb->update( $wpdb->prefix . 'postal_servers', [
			'migration_target_server_id' => $target_server_id
		], [ 'id' => $source_server_id ] );

		return [ 'success' => true, 'count' => $count ];
	}
}
