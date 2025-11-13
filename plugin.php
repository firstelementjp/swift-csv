<?php
/**
 * Plugin Name:       Your Plugin Name
 * Plugin URI:        https://example.com/
 * Description:       Brief description of your plugin.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://example.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       your-plugin-slug
 * Domain Path:       /languages
 *
 * @package           YourPlugin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'YOUR_PLUGIN_VERSION', '1.0.0' );

/**
 * Plugin basename.
 */
define( 'YOUR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin directory path.
 */
define( 'YOUR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'YOUR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load plugin textdomain.
 */
function your_plugin_load_textdomain() {
	load_plugin_textdomain(
		'your-plugin-slug',
		false,
		dirname( YOUR_PLUGIN_BASENAME ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'your_plugin_load_textdomain' );

/**
 * Plugin activation hook.
 */
function your_plugin_activate() {
	// Activation tasks here
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'your_plugin_activate' );

/**
 * Plugin deactivation hook.
 */
function your_plugin_deactivate() {
	// Deactivation tasks here
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'your_plugin_deactivate' );

/**
 * Include core plugin files.
 */
require_once YOUR_PLUGIN_DIR . 'includes/core/class-main.php';
require_once YOUR_PLUGIN_DIR . 'includes/admin/class-admin.php';
require_once YOUR_PLUGIN_DIR . 'includes/i18n/class-i18n.php';

/**
 * Initialize the plugin.
 */
function your_plugin_init() {
	if ( class_exists( 'Your_Plugin_Main' ) ) {
		new Your_Plugin_Main();
	}
}
add_action( 'init', 'your_plugin_init' );
