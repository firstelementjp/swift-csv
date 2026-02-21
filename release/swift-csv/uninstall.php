<?php
/**
 * Uninstall hook for Swift CSV
 *
 * Removes plugin data when the plugin is uninstalled.
 * This file is called when the user uninstalls the plugin via WordPress admin.
 *
 * @package Swift_CSV
 * @since  0.9.4
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Define plugin constants if not already defined
if ( ! defined( 'SWIFT_CSV_PLUGIN_DIR' ) ) {
	define( 'SWIFT_CSV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Clean up plugin data
 *
 * @since 0.9.4
 * @return void
 */
function swift_csv_uninstall() {
	global $wpdb;

	// Delete custom table
	$table_name = $wpdb->prefix . 'swift_csv_batches';
	$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

	// Delete plugin options
	$options = [
		'swift_csv_version',
		'swift_csv_db_version',
		'swift_csv_settings',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete transients
	// Note: WordPress automatically cleans expired transients, but we clean them explicitly
	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_swift_csv_%'" );
	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_swift_csv_%'" );

	// Clear any cached data
	wp_cache_flush();
}

// Run uninstall cleanup
swift_csv_uninstall();
