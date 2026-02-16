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
 *
 * @package           Swift_CSV
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Custom debug log path
$log_file = plugin_dir_path( __FILE__ ) . 'debug.log';
ini_set( 'error_log', $log_file );

// Define plugin constants.
define( 'SWIFT_CSV_VERSION', '0.9.7' );
define( 'SWIFT_CSV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWIFT_CSV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SWIFT_CSV_BASENAME', plugin_basename( __FILE__ ) );
define( 'SWIFT_CSV_PRO_URL', 'https://www.firstelement.co.jp/swift-csv/pro/' );
define( 'SWIFT_CSV_DOCS_URL', 'https://firstelementjp.github.io/swift-csv/#/' );
define( 'SWIFT_CSV_DEEPWIKI_URL', 'https://deepwiki.com/firstelementjp/swift-csv' );

/**
 * Custom autoloader for Swift CSV classes.
 *
 * Automatically loads classes following WordPress naming convention:
 * Swift_CSV_Admin → includes/admin/class-swift-csv-admin.php
 * Swift_CSV_Import_* → includes/import/class-swift-csv-import-*.php
 * Swift_CSV_Export_* → includes/export/class-swift-csv-export-*.php
 *
 * @since 0.9.8
 * @param string $class_name Class name to load.
 */
spl_autoload_register(
	function ( $class_name ) {
		$prefix   = 'Swift_CSV_';
		$base_dir = SWIFT_CSV_PLUGIN_DIR . 'includes/';

		// Only handle classes with Swift_CSV_ prefix.
		if ( strncmp( $prefix, $class_name, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		// Convert class name to file name.
		$relative_class = substr( $class_name, strlen( $prefix ) );
		$file_name      = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

		// Determine subdirectory based on class type.
		$sub_dir = '';
		if ( strpos( $relative_class, 'Admin' ) !== false ||
			strpos( $relative_class, 'License_Handler' ) !== false ||
			strpos( $relative_class, 'Updater' ) !== false ) {
			$sub_dir = 'admin/';
		} elseif ( strpos( $relative_class, 'Import' ) !== false ||
					strpos( $relative_class, 'Ajax_Import' ) !== false ) {
			$sub_dir = 'import/';
		} elseif ( strpos( $relative_class, 'Export' ) !== false ||
					strpos( $relative_class, 'Ajax_Export' ) !== false ) {
			$sub_dir = 'export/';
		}

		$file_path = $base_dir . $sub_dir . $file_name;

		// Load file if exists.
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
);

// Register plugin hooks.
register_activation_hook( __FILE__, 'swift_csv_activate' );
register_deactivation_hook( __FILE__, 'swift_csv_deactivate' );

// Initialize plugin.
add_action( 'init', 'swift_csv_load_textdomain', 0 );
add_action( 'init', 'swift_csv_init', 10 );

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
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$wp_filesystem->put_contents( $htaccess_file, "Deny from all\n" );
	}

	// Create index.php to prevent directory listing.
	$index_file = $temp_dir . '/index.php';
	if ( ! file_exists( $index_file ) ) {
		$wp_filesystem->put_contents( $index_file, "<?php\n// Silence is golden.\n" );
	}

	// Cleanup old temp files (older than 24 hours).
	$files = glob( $temp_dir . '/*.csv' );
	if ( $files ) {
		foreach ( $files as $file ) {
			if ( time() - filemtime( $file ) > 86400 ) { // 24 hours
				wp_delete_file( $file );
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
