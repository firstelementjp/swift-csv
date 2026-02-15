<?php
/**
 * Import Environment Manager for Swift CSV
 *
 * Handles environment preparation operations for CSV import.
 * Extracted from Swift_CSV_Ajax_Import for better separation of concerns.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles environment preparation operations for CSV import.
 *
 * This class is responsible for:
 * - Import environment setup
 * - Configuration validation
 * - Nonce verification
 * - File content reading
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Import_Environment_Manager {

	/**
	 * Constructor.
	 *
	 * @since 0.9.8
	 */
	public function __construct() {
		// Initialize dependencies
	}

	/**
	 * Prepare import environment and validate parameters.
	 *
	 * Extracted from prepare_import_environment() for better modularity.
	 *
	 * @since 0.9.8
	 * @return array|null Import configuration or null on error (sends JSON response).
	 */
	public function prepare_import_environment(): ?array {
		if ( ! $this->verify_nonce_or_send_error_and_cleanup() ) {
			return null;
		}

		// Handle file upload directly
		if ( ! isset( $_FILES['csv_file'] ) ) {
			Swift_CSV_Helper::send_error_response_and_return_null( 'No file uploaded', '' );
			return null;
		}

		$file = $_FILES['csv_file'];

		// Validate file
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			Swift_CSV_Helper::send_error_response_and_return_null( 'Upload error: ' . $file['error'], '' );
			return null;
		}

		// Read CSV directly from uploaded file
		$csv_content = (string) file_get_contents( $file['tmp_name'] );
		$csv_content = str_replace( [ "\r\n", "\r" ], "\n", $csv_content ); // Normalize line endings

		// Create temp directory and file path
		$temp_dir  = Swift_CSV_Helper::create_temp_directory();
		$temp_file = Swift_CSV_Helper::generate_temp_file_path( $temp_dir );

		// Save uploaded file
		if ( ! Swift_CSV_Helper::save_uploaded_file( $file, $temp_file ) ) {
			Swift_CSV_Helper::send_error_response_and_return_null( 'Failed to save file', '' );
			return null;
		}

		// Validate and extract parameters
		$start_row       = isset( $_POST['start_row'] ) ? intval( $_POST['start_row'] ) : 0;
		$batch_size      = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 10;
		$post_type       = sanitize_text_field( wp_unslash( $_POST['post_type'] ?? 'post' ) );
		$update_existing = sanitize_text_field( wp_unslash( $_POST['update_existing'] ?? 'no' ) );
		$taxonomy_format = sanitize_text_field( wp_unslash( $_POST['taxonomy_format'] ?? 'name' ) );
		$dry_run         = isset( $_POST['dry_run'] ) && 'true' === $_POST['dry_run'];

		// Validate post type
		if ( ! post_type_exists( $post_type ) ) {
			Swift_CSV_Helper::send_error_response_and_return_null( 'Invalid post type: ' . $post_type, $temp_file );
			return null;
		}

		return [
			'file_path'       => $temp_file,
			'start_row'       => $start_row,
			'batch_size'      => $batch_size,
			'post_type'       => $post_type,
			'update_existing' => $update_existing,
			'taxonomy_format' => $taxonomy_format,
			'dry_run'         => $dry_run,
			'csv_content'     => $csv_content,
		];
	}

	/**
	 * Verify nonce for AJAX request.
	 *
	 * @since 0.9.8
	 * @return bool True if nonce is valid.
	 */
	private function verify_nonce_or_send_error_and_cleanup(): bool {
		$nonce     = (string) ( $_POST['nonce'] ?? '' );
		$file_path = sanitize_text_field( wp_unslash( $_POST['file_path'] ?? '' ) );

		if ( ! Swift_CSV_Helper::verify_nonce( $nonce ) ) {
			Swift_CSV_Helper::send_security_error( $file_path );
			return false;
		}

		return true;
	}

	/**
	 * Read uploaded CSV content from request.
	 *
	 * @since 0.9.8
	 * @param string $file_path Temporary file path for cleanup.
	 * @return string|null CSV content or null on error (sends JSON response).
	 */
	public function read_uploaded_csv_content_or_send_error_and_cleanup( string $file_path ): ?string {
		// Handle file upload directly
		if ( ! isset( $_FILES['csv_file'] ) ) {
			return Swift_CSV_Helper::send_error_response_and_return_null( 'No file uploaded', $file_path );
		}

		$file = $_FILES['csv_file'];

		// Validate file
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return Swift_CSV_Helper::send_error_response_and_return_null( 'Upload error: ' . $file['error'], $file_path );
		}

		// Read CSV directly from uploaded file
		$csv_content = (string) file_get_contents( $file['tmp_name'] );
		$csv_content = str_replace( [ "\r\n", "\r" ], "\n", $csv_content ); // Normalize line endings

		return $csv_content;
	}

	/**
	 * Get allowed post fields.
	 *
	 * @since 0.9.8
	 * @return array Allowed post fields.
	 */
	public function get_allowed_post_fields(): array {
		return [
			'post_title',
			'post_content',
			'post_excerpt',
			'post_status',
			'post_name',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_modified',
			'post_modified_gmt',
			'post_parent',
			'menu_order',
			'guid',
			'comment_status',
			'ping_status',
		];
	}
}
