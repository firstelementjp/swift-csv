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

	// Custom export scope - use hook with fallback to basic
	if ( $export_scope === 'custom' ) {
		/**
		 * Filter custom export columns for custom export scope
		 *
		 * Allows developers to define custom column headers for export.
		 * This hook is only called when export_scope is set to 'custom'.
		 *
		 * @since 0.9.0
		 * @param array $custom_headers Array of custom header strings
		 * @param string $post_type The post type being exported
		 * @param bool $include_private_meta Whether to include private meta fields
		 * @return array Array of header strings to use for export
		 */
		$custom_headers = apply_filters( 'swift_csv_export_columns', [], $post_type, $include_private_meta );

		// If custom hook returns valid headers, use them
		if ( is_array( $custom_headers ) && ! empty( $custom_headers ) ) {
			return $custom_headers;
		}

		// Fallback to basic if no custom implementation
		$export_scope = 'basic';
	}

	$headers = [ 'ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name', 'post_date', 'post_author' ];
	if ( $export_scope === 'all' ) {
		$allowed_post_fields = [
			'post_title',
			'post_content',
			'post_excerpt',
			'post_status',
			'post_name',
			'post_date',
			'post_date_gmt',
			'post_modified',
			'post_modified_gmt',
			'post_author',
			'post_parent',
			'menu_order',
			'guid',
			'comment_status',
			'ping_status',
			'post_type',
		];
		foreach ( $allowed_post_fields as $field ) {
			$headers[] = $field;
		}
	}

	$taxonomies = get_object_taxonomies( $post_type, 'objects' );
	foreach ( $taxonomies as $taxonomy ) {
		if ( $taxonomy->public ) {
			$headers[] = 'tax_' . $taxonomy->name;
		}
	}

	$default_headers = $headers;
	/**
	 * Filter export headers
	 *
	 * Allows developers to modify the CSV headers before export.
	 * Can be used to add, remove, or reorder columns.
	 *
	 * @since 0.9.0
	 * @param array $headers Array of header strings
	 * @param array $args Export arguments including post_type
	 * @return array Modified headers array
	 */
	$filtered_headers = apply_filters( 'swift_csv_export_headers', $headers, [ 'post_type' => $post_type ] );
	if ( $filtered_headers !== $default_headers ) {
		return swift_csv_ajax_export_normalize_headers( $filtered_headers );
	}

	// Additional headers hook for extensions
	/**
	 * Filter additional headers for export
	 *
	 * Allows developers to add custom headers to the export.
	 * This hook can be used by any extension to add specialized fields.
	 *
	 * @since 0.9.0
	 * @param array $headers Current headers array
	 * @param string $post_type The post type being exported
	 * @param array $args Additional arguments
	 * @return array Modified headers with additional fields
	 */
	$headers = apply_filters( 'swift_csv_add_additional_headers', $headers, $post_type, [] );

	$sample_query_args = [
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => 1, // Check only the first post for header structure
		'orderby'        => 'post_date', // Match WordPress admin default
		'order'          => 'DESC', // Get newest post by date
		'fields'         => 'ids',
	];
	/**
	 * Filter export query arguments
	 *
	 * Allows developers to modify the WP_Query arguments used for export.
	 * Can be used to filter posts by taxonomy, meta values, etc.
	 *
	 * @since 0.9.0
	 * @param array $query_args WP_Query arguments
	 * @param array $args Export arguments including post_type
	 * @return array Modified query arguments
	 */
	$sample_query_args                   = apply_filters( 'swift_csv_export_query_args', $sample_query_args, [ 'post_type' => $post_type ] );
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

	// Check if Pro version is available and delegate processing
	if ( class_exists( 'Swift_CSV_Pro_ACF_Integration' ) ) {
		/**
		 * Filter all headers for export
		 *
		 * Allows extensions to completely override header generation.
		 * This hook can be used by Pro version or other extensions.
		 *
		 * @since 0.9.0
		 * @param array $headers Current headers array
		 * @param string $post_type The post type being exported
		 * @param bool $include_private_meta Whether to include private meta fields
		 * @return array Complete headers array
		 */
		$headers = apply_filters( 'swift_csv_generate_all_headers', $headers, $post_type, $include_private_meta );
	} else {
		// Free version default processing
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
 * Resolve post field value for CSV export
 *
 * @since 0.9.0
 * @param WP_Post $post The post object
 * @param string  $header The field name to resolve
 * @return string The resolved field value
 */
function swift_csv_ajax_export_resolve_post_field_value( WP_Post $post, $header ) {
	if ( $header === 'ID' ) {
		return (string) $post->ID;
	}

	if ( ! str_starts_with( $header, 'post_' ) || ! isset( $post->{$header} ) ) {
		return null;
	}

	$allowed_post_fields = [
		'post_title',
		'post_content',
		'post_excerpt',
		'post_status',
		'post_name',
		'post_date',
		'post_date_gmt',
		'post_modified',
		'post_modified_gmt',
		'post_author',
		'post_parent',
		'menu_order',
		'guid',
		'comment_status',
		'ping_status',
		'post_type',
	];
	if ( ! in_array( $header, $allowed_post_fields, true ) ) {
		return null;
	}

	$value = (string) $post->{$header};
	return $value;
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
			'posts_per_page' => $export_limit > 0 ? $export_limit : -1, // Use limit or all posts
			'fields'         => 'ids',
		];
		// Apply filter but check if it modifies our limit
		$filtered_args = apply_filters( 'swift_csv_export_query_args', $total_posts_query_args, [ 'post_type' => $post_type ] );

		// Force our limit regardless of filter
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
				} elseif ( in_array( $header, [ 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name', 'post_date', 'post_modified', 'post_parent', 'menu_order', 'comment_status', 'ping_status' ], true ) ) {
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
