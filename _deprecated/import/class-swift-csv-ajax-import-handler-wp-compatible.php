<?php
/**
 * Ajax Import Handler (WP Compatible)
 *
 * Handles asynchronous CSV import with WordPress-compatible processing.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax Import Handler (WP Compatible) Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Import_Handler_WP_Compatible extends Swift_CSV_Ajax_Import_Handler_Base {

	/**
	 * Handle import request.
	 *
	 * @since 0.9.10
	 * @return void
	 */
	public function handle(): void {
		$importer = new Swift_CSV_Import_WP_Compatible(
			$this->log_store,
			$this->csv_store,
			$this->csv_util,
			$this->csv_parser,
			$this->file_processor,
			$this->batch_processor,
			$this->response_manager,
			$this->request_parser
		);
		$importer->import();
	}
}
