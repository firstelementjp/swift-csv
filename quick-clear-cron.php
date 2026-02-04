<?php
/**
 * Quick cron clearer - access directly
 */

// WordPress environment
define( 'WP_USE_THEMES', false );
require_once( dirname( __FILE__ ) . '/../../../wp-config.php' );

global $wpdb;

echo "Clearing stuck cron jobs...\n";

// Clear all cron jobs
$result = $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name = 'cron'" );

if ( $result !== false ) {
    echo "Cron cleared successfully!\n";
    
    // Reset basic cron
    wp_schedule_event( time(), 'daily', 'wp_version_check' );
    echo "Basic cron reset.\n";
} else {
    echo "Error clearing cron: " . $wpdb->last_error . "\n";
}

echo "You can now try importing again.\n";
echo "<a href='/wp-admin/'>Return to admin</a>\n";
