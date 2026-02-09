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
 * Normalize CSV headers by removing empty values and duplicates
 *
 * @since 0.9.0
 * @param array $headers Array of CSV header strings
 * @return array Normalized headers array
 */
function swift_csv_ajax_export_normalize_headers( $headers ) {
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

/**
 * Get allowed post fields for CSV export based on scope
 *
 * @since 0.9.0
 * @param string $export_scope The export scope ('basic', 'all')
 * @return array Array of allowed post field names
 */
function swift_csv_get_allowed_post_fields( $export_scope = 'basic' ) {
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
 * @since 0.9.0
 * @param string $post_type The post type to export
 * @param string $export_scope The export scope ('basic', 'all', 'custom')
 * @param bool   $include_private_meta Whether to include private meta fields
 * @return array Array of CSV header strings
 */
function swift_csv_ajax_export_build_headers( $post_type, $export_scope = 'basic', $include_private_meta = false ) {
	$export_scope         = is_string( $export_scope ) ? $export_scope : 'basic';
	$include_private_meta = (bool) $include_private_meta;

	// Get allowed post fields using common function
	$headers = swift_csv_get_allowed_post_fields( $export_scope );

	$taxonomies       = get_object_taxonomies( $post_type, 'objects' );
	$taxonomy_headers = [];
	foreach ( $taxonomies as $taxonomy ) {
		if ( $taxonomy->public ) {
			$taxonomy_headers[] = 'tax_' . $taxonomy->name;
		}
	}

	// Hook for taxonomy-only customization
	/**
	 * Filter taxonomy headers for export
	 *
	 * Allows developers to customize taxonomy headers independently.
	 * This hook receives only taxonomy headers, making it easy to
	 * add, remove, or modify taxonomies without affecting post fields.
	 *
	 * @since 0.9.0
	 * @param array $taxonomy_headers Array of taxonomy headers (tax_ prefixed)
	 * @param array $taxonomies Array of taxonomy objects
	 * @param array $args Export arguments including context
	 * @return array Modified taxonomy headers array
	 */
	$taxonomy_args    = [
		'post_type'            => $post_type,
		'export_scope'         => $export_scope,
		'include_private_meta' => $include_private_meta,
		'context'              => 'taxonomy_headers',
	];
	$taxonomy_headers = apply_filters( 'swift_csv_filter_taxonomy_headers', $taxonomy_headers, $taxonomies, $taxonomy_args );

	// Hook for post fields customization (taxonomy-free)
	/**
	 * Filter post field headers for export
	 *
	 * Allows developers to customize post field headers independently.
	 * This hook receives only post field headers, making it easy to
	 * add, remove, or modify post fields without affecting taxonomies.
	 *
	 * @since 0.9.0
	 * @param array $headers Array of post field headers
	 * @param array $args Export arguments including context
	 * @return array Modified post field headers array
	 */
	$post_field_args = [
		'post_type'            => $post_type,
		'export_scope'         => $export_scope,
		'include_private_meta' => $include_private_meta,
		'context'              => 'post_fields',
	];
	$headers         = apply_filters( 'swift_csv_filter_post_field_headers', $headers, $post_field_args );

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

	// Hook for custom field headers (Pro version ACF integration)
	/**
	 * Filter custom field headers for export
	 *
	 * Allows extensions to completely customize custom field headers.
	 * This hook is ideal for Pro version ACF integration and other
	 * custom field processing systems.
	 *
	 * @since 0.9.0
	 * @param array $headers Current headers array
	 * @param array $meta_keys Discovered meta keys
	 * @param array $args Export arguments including context
	 * @return array Complete headers array
	 */
	$custom_field_args = [
		'post_type'            => $post_type,
		'export_scope'         => $export_scope,
		'include_private_meta' => $include_private_meta,
		'context'              => 'custom_fields',
		'discovered_meta_keys' => $all_meta_keys,
	];
	$headers           = apply_filters( 'swift_csv_filter_custom_field_headers', $headers, $all_meta_keys, $custom_field_args );

	// Free version default processing (only if hook didn't modify headers)
	$default_headers  = swift_csv_get_allowed_post_fields( 'basic' );
	$taxonomy_headers = array_filter(
		$headers,
		function ( $header ) {
			return str_starts_with( $header, 'tax_' );
		}
	);
	$expected_headers = array_merge( $default_headers, $taxonomy_headers );

	if ( $headers === $expected_headers ) {
		foreach ( $all_meta_keys as $meta_key ) {
			if ( ! is_string( $meta_key ) || $meta_key === '' ) {
				continue;
			}
			if ( ! $include_private_meta && str_starts_with( $meta_key, '_' ) ) {
				continue; // Skip private meta
			}
			$headers[] = 'cf_' . $meta_key;
		}
	}

	return swift_csv_ajax_export_normalize_headers( $headers );
}

/**
 * Generate a CSV row string from an array of values
 *
 * @since 0.9.0
 * @param array $row Array of values to convert to CSV
 * @return string CSV formatted string
 */
function swift_csv_ajax_export_fputcsv_row( array $row ) {
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
 * @param string $field The field value to normalize
 * @return string The normalized field value
 */
function swift_csv_ajax_normalize_quotes( $field ) {
	// Fix double escaped quotes that are already properly escaped
	$field = preg_replace( '/\\\\"\\\\"/', '""', $field );

	// Convert remaining escaped quotes to regular quotes
	$field = str_replace( '\\"', '"', $field );

	// Handle edge case of literal backslash before quote
	$field = preg_replace( '/\\\\\\\\(")/', '\\\\"$1', $field );

	return $field;
}

/**
 * Simple Ajax export handler
 */
function swift_csv_ajax_export_handler() {
	try {
		// Security check
		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		// Get parameters
		$post_type            = sanitize_text_field( $_POST['post_type'] ?? 'post' );
		$export_scope         = sanitize_text_field( $_POST['export_scope'] ?? 'basic' );
		$include_private_meta = ! empty( $_POST['include_private_meta'] ) && (string) $_POST['include_private_meta'] === '1';
		$export_limit         = ! empty( $_POST['export_limit'] ) ? intval( $_POST['export_limit'] ) : 0;
		$taxonomy_format      = sanitize_text_field( $_POST['taxonomy_format'] ?? 'name' );
		$start_row            = intval( $_POST['start_row'] ?? 0 );
		$batch_size           = 500; // Increase batch size for better performance

		// Log export start for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[Swift CSV] Export started: post_type={$post_type}, scope={$export_scope}, limit={$export_limit}, start_row={$start_row}, taxonomy_format={$taxonomy_format}" );
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
		 * Filter export query arguments for full export
		 *
		 * Allows developers to customize the main export query used to retrieve
		 * all posts for CSV generation. This affects the actual export content.
		 *
		 * @since 0.9.0
		 * @param array $query_args Export query arguments
		 * @param array $args Export arguments including context
		 * @return array Modified query arguments
		 */
		$export_query_args = [
			'post_type'    => $post_type,
			'context'      => 'full_export',
			'export_limit' => $export_limit,
		];
		$filtered_args     = apply_filters( 'swift_csv_export_query_args', $total_posts_query_args, $export_query_args );

		// Preserve export limit regardless of filter modifications
		$filtered_args['posts_per_page'] = $export_limit > 0 ? $export_limit : -1;

		$total_posts_query_args = $filtered_args;
		$total_posts            = get_posts( $total_posts_query_args );
		$total_count            = count( $total_posts );

		// Define max_posts_to_process
		$max_posts_to_process = $export_limit > 0 ? $export_limit : $total_count;

		// Log data retrieval info for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[Swift CSV] Data retrieved: total_count={$total_count}, max_posts={$max_posts_to_process}" );
		}

		// Get posts for current batch
		$posts_query_args = [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => min( $batch_size, $total_count - $start_row ),
			'offset'         => $start_row,
			'fields'         => 'ids',
		];
		$posts_query_args = apply_filters( 'swift_csv_export_query_args', $posts_query_args, [ 'post_type' => $post_type ] );

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
		$headers   = swift_csv_ajax_export_build_headers( $post_type, $export_scope, $include_private_meta );

		// Add headers for first chunk
		if ( $start_row === 0 ) {
			$csv_chunk .= swift_csv_ajax_export_fputcsv_row( $headers );
		}

		// Process each post
		foreach ( $posts as $post_id ) {
			$row = [];

			// Pass taxonomy_format to inner scope
			$current_taxonomy_format = $taxonomy_format;

			foreach ( $headers as $header ) {
				$value = '';

				// Handle ID field
				if ( $header === 'ID' ) {
					$value = $post_id;
				} elseif ( $header === 'post_author' ) {
					$author = get_user_by( 'id', get_post_field( 'post_author', $post_id ) );
					$value  = $author ? $author->display_name : '';
				} elseif ( in_array( $header, swift_csv_get_allowed_post_fields( 'basic' ), true ) ) {
					$value = get_post_field( $header, $post_id );
				} elseif ( str_starts_with( $header, 'cf_' ) ) {
					$meta_key    = substr( $header, 3 );
					$meta_values = get_post_meta( $post_id, $meta_key );
					if ( $meta_values && ! is_wp_error( $meta_values ) ) {
						$meta_value = $meta_values[0] ?? '';
						if ( is_array( $meta_value ) ) {
							$meta_value = implode( '|', $meta_value );
						}
					}
					$clean_value = strip_tags( (string) $meta_value );
					$clean_value = swift_csv_ajax_normalize_quotes( $clean_value );
					$value       = $clean_value;
				} elseif ( str_starts_with( $header, 'tax_' ) ) {
					$taxonomy_name = substr( $header, 4 );
					$terms         = get_the_terms( $post_id, $taxonomy_name );
					if ( $terms && ! is_wp_error( $terms ) ) {
						// Debug log for taxonomy processing
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "[Swift CSV] Processing taxonomy: {$taxonomy_name}, format: {$taxonomy_format}" );
						}

						$term_values = array_map(
							function ( $term ) use ( $current_taxonomy_format ) {
								$result = $current_taxonomy_format === 'id' ? $term->term_id : $term->name;
								if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
									error_log( "[Swift CSV] Term: {$term->name} (ID: {$term->term_id}) -> Result: {$result} (format: {$current_taxonomy_format})" );
								}
								return $result;
							},
							$terms
						);
						$value       = implode( '|', $term_values );

						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "[Swift CSV] Final taxonomy value: {$value}" );
						}
					}
					$clean_value = strip_tags( (string) $value );
					$clean_value = swift_csv_ajax_normalize_quotes( $clean_value );
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
							$clean_value = swift_csv_ajax_normalize_quotes( $clean_value );
							$value       = $clean_value;
						}
					}
				}

				$row[] = $value;
			}

			$csv_chunk .= swift_csv_ajax_export_fputcsv_row( $row );
		}

		$next_row = $start_row + count( $posts );
		$continue = $next_row < $max_posts_to_process;
		$progress = round( ( $next_row / $max_posts_to_process ) * 100, 2 );

		// Log batch completion for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Swift CSV] Export batch completed: posts_processed=' . count( $posts ) . ", next_row={$next_row}/{$max_posts_to_process}, progress={$progress}%" );
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

add_action( 'wp_ajax_swift_csv_ajax_export', 'swift_csv_ajax_export_handler' );
