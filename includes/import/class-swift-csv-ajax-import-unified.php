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
	 * @param bool $register_hooks Whether to register AJAX hooks.
	 */
	public function __construct( bool $register_hooks = true ) {
		if ( $register_hooks ) {
			add_action( 'wp_ajax_swift_csv_ajax_import', [ $this, 'handle' ] );
			add_action( 'wp_ajax_swift_csv_ajax_import_logs', [ $this, 'handle_ajax_import_logs' ] );
		}
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
	 * Handle import request by routing to the selected handler.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle(): void {
		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		$import_method = $this->get_request_parser()->parse_import_method();
		if ( 'direct_sql' === $import_method && ! $this->is_direct_sql_import_enabled() ) {
			$import_method = 'wp_compatible';
		}

		switch ( $import_method ) {
			case 'direct_sql':
				$this->handle_direct_sql_import();
				return;
			case 'wp_compatible':
			default:
				$this->handle_wp_compatible_import();
				return;
		}
	}

	/**
	 * Handle Direct SQL import.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	private function handle_direct_sql_import(): void {
		$handler = new Swift_CSV_Ajax_Import_Handler_Direct_SQL();
		$handler->handle();
	}

	/**
	 * Handle WP compatible import.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	private function handle_wp_compatible_import(): void {
		$handler = new Swift_CSV_Ajax_Import_Handler_WP_Compatible();
		$handler->handle();
	}

	/**
	 * Check if the direct SQL import method is enabled.
	 *
	 * Disabled by default to reduce the risk of data corruption.
	 *
	 * @since 0.9.10
	 * @return bool True if enabled.
	 */
	private function is_direct_sql_import_enabled(): bool {
		return (bool) apply_filters( 'swift_csv_enable_direct_sql_import', false );
	}
}
