<?php

/**
 * Exporter class for handling CSV exports
 *
 * This file contains the export functionality for the Swift CSV plugin,
 * including data retrieval, CSV formatting, and file download.
 *
 * @since  0.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include batch class (used for async export start).
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-batch.php';

class Swift_CSV_Exporter {

	/**
	 * Constructor
	 *
	 * Sets up WordPress hooks for export functionality.
	 *
	 * @since  0.9.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_post_swift_csv_export', array( $this, 'handle_export' ) );
	}

	/**
	 * Handle CSV export request
	 *
	 * Validates user permissions, nonce, and parameters before processing export.
	 *
	 * @since  0.9.0
	 * @return void
	 */
	public function handle_export() {
		// Security checks
		if (
			! current_user_can( 'manage_options' )
			|| ! isset( $_POST['csv_export_nonce'] )
			|| ! wp_verify_nonce( $_POST['csv_export_nonce'], 'swift_csv_export' )
		) {
			wp_die( esc_html__( 'Security check failed.', 'swift-csv' ) );
		}

		// Validate required parameters
		if ( ! isset( $_POST['post_type'] ) || ! isset( $_POST['posts_per_page'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'swift-csv' ) );
		}

		// Sanitize and validate inputs
		$post_type      = sanitize_text_field( $_POST['post_type'] );
		$posts_per_page = intval( $_POST['posts_per_page'] );

        // Validate post type exists
		if ( ! post_type_exists( $post_type ) ) {
			wp_die( esc_html__( 'Invalid post type.', 'swift-csv' ) );
		}

		$posts_per_page = max( 1, $posts_per_page );

		$batch = new Swift_CSV_Batch();
		$batch_id = $batch->start_export_batch( $post_type, [ 'posts_per_page' => $posts_per_page ] );
		if ( ! $batch_id ) {
			wp_die( esc_html__( 'Failed to create export batch.', 'swift-csv' ) );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'  => 'swift-csv',
					'tab'   => 'export',
					'batch' => $batch_id,
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Export posts to CSV
	 *
	 * Retrieves posts, generates CSV headers and content, and sends file to browser.
	 *
	 * @since  0.9.0
	 * @param  string $post_type      The post type to export.
	 * @param  int    $posts_per_page Number of posts to export.
	 * @return void
	 */
	private function export_csv( $post_type, $posts_per_page ) {
		$args = [
			'post_type' => $post_type,
			'posts_per_page' => $posts_per_page
		];

		// Fire before export hook
		do_action( 'swift_csv_before_export', $args );

		$this->_debugLog( 'Export CSV started for post type: ' . $post_type );

		// Query arguments for post retrieval
		$query_args = [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		];

		// Apply filter to query arguments
		$query_args = apply_filters( 'swift_csv_export_query_args', $query_args, $args );

		$this->_debugLog( 'Query args: ' . print_r( $query_args, true ) );

		$query = new WP_Query( $query_args );
		$posts = $query->posts;

		$this->_debugLog( 'Found posts count: ' . count( $posts ) );

		// Check if posts exist
		if ( empty( $posts ) ) {
			wp_die( esc_html__( 'No posts found to export.', 'swift-csv' ) );
		}

		// Prepare CSV headers with standard post fields
		$headers = array( 'ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name', 'post_date' );

        // Add taxonomy columns
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->public ) {
				$headers[] = 'tax_' . $taxonomy->name;
			}
		}

		// Add custom field columns (sample first 20 posts to detect fields)
		$custom_fields = array();
		$acf_fields = array();
		$sample_posts  = array_slice( $posts, 0, 20 );
		
		// First, get all ACF field keys from wp_posts
		global $wpdb;
		$acf_field_keys = $wpdb->get_col(
			"SELECT post_name FROM {$wpdb->posts} 
			 WHERE post_type = 'acf-field'"
		);
		
		// Get field key to field name mapping
		$acf_field_mapping = array();
		foreach ($acf_field_keys as $field_key) {
			$field_config = $wpdb->get_var($wpdb->prepare(
				"SELECT post_content FROM {$wpdb->posts} 
				 WHERE post_name = %s 
				 AND post_type = 'acf-field'",
				$field_key
			));
			if ($field_config) {
				$field_data = maybe_unserialize($field_config);
				if (is_array($field_data) && isset($field_data['name'])) {
					$acf_field_mapping[$field_data['name']] = $field_key;
					$this->_debugLog( 'Export: Found ACF field mapping: ' . $field_data['name'] . ' -> ' . $field_key );
				}
			}
		}
		
		$this->_debugLog( 'Export: ACF field mapping: ' . print_r( $acf_field_mapping, true ) );
		
		foreach ( $sample_posts as $post ) {
			$fields = get_post_meta( $post->ID );
			foreach ( $fields as $key => $value ) {
				// Skip private fields (starting with _) and duplicates
				if ( ! str_starts_with( $key, '_' ) && ! in_array( $key, $custom_fields, true ) && ! in_array( $key, $acf_fields, true ) ) {
					// Check if this is an ACF field by looking up in our mapping
					if ( isset( $acf_field_mapping[$key] ) ) {
						// This is an ACF field
						$acf_fields[] = $key;
						$this->_debugLog( 'Export: Detected ACF field: ' . $key . ' (key: ' . $acf_field_mapping[$key] . ')' );
					} else {
						// This is a regular custom field
						$custom_fields[] = $key;
						$this->_debugLog( 'Export: Detected custom field: ' . $key );
					}
				}
			}
		}

		// Debug: Log detected fields
		$custom_fields_dump = print_r( $custom_fields, true );
		$acf_fields_dump = print_r( $acf_fields, true );
		$this->_debugLog( 'Export: Detected custom fields: ' . $custom_fields_dump );
		$this->_debugLog( 'Export: Detected ACF fields: ' . $acf_fields_dump );

		// Add custom field headers with 'cf_' prefix
		foreach ( $custom_fields as $field ) {
			$headers[] = 'cf_' . $field;
		}

		// Add ACF field headers with 'acf_' prefix
		foreach ( $acf_fields as $field ) {
			$headers[] = 'acf_' . $field;
		}

		// Apply filter to headers
		$headers = apply_filters( 'swift_csv_export_headers', $headers, $args );

		// Generate CSV content
		$csv_content = $this->generate_csv_content( $posts, $headers, $taxonomies, $custom_fields, $acf_fields );

		// Generate filename with timestamp and plugin prefix (using local time)
		$filename = 'swiftcsv_export_' . $post_type . '_' . date_i18n( 'Y-m-d_H-i-s' ) . '.csv';

		// Clear any existing output buffer
		if ( ob_get_level() ) {
			ob_clean();
		}

		// Add BOM for Excel compatibility with Japanese characters
		$bom         = "\xEF\xBB\xBF";
		$full_content = $bom . $csv_content;

		// Set HTTP headers for CSV download
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $full_content ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );

		echo $full_content;

		// Fire after export hook
		do_action( 'swift_csv_after_export', $filename, $args );

		exit;
	}

	/**
	 * Cleans and prepares field data for CSV output.
	 * Handles newlines, quotes, backslashes, and special characters properly.
	 * Fixes over-escaping issues from data preprocessing.
	 *
	 * @since  0.9.5
	 * @param  mixed $field Field value to clean.
	 * @return string Cleaned field value.
	 */
	private function clean_csv_field( $field ) {
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

		// Fix over-escaping issues from data preprocessing
		$field = $this->normalize_backslashes( $field );
		$field = $this->normalize_quotes( $field );

		// Let fputcsv handle the escaping - it will properly quote fields with commas, quotes, and newlines
		return $field;
	}

	/**
	 * Normalize backslashes in field data
	 *
	 * Reduces excessive backslashes while preserving intentional ones.
	 * Handles common over-escaping patterns from data preprocessing.
	 *
	 * @since  0.9.5
	 * @param  string $field Field value to normalize.
	 * @return string Normalized field value.
	 */
	private function normalize_backslashes( $field ) {
		// Reduce excessive consecutive backslashes (3+ backslashes)
		$field = preg_replace( '/\\\\{3,}/', '\\', $field );

		// Fix double backslashes followed by quote (common over-escaping)
		$field = preg_replace( '/\\\\\\\\"/', '"', $field );

		return $field;
	}

	/**
	 * Normalize quotes in field data
	 *
	 * Fixes inconsistent quote escaping while preserving proper CSV formatting.
	 * Ensures quotes are properly balanced for CSV parsing.
	 *
	 * @since  0.9.5
	 * @param  string $field Field value to normalize.
	 * @return string Normalized field value.
	 */
	private function normalize_quotes( $field ) {
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
	 * Debug logger
	 *
	 * Logs debug messages only when WP_DEBUG is enabled.
	 *
	 * @since  0.9.0
	 * @param  string $message Message to log.
	 * @return void
	 */
	private function _debugLog( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $message );
		}
	}

    /**
	 * Generate CSV content from posts
	 *
	 * Creates CSV data including headers, post data, taxonomies, and custom fields.
	 *
	 * @since  0.9.0
	 * @param  array $posts         Array of WP_Post objects.
	 * @param  array $headers       CSV header columns.
	 * @param  array $taxonomies    Taxonomy objects for the post type.
	 * @param  array $custom_fields Custom field keys detected in posts.
	 * @param  array $acf_fields    ACF field keys detected in posts.
	 * @return string Generated CSV content.
	 */
	private function generate_csv_content( $posts, $headers, $taxonomies, $custom_fields, $acf_fields ) {
		$csv = array();

		// Add header row
		$csv[] = $headers;

		// Add data rows for each post
		foreach ( $posts as $post ) {
			$row = array();

			// Basic post data
			$row[] = $post->ID;
			$row[] = $this->clean_csv_field( $post->post_title );
			$row[] = $this->clean_csv_field( $post->post_content );
			$row[] = $this->clean_csv_field( $post->post_excerpt );
			$row[] = $post->post_status;
			$row[] = $post->post_name;
			$row[] = $post->post_date;

			// Taxonomy data
			foreach ( $taxonomies as $taxonomy ) {
				if ( $taxonomy->public ) {
					$terms      = wp_get_post_terms( $post->ID, $taxonomy->name );
					$term_names = array_map(
						function ( $term ) use ( $taxonomy ) {
							return $this->get_term_hierarchy_path( $term, $taxonomy->name );
						},
						$terms
					);
					// Pipe-separated term names for multiple terms (consistent with custom fields)
					$row[] = implode( '|', $term_names );
				}
			}

			// Custom field data with multi-value support
			foreach ( $custom_fields as $field ) {
				$values = get_post_meta( $post->ID, $field, false ); // Get all values
				if ( is_array( $values ) && count( $values ) > 1 ) {
					// Multiple values - clean each and join with pipe separator
					$cleaned_values = array_map( [ $this, 'clean_csv_field' ], $values );
					$row[] = implode( '|', $cleaned_values );
				} elseif ( is_array( $values ) && count( $values ) === 1 ) {
					// Single value - clean it
					$row[] = $this->clean_csv_field( $values[0] );
				} else {
					// No values
					$row[] = '';
				}
			}

			// ACF field data with multi-value support
			foreach ( $acf_fields as $field ) {
				$values = get_post_meta( $post->ID, $field, false ); // Get all values
				if ( is_array( $values ) && count( $values ) > 1 ) {
					// Multiple values - clean each and join with pipe separator
					$cleaned_values = array_map( [ $this, 'clean_csv_field' ], $values );
					$row[] = implode( '|', $cleaned_values );
				} elseif ( is_array( $values ) && count( $values ) === 1 ) {
					// Single value - clean it
					$row[] = $this->clean_csv_field( $values[0] );
				} else {
					// No values
					$row[] = '';
				}
			}

			// Apply filter to row data before adding to CSV
			$row = apply_filters( 'swift_csv_export_row', $row, $post->ID, $args );

			$csv[] = $row;
		}

		// Convert array to CSV string using PHP's built-in function
		$output = fopen( 'php://temp', 'r+' );
		foreach ( $csv as $row ) {
			fputcsv( $output, $row );
		}
		rewind( $output );
		$content = stream_get_contents( $output );
		fclose( $output );

		return $content;
	}

    /**
	 * Get term hierarchy path
	 *
	 * Returns the full hierarchy path for a term (e.g., "Parent > Child > Grandchild").
	 *
	 * @since  0.9.0
	 * @param  WP_Term $term     The term to get path for.
	 * @param  string  $taxonomy Taxonomy name.
	 * @return string  The hierarchy path.
	 */
	private function get_term_hierarchy_path( $term, $taxonomy ) {
		$path         = array();
		$current_term = $term;

		// Build path from root to current term
		while ( $current_term && $current_term->parent != 0 ) {
			array_unshift( $path, $current_term->name );
			$current_term = get_term( $current_term->parent, $taxonomy );
			if ( is_wp_error( $current_term ) ) {
				break;
			}
		}

		// Add the root term
		if ( $current_term ) {
			array_unshift( $path, $current_term->name );
		}

		return implode( ' > ', $path );
	}
}
