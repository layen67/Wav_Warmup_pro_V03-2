<?php

declare(strict_types=1);

namespace PostalWarmup\Services;

use PostalWarmup\Models\Database;



/**
 * Chargeur de templates (Helper)
 */
class TemplateLoader {

	private static array $cache = [];

	public static function load( string $name, ?string $domain = null ): ?array {
		if ( isset( self::$cache[ $name ] ) ) {
			return self::$cache[ $name ];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';
		
		$db_template = $wpdb->get_row( $wpdb->prepare( "SELECT id, data, folder_id, status, tags, timezone, default_label FROM $table WHERE name = %s", $name ), ARRAY_A );
		
		if ( $db_template ) {
			$data = json_decode( $db_template['data'], true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$data['id'] = (int)$db_template['id'];
				$data['name'] = $name;
				$data['folder_id'] = (int)$db_template['folder_id'];
				$data['status'] = $db_template['status'];
				$data['timezone'] = $db_template['timezone'];
				$data['default_label'] = $db_template['default_label']; // Crucial for shortcode fallback

				if ( ! empty( $db_template['tags'] ) ) {
					$data['tags'] = explode( ',', $db_template['tags'] );
				}

				self::$cache[ $name ] = $data;
				return $data;
			}
		}

		if ( defined( 'PW_TEMPLATES_DIR' ) ) {
			$file = PW_TEMPLATES_DIR . $name . '.json';
			if ( file_exists( $file ) ) {
				$content = file_get_contents( $file );
				$data = json_decode( $content, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					if (!isset($data['name'])) {
						$data['name'] = $name;
					}
					self::$cache[ $name ] = $data;
					return $data;
				}
			}
		}

		return null;
	}

	public static function get_default_template(): array {
		return self::get_fallback();
	}

	public static function get_fallback(): array {
		return [
			'subject' => [ get_option( 'pw_default_subject', 'Hello' ) ],
			'text' => [ get_option( 'pw_default_text', 'This is a warmup email.' ) ],
			'html' => [ get_option( 'pw_default_html', '<p>This is a warmup email.</p>' ) ],
			'from_name' => [ get_option( 'pw_default_from_name', 'Support' ) ],
			'reply_to' => [],
			'default_label' => __( 'Nous contacter', 'postal-warmup' )
		];
	}

	public static function pick_random( array $items ): string {
		if ( empty( $items ) ) return '';

		// Check if it's a weighted array of arrays: [['Value', 90], ['Other', 10]]
		if ( isset( $items[0] ) && is_array( $items[0] ) ) {
			$total_weight = 0;
			$weighted_items = [];

			foreach ( $items as $item ) {
				if ( count( $item ) >= 2 && is_numeric( $item[1] ) ) {
					$weight = (int) $item[1];
					$weighted_items[] = [ 'value' => (string)$item[0], 'weight' => $weight ];
					$total_weight += $weight;
				} else {
					// Fallback for malformed
					$weighted_items[] = [ 'value' => (string)($item[0] ?? ''), 'weight' => 1 ];
					$total_weight += 1;
				}
			}

			if ( $total_weight > 0 ) {
				$rand = mt_rand( 1, $total_weight );
				$current = 0;
				foreach ( $weighted_items as $item ) {
					$current += $item['weight'];
					if ( $rand <= $current ) {
						return $item['value'];
					}
				}
			}
			return $weighted_items[0]['value'] ?? '';
		}

		// Simple array of strings
		$key = array_rand( $items );
		$val = $items[$key];

		if ( is_array( $val ) ) {
			return (string)($val[0] ?? '');
		}

		return (string)$val;
	}

	public static function pick_weighted( array $array ): string {
		return self::pick_random( $array );
	}

	public static function apply_placeholders( string $text, array $vars ): string {
		foreach ( $vars as $key => $value ) {
			$text = str_replace( "{{{$key}}}", (string)$value, $text );
		}
		return $text;
	}
}
