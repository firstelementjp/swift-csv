<?php
/**
 * Simple CSV Import - No transactions, no batches, no locks
 */

// WordPress environment
define( 'WP_USE_THEMES', false );
require_once( dirname( __FILE__ ) . '/../../../wp-config.php' );

global $wpdb;

// Disable everything that could cause locks
define( 'DISABLE_WP_CRON', true );
wp_defer_term_counting( true );
wp_defer_comment_counting( true );

// Set MySQL to avoid locks
$wpdb->query( 'SET SESSION autocommit = 1' );
$wpdb->query( 'SET SESSION innodb_lock_wait_timeout = 1' );
$wpdb->query( 'SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED' );

echo "Starting simple CSV import...\n";

// Read CSV file
$csv_file = $_FILES['csv_file']['tmp_name'] ?? '';
if ( ! $csv_file ) {
    die( "No CSV file uploaded\n" );
}

$csv_content = file_get_contents( $csv_file );
$lines = explode( "\n", $csv_content );
$headers = str_getcsv( array_shift( $lines ) );

$imported = 0;
$errors = 0;

foreach ( $lines as $index => $line ) {
    if ( empty( trim( $line ) ) ) continue;
    
    $data = str_getcsv( $line );
    $title = $data[0] ?? 'Untitled';
    $content = $data[1] ?? '';
    
    try {
        // Simple post insert without transactions
        $post_id = wp_insert_post( [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'post'
        ] );
        
        if ( $post_id ) {
            $imported++;
            echo "Imported: $title (ID: $post_id)\n";
        } else {
            $errors++;
            echo "Error importing: $title\n";
        }
    } catch ( Exception $e ) {
        $errors++;
        echo "Exception: " . $e->getMessage() . "\n";
    }
    
    // Small delay to avoid overwhelming
    usleep( 10000 ); // 10ms
}

echo "Import completed: $imported imported, $errors errors\n";
echo "<a href='/wp-admin/'>Return to admin</a>\n";
