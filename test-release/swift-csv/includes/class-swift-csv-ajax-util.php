<?php
/**
 * AJAX utilities for Swift CSV.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX utilities.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Util {
	/**
	 * Initial output buffer level for the current request.
	 *
	 * @since 0.9.0
	 * @var int|null
	 */
	private static $initial_ob_level = null;

	/**
	 * Whether a JSON response has been sent.
	 *
	 * @since 0.9.0
	 * @var bool
	 */
	private static $response_sent = false;

	/**
	 * Current AJAX action name (for diagnostics).
	 *
	 * @since 0.9.0
	 * @var string
	 */
	private static $ajax_action = '';

	/**
	 * Diagnostic stage label.
	 *
	 * @since 0.9.0
	 * @var string
	 */
	private static $stage = '';

	/**
	 * Set initial output buffer level.
	 *
	 * @since 0.9.0
	 * @param int $level Initial output buffer level.
	 * @return void
	 */
	public static function set_initial_ob_level( int $level ): void {
		self::$initial_ob_level = $level;
	}

	/**
	 * Set current AJAX action name.
	 *
	 * @since 0.9.0
	 * @param string $action AJAX action.
	 * @return void
	 */
	public static function set_ajax_action( string $action ): void {
		self::$ajax_action = $action;
	}

	/**
	 * Set diagnostic stage label.
	 *
	 * @since 0.9.0
	 * @param string $stage Stage label.
	 * @return void
	 */
	public static function set_stage( string $stage ): void {
		self::$stage = $stage;
	}

	/**
	 * Check whether a JSON response has already been sent.
	 *
	 * @since 0.9.0
	 * @return bool
	 */
	public static function has_sent_response(): bool {
		return (bool) self::$response_sent;
	}

	/**
	 * Register shutdown handler to send JSON when a fatal error occurs.
	 *
	 * This helps avoid "Unexpected end of JSON input" on the client when PHP
	 * terminates before sending a valid JSON response.
	 *
	 * @since 0.9.0
	 * @return void
	 */
	public static function register_fatal_error_json_handler(): void {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;

		register_shutdown_function(
			static function (): void {
				$last_error = error_get_last();
				if ( ! is_array( $last_error ) ) {
					return;
				}

				$type = (int) ( $last_error['type'] ?? 0 );
				if ( ! in_array( $type, [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ], true ) ) {
					return;
				}

				if ( headers_sent() ) {
					return;
				}

				self::cleanup_output_buffers();

				$message = (string) ( $last_error['message'] ?? 'Fatal error' );
				$file    = (string) ( $last_error['file'] ?? '' );
				$line    = (int) ( $last_error['line'] ?? 0 );

				wp_send_json(
					[
						'success' => false,
						'error'   => sprintf( 'Fatal error: %s (%s:%d) stage=%s', $message, $file, $line, self::$stage ),
					]
				);
			}
		);
	}

	/**
	 * Register shutdown handler to avoid empty AJAX responses.
	 *
	 * When the request finishes without any JSON response being sent, return
	 * a generic JSON error for diagnostics.
	 *
	 * @since 0.9.0
	 * @return void
	 */
	public static function register_empty_response_json_handler(): void {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;

		register_shutdown_function(
			static function (): void {
				if ( self::$response_sent ) {
					return;
				}

				if ( headers_sent() ) {
					return;
				}

				if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
					return;
				}

				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
				if ( '' !== self::$ajax_action ) {
					$action = self::$ajax_action;
				}

				if ( '' === $action ) {
					return;
				}

				self::cleanup_output_buffers();
				wp_send_json(
					[
						'success' => false,
						'error'   => sprintf( 'Empty AJAX response (action=%s stage=%s)', $action, self::$stage ),
					]
				);
			}
		);
	}

	/**
	 * Cleanup output buffers to avoid corrupting JSON responses.
	 *
	 * @since 0.9.0
	 * @return void
	 */
	private static function cleanup_output_buffers(): void {
		$initial_level = is_int( self::$initial_ob_level ) ? self::$initial_ob_level : 0;

		while (
			function_exists( 'ob_get_level' ) &&
			function_exists( 'ob_end_clean' ) &&
			ob_get_level() > $initial_level
		) {
			ob_end_clean();
		}
	}

	/**
	 * Verify nonce for security check.
	 *
	 * @since 0.9.0
	 * @param string $nonce Nonce value to verify.
	 * @param string $nonce_action Nonce action.
	 * @return bool True if nonce is valid.
	 */
	public static function verify_nonce( string $nonce, string $nonce_action = 'swift_csv_ajax_nonce' ): bool {
		return '' !== $nonce && wp_verify_nonce( $nonce, $nonce_action );
	}

	/**
	 * Send JSON error response with optional file cleanup.
	 *
	 * @since 0.9.0
	 * @param string      $error_message Error message.
	 * @param string|null $file_path Optional file path to cleanup.
	 * @return void
	 */
	public static function send_error_response( string $error_message, ?string $file_path = null ): void {
		if ( $file_path && file_exists( $file_path ) ) {
			wp_delete_file( $file_path );
		}

		self::cleanup_output_buffers();
		self::$response_sent = true;

		wp_send_json(
			[
				'success' => false,
				'error'   => $error_message,
			]
		);
	}

	/**
	 * Send JSON response with buffer cleanup.
	 *
	 * This is used when the payload shape must be preserved.
	 *
	 * @since 0.9.0
	 * @param array $payload JSON payload.
	 * @return void
	 */
	public static function send_json( array $payload ): void {
		self::cleanup_output_buffers();
		self::$response_sent = true;
		wp_send_json( $payload );
	}

	/**
	 * Send JSON success response.
	 *
	 * @since 0.9.0
	 * @param mixed $data Response data.
	 * @return void
	 */
	public static function send_success_response( $data = null ): void {
		self::cleanup_output_buffers();
		self::$response_sent = true;
		wp_send_json_success( $data );
	}

	/**
	 * Send security error response with cleanup.
	 *
	 * @since 0.9.0
	 * @param string|null $file_path Optional file path to cleanup.
	 * @return void
	 */
	public static function send_security_error( ?string $file_path = null ): void {
		self::send_error_response( 'Security check failed', $file_path );
	}
}
