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
		add_menu_page(
			'Swift CSV',
			'Swift CSV',
			'manage_options',
			'swift-csv',
			[ $this, 'render_main_page' ],
			'dashicons-migrate',
			30
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
		if ( 'toplevel_page_swift-csv' === $hook ) {
			wp_enqueue_style(
				'swift-csv-admin',
				SWIFT_CSV_PLUGIN_URL . 'assets/css/style.css',
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
		if ( 'toplevel_page_swift-csv' === $hook ) {
			wp_enqueue_script(
				'swift-csv-admin',
				SWIFT_CSV_PLUGIN_URL . 'assets/js/admin.js',
				[],
				SWIFT_CSV_VERSION,
				true
			);
			wp_localize_script(
				'swift-csv-admin',
				'swiftCSV',
				[
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'swift_csv_nonce' ),
					'messages' => [
						'preparing'  => __( 'Preparing export...', 'swift-csv' ),
						'processing' => __( 'Processing posts...', 'swift-csv' ),
						'completed'  => __( 'Export completed!', 'swift-csv' ),
						'error'      => __( 'Export failed. Please try again.', 'swift-csv' ),
					],
				]
			);
		}
	}

	/**
	 * Render plugin header
	 *
	 * Displays professional header with version info and support links.
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
		$tab = in_array( $tab, [ 'export', 'import' ], true ) ? $tab : 'export';

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
			</nav>

			<div class="tab-content">
				<?php
				if ( 'export' === $tab ) {
					$this->render_export_tab();
				} elseif ( $batch_id ) {
					$this->render_batch_progress( $batch_id );
				} else {
					$this->render_import_tab( $import_results );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render batch progress page
	 *
	 * Displays real-time progress of batch import processing.
	 *
	 * @since  0.9.0
	 * @param  string $batch_id Batch ID to track.
	 * @return void
	 */
	private function render_batch_progress( $batch_id ) {
		?>
		<div class="card">
			<h3><?php esc_html_e( 'CSV Import Progress', 'swift-csv' ); ?></h3>
			<div id="batch-progress">
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

			<div id="batch-errors" class="swift-csv-batch-errors">
				<h3><?php esc_html_e( 'Error Details', 'swift-csv' ); ?></h3>
				<ul class="error-list"></ul>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var batchId = '<?php echo esc_js( $batch_id ); ?>';
			var progressInterval;

			function updateProgress() {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'swift_csv_batch_progress',
						nonce: '<?php echo wp_create_nonce( 'swift_csv_batch_nonce' ); ?>',
						batch_id: batchId
					},
					success: function(response) {
						if (response.success) {
							var data = response.data;

							// Update progress bar
							$('.progress-bar-fill').css('width', data.percentage + '%');
							$('.processed-rows').text(data.processed_rows);
							$('.total-rows').text(data.total_rows);
							$('.percentage').text(data.percentage);
							$('.created-count').text(data.created_rows);
							$('.updated-count').text(data.updated_rows);
							$('.error-count').text(data.error_rows);

							// Add ribbon classes if values exist
							if (data.created_rows > 0) {
								$('.created').addClass('has-count');
							}
							if (data.updated_rows > 0) {
								$('.modified').addClass('has-count');
							}
							if (data.error_rows > 0) {
								$('.errors').addClass('has-count');
							}

							// Show errors if any
							if (data.errors && data.errors.length > 0) {
								$('#batch-errors').show();
								var errorList = $('.error-list');
								errorList.empty();
								$.each(data.errors, function(index, error) {
									errorList.append('<li>' + error + '</li>');
								});
							}

							// Stop polling if completed
							if (data.status === 'completed') {
								clearInterval(progressInterval);
								$('.progress-bar').addClass('completed');
								// For UI testing: don't redirect, just show completion
								console.log('Batch processing completed - staying on page for UI testing');
							}
						}
					},
					error: function() {
						clearInterval(progressInterval);
						alert('<?php esc_html_e( 'Failed to get progress.', 'swift-csv' ); ?>');
					}
				});
			}

			// Start progress monitoring
			progressInterval = setInterval(updateProgress, 2000);
			updateProgress(); // Initial call
		});
		</script>

		<style>
		.progress-bar {
			width: 100%;
			height: 20px;
			background-color: #f0f0f0;
			border-radius: 10px;
			overflow: hidden;
			margin-bottom: 10px;
		}
		.progress-bar-fill {
			height: 100%;
			background-color: #0073aa;
			transition: width 0.3s ease;
		}
		.progress-bar.completed .progress-bar-fill {
			background-color: #46b450;
		}
		.progress-stats {
			font-weight: bold;
			margin-bottom: 15px;
		}
		.progress-details {
			display: flex;
			gap: 20px;
			margin-bottom: 15px;
		}
		.progress-details div {
			padding: 5px 10px;
			background-color: #f9f9f9;
			border-radius: 3px;
		}
		.error-list {
			max-height: 200px;
			overflow-y: auto;
			background-color: #fef7f7;
			border: 1px solid #dc3232;
			padding: 10px;
			border-radius: 3px;
		}
		.error-list li {
			color: #dc3232;
			margin-bottom: 5px;
		}
		</style>
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
		<div class="card">
			<h3><?php esc_html_e( 'Export Post Data', 'swift-csv' ); ?></h3>
			<form id="swift-csv-export-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'swift_csv_export', 'csv_export_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="post_type"><?php esc_html_e( 'Post Type', 'swift-csv' ); ?></label>
						</th>
						<td>
							<select name="post_type" id="post_type" required>
								<?php foreach ( $post_types as $post_type ) : ?>
									<option value="<?php echo esc_attr( $post_type->name ); ?>">
										<?php echo esc_html( $post_type->labels->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="posts_per_page"><?php esc_html_e( 'Number of Posts', 'swift-csv' ); ?></label>
						</th>
						<td>
							<input type="number" name="posts_per_page" id="posts_per_page" value="1000" min="1" max="5000">
							<p class="description"><?php esc_html_e( 'エクスポートする投稿数（バッチ処理で自動分割されます）', 'swift-csv' ); ?></p>
						</td>
					</tr>
				</table>

				<!-- Batch Export Progress -->
				<div id="swift-csv-export-progress" style="display: none;">
					<h3><?php esc_html_e( 'エクスポート進捗', 'swift-csv' ); ?></h3>
					<div class="progress-bar-container">
						<div class="progress-bar">
							<div class="progress-fill" style="width: 0%"></div>
						</div>
						<span class="progress-text">0%</span>
					</div>
					<p class="progress-status"><?php esc_html_e( 'エクスポートを準備しています...', 'swift-csv' ); ?></p>
					<div id="export-download-link" style="display: none;">
						<a href="#" class="button button-primary" download>
							<?php esc_html_e( 'CSVをダウンロード', 'swift-csv' ); ?>
						</a>
					</div>
				</div>

				<p class="submit">
					<input type="hidden" name="action" value="swift_csv_export">
					<input type="submit" name="export_csv" class="button button-primary" id="export-csv-btn" value="<?php esc_html_e( 'Export CSV', 'swift-csv' ); ?>">
				</p>
			</form>
			<?php
			$batch_id = isset( $_GET['batch'] ) ? sanitize_text_field( $_GET['batch'] ) : '';
			if ( ! empty( $batch_id ) ) :
				$ajax_url = admin_url( 'admin-ajax.php' );
				$nonce    = wp_create_nonce( 'swift_csv_nonce' );
				?>
				<script>
					(function () {
						var batchId = <?php echo wp_json_encode( $batch_id ); ?>;
						var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
						var nonce = <?php echo wp_json_encode( $nonce ); ?>;
						var progressEl = document.getElementById('swift-csv-export-progress');
						var fillEl = progressEl ? progressEl.querySelector('.progress-fill') : null;
						var textEl = progressEl ? progressEl.querySelector('.progress-text') : null;
						var statusEl = progressEl ? progressEl.querySelector('.progress-status') : null;
						var linkWrapEl = document.getElementById('export-download-link');
						var linkEl = linkWrapEl ? linkWrapEl.querySelector('a') : null;

						if (progressEl) {
							progressEl.style.display = 'block';
						}

						function poll() {
							var body = new URLSearchParams();
							body.append('action', 'swift_csv_export_progress');
							body.append('nonce', nonce);
							body.append('batch_id', batchId);

							fetch(ajaxUrl, {
								method: 'POST',
								headers: {
									'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
								},
								body: body.toString(),
								credentials: 'same-origin'
							})
							.then(function (r) { return r.json(); })
							.then(function (json) {
								if (!json || json.success !== true) {
									return;
								}

								var total = parseInt(json.total_rows || 0, 10);
								var done = parseInt(json.processed_rows || 0, 10);
								var percent = total > 0 ? Math.floor((done / total) * 100) : 0;

								if (fillEl) {
									fillEl.style.width = percent + '%';
								}
								if (textEl) {
									textEl.textContent = percent + '%';
								}
								if (statusEl) {
									statusEl.textContent = done + '/' + total;
								}

								if (json.completed && json.download_url) {
									if (linkWrapEl && linkEl) {
										linkEl.href = json.download_url;
										linkWrapEl.style.display = 'block';
									}
									window.location.href = json.download_url;
									return;
								}

								setTimeout(poll, 500);
							})
							.catch(function () {
								setTimeout(poll, 3000);
							});
						}

						setTimeout(poll, 300);
					})();
				</script>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render import results
	 *
	 * @since  0.9.3
	 * @param  array $results Import results.
	 * @return void
	 */
	private function render_import_results( $results ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Import completed!', 'swift-csv' ); ?></strong><br>
				<?php esc_html_e( 'Created:', 'swift-csv' ); ?> <?php echo $results['imported']; ?> <?php esc_html_e( 'posts', 'swift-csv' ); ?><br>
				<?php esc_html_e( 'Updated:', 'swift-csv' ); ?> <?php echo $results['updated']; ?> <?php esc_html_e( 'posts', 'swift-csv' ); ?>
				<?php if ( $results['errors'] > 0 ) : ?>
					<br><?php esc_html_e( 'Errors:', 'swift-csv' ); ?> <?php echo $results['errors']; ?>
				<?php endif; ?>
			</p>
		</div>

		<?php if ( ! empty( $results['error_details'] ) ) : ?>
			<div class="notice notice-error">
				<h3><?php esc_html_e( 'Errors:', 'swift-csv' ); ?></h3>
				<ul>
					<?php foreach ( $results['error_details'] as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
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
		<div class="card">
			<h3><?php esc_html_e( 'Import CSV File', 'swift-csv' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'swift_csv_import', 'csv_import_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="import_post_type"><?php esc_html_e( 'Post Type', 'swift-csv' ); ?></label>
						</th>
						<td>
							<select name="import_post_type" id="import_post_type" required>
								<?php foreach ( $post_types as $post_type ) : ?>
									<option value="<?php echo esc_attr( $post_type->name ); ?>">
										<?php echo esc_html( $post_type->labels->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Select the target post type for import.', 'swift-csv' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="csv_file"><?php esc_html_e( 'CSV File', 'swift-csv' ); ?></label>
						</th>
						<td>
							<input type="file" name="csv_file" id="csv_file" accept=".csv" required>
							<p class="description">
								<?php esc_html_e( 'CSVファイルを選択してください（UTF-8、Shift_JIS、EUC-JP、JIS対応）。', 'swift-csv' ); ?><br>
								<?php esc_html_e( 'The first row will be used as header for automatic mapping.', 'swift-csv' ); ?><br>
								<?php esc_html_e( 'Custom fields should be prefixed with "cf_" (example: cf_price).', 'swift-csv' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label>
								<input type="checkbox" name="update_existing" value="1">
								<?php esc_html_e( 'Update existing posts', 'swift-csv' ); ?>
							</label>
						</th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Updates existing posts when ID matches.', 'swift-csv' ); ?><br>
								<?php esc_html_e( 'If unchecked, creates new posts.', 'swift-csv' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="hidden" name="action" value="swift_csv_import">
					<input type="submit" name="import_csv" class="button button-primary" value="<?php esc_html_e( 'Import CSV', 'swift-csv' ); ?>">
				</p>
			</form>
		</div>
		<?php
	}
}
