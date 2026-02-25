<?php
/**
 * File utilities for Swift CSV.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File utilities.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_File_Util {
	/**
	 * Create temporary directory for CSV uploads.
	 *
	 * @since 0.9.0
	 * @return string Temporary directory path.
	 */
	public static function create_temp_directory(): string {
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
	public static function generate_temp_file_path( string $temp_dir ): string {
		return $temp_dir . '/ajax-import-' . time() . '.csv';
	}

	/**
	 * Cleanup old temporary files.
	 *
	 * Removes all ajax-import temporary files except the current one.
	 *
	 * @since 0.9.8
	 * @param string $temp_dir Temporary directory path.
	 * @param string $current_file Optional current file to preserve.
	 * @return void
	 */
	public static function cleanup_old_temp_files( string $temp_dir, string $current_file = '' ): void {
		if ( ! is_dir( $temp_dir ) ) {
			return;
		}

		$files = scandir( $temp_dir );

		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			$file_path = $temp_dir . '/' . $file;

			if ( strpos( $file, 'ajax-import-' ) !== 0 ) {
				continue;
			}

			if ( $current_file && basename( $current_file ) === $file ) {
				continue;
			}

			if ( is_file( $file_path ) ) {
				wp_delete_file( $file_path );
			}
		}
	}
}
