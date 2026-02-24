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
	 * @var Swift_CSV_Import_Log_Store|null
	 */
	private $log_store;

	/**
	 * Import CSV store.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Csv_Store|null
	 */
	private $csv_store;

	/**
	 * CSV utility.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Csv|null
	 */
	private $csv_util;

	/**
	 * CSV parser.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Csv_Parser|null
	 */
	private $csv_parser;

	/**
	 * File processor.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_File_Processor|null
	 */
	private $file_processor;

	/**
	 * Batch processor.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Batch_Processor|null
	 */
	private $batch_processor;

	/**
	 * Response manager.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Response_Manager|null
	 */
	private $response_manager;

	/**
	 * Request parser.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Request_Parser|null
	 */
	private $request_parser;

	/**
	 * Constructor: Register AJAX hooks.
	 *
	 * @since 0.9.8
	 */
	public function __construct() {
		add_action( 'wp_ajax_swift_csv_ajax_import', [ $this, 'handle' ] );
		add_action( 'wp_ajax_swift_csv_ajax_import_logs', [ $this, 'handle_ajax_import_logs' ] );
	}

	/**
	 * Get request parser.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Request_Parser
	 */
	private function get_request_parser(): Swift_CSV_Import_Request_Parser {
		if ( null === $this->request_parser ) {
			$this->request_parser = new Swift_CSV_Import_Request_Parser();
		}
		return $this->request_parser;
	}

	/**
	 * Handle AJAX request to fetch import logs.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle_ajax_import_logs(): void {
		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		$import_session = $this->get_request_parser()->parse_import_session();
		if ( '' === $import_session ) {
			wp_send_json_error( 'Missing import session' );
			return;
		}

		$enable_logs = $this->get_request_parser()->parse_enable_logs();
		if ( ! $enable_logs ) {
			wp_send_json_success(
				[
					'last_id' => 0,
					'logs'    => [],
				]
			);
			return;
		}

		$fetch_params = $this->get_request_parser()->parse_log_fetch_params();
		$after_id     = (int) $fetch_params['after_id'];
		$limit        = (int) $fetch_params['limit'];

		$result = $this->get_log_store()->fetch( $import_session, $after_id, $limit );
		wp_send_json_success( $result );
	}

	/**
	 * Get import log store.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Log_Store
	 */
	private function get_log_store(): Swift_CSV_Import_Log_Store {
		if ( null === $this->log_store ) {
			$this->log_store = new Swift_CSV_Import_Log_Store();
		}
		return $this->log_store;
	}

	/**
	 * Get import CSV store.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Csv_Store
	 */
	private function get_csv_store(): Swift_CSV_Import_Csv_Store {
		if ( null === $this->csv_store ) {
			$this->csv_store = new Swift_CSV_Import_Csv_Store();
		}
		return $this->csv_store;
	}

	/**
	 * Get CSV utility.
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
	 * Get CSV parser.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Csv_Parser
	 */
	private function get_csv_parser(): Swift_CSV_Import_Csv_Parser {
		if ( null === $this->csv_parser ) {
			$this->csv_parser = new Swift_CSV_Import_Csv_Parser();
		}
		return $this->csv_parser;
	}

	/**
	 * Get file processor.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_File_Processor
	 */
	private function get_file_processor(): Swift_CSV_Import_File_Processor {
		if ( null === $this->file_processor ) {
			$this->file_processor = new Swift_CSV_Import_File_Processor();
		}
		return $this->file_processor;
	}

	/**
	 * Get batch processor.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Batch_Processor
	 */
	private function get_batch_processor(): Swift_CSV_Import_Batch_Processor {
		if ( null === $this->batch_processor ) {
			$this->batch_processor = new Swift_CSV_Import_Batch_Processor();
		}
		return $this->batch_processor;
	}

	/**
	 * Get response manager.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Response_Manager
	 */
	private function get_response_manager(): Swift_CSV_Import_Response_Manager {
		if ( null === $this->response_manager ) {
			$this->response_manager = new Swift_CSV_Import_Response_Manager();
		}
		return $this->response_manager;
	}

	/**
	 * Handle import request by routing to the selected handler.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle(): void {
		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		$import_method = $this->get_request_parser()->parse_import_method();

		switch ( $import_method ) {
			case 'direct_sql':
				$handler = new Swift_CSV_Ajax_Import_Handler_Direct_SQL(
					$this->get_log_store(),
					$this->get_csv_store(),
					$this->get_csv_util(),
					$this->get_csv_parser(),
					$this->get_file_processor(),
					$this->get_batch_processor(),
					$this->get_response_manager()
				);
				$handler->handle();
				return;
			case 'wp_compatible':
			default:
				$handler = new Swift_CSV_Ajax_Import_Handler_WP_Compatible(
					$this->get_log_store(),
					$this->get_csv_store(),
					$this->get_csv_util(),
					$this->get_csv_parser(),
					$this->get_file_processor(),
					$this->get_batch_processor(),
					$this->get_response_manager()
				);
				$handler->handle();
				return;
		}
	}
}
