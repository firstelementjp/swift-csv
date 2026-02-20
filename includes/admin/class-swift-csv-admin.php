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
	 * Constructor
	 *
	 * Sets up WordPress hooks for admin menu and styles.
	 *
	 * @since  0.9.0
	 * @return void
	 */
	public function __construct() {
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
		if ( 'tools_page_swift-csv' === $hook ) {
			$debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;
			$suffix     = $debug_mode ? '' : '.min';

			wp_enqueue_style(
				'swift-csv-admin',
				SWIFT_CSV_PLUGIN_URL . 'assets/css/swift-csv-style' . $suffix . '.css',
				[],
				SWIFT_CSV_VERSION
			);
		}
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
		if ( 'tools_page_swift-csv' === $hook ) {
			$debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;
			$suffix     = $debug_mode ? '' : '.min';

			$script_url = static function ( $relative_path ) use ( $suffix ) {
				$min_path = preg_replace( '/\.js$/', $suffix . '.js', $relative_path );
				$fs_path  = SWIFT_CSV_PLUGIN_DIR . ltrim( $min_path, '/' );
				if ( '.min' === $suffix && file_exists( $fs_path ) ) {
					return SWIFT_CSV_PLUGIN_URL . ltrim( $min_path, '/' );
				}
				return SWIFT_CSV_PLUGIN_URL . ltrim( $relative_path, '/' );
			};

			// Core utilities (must be loaded first).
			wp_register_script(
				'swift-csv-core',
				$script_url( 'assets/js/swift-csv-core.js' ),
				[ 'wp-i18n' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'swift-csv-export-unified-module-ajax',
				$script_url( 'assets/js/export/swift-csv/ajax.js' ),
				[ 'swift-csv-core' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'swift-csv-export-unified-module-download',
				$script_url( 'assets/js/export/swift-csv/download.js' ),
				[ 'swift-csv-core' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'swift-csv-export-unified-module-form',
				$script_url( 'assets/js/export/swift-csv/form.js' ),
				[ 'swift-csv-core' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'swift-csv-export-unified-module-ui',
				$script_url( 'assets/js/export/swift-csv/ui.js' ),
				[ 'swift-csv-core' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'swift-csv-export-unified-module-logs',
				$script_url( 'assets/js/export/swift-csv/logs.js' ),
				[ 'swift-csv-core', 'swift-csv-export-unified-module-ajax' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'swift-csv-export-original',
				$script_url( 'assets/js/export/swift-csv/original.js' ),
				[ 'swift-csv-core', 'swift-csv-export-unified' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			// Export functionality.
			wp_register_script(
				'swift-csv-export-unified',
				$script_url( 'assets/js/swift-csv-export-unified.js' ),
				[
					'swift-csv-core',
					'swift-csv-export-unified-module-ajax',
					'swift-csv-export-unified-module-download',
					'swift-csv-export-unified-module-form',
					'swift-csv-export-unified-module-ui',
					'swift-csv-export-unified-module-logs',
				],
				'0.9.8',
				true
			);

			// Import functionality.
			wp_register_script(
				'swift-csv-import',
				$script_url( 'assets/js/swift-csv-import.js' ),
				[ 'swift-csv-core' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			// License functionality.
			wp_register_script(
				'swift-csv-license',
				$script_url( 'assets/js/swift-csv-license.js' ),
				[ 'swift-csv-core' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			// Main entry point (must be loaded last).
			wp_register_script(
				'swift-csv-main',
				$script_url( 'assets/js/swift-csv-main.js' ),
				[
					'swift-csv-core',
					'swift-csv-export-unified',
					'swift-csv-export-original',
					'swift-csv-import',
					'swift-csv-license',
				],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			// Set script translations for core module.
			wp_set_script_translations( 'swift-csv-core', 'swift-csv', SWIFT_CSV_PLUGIN_DIR . 'languages' );

			// Localize script data (attached to core module).
			wp_localize_script(
				'swift-csv-core',
				'swiftCSV',
				[
					'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
					'nonce'               => wp_create_nonce( 'swift_csv_ajax_nonce' ),
					'debug'               => $debug_mode,
					/**
					 * Filter maximum log entries to display
					 *
					 * Allows developers to customize the number of log entries
					 * that are kept in the UI display. Default is 30.
					 *
					 * @since 0.9.8
					 * @param int $max_entries Maximum number of log entries to keep.
					 * @return int Modified maximum entries.
					 */
					'maxLogEntries'       => apply_filters( 'swift_csv_max_log_entries', 30 ),
					// Button text for export.
					'highSpeedExportText' => esc_html__( 'High-Speed Export', 'swift-csv' ),
					'standardExportText'  => esc_html__( 'Standard Export (WP Compatible)', 'swift-csv' ),
					'exportCompleteText'  => esc_html__( 'Export Complete', 'swift-csv' ),
					'exportFailedText'    => esc_html__( 'Export Failed', 'swift-csv' ),
					'csvContentNotFound'  => esc_html__( 'CSV content not found in response', 'swift-csv' ),
					'unknownError'        => esc_html__( 'Unknown error', 'swift-csv' ),
					'messages'            => [
						'exportCsv'               => esc_html__( 'Export CSV', 'swift-csv' ),
						'startExport'             => esc_html__( 'Start Export', 'swift-csv' ),
						'importCsv'               => esc_html__( 'Import CSV', 'swift-csv' ),
						'startImport'             => esc_html__( 'Start Import', 'swift-csv' ),
						'exporting'               => esc_html__( 'Exporting...', 'swift-csv' ),
						'importing'               => esc_html__( 'Importing...', 'swift-csv' ),
						'readyToExport'           => esc_html__( 'Ready to start export...', 'swift-csv' ),
						'readyToImport'           => esc_html__( 'Ready to start import...', 'swift-csv' ),
						'error'                   => esc_html__( 'An error occurred. Please try again.', 'swift-csv' ),
						'success'                 => esc_html__( 'Operation completed successfully!', 'swift-csv' ),
						'cancelled'               => esc_html__( 'Export cancelled', 'swift-csv' ),
						'failed'                  => esc_html__( 'Export failed', 'swift-csv' ),
						'importSettings'          => esc_html__( 'Import Settings', 'swift-csv' ),
						'dropFileHere'            => esc_html__( 'Drop CSV file here or click to browse', 'swift-csv' ),
						'maxFileSize'             => /* translators: %s: File size (e.g., 10MB) */ esc_html__( 'Maximum file size: %s', 'swift-csv' ),
						'custom'                  => esc_html__( 'Custom', 'swift-csv' ),
						'customHelp'              => /* translators: 1: Hook name, 2: Documentation link */ esc_html__( 'Use the %1$s hook to specify custom export items and order. See %2$s for details.', 'swift-csv' ),
						'documentation'           => esc_html__( 'documentation', 'swift-csv' ),
						// Log messages.
						'startingImport'          => esc_html__( 'Starting import process...', 'swift-csv' ),
						'fileInfo'                => esc_html__( 'File:', 'swift-csv' ),
						'fileSize'                => esc_html__( 'File Size:', 'swift-csv' ),
						'postTypeInfo'            => esc_html__( 'Post Type:', 'swift-csv' ),
						'updateExistingInfo'      => esc_html__( 'Update Existing:', 'swift-csv' ),
						'processingChunk'         => esc_html__( 'Chunk processing start position (row):', 'swift-csv' ),
						'processedInfo'           => esc_html__( 'Processed', 'swift-csv' ),
						'createdInfo'             => esc_html__( 'Created:', 'swift-csv' ),
						'updatedInfo'             => esc_html__( 'Updated:', 'swift-csv' ),
						'errorsInfo'              => esc_html__( 'Errors:', 'swift-csv' ),
						'importCompleted'         => esc_html__( 'Import completed!', 'swift-csv' ),
						'importCancelledByUser'   => esc_html__( 'Import cancelled by user', 'swift-csv' ),
						'importError'             => esc_html__( 'Import error:', 'swift-csv' ),
						// Dry Run messages.
						'dryRunNotice'            => esc_html__( 'Test import without creating posts or modifying data.', 'swift-csv' ),
						'dryRunCompleted'         => esc_html__( 'Test completed!', 'swift-csv' ),
						'dryRunCreated'           => esc_html__( 'Created posts:', 'swift-csv' ),
						'dryRunUpdated'           => esc_html__( 'Updated posts:', 'swift-csv' ),
						'dryRunErrors'            => esc_html__( 'Errors:', 'swift-csv' ),
						// Log prefixes.
						'dryRunPrefix'            => esc_html__( 'Dry Run', 'swift-csv' ),
						'importPrefix'            => esc_html__( 'Import', 'swift-csv' ),
						// Action texts.
						'createAction'            => esc_html__( 'New', 'swift-csv' ),
						'updateAction'            => esc_html__( 'Update', 'swift-csv' ),
						'noFileSelected'          => esc_html__( 'No file selected', 'swift-csv' ),
						'removeFile'              => esc_html__( 'Remove', 'swift-csv' ),
						// Export scope mappings.
						'exportScopeBasic'        => esc_html__( 'Basic Fields', 'swift-csv' ),
						'exportScopeAll'          => esc_html__( 'All Fields', 'swift-csv' ),
						// Export messages.
						'startingExport'          => esc_html__( 'Starting export process...', 'swift-csv' ),
						'postTypeExport'          => esc_html__( 'Post Type:', 'swift-csv' ),
						'exportContent'           => esc_html__( 'Export Content:', 'swift-csv' ),
						'includePrivateMeta'      => esc_html__( 'Include Private Meta:', 'swift-csv' ),
						'exportLimit'             => esc_html__( 'Export Limit:', 'swift-csv' ),
						'exportCancelledByUser'   => esc_html__( 'Export cancelled by user', 'swift-csv' ),
						'processedExport'         => esc_html__( 'Processed', 'swift-csv' ),
						'exportError'             => esc_html__( 'Export error:', 'swift-csv' ),
						'exportCompleted'         => esc_html__( 'Export completed successfully!', 'swift-csv' ),
						'downloadReady'           => esc_html__( 'Download ready:', 'swift-csv' ),
						'batchExportStarted'      => esc_html__( 'Batch export started', 'swift-csv' ),
						'exportAction'            => esc_html__( 'Exported', 'swift-csv' ),
						'exportPrefix'            => esc_html__( 'Export', 'swift-csv' ),
						'startingDirectSqlExport' => esc_html__( 'Starting export process (High-Speed)...', 'swift-csv' ),
						'secondsLabel'            => esc_html__( 's', 'swift-csv' ),
						'rowLabel'                => esc_html__( 'Row', 'swift-csv' ),
						// Common messages.
						'yes'                     => esc_html__( 'Yes', 'swift-csv' ),
						'no'                      => esc_html__( 'No', 'swift-csv' ),
						'noLimit'                 => esc_html__( 'No limit', 'swift-csv' ),
						'errorOccurred'           => esc_html__( 'An error occurred. Please try again.', 'swift-csv' ),
						'totalImported'           => esc_html__( 'Imported', 'swift-csv' ),
						'totalErrors'             => esc_html__( 'Errors', 'swift-csv' ),
						// License UI.
						'show'                    => esc_html__( 'Show', 'swift-csv' ),
						'hide'                    => esc_html__( 'Hide', 'swift-csv' ),
					],
				]
			);

			// Enqueue all scripts in correct order.
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'swift-csv-core' );
			wp_enqueue_script( 'swift-csv-export-unified-module-ajax' );
			wp_enqueue_script( 'swift-csv-export-unified-module-download' );
			wp_enqueue_script( 'swift-csv-export-unified-module-form' );
			wp_enqueue_script( 'swift-csv-export-unified-module-ui' );
			wp_enqueue_script( 'swift-csv-export-unified-module-logs' );
			wp_enqueue_script( 'swift-csv-export-unified' ); // New unified export script.
			wp_enqueue_script( 'swift-csv-export-original' );
			wp_enqueue_script( 'swift-csv-import' );
			wp_enqueue_script( 'swift-csv-license' );
			wp_enqueue_script( 'swift-csv-main' );
		}
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
		// Export settings section.
		add_settings_section(
			'swift_csv_export_section',
			__( 'Export Settings', 'swift-csv' ),
			[ $this, 'export_section_description' ],
			'swift-csv'
		);

		add_settings_field(
			'swift_csv_export_post_type',
			'',
			[ $this, 'export_post_type_field_html' ],
			'swift-csv',
			'swift_csv_export_section'
		);

		add_settings_field(
			'swift_csv_export_post_status',
			'',
			[ $this, 'export_post_status_field_html' ],
			'swift-csv',
			'swift_csv_export_section'
		);

		add_settings_field(
			'swift_csv_export_scope',
			'',
			[ $this, 'export_scope_field_html' ],
			'swift-csv',
			'swift_csv_export_section'
		);

		add_settings_field(
			'swift_csv_export_enable_logs',
			'',
			[ $this, 'export_enable_logs_field_html' ],
			'swift-csv',
			'swift_csv_export_section'
		);

		add_settings_field(
			'swift_csv_export_taxonomy_format',
			'',
			[ $this, 'export_taxonomy_format_field_html' ],
			'swift-csv',
			'swift_csv_export_section'
		);

		add_settings_field(
			'swift_csv_include_private_meta',
			'',
			[ $this, 'export_include_private_meta_field_html' ],
			'swift-csv',
			'swift_csv_export_section'
		);

		add_settings_field(
			'swift_csv_export_limit',
			'',
			[ $this, 'export_limit_field_html' ],
			'swift-csv',
			'swift_csv_export_section'
		);

		// Import settings section.
		add_settings_section(
			'swift_csv_import_section',
			__( 'Import Settings', 'swift-csv' ),
			[ $this, 'import_section_description' ],
			'swift-csv'
		);

		add_settings_field(
			'swift_csv_import_post_type',
			'',
			[ $this, 'import_post_type_field_html' ],
			'swift-csv',
			'swift_csv_import_section'
		);

		add_settings_field(
			'swift_csv_import_update_existing',
			'',
			[ $this, 'import_update_existing_field_html' ],
			'swift-csv',
			'swift_csv_import_section'
		);

		add_settings_field(
			'swift_csv_import_taxonomy_format',
			'',
			[ $this, 'import_taxonomy_format_field_html' ],
			'swift-csv',
			'swift_csv_import_section'
		);

		add_settings_field(
			'swift_csv_import_enable_logs',
			'',
			[ $this, 'import_enable_logs_field_html' ],
			'swift-csv',
			'swift_csv_import_section'
		);

		add_settings_field(
			'swift_csv_import_dry_run',
			'',
			[ $this, 'import_dry_run_field_html' ],
			'swift-csv',
			'swift_csv_import_section'
		);

		// License settings section.
		add_settings_section(
			'swift_csv_license_section',
			'',
			[ $this, 'license_section_description' ],
			'swift-csv'
		);

		add_settings_field(
			'swift_csv_license_key',
			'',
			[ $this, 'license_field_html' ],
			'swift-csv',
			'swift_csv_license_section'
		);
	}

	/**
	 * Export section description callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function export_section_description() {
		echo '<p>' . esc_html__( 'Configure your CSV export settings below.', 'swift-csv' ) . '</p>';
	}

	/**
	 * Export post type field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function export_post_type_field_html() {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		?>
		<dl>
			<dt>
				<label for="swift_csv_export_post_type"><?php esc_html_e( 'Post Type', 'swift-csv' ); ?></label>
			</dt>
			<dd>
				<select name="swift_csv_export_post_type" id="swift_csv_export_post_type" required>
					<?php foreach ( $post_types as $post_type ) : ?>
						<option value="<?php echo esc_attr( $post_type->name ); ?>">
							<?php echo esc_html( $post_type->labels->name ); ?> (<?php echo esc_html( $post_type->name ); ?>)
						</option>
					<?php endforeach; ?>
				</select>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Export enable logs field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function export_enable_logs_field_html() {
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Log Output', 'swift-csv' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" id="swift_csv_export_enable_logs" name="swift_csv_export_enable_logs" value="1">
					<?php esc_html_e( 'Output logs during export', 'swift-csv' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'If checked, detailed per-row logs will be generated and displayed. Disable for maximum speed.', 'swift-csv' ); ?></p>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Export post status field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function export_post_status_field_html() {
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Post Status', 'swift-csv' ); ?>
			</dt>
			<dd>
				<label class="swift-csv-block-label">
					<input type="radio" name="swift_csv_export_post_status" id="swift_csv_post_status_publish" value="publish" checked>
					<?php esc_html_e( 'Published posts only', 'swift-csv' ); ?>
				</label>
				<label class="swift-csv-block-label">
					<input type="radio" name="swift_csv_export_post_status" id="swift_csv_post_status_any" value="any">
					<?php esc_html_e( 'All statuses', 'swift-csv' ); ?>
				</label>
				<label class="swift-csv-block-label">
					<input type="radio" name="swift_csv_export_post_status" id="swift_csv_post_status_custom" value="custom">
					<?php esc_html_e( 'Custom', 'swift-csv' ); ?>
				</label>
				<div id="custom-post-status-help" class="swift-csv-custom-help">
					<?php
					printf(
						/* translators: 1: Hook name, 2: Documentation link */
						esc_html__( 'Use the %1$s hook to specify target post status. See %2$s for details.', 'swift-csv' ),
						'<code>swift_csv_export_post_status_query</code>',
						'<a href="' . esc_url( SWIFT_CSV_DOCS_URL ) . 'hooks" target="_blank">' . esc_html__( 'documentation', 'swift-csv' ) . '</a>'
					);
					?>
				</div>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Export scope field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function export_scope_field_html() {
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Export Content', 'swift-csv' ); ?>
			</dt>
			<dd>
				<label class="swift-csv-block-label">
					<input type="radio" name="swift_csv_export_scope" id="swift_csv_export_scope_basic" value="basic" checked>
					<?php esc_html_e( 'Basic Fields', 'swift-csv' ); ?>
				</label>
				<label class="swift-csv-block-label">
					<input type="radio" name="swift_csv_export_scope" id="swift_csv_export_scope_all" value="all">
					<?php esc_html_e( 'All Fields', 'swift-csv' ); ?>
				</label>
				<label class="swift-csv-block-label">
					<input type="radio" name="swift_csv_export_scope" id="swift_csv_export_scope_custom" value="custom">
					<?php esc_html_e( 'Custom', 'swift-csv' ); ?>
				</label>
				<div id="custom-export-help" class="swift-csv-custom-help">
					<?php
					printf(
						/* translators: 1: Hook name, 2: Documentation link */
						esc_html__( 'Use the %1$s hook to specify export content and order. See %2$s for details.', 'swift-csv' ),
						'<code>swift_csv_export_columns</code>',
						'<a href="' . esc_url( SWIFT_CSV_DOCS_URL ) . 'hooks" target="_blank">' . esc_html__( 'documentation', 'swift-csv' ) . '</a>'
					);
					?>
				</div>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Export taxonomy format field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function export_taxonomy_format_field_html() {
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Taxonomy Format', 'swift-csv' ); ?>
			</dt>
			<dd>
				<label class="swift-csv-block-label">
					<input type="radio" id="swift_csv_taxonomy_format_name" name="taxonomy_format" value="name" checked>
					<?php esc_html_e( 'Names (name)', 'swift-csv' ); ?>
				</label>
				<label class="swift-csv-block-label">
					<input type="radio" id="swift_csv_taxonomy_format_id" name="taxonomy_format" value="id">
					<?php esc_html_e( 'Term IDs (term_id)', 'swift-csv' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Choose how taxonomy terms are exported: names for readability or term IDs for data integrity.', 'swift-csv' ); ?></p>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Export include private meta field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function export_include_private_meta_field_html() {
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Custom Fields', 'swift-csv' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" name="swift_csv_include_private_meta" value="1">
					<?php esc_html_e( 'Include private meta fields', 'swift-csv' ); ?>
				</label>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Export limit field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function export_limit_field_html() {
		?>
		<dl>
			<dt>
				<label for="swift_csv_export_limit"><?php esc_html_e( 'Export Limit', 'swift-csv' ); ?></label>
			</dt>
			<dd>
				<input type="number" name="swift_csv_export_limit" id="swift_csv_export_limit" min="0" value="1000" placeholder="<?php esc_attr_e( 'No limit (0 = all)', 'swift-csv' ); ?>" class="small-text">
				<p class="description"><?php esc_html_e( 'Maximum number of posts to export. Enter 0 for no limit.', 'swift-csv' ); ?></p>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Import section description callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function import_section_description() {
		echo '<p>' . esc_html__( 'Configure your CSV import settings below.', 'swift-csv' ) . '</p>';
	}

	/**
	 * Import post type field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function import_post_type_field_html() {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		?>
		<dl>
			<dt>
				<label for="ajax_import_post_type"><?php esc_html_e( 'Post Type', 'swift-csv' ); ?></label>
			</dt>
			<dd>
				<select name="swift_csv_import_post_type" id="ajax_import_post_type" required>
					<?php foreach ( $post_types as $post_type ) : ?>
						<option value="<?php echo esc_attr( $post_type->name ); ?>">
							<?php echo esc_html( $post_type->labels->name ); ?> (<?php echo esc_html( $post_type->name ); ?>)
						</option>
					<?php endforeach; ?>
				</select>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Import update existing field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function import_update_existing_field_html() {
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Update Existing', 'swift-csv' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" name="swift_csv_import_update_existing" value="1">
					<?php esc_html_e( 'Update existing posts if they match by ID', 'swift-csv' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'If checked, existing posts will be updated. If unchecked, only new posts will be created.', 'swift-csv' ); ?></p>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Import dry run field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function import_dry_run_field_html() {
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Dry Run', 'swift-csv' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" id="dry_run" name="swift_csv_import_dry_run" value="1">
					<?php esc_html_e( 'Test import without creating posts', 'swift-csv' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Run a test import to preview changes without modifying your data. (Dry Run)', 'swift-csv' ); ?></p>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Import enable logs field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function import_enable_logs_field_html() {
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Log Output', 'swift-csv' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" id="swift_csv_import_enable_logs" name="swift_csv_import_enable_logs" value="1">
					<?php esc_html_e( 'Output logs during import', 'swift-csv' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'If checked, detailed per-row logs will be generated and displayed. Disable for maximum speed.', 'swift-csv' ); ?></p>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Import taxonomy format field callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function import_taxonomy_format_field_html() {
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Taxonomy Format', 'swift-csv' ); ?>
			</dt>
			<dd>
				<label class="swift-csv-block-label">
					<input type="radio" name="swift_csv_import_taxonomy_format" value="name" checked>
					<?php esc_html_e( 'Names', 'swift-csv' ); ?>
				</label>
				<label class="swift-csv-block-label">
					<input type="radio" name="swift_csv_import_taxonomy_format" value="id">
					<?php esc_html_e( 'Term IDs', 'swift-csv' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Select whether the term values in the CSV are names (text) or term IDs (numeric).', 'swift-csv' ); ?></p>
			</dd>
		</dl>
		<?php
	}

	/**
	 * License section description callback
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function license_section_description() {
		echo '<p>' . esc_html__( 'Configure your license settings below.', 'swift-csv' ) . '</p>';
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
		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		$action      = isset( $_POST['license_action'] ) ? sanitize_key( wp_unslash( $_POST['license_action'] ) ) : '';

		if ( empty( $license_key ) || empty( $action ) ) {
			wp_send_json_error( [ 'message' => 'Missing license key or action.' ] );
		}

		// Perform the license action (handled by Free version).
		$handler = new Swift_CSV_License_Handler();
		$result  = ( 'activate' === $action ) ? $handler->activate( $license_key ) : $handler->deactivate( $license_key );

		// Determine the product ID from the remote response.
		$product_id = 328;
		if ( isset( $result['data']['data']['productId'] ) ) {
			$product_id = (int) $result['data']['data']['productId'];
		} elseif ( isset( $result['data']['productId'] ) ) {
			$product_id = (int) $result['data']['productId'];
		}

		$all_licenses = get_option( 'swift_csv_pro_license', [] );

		// Ensure we have a proper array.
		if ( ! is_array( $all_licenses ) ) {
			$all_licenses = [];
		}

		if ( ! isset( $all_licenses['products'] ) || ! is_array( $all_licenses['products'] ) ) {
			$all_licenses['products'] = [];
		}

		if ( $result && $result['success'] ) {
			// Determine the local license status based on the requested action.
			$local_status = ( 'activate' === $action ) ? 'active' : 'inactive';

			if ( $product_id > 0 ) {
				$all_licenses['products'][ $product_id ] = [
					'key'    => $license_key,
					'status' => $local_status,
					'data'   => $result['data'] ?? [],
				];
			}

			update_option( 'swift_csv_pro_license', $all_licenses );
			delete_transient( 'swift_csv_pro_license_error' );
			wp_send_json_success( [ 'message' => $result['message'] ] );

		} else {
			if ( $product_id > 0 ) {
				$all_licenses['products'][ $product_id ] = [
					'key'    => $license_key,
					'status' => 'inactive',
					'data'   => $result['data'] ?? [],
				];
			}

			update_option( 'swift_csv_pro_license', $all_licenses );
			set_transient( 'swift_csv_pro_license_error', $result['message'], 60 );
			wp_send_json_error( [ 'message' => $result['message'] ] );
		}
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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// Sanitize and validate tab parameter.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'export';

		// Allow custom tabs (like Pro version tabs) - don't restrict to export/import only.
		// The validation will be handled by the individual tab rendering logic.

		// Check for batch processing.
		$batch_id = isset( $_GET['batch'] ) ? sanitize_text_field( wp_unslash( $_GET['batch'] ) ) : '';

		// Check for import results.
		$import_results = [];
		if ( isset( $_GET['imported'] ) ) {
			$updated        = isset( $_GET['updated'] ) ? absint( wp_unslash( $_GET['updated'] ) ) : 0;
			$errors         = isset( $_GET['errors'] ) ? absint( wp_unslash( $_GET['errors'] ) ) : 0;
			$import_results = [
				'imported' => absint( wp_unslash( $_GET['imported'] ) ),
				'updated'  => $updated,
				'errors'   => $errors,
			];

			if ( isset( $_GET['error_details'] ) ) {
				$error_details                   = sanitize_text_field( wp_unslash( $_GET['error_details'] ) );
				$import_results['error_details'] = explode( '|', urldecode( $error_details ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wrap swift-csv">
			<?php $this->render_plugin_header(); ?>

			<nav class="nav-tab-wrapper">
				<a href="?page=swift-csv&tab=export" class="nav-tab <?php echo 'export' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Export', 'swift-csv' ); ?>
				</a>
				<a href="?page=swift-csv&tab=import" class="nav-tab <?php echo 'import' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Import', 'swift-csv' ); ?>
				</a>
				<?php
				// Add license tab directly as default tab.
				$icon = '';
				if ( class_exists( 'Swift_CSV_Pro_Admin' ) && ! Swift_CSV_License_Handler::is_pro_active() ) {
					$icon = '<span class="dashicons dashicons-warning" style="color: #f59e0b;"></span>';
				}

				// Custom allowed HTML list for safe output.
				$allowed_html = [
					'span' => [
						'class' => true,
						'style' => true,
					],
				];
				?>
				<a href="?page=swift-csv&tab=license" class="nav-tab <?php echo 'license' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'License', 'swift-csv' ); ?>
					<?php echo wp_kses( $icon, $allowed_html ); ?>
				</a>
				<?php
				/**
				 * Fires within the settings page's tab wrapper to add custom navigation tabs.
				 *
				 * This action allows Pro version and other add-ons to add custom tabs.
				 *
				 * @since 0.9.5
				 * @param string $tab Currently active tab
				 */
				do_action( 'swift_csv_settings_tabs', $tab );
				?>
			</nav>

			<div class="tab-content">
				<?php
				if ( 'export' === $tab ) {
					if ( $batch_id ) {
						$this->render_export_batch_progress( $batch_id );
					} else {
						$this->render_export_tab_content();
					}
				} elseif ( 'import' === $tab ) {
					if ( $batch_id ) {
						$this->render_batch_progress( $batch_id );
					} else {
						$this->render_import_tab_content( $import_results );
					}
				} elseif ( 'license' === $tab ) {
					$this->render_license_tab_content();
				}
				// Custom tabs (like Pro version) will be handled by the hook below.
				/**
				 * Fires within the main settings form to add custom tab content panels.
				 *
				 * This action is intended to be used in conjunction with the
				 * 'swift_csv_settings_tabs' action. It allows Pro version and other add-ons
				 * to render the content for the custom tabs they have added.
				 *
				 * @since 0.9.5
				 * @param string $tab Currently active tab
				 * @param array  $import_results Import results data (for import tab)
				 */
				do_action( 'swift_csv_settings_tabs_content', $tab, $import_results );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render import tab content
	 *
	 * @since  0.9.0
	 * @param  array $import_results Import results from URL parameters.
	 * @return void
	 */
	private function render_import_tab_content( $import_results = [] ) {
		// Get all public post types for selection.
		$post_types = get_post_types( [ 'public' => true ], 'objects' );

		// Display import results if available.
		if ( ! empty( $import_results ) ) {
			$this->render_import_results( $import_results );
		}

		?>
		<div class="swift-csv-layout">
			<!-- Left Column: Settings -->
			<div class="swift-csv-settings">
				<div class="card">
					<h3><?php esc_html_e( 'Import Settings', 'swift-csv' ); ?></h3>

					<!-- File Upload Area - Moved to top -->
					<div class="swift-csv-file-upload-section">
						<div class="file-upload-area" id="csv-file-upload">
							<div class="file-upload-content">
								<svg class="file-upload-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
									<polyline points="7,10 12,15 17,10"></polyline>
									<line x1="12" y1="15" x2="12" y2="3"></line>
								</svg>
								<p class="file-upload-text"><?php esc_html_e( 'Drop CSV file here or click to browse', 'swift-csv' ); ?></p>
								<p class="file-upload-hint">
								<?php
									$limits = $this->get_upload_limits();
									printf(
									/* translators: %s: File size (e.g., 10MB) */
										esc_html__( 'Maximum file size: %s', 'swift-csv' ),
										esc_html( $limits['effective_limit_human'] )
									);
								?>
								</p>
								<input type="file" name="csv_file" id="ajax_csv_file" accept=".csv" style="display: none;">
							</div>
						</div>
						<div class="file-info" id="csv-file-info">
							<span id="csv-file-name" class="file-name"></span>
							<span id="csv-file-size" class="file-size"></span>
							<button type="button" class="button button-secondary" id="remove-file-btn"><?php esc_html_e( 'Remove', 'swift-csv' ); ?></button>
						</div>
					</div>

					<form id="swift-csv-ajax-import-form" enctype="multipart/form-data">
						<?php do_settings_fields( 'swift-csv', 'swift_csv_import_section' ); ?>

						<p class="submit">
							<button type="submit" class="button button-primary" id="ajax-import-csv-btn">
								<?php esc_html_e( 'Start Import', 'swift-csv' ); ?>
							</button>
							<button type="button" class="button" id="ajax-import-cancel-btn" style="display: none; margin-left: 10px;">
					<?php esc_html_e( 'Cancel', 'swift-csv' ); ?>
				</button>
						</p>
					</form>
				</div>
			</div>

			<!-- Right Column: Log + Progress -->
			<div class="swift-csv-log">
				<div class="card">
					<h3><?php esc_html_e( 'Import Log', 'swift-csv' ); ?></h3>

					<!-- Log Area -->
					<div class="swift-csv-log-area">
						<div class="log-content" id="import-log-content">
							<div class="log-entry log-info"><?php esc_html_e( 'Ready to start import...', 'swift-csv' ); ?></div>
						</div>
					</div>

					<!-- Progress Bar -->
					<div class="swift-csv-progress">
						<div class="progress-bar">
							<div class="progress-bar-fill"></div>
						</div>
						<div class="progress-stats">
							<span class="processed-rows">0</span> / <span class="total-rows">0</span> <?php esc_html_e( 'rows processed', 'swift-csv' ); ?> (<span class="percentage">0</span>%)
						</div>
						<div class="progress-details">
							<div class="created"><?php esc_html_e( 'Created:', 'swift-csv' ); ?> <span class="created-count">0</span></div>
							<div class="modified"><?php esc_html_e( 'Updated:', 'swift-csv' ); ?> <span class="updated-count">0</span></div>
							<div class="errors"><?php esc_html_e( 'Errors:', 'swift-csv' ); ?> <span class="error-count">0</span></div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<?php
	}

	/**
	 * Render license tab content
	 *
	 * Renders the license activation interface and Pro promotion.
	 * This method contains all license-related HTML and JavaScript.
	 *
	 * @since 0.9.6
	 * @return void
	 */
	private function render_license_tab_content() {
		?>
		<div class="swift-csv-layout full-width">
			<!-- Left Column: Settings -->
			<div class="swift-csv-settings">
				<div class="card">
					<?php do_settings_fields( 'swift-csv', 'swift_csv_license_section' ); ?>

					<?php
					$is_license_active = Swift_CSV_License_Handler::is_pro_active();
					$pro_is_loaded     = class_exists( 'Swift_CSV_Pro_Admin' );

					if ( ! $pro_is_loaded || ! $is_license_active ) :
						?>
						<div class="swift-csv-pro-promo">
							<hr>
							<h3><?php esc_html_e( 'Unlock more with Swift CSV Pro', 'swift-csv' ); ?></h3>
							<p><?php esc_html_e( 'With a Pro license, you can:', 'swift-csv' ); ?></p>
							<ul>
								<li>
									<h4><?php esc_html_e( 'ACF Integration', 'swift-csv' ); ?></h4>
									<ul>
										<li><?php esc_html_e( 'Export and import Advanced Custom Fields with proper formatting', 'swift-csv' ); ?></li>
										<li><?php esc_html_e( 'Support for all ACF field types including taxonomy, repeater, and relationship fields', 'swift-csv' ); ?></li>
										<li><?php esc_html_e( 'Automatic field type detection and proper value formatting', 'swift-csv' ); ?></li>
									</ul>
								</li>
								<li>
									<h4><?php esc_html_e( 'Advanced Features', 'swift-csv' ); ?></h4>
									<ul>
										<li><?php esc_html_e( 'Batch processing for large datasets (1000+ records)', 'swift-csv' ); ?></li>
										<li><?php esc_html_e( 'Real-time progress tracking with AJAX', 'swift-csv' ); ?></li>
										<li><?php esc_html_e( 'Custom field filtering and selection', 'swift-csv' ); ?></li>
										<li><?php esc_html_e( 'Advanced taxonomy handling with term ID or name export', 'swift-csv' ); ?></li>
									</ul>
								</li>
								<li>
									<h4><?php esc_html_e( 'Import Enhancements', 'swift-csv' ); ?></h4>
									<ul>
										<li><?php esc_html_e( 'Multi-value custom fields support', 'swift-csv' ); ?></li>
										<li><?php esc_html_e( 'Advanced filtering and sorting options', 'swift-csv' ); ?></li>
										<li><?php esc_html_e( 'Custom export templates and formats', 'swift-csv' ); ?></li>
										<li><?php esc_html_e( 'Scheduled export functionality', 'swift-csv' ); ?></li>
									</ul>
								</li>
								<li>
									<h4><?php esc_html_e( 'Support & Updates', 'swift-csv' ); ?></h4>
									<ul>
										<li><?php esc_html_e( 'Priority customer support', 'swift-csv' ); ?></li>
										<li><?php esc_html_e( 'Automatic updates from our update server', 'swift-csv' ); ?></li>
										<li><?php esc_html_e( 'Access to beta features and early releases', 'swift-csv' ); ?></li>
										<li><?php esc_html_e( 'Extended documentation and tutorials', 'swift-csv' ); ?></li>
									</ul>
								</li>
							</ul>
							<a href="<?php echo esc_url( SWIFT_CSV_PRO_URL ); ?>" target="_blank" class="button button-primary button-hero">
								<?php esc_html_e( 'View Swift CSV Pro Details', 'swift-csv' ); ?>
							</a>
						</div>
						<?php
					endif;
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the HTML for the license key input field and action buttons.
	 *
	 * @since 0.9.6
	 * @return void
	 */
	public function license_field_html() {
		$license_data   = get_option( 'swift_csv_pro_license', [] );
		$products       = is_array( $license_data ) ? ( $license_data['products'] ?? [] ) : [];
		$pro_product    = is_array( $products ) ? ( $products[328] ?? [] ) : [];
		$license_key    = is_array( $pro_product ) ? ( $pro_product['key'] ?? '' ) : '';
		$license_status = Swift_CSV_License_Handler::is_pro_active() ? 'active' : 'inactive';
		?>
		<dl>
			<dt>
				<label for="swift_csv_pro_license_key_input"><?php esc_html_e( 'Pro License Key', 'swift-csv' ); ?></label>
			</dt>
			<dd>
				<div class="swift-csv-license-input-group">
					<input
						type="password"
						autocomplete="off"
						id="swift_csv_pro_license_key_input"
						name="swift_csv_pro_license_key_input"
						value="<?php echo esc_attr( $license_key ); ?>"
						class="regular-text swift-csv-license-input"
					>
					<button
						type="button"
						id="swift_csv_pro_license_toggle_visibility"
						class="button button-secondary"
					>
						<?php esc_html_e( 'Show', 'swift-csv' ); ?>
					</button>
					<?php if ( 'active' === $license_status ) : ?>
						<button type="button" id="swift_csv_pro_license_deactivate" class="button button-secondary swift-csv-license-button" data-action="deactivate"><?php esc_html_e( 'Deactivate', 'swift-csv' ); ?></button>
					<?php else : ?>
						<button type="button" id="swift_csv_pro_license_activate" class="button button-primary swift-csv-license-button" data-action="activate"><?php esc_html_e( 'Activate', 'swift-csv' ); ?></button>
					<?php endif; ?>
					<span class="spinner swift-csv-spinner"></span>
				</div>

				<?php if ( 'active' === $license_status ) : ?>
					<p class="description swift-csv-license-valid">
						<?php esc_html_e( 'The license is valid.', 'swift-csv' ); ?>
					</p>

					<?php
					// Get license data from the correct structure (LMFWC response).
					$pro_data            = is_array( $pro_product ) ? ( $pro_product['data'] ?? [] ) : [];
					$expires_at          = $pro_data['expiresAt'] ?? '';
					$times_activated     = $pro_data['timesActivated'] ?? 0;
					$times_activated_max = $pro_data['timesActivatedMax'] ?? 1;
					$remaining_days      = '';

					if ( ! empty( $expires_at ) ) {
						try {
							$expires_ts     = strtotime( $expires_at );
							$today_midnight = strtotime( 'today midnight' );
							if ( $expires_ts ) {
								$diff_days      = (int) floor( ( $expires_ts - $today_midnight ) / DAY_IN_SECONDS );
								$remaining_days = $diff_days;
							}
						} catch ( \Throwable $e ) {
							$remaining_days = '';
						}
					}

					$expires_text = '';
					if ( ! empty( $expires_at ) ) {
						// Format as Y-m-d for display with remaining days.
						$expires_text = sprintf(
						/* translators: 1: expiration date, 2: remaining days */
							esc_html__( 'License expiration date: %1$s (remaining %2$s days)', 'swift-csv' ),
							esc_html( date_i18n( 'Y-m-d', strtotime( $expires_at ) ) ),
							'' !== $remaining_days ? esc_html( (string) $remaining_days ) : '600'
						);
					}
					?>
					<?php if ( ! empty( $expires_text ) ) : ?>
						<p class="description">
							<?php echo esc_html( $expires_text ); ?>
						</p>
					<?php endif; ?>
					<p class="description">
						<?php
						$max_text = is_null( $times_activated_max ) ? esc_html__( 'Unlimited', 'swift-csv' ) : (string) (int) $times_activated_max;
						printf(
							/* translators: 1: times activated, 2: max activations */
							esc_html__( 'Activation count: %1$s / %2$s', 'swift-csv' ),
							esc_html( (string) $times_activated ),
							esc_html( $max_text )
						);
						?>
					</p>
				<?php else : ?>
					<p class="description">
						<?php esc_html_e( 'Enter the license key you received at the time of purchase and press the "Activate" button.', 'swift-csv' ); ?>
					</p>

					<?php
					$error = get_transient( 'swift_csv_pro_license_error' );
					if ( $error ) :
						?>
						<p class="swift-csv-license-error"><?php echo esc_html( $error ); ?></p>
						<?php
						delete_transient( 'swift_csv_pro_license_error' );
					endif;
					?>

				<?php endif; ?>
			</dd>
		</dl>
		<?php
	}
}
