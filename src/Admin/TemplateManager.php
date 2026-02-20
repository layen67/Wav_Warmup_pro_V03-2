<?php

declare(strict_types=1);

namespace PostalWarmup\Admin;

use PostalWarmup\Models\Database;
use PostalWarmup\Services\TemplateLoader;



/**
 * Gestionnaire des Templates (CRUD)
 */
class TemplateManager {

	public static function get_all_with_meta(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';
		
		// Use SELECT * to avoid SQL errors if new columns (timezone, default_label) are missing due to failed migration
		$templates = $wpdb->get_results( "SELECT * FROM $table ORDER BY name ASC", ARRAY_A );

		if ( ! is_array( $templates ) ) {
			return [];
		}

		foreach ( $templates as &$tpl ) {
			// Decode data to count variants
			$data = json_decode( $tpl['data'], true ) ?: [];
			$tpl['variants'] = [
				'subject' => count( $data['subject'] ?? [] ),
				'text'    => count( $data['text'] ?? [] ),
				'html'    => count( $data['html'] ?? [] ),
			];

			// Ensure keys exist if migration failed
			$tpl['timezone'] = $tpl['timezone'] ?? '';
			$tpl['default_label'] = $tpl['default_label'] ?? '';

			// Tags handling
			if ( ! empty( $tpl['tags'] ) && is_string( $tpl['tags'] ) ) {
				// Check if JSON or CSV
				if ( $tpl['tags'][0] === '[' ) {
					$decoded = json_decode( $tpl['tags'], true );
					if ( is_array( $decoded ) ) {
						$tpl['tags'] = $decoded;
					} else {
						$tpl['tags'] = [];
					}
				} else {
					// CSV
					$tpl['tags'] = explode( ',', $tpl['tags'] );
				}

				if ( is_array( $tpl['tags'] ) ) {
					// Normalize to objects if they are strings
					$normalized = [];
					foreach ( $tpl['tags'] as $t ) {
						if ( is_string( $t ) ) {
							$normalized[] = [ 'name' => trim( $t ) ];
						} elseif ( is_array( $t ) && isset( $t['name'] ) ) {
							$normalized[] = $t;
						}
					}
					$tpl['tags'] = $normalized;
				}
			} else {
				$tpl['tags'] = [];
			}
		}

		return $templates;
	}

	public static function get_template( string $name ): ?array {
		return TemplateLoader::load( $name );
	}

	public static function save_template( string $name, array $data, array $meta ): int|\WP_Error {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';

		if ( empty( $name ) ) {
			return new \WP_Error( 'invalid_name', 'Le nom du template est requis.' );
		}

		$json_data = json_encode( $data, JSON_UNESCAPED_UNICODE );
		$tags_str = is_array( $meta['tags'] ) ? implode( ',', $meta['tags'] ) : '';

		$fields = [
			'name'          => $name,
			'data'          => $json_data,
			'folder_id'     => $meta['folder_id'],
			'status'        => $meta['status'],
			'tags'          => $tags_str,
			'timezone'      => $meta['timezone'],
			'default_label' => $data['default_label'] ?? '',
			'updated_at'    => current_time( 'mysql' )
		];

		// Check ID
		$id = isset( $meta['id'] ) ? (int) $meta['id'] : 0;

		if ( $id > 0 ) {
			// Update
			// Versioning
			self::create_version( $id, 'Auto-save before update' );

			$updated = $wpdb->update( $table, $fields, [ 'id' => $id ] );
			if ( $updated === false ) {
				return new \WP_Error( 'db_error', 'Erreur lors de la mise à jour.' );
			}
			return $id;
		} else {
			// Create
			// Check name uniqueness
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE name = %s", $name ) );
			if ( $exists ) {
				return new \WP_Error( 'duplicate_name', 'Un template avec ce nom existe déjà.' );
			}

			$fields['created_at'] = current_time( 'mysql' );
			$inserted = $wpdb->insert( $table, $fields );

			if ( $inserted ) {
				return (int) $wpdb->insert_id;
			}
			return new \WP_Error( 'db_error', 'Erreur lors de la création.' );
		}
	}

	public static function delete_template( string $name ): bool|\WP_Error {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';

		$deleted = $wpdb->delete( $table, [ 'name' => $name ] );

		if ( $deleted ) {
			return true;
		}
		return new \WP_Error( 'db_error', 'Erreur lors de la suppression.' );
	}

	public static function duplicate_template( string $source_name, string $new_name ): int|\WP_Error {
		$template = self::get_template( $source_name );
		if ( ! $template ) {
			return new \WP_Error( 'not_found', 'Template source introuvable.' );
		}
		
		// Clean data for new entry
		$data = [
			'subject'          => $template['subject'] ?? [],
			'text'             => $template['text'] ?? [],
			'html'             => $template['html'] ?? [],
			'from_name'        => $template['from_name'] ?? [],
			'mailto_subject'   => $template['mailto_subject'] ?? [],
			'mailto_body'      => $template['mailto_body'] ?? [],
			'mailto_from_name' => $template['mailto_from_name'] ?? [],
			'default_label'    => $template['default_label'] ?? '',
		];

		$meta = [
			'id'        => 0,
			'folder_id' => $template['folder_id'] ?? self::ensure_uncategorized_folder(),
			'status'    => 'active',
			'tags'      => $template['tags'] ?? [],
			'timezone'  => $template['timezone'] ?? ''
		];

		return self::save_template( $new_name, $data, $meta );
	}

	public static function save_category( string $name, int $parent_id, string $color, int $id = 0 ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_template_folders';
		
		$data = [
			'name' => $name,
			'parent_id' => $parent_id > 0 ? $parent_id : null,
			'color' => $color
		];

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, [ 'id' => $id ] );
			return $id;
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			return (int) $wpdb->insert_id;
		}
	}

	public static function delete_category( int $id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_template_folders';
		$table_tpl = $wpdb->prefix . 'postal_templates';
		
		// Move templates to Uncategorized
		$uncat = self::ensure_uncategorized_folder();
		if ( $uncat !== $id ) {
			$wpdb->update( $table_tpl, [ 'folder_id' => $uncat ], [ 'folder_id' => $id ] );

			// Move children folders to root
			$wpdb->update( $table, [ 'parent_id' => null ], [ 'parent_id' => $id ] );

			$wpdb->delete( $table, [ 'id' => $id ] );
		}
	}

	public static function ensure_uncategorized_folder(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_template_folders';

		$id = $wpdb->get_var( "SELECT id FROM $table WHERE name = 'Non catégorisé' LIMIT 1" );
		if ( $id ) return (int) $id;

		$wpdb->insert( $table, [ 'name' => 'Non catégorisé', 'color' => '#646970', 'created_at' => current_time( 'mysql' ) ] );
		return (int) $wpdb->insert_id;
	}

	public static function get_folders_tree(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_template_folders';
		$table_tpl = $wpdb->prefix . 'postal_templates';

		$folders = $wpdb->get_results( "SELECT * FROM $table ORDER BY name ASC", ARRAY_A );

		// Count templates per folder
		$counts = $wpdb->get_results( "SELECT folder_id, COUNT(*) as count FROM $table_tpl GROUP BY folder_id", OBJECT_K );

		$tree = [];
		$map = [];

		// Initialize ALL templates node
		$total_templates = $wpdb->get_var( "SELECT COUNT(*) FROM $table_tpl" );
		$tree[] = [
			'id' => '',
			'name' => 'Tous les templates',
			'count' => $total_templates,
			'color' => '#2271b1',
			'children' => []
		];

		foreach ( $folders as &$f ) {
			$f['count'] = isset( $counts[ $f['id'] ] ) ? (int) $counts[ $f['id'] ]->count : 0;
			$f['children'] = [];
			$map[ $f['id'] ] = &$f;
		}

		foreach ( $folders as &$f ) {
			if ( $f['parent_id'] && isset( $map[ $f['parent_id'] ] ) ) {
				$map[ $f['parent_id'] ]['children'][] = &$f;
			} else {
				$tree[] = &$f;
			}
		}

		return $tree;
	}

	public static function toggle_favorite( int $id, bool $favorite ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';
		$wpdb->update( $table, [ 'is_favorite' => $favorite ? 1 : 0 ], [ 'id' => $id ] );
	}

	public static function move_template( int $id, int $folder_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';
		$wpdb->update( $table, [ 'folder_id' => $folder_id ], [ 'id' => $id ] );
	}

	public static function update_status( int $id, string $status ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';
		$wpdb->update( $table, [ 'status' => $status ], [ 'id' => $id ] );
	}

	public static function create_version( int $template_id, string $comment = '' ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';
		$table_v = $wpdb->prefix . 'postal_template_versions';

		$current = $wpdb->get_row( $wpdb->prepare( "SELECT data FROM $table WHERE id = %d", $template_id ), ARRAY_A );
		if ( ! $current ) return;

		$last_version = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(version_number) FROM $table_v WHERE template_id = %d", $template_id ) );
		$new_version = $last_version ? $last_version + 1 : 1;

		$wpdb->insert( $table_v, [
			'template_id' => $template_id,
			'data' => $current['data'],
			'version_number' => $new_version,
			'comment' => $comment,
			'created_at' => current_time( 'mysql' ),
			'created_by' => get_current_user_id()
		] );
	}

	public static function get_versions( int $template_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_template_versions';
		$users = $wpdb->users;
		
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT v.*, u.display_name as author_name
			FROM $table v
			LEFT JOIN $users u ON v.created_by = u.ID
			WHERE template_id = %d
			ORDER BY version_number DESC",
			$template_id
		), ARRAY_A ) ?: [];
	}

	public static function restore_version( int $version_id ): bool|\WP_Error {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';
		$table_v = $wpdb->prefix . 'postal_template_versions';
		
		$version = $wpdb->get_row( $wpdb->prepare( "SELECT template_id, data FROM $table_v WHERE id = %d", $version_id ), ARRAY_A );
		if ( ! $version ) return new \WP_Error( 'not_found', 'Version introuvable.' );
		
		// Create a backup of current before restoring? Yes, implicitly via save_template logic if we used it,
		// but here we do direct update. Let's create a backup version first.
		self::create_version( (int) $version['template_id'], 'Auto-save before restore' );
		
		$restored = $wpdb->update( $table, [ 'data' => $version['data'] ], [ 'id' => $version['template_id'] ] );

		if ( $restored !== false ) return true;
		return new \WP_Error( 'db_error', 'Erreur lors de la restauration.' );
	}
}
