<?php
/**
 * Admin class for managing plugin interface
 *
 * This file contains the admin functionality for the Swift CSV plugin,
 * including menu creation, style enqueueing, and rendering of the
 * import/export interface.
 *
 * @since   0.9.1
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class for Swift CSV plugin
 *
 * Handles admin menu, settings, and UI for CSV import/export functionality.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_Admin {

	/**
	 * Admin assets handler
	 *
	 * @var Swift_CSV_Admin_Assets
	 */
	private $assets;

	/**
	 * Admin AJAX handler
	 *
	 * @var Swift_CSV_Admin_Ajax
	 */
	private $ajax;

	/**
	 * Admin settings handler
	 *
	 * @var Swift_CSV_Admin_Settings
	 */
	private $settings;

	/**
	 * Admin page renderer
	 *
	 * @var Swift_CSV_Admin_Page
	 */
	private $page;

	/**
	 * Constructor
	 *
	 * Sets up WordPress hooks for admin menu and styles.
	 *
	 * @since  0.9.0
	 * @return void
	 */
	public function __construct() {
		$this->assets   = new Swift_CSV_Admin_Assets();
		$this->ajax     = new Swift_CSV_Admin_Ajax();
		$this->settings = new Swift_CSV_Admin_Settings( $this );
		$this->page     = new Swift_CSV_Admin_Page( $this );

		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this->assets, 'enqueue_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this->assets, 'enqueue_scripts' ] );

		add_action( 'admin_init', [ $this->settings, 'register_settings' ] );
		add_action( 'wp_ajax_swift_csv_save_advanced_settings', [ $this->ajax, 'ajax_save_advanced_settings' ] );
		add_action( 'wp_ajax_swift_csv_pro_manage_license', [ $this->ajax, 'ajax_manage_license' ] );
	}

	/**
	 * Add admin menu items
	 *
	 * Creates the main Swift CSV menu in WordPress admin.
	 *
	 * @since  0.9.0
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tools.php',
			'Swift CSV',
			'Swift CSV',
			'manage_options',
			'swift-csv',
			[ $this->page, 'render_main_page' ]
		);
	}
}
