<?php
/**
 * Import Response Manager for Swift CSV
 *
 * Handles response processing operations for CSV import.
 * Extracted from Swift_CSV_Ajax_Import for better separation of concerns.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles response processing operations for CSV import.
 *
 * This class is responsible for:
 * - Progress response formatting
 * - Error response handling
 * - Cleanup operations
 * - JSON response management
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Import_Response_Manager {

	/**
	 * Constructor.
	 *
	 * @since 0.9.8
	 */
	public function __construct() {
		// Initialize dependencies
	}

	/**
	 * Send import progress response.
	 *
	 * Extracted from send_import_progress_response() for better modularity.
	 *
	 * @since 0.9.8
	 * @param int   $start_row Starting row number.
	 * @param int   $processed Processed count.
	 * @param int   $total_rows Total rows.
	 * @param int   $errors Error count.
	 * @param int   $created Created count.
	 * @param int   $updated Updated count.
	 * @param int   $previous_created Previous cumulative created.
	 * @param int   $previous_updated Previous cumulative updated.
	 * @param int   $previous_errors Previous cumulative errors.
	 * @param bool  $dry_run Dry run flag.
	 * @param array $dry_run_log Dry run log.
	 * @param array $dry_run_details Detailed dry run results.
	 * @return void
	 */
	public function send_import_progress_response(
		int $start_row,
		int $processed,
		int $total_rows,
		int $errors,
		int $created,
		int $updated,
		int $previous_created,
		int $previous_updated,
		int $previous_errors,
		bool $dry_run,
		array $dry_run_log,
		array $dry_run_details
	): void {
		$next_row = $start_row + $processed; // Use actual processed count
		$continue = $next_row < $total_rows;

		wp_send_json(
			[
				'success'            => true,
				'processed'          => $next_row,
				'total'              => $total_rows,
				'batch_processed'    => $processed,
				'batch_errors'       => $errors,
				'created'            => $created,
				'updated'            => $updated,
				'errors'             => $errors,
				'cumulative_created' => $previous_created + $created,
				'cumulative_updated' => $previous_updated + $updated,
				'cumulative_errors'  => $previous_errors + $errors,
				'progress'           => round( ( $next_row / $total_rows ) * 100, 2 ),
				'continue'           => $continue,
				'dry_run'            => $dry_run,
				'dry_run_log'        => $dry_run_log,
				'dry_run_details'    => $dry_run_details,
			]
		);
	}

	/**
	 * Send error response and cleanup.
	 *
	 * @since 0.9.8
	 * @param string      $message Error message.
	 * @param string|null $file_path Optional file path to cleanup.
	 * @return void
	 */
	public function send_error_and_cleanup( string $message, string $file_path = null ): void {
		if ( ! empty( $file_path ) ) {
			@unlink( $file_path );
		}
		Swift_CSV_Helper::send_error_response( $message );
	}

	/**
	 * Cleanup temporary file when import is complete.
	 *
	 * @since 0.9.8
	 * @param bool   $continue Whether import continues.
	 * @param string $file_path Temporary file path.
	 * @return void
	 */
	public function cleanup_temp_file_if_complete( bool $continue, string $file_path ): void {
		if ( ! $continue && $file_path && file_exists( $file_path ) ) {
			unlink( $file_path );
		}
	}

	/**
	 * Get cumulative counts.
	 *
	 * @since 0.9.8
	 * @return array Cumulative counts.
	 */
	public function get_cumulative_counts(): array {
		// Get cumulative counts from POST data (AJAX request)
		$previous_created = isset( $_POST['cumulative_created'] ) ? intval( $_POST['cumulative_created'] ) : 0;
		$previous_updated = isset( $_POST['cumulative_updated'] ) ? intval( $_POST['cumulative_updated'] ) : 0;
		$previous_errors  = isset( $_POST['cumulative_errors'] ) ? intval( $_POST['cumulative_errors'] ) : 0;

		return [
			'created' => $previous_created,
			'updated' => $previous_updated,
			'errors'  => $previous_errors,
		];
	}
}
