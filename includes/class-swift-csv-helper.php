<?php
/**
 * Helper functions for Swift CSV processing.
 *
 * This file contains common utility functions used across the Swift CSV plugin
 * for parsing, validation, and data processing.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class for Swift CSV processing utilities.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_Helper {

	/**
	 * Parse CSV content line by line to handle quoted fields with newlines.
	 *
	 * @since 0.9.0
	 * @param string $csv_content CSV content.
	 * @return array<int, string>
	 */
	public static function parse_csv_lines_preserving_quoted_newlines( $csv_content ) {
		$lines        = [];
		$current_line = '';
		$in_quotes    = false;

		foreach ( explode( "\n", $csv_content ) as $line ) {
			// Count quotes to determine if we're inside a quoted field
			$quote_count = substr_count( $line, '"' );

			if ( $in_quotes ) {
				$current_line .= "\n" . $line;
			} else {
				$current_line = $line;
			}

			// Toggle quote state (odd number of quotes means we're inside quotes)
			if ( $quote_count % 2 === 1 ) {
				$in_quotes = ! $in_quotes;
			}

			// Only add line if we're not inside quotes
			if ( ! $in_quotes ) {
				$lines[]      = $current_line;
				$current_line = '';
			}
		}

		return $lines;
	}

	/**
	 * Detect CSV delimiter from first line.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines.
	 * @return string
	 */
	public static function detect_csv_delimiter( $lines ) {
		$first_line = $lines[0] ?? '';
		$delimiters = [ ',', ';', "\t" ];
		$delimiter  = ',';

		foreach ( $delimiters as $delim ) {
			if ( substr_count( $first_line, $delim ) > substr_count( $first_line, $delimiter ) ) {
				$delimiter = $delim;
			}
		}

		return $delimiter;
	}

	/**
	 * Read and normalize CSV headers.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines.
	 * @param string             $delimiter CSV delimiter.
	 * @return array<int, string>
	 */
	public static function read_and_normalize_headers( $lines, $delimiter ) {
		$headers = [];

		if ( empty( $lines ) ) {
			return $headers;
		}

		$first_line  = $lines[0];
		$header_data = str_getcsv( $first_line, $delimiter );

		foreach ( $header_data as $header ) {
			$normalized_header = trim( (string) $header );
			$normalized_header = preg_replace( '/^\xEF\xBB\xBF/', '', $normalized_header );
			$normalized_header = preg_replace( '/[\x00-\x1F\x7F]/', '', $normalized_header );
			$headers[]         = trim( $normalized_header );
		}

		return $headers;
	}

	/**
	 * Parse PHP ini size string to bytes.
	 *
	 * @since 0.9.0
	 * @param string $size The size string (e.g., '10M', '1G')
	 * @return int Size in bytes
	 */
	public static function parse_ini_size( $size ) {
		$unit  = strtoupper( substr( $size, -1 ) );
		$value = (int) substr( $size, 0, -1 );

		switch ( $unit ) {
			case 'G':
				return $value * 1024 * 1024 * 1024;
			case 'M':
				return $value * 1024 * 1024;
			case 'K':
				return $value * 1024;
			default:
				return (int) $size;
		}
	}

	/**
	 * Setup DB session for import process.
	 *
	 * @since 0.9.0
	 * @param wpdb $wpdb WordPress DB instance.
	 * @return void
	 */
	public static function setup_db_session( wpdb $wpdb ): void {
		// Disable locks
		$wpdb->query( 'SET SESSION autocommit = 1' );
		$wpdb->query( 'SET SESSION innodb_lock_wait_timeout = 1' );
	}

	/**
	 * Update GUID for newly inserted posts.
	 *
	 * @since 0.9.0
	 * @param wpdb $wpdb WordPress DB instance.
	 * @param int  $post_id Post ID.
	 * @return void
	 */
	public static function update_guid_for_new_post( wpdb $wpdb, int $post_id ): void {
		$wpdb->update(
			$wpdb->posts,
			[ 'guid' => get_permalink( $post_id ) ],
			[ 'ID' => $post_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Send JSON error response with optional file cleanup.
	 *
	 * @since 0.9.0
	 * @param string $error_message Error message.
	 * @param string $file_path Optional file path to cleanup.
	 * @return void
	 */
	public static function send_error_response( $error_message, $file_path = null ) {
		if ( $file_path && file_exists( $file_path ) ) {
			unlink( $file_path );
		}

		wp_send_json(
			[
				'success' => false,
				'error'   => $error_message,
			]
		);
	}

	/**
	 * Send JSON error response with cleanup and return null.
	 *
	 * @since 0.9.0
	 * @param string $error_message Error message.
	 * @param string $file_path Optional file path to cleanup.
	 * @return null
	 */
	public static function send_error_response_and_return_null( $error_message, $file_path = null ) {
		self::send_error_response( $error_message, $file_path );
		return null;
	}

	/**
	 * Verify nonce for security check.
	 *
	 * @since 0.9.0
	 * @param string $nonce Nonce value to verify.
	 * @param string $nonce_action Nonce action.
	 * @return bool True if nonce is valid.
	 */
	public static function verify_nonce( $nonce, $nonce_action = 'swift_csv_ajax_nonce' ) {
		return isset( $nonce ) && wp_verify_nonce( $nonce, $nonce_action );
	}

	/**
	 * Send security error response with cleanup.
	 *
	 * @since 0.9.0
	 * @param string $file_path Optional file path to cleanup.
	 * @return void
	 */
	public static function send_security_error( $file_path = null ) {
		self::send_error_response( 'Security check failed', $file_path );
	}

	/**
	 * Validate uploaded file.
	 *
	 * @since 0.9.0
	 * @param array $file $_FILES array item.
	 * @return array{valid:bool,error:string|null} Validation result.
	 */
	public static function validate_upload_file( $file ) {
		if ( ! isset( $file ) ) {
			return [
				'valid' => false,
				'error' => 'No file uploaded',
			];
		}

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return [
				'valid' => false,
				'error' => 'Upload error: ' . $file['error'],
			];
		}

		return [
			'valid' => true,
			'error' => null,
		];
	}

	/**
	 * Get PHP upload limits in bytes.
	 *
	 * @since 0.9.0
	 * @return int Maximum file size in bytes.
	 */
	public static function get_upload_limits() {
		$upload_max = ini_get( 'upload_max_filesize' );
		$post_max   = ini_get( 'post_max_size' );

		$upload_max_bytes = self::parse_ini_size( $upload_max );
		$post_max_bytes   = self::parse_ini_size( $post_max );

		return min( $upload_max_bytes, $post_max_bytes );
	}

	/**
	 * Validate file size against upload limits.
	 *
	 * @since 0.9.0
	 * @param array $file $_FILES array item.
	 * @return array{valid:bool,error:string|null} Validation result.
	 */
	public static function validate_file_size( $file ) {
		$max_file_size = self::get_upload_limits();

		if ( $file['size'] > $max_file_size ) {
			return [
				'valid' => false,
				'error' => 'File too large',
			];
		}

		return [
			'valid' => true,
			'error' => null,
		];
	}

	/**
	 * Create temporary directory for CSV uploads.
	 *
	 * @since 0.9.0
	 * @return string Temporary directory path.
	 */
	public static function create_temp_directory() {
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/swift-csv-temp';

		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		return $temp_dir;
	}

	/**
	 * Generate temporary file path.
	 *
	 * @since 0.9.0
	 * @param string $temp_dir Temporary directory path.
	 * @return string Temporary file path.
	 */
	public static function generate_temp_file_path( $temp_dir ) {
		return $temp_dir . '/ajax-import-' . time() . '.csv';
	}

	/**
	 * Save uploaded file to temporary location.
	 *
	 * @since 0.9.0
	 * @param array  $file $_FILES array item.
	 * @param string $temp_file Target temporary file path.
	 * @return bool True on success, false on failure.
	 */
	public static function save_uploaded_file( $file, $temp_file ) {
		return move_uploaded_file( $file['tmp_name'], $temp_file );
	}

	/**
	 * Analyze term values to determine format type.
	 *
	 * @since 0.9.0
	 * @param array $term_values Array of term values.
	 * @return array{all_numeric:bool,all_string:bool,mixed:bool} Format analysis result.
	 */
	public static function analyze_term_values_format( $term_values ) {
		$all_numeric = true;
		$all_string  = true;
		$mixed       = false;

		foreach ( $term_values as $term_val ) {
			if ( $term_val === '' ) {
				continue;
			}

			if ( is_numeric( $term_val ) ) {
				$all_string = false;
			} else {
				$all_numeric = false;
			}

			if ( ! $all_numeric && ! $all_string ) {
				$mixed = true;
				break;
			}
		}

		return [
			'all_numeric' => $all_numeric,
			'all_string'  => $all_string,
			'mixed'       => $mixed,
		];
	}

	/**
	 * Validate taxonomy format consistency.
	 *
	 * @since 0.9.0
	 * @param string $taxonomy_format UI selected format.
	 * @param array  $validation Taxonomy validation data.
	 * @param string $file_path Optional file path for cleanup.
	 * @return array{valid:bool,error:string|null} Validation result.
	 */
	public static function validate_taxonomy_format_consistency( $taxonomy_format, $validation, $file_path = null ) {
		// Check for format mismatches
		if ( $taxonomy_format === 'name' && $validation['all_numeric'] ) {
			$error = sprintf(
				/* translators: 1: taxonomy name, 2: UI format, 3: sample values */
				__( 'Format mismatch detected for taxonomy "%1$s". UI is set to "Names" but CSV contains only numeric values: %2$s. Please check your data format.', 'swift-csv' ),
				$validation['taxonomy_name'] ?? 'unknown',
				implode( ', ', $validation['sample_values'] ?? [] )
			);

			if ( $file_path && file_exists( $file_path ) ) {
				unlink( $file_path );
			}

			return [
				'valid' => false,
				'error' => $error,
			];
		}

		if ( $taxonomy_format === 'id' && $validation['all_string'] ) {
			$error = sprintf(
				/* translators: 1: taxonomy name, 2: UI format, 3: sample values */
				__( 'Format mismatch detected for taxonomy "%1$s". UI is set to "Term IDs" but CSV contains only text values: %2$s. Please check your data format.', 'swift-csv' ),
				$validation['taxonomy_name'] ?? 'unknown',
				implode( ', ', $validation['sample_values'] ?? [] )
			);

			if ( $file_path && file_exists( $file_path ) ) {
				unlink( $file_path );
			}

			return [
				'valid' => false,
				'error' => $error,
			];
		}

		return [
			'valid' => true,
			'error' => null,
		];
	}

	/**
	 * Validate ID column exists in CSV headers.
	 *
	 * @since 0.9.0
	 * @param array  $headers CSV headers.
	 * @param string $file_path Optional file path for cleanup.
	 * @return array{valid:bool,id_col:int|null,error:string|null} Validation result.
	 */
	public static function validate_id_column( $headers, $file_path = null ) {
		$id_col = array_search( 'ID', $headers, true );
		if ( $id_col !== false ) {
			return [
				'valid'  => true,
				'id_col' => $id_col,
				'error'  => null,
			];
		}

		if ( $file_path && file_exists( $file_path ) ) {
			unlink( $file_path );
		}

		return [
			'valid'  => false,
			'id_col' => null,
			'error'  => 'Invalid CSV format: ID column is required',
		];
	}

	/**
	 * Count actual data rows (exclude empty lines).
	 *
	 * @since 0.9.0
	 * @param array $lines CSV lines.
	 * @return int Number of non-empty rows.
	 */
	public static function count_data_rows( $lines ) {
		$total_rows = 0;
		foreach ( $lines as $line ) {
			if ( ! empty( trim( $line ) ) ) {
				++$total_rows;
			}
		}
		return $total_rows;
	}

	/**
	 * Resolve term by ID with validation.
	 *
	 * @since 0.9.0
	 * @param int    $term_id Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array{valid:bool,term_id:int|null,error:string|null} Resolution result.
	 */
	public static function resolve_term_by_id( $term_id, $taxonomy ) {
		$term = get_term( $term_id, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			return [
				'valid'   => true,
				'term_id' => $term_id,
				'error'   => null,
			];
		}

		return [
			'valid'   => false,
			'term_id' => null,
			'error'   => "Invalid term ID: {$term_id} in taxonomy '{$taxonomy}'",
		];
	}

	/**
	 * Resolve term by name, creating if not found.
	 *
	 * @since 0.9.0
	 * @param string $term_name Term name.
	 * @param string $taxonomy Taxonomy name.
	 * @return array{valid:bool,term_id:int|null,error:string|null} Resolution result.
	 */
	public static function resolve_term_by_name( $term_name, $taxonomy ) {
		$term = get_term_by( 'name', $term_name, $taxonomy );
		if ( $term ) {
			return [
				'valid'   => true,
				'term_id' => (int) $term->term_id,
				'error'   => null,
			];
		}

		// Create new term
		$created = wp_insert_term( $term_name, $taxonomy );
		if ( is_wp_error( $created ) ) {
			return [
				'valid'   => false,
				'term_id' => null,
				'error'   => "Failed to create term '{$term_name}' in taxonomy '{$taxonomy}'",
			];
		}

		return [
			'valid'   => true,
			'term_id' => (int) $created['term_id'],
			'error'   => null,
		];
	}

	/**
	 * Resolve term IDs from a term value based on format.
	 *
	 * @since 0.9.0
	 * @param string $taxonomy Taxonomy name.
	 * @param string $term_value Term value.
	 * @param string $taxonomy_format Taxonomy format ('name' or 'id').
	 * @param array  $taxonomy_format_validation Taxonomy format validation data.
	 * @return array<int, int> Array of resolved term IDs.
	 */
	public static function resolve_term_ids_from_value( $taxonomy, $term_value, $taxonomy_format, $taxonomy_format_validation ) {
		// Handle different taxonomy formats with mixed data support
		if ( $taxonomy_format === 'id' ) {
			// Handle term IDs
			$term_id = intval( $term_value );

			// Check if the term value is actually a valid ID
			if ( $term_id === 0 ) {
				// For mixed format, treat numeric values as names
				if ( isset( $taxonomy_format_validation[ $taxonomy ] ) && $taxonomy_format_validation[ $taxonomy ]['mixed'] ) {
					$name_result = self::resolve_term_by_name( $term_value, $taxonomy );
					if ( $name_result['valid'] ) {
						return [ $name_result['term_id'] ];
					}
				}

				// Skip this term as it's not a valid ID
				return [];
			}

			// Valid ID, process normally
			$id_result = self::resolve_term_by_id( $term_id, $taxonomy );
			if ( $id_result['valid'] ) {
				return [ $id_result['term_id'] ];
			}

			return [];
		}

		// Handle term names (default)
		$name_result = self::resolve_term_by_name( $term_value, $taxonomy );
		if ( $name_result['valid'] ) {
			return [ $name_result['term_id'] ];
		}

		return [];
	}

	/**
	 * Parse CSV row using str_getcsv.
	 *
	 * @since 0.9.0
	 * @param string $line CSV line.
	 * @param string $delimiter CSV delimiter.
	 * @return array Parsed CSV row data.
	 */
	public static function parse_csv_row( $line, $delimiter ) {
		return str_getcsv( $line, $delimiter );
	}

	/**
	 * Check if a CSV line is empty.
	 *
	 * @since 0.9.0
	 * @param string $line CSV line.
	 * @return bool True if line is empty.
	 */
	public static function is_empty_csv_line( $line ) {
		return empty( trim( $line ) );
	}

	/**
	 * Normalize field name by removing BOM and control characters.
	 *
	 * @since 0.9.0
	 * @param string $name Field name.
	 * @return string Normalized field name.
	 */
	public static function normalize_field_name( $name ) {
		$name = trim( (string) $name );
		$name = preg_replace( '/^\xEF\xBB\xBF/', '', $name );
		$name = preg_replace( '/[\x00-\x1F\x7F]/', '', $name );
		return trim( $name );
	}

	/**
	 * Resolve post author ID from display name or ID.
	 *
	 * @since 0.9.0
	 * @param string $author_display_name Author display name or ID.
	 * @return int Author ID.
	 */
	public static function resolve_post_author_id( $author_display_name ) {
		$post_author_id = 1;

		if ( ! empty( $author_display_name ) ) {
			$author_display_name = trim( $author_display_name );

			if ( is_numeric( $author_display_name ) ) {
				$post_author_id = (int) $author_display_name;
			} else {
				$author_user = get_user_by( 'display_name', $author_display_name );
				if ( $author_user ) {
					$post_author_id = $author_user->ID;
				} elseif ( get_current_user_id() ) {
					$post_author_id = get_current_user_id();
				}
			}
		} elseif ( get_current_user_id() ) {
			$post_author_id = get_current_user_id();
		}

		return $post_author_id;
	}

	/**
	 * Build post data for update operation.
	 *
	 * @since 0.9.0
	 * @param array<string, string> $post_fields_from_csv Post fields from CSV.
	 * @return array<string, mixed> Post data array.
	 */
	public static function build_post_data_for_update( $post_fields_from_csv ) {
		$post_data = [];
		foreach ( $post_fields_from_csv as $key => $value ) {
			$post_data[ $key ] = $value;
		}
		$post_data['post_modified']     = current_time( 'mysql' );
		$post_data['post_modified_gmt'] = current_time( 'mysql', true );
		return $post_data;
	}

	/**
	 * Build post data for insert operation.
	 *
	 * @since 0.9.0
	 * @param array<string, string> $post_fields_from_csv Post fields from CSV.
	 * @param string                $post_type Post type.
	 * @return array<string, mixed> Post data array.
	 */
	public static function build_post_data_for_insert( $post_fields_from_csv, $post_type ) {
		$post_author_id = self::resolve_post_author_id( $post_fields_from_csv['post_author'] ?? '' );
		$post_title     = $post_fields_from_csv['post_title'] ?? '';

		return [
			'post_author'       => $post_author_id,
			'post_date'         => $post_fields_from_csv['post_date'] ?? current_time( 'mysql' ),
			'post_date_gmt'     => $post_fields_from_csv['post_date_gmt'] ?? current_time( 'mysql', true ),
			'post_content'      => $post_fields_from_csv['post_content'] ?? '',
			'post_title'        => $post_title,
			'post_excerpt'      => $post_fields_from_csv['post_excerpt'] ?? '',
			'post_status'       => $post_fields_from_csv['post_status'] ?? 'publish',
			'post_name'         => $post_fields_from_csv['post_name'] ?? sanitize_title( $post_title ),
			'post_type'         => $post_type,
			'comment_status'    => $post_fields_from_csv['comment_status'] ?? 'closed',
			'ping_status'       => $post_fields_from_csv['ping_status'] ?? 'closed',
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', true ),
			'post_parent'       => (int) ( $post_fields_from_csv['post_parent'] ?? 0 ),
			'menu_order'        => (int) ( $post_fields_from_csv['menu_order'] ?? 0 ),
			'post_mime_type'    => '',
			'comment_count'     => 0,
		];
	}
}
