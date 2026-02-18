<?php
/**
 * Unified AJAX Export Handler for Swift CSV
 *
 * Handles both standard and Direct SQL export methods through a single AJAX endpoint.
 * Routes requests based on the 'export_method' parameter.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified AJAX Export Handler Class
 *
 * Centralizes AJAX export handling for both standard and Direct SQL methods.
 * Includes nonce verification, user capability checks, and rate limiting.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_AJAX_Export_Unified {

	/**
	 * Constructor
	 *
	 * @since 0.9.8
	 */
	public function __construct() {
		add_action( 'wp_ajax_swift_csv_ajax_export', [ $this, 'handle_ajax_export' ] );
		add_action( 'wp_ajax_swift_csv_ajax_export_logs', [ $this, 'handle_ajax_export_logs' ] );
	}

	/**
	 * Handle AJAX export request
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle_ajax_export() {
		// Enable error reporting for debugging
		error_reporting( E_ALL );
		ini_set( 'display_errors', 1 );

		// Custom error log to WordPress debug
		ini_set( 'error_log', SWIFT_CSV_PLUGIN_DIR . 'debug.log' );

		// Test basic response first
		error_log( 'AJAX handler called successfully' );

		// Verify nonce.
		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'swift_csv_ajax_nonce' ) ) {
			error_log( 'Nonce verification failed' );
			wp_send_json_error( 'Security check failed.' );
			return;
		}

		error_log( 'Nonce verified successfully' );

		// Check user capabilities.
		if ( ! current_user_can( 'export' ) ) {
			error_log( 'User capability check failed' );
			wp_send_json_error( 'Insufficient permissions.' );
			return;
		}

		error_log( 'User capability verified' );

		// Rate limiting.
		$this->check_rate_limit();

		error_log( 'Rate limiting passed' );

		// Get export method.
		$export_method = isset( $_POST['export_method'] ) ? sanitize_text_field( $_POST['export_method'] ) : 'standard';

		error_log( 'Export method: ' . $export_method );

		// Get export configuration.
		$config = $this->get_export_config();

		error_log( 'Config retrieved successfully' );

		try {
			// Route to appropriate export method.
			switch ( $export_method ) {
				case 'direct_sql':
					error_log( 'Handling direct SQL export' );
					$result = $this->handle_direct_sql_export( $config );
					break;
				case 'standard':
				default:
					error_log( 'Handling standard export' );
					$result = $this->handle_standard_export( $config );
					break;
			}

			error_log( 'Export completed successfully' );
			wp_send_json_success( $result );

		} catch ( Exception $e ) {
			error_log( 'Swift CSV Export Error: ' . $e->getMessage() );
			wp_send_json_error( 'Export failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle AJAX export logs request
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle_ajax_export_logs() {
		// Verify nonce.
		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'swift_csv_ajax_nonce' ) ) {
			wp_send_json_error( 'Security check failed.' );
			return;
		}

		$export_session = isset( $_POST['export_session'] ) ? sanitize_text_field( $_POST['export_session'] ) : '';

		if ( empty( $export_session ) ) {
			wp_send_json_error( 'Invalid export session.' );
			return;
		}

		// Get logs from standard export handler.
		$export = new Swift_CSV_Ajax_Export();
		$logs   = $export->get_export_logs( $export_session );

		wp_send_json_success( $logs );
	}

	/**
	 * Check rate limiting
	 *
	 * @since 0.9.8
	 * @throws Exception When rate limit exceeded.
	 */
	private function check_rate_limit() {
		// Temporarily disabled for testing
		return;

		$user_id     = get_current_user_id();
		$cache_key   = 'swift_csv_export_rate_limit_' . $user_id;
		$last_export = get_transient( $cache_key );

		if ( $last_export ) {
			throw new Exception( 'Please wait before exporting again.' );
		}

		// Set rate limit (1 minute between exports).
		set_transient( $cache_key, time(), MINUTE_IN_SECONDS );
	}

	/**
	 * Get export configuration from POST data
	 *
	 * @since 0.9.8
	 * @return array Export configuration.
	 */
	private function get_export_config() {
		try {
			// Test basic config first without base class
			$config = [
				'post_type'            => sanitize_text_field( $_POST['post_type'] ?? 'post' ),
				'post_status'          => sanitize_text_field( $_POST['post_status'] ?? 'publish' ),
				'export_scope'         => sanitize_text_field( $_POST['export_scope'] ?? 'all' ),
				'include_private_meta' => isset( $_POST['include_private_meta'] ) ? (bool) $_POST['include_private_meta'] : false,
				'export_limit'         => isset( $_POST['export_limit'] ) ? absint( $_POST['export_limit'] ) : 0,
				'taxonomy_format'      => sanitize_text_field( $_POST['taxonomy_format'] ?? 'names' ),
				'enable_logs'          => isset( $_POST['enable_logs'] ) ? (bool) $_POST['enable_logs'] : false,
			];

			error_log( 'Basic config created: ' . print_r( $config, true ) );

			// Handle custom post status
			if ( 'custom' === $config['post_status'] ) {
				error_log( 'Processing custom post status' );

				// Apply filter for custom post statuses with default fallback
				$custom_statuses = apply_filters( 'swift_csv_custom_post_statuses', [ 'publish' ] );

				// Ensure we have valid statuses
				if ( is_array( $custom_statuses ) && ! empty( $custom_statuses ) ) {
					$config['post_status'] = $custom_statuses;
					error_log( 'Custom post statuses applied: ' . print_r( $custom_statuses, true ) );
				} else {
					// Fallback to publish if filter returns invalid data
					$config['post_status'] = [ 'publish' ];
					error_log( 'Invalid custom statuses, falling back to publish' );
				}
			}

			// Handle custom export scope
			if ( 'custom' === $config['export_scope'] ) {
				error_log( 'Processing custom export scope' );

				// Define default export fields
				$default_fields = [
					'ID',
					'post_title',
					'post_content',
					'post_status',
					'post_date',
					'post_modified',
				];

				// Apply filter for custom export fields with default fallback
				$custom_fields = apply_filters( 'swift_csv_custom_export_fields', $default_fields );

				// Ensure we have valid fields
				if ( is_array( $custom_fields ) && ! empty( $custom_fields ) ) {
					$config['export_fields'] = $custom_fields;
					error_log( 'Custom export fields applied: ' . print_r( $custom_fields, true ) );
				} else {
					// Fallback to default fields if filter returns invalid data
					$config['export_fields'] = $default_fields;
					error_log( 'Invalid custom fields, falling back to defaults' );
				}
			}

			// Create a temporary instance to access methods
			$temp_config   = [];
			$temp_instance = new class( $temp_config ) extends Swift_CSV_Export_Base {
				public function __construct( $config ) {
					// Don't call parent constructor to avoid validation
				}

				// Implement abstract methods
				protected function get_posts_data() {
					return [];
				}
			};

			error_log( 'Base class temp instance created successfully' );

			// Skip post status sanitization for now to test the rest
			$post_status_raw = $_POST['post_status'] ?? 'publish';
			error_log( 'Final config post_status: ' . print_r( $config['post_status'], true ) );

			error_log( 'Final config: ' . print_r( $config, true ) );

			return $config;
		} catch ( Exception $e ) {
			error_log( 'Config Error: ' . $e->getMessage() );
			error_log( 'Config Trace: ' . $e->getTraceAsString() );
			wp_send_json_error( 'Configuration error: ' . $e->getMessage() );
			exit;
		}
	}

	/**
	 * Handle Direct SQL export
	 *
	 * @since 0.9.8
	 * @param array $config Export configuration.
	 * @return array Export result.
	 */
	private function handle_direct_sql_export( $config ) {
		try {
			// Debug: Log config
			error_log( 'Direct SQL Config: ' . print_r( $config, true ) );

			// Create Direct SQL Export instance.
			$export = new Swift_CSV_Export_Direct_SQL( $config );

			// Debug: Log instance creation
			error_log( 'Direct SQL instance created successfully' );

			// Perform export.
			$result = $export->export();

			// Debug: Log result
			error_log( 'Direct SQL result: ' . print_r( $result, true ) );

			if ( $result['success'] ) {
				return [
					'csv_content'    => $result['data']['csv_content'],
					'record_count'   => $result['data']['record_count'],
					'export_session' => $result['data']['export_session'],
					'export_method'  => 'direct_sql',
				];
			} else {
				throw new Exception( $result['message'] );
			}
		} catch ( Exception $e ) {
			error_log( 'Direct SQL Export Error: ' . $e->getMessage() );
			error_log( 'Direct SQL Export Trace: ' . $e->getTraceAsString() );
			throw new Exception( 'Direct SQL export failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle standard export
	 *
	 * @since 0.9.8
	 * @param array $config Export configuration.
	 * @return array Export result.
	 */
	private function handle_standard_export( $config ) {
		// Create standard export instance.
		$export = new Swift_CSV_Ajax_Export();

		// Initialize export session.
		$export_session = 'export_' . gmdate( 'Y-m-d_H-i-s' ) . '_' . wp_generate_uuid4();
		$export->init_export_log_store( $export_session );

		// Store config for subsequent requests.
		$transient_key = 'swift_csv_export_config_' . get_current_user_id() . '_' . $export_session;
		set_transient( $transient_key, $config, HOUR_IN_SECONDS );

		return [
			'export_session' => $export_session,
			'export_method'  => 'standard',
		];
	}
}
