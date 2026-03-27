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
		$lines        = [];
		$current_line = '';
		$in_quotes    = false;

		foreach ( explode( "\n", $csv_content ) as $line ) {
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
				$lines[]      = $current_line;
				$current_line = '';
			}
		}

		return $lines;
	}

	/**
	 * Split a pipe-separated string respecting backslash escaping.
	 *
	 * @since 0.9.8
	 * @param string $value Raw value from CSV.
	 * @return array<int, string>
	 */
	public function split_pipe_separated_values( string $value ): array {
		if ( '' === $value ) {
			return [];
		}

		$parts  = [];
		$buffer = '';
		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $value[ $i ];
			if ( '\\' === $char ) {
				$next = ( $i + 1 ) < $length ? $value[ $i + 1 ] : '';
				if ( '|' === $next || '\\' === $next ) {
					$buffer .= $next;
					++$i;
					continue;
				}
				$buffer .= '\\';
				continue;
			}
			if ( '|' === $char ) {
				$parts[] = $buffer;
				$buffer  = '';
				continue;
			}
			$buffer .= $char;
		}

		$parts[] = $buffer;
		return $parts;
	}

	/**
	 * Normalize field name by removing BOM and control characters.
	 *
	 * @since 0.9.8
	 * @param string $name Field name.
	 * @return string
	 */
	public function normalize_field_name( string $name ): string {
		$name = trim( (string) $name );
		$name = preg_replace( '/^\xEF\xBB\xBF/', '', $name );
		$name = preg_replace( '/[\x00-\x1F\x7F]/', '', $name );
		return trim( (string) $name );
	}

	/**
	 * Detect CSV delimiter from first line.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines.
	 * @return string
	 */
	public function detect_csv_delimiter( array $lines ): string {
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
	 * Read CSV header row and normalize it.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines (will consume the first line).
	 * @param string             $delimiter CSV delimiter.
	 * @return array<int, string>
	 */
	public function read_and_normalize_headers( array &$lines, string $delimiter ): array {
		$headers = str_getcsv( (string) array_shift( $lines ), $delimiter, '"', '' );
		// Normalize headers - remove BOM and control characters.
		$headers = array_map(
			function ( $header ): string {
				$header = (string) $header;
				// Remove BOM (UTF-8 BOM is \xEF\xBB\xBF).
				$header = preg_replace( '/^\xEF\xBB\xBF/', '', $header );
				// Remove other control characters.
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
		return str_getcsv( $line, $delimiter, '"', '' );
	}

	/**
	 * Check if a CSV line is empty.
	 *
	 * @since 0.9.0
	 * @param string $line CSV line.
	 * @return bool
	 */
	public function is_empty_csv_line( string $line ): bool {
		return empty( trim( $line ) );
	}

	/**
	 * Count actual data rows (exclude empty lines).
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines.
	 * @return int
	 */
	public function count_total_rows( array $lines ): int {
		$total_rows = 0;
		foreach ( $lines as $line ) {
			if ( ! empty( trim( $line ) ) ) {
				++$total_rows;
			}
		}
		return $total_rows;
	}
}
