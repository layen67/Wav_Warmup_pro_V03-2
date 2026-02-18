<?php

namespace PostalWarmup\Models;

declare(strict_types=1);

class ReplyTemplateRule {

	public static function get_all(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_reply_template_rules';
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY priority DESC", ARRAY_A ) ?: [];
	}

	public static function save( array $data ): int|false {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_reply_template_rules';

		$defaults = [ 'priority' => 10, 'active' => 1 ];
		$data = array_merge( $defaults, $data );

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
		return (bool) $wpdb->delete( $wpdb->prefix . 'postal_reply_template_rules', [ 'id' => $id ] );
	}

	public static function find_matching_rule( array $context ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_reply_template_rules';

		$sql = "SELECT * FROM $table WHERE active = 1 ORDER BY priority DESC";
		$rules = $wpdb->get_results( $sql, ARRAY_A );

		foreach ( $rules as $rule ) {
			// Prefix Match
			if ( ! empty( $rule['match_prefix'] ) && $rule['match_prefix'] !== $context['prefix'] ) continue;

			// Server Match
			if ( ! empty( $rule['match_server_id'] ) && (int)$rule['match_server_id'] !== $context['server_id'] ) continue;

			// Keyword Match (Subject)
			if ( ! empty( $rule['match_subject_contains'] ) ) {
				if ( stripos( $context['subject'], $rule['match_subject_contains'] ) === false ) continue;
			}

			// Keyword Match (Body)
			if ( ! empty( $rule['match_body_contains'] ) ) {
				if ( stripos( $context['body'], $rule['match_body_contains'] ) === false ) continue;
			}

			return $rule;
		}

		return null;
	}
}
