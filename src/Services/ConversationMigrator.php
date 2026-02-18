<?php

declare(strict_types=1);

namespace PostalWarmup\Services;

use PostalWarmup\Models\Database;
use PostalWarmup\API\Client;
use PostalWarmup\Admin\Settings;
use PostalWarmup\Services\TemplateLoader;



class ConversationMigrator {

	public static function prepare_migration( int $from_server_id, int $to_server_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_conversations';

		// Load active + paused
		$conversations = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM $table WHERE server_id = %d AND status IN ('active', 'paused')",
			$from_server_id
		) );

		$count = count( $conversations );

		if ( $count > 0 ) {
			// Mark pending
			$placeholders = implode( ',', array_fill( 0, $count, '%d' ) );
			$wpdb->query( $wpdb->prepare(
				"UPDATE $table SET migration_status = 'pending', migrated_to_server_id = %d WHERE id IN ($placeholders)",
				array_merge( [ $to_server_id ], $conversations )
			) );
		}

		return [ 'total' => $count, 'ids' => $conversations ];
	}

	public static function execute_migration( int $from_server_id, int $to_server_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_conversations';

		$from_server = Database::get_server( $from_server_id );
		$to_server = Database::get_server( $to_server_id );

		if ( ! $from_server || ! $to_server ) {
			return [ 'success' => 0, 'failed' => 0, 'error' => 'Server not found' ];
		}

		$pending = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE server_id = %d AND migration_status = 'pending'",
			$from_server_id
		), ARRAY_A );

		$success = 0;
		$failed = 0;

		// Load Handover Template
		$tpl_name = Settings::get( 'default_migration_template', 'migration_handover' );
		$template = TemplateLoader::load( $tpl_name, $from_server['domain'] );

		if ( ! $template ) {
			// Fallback hardcoded
			$template = [
				'subject' => ['Suite à notre échange...'],
				'text'    => ['Bonjour, voici nos nouvelles coordonnées pour continuer notre discussion.'],
				'html'    => ['<p>Bonjour, voici nos nouvelles coordonnées pour continuer notre discussion.</p>']
			];
		}

		foreach ( $pending as $conv ) {
			$prefix = $conv['from_prefix'] ?? 'contact';
			$from_email = $prefix . '@' . $from_server['domain'];
			$reply_to = $prefix . '@' . $to_server['domain'];

			$payload = [
				'to' => [[ 'address' => $conv['contact_email'] ]],
				'from' => "$prefix <$from_email>",
				'reply_to' => $reply_to,
				'subject' => TemplateLoader::pick_random( $template['subject'] ?? [] ),
				'plain_body' => TemplateLoader::pick_random( $template['text'] ?? [] ),
				'html_body' => TemplateLoader::pick_random( $template['html'] ?? [] ),
			];

			// Direct send via Client (bypass QueueManager)
			$result = Client::request( $from_server_id, 'send/message', 'POST', $payload );

			if ( ! is_wp_error( $result ) && isset( $result['message_id'] ) ) {
				// Success
				ConversationManager::clone_to_server( $conv, $to_server_id );

				$wpdb->update( $table, [
					'status' => 'migrated',
					'migration_status' => 'completed',
					'migrated_at' => current_time( 'mysql' )
				], [ 'id' => $conv['id'] ] );

				$success++;
			} else {
				// Failed
				$wpdb->update( $table, [ 'migration_status' => 'failed' ], [ 'id' => $conv['id'] ] );
				Logger::error( "Migration: Failed for conv {$conv['id']}", [ 'error' => is_wp_error($result) ? $result->get_error_message() : 'API Error' ] );
				$failed++;
			}
		}

		return [ 'success' => $success, 'failed' => $failed, 'total' => count($pending) ];
	}

	public static function get_migration_status( int $server_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_conversations';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT migration_status, COUNT(*) as count FROM $table WHERE server_id = %d GROUP BY migration_status",
			$server_id
		), ARRAY_A );

		$stats = [ 'none' => 0, 'pending' => 0, 'completed' => 0, 'failed' => 0 ];
		foreach ( $results as $row ) {
			$stats[ $row['migration_status'] ] = (int) $row['count'];
		}

		return $stats;
	}

	public static function rollback_migration( int $from_server_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_conversations';

		// Reset pending
		$wpdb->query( $wpdb->prepare(
			"UPDATE $table SET migration_status = 'none', migrated_to_server_id = NULL WHERE server_id = %d AND migration_status = 'pending'",
			$from_server_id
		) );

		// Note: Completed migrations are irreversible logic-wise unless we manually delete clones?
		// For MVP, rollback only affects pending.
	}
}
