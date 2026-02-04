<?php
/**
 * Emergency reset after database freeze
 */

// WordPress environment
define( 'WP_USE_THEMES', false );
require_once( dirname( __FILE__ ) . '/../../../wp-config.php' );

global $wpdb;

echo "Emergency reset after database freeze...\n";

// Clear all cron-related data
echo "Clearing cron data...\n";
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%cron%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient%'" );

// Clear batch data
echo "Clearing batch data...\n";
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}swift_csv_batches" );

// Reset WordPress optimizations
echo "Resetting WordPress optimizations...\n";
wp_defer_term_counting( false );
wp_defer_comment_counting( false );

// Force database optimization
echo "Optimizing database...\n";
$wpdb->query( "OPTIMIZE TABLE {$wpdb->posts}" );
$wpdb->query( "OPTIMIZE TABLE {$wpdb->postmeta}" );
$wpdb->query( "OPTIMIZE TABLE {$wpdb->options}" );

echo "Emergency reset completed!\n";
echo "You can now try importing again.\n";
echo "<a href='/wp-admin/'>Return to admin</a>\n";
