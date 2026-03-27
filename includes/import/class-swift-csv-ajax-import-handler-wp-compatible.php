<?php
/**
 * WP Compatible AJAX Import Handler
 *
 * Contains the request-scoped import logic for the WP compatible import method.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include required classes for bulk processing
require_once __DIR__ . '/class-swift-csv-import-wp-compatible.php';
require_once __DIR__ . '/class-swift-csv-import-wp-compatible-enhanced.php';
require_once __DIR__ . '/swift-csv-bulk-hooks.php';

/**
 * WP Compatible AJAX Import Handler Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Import_Handler_WP_Compatible {

	/**
	 * Handle WP compatible import with bulk processing support.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle(): void {
		// Check if bulk processing is available and enabled
		$use_bulk = $this->should_use_bulk_processing();

		// Debug logging to track processor selection
		error_log( '[Swift CSV] Bulk processing available: ' . ( class_exists( 'Swift_CSV_Import_WP_Compatible_Enhanced' ) ? 'yes' : 'no' ) );
		error_log( '[Swift CSV] Bulk processing enabled: ' . ( $use_bulk ? 'yes' : 'no' ) );

		if ( $use_bulk && class_exists( 'Swift_CSV_Import_WP_Compatible_Enhanced' ) ) {
			$importer = new Swift_CSV_Import_WP_Compatible_Enhanced();
			error_log( '[Swift CSV] Using enhanced bulk processor for import' );
		} else {
			$importer = new Swift_CSV_Import_WP_Compatible();
			error_log( '[Swift CSV] Using standard row-by-row processor for import' );
		}

		$importer->import();
	}

	/**
	 * Determine if bulk processing should be used.
	 *
	 * @since 0.9.9
	 * @return bool True if bulk processing should be used.
	 */
	private function should_use_bulk_processing(): bool {
		// Check if bulk processing is supported
		if ( ! function_exists( 'swift_csv_is_bulk_processing_supported' ) ) {
			return false;
		}

		if ( ! swift_csv_is_bulk_processing_supported() ) {
			return false;
		}

		// Get import configuration to determine if bulk should be used
		$config = $this->get_import_config();

		return apply_filters( 'swift_csv_use_bulk_processing', true, $config );
	}

	/**
	 * Get import configuration for bulk processing decision.
	 *
	 * @since 0.9.9
	 * @return array Import configuration.
	 */
	private function get_import_config(): array {
		$config = [];

		// Get basic configuration from POST data
		$config['post_type']       = $_POST['swift_csv_import_post_type'] ?? 'post';
		$config['update_existing'] = isset( $_POST['swift_csv_import_update_existing'] );
		$config['dry_run']         = isset( $_POST['swift_csv_import_dry_run'] );
		$config['file_size']       = $_FILES['import_file']['size'] ?? 0;

		return $config;
	}
}
