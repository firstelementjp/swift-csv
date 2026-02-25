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
	 * Escape a value for pipe-separated serialization.
	 *
	 * This escapes backslash and pipe so that values can be joined with '|'
	 * without losing literal pipe characters.
	 *
	 * @since 0.9.8
	 * @param string $value Value.
	 * @return string Escaped value.
	 */
	public static function escape_pipe_separated_value( string $value ): string {
		$value = str_replace( '\\', '\\\\', $value );
		return str_replace( '|', '\\|', $value );
	}

	/**
	 * Split a pipe-separated string respecting backslash escaping.
	 *
	 * Splits only on unescaped '|' and unescapes backslash sequences.
	 *
	 * @since 0.9.8
	 * @param string $value Raw value from CSV.
	 * @return array<int, string> Split values.
	 */
	public static function split_pipe_separated_values( string $value ): array {
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
	 * Join values into a pipe-separated string with escaping.
	 *
	 * @since 0.9.8
	 * @param array<int, string> $values Values.
	 * @return string Joined string.
	 */
	public static function join_pipe_separated_values( array $values ): string {
		$escaped_values = [];
		foreach ( $values as $value ) {
			$escaped_values[] = self::escape_pipe_separated_value( (string) $value );
		}
		return implode( '|', $escaped_values );
	}
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
			// Count quotes to determine if we're inside a quoted field.
			$quote_count = substr_count( $line, '"' );

			if ( $in_quotes ) {
				$current_line .= "\n" . $line;
			} else {
				$current_line = $line;
			}

			// Toggle quote state (odd number of quotes means we're inside quotes).
			if ( 1 === $quote_count % 2 ) {
				$in_quotes = ! $in_quotes;
			}

			// Only add line if we're not inside quotes.
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
}
