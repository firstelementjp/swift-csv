<?php
/**
 * Admin page to clear stuck cron jobs
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add admin menu item
add_action( 'admin_menu', 'swift_csv_clear_cron_menu' );

function swift_csv_clear_cron_menu() {
    add_submenu_page(
        'swift-csv',
        'Clear Cron',
        'Clear Cron',
        'manage_options',
        'swift-csv-clear-cron',
        'swift_csv_clear_cron_page'
    );
    
    // Debug: Check if menu was registered
    global $submenu;
    if ( isset( $submenu['swift-csv'] ) ) {
        error_log( 'Swift CSV: Submenu registered successfully: ' . print_r( $submenu['swift-csv'], true ) );
    } else {
        error_log( 'Swift CSV: Submenu registration failed' );
    }
}

function swift_csv_clear_cron_page() {
    global $wpdb;
    
    if ( isset( $_POST['clear_cron'] ) && check_admin_referer( 'clear_cron_nonce' ) ) {
        // Clear all cron jobs
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name = 'cron'" );
        
        // Reset basic cron
        wp_schedule_event( time(), 'daily', 'wp_version_check' );
        
        echo '<div class="notice notice-success"><p>Cron jobs cleared successfully!</p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1>Clear Stuck Cron Jobs</h1>
        <p>If you're experiencing "Lock wait timeout" errors, clearing stuck cron jobs may help.</p>
        
        <form method="post">
            <?php wp_nonce_field( 'clear_cron_nonce' ); ?>
            <p>
                <input type="submit" name="clear_cron" class="button button-primary" value="Clear All Cron Jobs">
            </p>
        </form>
        
        <h3>Current Cron Jobs</h3>
        <pre><?php print_r( _get_cron_array() ); ?></pre>
    </div>
    <?php
}
