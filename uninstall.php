<?php
/**
 * Uninstall hook for Swift CSV
 *
 * Removes plugin data when the plugin is uninstalled.
 *
 * @package Swift_CSV
 * @since   0.9.4
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Define plugin constants if not already defined.
if ( ! defined( 'FE_CSV_IMPORT_EXPORT_PLUGIN_DIR' ) ) {
	define( 'FE_CSV_IMPORT_EXPORT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Clean up plugin data
 *
 * @since  0.9.4
 * @return void
 */
function fe_csv_import_export_uninstall() {
	global $wpdb;

	// Check if user wants to remove all data.
	$remove_all_data = true; // Default to true for backward compatibility.
	if ( class_exists( 'FE_CSV_Import_Export_Settings_Helper' ) ) {
		$remove_all_data = (bool) FE_CSV_Import_Export_Settings_Helper::get( 'advanced', 'uninstall_remove_all_data', true );
	}

	if ( ! $remove_all_data ) {
		// User chose to preserve data, only delete custom table.
		$table_name = $wpdb->prefix . 'fe_csv_import_export_batches';
		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "DROP TABLE IF EXISTS $table_name" );
		return;
	}

	// Delete custom table.
	$table_name = $wpdb->prefix . 'fe_csv_import_export_batches';
	include_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( "DROP TABLE IF EXISTS $table_name" );

	// Delete plugin options.
	$options = [
		'fe_csv_import_export_version',
		'fe_csv_import_export_db_version',
		'fe_csv_import_export_settings',
		'fe_csv_import_export_pro_license', // Also remove license data
		'fe_csv_import_export_encryption_key', // Remove encryption key
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete transients
	// Note: WordPress automatically cleans expired transients, but we clean them explicitly
	global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete(
		$wpdb->options,
		[ 'option_name' => '_transient_fe_csv_import_export_%' ],
		[ 'option_name' => 'LIKE' ]
	);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete(
		$wpdb->options,
		[ 'option_name' => '_transient_timeout_fe_csv_import_export_%' ],
		[ 'option_name' => 'LIKE' ]
	);

	// Clear any cached data
	wp_cache_flush();
}

// Run uninstall cleanup
fe_csv_import_export_uninstall();
