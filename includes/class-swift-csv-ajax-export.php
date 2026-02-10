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
	 * Constructor
	 *
	 * @since 0.9.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_swift_csv_ajax_export', [ $this, 'handle_ajax_export' ] );
		add_action( 'wp_ajax_swift_csv_cancel_export', [ $this, 'handle_ajax_export_cancel' ] );
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
	 * @return string[] Array of CSV header strings
	 */
	private function build_headers( string $post_type, string $export_scope = 'basic', bool $include_private_meta = false ): array {
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
			'post_status'    => 'publish',
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

		// Hook for meta key classification (Pro version ACF integration)
		/**
		 * Filter and classify discovered meta keys
		 *
		 * Allows developers to classify meta keys into different categories
		 * (ACF, regular, private) for specialized processing. This hook is
		 * ideal for Pro versions with ACF integration.
		 *
		 * @since 0.9.0
		 * @param array $all_meta_keys All discovered meta keys
		 * @param array $args Export arguments including context
		 * @return array Classified meta keys array with 'acf', 'regular', 'private' keys
		 */
		$meta_classify_args = [
			'post_type'            => $post_type,
			'export_scope'         => $export_scope,
			'include_private_meta' => $include_private_meta,
			'context'              => 'meta_key_classification',
		];

		$classified_meta_keys = apply_filters( 'swift_csv_classify_meta_keys', $all_meta_keys, $meta_classify_args );

		// Ensure classified structure exists
		if ( ! is_array( $classified_meta_keys ) || ! isset( $classified_meta_keys['acf'] ) ) {
			// Fallback: create basic classification
			$classified_meta_keys = [
				'acf'     => [],
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

		// Hook for custom field headers generation (Pro version ACF integration)
		/**
		 * Generate custom field headers for export
		 *
		 * Allows extensions to generate custom field headers from classified meta keys.
		 * This hook is ideal for Pro versions with ACF integration that need to create
		 * headers with different prefixes (acf_, cf_) based on field type.
		 *
		 * @since 0.9.0
		 * @param array $custom_field_headers Array of custom field headers (empty array to start)
		 * @param array $classified_meta_keys Classified meta keys with 'acf', 'regular', 'private' keys
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
			$total_posts_query_args = [
				'post_type'      => $post_type,
				'post_status'    => 'publish',
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

			// Get posts for current batch
			$posts_query_args = [
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => min( self::BATCH_SIZE, $total_count - $start_row ),
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
			$posts_query_args['posts_per_page'] = min( self::BATCH_SIZE, $max_posts_to_process - $start_row );
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
						'csv_chunk' => '',
					]
				);
				return;
			}

			// Simple CSV generation with headers
			$csv_chunk = '';
			$headers   = $this->build_headers( $post_type, $export_scope, $include_private_meta );

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
					} elseif ( str_starts_with( $header, 'acf_' ) ) {
						// Handle ACF fields - delegate to Pro version processing
						$meta_key = substr( $header, 4 );

						/**
						 * Process ACF field value for export
						 *
						 * Allows Pro version to handle ACF field processing with proper
						 * formatting, taxonomy term resolution, and field type handling.
						 *
						 * @since 0.9.0
						 * @param string $value Processed field value (default: empty)
						 * @param string $meta_key Original ACF field name
						 * @param int $post_id Post ID
						 * @param array $args Processing arguments
						 * @return string Processed ACF field value
						 */
						$acf_args = [
							'post_type' => $post_type,
							'context'   => 'export_data_processing',
						];
						$value    = apply_filters( 'swift_csv_process_acf_field_value', '', $meta_key, $post_id, $acf_args );

					} elseif ( str_starts_with( $header, 'cf_' ) ) {
						$meta_key    = substr( $header, 3 );
						$meta_values = get_post_meta( $post_id, $meta_key );
						if ( $meta_values && ! is_wp_error( $meta_values ) ) {
							// Handle multiple values - join with pipe separator
							if ( count( $meta_values ) > 1 ) {
								$meta_value = implode( '|', $meta_values );
							} else {
								$meta_value = $meta_values[0] ?? '';
							}
						}
						$clean_value = strip_tags( (string) $meta_value );
						$clean_value = $this->normalize_quotes( $clean_value );
						$value       = $clean_value;
					} else {
						// Try to get as post field first
						$post_field_value = get_post_field( $header, $post_id );
						if ( $post_field_value !== '' && $post_field_value !== null ) {
							$value = $post_field_value;
						} else {
							// Try as meta field
							$meta_values = get_post_meta( $post_id, $header );
							if ( $meta_values && ! is_wp_error( $meta_values ) ) {
								$meta_value = $meta_values[0] ?? '';
								if ( is_array( $meta_value ) ) {
									$meta_value = implode( '|', $meta_value );
								}
								$clean_value = strip_tags( (string) $meta_value );
								$clean_value = $this->normalize_quotes( $clean_value );
								$value       = $clean_value;
							}
						}
					}

					$row[] = $value;
				}

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
					'csv_chunk'       => $csv_chunk,
					'posts_processed' => count( $posts ),
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
