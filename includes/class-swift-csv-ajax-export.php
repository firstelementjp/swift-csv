<?php
/**
 * Ajax Export Handler for Swift CSV
 *
 * Handles asynchronous CSV export with chunked processing for large datasets.
 * Supports basic, all fields, and custom export scopes.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax Export Handler Class
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Export {

	/**
	 * Batch size for processing posts
	 *
	 * @since 0.9.0
	 * @var int
	 */
	private const BATCH_SIZE = 500;

	/**
	 * Whether Pro license is active
	 *
	 * @since 0.9.7
	 * @var bool|null
	 */
	private static $pro_license_active = null;

	/**
	 * Constructor
	 *
	 * @since 0.9.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_swift_csv_ajax_export', [ $this, 'handle_ajax_export' ] );
		add_action( 'wp_ajax_swift_csv_cancel_export', [ $this, 'handle_ajax_export_cancel' ] );
	}

	/**
	 * Check if Pro version is licensed (static cached for performance)
	 *
	 * @since 0.9.7
	 * @return bool True if Pro version is available and license is active
	 */
	private function is_pro_version_licensed() {
		if ( null === self::$pro_license_active ) {
			self::$pro_license_active = Swift_CSV_License_Handler::is_pro_active();
		}
		return self::$pro_license_active;
	}

	/**
	 * Get export cancel option name
	 *
	 * @since 0.9.0
	 * @param string $export_session Export session identifier
	 * @return string Option name
	 */
	private function get_cancel_option_name( string $export_session ): string {
		return 'swift_csv_export_cancelled_' . get_current_user_id() . '_' . $export_session;
	}

	/**
	 * Cleanup old cancel flags for current user
	 *
	 * This prevents cancelled session flags from accumulating in the options table.
	 *
	 * @since 0.9.0
	 * @return void
	 */
	private function cleanup_old_cancel_flags(): void {
		global $wpdb;

		$user_id = get_current_user_id();
		$prefix  = 'swift_csv_export_cancelled_' . $user_id . '_';
		$like    = $wpdb->esc_like( $prefix ) . '%';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			)
		);
	}

	/**
	 * Get dynamic batch size for export processing
	 *
	 * @since 0.9.7
	 * @param int    $total_count Total number of posts to export
	 * @param string $post_type Post type being exported
	 * @param array  $config Export configuration
	 * @return int Dynamic batch size
	 */
	private function get_export_batch_size( int $total_count, string $post_type, array $config ): int {
		// Determine threshold for row-by-row vs batch processing
		$row_processing_threshold = 100;

		/**
		 * Filter the threshold for switching between row-by-row and batch processing for export
		 *
		 * Allows developers to customize when to switch from row-by-row processing
		 * to batch processing based on their specific needs and server capabilities.
		 *
		 * @since 0.9.7
		 * @param int $threshold Number of rows at which to switch to batch processing.
		 * @param int $total_count Total number of posts to export.
		 * @param string $post_type Post type being exported.
		 * @param array $config Export configuration.
		 * @return int Modified threshold.
		 */
		$row_processing_threshold = apply_filters(
			'swift_csv_export_row_processing_threshold',
			$row_processing_threshold,
			$total_count,
			$post_type,
			$config
		);

		// Determine base batch size based on actual export limit, not total count
		$actual_export_count = min( $total_count, $export_limit );
		$base_batch_size     = ( $actual_export_count <= $row_processing_threshold ) ? 1 : self::BATCH_SIZE;

		/**
		 * Filter the batch size for export processing
		 *
		 * Allows developers to customize batch size based on their specific needs,
		 * server capabilities, or data characteristics.
		 *
		 * @since 0.9.7
		 * @param int $batch_size Current batch size (1 for row-by-row, 500 for batch).
		 * @param int $total_count Total number of posts to export.
		 * @param string $post_type Post type being exported.
		 * @param array $config Export configuration.
		 * @return int Modified batch size.
		 */
		return apply_filters(
			'swift_csv_export_batch_size',
			$base_batch_size,
			$total_count,
			$post_type,
			$config
		);
	}

	/**
	 * Check whether export should be cancelled
	 *
	 * Notes:
	 * - connection_aborted() enables immediate cancellation when the browser aborts the fetch.
	 * - A direct DB read avoids stale get_option() cache within the same PHP request.
	 *
	 * @since 0.9.0
	 * @param string $export_session Export session identifier
	 * @return bool True if the current export should be cancelled
	 */
	private function is_cancelled( string $export_session ): bool {
		if ( connection_aborted() ) {
			return true;
		}

		$cancel_option_name = $this->get_cancel_option_name( $export_session );

		global $wpdb;
		$option_value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$cancel_option_name
			)
		);

		return ! is_null( $option_value );
	}

	/**
	 * Check whether the request has been aborted
	 *
	 * This is a fast check intended to be used inside hot loops.
	 *
	 * @since 0.9.0
	 * @return bool True if the client connection has been aborted
	 */
	private function is_connection_aborted(): bool {
		return connection_aborted();
	}

	/*
	 * Public Methods - Main Entry Points
	 */

	/**
	 * Normalize CSV headers by removing empty values and duplicates
	 *
	 * Cleans up header array by removing empty strings, trimming whitespace,
	 * eliminating duplicates, and ensuring 'ID' is always the first header.
	 *
	 * @since 0.9.0
	 * @param string[] $headers Array of CSV header strings
	 * @return string[] Normalized headers array with ID as first element
	 */
	private function normalize_headers( array $headers ): array {
		$headers = array_values( array_filter( array_map( 'trim', (array) $headers ), 'strlen' ) );
		$headers = array_values( array_unique( $headers ) );

		$headers = array_values(
			array_filter(
				$headers,
				function ( $h ) {
					return $h !== 'ID';
				}
			)
		);
		array_unshift( $headers, 'ID' );

		return $headers;
	}

	/*
	 * Private Helper Methods - Data Preparation
	 */

	/**
	 * Get post status for WP_Query based on user selection
	 *
	 * Converts the user's post status selection into the appropriate
	 * value for WP_Query post_status parameter.
	 *
	 * @since 0.9.0
	 * @param string $post_type The post type being exported
	 * @param string $post_status The user's selection ('publish', 'any', 'custom')
	 * @return string|array The post status value for WP_Query
	 */
	private function get_post_status_for_query( string $post_type, string $post_status ) {
		switch ( $post_status ) {
			case 'publish':
				// For attachment type, use 'inherit' instead of 'publish'
				return $post_type === 'attachment' ? 'inherit' : 'publish';

			case 'any':
				return 'any';

			case 'custom':
				/**
				 * Filter custom post status for export queries
				 *
				 * Allows developers to specify custom post status filtering
				 * when 'custom' option is selected in the export interface.
				 *
				 * @since 0.9.0
				 * @param string|array $post_status Post status(es) to query
				 * @param string $post_type The post type being exported
				 * @param array $args Additional context including export parameters
				 * @return string|array Modified post status(es)
				 */
				$custom_args = [
					'post_type' => $post_type,
					'context'   => 'custom_post_status',
				];

				$custom_status = apply_filters( 'swift_csv_export_post_status_query', 'publish', $custom_args );

				// Ensure we return a valid value
				return is_array( $custom_status ) ? $custom_status : (string) $custom_status;

			default:
				// Fallback to publish for safety
				return $post_type === 'attachment' ? 'inherit' : 'publish';
		}
	}

	/**
	 * Get allowed post fields for CSV export based on scope
	 *
	 * Returns an array of WordPress post field names that should be included
	 * in the CSV export based on the specified scope. The 'basic' scope includes
	 * essential fields, while 'all' scope includes additional metadata fields.
	 *
	 * @since 0.9.0
	 * @param string $export_scope The export scope ('basic', 'all')
	 * @return string[] Array of allowed post field names
	 */
	private function get_allowed_post_fields( string $export_scope = 'basic' ): array {
		$basic_fields = [
			'ID',
			'post_title',
			'post_content',
			'post_excerpt',
			'post_status',
			'post_name',
			'post_date',
			'post_author',
		];

		if ( 'all' === $export_scope ) {
			$additional_fields = [
				'post_date_gmt',
				'post_modified',
				'post_modified_gmt',
				'post_parent',
				'menu_order',
				'guid',
				'comment_status',
				'ping_status',
				'post_type',
			];

			/**
			 * Filter additional post fields for 'all' export scope
			 *
			 * Allows developers to modify the additional fields included in 'all' scope exports.
			 *
			 * @since 0.9.0
			 * @param array $additional_fields Array of additional field names
			 * @param string $export_scope The current export scope
			 * @return array Modified additional fields array
			 */
			$additional_fields = apply_filters( 'swift_csv_additional_post_fields', $additional_fields, $export_scope );

			return array_merge( $basic_fields, $additional_fields );
		}

		/**
		 * Filter basic post fields for CSV export
		 *
		 * Allows developers to modify the basic fields included in exports.
		 *
		 * @since 0.9.0
		 * @param array $basic_fields Array of basic field names
		 * @param string $export_scope The current export scope
		 * @return array Modified basic fields array
		 */
		$basic_fields = apply_filters( 'swift_csv_basic_post_fields', $basic_fields, $export_scope );

		return $basic_fields;
	}

	/**
	 * Build CSV headers based on post type and export scope
	 *
	 * Generates a comprehensive array of CSV headers including post fields,
	 * taxonomy terms, and custom fields. Supports different export scopes and
	 * private meta field inclusion options.
	 *
	 * @since 0.9.0
	 * @param string $post_type The post type to export
	 * @param string $export_scope The export scope ('basic', 'all', 'custom')
	 * @param bool   $include_private_meta Whether to include private meta fields
	 * @param string $post_status The post status to query
	 * @return string[] Array of CSV header strings
	 */
	private function build_headers( string $post_type, string $export_scope = 'basic', bool $include_private_meta = false, string $post_status = 'publish' ): array {
		$export_scope         = is_string( $export_scope ) ? $export_scope : 'basic';
		$include_private_meta = (bool) $include_private_meta;

		// Get allowed post fields using common function
		$headers = $this->get_allowed_post_fields( $export_scope );

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );

		// Hook for taxonomy object filtering
		/**
		 * Filter taxonomy objects for export header generation
		 *
		 * Allows developers to filter which taxonomy objects should be included
		 * in header generation. This hook receives the actual taxonomy objects,
		 * enabling selective inclusion/exclusion based on taxonomy properties.
		 *
		 * @since 0.9.0
		 * @param array $taxonomies Array of taxonomy objects
		 * @param array $args Export arguments including context
		 * @return array Modified taxonomy objects array
		 */
		$taxonomy_filter_args = [
			'post_type'            => $post_type,
			'export_scope'         => $export_scope,
			'include_private_meta' => $include_private_meta,
			'context'              => 'taxonomy_objects_filter',
		];
		$taxonomies           = apply_filters( 'swift_csv_filter_taxonomy_objects', $taxonomies, $taxonomy_filter_args );

		// Build taxonomy headers from filtered taxonomy objects
		$taxonomy_headers = [];
		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->public ) {
				$taxonomy_headers[] = 'tax_' . $taxonomy->name;
			}
		}

		// Merge post fields and taxonomies
		$headers = array_merge( $headers, $taxonomy_headers );

		// Apply sample query hook for meta field discovery
		/**
		 * Filter sample query arguments for meta field discovery
		 *
		 * Allows developers to customize the sample post query used to discover
		 * custom field keys for header generation.
		 *
		 * @since 0.9.0
		 * @param array $query_args Sample query arguments
		 * @param array $args Export arguments including context
		 * @return array Modified query arguments
		 */
		$sample_args                         = [
			'post_type' => $post_type,
			'context'   => 'meta_discovery',
		];
		$sample_query_args                   = [
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => 1,
			'orderby'        => 'post_date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		];
		$sample_query_args                   = apply_filters( 'swift_csv_sample_query_args', $sample_query_args, $sample_args );
		$sample_query_args['posts_per_page'] = 1; // Ensure only 1 post
		$sample_post_ids                     = get_posts( $sample_query_args );

		// Hook for sample post filtering (Pro version optimization)
		/**
		 * Filter sample posts for meta key discovery
		 *
		 * Allows developers to customize which sample posts are used for meta key
		 * discovery. This hook is ideal for Pro versions that may want to use
		 * specific posts for better field detection.
		 *
		 * @since 0.9.0
		 * @param array $sample_post_ids Sample post IDs
		 * @param array $args Export arguments including context
		 * @return array Modified sample post IDs
		 */
		$sample_filter_args = [
			'post_type'            => $post_type,
			'export_scope'         => $export_scope,
			'include_private_meta' => $include_private_meta,
			'context'              => 'sample_posts_filter',
		];
		$sample_post_ids    = apply_filters( 'swift_csv_filter_sample_posts', $sample_post_ids, $sample_filter_args );

		$all_meta_keys      = [];
		$found_private_meta = false;

		foreach ( $sample_post_ids as $sample_post_id ) {
			$sample_post_id = (int) $sample_post_id;
			$post_meta      = get_post_meta( $sample_post_id );
			$meta_keys      = array_keys( (array) $post_meta );

			foreach ( $meta_keys as $meta_key ) {
				if ( ! in_array( $meta_key, $all_meta_keys ) ) {
					$all_meta_keys[] = $meta_key;
				}
				if ( str_starts_with( $meta_key, '_' ) ) {
					$found_private_meta = true;
				}
			}

			if ( $found_private_meta && ! $include_private_meta ) {
				break; // Found what we need
			}
		}

		// Hook for meta key classification
		/**
		 * Filter and classify discovered meta keys
		 *
		 * Allows developers to classify meta keys into different categories
		 * (regular, private) for specialized processing. This hook enables
		 * custom field type classification and processing.
		 *
		 * @since 0.9.0
		 * @param array $all_meta_keys All discovered meta keys
		 * @param array $args Export arguments including context
		 * @return array Classified meta keys array with 'regular', 'private' keys
		 */
		$meta_classify_args = [
			'post_type'            => $post_type,
			'export_scope'         => $export_scope,
			'include_private_meta' => $include_private_meta,
			'context'              => 'meta_key_classification',
		];

		$classified_meta_keys = apply_filters( 'swift_csv_classify_meta_keys', $all_meta_keys, $meta_classify_args );

		// License check: Only allow ACF processing if Pro version is active and licensed
		if ( ! $this->is_pro_version_licensed() ) {
			// Remove ACF keys from classification if not licensed
			if ( isset( $classified_meta_keys['acf'] ) ) {
				// Move ACF keys to regular keys (they'll be treated as regular custom fields)
				if ( isset( $classified_meta_keys['regular'] ) && is_array( $classified_meta_keys['regular'] ) ) {
					$classified_meta_keys['regular'] = array_merge(
						$classified_meta_keys['regular'],
						$classified_meta_keys['acf'] ?? []
					);
				}
				unset( $classified_meta_keys['acf'] );
			}
		}

		// Ensure classified structure exists
		if ( ! is_array( $classified_meta_keys ) || ! isset( $classified_meta_keys['regular'] ) ) {
			// Fallback: create basic classification
			$classified_meta_keys = [
				'regular' => [],
				'private' => [],
			];

			foreach ( $all_meta_keys as $meta_key ) {
				if ( str_starts_with( $meta_key, '_' ) ) {
					$classified_meta_keys['private'][] = $meta_key;
				} else {
					$classified_meta_keys['regular'][] = $meta_key;
				}
			}
		}

		// Hook for custom field headers generation
		/**
		 * Generate custom field headers for export
		 *
		 * Allows extensions to generate custom field headers from classified meta keys.
		 * This hook enables custom header generation with different prefixes based on field type.
		 *
		 * @since 0.9.0
		 * @param array $custom_field_headers Array of custom field headers (empty array to start)
		 * @param array $classified_meta_keys Classified meta keys with 'regular', 'private' keys
		 * @param array $args Export arguments including context
		 * @return array Complete custom field headers array
		 */
		$custom_field_args = [
			'post_type'            => $post_type,
			'export_scope'         => $export_scope,
			'include_private_meta' => $include_private_meta,
			'context'              => 'custom_field_headers_generation',
		];

		$custom_field_headers = apply_filters( 'swift_csv_generate_custom_field_headers', [], $classified_meta_keys, $custom_field_args );

		// Fallback: if no hook implementation, use basic processing
		if ( empty( $custom_field_headers ) ) {
			$custom_field_headers = [];

			// Process regular fields
			foreach ( $classified_meta_keys['regular'] as $meta_key ) {
				if ( ! is_string( $meta_key ) || $meta_key === '' ) {
					continue;
				}
				$custom_field_headers[] = 'cf_' . $meta_key;
			}

			// Process private fields if allowed
			if ( $include_private_meta ) {
				foreach ( $classified_meta_keys['private'] as $meta_key ) {
					if ( ! is_string( $meta_key ) || $meta_key === '' ) {
						continue;
					}
					$custom_field_headers[] = 'cf_' . $meta_key;
				}
			}
		}

		// Merge all three header types
		$headers = array_merge( $headers, $custom_field_headers );

		return $this->normalize_headers( $headers );
	}

	/*
	 * Private Helper Methods - CSV Generation
	 */

	/**
	 * Generate a CSV row string from an array of values
	 *
	 * Converts an array of values into a properly formatted CSV string using
	 * PHP's built-in fputcsv function with proper escaping and quoting.
	 *
	 * @since 0.9.0
	 * @param mixed[] $row Array of values to convert to CSV
	 * @return string CSV formatted string
	 */
	private function fputcsv_row( array $row ): string {
		$fh = fopen( 'php://temp', 'r+' );
		fputcsv( $fh, $row );
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );
		return $csv;
	}

	/**
	 * Normalize quotes and escape characters for CSV output
	 *
	 * Handles various quote escaping scenarios to ensure proper CSV formatting.
	 * Fixes double-escaped quotes, converts escaped quotes, and handles edge cases
	 * with literal backslashes before quotes.
	 *
	 * @since 0.9.0
	 * @param string $field The field value to normalize
	 * @return string The normalized field value
	 */
	private function normalize_quotes( string $field ): string {
		// Fix double escaped quotes that are already properly escaped
		$field = preg_replace( '/\\\\"\\\\"/', '""', $field );

		// Convert remaining escaped quotes to regular quotes
		$field = str_replace( '\\"', '"', $field );

		// Handle edge case of literal backslash before quote
		$field = preg_replace( '/\\\\\\\\(")/', '\\\\"$1', $field );

		return $field;
	}

	/**
	 * Handle Ajax export requests with chunked processing
	 *
	 * Processes AJAX requests for CSV export with support for large datasets
	 * through chunked processing. Handles security validation, parameter parsing,
	 * data retrieval, and CSV generation with progress tracking.
	 *
	 * @since 0.9.0
	 * @return void Sends JSON response with export data or error message
	 */
	public function handle_ajax_export(): void {
		try {
			// Security check
			check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );
			ignore_user_abort( false );

			// Get parameters
			$post_type            = sanitize_text_field( $_POST['post_type'] ?? 'post' );
			$post_status          = sanitize_text_field( $_POST['post_status'] ?? 'publish' );
			$export_scope         = sanitize_text_field( $_POST['export_scope'] ?? 'basic' );
			$include_private_meta = ! empty( $_POST['include_private_meta'] ) && (string) $_POST['include_private_meta'] === '1';
			$export_limit         = ! empty( $_POST['export_limit'] ) ? intval( $_POST['export_limit'] ) : 0;
			$taxonomy_format      = sanitize_text_field( $_POST['taxonomy_format'] ?? 'name' );
			$start_row            = intval( $_POST['start_row'] ?? 0 );
			$export_session       = sanitize_key( $_POST['export_session'] ?? '' );
			if ( '' === $export_session ) {
				wp_send_json_error( 'Missing export session' );
				return;
			}

			if ( 0 === $start_row ) {
				$this->cleanup_old_cancel_flags();
			}

			if ( $this->is_cancelled( $export_session ) ) {
				wp_send_json_error( 'Export cancelled by user' );
				return;
			}

			// Validate post type
			if ( ! post_type_exists( $post_type ) ) {
				wp_send_json_error( 'Invalid post type' );
			}

			// Get total posts count with limit
			$query_post_status      = $this->get_post_status_for_query( $post_type, $post_status );
			$total_posts_query_args = [
				'post_type'      => $post_type,
				'post_status'    => $query_post_status,
				'posts_per_page' => $export_limit > 0 ? $export_limit : -1,
				'fields'         => 'ids',
			];

			// Apply export query filter for full export
			/**
			 * Filter export query arguments for count retrieval
			 *
			 * Allows developers to customize the query used to retrieve
			 * the total count of posts for export progress tracking.
			 *
			 * @since 0.9.0
			 * @param array $query_args Export query arguments
			 * @param array $args Export arguments including context
			 * @return array Modified query arguments
			 */
			$export_query_args = [
				'post_type'    => $post_type,
				'context'      => 'count_retrieval',
				'export_limit' => $export_limit,
			];
			$filtered_args     = apply_filters( 'swift_csv_export_count_query_args', $total_posts_query_args, $export_query_args );

			// Preserve export limit regardless of filter modifications
			$filtered_args['posts_per_page'] = $export_limit > 0 ? $export_limit : -1;

			$total_posts_query_args = $filtered_args;

			// Use WP_Query for efficient count retrieval
			$total_query = new WP_Query( $total_posts_query_args );
			$total_count = $total_query->found_posts;

			// Define max_posts_to_process
			$max_posts_to_process = $export_limit > 0 ? $export_limit : $total_count;

			// Get dynamic batch size for export
			$export_config = [
				'post_type'    => $post_type,
				'export_limit' => $export_limit,
				'post_status'  => $query_post_status,
			];
			$batch_size    = $this->get_export_batch_size( $total_count, $post_type, $export_config );

			// Get posts for current batch
			$posts_query_args = [
				'post_type'      => $post_type,
				'post_status'    => $query_post_status,
				'posts_per_page' => min( $batch_size, $total_count - $start_row ),
				'offset'         => $start_row,
				'fields'         => 'ids',
			];

			/**
			 * Filter export query arguments for data retrieval
			 *
			 * Allows developers to customize the query used to retrieve
			 * the actual post data for CSV generation.
			 *
			 * @since 0.9.0
			 * @param array $query_args Export query arguments
			 * @param array $args Export arguments including context
			 * @return array Modified query arguments
			 */
			$posts_query_args = apply_filters( 'swift_csv_export_data_query_args', $posts_query_args, [ 'post_type' => $post_type ] );

			// Force our limit regardless of filter
			$posts_query_args['posts_per_page'] = min( $batch_size, $max_posts_to_process - $start_row );
			$posts_query_args['offset']         = $start_row;
			$posts_query_args['fields']         = 'ids';

			// Stop if we've reached the limit
			if ( $start_row >= $max_posts_to_process ) {
				wp_send_json(
					[
						'success'   => true,
						'processed' => $start_row,
						'total'     => $max_posts_to_process,
						'continue'  => false,
						'progress'  => 100,
						'status'    => 'completed',
						'csv_chunk' => '',
					]
				);
				return;
			}

			$posts = get_posts( $posts_query_args );

			// Additional safety check: ensure we don't exceed the limit
			$actual_batch_size = min( count( $posts ), $max_posts_to_process - $start_row );
			if ( $actual_batch_size < count( $posts ) ) {
				$posts = array_slice( $posts, 0, $actual_batch_size );
			}

			if ( empty( $posts ) ) {
				wp_send_json(
					[
						'success'   => true,
						'processed' => $start_row,
						'total'     => $max_posts_to_process,
						'continue'  => false,
						'progress'  => 100,
						'status'    => 'completed',
						'csv_chunk' => '',
					]
				);
				return;
			}

			// Simple CSV generation with headers
			$csv_chunk      = '';
			$headers        = $this->build_headers( $post_type, $export_scope, $include_private_meta, $query_post_status );
			$export_details = []; // Collect export details for logging

			// Add headers for first chunk
			if ( $start_row === 0 ) {
				$csv_chunk .= $this->fputcsv_row( $headers );
			}

			// Process each post
			foreach ( $posts as $post_id ) {
				if ( $this->is_cancelled( $export_session ) ) {
					wp_send_json_error( 'Export cancelled by user' );
					return;
				}

				// Get post data for logging
				$post       = get_post( $post_id );
				$post_title = $post ? $post->post_title : 'Untitled';

				// Get all meta data at once for performance and to preserve serialized values
				$all_meta = get_post_meta( $post_id );

				$row = [];

				// Pass taxonomy_format to inner scope
				$current_taxonomy_format = $taxonomy_format;

				foreach ( $headers as $header ) {
					if ( $this->is_connection_aborted() ) {
						wp_send_json_error( 'Export cancelled by user' );
						return;
					}

					$value = '';

					// Handle ID field
					if ( $header === 'ID' ) {
						$value = $post_id;

					} elseif ( $header === 'post_author' ) {
						$author = get_user_by( 'id', get_post_field( 'post_author', $post_id ) );
						$value  = $author ? $author->display_name : '';

					} elseif ( in_array( $header, $this->get_allowed_post_fields( 'basic' ), true ) ) {
						$value = get_post_field( $header, $post_id );

					} elseif ( str_starts_with( $header, 'tax_' ) ) {
						$taxonomy_name = substr( $header, 4 );
						$terms         = get_the_terms( $post_id, $taxonomy_name );
						if ( $terms && ! is_wp_error( $terms ) ) {
							$term_values = array_map(
								function ( $term ) use ( $current_taxonomy_format ) {
									$result = $current_taxonomy_format === 'id' ? $term->term_id : $term->name;
									return $result;
								},
								$terms
							);
							$value       = implode( '|', $term_values );
						}
						$clean_value = strip_tags( (string) $value );
						$clean_value = $this->normalize_quotes( $clean_value );
						$value       = $clean_value;

					} elseif ( str_starts_with( $header, 'cf_' ) ) {
						$meta_key = substr( $header, 3 );

						// Get meta values from bulk data to preserve serialized values
						$meta_value = '';
						if ( isset( $all_meta[ $meta_key ] ) ) {
							$meta_values = $all_meta[ $meta_key ];

							if ( is_array( $meta_values ) ) {
								if ( count( $meta_values ) > 1 ) {
									$meta_value = implode( '|', $meta_values );
								} else {
									$meta_value = $meta_values[0] ?? '';
								}
							} else {
								$meta_value = $meta_values; // String value
							}
						}

						$clean_value = strip_tags( (string) $meta_value );
						$clean_value = $this->normalize_quotes( $clean_value );
						$value       = $clean_value;

					} else {
						/**
						 * Process custom header values for export
						 *
						 * Allows developers to process custom headers that don't fit
						 * into standard categories (ID, post fields, taxonomies, custom fields).
						 * This hook is ideal for plugin-specific fields or special data processing.
						 *
						 * @since 0.9.0
						 * @param string $value Processed value (default: empty)
						 * @param string $header Header name
						 * @param int $post_id Post ID
						 * @param array $args Processing arguments
						 * @return string Processed value
						 */
						$custom_args = [
							'post_type' => $post_type,
							'context'   => 'export_data_processing',
						];

						$value = apply_filters( 'swift_csv_process_custom_header', '', $header, $post_id, $custom_args );

						// Fallback: simple field value if no hook implementation
						if ( '' === $value ) {
							$value = get_post_field( $header, $post_id ) ?? '';
						}
					}

					$row[] = $value;
				}

				// Add export detail for logging
				$export_details[] = [
					'row'     => $start_row + count( $export_details ) + 1,
					'action'  => 'export',
					'title'   => $post_title,
					'post_id' => $post_id,
					'status'  => 'success',
					'details' => sprintf(
						__( 'Export post: ID=%1$s, title=%2$s', 'swift-csv' ),
						$post_id,
						$post_title
					),
				];

				$csv_chunk .= $this->fputcsv_row( $row );
			}

			$next_row = $start_row + count( $posts );
			$continue = $next_row < $max_posts_to_process;
			$progress = round( ( $next_row / $max_posts_to_process ) * 100, 2 );

			if ( ! $continue ) {
				$cancel_option_name = $this->get_cancel_option_name( $export_session );
				delete_option( $cancel_option_name );
			}

			wp_send_json(
				[
					'success'         => true,
					'processed'       => $start_row + count( $posts ),
					'total'           => $max_posts_to_process,
					'continue'        => $continue,
					'progress'        => $progress,
					'status'          => $continue ? 'processing' : 'completed',
					'csv_chunk'       => $csv_chunk,
					'posts_processed' => count( $posts ),
					'export_details'  => $export_details,
				]
			);
		} catch ( Exception $e ) {
			wp_send_json_error( 'Export failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle Ajax export cancellation
	 *
	 * @since 0.9.0
	 * @return void Sends JSON response
	 */
	public function handle_ajax_export_cancel(): void {
		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		$export_session = sanitize_key( $_POST['export_session'] ?? '' );
		if ( '' === $export_session ) {
			wp_send_json_error( 'Missing export session' );
			return;
		}

		$cancel_option_name = $this->get_cancel_option_name( $export_session );
		update_option( $cancel_option_name, time(), false );

		wp_send_json_success( 'Export cancellation signal sent' );
	}
}
