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
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'wp_ajax_swift_csv_pro_manage_license', [ $this, 'ajax_manage_license' ] );
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
			[ $this, 'render_main_page' ]
		);
	}

	/**
	 * Enqueue admin styles
	 *
	 * Loads CSS files for the admin interface.
	 * Uses minified version in production for better performance.
	 *
	 * @since  0.9.0
	 * @param  string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_styles( $hook ) {
		$this->assets->enqueue_styles( $hook );
	}

	/**
	 * Enqueue admin scripts
	 *
	 * Loads modular JavaScript files for better maintainability.
	 * Uses minified versions in production for better performance.
	 *
	 * @since  0.9.0
	 * @param  string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		$this->assets->enqueue_scripts( $hook );
	}

	/**
	 * Register plugin settings
	 *
	 * Registers all settings sections and fields using WordPress Settings API.
	 * Follows WordPress best practices for settings management.
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function register_settings() {
		$this->settings->register_settings();
	}

	/**
	 * Export section description callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function export_section_description() {
		$this->settings->export_section_description();
	}

	/**
	 * Export post type field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function export_post_type_field_html() {
		$this->settings->export_post_type_field_html();
	}

	/**
	 * Export include custom fields field callback
	 *
	 * @since 0.9.10
	 * @return void
	 */
	public function export_include_custom_fields_field_html() {
		$this->settings->export_include_custom_fields_field_html();
	}

	/**
	 * Export enable logs field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function export_enable_logs_field_html() {
		$this->settings->export_enable_logs_field_html();
	}

	/**
	 * Export post status field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function export_post_status_field_html() {
		$this->settings->export_post_status_field_html();
	}

	/**
	 * Export include taxonomies field callback
	 *
	 * @since 0.9.10
	 * @return void
	 */
	public function export_include_taxonomies_field_html() {
		$this->settings->export_include_taxonomies_field_html();
	}

	/**
	 * Export scope field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function export_scope_field_html() {
		$this->settings->export_scope_field_html();
	}

	/**
	 * Export limit field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function export_limit_field_html() {
		$this->settings->export_limit_field_html();
	}

	/**
	 * Import section description callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function import_section_description() {
		$this->settings->import_section_description();
	}

	/**
	 * Import post type field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function import_post_type_field_html() {
		$this->settings->import_post_type_field_html();
	}

	/**
	 * Import update existing field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function import_update_existing_field_html() {
		$this->settings->import_update_existing_field_html();
	}

	/**
	 * Import dry run field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function import_dry_run_field_html() {
		$this->settings->import_dry_run_field_html();
	}

	/**
	 * Import enable logs field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function import_enable_logs_field_html() {
		$this->settings->import_enable_logs_field_html();
	}

	/**
	 * Import taxonomy format field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function import_taxonomy_format_field_html() {
		$this->settings->import_taxonomy_format_field_html();
	}

	/**
	 * License section description callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function license_section_description() {
		$this->settings->license_section_description();
	}

	/**
	 * AJAX handler for license management
	 *
	 * Handles license activation and deactivation requests.
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function ajax_manage_license() {
		$this->ajax->ajax_manage_license();
	}

	/**
	 * Render main admin page
	 *
	 * Displays the main interface with export/import tabs.
	 *
	 * @since  0.9.0
	 * @return void
	 */
	public function render_main_page() {
		$this->page->render_main_page();
	}

	/**
	 * Renders the HTML for the license key input field and action buttons.
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function license_field_html() {
		$this->settings->license_field_html();
	}
}
