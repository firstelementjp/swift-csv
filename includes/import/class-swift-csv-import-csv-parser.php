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
	 * Taxonomy utility instance.
	 *
	 * @since 0.9.11
	 * @var Swift_CSV_Import_Taxonomy_Util|null
	 */
	private $taxonomy_util;

	/**
	 * Constructor.
	 *
	 * @since 0.9.8
	 * @param Swift_CSV_Import_Taxonomy_Util|null $taxonomy_util Taxonomy util.
	 */
	public function __construct( ?Swift_CSV_Import_Taxonomy_Util $taxonomy_util = null ) {
		$this->taxonomy_util = $taxonomy_util;
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
		Swift_CSV_Ajax_Util::set_stage( 'csv_parser:parse_and_validate_csv' );

		try {
			// Parse CSV content line by line to handle quoted fields with newlines.
			$lines = $this->get_csv_util()->parse_csv_lines_preserving_quoted_newlines( $csv_content );

			// Detect CSV delimiter from first line.
			$delimiter = $this->get_csv_util()->detect_csv_delimiter( $lines );

			// Read and normalize headers.
			$headers = $this->get_csv_util()->read_and_normalize_headers( $lines, $delimiter );

			$taxonomy_format = isset( $config['taxonomy_format'] ) ? (string) $config['taxonomy_format'] : '';
			if ( '' === $taxonomy_format ) {
				Swift_CSV_Ajax_Util::send_error_response( 'Missing taxonomy_format' );
				return null;
			}

			// Detect taxonomy format validation.
			$taxonomy_format_validation = $this->detect_taxonomy_format_validation_or_send_error_and_cleanup(
				$lines,
				$delimiter,
				$headers,
				$taxonomy_format,
				$file_path
			);

			if ( null === $taxonomy_format_validation ) {
				return null;
			}

			// Count total rows.
			$total_rows = $this->get_csv_util()->count_total_rows( $lines );

			return [
				'lines'                      => $lines,
				'delimiter'                  => $delimiter,
				'headers'                    => $headers,
				'taxonomy_format_validation' => $taxonomy_format_validation,
				'total_rows'                 => $total_rows,
			];
		} catch ( Throwable $t ) {
			Swift_CSV_Ajax_Util::send_error_response( 'CSV parser error: ' . $t->getMessage() );
			return null;
		}
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
		$taxonomy_util              = $this->get_taxonomy_util();
		$taxonomy_format_validation = [];
		$first_row_processed        = false;

		// Process first row for format detection.
		$data = [];
		foreach ( $lines as $line ) {
			$row = $this->get_csv_util()->parse_csv_row( $line, $delimiter );
			if ( count( $row ) !== count( $headers ) ) {
				continue; // Skip malformed rows.
			}
			$data[] = $row;

			// Process taxonomies for format detection on first data row only.
			if ( ! $first_row_processed ) {
				foreach ( $headers as $j => $header_name ) {
					$header_name_normalized = strtolower( trim( $header_name ) );

					if ( strpos( $header_name_normalized, 'tax_' ) === 0 ) {
						$taxonomy_name = substr( $header_name_normalized, 4 ); // Remove tax_.

						// Get taxonomy object to validate.
						$taxonomy_obj = get_taxonomy( $taxonomy_name );
						if ( ! $taxonomy_obj ) {
							continue; // Skip invalid taxonomy.
						}

						$meta_value = $row[ $j ] ?? '';
						if ( '' !== $meta_value ) {
							$term_values = [];
							$pipe_parts  = $this->get_csv_util()->split_pipe_separated_values( (string) $meta_value );
							foreach ( $pipe_parts as $pipe_part ) {
								$comma_parts = explode( ',', (string) $pipe_part );
								foreach ( $comma_parts as $comma_part ) {
									$term_values[] = (string) $comma_part;
								}
							}
							$term_values     = array_values( array_filter( array_map( 'trim', (array) $term_values ), 'strlen' ) );
							$format_analysis = $taxonomy_util->analyze_term_values_format( $term_values );

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

		// Validate format consistency.
		foreach ( $taxonomy_format_validation as $taxonomy_name => $validation ) {
			$consistency_result = $taxonomy_util->validate_taxonomy_format_consistency( $taxonomy_format, $validation, $file_path );
			if ( ! $consistency_result['valid'] ) {
				Swift_CSV_Ajax_Util::send_error_response( (string) $consistency_result['error'] );
				return null;
			}
		}

		return $taxonomy_format_validation;
	}

	/**
	 * Get taxonomy utility instance.
	 *
	 * @since 0.9.11
	 * @return Swift_CSV_Import_Taxonomy_Util
	 */
	private function get_taxonomy_util(): Swift_CSV_Import_Taxonomy_Util {
		if ( null === $this->taxonomy_util ) {
			$this->taxonomy_util = new Swift_CSV_Import_Taxonomy_Util();
		}
		return $this->taxonomy_util;
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
