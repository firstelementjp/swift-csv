<?php
/**
 * Plugin Name:       Swift CSV
 * Plugin URI:        https://github.com/firstelementjp/swift-csv
 * Description:       Lightweight and simple CSV import/export plugin. Supports custom post types, custom taxonomies, and custom fields.
 * Version:           0.9.7
 * Author:            FirstElement, Inc.
 * Author URI:        https://www.firstelement.co.jp/
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       swift-csv
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants.
define( 'SWIFT_CSV_VERSION', '0.9.7' );
define( 'SWIFT_CSV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWIFT_CSV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SWIFT_CSV_BASENAME', plugin_basename( __FILE__ ) );
define( 'SWIFT_CSV_PRO_URL', 'https://www.firstelement.co.jp/swift-csv/pro/' );
define( 'SWIFT_CSV_DOCS_URL', 'https://firstelementjp.github.io/swift-csv/#/' );
define( 'SWIFT_CSV_DEEPWIKI_URL', 'https://deepwiki.com/firstelementjp/swift-csv' );

// Include required files.
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-admin.php';
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-license-handler.php';
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-updater.php';
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-helper.php';
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-import-csv.php';
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-import-row-context.php';
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-import-meta-tax.php';
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-import-persister.php';
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-import-row-processor.php';
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-ajax-import.php';
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-ajax-export.php';

// Register plugin hooks.
register_activation_hook( __FILE__, 'swift_csv_activate' );
register_deactivation_hook( __FILE__, 'swift_csv_deactivate' );

// Initialize plugin.
add_action( 'plugins_loaded', 'swift_csv_init' );
add_action( 'plugins_loaded', 'swift_csv_load_textdomain' );

/**
 * Load plugin textdomain.
 *
 * Loads the plugin text domain for internationalization.
 *
 * @since 0.9.0
 * @return void
 */
function swift_csv_load_textdomain() {
	load_plugin_textdomain(
		'swift-csv',
		false,
		dirname( SWIFT_CSV_BASENAME ) . '/languages'
	);
}

/**
 * Initialize Swift CSV plugin
 *
 * Creates instances of all main plugin classes and sets up hooks.
 * This function is called on 'plugins_loaded' action.
 *
 * @since 0.9.0
 * @return void
 */
function swift_csv_init() {
	new Swift_CSV_Admin();
	new Swift_CSV_Ajax_Import();
	new Swift_CSV_Ajax_Export();
	new Swift_CSV_Updater( __FILE__ );
}

/**
 * Plugin activation hook
 *
 * Creates necessary directories and sets up initial plugin state.
 * This function runs only when the plugin is activated.
 *
 * @since 0.9.0
 * @return void
 */
function swift_csv_activate() {
	// Clean up any orphaned cron jobs from previous installations.
	wp_clear_scheduled_hook( 'swift_csv_process_batch' );

	// Create upload directory if needed.
	$upload_dir = wp_upload_dir();
	$csv_dir    = $upload_dir['basedir'] . '/swift-csv';
	if ( ! file_exists( $csv_dir ) ) {
		wp_mkdir_p( $csv_dir );
	}

	// Create temp directory and cleanup old files.
	$temp_dir = $upload_dir['basedir'] . '/swift-csv-temp';
	if ( ! file_exists( $temp_dir ) ) {
		wp_mkdir_p( $temp_dir );
	}

	// Create .htaccess to restrict web access.
	$htaccess_file = $temp_dir . '/.htaccess';
	if ( ! file_exists( $htaccess_file ) ) {
		file_put_contents( $htaccess_file, "Deny from all\n" );
	}

	// Cleanup old temp files (older than 24 hours).
	$files = glob( $temp_dir . '/*.csv' );
	if ( $files ) {
		foreach ( $files as $file ) {
			if ( time() - filemtime( $file ) > 86400 ) { // 24 hours
				unlink( $file );
			}
		}
	}
}

register_deactivation_hook( __FILE__, 'swift_csv_deactivate' );

/**
 * Plugin deactivation hook
 *
 * Cleans up when the plugin is deactivated.
 *
 * @since 0.9.0
 * @return void
 */
function swift_csv_deactivate() {
	// Clean up all scheduled cron jobs.
	wp_clear_scheduled_hook( 'swift_csv_process_batch' );
}
