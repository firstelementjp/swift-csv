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
}
