<?php
/**
 * Simple Ajax Export Handler for Swift CSV Plugin
 *
 * @since  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple Ajax export handler
 */
function swift_csv_ajax_export_handler() {
	// Security check
	check_ajax_referer( 'swift_csv_nonce', 'nonce' );
	
	// Get parameters
	$post_type = sanitize_text_field( $_POST['post_type'] ?? 'post' );
	$start_row = intval( $_POST['start_row'] ?? 0 );
	$batch_size = 10; // Small batch size for testing
	
	error_log('[Swift CSV Simple Ajax Export] Starting: post_type=' . $post_type . ', start_row=' . $start_row);
	
	// Validate post type
	if ( ! post_type_exists( $post_type ) ) {
		wp_send_json_error( 'Invalid post type' );
	}
	
	// Get total posts count with limit
	$total_posts = get_posts([
		'post_type' => $post_type,
		'post_status' => 'publish',
		'posts_per_page' => 100, // Limit to 100 posts for testing
		'fields' => 'ids'
	]);
	$total_count = count($total_posts);
	
	// For testing, limit to 100 posts max
	$max_posts = 100;
	$posts = get_posts([
		'post_type' => $post_type,
		'post_status' => 'publish',
		'posts_per_page' => min($batch_size, $max_posts - $start_row),
		'offset' => $start_row,
		'fields' => 'ids'
	]);
	
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
	
	// Add headers for first chunk
	if ($start_row === 0) {
		// Get basic headers
		$headers = ['ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name', 'post_date'];
		
		// Add dynamic taxonomy headers (same as batch processor)
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->public ) {
				$headers[] = 'tax_' . $taxonomy->name;
			}
		}
		
		// Apply filter to headers (same as main exporter)
		$headers = apply_filters( 'swift_csv_export_headers', $headers, [ 'post_type' => $post_type ] );
		
		// Convert headers to CSV row
		$csv_chunk .= implode(',', $headers) . "\n";
	}
	
	foreach ( $posts as $post_id ) {
		$post = get_post( $post_id );
		
		// Get filtered headers again for data consistency
		$headers = ['ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name', 'post_date'];
		
		// Add dynamic taxonomy headers (same as batch processor)
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->public ) {
				$headers[] = 'tax_' . $taxonomy->name;
			}
		}
		
		$headers = apply_filters( 'swift_csv_export_headers', $headers, [ 'post_type' => $post_type ] );
		
		// Build data row based on headers
		$row_data = [];
		foreach ($headers as $header) {
			$value = '';
			switch ($header) {
				case 'ID':
					$value = $post_id;
					break;
				case 'post_title':
					$value = str_replace(",", ";", $post->post_title);
					break;
				case 'post_content':
					$value = str_replace(["\r\n", "\n", "\r", ","], [" ", " ", " ", ";"], $post->post_content);
					break;
				case 'post_excerpt':
					$value = str_replace(",", ";", $post->post_excerpt ?? '');
					break;
				case 'post_status':
					$value = $post->post_status;
					break;
				case 'post_name':
					$value = $post->post_name;
					break;
				case 'post_date':
					$value = $post->post_date;
					break;
				default:
					// Handle custom fields from filter
					if (str_starts_with($header, 'cf_')) {
						$field_name = substr($header, 3);
						$value = get_post_meta($post_id, $field_name, true);
						if (is_array($value)) {
							$value = implode('|', $value);
						}
						$value = str_replace(",", ";", (string)$value);
					}
					// Handle taxonomy fields from filter
					elseif (str_starts_with($header, 'tax_')) {
						$taxonomy_name = substr($header, 4);
						$terms = get_the_terms($post_id, $taxonomy_name);
						if ($terms && !is_wp_error($terms)) {
							$term_names = array_map(function($term) {
								return $term->name;
							}, $terms);
							$value = implode('|', $term_names);
						} else {
							$value = '';
						}
						$value = str_replace(",", ";", $value);
					}
					break;
			}
			$row_data[] = $value;
		}
		
		$csv_chunk .= implode(',', $row_data) . "\n";
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
