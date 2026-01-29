<?php
/**
 * Plugin Name:       Swift CSV
 * Plugin URI:        https://github.com/firstelementjp/swift-csv
 * Description:       軽量でシンプルなCSVインポート/エクスポートプラグイン。カスタム投稿タイプ、カスタムタクソノミー、カスタムフィールドに対応。
 * Version:           0.9.0
 * Author:            FirstElement,Inc.
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

// Define plugin constants.
define( 'SWIFT_CSV_VERSION', '0.9.0' );
define( 'SWIFT_CSV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWIFT_CSV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SWIFT_CSV_BASENAME', plugin_basename( __FILE__ ) );

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
add_action( 'plugins_loaded', 'swift_csv_load_textdomain' );

// Include required files.
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-admin.php';
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-exporter.php';
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-importer.php';
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-batch.php';
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-updater.php';

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
	new Swift_CSV_Exporter();
	new Swift_CSV_Importer();
	new Swift_CSV_Batch();
	new Swift_CSV_Updater( __FILE__ );
}
add_action( 'plugins_loaded', 'swift_csv_init' );

register_activation_hook( __FILE__, 'swift_csv_activate' );

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
	// Create upload directory if needed.
	$upload_dir = wp_upload_dir();
	$csv_dir    = $upload_dir['basedir'] . '/swift-csv';
	if ( ! file_exists( $csv_dir ) ) {
		wp_mkdir_p( $csv_dir );
	}

	// Flush rewrite rules if needed.
	flush_rewrite_rules();
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
	// Flush rewrite rules.
	flush_rewrite_rules();
}
