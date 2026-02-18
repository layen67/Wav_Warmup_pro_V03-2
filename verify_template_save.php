<?php
// Mock WordPress environment for minimal testing
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', './' );
if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type ) { return date( 'Y-m-d H:i:s' ); }
}
if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() { return 1; }
}

// Mock WPDB
class MockWPDB {
    public $prefix = 'wp_';
    public $insert_id = 123;

    public function get_var( $query ) {
        return null; // Simulate no duplicate name
    }

    public function prepare( $query, ...$args ) {
        return vsprintf( str_replace( '%s', "'%s'", str_replace( '%d', '%d', $query ) ), $args );
    }

    public function insert( $table, $data ) {
        echo "INSERT into $table: " . json_encode($data) . "\n";
        return 1;
    }

    public function update( $table, $data, $where ) {
        echo "UPDATE $table: " . json_encode($data) . " WHERE " . json_encode($where) . "\n";
        return 1;
    }

    public function get_row( $query, $type ) {
        return ['data' => '{}'];
    }
}

$wpdb = new MockWPDB();

// Mock WP_Error
class WP_Error {
    public function __construct( $code, $message ) {
        echo "WP_Error: [$code] $message\n";
    }
}

// Include TemplateManager (assuming dependencies are mocked or minimal)
require_once 'src/Admin/TemplateManager.php';

// Test Case
$name = 'Test Template';
$data = [
    'subject' => ['Hello'],
    'text' => ['World'],
    'html' => ['<p>World</p>'],
    'default_label' => 'Test Button'
];
$meta = [
    'id' => 0,
    'folder_id' => 1,
    'status' => 'active',
    'tags' => ['tag1', 'tag2'],
    'timezone' => 'UTC'
];

echo "Testing Template Save...\n";
\PostalWarmup\Admin\TemplateManager::save_template( $name, $data, $meta );
