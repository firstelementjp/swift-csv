<?php

/**
 * Ajax Export Handler for Swift CSV Plugin
 *
 * Handles chunked CSV export via Ajax requests with real-time progress.
 *
 * @since  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax export handler
 *
 * Processes CSV export in chunks with real-time progress reporting.
 *
 * @since  1.0.0
 * @return void
 */
function swift_csv_ajax_export_handler() {
	global $wpdb;
	
	// Security check
	check_ajax_referer( 'swift_csv_nonce', 'nonce' );
	
	// Get parameters
	$start_row = intval( $_POST['start_row'] ?? 0 );
	$batch_size = 20; // Same as import for consistency
	$post_type = sanitize_text_field( $_POST['post_type'] ?? 'post' );
	$include_headers = isset( $_POST['include_headers'] ) && $_POST['include_headers'] === 'true';
	
	error_log('[Swift CSV Ajax Export] Starting batch: start_row=' . $start_row . ', batch_size=' . $batch_size . ', post_type=' . $post_type . ', include_headers=' . ($include_headers ? 'true' : 'false'));
	
	// Validate post type
	if ( ! post_type_exists( $post_type ) ) {
		wp_send_json_error( 'Invalid post type' );
		return;
	}
	
	// Get total posts count
	$total_posts = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
		$post_type
	) );
	
	if ( ! $total_posts ) {
		wp_send_json_error( 'No posts found' );
		return;
	}
	
	// Get posts for this chunk
	$posts = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->posts} 
		 WHERE post_type = %s 
		 AND post_status = 'publish'
		 ORDER BY ID DESC 
		 LIMIT %d, %d",
		$post_type,
		$start_row,
		$batch_size
	) );
	
	error_log('[Swift CSV Ajax Export] Found ' . count($posts) . ' posts for this chunk');
	
	// Generate CSV chunk
	$csv_chunk = '';
	
	if ( $include_headers && $start_row === 0 ) {
		// Generate headers only for first chunk
		$csv_chunk .= generate_export_headers( $post_type );
		error_log('[Swift CSV Ajax Export] Generated headers');
	}
	
	// Generate data rows
	foreach ( $posts as $post ) {
		$csv_chunk .= generate_export_row( $post, $post_type );
	}
	
	// Calculate progress
	$next_row = $start_row + count( $posts );
	$continue = $next_row < $total_posts;
	$progress = round( ($next_row / $total_posts) * 100, 2 );
	
	error_log('[Swift CSV Ajax Export] Batch completed: processed=' . $next_row . ', total=' . $total_posts . ', continue=' . ($continue ? 'yes' : 'no'));
	
	// Send response
	wp_send_json([
		'processed' => $next_row,
		'total' => $total_posts,
		'continue' => $continue,
		'progress' => $progress,
		'csv_chunk' => $csv_chunk,
		'posts_processed' => count( $posts )
	] );
}

/**
 * Generate export headers
 *
 * @since  1.0.0
 * @param  string $post_type Post type to export.
 * @return string CSV headers row.
 */
function generate_export_headers( $post_type ) {
	$headers = array( 'ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name', 'post_date' );
	
	// Add taxonomy columns
	$taxonomies = get_object_taxonomies( $post_type, 'objects' );
	foreach ( $taxonomies as $taxonomy ) {
		if ( $taxonomy->public ) {
			$headers[] = 'tax_' . $taxonomy->name;
		}
	}
	
	// Get ACF field mapping (same logic as batch processor)
	global $wpdb;
	$acf_field_keys = $wpdb->get_col(
		"SELECT post_name FROM {$wpdb->posts} 
		 WHERE post_type = 'acf-field'"
	);
	
	$acf_field_mapping = array();
	foreach ( $acf_field_keys as $field_key ) {
		$field_config = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_content FROM {$wpdb->posts} 
			 WHERE post_name = %s 
			 AND post_type = 'acf-field'",
			$field_key
		));
		if ( $field_config ) {
			$field_data = maybe_unserialize( $field_config );
			if ( is_array( $field_data ) && isset( $field_data['name'] ) ) {
				$acf_field_mapping[ $field_data['name'] ] = $field_key;
			}
		}
	}
	
	// Get sample posts to detect fields
	$sample_posts = $wpdb->get_results( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} 
		 WHERE post_type = %s 
		 AND post_status = 'publish'
		 LIMIT 5",
		$post_type
	) );
	
	$custom_fields = array();
	$acf_fields = array();
	
	foreach ( $sample_posts as $post ) {
		$fields = $wpdb->get_results( $wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->postmeta} 
			 WHERE post_id = %d 
			 AND meta_key NOT LIKE '\_%'",
			$post->ID
		) );
		
		foreach ( $fields as $field ) {
			$key = $field->meta_key;
			if ( ! in_array( $key, $custom_fields, true ) && ! in_array( $key, $acf_fields, true ) ) {
				if ( isset( $acf_field_mapping[ $key ] ) ) {
					$acf_fields[] = $key;
				} else {
					$custom_fields[] = $key;
				}
			}
		}
	}
	
	// Add custom field headers
	foreach ( $custom_fields as $field ) {
		$headers[] = 'cf_' . $field;
	}
	
	// Add ACF field headers
	foreach ( $acf_fields as $field ) {
		$headers[] = 'acf_' . $field;
	}
	
	error_log('[Swift CSV Ajax Export] Headers: ' . print_r($headers, true));
	
	// Convert to CSV
	$output = fopen( 'php://temp', 'r+' );
	fputcsv( $output, $headers );
	rewind( $output );
	$csv = stream_get_contents( $output );
	fclose( $output );
	
	return $csv;
}

/**
 * Generate export row for a single post
 *
 * @since  1.0.0
 * @param  object $post      Post object.
 * @param  string $post_type Post type.
 * @return string CSV row.
 */
function generate_export_row( $post, $post_type ) {
	global $wpdb;
	
	$row = array();
	
	// Basic post data
	$row[] = $post->ID;
	$row[] = $post->post_title;
	$row[] = $post->post_content;
	$row[] = $post->post_excerpt;
	$row[] = $post->post_status;
	$row[] = $post->post_name;
	$row[] = $post->post_date;
	
	// Taxonomy data
	$taxonomies = get_object_taxonomies( $post_type, 'objects' );
	foreach ( $taxonomies as $taxonomy ) {
		if ( $taxonomy->public ) {
			$terms = wp_get_post_terms( $post->ID, $taxonomy->name );
			$term_names = array_map( function( $term ) {
				return $term->name;
			}, $terms );
			$row[] = implode( '|', $term_names );
		}
	}
	
	// Get all meta fields for this post
	$all_meta = $wpdb->get_results( $wpdb->prepare(
		"SELECT meta_key, meta_value FROM {$wpdb->postmeta} 
		 WHERE post_id = %d 
		 AND meta_key NOT LIKE '\_%'",
		$post->ID
	) );
	
	// Convert to associative array
	$meta_array = array();
	foreach ( $all_meta as $meta ) {
		if ( ! isset( $meta_array[ $meta->meta_key ] ) ) {
			$meta_array[ $meta->meta_key ] = array();
		}
		$meta_array[ $meta->meta_key ][] = $meta->meta_value;
	}
	
	// Get ACF field mapping
	$acf_field_keys = $wpdb->get_col(
		"SELECT post_name FROM {$wpdb->posts} 
		 WHERE post_type = 'acf-field'"
	);
	
	$acf_field_mapping = array();
	foreach ( $acf_field_keys as $field_key ) {
		$field_config = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_content FROM {$wpdb->posts} 
			 WHERE post_name = %s 
			 AND post_type = 'acf-field'",
			$field_key
		));
		if ( $field_config ) {
			$field_data = maybe_unserialize( $field_config );
			if ( is_array( $field_data ) && isset( $field_data['name'] ) ) {
				$acf_field_mapping[ $field_data['name'] ] = $field_key;
			}
		}
	}
	
	// Process custom fields and ACF fields
	foreach ( $meta_array as $key => $values ) {
		if ( isset( $acf_field_mapping[ $key ] ) ) {
			// This is an ACF field
			$cleaned_values = array_map( 'clean_csv_field', $values );
			$row[] = implode( '|', $cleaned_values );
		} else {
			// This is a regular custom field
			$cleaned_values = array_map( 'clean_csv_field', $values );
			$row[] = implode( '|', $cleaned_values );
		}
	}
	
	// Convert to CSV
	$output = fopen( 'php://temp', 'r+' );
	fputcsv( $output, $row );
	rewind( $output );
	$csv = stream_get_contents( $output );
	fclose( $output );
	
	return $csv;
}

/**
 * Clean CSV field value
 *
 * @since  1.0.0
 * @param  mixed $field Field value.
 * @return string Cleaned value.
 */
function clean_csv_field( $field ) {
	if ( null === $field || '' === $field ) {
		return '';
	}
	
	if ( is_array( $field ) ) {
		$field = implode( '|', array_map( 'strval', $field ) );
	} elseif ( is_bool( $field ) ) {
		$field = $field ? '1' : '0';
	} elseif ( is_object( $field ) ) {
		if ( method_exists( $field, '__toString' ) ) {
			$field = (string) $field;
		} else {
			$field = wp_json_encode( $field );
		}
	} else {
		$field = (string) $field;
	}
	
	return $field;
}

// Register Ajax handler
add_action( 'wp_ajax_swift_csv_ajax_export', 'swift_csv_ajax_export_handler' );
