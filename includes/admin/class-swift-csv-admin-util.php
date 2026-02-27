<?php
/**
 * Admin utility functions
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin utility functions
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Admin_Util {

	/**
	 * Get PHP upload limits
	 *
	 * @since 0.9.8
	 * @return array Upload limit information
	 */
	public static function get_upload_limits() {
		$upload_max = ini_get( 'upload_max_filesize' );
		$post_max   = ini_get( 'post_max_size' );

		// Convert to bytes.
		$upload_max_bytes = self::parse_ini_size( $upload_max );
		$post_max_bytes   = self::parse_ini_size( $post_max );

		// Get the smaller limit.
		$max_file_size       = min( $upload_max_bytes, $post_max_bytes );
		$max_file_size_human = self::format_bytes( $max_file_size );

		return [
			'upload_max_filesize'   => $upload_max,
			'post_max_size'         => $post_max,
			'effective_limit'       => $max_file_size,
			'effective_limit_human' => $max_file_size_human,
		];
	}

	/**
	 * Parse PHP ini size string to bytes
	 *
	 * @since 0.9.8
	 * @param string $size Size string (e.g., "2M", "8M").
	 * @return int Size in bytes.
	 */
	private static function parse_ini_size( $size ) {
		$unit  = strtoupper( substr( (string) $size, -1 ) );
		$value = (int) substr( (string) $size, 0, -1 );

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
	 * Format bytes to human readable format
	 *
	 * @since 0.9.8
	 * @param int $bytes Bytes.
	 * @return string Formatted size.
	 */
	private static function format_bytes( $bytes ) {
		$bytes = (int) $bytes;

		if ( $bytes >= 1024 * 1024 * 1024 ) {
			return round( $bytes / 1024 / 1024 / 1024, 1 ) . 'GB';
		} elseif ( $bytes >= 1024 * 1024 ) {
			return round( $bytes / 1024 / 1024, 1 ) . 'MB';
		} elseif ( $bytes >= 1024 ) {
			return round( $bytes / 1024, 1 ) . 'KB';
		} else {
			return $bytes . 'B';
		}
	}
}
