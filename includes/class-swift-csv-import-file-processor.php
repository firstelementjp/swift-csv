<?php
/**
 * Import File Processor for Swift CSV
 *
 * Handles file upload, CSV parsing, and validation operations.
 * Extracted from Swift_CSV_Ajax_Import for better separation of concerns.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles file processing operations for CSV import.
 *
 * This class is responsible for:
 * - File upload validation
 * - CSV content parsing
 * - Temporary file management
 * - Error handling for file operations
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Import_File_Processor {

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

	/**
	 * Handle file upload and processing.
	 *
	 * Processes uploaded CSV file with validation and temporary storage.
	 * Extracted from upload_handler() for better separation of concerns.
	 *
	 * @since 0.9.8
	 * @param array $upload_data File upload data from $_FILES.
	 * @return array|false File path on success, false on failure.
	 */
	public function process_upload( array $upload_data ) {
		// Validate uploaded file
		$file_validation = Swift_CSV_Helper::validate_upload_file( $upload_data );
		if ( ! $file_validation['valid'] ) {
			$this->send_error_response( $file_validation['error'] );
			return false;
		}

		// Validate file size
		$size_validation = Swift_CSV_Helper::validate_file_size( $upload_data );
		if ( ! $size_validation['valid'] ) {
			$this->send_error_response( $size_validation['error'] );
			return false;
		}

		// Create temp directory and file path
		$temp_dir  = Swift_CSV_Helper::create_temp_directory();
		$temp_file = Swift_CSV_Helper::generate_temp_file_path( $temp_dir );

		// Save uploaded file
		if ( Swift_CSV_Helper::save_uploaded_file( $upload_data, $temp_file ) ) {
			return [ 'file_path' => $temp_file ];
		} else {
			$this->send_error_response( 'Failed to save file' );
			return false;
		}
	}

	/**
	 * Validate uploaded file.
	 *
	 * @since 0.9.8
	 * @param array $file File data from $_FILES.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_file( array $file ): bool {
		$file_validation = Swift_CSV_Helper::validate_upload_file( $file );
		return $file_validation['valid'];
	}

	/**
	 * Parse CSV content.
	 *
	 * @since 0.9.8
	 * @param string $content CSV content.
	 * @return array|false Parsed data or false on failure.
	 */
	public function parse_csv_content( string $content ) {
		return $this->get_csv_util()->parse_csv_content( $content );
	}

	/**
	 * Send error response.
	 *
	 * @since 0.9.8
	 * @param string $message Error message.
	 * @return void
	 */
	private function send_error_response( string $message ): void {
		Swift_CSV_Helper::send_error_response( $message );
	}
}
