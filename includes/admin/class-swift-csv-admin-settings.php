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
class Swift_CSV_Admin_Settings {

	/**
	 * Admin instance
	 *
	 * @var Swift_CSV_Admin
	 */
	private $admin;

	/**
	 * Constructor
	 *
	 * @since 0.9.8
	 * @param Swift_CSV_Admin $admin Admin instance.
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
			'swift_csv_include_taxonomies',
			'',
			[ $this, 'export_include_taxonomies_field_html' ],
			'swift-csv',
			'swift_csv_export_section'
		);

		add_settings_field(
			'swift_csv_include_custom_fields',
			'',
			[ $this, 'export_include_custom_fields_field_html' ],
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
			'swift_csv_import_dry_run',
			'',
			[ $this, 'import_dry_run_field_html' ],
			'swift-csv',
			'swift_csv_import_section'
		);

		// Advanced settings section.
		add_settings_section(
			'swift_csv_advanced_section',
			__( 'Advanced Settings', 'swift-csv' ),
			[ $this, 'advanced_section_description' ],
			'swift-csv'
		);

		add_settings_field(
			'swift_csv_advanced_enable_logs',
			'',
			[ $this, 'advanced_enable_logs_field_html' ],
			'swift-csv',
			'swift_csv_advanced_section'
		);

		add_settings_field(
			'swift_csv_import_updraft_backup_before_import',
			'',
			[ $this, 'import_updraft_backup_before_import_field_html' ],
			'swift-csv',
			'swift_csv_advanced_section'
		);

		add_settings_field(
			'swift_csv_advanced_tools_access',
			'',
			[ $this, 'advanced_tools_access_field_html' ],
			'swift-csv',
			'swift_csv_advanced_section'
		);

		// License section.
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
	 * @since 0.9.8
	 * @return void
	 */
	public function export_section_description() {
		echo '<p>' . esc_html__( 'Configure your CSV export settings below.', 'swift-csv' ) . '</p>';
	}
	/**
	 * Advanced section description callback
	 *
	 * @since 0.9.14
	 * @return void
	 */
	public function advanced_section_description() {
		echo '<p>' . esc_html__( 'Configure advanced settings below.', 'swift-csv' ) . '</p>';
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
	 * Import UpdraftPlus backup before import field callback
	 *
	 * Displays a Pro feature checkbox that triggers an UpdraftPlus database backup
	 * before starting import when the Pro plugin is licensed.
	 *
	 * @since 0.9.13
	 * @return void
	 */
	public function import_updraft_backup_before_import_field_html() {
		global $updraftplus_admin;

		$is_updraft_available = is_a( $updraftplus_admin, 'UpdraftPlus_Admin' )
			&& is_callable( [ $updraftplus_admin, 'add_backup_scaffolding' ] )
			&& is_callable( [ $updraftplus_admin, 'backupnow_modal_contents' ] );

		if ( ! $is_updraft_available ) {
			return;
		}

		$is_pro_license_active = class_exists( 'Swift_CSV_License_Handler' )
			&& is_callable( [ 'Swift_CSV_License_Handler', 'is_pro_active' ] )
			&& Swift_CSV_License_Handler::is_pro_active();
		$has_pro_plugin        = class_exists( 'Swift_CSV_Pro_Admin' )
			|| class_exists( 'Swift_CSV_Pro_Settings_Helper' );
		$is_pro_ready          = $is_pro_license_active && $has_pro_plugin;

		$checkbox_id   = 'swift-csv-pro-backup-before-import';
		$disabled_attr = $is_pro_ready ? '' : 'disabled';
		$checked_attr  = $is_pro_ready ? 'checked' : '';
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Safety', 'swift-csv' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" id="<?php echo esc_attr( $checkbox_id ); ?>" <?php echo esc_attr( $checked_attr ); ?> <?php echo esc_attr( $disabled_attr ); ?>>
					<?php
					echo wp_kses(
						__( 'Run UpdraftPlus database backup before executing import', 'swift-csv' ),
						[
							'a' => [
								'href'   => [],
								'target' => [],
								'rel'    => [],
							],
						]
					);
					if ( ! $is_pro_ready ) {
						echo ' (';
						echo '<a href="?page=swift-csv&tab=license">';
						esc_html_e( 'Pro', 'swift-csv' );
						echo '</a>)';
					}
					?>
				</label>
				<p class="description">
					<?php esc_html_e( 'A backup dialog will open. Start the backup and the import will begin automatically after it completes.', 'swift-csv' ); ?>
				</p>
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
		$has_custom_hook = has_filter( 'swift_csv_export_post_fields' );
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
			<?php if ( $has_custom_hook ) : ?>
				<p class="description swift-csv-hook-active-description">
					<?php esc_html_e( 'Hook-based customization is active. Export fields may be modified by code.', 'swift-csv' ); ?>
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
			<?php esc_html_e( 'Taxonomies', 'swift-csv' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" id="swift_csv_include_taxonomies" name="swift_csv_include_taxonomies" value="1" checked>
				<?php esc_html_e( 'Include taxonomy columns', 'swift-csv' ); ?>
				</label>
				<div class="swift-csv-sub-options" style="margin-left: 20px; margin-top: 10px;">
					<div style="margin-left: 10px;">
						<label class="swift-csv-block-label">
							<input type="radio" id="swift_csv_taxonomy_format_name" name="taxonomy_format" value="name" checked>
						<?php esc_html_e( 'Names (name)', 'swift-csv' ); ?>
						</label>
						<label class="swift-csv-block-label">
							<input type="radio" id="swift_csv_taxonomy_format_id" name="taxonomy_format" value="id">
						<?php esc_html_e( 'Term IDs (term_id)', 'swift-csv' ); ?>
						</label>
						<label>
							<input type="checkbox" id="swift_csv_taxonomy_hierarchical" name="swift_csv_taxonomy_hierarchical" value="1">
						<?php esc_html_e( 'Display hierarchy', 'swift-csv' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Choose how taxonomy terms are exported: names for readability or term IDs for data integrity.', 'swift-csv' ); ?></p>
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
			<?php esc_html_e( 'Custom Fields', 'swift-csv' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" id="swift_csv_include_custom_fields" name="swift_csv_include_custom_fields" value="1" checked>
				<?php esc_html_e( 'Include custom field columns', 'swift-csv' ); ?>
				</label>
				<div class="swift-csv-sub-options" style="margin-left: 20px; margin-top: 10px;">
					<div style="margin-left: 10px;">
						<label>
							<input type="checkbox" id="swift_csv_include_private_meta" name="swift_csv_include_private_meta" value="1">
						<?php esc_html_e( 'Include private meta fields', 'swift-csv' ); ?>
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
	 * @since 0.9.8
	 * @return void
	 */
	public function import_section_description() {
		echo '<p>' . esc_html__( 'Configure your CSV import settings below.', 'swift-csv' ) . '</p>';
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
	 * @since 0.9.8
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
	 * Import taxonomy format field callback
	 *
	 * @since 0.9.8
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
	 * Advanced enable logs field callback
	 *
	 * @since 0.9.15
	 * @return void
	 */
	public function advanced_enable_logs_field_html() {
		$enable_logs = class_exists( 'Swift_CSV_Settings_Helper' )
			? (bool) Swift_CSV_Settings_Helper::get( 'advanced', 'enable_logs', true )
			: true;
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Log Output', 'swift-csv' ); ?>
			</dt>
			<dd>
				<label>
					<input type="checkbox" id="swift_csv_advanced_enable_logs" name="swift_csv_advanced_enable_logs" value="1" <?php checked( $enable_logs ); ?>>
					<?php esc_html_e( 'Output logs during import and export', 'swift-csv' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'If checked, detailed per-row logs will be generated and displayed during import and export. Disable for maximum speed.', 'swift-csv' ); ?></p>
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
	 * Advanced tools access field callback
	 *
	 * Displays a Pro feature select for execution permission settings.
	 * Shows the field even when Pro is inactive, but disables it.
	 *
	 * @since 0.9.17
	 * @return void
	 */
	public function advanced_tools_access_field_html() {
		$is_pro_license_active = class_exists( 'Swift_CSV_License_Handler' )
			&& is_callable( [ 'Swift_CSV_License_Handler', 'is_pro_active' ] )
			&& Swift_CSV_License_Handler::is_pro_active();
		$has_pro_plugin        = class_exists( 'Swift_CSV_Pro_Admin' )
			|| class_exists( 'Swift_CSV_Pro_Settings_Helper' );
		$is_pro_ready          = $is_pro_license_active && $has_pro_plugin;

		$tools_scope = 'admin_only';
		if ( class_exists( 'Swift_CSV_Pro_Settings_Helper' ) ) {
			$tools_scope = (string) Swift_CSV_Pro_Settings_Helper::get( 'security', 'tools_access_scope', 'admin_only' );
		} else {
			$tools_scope = (string) get_option( 'swift_csv_pro_tools_access_scope', 'admin_only' );
		}

		$disabled_attr = $is_pro_ready ? '' : 'disabled';
		?>
		<dl>
			<dt>
				<?php esc_html_e( 'Execution Permission', 'swift-csv' ); ?>
			</dt>
			<dd>
				<label>
					<select name="tools_access_scope" id="swift-csv-pro-tools-access-scope" <?php echo esc_attr( $disabled_attr ); ?>>
						<option value="admin_only" <?php selected( $tools_scope, 'admin_only' ); ?>><?php esc_html_e( 'Administrators only', 'swift-csv' ); ?></option>
						<option value="admin_editor" <?php selected( $tools_scope, 'admin_editor' ); ?>><?php esc_html_e( 'Administrators and Editors', 'swift-csv' ); ?></option>
					</select>
					<?php
					if ( ! $is_pro_ready ) {
						echo ' (';
						echo '<a href="?page=swift-csv&tab=license">';
						esc_html_e( 'Pro', 'swift-csv' );
						echo '</a>)';
					}
					?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Set which user roles can execute import and export operations.', 'swift-csv' ); ?>
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
		echo '<p>' . esc_html__( 'Configure your license settings below.', 'swift-csv' ) . '</p>';
	}

	/**
	 * Renders the HTML for the license key input field and action buttons.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function license_field_html() {
		$license_data   = get_option( 'swift_csv_pro_license', [] );
		$products       = is_array( $license_data ) ? ( $license_data['products'] ?? [] ) : [];
		$pro_product_id = class_exists( 'Swift_CSV_License_Handler' ) ? Swift_CSV_License_Handler::PRODUCT_ID_PRO : 0;
		$pro_product    = is_array( $products ) && $pro_product_id ? ( $products[ $pro_product_id ] ?? [] ) : [];
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
