<?php
/**
 * CSV parsing utilities for Swift CSV import.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV parsing utilities used by the import handler.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_Import_Csv {
	/**
	 * Parse CSV content line by line to handle quoted fields with newlines.
	 *
	 * @since 0.9.0
	 * @param string $csv_content CSV content.
	 * @return array<int, string>
	 */
	public function parse_csv_lines_preserving_quoted_newlines( string $csv_content ): array {
		return Swift_CSV_Helper::parse_csv_lines_preserving_quoted_newlines( $csv_content );
	}

	/**
	 * Detect CSV delimiter from first line.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines.
	 * @return string
	 */
	public function detect_csv_delimiter( array $lines ): string {
		return Swift_CSV_Helper::detect_csv_delimiter( $lines );
	}

	/**
	 * Read CSV header row and normalize it.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines (will consume the first line).
	 * @param string             $delimiter CSV delimiter.
	 * @return array<int, string>
	 */
	public function read_and_normalize_headers( array &$lines, string $delimiter ): array {
		$headers = str_getcsv( (string) array_shift( $lines ), $delimiter );
		// Normalize headers - remove BOM and control characters
		$headers = array_map(
			function ( $header ): string {
				$header = (string) $header;
				// Remove BOM (UTF-8 BOM is \xEF\xBB\xBF)
				$header = preg_replace( '/^\xEF\xBB\xBF/', '', $header );
				// Remove other control characters
				return preg_replace( '/[\x00-\x1F\x7F]/u', '', trim( $header ?? '' ) );
			},
			$headers
		);

		return $headers;
	}

	/**
	 * Parse one CSV row.
	 *
	 * @since 0.9.0
	 * @param string $line CSV line.
	 * @param string $delimiter CSV delimiter.
	 * @return array<int, string>
	 */
	public function parse_csv_row( string $line, string $delimiter ): array {
		return Swift_CSV_Helper::parse_csv_row( $line, $delimiter );
	}

	/**
	 * Check if a CSV line is empty.
	 *
	 * @since 0.9.0
	 * @param string $line CSV line.
	 * @return bool
	 */
	public function is_empty_csv_line( string $line ): bool {
		return Swift_CSV_Helper::is_empty_csv_line( $line );
	}

	/**
	 * Count actual data rows (exclude empty lines).
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines.
	 * @return int
	 */
	public function count_total_rows( array $lines ): int {
		return Swift_CSV_Helper::count_data_rows( $lines );
	}
}
