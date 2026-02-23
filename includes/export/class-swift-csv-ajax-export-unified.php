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
class Swift_CSV_Ajax_Export_Unified {

	/**
	 * Constructor
	 *
	 * @since 0.9.8
	 */
	public function __construct() {
		// Enable unified handler for Direct SQL export.
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
		$initial_ob_level = function_exists( 'ob_get_level' ) ? ob_get_level() : 0;
		if ( function_exists( 'ob_start' ) ) {
			ob_start();
		}

		// Verify nonce.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'swift_csv_ajax_nonce' ) ) {
			while ( function_exists( 'ob_get_level' ) && function_exists( 'ob_end_clean' ) && ob_get_level() > $initial_ob_level ) {
				ob_end_clean();
			}
			wp_send_json_error( 'Security check failed.' );
			return;
		}

		// Check user capabilities.
		if ( ! current_user_can( 'export' ) ) {
			while ( function_exists( 'ob_get_level' ) && function_exists( 'ob_end_clean' ) && ob_get_level() > $initial_ob_level ) {
				ob_end_clean();
			}
			wp_send_json_error( 'Insufficient permissions.' );
			return;
		}

		// Get export method.
		$export_method = isset( $_POST['export_method'] ) ? sanitize_text_field( wp_unslash( $_POST['export_method'] ) ) : 'standard';

		// Get export configuration.
		$config = $this->get_export_config();

		try {
			// Rate limiting (Direct SQL only).
			if ( 'direct_sql' === $export_method ) {
				$this->check_rate_limit();
			}

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

			while ( function_exists( 'ob_get_level' ) && function_exists( 'ob_end_clean' ) && ob_get_level() > $initial_ob_level ) {
				ob_end_clean();
			}
			wp_send_json( $result );
		} catch ( Exception $e ) {
			while ( function_exists( 'ob_get_level' ) && function_exists( 'ob_end_clean' ) && ob_get_level() > $initial_ob_level ) {
				ob_end_clean();
			}
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
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'swift_csv_ajax_nonce' ) ) {
			wp_send_json_error( 'Security check failed.' );
			return;
		}

		$export_session = isset( $_POST['export_session'] ) ? sanitize_text_field( wp_unslash( $_POST['export_session'] ) ) : '';

		if ( empty( $export_session ) ) {
			wp_send_json_error( 'Invalid export session.' );
			return;
		}

		$enable_logs = isset( $_POST['enable_logs'] ) && in_array( (string) $_POST['enable_logs'], [ '1', 'true' ], true );
		if ( ! $enable_logs ) {
			wp_send_json_success(
				[
					'last_id' => 0,
					'logs'    => [],
				]
			);
			return;
		}

		$after_id = isset( $_POST['after_id'] ) ? intval( $_POST['after_id'] ) : 0;
		$limit    = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 100;
		$limit    = max( 1, min( 200, $limit ) );

		// Get logs directly.
		$transient_key = 'swift_csv_export_logs_' . get_current_user_id() . '_' . $export_session;
		$store         = get_transient( $transient_key );

		if ( ! is_array( $store ) ) {
			$payload = [
				'last_id' => 0,
				'logs'    => [],
			];
		} else {
			$logs = [];
			if ( ! empty( $store['logs'] ) && is_array( $store['logs'] ) ) {
				foreach ( $store['logs'] as $entry ) {
					if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
						continue;
					}
					$entry_id = intval( $entry['id'] );
					if ( $entry_id <= $after_id ) {
						continue;
					}
					$logs[] = $entry;
					if ( count( $logs ) >= $limit ) {
						break;
					}
				}
			}

			$payload = [
				'last_id' => intval( $store['last_id'] ?? 0 ),
				'logs'    => $logs,
			];
		}

		wp_send_json_success( $payload );
	}

	/**
	 * Check rate limiting
	 *
	 * @since 0.9.8
	 * @throws Exception When rate limit exceeded.
	 */
	private function check_rate_limit() {
		// Check for concurrent exports in the same session.
		$session_id   = session_id();
		$cache_key    = 'swift_csv_concurrent_export_' . $session_id;
		$is_exporting = get_transient( $cache_key );

		if ( $is_exporting ) {
			throw new Exception( 'Another export is already in progress. Please wait for it to complete.' );
		}

		// Set concurrent export flag.
		// This lock is released at request shutdown to allow batched requests.
		set_transient( $cache_key, true, 5 * MINUTE_IN_SECONDS );

		register_shutdown_function(
			static function () use ( $cache_key ) {
				delete_transient( $cache_key );
			}
		);

		return $cache_key;
	}

	/**
	 * Get export configuration from POST data
	 *
	 * @since 0.9.8
	 * @return array Export configuration.
	 */
	private function get_export_config() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		// Get basic configuration.
		$config = [
			'post_type'             => sanitize_text_field( wp_unslash( $_POST['post_type'] ?? 'post' ) ),
			'post_status'           => sanitize_text_field( wp_unslash( $_POST['post_status'] ?? 'publish' ) ),
			'export_scope'          => sanitize_text_field( wp_unslash( $_POST['export_scope'] ?? 'all' ) ),
			'include_taxonomies'    => isset( $_POST['include_taxonomies'] ) && in_array( (string) wp_unslash( $_POST['include_taxonomies'] ), [ '1', 'true' ], true ),
			'include_custom_fields' => isset( $_POST['include_custom_fields'] ) && in_array( (string) wp_unslash( $_POST['include_custom_fields'] ), [ '1', 'true' ], true ),
			'include_private_meta'  => isset( $_POST['include_private_meta'] ) ? (bool) $_POST['include_private_meta'] : false,
			'export_limit'          => isset( $_POST['export_limit'] ) ? absint( $_POST['export_limit'] ) : 0,
			'taxonomy_format'       => sanitize_text_field( wp_unslash( $_POST['taxonomy_format'] ?? 'name' ) ),
			'enable_logs'           => isset( $_POST['enable_logs'] ) ? (bool) $_POST['enable_logs'] : false,
		];

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

		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $config;
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
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		try {
			// Get parameters similar to standard export.
			$start_row      = intval( $_POST['start_row'] ?? 0 );
			$export_session = isset( $_POST['export_session'] ) ? sanitize_text_field( wp_unslash( $_POST['export_session'] ) ) : '';

			// Initialize session if first request.
			if ( '' === $export_session ) {
				$export_session = 'direct_sql_export_' . gmdate( 'Ymd_His' ) . '_' . wp_generate_uuid4();
			}

			// Initialize on first batch.
			if ( 0 === $start_row ) {
				$headers_key  = 'swift_csv_csv_headers_' . get_current_user_id() . '_' . $export_session;
				$headers_line = get_transient( $headers_key );
				if ( ! is_string( $headers_line ) || '' === $headers_line ) {
					$export       = new Swift_CSV_Export_Direct_SQL( $config );
					$headers      = $export->get_csv_headers_public();
					$headers_line = implode( ',', array_map( [ $this, 'escape_csv_field' ], $headers ) );
					set_transient( $headers_key, $headers_line, HOUR_IN_SECONDS );
				}

				// Initialize log store if logging enabled.
				$enable_logs = isset( $_POST['enable_logs'] ) && in_array( (string) $_POST['enable_logs'], [ '1', 'true' ], true );
				if ( $enable_logs ) {
					$this->init_export_log_store( $export_session );
				}
			}

			// Check for cancellation.
			if ( $this->is_cancelled( $export_session ) ) {
				wp_send_json_error( 'Export cancelled by user' );
				return;
			}

			$transient_key = 'swift_csv_unified_export_config_' . get_current_user_id() . '_' . $export_session;
			$export_config = get_transient( $transient_key );

			if ( 0 === $start_row || ! is_array( $export_config ) ) {
				$total_posts = $this->get_total_posts_count( $config );
				$batch_size  = $this->get_export_batch_size( $total_posts, $config['post_type'], $config );

				$export_config = [
					'total_posts' => $total_posts,
					'batch_size'  => $batch_size,
				];

				set_transient( $transient_key, $export_config, HOUR_IN_SECONDS );
			} else {
				$total_posts = isset( $export_config['total_posts'] ) ? (int) $export_config['total_posts'] : 0;
				$batch_size  = isset( $export_config['batch_size'] ) ? (int) $export_config['batch_size'] : 0;
			}

			// Create Direct SQL Export instance.
			$export = isset( $export ) && $export instanceof Swift_CSV_Export_Direct_SQL ? $export : new Swift_CSV_Export_Direct_SQL( $config );

			// Get posts for current batch.
			$posts_data = $export->get_posts_batch( $start_row, $batch_size );

			if ( empty( $posts_data ) ) {
				return [
					'success'        => true,
					'export_session' => $export_session,
					'processed'      => $start_row,
					'total'          => $total_posts,
					'continue'       => false,
					'progress'       => 100,
					'status'         => 'completed',
					'csv_chunk'      => '',
				];
			}

			// Generate CSV for this batch.
			$csv_chunk = $export->generate_csv_batch( $posts_data );
			if ( 0 === $start_row ) {
				$headers_key  = 'swift_csv_csv_headers_' . get_current_user_id() . '_' . $export_session;
				$headers_line = get_transient( $headers_key );
				if ( is_string( $headers_line ) && '' !== $headers_line ) {
					$csv_chunk = $headers_line . "\n" . $csv_chunk;
				}
			}

			// Add log entry if logging enabled.
			if ( isset( $_POST['enable_logs'] ) && in_array( (string) $_POST['enable_logs'], [ '1', 'true' ], true ) ) {
				$batch_number = floor( $start_row / $batch_size ) + 1;
				$message      = sprintf(
				/* translators: 1: Batch number, 2: Number of posts processed */
					__( 'Batch %1$d: Bulk export %2$d posts', 'swift-csv' ),
					$batch_number,
					count( $posts_data )
				);
				$this->append_export_log(
					$export_session,
					[
						'row'    => $start_row,
						'status' => 'success',
						'title'  => $message,
						'time'   => current_time( 'mysql' ),
					]
				);
			}

			$next_row = $start_row + count( $posts_data );
			$continue = $next_row < $total_posts;
			$progress = $total_posts > 0 ? round( ( $next_row / $total_posts ) * 100, 2 ) : 100;
			$progress = min( 100, max( 0, $progress ) );

			if ( ! $continue ) {
				$headers_key = 'swift_csv_csv_headers_' . get_current_user_id() . '_' . $export_session;
				delete_transient( $headers_key );
			}

			// Return batch progress.
			return [
				'success'        => true,
				'export_session' => $export_session,
				'processed'      => $next_row,
				'total'          => $total_posts,
				'continue'       => $continue,
				'progress'       => $progress,
				'status'         => $continue ? 'processing' : 'completed',
				'csv_chunk'      => $csv_chunk,
			];
		} catch ( Exception $e ) {
			throw new Exception( 'Direct SQL export failed: ' . esc_html( $e->getMessage() ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Handle standard export
	 *
	 * @since 0.9.8
	 * @param array $config Export configuration.
	 * @return array Export result.
	 */
	private function handle_standard_export( $config ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// Use existing standard export handler.
		$standard_handler = new Swift_CSV_Ajax_Export();
		return $standard_handler->handle_ajax_export();
	}

	/**
	 * Get export batch size
	 *
	 * @since 0.9.8
	 * @param int    $total_count Total posts count.
	 * @param string $post_type Post type.
	 * @param array  $config Export configuration.
	 * @return int Batch size.
	 */
	private function get_export_batch_size( $total_count, $post_type, $config ) {
		// Default batch size.
		$batch_size = 1000;

		// Dynamic batch size based on total count.
		if ( $total_count > 10000 ) {
			$batch_size = 2000;
		} elseif ( $total_count > 5000 ) {
			$batch_size = 1500;
		} elseif ( $total_count > 1000 ) {
			$batch_size = 1000;
		} else {
			$batch_size = 500;
		}

		// Apply filter for customization.
		return apply_filters( 'swift_csv_direct_sql_batch_size', $batch_size, $total_count, $post_type, $config );
	}

	/**
	 * Store CSV chunk for final assembly
	 *
	 * @since 0.9.8
	 * @param string $export_session Export session identifier.
	 * @param string $csv_chunk CSV chunk.
	 * @return void
	 */
	private function store_csv_chunk( $export_session, $csv_chunk ) {
		$chunks_key = 'swift_csv_csv_chunks_' . get_current_user_id() . '_' . $export_session;
		$chunks     = get_transient( $chunks_key );

		if ( ! $chunks ) {
			$chunks = [];
		}

		$chunks[] = $csv_chunk;
		set_transient( $chunks_key, $chunks, HOUR_IN_SECONDS );
	}

	/**
	 * Generate final CSV from stored chunks
	 *
	 * @since 0.9.8
	 * @param string $export_session Export session identifier.
	 * @return string Final CSV content.
	 */
	private function generate_final_csv( $export_session ) {
		$chunks_key  = 'swift_csv_csv_chunks_' . get_current_user_id() . '_' . $export_session;
		$chunks      = get_transient( $chunks_key );
		$headers_key = 'swift_csv_csv_headers_' . get_current_user_id() . '_' . $export_session;
		$headers     = get_transient( $headers_key );

		if ( ! $chunks ) {
			return '';
		}

		// Generate headers.
		$final_csv = is_string( $headers ) && '' !== $headers ? $headers . "\n" : '';

		// Combine all chunks.
		foreach ( $chunks as $chunk ) {
			$final_csv .= $chunk;
			if ( substr( $chunk, -1 ) !== "\n" ) {
				$final_csv .= "\n";
			}
		}

		// Clean up chunks.
		delete_transient( $chunks_key );
		delete_transient( $headers_key );

		return $final_csv;
	}

	/**
	 * Escape CSV field
	 *
	 * @since 0.9.8
	 * @param string $field Field value.
	 * @return string Escaped field.
	 */
	private function escape_csv_field( $field ) {
		$field = (string) $field;
		$field = str_replace( '"', '""', $field );
		return '"' . $field . '"';
	}

	/**
	 * Get CSV headers array
	 *
	 * @since 0.9.8
	 * @param array $config Export configuration.
	 * @return array CSV headers.
	 */
	private function get_csv_headers_array( array $config ): array {
		$export_scope = $config['export_scope'] ?? 'basic';
		$scope        = is_string( $export_scope ) ? $export_scope : 'basic';

		$basic_headers = [
			'ID',
			'post_title',
			'post_content',
			'post_status',
			'post_date',
			'post_modified',
			'post_name',
			'post_excerpt',
			'post_author',
			'comment_count',
			'menu_order',
		];

		$all_headers = array_merge(
			$basic_headers,
			[
				'post_type',
				'post_parent',
				'comment_status',
				'ping_status',
				'post_password',
				'post_sticky',
			]
		);

		switch ( $scope ) {
			case 'all':
				$headers = $all_headers;
				break;
			case 'basic':
			default:
				$headers = $basic_headers;
				break;
		}

		// Always include taxonomies for Direct SQL.
		$post_type  = $config['post_type'] ?? 'post';
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			$headers[] = 'tax_' . $taxonomy->name;
		}

		return apply_filters( 'swift_csv_export_headers', $headers, $config );
	}

	/**
	 * Get CSV headers line
	 *
	 * @since 0.9.8
	 * @param array $config Export configuration.
	 * @return string CSV headers line.
	 */
	private function get_csv_headers_line( array $config ): string {
		$headers = $this->get_csv_headers_array( $config );

		return implode(
			',',
			array_map(
				static function ( $header ) {
					return '"' . str_replace( '"', '""', (string) $header ) . '"';
				},
				$headers
			)
		);
	}

	/**
	 * Initialize export log store
	 *
	 * @since 0.9.8
	 * @param string $export_session Export session identifier.
	 * @return void
	 */
	private function init_export_log_store( $export_session ) {
		$transient_key = 'swift_csv_export_logs_' . get_current_user_id() . '_' . $export_session;
		set_transient(
			$transient_key,
			[
				'last_id' => 0,
				'logs'    => [],
			],
			3600
		);
	}

	/**
	 * Append export log entry
	 *
	 * @since 0.9.8
	 * @param string $export_session Export session identifier.
	 * @param array  $detail Export detail.
	 * @return int New log ID.
	 */
	private function append_export_log( $export_session, array $detail ) {
		$transient_key = 'swift_csv_export_logs_' . get_current_user_id() . '_' . $export_session;
		$store         = get_transient( $transient_key );
		if ( ! is_array( $store ) ) {
			$store = [
				'last_id' => 0,
				'logs'    => [],
			];
		}

		++$store['last_id'];
		$store['logs'][] = [
			'id'     => $store['last_id'],
			'detail' => $detail,
		];

		set_transient( $transient_key, $store, 3600 );
		return $store['last_id'];
	}

	/**
	 * Get export cancel option name
	 *
	 * Uses the same key format as the standard export handler to keep behavior consistent.
	 *
	 * @since 0.9.8
	 * @param string $export_session Export session identifier.
	 * @return string Option name.
	 */
	private function get_cancel_option_name( string $export_session ): string {
		return 'swift_csv_export_cancelled_' . get_current_user_id() . '_' . $export_session;
	}

	/**
	 * Check if export is cancelled
	 *
	 * @since 0.9.8
	 * @param string $export_session Export session identifier.
	 * @return bool True if cancelled.
	 */
	private function is_cancelled( $export_session ) {
		if ( empty( $export_session ) ) {
			return false;
		}

		$cancel_option_name = $this->get_cancel_option_name( (string) $export_session );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$option_value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$cancel_option_name
			)
		);

		return ! empty( $option_value );
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

		// Apply export limit if specified.
		if ( ! empty( $config['export_limit'] ) && $config['export_limit'] > 0 ) {
			$args['posts_per_page'] = $config['export_limit'];
		}

		$query = new WP_Query( $args );

		$found_posts  = $query->found_posts;
		$export_limit = $config['export_limit'] ?? 0;
		$total_posts  = $export_limit > 0 ? min( $found_posts, $export_limit ) : $found_posts;

		return $total_posts;
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
	private function process_standard_export_batch( $config, $start_row, $export_session ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$batch_size = 500;

		// Get posts for this batch.
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
			$transient_key = 'swift_csv_unified_export_config_' . get_current_user_id() . '_' . $export_session;
			$export_config = get_transient( $transient_key );
			$total_posts   = isset( $export_config['total_posts'] ) ? (int) $export_config['total_posts'] : 0;

			return [
				'success'     => true,
				'completed'   => true,
				'csv_content' => '',
				'processed'   => $start_row,
				'total'       => $total_posts,
			];
		}

		// Generate CSV for this batch.
		$csv_content = $this->generate_csv_for_posts( $post_ids, $config, 0 === $start_row );

		return [
			'success'     => true,
			'completed'   => false,
			'csv_content' => $csv_content,
			'processed'   => $start_row + count( $post_ids ),
			'total'       => isset( $export_config['total_posts'] ) ? (int) $export_config['total_posts'] : 0,
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
}

if ( ! class_exists( 'Swift_CSV_AJAX_Export_Unified' ) ) {
	class_alias( 'Swift_CSV_Ajax_Export_Unified', 'Swift_CSV_AJAX_Export_Unified' );
}
