<?php
// src/Services/ConversationManager.php

declare(strict_types=1);

namespace PostalWarmup\Services;

use PostalWarmup\Models\Database;
use PostalWarmup\Models\Scenario;
use PostalWarmup\Core\TemplateEngine;

/**
 * Manages conversation lifecycle (creation, updates, stage progression).
 */
class ConversationManager {

	private static string $table = 'postal_conversations';

	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::$table;
	}

	public static function get_by_id( int $id ): ?array {
		global $wpdb;
		$table = self::get_table_name();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );
		if ( $row ) {
			$row['templates_sent'] = json_decode( $row['templates_sent'], true ) ?: [];
		}
		return $row;
	}

	public static function get_active_conversations( int $limit = 50 ): array {
		global $wpdb;
		$table = self::get_table_name();
		// Fetch conversations that are active, not migrated, and due for action
		$now = current_time( 'mysql' );
		$sql = "SELECT * FROM $table
				WHERE status = 'active'
				AND migration_status = 'none'
				AND next_scheduled_at <= %s
				LIMIT %d";

		return $wpdb->get_results( $wpdb->prepare( $sql, $now, $limit ), ARRAY_A );
	}

	public static function create_conversation(
		string $contact_email,
		int $server_id,
		int $scenario_id,
		string $initial_message_id = null
	): int {
		global $wpdb;
		$table = self::get_table_name();

		$wpdb->insert( $table, [
			'contact_email' => $contact_email,
			'server_id' => $server_id,
			'scenario_id' => $scenario_id,
			'original_message_id' => $initial_message_id,
			'current_stage' => 0,
			'loop_cycle' => 0,
			'status' => 'active',
			'waiting_for_reply' => 1, // Usually starts by waiting for reply if initiated by user, or 0 if we initiate.
			// Assuming we initiate:
			'last_contact_at' => current_time( 'mysql' ),
			'next_scheduled_at' => current_time( 'mysql' ), // Ready to process immediately
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' )
		] );

		return $wpdb->insert_id;
	}

	public static function update_stage( int $conversation_id, int $stage, ?string $next_run = null ): void {
		global $wpdb;
		$table = self::get_table_name();

		$data = [
			'current_stage' => $stage,
			'updated_at' => current_time( 'mysql' )
		];

		if ( $next_run ) {
			$data['next_scheduled_at'] = $next_run;
		}

		$wpdb->update( $table, $data, [ 'id' => $conversation_id ] );
	}

	public static function mark_reply_received( int $conversation_id, string $body ): void {
		global $wpdb;
		$table = self::get_table_name();

		$wpdb->update( $table, [
			'waiting_for_reply' => 0,
			'pending_reply' => 1,
			'reply_body' => $body,
			'last_contact_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' )
		], [ 'id' => $conversation_id ] );
	}

	public static function log_template_sent( int $conversation_id, string $template_name ): void {
		$conv = self::get_by_id( $conversation_id );
		if ( ! $conv ) return;

		$history = $conv['templates_sent'] ?? [];
		$history[] = [
			'name' => $template_name,
			'sent_at' => current_time( 'mysql' )
		];

		global $wpdb;
		$table = self::get_table_name();
		$wpdb->update( $table, [
			'templates_sent' => json_encode( $history ),
			'waiting_for_reply' => 1, // Now we wait
			'pending_reply' => 0,
			'updated_at' => current_time( 'mysql' )
		], [ 'id' => $conversation_id ] );
	}
}
