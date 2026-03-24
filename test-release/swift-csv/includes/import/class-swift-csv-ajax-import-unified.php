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
		$initial_ob_level = function_exists( 'ob_get_level' ) ? ob_get_level() : 0;
		if ( function_exists( 'ob_start' ) ) {
			ob_start();
		}
		Swift_CSV_Ajax_Util::set_initial_ob_level( $initial_ob_level );
		Swift_CSV_Ajax_Util::set_ajax_action( 'swift_csv_ajax_import_logs' );
		Swift_CSV_Ajax_Util::register_fatal_error_json_handler();
		Swift_CSV_Ajax_Util::register_empty_response_json_handler();

		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		$can_import = (bool) apply_filters( 'swift_csv_user_can_import', (bool) current_user_can( 'import' ) );
		if ( ! $can_import ) {
			$this->cleanup_output_buffers( $initial_ob_level );
			wp_send_json_error( 'Insufficient permissions.' );
			return;
		}

		$precheck_result = apply_filters( 'swift_csv_pre_ajax_import', true, $_POST );
		if ( is_wp_error( $precheck_result ) ) {
			$this->cleanup_output_buffers( $initial_ob_level );
			wp_send_json_error( $precheck_result->get_error_message() );
			return;
		}

		try {

			$import_session = $this->get_request_parser()->parse_import_session();
			if ( '' === $import_session ) {
				$this->cleanup_output_buffers( $initial_ob_level );
				wp_send_json_error( 'Missing import session' );
				return;
			}

			$enable_logs = $this->get_request_parser()->parse_enable_logs();
			if ( ! $enable_logs ) {
				$this->cleanup_output_buffers( $initial_ob_level );
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
			$this->cleanup_output_buffers( $initial_ob_level );
			wp_send_json_success( $result );
		} catch ( Throwable $e ) {
			$this->cleanup_output_buffers( $initial_ob_level );
			wp_send_json_error( 'Import logs failed: ' . wp_kses_post( $e->getMessage() ) );
		}
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
		$initial_ob_level = function_exists( 'ob_get_level' ) ? ob_get_level() : 0;
		if ( function_exists( 'ob_start' ) ) {
			ob_start();
		}
		Swift_CSV_Ajax_Util::set_initial_ob_level( $initial_ob_level );
		Swift_CSV_Ajax_Util::set_ajax_action( 'swift_csv_ajax_import' );
		Swift_CSV_Ajax_Util::register_fatal_error_json_handler();
		Swift_CSV_Ajax_Util::register_empty_response_json_handler();

		register_shutdown_function(
			function () use ( $initial_ob_level ): void {
				if ( headers_sent() ) {
					return;
				}

				$last_error = error_get_last();
				if ( is_array( $last_error ) ) {
					$type = (int) ( $last_error['type'] ?? 0 );
					if ( in_array( $type, [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ], true ) ) {
						$this->cleanup_output_buffers( $initial_ob_level );
						wp_send_json_error(
							sprintf(
								'Fatal error: %s (%s:%d)',
								(string) ( $last_error['message'] ?? '' ),
								(string) ( $last_error['file'] ?? '' ),
								(int) ( $last_error['line'] ?? 0 )
							)
						);
					}
				}

				// If nothing was sent (no headers), avoid empty response.
				$this->cleanup_output_buffers( $initial_ob_level );
				wp_send_json_error( 'Empty AJAX response' );
			}
		);

		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		$can_import = (bool) apply_filters( 'swift_csv_user_can_import', (bool) current_user_can( 'import' ) );
		if ( ! $can_import ) {
			$this->cleanup_output_buffers( $initial_ob_level );
			wp_send_json_error( 'Insufficient permissions.' );
			return;
		}

		try {

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
		} catch ( Throwable $e ) {
			$this->cleanup_output_buffers( $initial_ob_level );
			wp_send_json_error( 'Import failed: ' . wp_kses_post( $e->getMessage() ) );
		}
	}

	/**
	 * Cleanup output buffers to avoid corrupting JSON responses.
	 *
	 * @since 0.9.8
	 * @param int $initial_ob_level Initial output buffer level.
	 * @return void
	 */
	private function cleanup_output_buffers( int $initial_ob_level ): void {
		while (
			function_exists( 'ob_get_level' ) &&
			function_exists( 'ob_end_clean' ) &&
			ob_get_level() > $initial_ob_level
		) {
			ob_end_clean();
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
	 * @since 0.9.8
	 * @return bool True if enabled.
	 */
	private function is_direct_sql_import_enabled(): bool {
		return (bool) apply_filters( 'swift_csv_enable_direct_sql_import', false );
	}
}
