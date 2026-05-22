<?php
/**
 * Ajax Import Handler (Direct SQL)
 *
 * Placeholder for future high-speed import implementation.
 *
 * @since 0.9.8
 * @package FE_CSV_Import_Export
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax Import Handler (Direct SQL) Class
 *
 * @since 0.9.8
 * @package FE_CSV_Import_Export
 * @deprecated 0.9.8 Migrated to FE CSV Import & Export Pro. Use fe_csv_import_export_import_direct_sql hook instead.
 */
class Swift_CSV_Ajax_Import_Handler_Direct_SQL extends Swift_CSV_Ajax_Import_Handler_Base {

	/**
	 * Handle import request.
	 *
	 * @since 0.9.10
	 * @return void
	 */
	public function handle(): void {
		$importer = new Swift_CSV_Import_Direct_SQL(
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
