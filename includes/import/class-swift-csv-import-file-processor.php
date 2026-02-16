<?php
/**
 * Import File Processor for Swift CSV
 *
 * Handles file processing operations for CSV import.
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
 * - Temporary file management
 * - File path generation
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Import_File_Processor {

	/**
	 * Constructor.
	 *
	 * @since 0.9.8
	 */
	public function __construct() {
		// Initialize dependencies
	}

	/**
	 * Handle file upload.
	 *
	 * Processes uploaded CSV file with validation and temporary storage.
	 * Extracted from upload_handler() for better separation of concerns.
	 *
	 * @since 0.9.8
	 * @return array{file_path:string}|null File path or null on error.
	 */
	public function handle_upload(): ?array {
		// Verify nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$nonce = wp_unslash( $_POST['nonce'] ?? '' );
		if ( ! Swift_CSV_Helper::verify_nonce( $nonce ) ) {
			Swift_CSV_Helper::send_security_error();
			return null;
		}

		// Handle file upload securely using wp_handle_upload.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $_FILES['csv_file'] ) ) {
			// For batch requests (start_row > 0), use existing file
			$start_row = isset( $_POST['start_row'] ) ? intval( $_POST['start_row'] ) : 0;
			if ( $start_row > 0 ) {
				// Return existing temp file path for batch processing
				$temp_dir = Swift_CSV_Helper::create_temp_directory();
				$files    = scandir( $temp_dir );
				foreach ( $files as $file ) {
					if ( strpos( $file, 'ajax-import-' ) === 0 ) {
						return [ 'file_path' => $temp_dir . '/' . $file ];
					}
				}
				Swift_CSV_Helper::send_error_response( 'No existing file found for batch processing' );
				return null;
			} else {
				Swift_CSV_Helper::send_error_response( 'No file uploaded' );
				return null;
			}
		}

		// Use WordPress built-in file upload handler for security.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$uploaded_file = wp_handle_upload( $_FILES['csv_file'], [ 'test_form' => false ] );

		if ( isset( $uploaded_file['error'] ) ) {
			Swift_CSV_Helper::send_error_response( 'Upload error: ' . $uploaded_file['error'] );
			return null;
		}

		// Create temp directory and file path.
		$temp_dir  = Swift_CSV_Helper::create_temp_directory();
		$temp_file = Swift_CSV_Helper::generate_temp_file_path( $temp_dir );

		// Cleanup old temporary files only on first upload.
		$start_row = isset( $_POST['start_row'] ) ? intval( $_POST['start_row'] ) : 0;
		if ( 0 === $start_row ) {
			Swift_CSV_Helper::cleanup_old_temp_files( $temp_dir, $temp_file );
		}

		// Copy uploaded file to temp location.
		if ( ! copy( $uploaded_file['file'], $temp_file ) ) {
			Swift_CSV_Helper::send_error_response( 'Failed to save file' );
			return null;
		}

		return [ 'file_path' => $temp_file ];
	}

	/**
	 * Validate uploaded file.
	 *
	 * @since 0.9.8
	 * @param array $file Uploaded file data.
	 * @return array{valid:bool,error:string|null} Validation result.
	 */
	private function validate_file( array $file ): array {
		// Placeholder implementation
		return [
			'valid' => false,
			'error' => 'Not implemented',
		];
	}

	/**
	 * Create temporary file path.
	 *
	 * @since 0.9.8
	 * @return string Temporary file path.
	 */
	private function create_temp_file_path(): string {
		// Placeholder implementation
		return '';
	}

	/**
	 * Save uploaded file.
	 *
	 * @since 0.9.8
	 * @param array  $file Uploaded file data.
	 * @param string $temp_file Temporary file path.
	 * @return bool Success status.
	 */
	private function save_file( array $file, string $temp_file ): bool {
		// Placeholder implementation
		return false;
	}
}
