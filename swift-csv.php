<?php
/**
 * Plugin Name:  Swift CSV
 * Plugin URI:   https://github.com/firstelementjp/swift-csv
 * Description:  Lightweight and simple CSV import/export plugin. Supports custom post types, custom taxonomies, and custom fields.
 * Version:      0.9.8
 * Author:       FirstElement, Inc.
 * Author URI:   https://www.firstelement.co.jp/
 * License:      GPL-2.0+
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:  swift-csv
 * Domain Path:  /languages
 *
 * @package      Swift_CSV
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants.
define( 'SWIFT_CSV_VERSION', '0.9.8' );
define( 'SWIFT_CSV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWIFT_CSV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SWIFT_CSV_BASENAME', plugin_basename( __FILE__ ) );
define( 'SWIFT_CSV_PRO_URL', 'https://www.firstelement.co.jp/products/swift-csv/' );
define( 'SWIFT_CSV_DOCS_URL', 'https://firstelementjp.github.io/swift-csv/#/' );
define( 'SWIFT_CSV_DEEPWIKI_URL', 'https://deepwiki.com/firstelementjp/swift-csv/' );

/**
 * Custom autoloader for Swift CSV classes
 *
 * Uses consistent naming convention for reliable class loading.
 * Class: Swift_CSV_Admin_Assets → File: admin/class-swift-csv-admin-assets.php
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

		// Convert class name to file path.
		$relative_class = substr( $class_name, strlen( $prefix ) );
		$file_name      = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

		// Determine subdirectory based on class naming.
		$sub_dir = '';
		if ( strpos( $relative_class, 'Admin' ) !== false ||
			strpos( $relative_class, 'License_Handler' ) !== false ||
			strpos( $relative_class, 'Updater' ) !== false ||
			strpos( $relative_class, 'Settings' ) !== false ||
			strpos( $relative_class, 'Settings_Helper' ) !== false ||
			strpos( $relative_class, 'Encryption_Utils' ) !== false ) {
			$sub_dir = 'admin/';
		} elseif ( strpos( $relative_class, 'Export' ) !== false ) {
			$sub_dir = 'export/';
		} elseif ( strpos( $relative_class, 'Import' ) !== false ) {
			$sub_dir = 'import/';
		}

		$file_path = $base_dir . $sub_dir . $file_name;

		// Preload base classes if needed.
		$base_classes = [
			'Swift_CSV_Export_Base'                 => $base_dir . 'export/class-swift-csv-export-base.php',
			'Swift_CSV_Import_Base'                 => $base_dir . 'import/class-swift-csv-import-base.php',
			'Swift_CSV_Import_Batch_Processor_Base' => $base_dir . 'import/class-swift-csv-import-batch-processor-base.php',
		];

		foreach ( $base_classes as $base_class => $base_file ) {
			if ( 0 === strpos( $class_name, $base_class ) &&
				$base_class !== $class_name &&
				file_exists( $base_file ) ) {
				require_once $base_file;
			}
		}

		// Load the class file if exists.
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
add_action( 'plugins_loaded', 'swift_csv_init', 10 );

/**
 * Load plugin textdomain
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
	// Initialize settings helper and migrate legacy options.
	if ( class_exists( 'Swift_CSV_Settings_Helper' ) ) {
		Swift_CSV_Settings_Helper::migrate_legacy_options();
	}

	if ( class_exists( 'Swift_CSV_License_Handler' ) ) {
		Swift_CSV_License_Handler::register_license_resync_cron();
		add_action( 'admin_init', [ 'Swift_CSV_License_Handler', 'maybe_schedule_license_resync' ] );
	}

	if ( is_admin() ) {
		new Swift_CSV_Admin();
	}
	new Swift_CSV_Ajax_Import_Unified();
	new Swift_CSV_Ajax_Export_Unified();

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
	if ( class_exists( 'Swift_CSV_License_Handler' ) ) {
		Swift_CSV_License_Handler::clear_license_resync_schedule();
		Swift_CSV_License_Handler::register_license_resync_cron();
		Swift_CSV_License_Handler::maybe_schedule_license_resync();
	}

	// Create temp directory and cleanup old files.
	$upload_dir = wp_upload_dir();
	$temp_dir   = $upload_dir['basedir'] . '/swift-csv-temp';
	if ( ! file_exists( $temp_dir ) ) {
		wp_mkdir_p( $temp_dir );
	}

	global $wp_filesystem;
	if ( ! $wp_filesystem ) {
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
	}

	// Create .htaccess to restrict web access.
	$htaccess_file = $temp_dir . '/.htaccess';
	if ( ! file_exists( $htaccess_file ) && $wp_filesystem ) {
		$wp_filesystem->put_contents( $htaccess_file, "Deny from all\n" );
	}

	// Create index.php to prevent directory listing.
	$index_file = $temp_dir . '/index.php';
	if ( ! file_exists( $index_file ) && $wp_filesystem ) {
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
	if ( class_exists( 'Swift_CSV_License_Handler' ) ) {
		Swift_CSV_License_Handler::clear_license_resync_schedule();
	}
}
