<?php
/**
 * Unified AJAX Export Handler for Swift CSV
 *
 * Handles both standard and Direct SQL export methods through a single AJAX endpoint.
 * Routes requests based on the 'export_method' parameter.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified AJAX Export Handler Class
 *
 * Centralizes AJAX export handling for both standard and Direct SQL methods.
 * Includes nonce verification, user capability checks, and rate limiting.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Export_Unified {

	/**
	 * Export log store
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Export_Log_Store
	 */
	private $log_store;

	/**
	 * Export cancel manager
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Export_Cancel_Manager
	 */
	private $cancel_manager;

	/**
	 * Constructor
	 *
	 * @since 0.9.8
	 */
	public function __construct() {
		$this->log_store      = new Swift_CSV_Export_Log_Store();
		$this->cancel_manager = new Swift_CSV_Export_Cancel_Manager();

		// Enable unified handler for Direct SQL export.
		add_action( 'wp_ajax_swift_csv_ajax_export', [ $this, 'handle_ajax_export' ] );
		add_action( 'wp_ajax_swift_csv_ajax_export_logs', [ $this, 'handle_ajax_export_logs' ] );
		add_action( 'wp_ajax_swift_csv_cancel_export', [ $this, 'handle_ajax_export_cancel' ] );
	}

	/**
	 * Handle AJAX export request
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle_ajax_export() {
		$initial_ob_level = function_exists( 'ob_get_level' ) ? ob_get_level() : 0;
		if ( function_exists( 'ob_start' ) ) {
			ob_start();
		}

		// Verify nonce.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'swift_csv_ajax_nonce' ) ) {
			while ( function_exists( 'ob_get_level' ) && function_exists( 'ob_end_clean' ) && ob_get_level() > $initial_ob_level ) {
				ob_end_clean();
			}
			wp_send_json_error( 'Security check failed.' );
			return;
		}

		// Check user capabilities.
		$can_export = (bool) apply_filters( 'swift_csv_user_can_export', (bool) current_user_can( 'export' ) );
		if ( ! $can_export ) {
			while ( function_exists( 'ob_get_level' ) && function_exists( 'ob_end_clean' ) && ob_get_level() > $initial_ob_level ) {
				ob_end_clean();
			}
			wp_send_json_error( 'Insufficient permissions.' );
			return;
		}

		$precheck_result = apply_filters( 'swift_csv_pre_ajax_export', true, $_POST );
		if ( is_wp_error( $precheck_result ) ) {
			while ( function_exists( 'ob_get_level' ) && function_exists( 'ob_end_clean' ) && ob_get_level() > $initial_ob_level ) {
				ob_end_clean();
			}
			wp_send_json_error( $precheck_result->get_error_message() );
			return;
		}

		// Get export method.
		$export_method = isset( $_POST['export_method'] ) ? sanitize_text_field( wp_unslash( $_POST['export_method'] ) ) : 'wp_compatible';
		if ( 'standard' === $export_method ) {
			$export_method = 'wp_compatible';
		}

		if ( 'direct_sql' === $export_method && ! $this->is_direct_sql_export_enabled() ) {
			while ( function_exists( 'ob_get_level' ) && function_exists( 'ob_end_clean' ) && ob_get_level() > $initial_ob_level ) {
				ob_end_clean();
			}
			wp_send_json_error( __( 'SQL export is available in Swift CSV Pro only.', 'swift-csv' ) );
			return;
		}

		// Get export configuration.
		$config = $this->get_export_config();

		$rate_limit_key = null;
		$result         = null;
		$error_message  = '';
		try {
			// Rate limiting (Direct SQL only).
			if ( 'direct_sql' === $export_method ) {
				$rate_limit_key = $this->check_rate_limit();
			}

			// Route to appropriate export method.
			switch ( $export_method ) {
				case 'direct_sql':
					$result = $this->handle_direct_sql_export( $config );
					break;
				case 'wp_compatible':
					$result = $this->handle_wp_compatible_export( $config );
					break;
				default:
					$result = $this->handle_wp_compatible_export( $config );
					break;
			}
		} catch ( Exception $e ) {
			$error_message = 'Export failed: ' . wp_kses_post( $e->getMessage() );
		}

		while ( function_exists( 'ob_get_level' ) && function_exists( 'ob_end_clean' ) && ob_get_level() > $initial_ob_level ) {
			ob_end_clean();
		}

		if ( is_string( $rate_limit_key ) && '' !== $rate_limit_key ) {
			delete_transient( $rate_limit_key );
		}

		if ( '' !== $error_message ) {
			wp_send_json_error( $error_message );
			return;
		}

		wp_send_json( $result );
	}

	/**
	 * Handle AJAX export logs request
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle_ajax_export_logs() {
		// Verify nonce.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'swift_csv_ajax_nonce' ) ) {
			wp_send_json_error( 'Security check failed.' );
			return;
		}

		$export_session = isset( $_POST['export_session'] ) ? sanitize_text_field( wp_unslash( $_POST['export_session'] ) ) : '';

		if ( empty( $export_session ) ) {
			wp_send_json_error( 'Invalid export session.' );
			return;
		}

		$enable_logs = isset( $_POST['enable_logs'] ) && in_array( (string) $_POST['enable_logs'], [ '1', 'true' ], true );
		if ( ! $enable_logs ) {
			wp_send_json_success(
				[
					'last_id' => 0,
					'logs'    => [],
				]
			);
			return;
		}

		$after_id = isset( $_POST['after_id'] ) ? intval( $_POST['after_id'] ) : 0;
		$limit    = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 100;
		$payload  = $this->log_store->fetch( $export_session, $after_id, $limit );

		wp_send_json_success( $payload );
	}

	/**
	 * Handle AJAX export cancellation
	 *
	 * @since 0.9.8
	 * @return void Sends JSON response.
	 */
	public function handle_ajax_export_cancel(): void {
		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		$export_session = sanitize_key( wp_unslash( $_POST['export_session'] ?? '' ) );
		if ( '' === $export_session ) {
			wp_send_json_error( 'Missing export session' );
			return;
		}
		$this->cancel_manager->cancel( $export_session );
		$this->log_store->cleanup( $export_session );

		wp_send_json_success( 'Export cancellation signal sent' );
	}

	/**
	 * Check rate limiting
	 *
	 * @since 0.9.8
	 * @throws Exception When rate limit exceeded.
	 */
	private function check_rate_limit() {
		// Check for concurrent exports in the same session.
		$session_id = (string) session_id();
		if ( '' === $session_id && function_exists( 'wp_get_session_token' ) ) {
			$session_id = (string) wp_get_session_token();
		}
		if ( '' === $session_id ) {
			$session_id = (string) get_current_user_id();
		}
		$cache_key    = 'swift_csv_concurrent_export_' . $session_id;
		$is_exporting = get_transient( $cache_key );

		if ( $is_exporting ) {
			throw new Exception( 'Another export is already in progress. Please wait for it to complete.' );
		}

		// Set concurrent export flag.
		// Keep the lock short and release it explicitly in a finally block.
		set_transient( $cache_key, true, 30 );

		return $cache_key;
	}

	/**
	 * Get export configuration from POST data
	 *
	 * @since 0.9.8
	 * @return array Export configuration.
	 */
	private function get_export_config() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		// Get basic configuration.
		$config = [
			'post_type'             => sanitize_text_field( wp_unslash( $_POST['post_type'] ?? 'post' ) ),
			'post_status'           => sanitize_text_field( wp_unslash( $_POST['post_status'] ?? 'publish' ) ),
			'export_scope'          => sanitize_text_field( wp_unslash( $_POST['export_scope'] ?? 'all' ) ),
			'include_taxonomies'    => isset( $_POST['include_taxonomies'] ) && in_array( (string) wp_unslash( $_POST['include_taxonomies'] ), [ '1', 'true' ], true ),
			'include_custom_fields' => isset( $_POST['include_custom_fields'] ) && in_array( (string) wp_unslash( $_POST['include_custom_fields'] ), [ '1', 'true' ], true ),
			'include_private_meta'  => isset( $_POST['include_private_meta'] ) ? (bool) $_POST['include_private_meta'] : false,
			'export_limit'          => isset( $_POST['export_limit'] ) ? absint( $_POST['export_limit'] ) : 0,
			'taxonomy_format'       => sanitize_text_field( wp_unslash( $_POST['taxonomy_format'] ?? 'name' ) ),
			'enable_logs'           => isset( $_POST['enable_logs'] ) ? (bool) $_POST['enable_logs'] : false,
		];

		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $config;
	}

	/**
	 * Handle Direct SQL export
	 *
	 * @since 0.9.8
	 * @param array $config Export configuration.
	 * @return array Export result.
	 * @throws Exception When export fails.
	 */
	private function handle_direct_sql_export( $config ) {
		$handler_class = 'Swift_CSV_Ajax_Export_Handler_Direct_SQL';

		if ( class_exists( 'Swift_CSV_Pro_Ajax_Export_Handler_Direct_SQL' ) ) {
			$handler_class = 'Swift_CSV_Pro_Ajax_Export_Handler_Direct_SQL';
		}

		$handler = new $handler_class( $this->log_store, $this->cancel_manager );
		return $handler->handle( (array) $config );
	}

	/**
	 * Handle WP compatible export
	 *
	 * @since 0.9.8
	 * @param array $config Export configuration.
	 * @return array Export result.
	 * @throws Exception When export fails.
	 */
	private function handle_wp_compatible_export( $config ) {
		$handler = new Swift_CSV_Ajax_Export_Handler_WP_Compatible( $this->log_store, $this->cancel_manager );
		return $handler->handle( (array) $config );
	}

	/**
	 * Check whether Direct SQL export is enabled.
	 *
	 * @since 0.9.17
	 * @return bool
	 */
	private function is_direct_sql_export_enabled(): bool {
		return class_exists( 'Swift_CSV_License_Handler' )
			&& is_callable( [ 'Swift_CSV_License_Handler', 'is_pro_active' ] )
			&& Swift_CSV_License_Handler::is_pro_active();
	}
}
