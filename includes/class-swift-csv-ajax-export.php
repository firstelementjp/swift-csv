<?php
/**
 * Simple Ajax Export Handler for Swift CSV Plugin
 *
 * @since  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

function swift_csv_ajax_export_build_headers( $post_type, $export_scope = 'basic', $include_private_meta = false ) {
	$export_scope         = is_string( $export_scope ) ? $export_scope : 'basic';
	$include_private_meta = (bool) $include_private_meta;

	$headers = array( 'ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name', 'post_date', 'post_author' );
	if ( $export_scope === 'all' ) {
		$allowed_post_fields = array(
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
		);
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

	if ( function_exists( 'acf_get_field_groups' ) ) {
		$field_groups = acf_get_field_groups();
		error_log( 'ACF field groups found: ' . print_r( $field_groups, true ) );
		foreach ( $field_groups as $group ) {
			$show_for_post_type = false;
			if ( isset( $group['location'] ) ) {
				foreach ( $group['location'] as $location_group ) {
					foreach ( $location_group as $rule ) {
						if ( $rule['param'] === 'post_type' && $rule['operator'] === '==' && $rule['value'] === $post_type ) {
							$show_for_post_type = true;
							error_log( 'ACF group matches post type: ' . $post_type );
							break 2;
						}
					}
				}
			}

			if ( $show_for_post_type ) {
				$fields = acf_get_fields( $group );
				if ( $fields ) {
					foreach ( $fields as $field ) {
						if ( isset( $field['name'] ) && $field['name'] !== '' ) {
							$headers[] = 'acf_' . $field['name'];
							error_log( 'ACF field added to headers: acf_' . $field['name'] );
						}
					}
				}
			}
		}
	} else {
		error_log( 'ACF functions not available' );
	}

	$default_headers  = $headers;
	$filtered_headers = apply_filters( 'swift_csv_export_headers', $headers, array( 'post_type' => $post_type ) );
	if ( $filtered_headers !== $default_headers ) {
		return swift_csv_ajax_export_normalize_headers( $filtered_headers );
	}

	$acf_field_names = array();
	foreach ( $headers as $header ) {
		if ( str_starts_with( $header, 'acf_' ) ) {
			$acf_field_names[ substr( $header, 4 ) ] = true;
		}
	}

	$sample_query_args                   = array(
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'orderby'        => 'ID',
		'order'          => 'DESC',
		'fields'         => 'ids',
	);
	$sample_query_args                   = apply_filters( 'swift_csv_export_query_args', $sample_query_args, array( 'post_type' => $post_type ) );
	$sample_query_args['posts_per_page'] = 1;
	$sample_query_args['offset']         = 0;
	$sample_query_args['fields']         = 'ids';
	$sample_post_ids                     = get_posts( $sample_query_args );

	if ( ! empty( $sample_post_ids ) ) {
		$sample_post_id = (int) $sample_post_ids[0];
		$all_meta       = get_post_meta( $sample_post_id );
		foreach ( array_keys( (array) $all_meta ) as $meta_key ) {
			if ( ! is_string( $meta_key ) || $meta_key === '' ) {
				continue;
			}
			if ( ! $include_private_meta && str_starts_with( $meta_key, '_' ) ) {
				continue;
			}
			if ( isset( $acf_field_names[ $meta_key ] ) ) {
				continue;
			}
			$headers[] = 'cf_' . $meta_key;
		}
	}

	return swift_csv_ajax_export_normalize_headers( $headers );
}

function swift_csv_ajax_export_fputcsv_row( array $row ) {
	$fh = fopen( 'php://temp', 'r+' );
	fputcsv( $fh, $row );
	rewind( $fh );
	$csv = stream_get_contents( $fh );
	fclose( $fh );
	return $csv;
}

function swift_csv_ajax_export_resolve_post_field_value( WP_Post $post, $header ) {
	if ( $header === 'ID' ) {
		return (string) $post->ID;
	}

	if ( ! str_starts_with( $header, 'post_' ) || ! isset( $post->{$header} ) ) {
		return null;
	}

	$allowed_post_fields = array(
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
	);
	if ( ! in_array( $header, $allowed_post_fields, true ) ) {
		return null;
	}

	$value = (string) $post->{$header};
	return str_replace( ',', ';', $value );
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
	// fputcsv will handle proper CSV escaping
	$field = str_replace( '\\"', '"', $field );

	// Handle edge case of literal backslash before quote
	// Preserve intentional \" patterns
	$field = preg_replace( '/\\\\\\\\(")/', '\\\\"$1', $field );

	return $field;
}

/**
 * Simple Ajax export handler
 */
function swift_csv_ajax_export_handler() {
	// Security check
	check_ajax_referer( 'swift_csv_nonce', 'nonce' );

	// Get parameters
	$post_type            = sanitize_text_field( $_POST['post_type'] ?? 'post' );
	$export_scope         = sanitize_text_field( $_POST['export_scope'] ?? 'basic' );
	$include_private_meta = ! empty( $_POST['include_private_meta'] ) && (string) $_POST['include_private_meta'] === '1';
	$export_limit         = ! empty( $_POST['export_limit'] ) ? intval( $_POST['export_limit'] ) : 0;
	$start_row            = intval( $_POST['start_row'] ?? 0 );
	$batch_size           = 200; // Increase batch size for better performance

	// Validate post type
	if ( ! post_type_exists( $post_type ) ) {
		wp_send_json_error( 'Invalid post type' );
	}

	// Get total posts count with limit
	$total_posts_query_args = array(
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => $export_limit > 0 ? $export_limit : -1, // Use limit or all posts
		'fields'         => 'ids',
	);
	// Apply filter but check if it modifies our limit
	$filtered_args = apply_filters( 'swift_csv_export_query_args', $total_posts_query_args, array( 'post_type' => $post_type ) );

	// Force our limit regardless of filter
	$filtered_args['posts_per_page'] = $export_limit > 0 ? $export_limit : -1;

	$total_posts_query_args = $filtered_args;
	$total_posts            = get_posts( $total_posts_query_args );
	$total_count            = count( $total_posts );

	// Define max_posts_to_process
	$max_posts_to_process = $export_limit > 0 ? $export_limit : $total_count;

	// Get posts for current batch
	$posts_query_args = array(
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => min( $batch_size, $total_count - $start_row ),
		'offset'         => $start_row,
		'fields'         => 'ids',
	);
	$posts_query_args = apply_filters( 'swift_csv_export_query_args', $posts_query_args, array( 'post_type' => $post_type ) );

	// Force our limit regardless of filter
	$posts_query_args['posts_per_page'] = min( $batch_size, $max_posts_to_process - $start_row );
	$posts_query_args['offset']         = $start_row;
	$posts_query_args['fields']         = 'ids';

	// Stop if we've reached the limit
	if ( $start_row >= $max_posts_to_process ) {
		wp_send_json(
			array(
				'success'   => true,
				'processed' => $start_row,
				'total'     => $max_posts_to_process,
				'continue'  => false,
				'csv_chunk' => '',
			)
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
			array(
				'success'   => true,
				'processed' => $start_row,
				'total'     => $max_posts_to_process,
				'continue'  => false,
				'csv_chunk' => '',
			)
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

	// Cache ACF field objects to reduce repeated calls
	static $acf_field_cache = array();

	foreach ( $posts as $post_id ) {
		$post = get_post( $post_id );

		// Batch get all meta data for this post to reduce queries
		$all_meta = get_post_meta( $post_id );

		// Build data row based on headers
		$row_data = array();
		foreach ( $headers as $header ) {
			$value = '';

			$post_field_value = swift_csv_ajax_export_resolve_post_field_value( $post, $header );
			if ( $post_field_value !== null ) {
				// Clean up HTML tags and normalize quotes, but keep newlines for readability
				$clean_value = strip_tags( $post_field_value );
				$clean_value = swift_csv_ajax_normalize_quotes( $clean_value );
				$row_data[]  = $clean_value;
				continue;
			}

			if ( str_starts_with( $header, 'cf_' ) ) {
				$field_name = substr( $header, 3 );
				$meta_value = $all_meta[ $field_name ][0] ?? '';
				if ( is_array( $meta_value ) ) {
					$meta_value = implode( '|', $meta_value );
				}
				$clean_value = strip_tags( (string) $meta_value );
				$clean_value = swift_csv_ajax_normalize_quotes( $clean_value );
				$value       = str_replace( ',', ';', $clean_value );
			} elseif ( str_starts_with( $header, 'tax_' ) ) {
				$taxonomy_name = substr( $header, 4 );
				$terms         = get_the_terms( $post_id, $taxonomy_name );
				if ( $terms && ! is_wp_error( $terms ) ) {
					$term_names = array_map(
						function ( $term ) {
							return $term->name;
						},
						$terms
					);
					$value      = implode( '|', $term_names );
				}
				$clean_value = strip_tags( (string) $value );
				$clean_value = swift_csv_ajax_normalize_quotes( $clean_value );
				$value       = str_replace( ',', ';', $clean_value );
			} elseif ( str_starts_with( $header, 'acf_' ) ) {
				$field_name = substr( $header, 4 );
				error_log( 'Processing ACF field: ' . $field_name . ' for post: ' . $post_id );

				if ( function_exists( 'get_field_object' ) ) {
					// Use cache to avoid repeated get_field_object calls
					if ( ! isset( $acf_field_cache[ $field_name ] ) ) {
						// Try to get field object with post context
						$acf_field_cache[ $field_name ] = get_field_object( $field_name, $post_id, false, false );
						error_log( 'Field object for ' . $field_name . ': ' . print_r( $acf_field_cache[ $field_name ], true ) );
					}
					$field_object = $acf_field_cache[ $field_name ];

					if ( $field_object ) {
						$acf_value = get_field( $field_name, $post_id );
						error_log( 'ACF value for ' . $field_name . ': ' . print_r( $acf_value, true ) );

						if ( $field_object['type'] === 'taxonomy' ) {
							$term_names = array();
							if ( is_array( $acf_value ) ) {
								foreach ( $acf_value as $term ) {
									if ( is_object( $term ) && isset( $term->name ) ) {
										$term_names[] = $term->name;
									} elseif ( is_numeric( $term ) ) {
										$term_obj = get_term( (int) $term );
										if ( $term_obj && ! is_wp_error( $term_obj ) ) {
											$term_names[] = $term_obj->name;
										}
									} elseif ( is_string( $term ) && $term !== '' ) {
										$term_names[] = $term;
									}
								}
								$value = implode( '|', $term_names );
							} else {
								if ( is_object( $acf_value ) && isset( $acf_value->name ) ) {
									$term_names[] = $acf_value->name;
								} elseif ( is_string( $acf_value ) && $acf_value !== '' ) {
									$term_names[] = $acf_value;
								}
								$value = implode( '|', $term_names );
							}
						} else {
							if ( is_array( $acf_value ) ) {
								$acf_value = implode( '|', $acf_value );
							}
							$clean_value = strip_tags( (string) $acf_value );
							$clean_value = swift_csv_ajax_normalize_quotes( $clean_value );
							$value       = str_replace( ',', ';', $clean_value );
							error_log( 'ACF final value for ' . $field_name . ': "' . $value . '"' );
						}
					} else {
						$value = '';
						error_log( 'ACF field object not found for: ' . $field_name );

						// Fallback: try to get value directly
						$fallback_value = get_field( $field_name, $post_id );
						if ( $fallback_value !== null && $fallback_value !== false ) {
							if ( is_array( $fallback_value ) ) {
								$fallback_value = implode( '|', $fallback_value );
							}
							$clean_value = strip_tags( (string) $fallback_value );
							$clean_value = swift_csv_ajax_normalize_quotes( $clean_value );
							$value       = str_replace( ',', ';', $clean_value );
							error_log( 'ACF fallback value for ' . $field_name . ': "' . $value . '"' );
						}
					}
				} else {
					$value = '';
					error_log( 'get_field_object function not available for: ' . $field_name );
				}
			} else {
				$value = '';
			}
			error_log( 'Adding to CSV row - ' . $header . ': "' . $value . '"' );
			$row_data[] = $value;
		}
		$csv_chunk .= swift_csv_ajax_export_fputcsv_row( $row_data );
	}

	$next_row = $start_row + count( $posts );
	$continue = $next_row < $max_posts_to_process;
	$progress = round( ( $next_row / $max_posts_to_process ) * 100, 2 );

	wp_send_json(
		array(
			'success'         => true,
			'processed'       => $start_row + count( $posts ),
			'total'           => $max_posts_to_process,
			'continue'        => $continue,
			'progress'        => $progress,
			'csv_chunk'       => $csv_chunk,
			'posts_processed' => count( $posts ),
		)
	);
}

add_action( 'wp_ajax_swift_csv_ajax_export', 'swift_csv_ajax_export_handler' );
