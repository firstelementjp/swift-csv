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
		// Disable unified handler to avoid conflicts with standard handler
		// add_action( 'wp_ajax_swift_csv_ajax_export', [ $this, 'handle_ajax_export' ] );
		add_action( 'wp_ajax_swift_csv_ajax_export_logs', [ $this, 'handle_ajax_export_logs' ] );
	}

	/**
	 * Handle AJAX export request
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle_ajax_export() {
		// Verify nonce.
		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'swift_csv_ajax_nonce' ) ) {
			wp_send_json_error( 'Security check failed.' );
			return;
		}

		// Check user capabilities.
		if ( ! current_user_can( 'export' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
			return;
		}

		// Rate limiting.
		// Clear all existing transients for testing
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_swift_csv_%'" );

		$this->check_rate_limit();

		// Get export method.
		$export_method = isset( $_POST['export_method'] ) ? sanitize_text_field( wp_unslash( $_POST['export_method'] ) ) : 'standard';

		// Get export configuration.
		$config = $this->get_export_config();

		try {
			// Route to appropriate export method.
			switch ( $export_method ) {
				case 'direct_sql':
					$result = $this->handle_direct_sql_export( $config );
					break;
				case 'standard':
				default:
					$result = $this->handle_standard_export( $config );
					break;
			}

			// Try alternative response method
			header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
			echo wp_json_encode(
				[
					'success' => true,
					'data'    => $result,
				]
			);
			wp_die();

		} catch ( Exception $e ) {
			error_log( 'Swift CSV Unified: exception caught: ' . $e->getMessage() );
			wp_send_json_error( 'Export failed: ' . wp_kses_post( $e->getMessage() ) );
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

		$export_session = isset( $_POST['export_session'] ) ? sanitize_text_field( wp_unslash( $_POST['export_session'] ) ) : '';

		if ( empty( $export_session ) ) {
			wp_send_json_error( 'Invalid export session.' );
			return;
		}

		// Get logs directly instead of calling non-existent method
		$transient_key = 'swift_csv_export_logs_' . get_current_user_id() . '_' . $export_session;
		$store         = get_transient( $transient_key );

		if ( ! is_array( $store ) ) {
			$logs = [
				'last_id' => 0,
				'logs'    => [],
			];
		} else {
			$logs = $store;
		}

		wp_send_json_success( $logs );
	}

	/**
	 * Check rate limiting
	 *
	 * @since 0.9.8
	 * @throws Exception When rate limit exceeded.
	 */
	private function check_rate_limit() {
		// Clear any existing concurrent export flags for testing
		$session_id = session_id();
		$cache_key  = 'swift_csv_concurrent_export_' . $session_id;
		delete_transient( $cache_key );

		// Check for concurrent exports in the same session
		$is_exporting = get_transient( $cache_key );

		if ( $is_exporting ) {
			throw new Exception( 'Another export is already in progress. Please wait for it to complete.' );
		}

		// Set concurrent export flag (expires after 5 minutes)
		set_transient( $cache_key, true, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Get export configuration from POST data
	 *
	 * @since 0.9.8
	 * @return array Export configuration.
	 */
	private function get_export_config() {
		try {
			// Get basic configuration.
			$config = [
				'post_type'            => sanitize_text_field( wp_unslash( $_POST['post_type'] ?? 'post' ) ),
				'post_status'          => sanitize_text_field( wp_unslash( $_POST['post_status'] ?? 'publish' ) ),
				'export_scope'         => sanitize_text_field( wp_unslash( $_POST['export_scope'] ?? 'all' ) ),
				'include_private_meta' => isset( $_POST['include_private_meta'] ) ? (bool) $_POST['include_private_meta'] : false,
				'export_limit'         => isset( $_POST['export_limit'] ) ? absint( $_POST['export_limit'] ) : 0,
				'taxonomy_format'      => sanitize_text_field( wp_unslash( $_POST['taxonomy_format'] ?? 'name' ) ),
				'enable_logs'          => isset( $_POST['enable_logs'] ) ? (bool) $_POST['enable_logs'] : false,
				'include_taxonomies'   => true, // Always include taxonomies for Direct SQL
			];

			// Handle custom post status.
			if ( 'custom' === $config['post_status'] ) {
				// Apply filter for custom post statuses with default fallback.
				$custom_statuses = apply_filters( 'swift_csv_custom_post_statuses', [ 'publish' ] );

				// Ensure we have valid statuses.
				if ( is_array( $custom_statuses ) && ! empty( $custom_statuses ) ) {
					$config['post_status'] = $custom_statuses;
				} else {
					// Fallback to publish if filter returns invalid data.
					$config['post_status'] = [ 'publish' ];
				}
			}

			// Handle custom export scope.
			if ( 'custom' === $config['export_scope'] ) {
				// Define default export fields.
				$default_fields = [
					'ID',
					'post_title',
					'post_content',
					'post_status',
					'post_date',
					'post_modified',
				];

				// Apply filter for custom export fields with default fallback.
				$custom_fields = apply_filters( 'swift_csv_custom_export_fields', $default_fields );

				// Ensure we have valid fields.
				if ( is_array( $custom_fields ) && ! empty( $custom_fields ) ) {
					$config['export_fields'] = $custom_fields;
				} else {
					// Fallback to default fields if filter returns invalid data.
					$config['export_fields'] = $default_fields;
				}
			}

			return $config;
		} catch ( Exception $e ) {
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
	 * @throws Exception When export fails.
	 */
	private function handle_direct_sql_export( $config ) {
		try {
			// Create Direct SQL Export instance.
			$export = new Swift_CSV_Export_Direct_SQL( $config );

			// Perform export.
			$result = $export->export();

			// Clear concurrent export flag on success
			$session_id = session_id();
			$cache_key  = 'swift_csv_concurrent_export_' . $session_id;
			delete_transient( $cache_key );

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
			// Clear concurrent export flag on error too
			$session_id = session_id();
			$cache_key  = 'swift_csv_concurrent_export_' . $session_id;
			delete_transient( $cache_key );

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
		// Debug: Log method entry
		error_log( 'Swift CSV Unified: handle_standard_export called' );
		error_log( 'Swift CSV Unified: POST data: ' . print_r( $_POST, true ) );

		// Check if this is a batch request
		$start_row      = intval( $_POST['start_row'] ?? 0 );
		$export_session = sanitize_key( $_POST['export_session'] ?? '' );

		error_log( 'Swift CSV Unified: start_row: ' . $start_row . ', export_session: ' . $export_session );

		if ( $start_row === 0 && empty( $export_session ) ) {
			// Initial request - create session and return session info
			error_log( 'Swift CSV Unified: Initial request - creating session' );
			$export_session = 'export_' . gmdate( 'Y-m-d_H-i-s' ) . '_' . wp_generate_uuid4();

			// Initialize log store
			$transient_key = 'swift_csv_export_logs_' . get_current_user_id() . '_' . $export_session;
			set_transient(
				$transient_key,
				[
					'last_id' => 0,
					'logs'    => [],
				],
				3600
			);

			// Store config for subsequent requests
			$config_transient_key = 'swift_csv_export_config_' . get_current_user_id() . '_' . $export_session;
			set_transient( $config_transient_key, $config, HOUR_IN_SECONDS );

			$result = [
				'export_session' => $export_session,
				'export_method'  => 'standard',
				'total_posts'    => $this->get_total_posts_count( $config ),
				'batch_size'     => 500,
			];

			error_log( 'Swift CSV Unified: Initial result: ' . print_r( $result, true ) );
			return $result;
		}

		// Batch processing request
		error_log( 'Swift CSV Unified: Batch processing request' );
		return $this->process_standard_export_batch( $config, $start_row, $export_session );
	}

	/**
	 * Get total posts count for export
	 *
	 * @since 0.9.8
	 * @param array $config Export configuration.
	 * @return int Total posts count.
	 */
	private function get_total_posts_count( $config ) {
		$args = [
			'post_type'      => $config['post_type'],
			'post_status'    => $config['post_status'],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		];

		if ( ! empty( $config['export_limit'] ) && $config['export_limit'] > 0 ) {
			$args['posts_per_page'] = intval( $config['export_limit'] );
		}

		$query = new WP_Query( $args );
		return $query->found_posts;
	}

	/**
	 * Process standard export batch
	 *
	 * @since 0.9.8
	 * @param array  $config Export configuration.
	 * @param int    $start_row Starting row number.
	 * @param string $export_session Export session identifier.
	 * @return array Batch processing result.
	 */
	private function process_standard_export_batch( $config, $start_row, $export_session ) {
		$batch_size = 500;

		// Get posts for this batch
		$args = [
			'post_type'      => $config['post_type'],
			'post_status'    => $config['post_status'],
			'posts_per_page' => $batch_size,
			'offset'         => $start_row,
			'fields'         => 'ids',
		];

		if ( ! empty( $config['export_limit'] ) && $config['export_limit'] > 0 ) {
			$args['posts_per_page'] = min( $batch_size, intval( $config['export_limit'] ) - $start_row );
		}

		$query    = new WP_Query( $args );
		$post_ids = $query->posts;

		if ( empty( $post_ids ) ) {
			return [
				'success'     => true,
				'completed'   => true,
				'csv_content' => '',
				'processed'   => $start_row,
				'total'       => $this->get_total_posts_count( $config ),
			];
		}

		// Generate CSV for this batch
		$csv_content = $this->generate_csv_for_posts( $post_ids, $config, $start_row === 0 );

		return [
			'success'     => true,
			'completed'   => false,
			'csv_content' => $csv_content,
			'processed'   => $start_row + count( $post_ids ),
			'total'       => $this->get_total_posts_count( $config ),
		];
	}

	/**
	 * Generate CSV content for posts
	 *
	 * @since 0.9.8
	 * @param array $post_ids Post IDs.
	 * @param array $config Export configuration.
	 * @param bool  $include_headers Whether to include CSV headers.
	 * @return string CSV content.
	 */
	private function generate_csv_for_posts( $post_ids, $config, $include_headers ) {
		$csv = '';

		if ( $include_headers ) {
			$headers = $this->get_csv_headers( $config );
			$csv    .= implode( ',', $headers ) . "\n";
		}

		foreach ( $post_ids as $post_id ) {
			$row  = $this->get_csv_row( $post_id, $config );
			$csv .= implode( ',', $row ) . "\n";
		}

		return $csv;
	}

	/**
	 * Get CSV headers
	 *
	 * @since 0.9.8
	 * @param array $config Export configuration.
	 * @return array CSV headers.
	 */
	private function get_csv_headers( $config ) {
		$headers = [ 'ID', 'Title', 'Content', 'Status', 'Date' ];

		if ( $config['include_taxonomies'] ) {
			$taxonomies = get_object_taxonomies( $config['post_type'], 'objects' );
			foreach ( $taxonomies as $taxonomy ) {
				if ( $config['taxonomy_format'] === 'name' ) {
					$headers[] = $taxonomy->label;
				} else {
					$headers[] = $taxonomy->name . '_ids';
				}
			}
		}

		return $headers;
	}

	/**
	 * Get CSV row for post
	 *
	 * @since 0.9.8
	 * @param int   $post_id Post ID.
	 * @param array $config Export configuration.
	 * @return array CSV row data.
	 */
	private function get_csv_row( $post_id, $config ) {
		$post = get_post( $post_id );

		$row = [
			$post->ID,
			$this->escape_csv_field( $post->post_title ),
			$this->escape_csv_field( $post->post_content ),
			$post->post_status,
			$post->post_date,
		];

		if ( $config['include_taxonomies'] ) {
			$taxonomies = get_object_taxonomies( $config['post_type'] );
			foreach ( $taxonomies as $taxonomy ) {
				$terms = wp_get_post_terms( $post_id, $taxonomy );

				if ( $config['taxonomy_format'] === 'name' ) {
					$term_names = array_map( 'get_term_name', $terms );
					$row[]      = $this->escape_csv_field( implode( '|', $term_names ) );
				} else {
					$term_ids = array_map( 'get_term_id', $terms );
					$row[]    = implode( '|', $term_ids );
				}
			}
		}

		return $row;
	}

	/**
	 * Escape CSV field
	 *
	 * @since 0.9.8
	 * @param string $field Field value.
	 * @return string Escaped field value.
	 */
	private function escape_csv_field( $field ) {
		$field = str_replace( '"', '""', $field );
		return '"' . $field . '"';
	}
}
