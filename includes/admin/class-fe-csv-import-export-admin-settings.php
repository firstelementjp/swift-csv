<?php
/**
 * Admin settings handler
 *
 * Registers Settings API sections/fields and renders field HTML.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings handler
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class FE_CSV_Import_Export_Admin_Settings {

	/**
	 * Admin instance
	 *
	 * @var FE_CSV_Import_Export_Admin
	 */
	private $admin;

	/**
	 * Constructor
	 *
	 * @since 0.9.8
	 * @param FE_CSV_Import_Export_Admin $admin Admin instance.
	 */
	public function __construct( $admin ) {
		$this->admin = $admin;
	}

	/**
	 * Register plugin settings
	 *
	 * Registers all settings sections and fields using WordPress Settings API.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function register_settings() {
		// Export settings section.
		add_settings_section(
			'fe_csv_import_export_export_section',
			__( 'Export Settings', 'fe-csv-import-export' ),
			[ $this, 'export_section_description' ],
			'fe-csv-import-export'
		);

		add_settings_field(
			'fe_csv_import_export_export_post_type',
			'',
			[ $this, 'export_post_type_field_html' ],
			'fe-csv-import-export',
			'fe_csv_import_export_export_section'
		);

		add_settings_field(
			'fe_csv_import_export_export_post_status',
			'',
			[ $this, 'export_post_status_field_html' ],
			'fe-csv-import-export',
			'fe_csv_import_export_export_section'
		);

		add_settings_field(
			'fe_csv_import_export_export_scope',
			'',
			[ $this, 'export_scope_field_html' ],
			'fe-csv-import-export',
			'fe_csv_import_export_export_section'
		);

		add_settings_field(
			'fe_csv_import_export_include_taxonomies',
			'',
			[ $this, 'export_include_taxonomies_field_html' ],
			'fe-csv-import-export',
			'fe_csv_import_export_export_section'
		);

		add_settings_field(
			'fe_csv_import_export_include_custom_fields',
			'',
			[ $this, 'export_include_custom_fields_field_html' ],
			'fe-csv-import-export',
			'fe_csv_import_export_export_section'
		);

		add_settings_field(
			'fe_csv_import_export_export_limit',
			'',
			[ $this, 'export_limit_field_html' ],
			'fe-csv-import-export',
			'fe_csv_import_export_export_section'
		);

		// Import settings section.
		add_settings_section(
			'fe_csv_import_export_import_section',
			__( 'Import Settings', 'fe-csv-import-export' ),
			[ $this, 'import_section_description' ],
			'fe-csv-import-export'
		);

		add_settings_field(
			'fe_csv_import_export_import_post_type',
			'',
			[ $this, 'import_post_type_field_html' ],
			'fe-csv-import-export',
			'fe_csv_import_export_import_section'
		);

		add_settings_field(
			'fe_csv_import_export_import_update_existing',
			'',
			[ $this, 'import_update_existing_field_html' ],
			'fe-csv-import-export',
			'fe_csv_import_export_import_section'
		);

		add_settings_field(
			'fe_csv_import_export_import_taxonomy_format',
			'',
			[ $this, 'import_taxonomy_format_field_html' ],
			'fe-csv-import-export',
			'fe_csv_import_export_import_section'
		);

		add_settings_field(
			'fe_csv_import_export_import_dry_run',
			'',
			[ $this, 'import_dry_run_field_html' ],
			'fe-csv-import-export',
			'fe_csv_import_export_import_section'
		);

		// Advanced settings section.
		add_settings_section(
			'fe_csv_import_export_advanced_section',
			__( 'Advanced Settings', 'fe-csv-import-export' ),
			[ $this, 'advanced_section_description' ],
			'fe-csv-import-export'
		);

		add_settings_field(
			'fe_csv_import_export_advanced_enable_logs',
			'',
			[ $this, 'advanced_enable_logs_field_html' ],
			'fe-csv-import-export',
			'fe_csv_import_export_advanced_section'
		);

		add_settings_field(
			'fe_csv_import_export_advanced_uninstall_remove_all_data',
			'',
			[ $this, 'uninstall_data_removal_field_html' ],
			'fe-csv-import-export',
			'fe_csv_import_export_advanced_section'
		);

		add_settings_field(
			'fe_csv_import_export_advanced_tools_access',
			'',
			[ $this, 'advanced_tools_access_field_html' ],
			'fe-csv-import-export',
			'fe_csv_import_export_advanced_section'
		);

		// License section.
		add_settings_section(
			'fe_csv_import_export_license_section',
			'',
			[ $this, 'license_section_description' ],
			'fe-csv-import-export'
		);

		add_settings_field(
			'fe_csv_import_export_license_key',
			'',
			[ $this, 'license_field_html' ],
			'fe-csv-import-export',
			'fe_csv_import_export_license_section'
		);
	}

	/**
	 * Export section description callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function export_section_description() {
		echo '<p>' . esc_html__( 'Configure your CSV export settings below.', 'fe-csv-import-export' ) . '</p>';
	}
	/**
	 * Advanced section description callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function advanced_section_description() {
		echo '<p>' . esc_html__( 'Configure advanced settings below.', 'fe-csv-import-export' ) . '</p>';
	}

	/**
	 * Export post type field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function export_post_type_field_html() {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		?>
		<dl>
			<dt>
				<label for="fe_csv_import_export_export_post_type"><?php esc_html_e( 'Post Type', 'fe-csv-import-export' ); ?></label>
			</dt>
			<dd>
				<select name="fe_csv_import_export_export_post_type" id="fe_csv_import_export_export_post_type" required>
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
	 * Export post status field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function export_post_status_field_html() {
		?>
		<dl>
			<dt>
			<?php esc_html_e( 'Post Status', 'fe-csv-import-export' ); ?>
			</dt>
			<dd>
				<label class="fe-csv-import-export-block-label">
					<input type="radio" name="fe_csv_import_export_export_post_status" id="fe_csv_import_export_post_status_publish" value="publish" checked>
				<?php esc_html_e( 'Published posts only', 'fe-csv-import-export' ); ?>
				</label>
				<label class="fe-csv-import-export-block-label">
					<input type="radio" name="fe_csv_import_export_export_post_status" id="fe_csv_import_export_post_status_any" value="any">
				<?php esc_html_e( 'All statuses', 'fe-csv-import-export' ); ?>
				</label>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Export scope field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function export_scope_field_html() {
		$has_custom_hook = has_filter( 'fe_csv_import_export_export_post_fields' );
		?>
		<dl>
			<dt>
			<?php esc_html_e( 'Export Content', 'fe-csv-import-export' ); ?>
			</dt>
			<dd>
				<label class="fe-csv-import-export-block-label">
					<input type="radio" name="fe_csv_import_export_export_scope" id="fe_csv_import_export_export_scope_basic" value="basic" checked>
				<?php esc_html_e( 'Basic Fields', 'fe-csv-import-export' ); ?>
				</label>
				<label class="fe-csv-import-export-block-label">
					<input type="radio" name="fe_csv_import_export_export_scope" id="fe_csv_import_export_export_scope_all" value="all">
				<?php esc_html_e( 'All Fields', 'fe-csv-import-export' ); ?>
				</label>
			<?php if ( $has_custom_hook ) : ?>
				<p class="description fe-csv-import-export-hook-active-description">
					<?php esc_html_e( 'Hook-based customization is active. Export fields may be modified by code.', 'fe-csv-import-export' ); ?>
				</p>
				<?php endif; ?>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Export include taxonomies field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function export_include_taxonomies_field_html() {
		?>
		<dl>
			<dt>
			<?php esc_html_e( 'Taxonomies', 'fe-csv-import-export' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" id="fe_csv_import_export_include_taxonomies" name="fe_csv_import_export_include_taxonomies" value="1" checked>
				<?php esc_html_e( 'Include taxonomy columns', 'fe-csv-import-export' ); ?>
				</label>
				<div class="fe-csv-import-export-sub-options" style="margin-left: 20px; margin-top: 10px;">
					<div style="margin-left: 10px;">
						<label class="fe-csv-import-export-block-label">
							<input type="radio" id="fe_csv_import_export_taxonomy_format_name" name="taxonomy_format" value="name" checked>
						<?php esc_html_e( 'Names (name)', 'fe-csv-import-export' ); ?>
						</label>
						<label class="fe-csv-import-export-block-label">
							<input type="radio" id="fe_csv_import_export_taxonomy_format_id" name="taxonomy_format" value="id">
						<?php esc_html_e( 'Term IDs (term_id)', 'fe-csv-import-export' ); ?>
						</label>
						<label>
							<input type="checkbox" id="fe_csv_import_export_taxonomy_hierarchical" name="fe_csv_import_export_taxonomy_hierarchical" value="1">
						<?php esc_html_e( 'Display hierarchy', 'fe-csv-import-export' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Choose how taxonomy terms are exported: names for readability or term IDs for data integrity.', 'fe-csv-import-export' ); ?></p>
					</div>
				</div>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Export include custom fields field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function export_include_custom_fields_field_html() {
		?>
		<dl>
			<dt>
			<?php esc_html_e( 'Custom Fields', 'fe-csv-import-export' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" id="fe_csv_import_export_include_custom_fields" name="fe_csv_import_export_include_custom_fields" value="1" checked>
				<?php esc_html_e( 'Include custom field columns', 'fe-csv-import-export' ); ?>
				</label>
				<div class="fe-csv-import-export-sub-options" style="margin-left: 20px; margin-top: 10px;">
					<div style="margin-left: 10px;">
						<label>
							<input type="checkbox" id="fe_csv_import_export_include_private_meta" name="fe_csv_import_export_include_private_meta" value="1">
						<?php esc_html_e( 'Include private meta fields', 'fe-csv-import-export' ); ?>
						</label>
					</div>
				</div>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Export limit field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function export_limit_field_html() {
		?>
		<dl>
			<dt>
				<label for="fe_csv_import_export_export_limit"><?php esc_html_e( 'Export Limit', 'fe-csv-import-export' ); ?></label>
			</dt>
			<dd>
				<input type="number" name="fe_csv_import_export_export_limit" id="fe_csv_import_export_export_limit" min="0" value="0" placeholder="<?php esc_attr_e( 'No limit (0 = all)', 'fe-csv-import-export' ); ?>" class="small-text">
				<p class="description"><?php esc_html_e( 'Maximum number of posts to export. Enter 0 for no limit.', 'fe-csv-import-export' ); ?></p>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Import section description callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function import_section_description() {
		echo '<p>' . esc_html__( 'Configure your CSV import settings below.', 'fe-csv-import-export' ) . '</p>';
	}

	/**
	 * Import post type field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function import_post_type_field_html() {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		?>
		<dl>
			<dt>
				<label for="ajax_import_post_type"><?php esc_html_e( 'Post Type', 'fe-csv-import-export' ); ?></label>
			</dt>
			<dd>
				<select name="fe_csv_import_export_import_post_type" id="ajax_import_post_type" required>
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
	 * @since 0.9.8
	 * @return void
	 */
	public function import_update_existing_field_html() {
		?>
		<dl>
			<dt>
			<?php esc_html_e( 'Update Existing', 'fe-csv-import-export' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" name="fe_csv_import_export_import_update_existing" value="1">
				<?php esc_html_e( 'Update existing posts if they match by ID', 'fe-csv-import-export' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'If checked, existing posts will be updated. If unchecked, only new posts will be created.', 'fe-csv-import-export' ); ?></p>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Import taxonomy format field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function import_taxonomy_format_field_html() {
		?>
		<dl>
			<dt>
			<?php esc_html_e( 'Taxonomy Format', 'fe-csv-import-export' ); ?>
			</dt>
			<dd>
				<label class="fe-csv-import-export-block-label">
					<input type="radio" name="fe_csv_import_export_import_taxonomy_format" value="name" checked>
				<?php esc_html_e( 'Names', 'fe-csv-import-export' ); ?>
				</label>
				<label class="fe-csv-import-export-block-label">
					<input type="radio" name="fe_csv_import_export_import_taxonomy_format" value="id">
				<?php esc_html_e( 'Term IDs', 'fe-csv-import-export' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Select whether the term values in the CSV are names (text) or term IDs (numeric).', 'fe-csv-import-export' ); ?></p>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Advanced enable logs field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function advanced_enable_logs_field_html() {
		$enable_logs = class_exists( 'FE_CSV_Import_Export_Settings_Helper' )
			? (bool) FE_CSV_Import_Export_Settings_Helper::get( 'advanced', 'enable_logs', true )
			: true;
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Log Output', 'fe-csv-import-export' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" id="fe_csv_import_export_advanced_enable_logs" name="fe_csv_import_export_advanced_enable_logs" value="1" <?php checked( $enable_logs ); ?>>
					<?php esc_html_e( 'Output logs during import and export', 'fe-csv-import-export' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'If checked, detailed per-row logs will be generated and displayed during import and export. Disable for maximum speed.', 'fe-csv-import-export' ); ?></p>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Data removal on uninstall field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function uninstall_data_removal_field_html() {
		$uninstall_remove_all_data = class_exists( 'FE_CSV_Import_Export_Settings_Helper' )
			? (bool) FE_CSV_Import_Export_Settings_Helper::get( 'advanced', 'uninstall_remove_all_data', true )
			: true;
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Data Removal on Uninstall', 'fe-csv-import-export' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" id="fe_csv_import_export_advanced_uninstall_remove_all_data" name="fe_csv_import_export_advanced_uninstall_remove_all_data" value="1" <?php checked( $uninstall_remove_all_data ); ?>>
					<?php esc_html_e( 'Remove all plugin data when uninstalling', 'fe-csv-import-export' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'If checked, all plugin settings, transients, and custom database tables will be removed when the plugin is uninstalled. Uncheck to preserve data for future reinstallation.', 'fe-csv-import-export' ); ?></p>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Import dry run field callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function import_dry_run_field_html() {
		?>
		<dl>
			<dt>
			<?php esc_html_e( 'Dry Run', 'fe-csv-import-export' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" id="dry_run" name="fe_csv_import_export_import_dry_run" value="1">
				<?php esc_html_e( 'Test import without creating posts', 'fe-csv-import-export' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Run a test import to preview changes without modifying your data. (Dry Run)', 'fe-csv-import-export' ); ?></p>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Advanced tools access field callback
	 *
	 * Displays a Pro feature select for execution permission settings.
	 * Shows the field even when Pro is inactive, but disables it.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function advanced_tools_access_field_html() {
		$is_pro_license_active = class_exists( 'FE_CSV_Import_Export_License_Handler' )
			&& is_callable( [ 'FE_CSV_Import_Export_License_Handler', 'is_pro_active' ] )
			&& FE_CSV_Import_Export_License_Handler::is_pro_active();
		$has_pro_plugin        = class_exists( 'FE_CSV_Import_Export_Pro_Admin' )
			|| class_exists( 'FE_CSV_Import_Export_Pro_Settings_Helper' );
		$is_pro_ready          = $is_pro_license_active && $has_pro_plugin;

		$tools_scope = 'admin_only';
		if ( class_exists( 'FE_CSV_Import_Export_Pro_Settings_Helper' ) ) {
			$tools_scope = (string) FE_CSV_Import_Export_Pro_Settings_Helper::get( 'security', 'tools_access_scope', 'admin_only' );
		} else {
			$tools_scope = (string) get_option( 'fe_csv_import_export_pro_tools_access_scope', 'admin_only' );
		}

		$disabled_attr = $is_pro_ready ? '' : 'disabled';
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Execution Permission', 'fe-csv-import-export' ); ?>
			</dt>
			<dd>
				<label>
					<select name="tools_access_scope" id="fe-csv-import-export-pro-tools-access-scope" <?php echo esc_attr( $disabled_attr ); ?>>
						<option value="admin_only" <?php selected( $tools_scope, 'admin_only' ); ?>><?php esc_html_e( 'Administrators only', 'fe-csv-import-export' ); ?></option>
						<option value="admin_editor" <?php selected( $tools_scope, 'admin_editor' ); ?>><?php esc_html_e( 'Administrators and Editors', 'fe-csv-import-export' ); ?></option>
					</select>
					<?php
					if ( ! $is_pro_ready ) {
						echo ' (';
						echo '<a href="?page=fe-csv-import-export&tab=license">';
						esc_html_e( 'Pro', 'fe-csv-import-export' );
						echo '</a>)';
					}
					?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Set which user roles can execute import and export operations.', 'fe-csv-import-export' ); ?>
				</p>
			</dd>
		</dl>
		<?php
	}

	/**
	 * License section description callback
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function license_section_description() {
		echo '<p>' . esc_html__( 'Configure your license settings below.', 'fe-csv-import-export' ) . '</p>';
	}

	/**
	 * Renders the HTML for the license key input field and action buttons.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function license_field_html() {
		$license_data   = get_option( 'fe_csv_import_export_pro_license', [] );
		$products       = is_array( $license_data ) ? ( $license_data['products'] ?? [] ) : [];
		$pro_product_id = class_exists( 'FE_CSV_Import_Export_License_Handler' ) ? FE_CSV_Import_Export_License_Handler::get_pro_product_id() : 0;
		$pro_product    = is_array( $products ) && $pro_product_id ? ( $products[ $pro_product_id ] ?? [] ) : [];
		$license_key    = is_array( $pro_product ) ? ( $pro_product['key'] ?? '' ) : '';
		$license_key    = class_exists( 'FE_CSV_Import_Export_License_Handler' ) ? FE_CSV_Import_Export_License_Handler::maybe_decrypt_license_key( $license_key ) : $license_key;
		$license_status = FE_CSV_Import_Export_License_Handler::is_pro_active() ? 'active' : 'inactive';
		?>
		<dl>
			<dt>
				<label for="fe_csv_import_export_pro_license_key_input"><?php esc_html_e( 'Pro License Key', 'fe-csv-import-export' ); ?></label>
			</dt>
			<dd>
				<div class="fe-csv-import-export-license-input-group">
					<input
						type="password"
						autocomplete="off"
						id="fe_csv_import_export_pro_license_key_input"
						name="fe_csv_import_export_pro_license_key_input"
						value="<?php echo esc_attr( $license_key ); ?>"
						class="regular-text fe-csv-import-export-license-input"
					>
					<button
						type="button"
						id="fe_csv_import_export_pro_license_toggle_visibility"
						class="button button-secondary"
					>
					<?php esc_html_e( 'Show', 'fe-csv-import-export' ); ?>
					</button>
				<?php if ( 'active' === $license_status ) : ?>
					<button type="button" id="fe_csv_import_export_pro_license_deactivate" class="button button-secondary fe-csv-import-export-license-button" data-action="deactivate"><?php esc_html_e( 'Deactivate', 'fe-csv-import-export' ); ?></button>
				<?php else : ?>
					<button type="button" id="fe_csv_import_export_pro_license_activate" class="button button-primary fe-csv-import-export-license-button" data-action="activate"><?php esc_html_e( 'Activate', 'fe-csv-import-export' ); ?></button>
				<?php endif; ?>
					<span class="spinner fe-csv-import-export-spinner"></span>
				</div>

			<?php if ( 'active' === $license_status ) : ?>
				<p class="description fe-csv-import-export-license-valid">
					<?php esc_html_e( 'The license is valid.', 'fe-csv-import-export' ); ?>
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
						esc_html__( 'License expiration date: %1$s (remaining %2$s days)', 'fe-csv-import-export' ),
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
					$max_text = is_null( $times_activated_max ) ? esc_html__( 'Unlimited', 'fe-csv-import-export' ) : (string) (int) $times_activated_max;
					printf(
						/* translators: 1: times activated, 2: max activations */
						esc_html__( 'Activation count: %1$s / %2$s', 'fe-csv-import-export' ),
						esc_html( (string) $times_activated ),
						esc_html( $max_text )
					);
					?>
				</p>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e( 'Enter the license key you received at the time of purchase and press the "Activate" button.', 'fe-csv-import-export' ); ?>
				</p>

				<?php
				$error = get_transient( 'fe_csv_import_export_pro_license_error' );
				if ( $error ) :
					?>
					<p class="fe-csv-import-export-license-error"><?php echo esc_html( $error ); ?></p>
					<?php
					delete_transient( 'fe_csv_import_export_pro_license_error' );
				endif;
				?>

			<?php endif; ?>
			</dd>
		</dl>
		<?php
	}
}
