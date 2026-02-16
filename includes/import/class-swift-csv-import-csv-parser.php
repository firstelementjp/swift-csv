<?php
/**
 * Import CSV Parser for Swift CSV
 *
 * Handles CSV parsing operations for CSV import.
 * Extracted from Swift_CSV_Ajax_Import for better separation of concerns.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CSV parsing operations for CSV import.
 *
 * This class is responsible for:
 * - CSV content parsing and validation
 * - Header processing
 * - Taxonomy format validation
 * - Data structure preparation
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Import_Csv_Parser {

	/**
	 * CSV utility instance.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Csv|null
	 */
	private $csv_util;

	/**
	 * Constructor.
	 *
	 * @since 0.9.8
	 */
	public function __construct() {
		// Initialize dependencies
	}

	/**
	 * Parse and validate CSV content.
	 *
	 * Extracted from parse_and_validate_csv() for better modularity.
	 *
	 * @since 0.9.8
	 * @param string $csv_content CSV content.
	 * @param array  $config Import configuration.
	 * @param string $file_path Temporary file path for cleanup.
	 * @return array|null Parsed CSV data or null on error (sends JSON response).
	 */
	public function parse_and_validate_csv( string $csv_content, array $config, string $file_path ): ?array {
		// Parse CSV content line by line to handle quoted fields with newlines
		$lines = $this->get_csv_util()->parse_csv_lines_preserving_quoted_newlines( $csv_content );

		// Detect CSV delimiter from first line
		$delimiter = $this->get_csv_util()->detect_csv_delimiter( $lines );

		// Read and normalize headers
		$headers = $this->get_csv_util()->read_and_normalize_headers( $lines, $delimiter );

		// Detect taxonomy format validation
		$taxonomy_format_validation = $this->detect_taxonomy_format_validation_or_send_error_and_cleanup(
			$lines,
			$delimiter,
			$headers,
			$config['taxonomy_format'],
			$file_path
		);

		if ( null === $taxonomy_format_validation ) {
			return null;
		}

		// Count total rows
		$total_rows = $this->get_csv_util()->count_total_rows( $lines );

		return [
			'lines'                     => $lines,
			'delimiter'                 => $delimiter,
			'headers'                   => $headers,
			'taxonomy_format_validation' => $taxonomy_format_validation,
			'total_rows'                => $total_rows,
		];
	}

	/**
	 * Detect taxonomy format validation.
	 *
	 * @since 0.9.8
	 * @param array  $lines CSV lines.
	 * @param string $delimiter CSV delimiter.
	 * @param array  $headers CSV headers.
	 * @param string $taxonomy_format Taxonomy format selected in UI.
	 * @param string $file_path Temporary file path for cleanup.
	 * @return array|null Taxonomy validation data or null on error (sends JSON response).
	 */
	private function detect_taxonomy_format_validation_or_send_error_and_cleanup(
		array $lines,
		string $delimiter,
		array $headers,
		string $taxonomy_format,
		string $file_path
	): ?array {
		$taxonomy_format_validation = [];
		$first_row_processed        = false;

		// Process first row for format detection
		$data = [];
		foreach ( $lines as $line ) {
			$row = str_getcsv( $line, $delimiter );
			if ( count( $row ) !== count( $headers ) ) {
				continue; // Skip malformed rows
			}
			$data[] = $row;

			// Process taxonomies for format detection on first data row only
			if ( ! $first_row_processed ) {
				foreach ( $headers as $j => $header_name ) {
					$header_name_normalized = strtolower( trim( $header_name ) );

					if ( strpos( $header_name_normalized, 'tax_' ) === 0 ) {
						$taxonomy_name = substr( $header_name_normalized, 4 ); // Remove tax_

						// Get taxonomy object to validate
						$taxonomy_obj = get_taxonomy( $taxonomy_name );
						if ( ! $taxonomy_obj ) {
							continue; // Skip invalid taxonomy
						}

						$meta_value = $row[ $j ] ?? '';
						if ( $meta_value !== '' ) {
							$term_values     = array_map( 'trim', explode( ',', $meta_value ) );
							$format_analysis = Swift_CSV_Helper::analyze_term_values_format( $term_values );

							$taxonomy_format_validation[ $taxonomy_name ] = array_merge(
								$format_analysis,
								[
									'sample_values' => $term_values,
									'taxonomy_name' => $taxonomy_name,
								]
							);
						}
					}
				}
				$first_row_processed = true;
			}
		}

		// Validate format consistency
		foreach ( $taxonomy_format_validation as $taxonomy_name => $validation ) {
			$consistency_result = Swift_CSV_Helper::validate_taxonomy_format_consistency( $taxonomy_format, $validation, $file_path );
			if ( ! $consistency_result['valid'] ) {
				Swift_CSV_Helper::send_error_response( $consistency_result['error'] );
				return null;
			}
		}

		return $taxonomy_format_validation;
	}

	/**
	 * Get CSV utility instance.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Csv
	 */
	private function get_csv_util(): Swift_CSV_Import_Csv {
		if ( null === $this->csv_util ) {
			$this->csv_util = new Swift_CSV_Import_Csv();
		}
		return $this->csv_util;
	}
}
