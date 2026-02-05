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
	$export_scope = is_string( $export_scope ) ? $export_scope : 'basic';
	$include_private_meta = (bool) $include_private_meta;

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

	if ( function_exists( 'acf_get_field_groups' ) ) {
		$field_groups = acf_get_field_groups();
		foreach ( $field_groups as $group ) {
			$show_for_post_type = false;
			if ( isset( $group['location'] ) ) {
				foreach ( $group['location'] as $location_group ) {
					foreach ( $location_group as $rule ) {
						if ( $rule['param'] === 'post_type' && $rule['operator'] === '==' && $rule['value'] === $post_type ) {
							$show_for_post_type = true;
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
						}
					}
				}
			}
		}
	}

	$default_headers  = $headers;
	$filtered_headers = apply_filters( 'swift_csv_export_headers', $headers, [ 'post_type' => $post_type ] );
	if ( $filtered_headers !== $default_headers ) {
		return swift_csv_ajax_export_normalize_headers( $filtered_headers );
	}

	$acf_field_names = [];
	foreach ( $headers as $header ) {
		if ( str_starts_with( $header, 'acf_' ) ) {
			$acf_field_names[ substr( $header, 4 ) ] = true;
		}
	}

	$sample_query_args = [
		'post_type' => $post_type,
		'post_status' => 'publish',
		'posts_per_page' => 1,
		'orderby' => 'ID',
		'order' => 'DESC',
		'fields' => 'ids',
	];
	$sample_query_args = apply_filters( 'swift_csv_export_query_args', $sample_query_args, [ 'post_type' => $post_type ] );
	$sample_query_args['posts_per_page'] = 1;
	$sample_query_args['offset'] = 0;
	$sample_query_args['fields'] = 'ids';
	$sample_post_ids = get_posts( $sample_query_args );

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
	return str_replace( ',', ';', $value );
}

/**
 * Simple Ajax export handler
 */
function swift_csv_ajax_export_handler() {
	// Security check
	check_ajax_referer( 'swift_csv_nonce', 'nonce' );
	
	// Get parameters
	$post_type = sanitize_text_field( $_POST['post_type'] ?? 'post' );
	$export_scope = sanitize_text_field( $_POST['export_scope'] ?? 'basic' );
	$include_private_meta = ! empty( $_POST['include_private_meta'] ) && (string) $_POST['include_private_meta'] === '1';
	$start_row = intval( $_POST['start_row'] ?? 0 );
	$batch_size = 10; // Small batch size for testing
	
	error_log('[Swift CSV Simple Ajax Export] Starting: post_type=' . $post_type . ', start_row=' . $start_row);
	
	// Validate post type
	if ( ! post_type_exists( $post_type ) ) {
		wp_send_json_error( 'Invalid post type' );
	}
	
	// Get total posts count with limit
	$total_posts_query_args = [
		'post_type' => $post_type,
		'post_status' => 'publish',
		'posts_per_page' => 100, // Limit to 100 posts for testing
		'fields' => 'ids',
	];
	$total_posts_query_args = apply_filters( 'swift_csv_export_query_args', $total_posts_query_args, [ 'post_type' => $post_type ] );
	$total_posts_query_args['posts_per_page'] = 100;
	$total_posts_query_args['fields'] = 'ids';
	$total_posts = get_posts( $total_posts_query_args );
	$total_count = count($total_posts);
	
	// For testing, limit to 100 posts max
	$max_posts = 100;
	$posts_query_args = [
		'post_type' => $post_type,
		'post_status' => 'publish',
		'posts_per_page' => min($batch_size, $max_posts - $start_row),
		'offset' => $start_row,
		'fields' => 'ids',
	];
	$posts_query_args = apply_filters( 'swift_csv_export_query_args', $posts_query_args, [ 'post_type' => $post_type ] );
	$posts_query_args['posts_per_page'] = min($batch_size, $max_posts - $start_row);
	$posts_query_args['offset'] = $start_row;
	$posts_query_args['fields'] = 'ids';
	$posts = get_posts( $posts_query_args );
	
	if ( empty( $posts ) ) {
		wp_send_json([
			'success' => true,
			'processed' => $start_row,
			'total' => $max_posts,
			'continue' => false,
			'progress' => 100,
			'csv_chunk' => '',
			'posts_processed' => 0
		]);
	}
	
	// Simple CSV generation with headers
	$csv_chunk = '';
	$headers = swift_csv_ajax_export_build_headers( $post_type, $export_scope, $include_private_meta );
	
	// Add headers for first chunk
	if ($start_row === 0) {
		$csv_chunk .= swift_csv_ajax_export_fputcsv_row( $headers );
	}
	
	foreach ( $posts as $post_id ) {
		$post = get_post( $post_id );
		
		// Build data row based on headers
		$row_data = [];
		foreach ( $headers as $header ) {
			$value = '';

			$post_field_value = swift_csv_ajax_export_resolve_post_field_value( $post, $header );
			if ( $post_field_value !== null ) {
				$row_data[] = $post_field_value;
				continue;
			}

			if ( str_starts_with( $header, 'cf_' ) ) {
				$field_name = substr( $header, 3 );
				$meta_value = get_post_meta( $post_id, $field_name, true );
				if ( is_array( $meta_value ) ) {
					$meta_value = implode( '|', $meta_value );
				}
				$value = str_replace( ',', ';', (string) $meta_value );
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
					$value = implode( '|', $term_names );
				}
				$value = str_replace( ',', ';', (string) $value );
			} elseif ( str_starts_with( $header, 'acf_' ) ) {
				$field_name = substr( $header, 4 );
				if ( function_exists( 'get_field_object' ) ) {
					$field_object = get_field_object( $field_name, $post_id );
					if ( $field_object ) {
						$acf_value = $field_object['value'];
						if ( $field_object['type'] === 'taxonomy' ) {
							$term_names = [];
							if ( is_array( $acf_value ) ) {
								foreach ( $acf_value as $term ) {
									if ( is_object( $term ) && isset( $term->name ) ) {
										$term_names[] = $term->name;
									} elseif ( is_numeric( $term ) ) {
										$term_obj = get_term( (int) $term );
										if ( $term_obj && ! is_wp_error( $term_obj ) ) {
											$term_names[] = $term_obj->name;
										}
									}
								}
							} elseif ( is_object( $acf_value ) && isset( $acf_value->name ) ) {
								$term_names[] = $acf_value->name;
							} elseif ( is_string( $acf_value ) && $acf_value !== '' ) {
								$term_names[] = $acf_value;
							}
							$value = implode( '|', $term_names );
						} elseif ( is_array( $acf_value ) ) {
							$value = implode( '|', $acf_value );
						} elseif ( is_object( $acf_value ) ) {
							$value = serialize( $acf_value );
						} else {
							$value = (string) $acf_value;
						}
						$value = str_replace( ',', ';', (string) $value );
					}
				} else {
					$value = '';
				}
			} else {
				$value = '';
			}
			$row_data[] = $value;
		}
		$csv_chunk .= swift_csv_ajax_export_fputcsv_row( $row_data );
	}
	
	$next_row = $start_row + count( $posts );
	$total_posts = $max_posts; // Use our limit
	$continue = $next_row < $total_posts;
	$progress = round( ($next_row / $total_posts) * 100, 2 );
	
	wp_send_json([
		'success' => true,
		'processed' => $next_row,
		'total' => $total_posts,
		'continue' => $continue,
		'progress' => $progress,
		'csv_chunk' => $csv_chunk,
		'posts_processed' => count( $posts )
	]);
}

add_action( 'wp_ajax_swift_csv_ajax_export', 'swift_csv_ajax_export_handler' );
