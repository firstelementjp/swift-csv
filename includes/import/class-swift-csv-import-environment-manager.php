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
		// Initialize dependencies.
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

		// Note: File upload is handled by Swift_CSV_Import_File_Processor
		// to avoid duplicate upload attempts.

		// Get uploaded file path from file-processor result.
		$file_processor = new Swift_CSV_Import_File_Processor();
		$file_result    = $file_processor->handle_upload();
		if ( null === $file_result ) {
			return null;
		}
		$uploaded_file = [
			'file' => $file_result['file_path'],
		];

		// Read CSV from the uploaded file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$csv_content = (string) file_get_contents( $uploaded_file['file'] );
		$csv_content = str_replace( [ "\r\n", "\r" ], "\n", $csv_content ); // Normalize line endings.

		// Store the uploaded file path for cleanup.
		$uploaded_file_path = $uploaded_file['file'];

		// Note: File processing is handled by Swift_CSV_Import_File_Processor
		// to avoid duplicate file generation.

		// Validate and extract parameters.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$start_row       = isset( $_POST['start_row'] ) ? intval( $_POST['start_row'] ) : 0;
		$batch_size      = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 10;
		$post_type       = sanitize_text_field( wp_unslash( $_POST['post_type'] ?? 'post' ) );
		$update_existing = sanitize_text_field( wp_unslash( $_POST['update_existing'] ?? 'no' ) );
		$taxonomy_format = sanitize_text_field( wp_unslash( $_POST['taxonomy_format'] ?? 'name' ) );
		$dry_run         = isset( $_POST['dry_run'] ) && in_array( (string) $_POST['dry_run'], [ '1', 'true' ], true );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Validate post type.
		if ( ! post_type_exists( $post_type ) ) {
			Swift_CSV_Helper::send_error_response_and_return_null( 'Invalid post type: ' . $post_type, '' );
			return null;
		}

		return [
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
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$nonce     = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$file_path = isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! Swift_CSV_Helper::verify_nonce( $nonce ) ) {
			Swift_CSV_Helper::send_security_error( $file_path );
			return false;
		}

		return true;
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
