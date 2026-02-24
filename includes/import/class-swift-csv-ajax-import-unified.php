<?php
/**
 * Ajax Import Unified Router
 *
 * Routes import requests to wp-compatible or direct-sql handlers.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax Import Unified Router Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Import_Unified {

	/**
	 * Import log store.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Log_Store
	 */
	private $log_store;

	/**
	 * Import CSV store.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Csv_Store
	 */
	private $csv_store;

	/**
	 * CSV utility.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Csv
	 */
	private $csv_util;

	/**
	 * CSV parser.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Csv_Parser
	 */
	private $csv_parser;

	/**
	 * File processor.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_File_Processor
	 */
	private $file_processor;

	/**
	 * Batch processor.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Batch_Processor
	 */
	private $batch_processor;

	/**
	 * Response manager.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Response_Manager
	 */
	private $response_manager;

	/**
	 * Constructor.
	 *
	 * @since 0.9.8
	 * @param Swift_CSV_Import_Log_Store        $log_store Log store.
	 * @param Swift_CSV_Import_Csv_Store        $csv_store CSV store.
	 * @param Swift_CSV_Import_Csv              $csv_util CSV utility.
	 * @param Swift_CSV_Import_Csv_Parser       $csv_parser CSV parser.
	 * @param Swift_CSV_Import_File_Processor   $file_processor File processor.
	 * @param Swift_CSV_Import_Batch_Processor  $batch_processor Batch processor.
	 * @param Swift_CSV_Import_Response_Manager $response_manager Response manager.
	 */
	public function __construct(
		Swift_CSV_Import_Log_Store $log_store,
		Swift_CSV_Import_Csv_Store $csv_store,
		Swift_CSV_Import_Csv $csv_util,
		Swift_CSV_Import_Csv_Parser $csv_parser,
		Swift_CSV_Import_File_Processor $file_processor,
		Swift_CSV_Import_Batch_Processor $batch_processor,
		Swift_CSV_Import_Response_Manager $response_manager
	) {
		$this->log_store        = $log_store;
		$this->csv_store        = $csv_store;
		$this->csv_util         = $csv_util;
		$this->csv_parser       = $csv_parser;
		$this->file_processor   = $file_processor;
		$this->batch_processor  = $batch_processor;
		$this->response_manager = $response_manager;
	}

	/**
	 * Handle import request by routing to the selected handler.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle(): void {
		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		$import_method = sanitize_key( $_POST['import_method'] ?? 'wp_compatible' );
		if ( '' === $import_method ) {
			$import_method = 'wp_compatible';
		}

		switch ( $import_method ) {
			case 'direct_sql':
				$handler = new Swift_CSV_Ajax_Import_Handler_Direct_SQL();
				$handler->handle();
				return;
			case 'wp_compatible':
			default:
				$handler = new Swift_CSV_Ajax_Import_Handler_WP_Compatible(
					$this->log_store,
					$this->csv_store,
					$this->csv_util,
					$this->csv_parser,
					$this->file_processor,
					$this->batch_processor,
					$this->response_manager
				);
				$handler->handle();
				return;
		}
	}
}
