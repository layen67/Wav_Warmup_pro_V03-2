<?php

namespace PostalWarmup\Services;

use PostalWarmup\Models\Database;

declare(strict_types=1);

class ConversationManager {

	public static function record_reply( array $data ): int|false {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_conversations';

		$defaults = [
			'status' => 'active',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
			'last_contact_at' => current_time( 'mysql' ),
			'templates_sent' => '[]'
		];

		$data = array_merge( $defaults, $data );

		$result = $wpdb->insert( $table, $data );

		return $result ? (int) $wpdb->insert_id : false;
	}

	public static function get_active_for_contact( string $email, int $server_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_conversations';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE contact_email = %s AND server_id = %d AND status = 'active' LIMIT 1",
			$email, $server_id
		), ARRAY_A );
	}

	public static function get( int $id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_conversations';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_conversations';
		$data['updated_at'] = current_time( 'mysql' );

		return (bool) $wpdb->update( $table, $data, [ 'id' => $id ] );
	}

	public static function update_last_contact( int $id ): void {
		self::update( $id, [ 'last_contact_at' => current_time( 'mysql' ) ] );
	}

	public static function set_waiting( int $id, bool $waiting ): void {
		self::update( $id, [ 'waiting_for_reply' => $waiting ? 1 : 0 ] );
	}

	public static function add_template_history( int $id, string $template_name ): void {
		$conv = self::get( $id );
		if ( ! $conv ) return;

		$history = json_decode( $conv['templates_sent'] ?? '[]', true ) ?: [];
		$history[] = $template_name;

		self::update( $id, [ 'templates_sent' => json_encode( $history ) ] );
	}

	public static function cancel_all_for_contact( string $email ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_conversations';

		// Get IDs first to cancel scheduled actions
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE contact_email = %s AND status = 'active'", $email ) );

		foreach ( $ids as $id ) {
			if ( function_exists( 'as_unschedule_action' ) ) {
				as_unschedule_action( 'pw_send_engagement_step', [ 'conversation_id' => $id ] );
				as_unschedule_action( 'pw_check_contact_silence', [ 'conversation_id' => $id ] );
			}
		}

		$wpdb->update( $table, [ 'status' => 'cancelled' ], [ 'contact_email' => $email, 'status' => 'active' ] );
	}

	public static function clone_to_server( array $conv, int $to_server_id ): int|false {
		$new_data = [
			'contact_email' => $conv['contact_email'],
			'server_id'     => $to_server_id,
			'scenario_id'   => $conv['scenario_id'],
			'from_prefix'   => $conv['from_prefix'],
			'current_stage' => $conv['current_stage'],
			'loop_cycle'    => $conv['loop_cycle'],
			'templates_sent'=> $conv['templates_sent'],
			'status'        => 'waiting_for_reply', // Assume waiting after handover
			'waiting_for_reply' => 1
		];

		return self::record_reply( $new_data );
	}
}
