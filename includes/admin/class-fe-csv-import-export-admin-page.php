<?php
/**
 * Admin page renderer
 *
 * Renders the FE CSV Import & Export admin UI (tabs and tab content).
 *
 * @since 0.9.8
 * @package FE_CSV_Import_Export
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page renderer
 *
 * @since 0.9.8
 * @package FE_CSV_Import_Export
 */
class FE_CSV_Import_Export_Admin_Page {

	/**
	 * Admin instance
	 *
	 * @var object
	 */
	private $admin;

	/**
	 * Constructor
	 *
	 * @since 0.9.8
	 * @param object $admin Admin instance.
	 */
	public function __construct( $admin ) {
		$this->admin = $admin;
	}

	/**
	 * Render advanced settings tab content
	 *
	 * @since 0.9.8
	 * @return void
	 */
	private function render_advanced_tab_content(): void {
		?>
		<div class="fe-csv-import-export-layout full-width">
			<div class="fe-csv-import-export-settings">
				<div class="card">
					<h3><?php esc_html_e( 'Advanced Settings', 'fe-csv-import-export' ); ?></h3>
					<form id="fe-csv-import-export-advanced-settings-form" onsubmit="return false;">
						<?php do_settings_fields( 'fe-csv-import-export', 'fe_csv_import_export_advanced_section' ); ?>
						<?php do_action( 'fe_csv_import_export_after_advanced_settings_fields', $this->admin ); ?>

						<div class="fe-csv-import-export-unified-save-section">
							<p class="submit">
								<button type="button" class="button button-primary" id="fe-csv-import-export-save-all-settings">
									<?php esc_html_e( 'Save All Settings', 'fe-csv-import-export' ); ?>
								</button>
								<span class="spinner" style="display: none;"></span>
							</p>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render main admin page
	 *
	 * Displays the main interface with export/import tabs.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function render_main_page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// Sanitize and validate tab parameter.
		$tab                = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'export';
		$can_manage_options = current_user_can( 'manage_options' );
		if ( ! $can_manage_options && in_array( $tab, [ 'advanced', 'license' ], true ) ) {
			$tab = 'export';
		}

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
		<div class="wrap fe-csv-import-export">
		<?php $this->render_plugin_header(); ?>
			<nav class="nav-tab-wrapper">
				<a href="?page=fe-csv-import-export&tab=export" class="nav-tab <?php echo 'export' === $tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Export', 'fe-csv-import-export' ); ?>
				</a>
				<a href="?page=fe-csv-import-export&tab=import" class="nav-tab <?php echo 'import' === $tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Import', 'fe-csv-import-export' ); ?>
				</a>
				<?php if ( $can_manage_options ) : ?>
					<a href="?page=fe-csv-import-export&tab=advanced" class="nav-tab <?php echo 'advanced' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Advanced Settings', 'fe-csv-import-export' ); ?>
					</a>
				<?php endif; ?>
				<?php
					/**
					 * Fires within the settings page's tab wrapper to add custom navigation tabs.
					 *
					 * This action allows Pro version and other add-ons to add custom tabs.
					 *
					 * @since 0.9.5
					 * @param string $tab Currently active tab
					 */
					do_action( 'fe_csv_import_export_settings_tabs', $tab );
				?>
				<?php
				// Add license tab directly as default tab (always last).
				$icon = '';
				if ( class_exists( 'FE_CSV_Import_Export_Pro_Admin' ) && ! FE_CSV_Import_Export_License_Handler::is_pro_active() ) {
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
				<?php if ( $can_manage_options ) : ?>
					<a href="?page=fe-csv-import-export&tab=license" class="nav-tab <?php echo 'license' === $tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'License', 'fe-csv-import-export' ); ?>
						<?php echo wp_kses( $icon, $allowed_html ); ?>
					</a>
				<?php endif; ?>
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
				} elseif ( 'advanced' === $tab && $can_manage_options ) {
					$this->render_advanced_tab_content();
				} elseif ( 'license' === $tab && $can_manage_options ) {
					$this->render_license_tab_content();
				}
				// Custom tabs (like Pro version) will be handled by the hook below.
				/**
				 * Fires within the main settings form to add custom tab content panels.
				 *
				 * This action is intended to be used in conjunction with the
				 * 'fe_csv_import_export_settings_tabs' action. It allows Pro version and other add-ons
				 * to render the content for the custom tabs they have added.
				 *
				 * @since 0.9.5
				 * @param string $tab Currently active tab
				 * @param array  $import_results Import results data (for import tab)
				 */
				do_action( 'fe_csv_import_export_settings_tabs_content', $tab, $import_results );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render export tab content
	 *
	 * Displays the export form using WordPress Settings API.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	private function render_export_tab_content() {
		?>
		<div class="fe-csv-import-export-layout">
			<!-- Left Column: Settings -->
			<div class="fe-csv-import-export-settings">
				<div class="card">
					<h3><?php esc_html_e( 'Export Settings', 'fe-csv-import-export' ); ?></h3>

					<form id="fe-csv-import-export-ajax-export-form" onsubmit="return false;">
					<?php
					/**
					 * Filter the export form action URL
					 *
					 * @since 0.9.6
					 * @param string $action_url The form action URL
					 */
					$action_url = apply_filters( 'fe_csv_import_export_export_form_action', '' );
					if ( $action_url ) {
						echo '<input type="hidden" name="action" value="' . esc_attr( $action_url ) . '">';
					}
					?>

					<?php do_settings_fields( 'fe-csv-import-export', 'fe_csv_import_export_export_section' ); ?>
						<?php do_action( 'fe_csv_import_export_after_export_settings_fields', $this->admin ); ?>

						<p class="submit">
							<input type="submit" name="ajax_export_csv" class="button button-primary" id="ajax-export-csv-btn" value="<?php esc_html_e( 'Export', 'fe-csv-import-export' ); ?>">
							<?php do_action( 'fe_csv_import_export_after_export_button' ); ?>
							<button type="button" class="button" id="ajax-export-cancel-btn" style="display: none;">
					<?php esc_html_e( 'Cancel', 'fe-csv-import-export' ); ?>
				</button>
						</p>
					</form>
				</div>
			</div>

			<!-- Right Column: Log + Progress -->
			<div class="fe-csv-import-export-log">
				<div class="card">
					<h3><?php esc_html_e( 'Export Log', 'fe-csv-import-export' ); ?></h3>

					<!-- Export Logs Section -->
					<div class="fe-csv-import-export-logs-area">
						<div class="log-panels">
							<div class="log-panel active" data-panel="export">
								<div class="log-content" id="export-log-content">
									<div class="log-entry log-info log-export"><span class="log-time">[00:00:00]</span><span class="log-message"><?php esc_html_e( 'Ready to start export...', 'fe-csv-import-export' ); ?></span></div>
								</div>
							</div>
						</div>
					</div>

					<!-- Progress Bar -->
					<div class="fe-csv-import-export-progress">
						<div class="progress-bar">
							<div class="progress-bar-fill"></div>
						</div>
						<div class="progress-stats">
							<span class="processed-rows">0</span> / <span class="total-rows">0</span> <?php esc_html_e( 'rows processed', 'fe-csv-import-export' ); ?> (<span class="percentage">0</span>%)
						</div>
						<div class="fe-csv-import-export-download-section">
							<a href="#" id="export-download-btn" class="fe-csv-import-export-download-btn" download="">
								<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Download CSV', 'fe-csv-import-export' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render import tab content
	 *
	 * @since 0.9.8
	 * @param array $import_results Import results from URL parameters.
	 * @return void
	 */
	private function render_import_tab_content( $import_results = [] ) {
		// Display import results if available.
		if ( ! empty( $import_results ) ) {
			$this->render_import_results( $import_results );
		}

		?>
		<div class="fe-csv-import-export-layout">
			<!-- Left Column: Settings -->
			<div class="fe-csv-import-export-settings">
				<div class="card">
					<h3><?php esc_html_e( 'Import Settings', 'fe-csv-import-export' ); ?></h3>

					<!-- File Upload Area - Moved to top -->
					<div class="fe-csv-import-export-file-upload-section">
						<div class="file-upload-area" id="csv-file-upload">
							<div class="file-upload-content">
								<svg class="file-upload-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
									<polyline points="7,10 12,15 17,10"></polyline>
									<line x1="12" y1="15" x2="12" y2="3"></line>
								</svg>
								<p class="file-upload-text"><?php esc_html_e( 'Drop CSV file here or click to browse', 'fe-csv-import-export' ); ?></p>
								<p class="file-upload-hint">
						<?php
							$limits = FE_CSV_Import_Export_Admin_Util::get_upload_limits();
							printf(
							/* translators: %s: File size (e.g., 10MB) */
								esc_html__( 'Maximum file size: %s', 'fe-csv-import-export' ),
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
							<button type="button" class="button button-secondary" id="remove-file-btn"><?php esc_html_e( 'Remove', 'fe-csv-import-export' ); ?></button>
						</div>
					</div>

					<form id="fe-csv-import-export-ajax-import-form" enctype="multipart/form-data" onsubmit="return false;">
							<?php do_settings_fields( 'fe-csv-import-export', 'fe_csv_import_export_import_section' ); ?>
							<?php do_action( 'fe_csv_import_export_after_import_settings_fields', $this->admin ); ?>

						<p class="submit">
							<button type="submit" name="ajax_import_csv" class="button button-primary" id="ajax-import-csv-btn" style="margin-left: 10px;">
							<?php esc_html_e( 'Import', 'fe-csv-import-export' ); ?>
						</button>
						<button type="button" class="button" id="ajax-import-cancel-btn" style="display: none; margin-left: 10px;">
							<?php esc_html_e( 'Cancel', 'fe-csv-import-export' ); ?>
						</button>
					</p>
					</form>
				</div>
			</div>

			<!-- Right Column: Log + Progress -->
			<div class="fe-csv-import-export-log">
				<div class="card">
					<h3><?php esc_html_e( 'Import Log', 'fe-csv-import-export' ); ?></h3>

					<!-- Import Logs Section -->
					<div class="fe-csv-import-export-logs-area">
						<div class="progress-details">
							<div class="log-tab created active" data-tab="created"><?php esc_html_e( 'Created:', 'fe-csv-import-export' ); ?> <span class="created-count">0</span></div>
							<div class="log-tab modified" data-tab="updated"><?php esc_html_e( 'Updated:', 'fe-csv-import-export' ); ?> <span class="updated-count">0</span></div>
							<div class="log-tab errors" data-tab="errors"><?php esc_html_e( 'Errors:', 'fe-csv-import-export' ); ?> <span class="error-count">0</span></div>
						</div>
						<div class="log-panels">
							<div class="log-panel active" data-panel="created">
								<div class="log-content" id="import-log-content">
									<div class="log-entry log-info log-import"><span class="log-time">[00:00:00]</span><span class="log-message"><?php esc_html_e( 'Ready to start import...', 'fe-csv-import-export' ); ?></span></div>
								</div>
							</div>
							<div class="log-panel" data-panel="updated">
								<div class="log-content">
									<div class="log-entry log-info log-import"><span class="log-time">[00:00:00]</span><span class="log-message"><?php esc_html_e( 'Ready to start import...', 'fe-csv-import-export' ); ?></span></div>
								</div>
							</div>
							<div class="log-panel" data-panel="errors">
								<div class="log-content">
									<div class="log-entry log-info log-import"><span class="log-time">[00:00:00]</span><span class="log-message"><?php esc_html_e( 'Ready to start import...', 'fe-csv-import-export' ); ?></span></div>
								</div>
							</div>
						</div>
					</div>

					<!-- Progress Bar -->
					<div class="fe-csv-import-export-progress">
						<div class="progress-bar">
							<div class="progress-bar-fill"></div>
						</div>
						<div class="progress-stats">
							<span class="processed-rows">0</span> / <span class="total-rows">0</span> <?php esc_html_e( 'rows processed', 'fe-csv-import-export' ); ?> (<span class="percentage">0</span>%)
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
	 * @since 0.9.8
	 * @return void
	 */
	private function render_license_tab_content() {
		?>
		<div class="fe-csv-import-export-layout full-width">
			<!-- Left Column: Settings -->
			<div class="fe-csv-import-export-settings">
				<div class="card">
				<?php do_settings_fields( 'fe-csv-import-export', 'fe_csv_import_export_license_section' ); ?>
				<?php do_action( 'fe_csv_import_export_after_license_settings_fields', $this->admin ); ?>

				<?php
				$is_license_active = FE_CSV_Import_Export_License_Handler::is_pro_active();
				$pro_is_loaded     = class_exists( 'FE_CSV_Import_Export_Pro_Admin' );

				if ( ! $pro_is_loaded || ! $is_license_active ) :
					?>
					<div class="fe-csv-import-export-pro-promo">
						<hr>
						<h3><?php esc_html_e( 'Unlock more with FE CSV Import & Export Pro', 'fe-csv-import-export' ); ?></h3>
						<p><?php esc_html_e( 'With a Pro license, you can:', 'fe-csv-import-export' ); ?></p>
						<ul>
							<li>
								<h4><?php esc_html_e( 'ACF Integration', 'fe-csv-import-export' ); ?></h4>
								<ul>
									<li><?php esc_html_e( 'Import and export ACF data while preserving field formatting', 'fe-csv-import-export' ); ?></li>
									<li><?php esc_html_e( 'Export taxonomy values as readable names and handle multi-value fields with pipe-separated values', 'fe-csv-import-export' ); ?></li>
									<li><?php esc_html_e( 'Support major ACF field types including Taxonomy, User, File, Relationship, Repeater, and Flexible Content fields', 'fe-csv-import-export' ); ?></li>
								</ul>
							</li>
							<li>
								<h4><?php esc_html_e( 'Security', 'fe-csv-import-export' ); ?></h4>
								<ul>
									<li><?php esc_html_e( 'When used with the backup plugin \'Updraft Plus\', automatic SQL backup is executed during import and import begins after completion. If problems are discovered after import, you can immediately restore from backup.', 'fe-csv-import-export' ); ?></li>
									<li><?php esc_html_e( 'Shared execution-only password protection for import and export operations', 'fe-csv-import-export' ); ?></li>
									<li><?php esc_html_e( 'Require the logged-in user password at execution time', 'fe-csv-import-export' ); ?></li>
									<li><?php esc_html_e( 'Access control for the tools page and execution permissions', 'fe-csv-import-export' ); ?></li>
								</ul>
							</li>
							<li>
								<h4><?php esc_html_e( 'Support & Updates', 'fe-csv-import-export' ); ?></h4>
								<ul>
									<li><?php esc_html_e( 'Dedicated customer support', 'fe-csv-import-export' ); ?></li>
									<li><?php esc_html_e( 'Automatic updates from our update server', 'fe-csv-import-export' ); ?></li>
								</ul>
							</li>
							<li>
								<h4><?php esc_html_e( 'Upcoming Features', 'fe-csv-import-export' ); ?></h4>
								<ul>
									<li><?php esc_html_e( 'Downloadable detailed export/import operation logs', 'fe-csv-import-export' ); ?></li>
									<li><?php esc_html_e( 'AI (LLM)-powered data checks', 'fe-csv-import-export' ); ?></li>
								</ul>
							</li>
						</ul>
						<?php
						$current_locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
						$is_japanese    = is_string( $current_locale ) && 0 === strpos( strtolower( $current_locale ), 'ja' );
						$pro_url        = $is_japanese
							? 'https://www.firstelement.co.jp/products/fe-csv-import-export-plugin/'
							: 'https://www.firstelement.co.jp/en/products/fe-csv-import-export-plugin/';
						?>
						<a href="<?php echo esc_url( $pro_url ); ?>" target="_blank" class="button button-primary button-hero">
						<?php esc_html_e( 'View FE CSV Import & Export Pro Details', 'fe-csv-import-export' ); ?>
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
	 * Displays professional header with version info and support links .
	 *
	 * @since 0.9.8
	 * @return void
	 */
	private function render_plugin_header() {
		$forum_url = 'https://github.com/firstelementjp/fe-csv-import-export/issues';

		// Check if Pro license is active.
		$is_pro_license_active = class_exists( 'FE_CSV_Import_Export_License_Handler' )
			&& is_callable( [ 'FE_CSV_Import_Export_License_Handler', 'is_pro_active' ] )
			&& FE_CSV_Import_Export_License_Handler::is_pro_active()
			&& defined( 'FE_CSV_IMPORT_EXPORT_PRO_VERSION' ); // Also check if Pro plugin is actually installed and active.
		$version_label         = (string) FE_CSV_IMPORT_EXPORT_VERSION;
		$current_locale        = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$docs_url              = FE_CSV_IMPORT_EXPORT_DOCS_URL;
		$contact_url           = 'https://www.firstelement.co.jp/contact';
		$site_url              = 'https://www.firstelement.co.jp/';

		if ( defined( 'FE_CSV_IMPORT_EXPORT_PRO_VERSION' ) && is_string( FE_CSV_IMPORT_EXPORT_PRO_VERSION ) && '' !== FE_CSV_IMPORT_EXPORT_PRO_VERSION ) {
			$version_label .= ' / ' . FE_CSV_IMPORT_EXPORT_PRO_VERSION;
		}

		if ( is_string( $current_locale ) && 0 === strpos( strtolower( $current_locale ), 'ja' ) ) {
			$docs_url = trailingslashit( FE_CSV_IMPORT_EXPORT_DOCS_URL ) . 'ja/';
		} else {
			$site_url    = 'https://www.firstelement.co.jp/en/';
			$contact_url = 'https://www.firstelement.co.jp/en/contact';
		}
		?>
		<div id="plugin_header">
			<div id="plugin_header_upper">
				<div id="plugin_header_title">FE <span>CSV</span> Import & Export
				<?php
				if ( $is_pro_license_active ) :
					?>
					<span class="pro-badge">Pro</span>
					<?php
				endif;
				?>
				</div>
				<a href="<?php echo esc_url( $site_url ); ?>" id="plugin_logo" target="_blank" title="Go to the developer's website">
					<img src="<?php echo esc_url( FE_CSV_IMPORT_EXPORT_PLUGIN_URL . 'assets/images/logo-feas-white-shadow.png' ); ?>" width="106" height="27" alt="FirstElement">
				</a>
			</div>
			<div id="plugin_version">
				version <?php echo esc_html( $version_label ); ?>
			</div>
			<div id="plugin_support">
				<a href="<?php echo esc_url( $docs_url ); ?>"
					target="_blank"
					title="<?php esc_attr_e( 'Go to the instruction manual', 'fe-csv-import-export' ); ?>">
					<?php esc_html_e( 'Documentation', 'fe-csv-import-export' ); ?>
				</a>
				<a href="<?php echo esc_url( FE_CSV_IMPORT_EXPORT_DEEPWIKI_URL ); ?>"
					target="_blank"
					title="<?php esc_attr_e( 'Go to DeepWiki documentation', 'fe-csv-import-export' ); ?>">
					<?php esc_html_e( 'DeepWiki', 'fe-csv-import-export' ); ?>
				</a>
				<a href="https://github.com/firstelementjp/fe-csv-import-export"
					target="_blank"
					title="<?php esc_attr_e( 'Go to GitHub repository', 'fe-csv-import-export' ); ?>"
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
					title="<?php esc_attr_e( 'Go to X', 'fe-csv-import-export' ); ?>"
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
					title="<?php esc_attr_e( 'Go to Facebook page', 'fe-csv-import-export' ); ?>"
					class="icon icon_fb">
				</a>
				<a href="<?php echo esc_url( $contact_url ); ?>"
					target="_blank"
					title="<?php esc_attr_e( 'Go to contact form', 'fe-csv-import-export' ); ?>"
					class="icon icon_mail">
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render export batch progress UI.
	 *
	 * @since 0.9.8
	 * @param string $batch_id Batch ID.
	 * @return void
	 */
	private function render_export_batch_progress( $batch_id ) {
		?>
		<div class="notice notice-info">
			<p><?php echo esc_html( sprintf( 'Batch export: %s', (string) $batch_id ) ); ?></p>
		</div>
			<?php
	}

	/**
	 * Render import batch progress UI.
	 *
	 * @since 0.9.8
	 * @param string $batch_id Batch ID.
	 * @return void
	 */
	private function render_batch_progress( $batch_id ) {
		?>
		<div class="notice notice-info">
			<p><?php echo esc_html( sprintf( 'Batch import: %s', (string) $batch_id ) ); ?></p>
		</div>
			<?php
	}

	/**
	 * Render import results UI.
	 *
	 * @since 0.9.8
	 * @param array $import_results Import results.
	 * @return void
	 */
	private function render_import_results( $import_results ) {
		$imported = (int) ( $import_results['imported'] ?? 0 );
		$updated  = (int) ( $import_results['updated'] ?? 0 );
		$errors   = (int) ( $import_results['errors'] ?? 0 );

		?>
		<div class="notice notice-success is-dismissible">
			<p>
			<?php
			printf(
				/* translators: 1: imported count, 2: updated count, 3: error count */
				esc_html__( 'Import finished. Created: %1$s, Updated: %2$s, Errors: %3$s', 'fe-csv-import-export' ),
				esc_html( (string) $imported ),
				esc_html( (string) $updated ),
				esc_html( (string) $errors )
			);
			?>
			</p>
				<?php if ( ! empty( $import_results['error_details'] ) && is_array( $import_results['error_details'] ) ) : ?>
				<ul>
					<?php foreach ( $import_results['error_details'] as $detail ) : ?>
						<li><?php echo esc_html( (string) $detail ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
			<?php
	}
}
