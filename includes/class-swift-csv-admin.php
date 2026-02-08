<?php
/**
 * Admin class for managing plugin interface
 *
 * This file contains the admin functionality for the Swift CSV plugin,
 * including menu creation, style enqueueing, and rendering of the
 * import/export interface.
 *
 * @since   0.9.1
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
	 * Loads CSS styles only on the plugin's admin pages.
	 *
	 * @since  0.9.0
	 * @param  string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_styles( $hook ) {
		if ( 'tools_page_swift-csv' === $hook ) {
			wp_enqueue_style(
				'swift-csv-admin',
				SWIFT_CSV_PLUGIN_URL . 'assets/css/swift-csv-admin-style.min.css',
				[],
				SWIFT_CSV_VERSION
			);
		}
	}

	/**
	 * Enqueue admin scripts
	 *
	 * JavaScript loading disabled for simple operation.
	 *
	 * @since  0.9.3
	 * @param  string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'tools_page_swift-csv' === $hook ) {
			wp_register_script(
				'swift-csv-admin',
				plugin_dir_url( __FILE__ ) . '../assets/js/swift-csv-admin-scripts.min.js',
				[ 'wp-i18n' ],
				SWIFT_CSV_VERSION,
				true
			);

			wp_set_script_translations( 'swift-csv-admin', 'swift-csv', SWIFT_CSV_PLUGIN_DIR . 'languages' );

			wp_localize_script(
				'swift-csv-admin',
				'swiftCSV',
				[
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'swift_csv_ajax_nonce' ),
					'debug'    => defined( 'WP_DEBUG' ) && WP_DEBUG,
					'messages' => [
						'exportCsv'             => esc_html__( 'Export CSV', 'swift-csv' ),
						'importCsv'             => esc_html__( 'Import CSV', 'swift-csv' ),
						'startImport'           => esc_html__( 'Start Import', 'swift-csv' ),
						'exporting'             => esc_html__( 'Exporting...', 'swift-csv' ),
						'importing'             => esc_html__( 'Importing...', 'swift-csv' ),
						'readyToExport'         => esc_html__( 'Ready to start export...', 'swift-csv' ),
						'readyToImport'         => esc_html__( 'Ready to start import...', 'swift-csv' ),
						'error'                 => esc_html__( 'An error occurred. Please try again.', 'swift-csv' ),
						'success'               => esc_html__( 'Operation completed successfully!', 'swift-csv' ),
						'cancelled'             => esc_html__( 'Export cancelled', 'swift-csv' ),
						'failed'                => esc_html__( 'Export failed', 'swift-csv' ),
						'importSettings'        => esc_html__( 'Import Settings', 'swift-csv' ),
						'dropFileHere'          => esc_html__( 'Drop CSV file here or click to browse', 'swift-csv' ),
						'maxFileSize'           => /* translators: %s: File size (e.g., 10MB) */ esc_html__( 'Maximum file size: %s', 'swift-csv' ),
						'custom'                => esc_html__( 'Custom', 'swift-csv' ),
						'customHelp'            => /* translators: 1: Hook name, 2: Documentation link */ esc_html__( 'Use the %1$s hook to specify custom export items and order. See %2$s for details.', 'swift-csv' ),
						'documentation'         => esc_html__( 'documentation', 'swift-csv' ),
						// Log messages
						'startingImport'        => esc_html__( 'Starting import process...', 'swift-csv' ),
						'fileInfo'              => esc_html__( 'File:', 'swift-csv' ),
						'fileSize'              => esc_html__( 'File Size:', 'swift-csv' ),
						'postTypeInfo'          => esc_html__( 'Post Type:', 'swift-csv' ),
						'updateExistingInfo'    => esc_html__( 'Update Existing:', 'swift-csv' ),
						'processingChunk'       => esc_html__( 'Chunk processing start position (row):', 'swift-csv' ),
						'processedInfo'         => esc_html__( 'Processed', 'swift-csv' ),
						'createdInfo'           => esc_html__( 'Created:', 'swift-csv' ),
						'updatedInfo'           => esc_html__( 'Updated:', 'swift-csv' ),
						'errorsInfo'            => esc_html__( 'Errors:', 'swift-csv' ),
						'importCompleted'       => esc_html__( 'Import completed!', 'swift-csv' ),
						'totalUpdated'          => esc_html__( 'Total updated:', 'swift-csv' ),
						'totalErrors'           => esc_html__( 'Total errors:', 'swift-csv' ),
						'importCancelledByUser' => esc_html__( 'Import cancelled by user', 'swift-csv' ),
						'importError'           => esc_html__( 'Import error:', 'swift-csv' ),
						// Export messages
						'startingExport'        => esc_html__( 'Starting export process...', 'swift-csv' ),
						'postTypeExport'        => esc_html__( 'Post Type:', 'swift-csv' ),
						'exportScope'           => esc_html__( 'Export Scope:', 'swift-csv' ),
						'includePrivateMeta'    => esc_html__( 'Include Private Meta:', 'swift-csv' ),
						'exportLimit'           => esc_html__( 'Export Limit:', 'swift-csv' ),
						'exporting'             => esc_html__( 'Exporting...', 'swift-csv' ),
						'exportCancelledByUser' => esc_html__( 'Export cancelled by user', 'swift-csv' ),
						'processedExport'       => esc_html__( 'Processed', 'swift-csv' ),
						'exportError'           => esc_html__( 'Export error:', 'swift-csv' ),
						'exportCompleted'       => esc_html__( 'Export completed successfully!', 'swift-csv' ),
						'downloadReady'         => esc_html__( 'Download ready:', 'swift-csv' ),
						'batchExportStarted'    => esc_html__( 'Batch export started', 'swift-csv' ),
						// Common messages
						'yes'                   => esc_html__( 'Yes', 'swift-csv' ),
						'no'                    => esc_html__( 'No', 'swift-csv' ),
						'noLimit'               => esc_html__( 'No limit', 'swift-csv' ),
						'errorOccurred'         => esc_html__( 'An error occurred. Please try again.', 'swift-csv' ),
						'totalImported'         => esc_html__( 'Total imported:', 'swift-csv' ),
						// File operation messages
						'fileRemoved'           => esc_html__( 'File removed', 'swift-csv' ),
						'selectCsvFile'         => esc_html__( 'Please select a CSV file', 'swift-csv' ),
						'fileSizeExceedsLimit'  => esc_html__( 'File size exceeds 10MB limit', 'swift-csv' ),
						'fileSelected'          => esc_html__( 'File selected:', 'swift-csv' ),
						'removeFile'            => esc_html__( 'Remove', 'swift-csv' ),
						// Export scope mappings
						'exportScopeBasic'      => esc_html__( 'Basic Fields', 'swift-csv' ),
						'exportScopeAll'        => esc_html__( 'All Fields', 'swift-csv' ),
					],
				]
			);

			wp_enqueue_script( 'swift-csv-admin' );

			// Log script loading for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Swift CSV] Admin scripts loaded: swift-csv-admin v' . SWIFT_CSV_VERSION );
			}
		}
	}

	/**
	 * Get PHP upload limits
	 *
	 * @return array Upload limit information
	 */
	private function get_upload_limits() {
		$upload_max = ini_get( 'upload_max_filesize' );
		$post_max   = ini_get( 'post_max_size' );

		// Convert to bytes
		$upload_max_bytes = $this->parse_ini_size( $upload_max );
		$post_max_bytes   = $this->parse_ini_size( $post_max );

		// Get the smaller limit
		$max_file_size       = min( $upload_max_bytes, $post_max_bytes );
		$max_file_size_human = $this->format_bytes( $max_file_size );

		return [
			'upload_max_filesize'   => $upload_max,
			'post_max_size'         => $post_max,
			'effective_limit'       => $max_file_size,
			'effective_limit_human' => $max_file_size_human,
		];
	}

	/**
	 * Parse PHP ini size string to bytes
	 *
	 * @param string $size Size string (e.g., "2M", "8M")
	 * @return int Size in bytes
	 */
	private function parse_ini_size( $size ) {
		$unit  = strtoupper( substr( $size, -1 ) );
		$value = (int) substr( $size, 0, -1 );

		switch ( $unit ) {
			case 'G':
				return $value * 1024 * 1024 * 1024;
			case 'M':
				return $value * 1024 * 1024;
			case 'K':
				return $value * 1024;
			default:
				return (int) $size;
		}
	}

	/**
	 * Format bytes to human readable format
	 *
	 * @param int $bytes Bytes
	 * @return string Formatted size
	 */
	private function format_bytes( $bytes ) {
		if ( $bytes >= 1024 * 1024 * 1024 ) {
			return round( $bytes / 1024 / 1024 / 1024, 1 ) . 'GB';
		} elseif ( $bytes >= 1024 * 1024 ) {
			return round( $bytes / 1024 / 1024, 1 ) . 'MB';
		} elseif ( $bytes >= 1024 ) {
			return round( $bytes / 1024, 1 ) . 'KB';
		} else {
			return $bytes . 'B';
		}
	}
	/**
	 * Displays professional header with version info and support links .
	 *
	 * @since  0.9.0
	 * @return void
	 */
	private function render_plugin_header() {
		$docs_url  = 'https://firstelementjp.github.io/swift-csv/#/';
		$forum_url = 'https://github.com/firstelementjp/swift-csv/issues';
		?>
		<div id="plugin_header">
			<div id="plugin_header_upper">
				<div id="plugin_header_title">Swift <span>CSV</span></div>
				<a href="https://www.firstelement.co.jp/" id="plugin_logo" target="_blank" title="Go to the developer's website">
					<img src="<?php echo esc_url( SWIFT_CSV_PLUGIN_URL . 'assets/images/logo-feas-white-shadow-s@2x-min.png' ); ?>" width="106" height="27" alt="FirstElement">
				</a>
			</div>
			<div id="plugin_version">
				version <?php echo esc_html( SWIFT_CSV_VERSION ); ?>
			</div>
			<div id="plugin_support">
				<a href="<?php echo esc_url( $docs_url ); ?>"
					target="_blank"
					title="<?php esc_attr_e( 'Go to the instruction manual', 'swift-csv' ); ?>">
					<?php esc_html_e( 'Documentation', 'swift-csv' ); ?>
				</a>
				<a href="https://github.com/firstelementjp/swift-csv"
					target="_blank"
					title="<?php esc_attr_e( 'Go to GitHub repository', 'swift-csv' ); ?>"
					class="icon icon_gh">
					<svg
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 20 20"
						width="16"
						height="16"
					>
						<g transform="translate(-140 -7559)" fill="currentColor" fill-rule="evenodd">
							<g transform="translate(56 160)">
								<path d="M94,7399 C99.523,7399 104,7403.59 104,7409.253 C104,7413.782 101.138,7417.624 97.167,7418.981 C96.66,7419.082 96.48,7418.762 96.48,7418.489 C96.48,7418.151 96.492,7417.047 96.492,7415.675 C96.492,7414.719 96.172,7414.095 95.813,7413.777 C98.04,7413.523 100.38,7412.656 100.38,7408.718 C100.38,7407.598 99.992,7406.684 99.35,7405.966 C99.454,7405.707 99.797,7404.664 99.252,7403.252 C99.252,7403.252 98.414,7402.977 96.505,7404.303 C95.706,7404.076 94.85,7403.962 94,7403.958 C93.15,7403.962 92.295,7404.076 91.497,7404.303 C89.586,7402.977 88.746,7403.252 88.746,7403.252 C88.203,7404.664 88.546,7405.707 88.649,7405.966 C88.01,7406.684 87.619,7407.598 87.619,7408.718 C87.619,7412.646 89.954,7413.526 92.175,7413.785 C91.889,7414.041 91.63,7414.493 91.54,7415.156 C90.97,7415.418 89.522,7415.871 88.63,7414.304 C88.63,7414.304 88.101,7413.319 87.097,7413.247 C87.097,7413.247 86.122,7413.234 87.029,7413.87 C87.029,7413.87 87.684,7414.185 88.139,7415.37 C88.139,7415.37 88.726,7417.2 91.508,7416.58 C91.513,7417.437 91.522,7418.245 91.522,7418.489 C91.522,7418.76 91.338,7419.077 90.839,7418.982 C86.865,7417.627 84,7413.783 84,7409.253 C84,7403.59 88.478,7399 94,7399" />
							</g>
						</g>
					</svg>
				</a>
				<a href="https://x.com/firstelement"
					target="_blank"
					title="<?php esc_attr_e( 'Go to X', 'swift-csv' ); ?>"
					class="icon icon_tw">
					<svg
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 1226.37 1226.37"
						width="20"
						height="20"
					>
						<path
							fill="currentColor"
							d="m727.348 519.284 446.727-519.284h-105.86l-387.893 450.887-309.809-450.887h-357.328l468.492 681.821-468.492 544.549h105.866l409.625-476.152 327.181 476.152h357.328l-485.863-707.086zm-144.998 168.544-47.468-67.894-377.686-540.24h162.604l304.797 435.991 47.468 67.894 396.2 566.721h-162.604l-323.311-462.446z"
						/>
					</svg>
				</a>
				<a href="https://www.facebook.com/firstelementjp"
					target="_blank"
					title="<?php esc_attr_e( 'Go to Facebook page', 'swift-csv' ); ?>"
					class="icon icon_fb">
				</a>
				<a href="https://www.firstelement.co.jp/contact"
					target="_blank"
					title="<?php esc_attr_e( 'Go to contact form', 'swift-csv' ); ?>"
					class="icon icon_mail">
				</a>
			</div>
		</div>
		<?php
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
		// Sanitize and validate tab parameter.
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'export';

		// Log admin page access for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[Swift CSV] Admin page accessed: tab={$tab}" );
		}

		// Allow custom tabs (like Pro version tabs) - don't restrict to export/import only
		// The validation will be handled by the individual tab rendering logic

		// Check for batch processing
		$batch_id = isset( $_GET['batch'] ) ? sanitize_text_field( $_GET['batch'] ) : '';

		// Check for import results
		$import_results = [];
		if ( isset( $_GET['imported'] ) ) {
			$import_results = [
				'imported' => intval( $_GET['imported'] ),
				'updated'  => intval( $_GET['updated'] ),
				'errors'   => intval( $_GET['errors'] ),
			];

			if ( isset( $_GET['error_details'] ) ) {
				$import_results['error_details'] = explode( '|', urldecode( $_GET['error_details'] ) );
			}

			// Log import results for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "[Swift CSV] Import results displayed: imported={$import_results['imported']}, updated={$import_results['updated']}, errors={$import_results['errors']}" );
			}
		}

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
						$this->render_export_tab();
					}
				} elseif ( 'import' === $tab ) {
					if ( $batch_id ) {
						$this->render_batch_progress( $batch_id );
					} else {
						$this->render_import_tab( $import_results );
					}
				}
				// Custom tabs (like Pro version) will be handled by the hook below
				?>

				<?php
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
	 * Render export tab
	 *
	 * Displays the export form with post type selection and options.
	 *
	 * @since  0.9.0
	 * @return void
	 */
	private function render_export_tab() {
		// Get all public post types for selection.
		$post_types = get_post_types( [ 'public' => true ], 'objects' );

		?>
		<div class="swift-csv-layout">
			<!-- Left Column: Settings -->
			<div class="swift-csv-settings">
				<div class="card">
					<h3><?php esc_html_e( 'Export Settings', 'swift-csv' ); ?></h3>

					<form id="swift-csv-ajax-export-form" onsubmit="return false;">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="ajax_export_post_type"><?php esc_html_e( 'Post Type', 'swift-csv' ); ?></label>
								</th>
								<td>
									<select name="post_type" id="ajax_export_post_type" required>
										<?php foreach ( $post_types as $post_type ) : ?>
											<option value="<?php echo esc_attr( $post_type->name ); ?>">
												<?php echo esc_html( $post_type->labels->name ); ?> (<?php echo esc_html( $post_type->name ); ?>)
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php esc_html_e( 'Export Scope', 'swift-csv' ); ?>
								</th>
								<td>
									<label style="display:block;">
										<input type="radio" name="export_scope" value="basic" checked>
										<?php esc_html_e( 'Basic Fields', 'swift-csv' ); ?>
									</label>
									<label style="display:block;">
										<input type="radio" name="export_scope" value="all">
										<?php esc_html_e( 'All Fields', 'swift-csv' ); ?>
									</label>
									<label style="display:block;">
										<input type="radio" name="export_scope" value="custom">
										<?php esc_html_e( 'Custom', 'swift-csv' ); ?>
									</label>
									<div id="custom-export-help" style="display: none; margin-top: 10px; padding: 8px; background-color: #f9f9f9; border-left: 3px solid #0073aa; font-size: 12px; color: #666;">
										<?php
										$docs_url = SWIFT_CSV_PLUGIN_URL . 'docs/hooks.md#swift_csv_export_columns';
										printf(
											esc_html__( 'Use the %1$s hook to specify custom export items and order. See %2$s for details.', 'swift-csv' ),
											'<code>swift_csv_export_columns</code>',
											'<a href="' . esc_url( $docs_url ) . '" target="_blank">' . esc_html__( 'documentation', 'swift-csv' ) . '</a>'
										);
										?>
									</div>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php esc_html_e( 'Taxonomy Format', 'swift-csv' ); ?>
								</th>
								<td>
									<label style="display:block;">
										<input type="radio" name="taxonomy_format" value="name" checked>
										<?php esc_html_e( 'Names (name)', 'swift-csv' ); ?>
									</label>
									<label style="display:block;">
										<input type="radio" name="taxonomy_format" value="id">
										<?php esc_html_e( 'Term IDs (term_id)', 'swift-csv' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Choose how taxonomy terms are exported: names for readability or term IDs for data integrity.', 'swift-csv' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php esc_html_e( 'Custom Fields', 'swift-csv' ); ?>
								</th>
								<td>
									<label style="display:block;">
										<input type="checkbox" name="include_private_meta" value="1">
										<?php esc_html_e( 'Include fields starting with "_"', 'swift-csv' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="export_limit"><?php esc_html_e( 'Export Limit', 'swift-csv' ); ?></label>
								</th>
								<td>
									<input type="number" name="export_limit" id="export_limit" min="0" value="1000" placeholder="<?php esc_attr_e( 'No limit (0 = all)', 'swift-csv' ); ?>" class="small-text">
									<p class="description"><?php esc_html_e( 'Maximum number of posts to export. Enter 0 for no limit.', 'swift-csv' ); ?></p>
								</td>
							</tr>
						</table>

						<p class="submit">
							<input type="submit" name="ajax_export_csv" class="button button-primary" id="ajax-export-csv-btn" value="<?php esc_html_e( 'Start Export', 'swift-csv' ); ?>">
							<button type="button" class="button" id="ajax-export-cancel-btn" style="display: none;">
								<?php esc_html_e( 'Cancel', 'swift-csv' ); ?>
							</button>
						</p>
					</form>
				</div>
			</div>

			<!-- Right Column: Log + Progress -->
			<div class="swift-csv-log">
				<div class="card">
					<h3><?php esc_html_e( 'Export Log', 'swift-csv' ); ?></h3>

					<!-- Log Area -->
					<div class="swift-csv-log-area">
						<div class="log-content" id="export-log-content">
							<div class="log-entry log-info"><?php esc_html_e( 'Ready to start export...', 'swift-csv' ); ?></div>
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

						<!-- Download Button - Always visible -->
						<div class="swift-csv-download-section">
							<a href="#" id="export-download-btn" class="swift-csv-download-btn" download>
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Download CSV', 'swift-csv' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render import tab
	 *
	 * @since  0.9.0
	 * @param  array $import_results Import results from URL parameters.
	 * @return void
	 */
	private function render_import_tab( $import_results = [] ) {
		// Get all public post types for selection.
		$post_types = get_post_types( [ 'public' => true ], 'objects' );

		// Display import results if available
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
										esc_html__( 'Maximum file size: %s', 'swift-csv' ),
										esc_html( $limits['effective_limit_human'] )
									);
								?>
								</p>
								<input type="file" name="csv_file" id="ajax_csv_file" accept=".csv" style="display: none;">
							</div>
						</div>
						<div class="file-info" id="csv-file-info" style="display: none;">
							<span class="file-name"></span>
							<button type="button" class="button button-secondary" id="remove-file-btn"><?php esc_html_e( 'Remove', 'swift-csv' ); ?></button>
						</div>
					</div>

					<form id="swift-csv-ajax-import-form" enctype="multipart/form-data">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="ajax_post_type"><?php esc_html_e( 'Post Type', 'swift-csv' ); ?></label>
								</th>
								<td>
									<select name="post_type" id="ajax_post_type" required>
										<?php
										$post_types = get_post_types( [ 'public' => true ], 'objects' );
										foreach ( $post_types as $post_type ) :
											?>
											<option value="<?php echo esc_attr( $post_type->name ); ?>">
												<?php echo esc_html( $post_type->labels->name ); ?> (<?php echo esc_html( $post_type->name ); ?>)
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Select the target post type for import.', 'swift-csv' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ajax_update_existing"><?php esc_html_e( 'Update Existing Posts', 'swift-csv' ); ?></label>
								</th>
								<td>
									<input type="checkbox" name="update_existing" id="ajax_update_existing" value="1">
									<p class="description"><?php esc_html_e( 'Check to update existing posts based on ID.', 'swift-csv' ); ?></p>
								</td>
							</tr>
						</table>

						<p class="submit">
							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Start Import', 'swift-csv' ); ?>
							</button>
							<button type="button" class="button" id="ajax-import-cancel-btn" style="display: none;">
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
}
