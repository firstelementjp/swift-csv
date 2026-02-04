<?php
/**
 * Force kill all MySQL queries and processes
 */

// WordPress environment
define( 'WP_USE_THEMES', false );
require_once( dirname( __FILE__ ) . '/../../../wp-config.php' );

global $wpdb;

echo "Killing all MySQL processes...\n";

// Show current processes
$processes = $wpdb->get_results( "SHOW PROCESSLIST" );

echo "Current processes:\n";
foreach ( $processes as $process ) {
    echo "ID: {$process->Id}, User: {$process->User}, DB: {$process->db}, Time: {$process->Time}, State: {$process->State}\n";
    
    // Kill long-running queries
    if ( $process->Time > 10 || strpos( $process->Info, 'UPDATE' ) !== false || strpos( $process->Info, 'INSERT' ) !== false ) {
        $kill_result = $wpdb->query( "KILL {$process->Id}" );
        echo "Killed process {$process->Id}\n";
    }
}

echo "All processes killed. You can now try importing again.\n";
echo "<a href='/wp-admin/'>Return to admin</a>\n";
