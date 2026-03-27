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
	 * @var object|null
	 */
	private $csv_util;

	/**
	 * Taxonomy utility instance.
	 *
	 * @since 0.9.8
	 * @var object|null
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
	 * Parse and validate CSV file.
	 *
	 * Extracted from parse_and_validate_csv() for better modularity.
	 *
	 * @since 0.9.8
	 * @param string $file_path Temporary file path for cleanup.
	 * @param array  $config Import configuration.
	 * @return array|null Parsed CSV data or null on error (sends JSON response).
	 */
	public function parse_and_validate_csv_file( string $file_path, array $config ): ?array {
		Swift_CSV_Ajax_Util::set_stage( 'csv_parser:parse_and_validate_csv' );

		try {
			$handle = fopen( $file_path, 'rb' );
			if ( false === $handle ) {
				Swift_CSV_Ajax_Util::send_error_response( 'CSV parsing failed: unable to open file' );
				return null;
			}

			$first_line = $this->read_next_logical_line( $handle );
			if ( null === $first_line ) {
				fclose( $handle );
				Swift_CSV_Ajax_Util::send_error_response( 'CSV parsing failed: empty file' );
				return null;
			}

			// Detect CSV delimiter from first line.
			$delimiter = $this->get_csv_util()->detect_csv_delimiter( [ $first_line ] );

			// Read and normalize headers.
			$headers = array_map(
				[ $this->get_csv_util(), 'normalize_field_name' ],
				str_getcsv( $first_line, $delimiter )
			);

			$taxonomy_format = isset( $config['taxonomy_format'] ) ? (string) $config['taxonomy_format'] : '';
			if ( '' === $taxonomy_format ) {
				fclose( $handle );
				Swift_CSV_Ajax_Util::send_error_response( 'Missing taxonomy_format' );
				return null;
			}

			$taxonomy_format_validation = [];
			$total_rows                 = 0;
			$first_data_row             = null;

			try {
				while ( null !== ( $line = $this->read_next_logical_line( $handle ) ) ) {
					if ( '' === trim( $line ) ) {
						continue;
					}

					++$total_rows;

					if ( null === $first_data_row ) {
						$first_data_row = $this->get_csv_util()->parse_csv_row( $line, $delimiter );
					}
				}
			} finally {
				fclose( $handle );
			}

			$taxonomy_format_validation = $this->analyze_taxonomy_format_validation_from_first_row(
				(array) $first_data_row,
				$headers,
				$taxonomy_format,
				$file_path
			);

			if ( null === $taxonomy_format_validation ) {
				return null;
			}

			return [
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
	 * Read current batch lines from CSV file.
	 *
	 * @since 0.9.8
	 * @param string $file_path Temporary file path.
	 * @param int    $start_row Start row offset.
	 * @param int    $batch_size Batch size.
	 * @return array<int, string>
	 */
	public function read_batch_lines( string $file_path, int $start_row, int $batch_size ): array {
		$handle = fopen( $file_path, 'rb' );
		if ( false === $handle ) {
			return [];
		}

		$logical_row_index = -1;
		$batch_lines       = [];
		$target_end_row    = $start_row + max( 0, $batch_size );

		try {
			while ( null !== ( $line = $this->read_next_logical_line( $handle ) ) ) {
				++$logical_row_index;

				if ( 0 === $logical_row_index ) {
					continue;
				}

				$data_row_index = $logical_row_index - 1;
				if ( $data_row_index >= $start_row && $data_row_index < $target_end_row ) {
					$batch_lines[] = $line;
				}

				if ( $data_row_index + 1 >= $target_end_row ) {
					break;
				}
			}
		} finally {
			fclose( $handle );
		}

		return $batch_lines;
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
	private function analyze_taxonomy_format_validation_from_first_row(
		array $row,
		array $headers,
		string $taxonomy_format,
		string $file_path
	): ?array {
		$taxonomy_util              = $this->get_taxonomy_util();
		$taxonomy_format_validation = [];

		if ( count( $row ) !== count( $headers ) ) {
			return $taxonomy_format_validation;
		}

		foreach ( $headers as $j => $header_name ) {
			$header_name_normalized = strtolower( trim( $header_name ) );

			if ( strpos( $header_name_normalized, 'tax_' ) !== 0 ) {
				continue;
			}

			$taxonomy_name = substr( $header_name_normalized, 4 );
			$taxonomy_obj  = get_taxonomy( $taxonomy_name );
			if ( ! $taxonomy_obj ) {
				continue;
			}

			$meta_value = $row[ $j ] ?? '';
			if ( '' === $meta_value ) {
				continue;
			}

			$term_values = [];
			$pipe_parts  = $this->get_csv_util()->split_pipe_separated_values( (string) $meta_value );
			foreach ( $pipe_parts as $pipe_part ) {
				$comma_parts = explode( ',', (string) $pipe_part );
				foreach ( $comma_parts as $comma_part ) {
					$term_values[] = (string) $comma_part;
				}
			}

			$term_values = array_values( array_filter( array_map( 'trim', (array) $term_values ), 'strlen' ) );
			if ( empty( $term_values ) ) {
				continue;
			}

			$format_analysis                              = $taxonomy_util->analyze_term_values_format( $term_values );
			$taxonomy_format_validation[ $taxonomy_name ] = array_merge(
				$format_analysis,
				[
					'sample_values' => $term_values,
					'taxonomy_name' => $taxonomy_name,
				]
			);
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
	 * Read next logical CSV line while preserving quoted newlines.
	 *
	 * @since 0.9.8
	 * @param resource $handle Open file handle.
	 * @return string|null
	 */
	private function read_next_logical_line( $handle ): ?string {
		$current_line = '';
		$in_quotes    = false;

		while ( false !== ( $line = fgets( $handle ) ) ) {
			$line        = str_replace( [ "\r\n", "\r" ], "\n", $line );
			$line        = rtrim( $line, "\n" );
			$quote_count = substr_count( $line, '"' );

			if ( $in_quotes ) {
				$current_line .= "\n" . $line;
			} else {
				$current_line = $line;
			}

			if ( 1 === $quote_count % 2 ) {
				$in_quotes = ! $in_quotes;
			}

			if ( ! $in_quotes ) {
				return $current_line;
			}
		}

		return '' !== $current_line ? $current_line : null;
	}

	/**
	 * Get taxonomy utility instance.
	 *
	 * @since 0.9.8
	 * @return object
	 */
	private function get_taxonomy_util(): object {
		if ( null === $this->taxonomy_util ) {
			$this->taxonomy_util = new Swift_CSV_Import_Taxonomy_Util();
		}
		return $this->taxonomy_util;
	}

	/**
	 * Get CSV utility instance.
	 *
	 * @since 0.9.8
	 * @return object
	 */
	private function get_csv_util(): object {
		if ( null === $this->csv_util ) {
			$this->csv_util = new Swift_CSV_Import_Csv();
		}
		return $this->csv_util;
	}
}
