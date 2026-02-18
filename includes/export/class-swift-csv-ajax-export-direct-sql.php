<?php
/**
 * Direct SQL Export AJAX Handler
 *
 * Handles AJAX requests for Direct SQL Export functionality.
 *
 * @package Swift_CSV
 * @since 0.9.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Direct SQL Export AJAX Handler Class
 *
 * Processes AJAX requests for Direct SQL Export.
 *
 * @since 0.9.8
 */
class Swift_CSV_AJAX_Export_Direct_SQL {

	/**
	 * Constructor
	 *
	 * @since 0.9.8
	 */
	public function __construct() {
		add_action( 'wp_ajax_swift_csv_ajax_export_direct_sql', [ $this, 'handle_ajax_export' ] );
	}

	/**
	 * Sanitize post status field (handles single and multiple values)
	 *
	 * @since 0.9.8
	 * @param string $post_status Post status string (comma-separated for multiple)
	 * @return string|array Sanitized post status(es)
	 */
	private function sanitize_post_status( $post_status ) {
		// Handle multiple statuses (comma-separated)
		if ( strpos( $post_status, ',' ) !== false ) {
			$statuses  = explode( ',', $post_status );
			$sanitized = [];

			foreach ( $statuses as $status ) {
				$sanitized_status = sanitize_text_field( trim( $status ) );
				if ( ! empty( $sanitized_status ) ) {
					$sanitized[] = $sanitized_status;
				}
			}

			return $sanitized;
		}

		// Handle "all" or "any" status - return all common post statuses
		if ( 'all' === $post_status || 'any' === $post_status ) {
			return [
				'publish',
				'pending',
				'draft',
				'auto-draft',
				'future',
				'private',
				'inherit',
				'trash',
			];
		}

		// Single status
		return sanitize_text_field( $post_status );
	}

	/**
	 * Sanitize export scope and handle custom columns
	 *
	 * @since 0.9.8
	 * @param string $export_scope Export scope value
	 * @return array Export scope configuration with custom columns
	 */
	private function sanitize_export_scope( $export_scope ) {
		$sanitized_scope = sanitize_text_field( $export_scope );

		// Get custom columns from hook
		$custom_columns = apply_filters( 'swift_csv_export_columns', [] );

		return [
			'scope'          => $sanitized_scope,
			'custom_columns' => is_array( $custom_columns ) ? $custom_columns : [],
		];
	}

	/**
	 * Handle AJAX export request
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle_ajax_export() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'swift_csv_ajax_nonce' ) ) {
			wp_send_json_error( 'Security check failed.' );
			return;
		}

		// Check user capabilities
		if ( ! current_user_can( 'export' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
			return;
		}

		// Rate limiting (prevent abuse) - separate key for Direct SQL
		$user_id   = get_current_user_id();
		$cache_key = 'swift_csv_direct_sql_export_rate_limit_' . $user_id;

		// Increase memory limit for large exports
		@ini_set( 'memory_limit', '256M' );

		// Set time limit
		@set_time_limit( 300 ); // 5 minutes

		// Get export parameters
		$config = [
			'post_type'            => sanitize_text_field( $_POST['post_type'] ?? 'post' ),
			'post_status'          => $this->sanitize_post_status( $_POST['post_status'] ?? 'publish' ),
			'export_scope'         => $this->sanitize_export_scope( $_POST['export_scope'] ?? 'basic' ),
			'include_private_meta' => rest_sanitize_boolean( $_POST['include_private_meta'] ?? false ),
			'export_limit'         => absint( $_POST['export_limit'] ?? 0 ),
			'taxonomy_format'      => sanitize_text_field( $_POST['taxonomy_format'] ?? 'names' ),
			'enable_logs'          => rest_sanitize_boolean( $_POST['enable_logs'] ?? false ),
		];

		try {
			// Create Direct SQL Export instance
			$export = new Swift_CSV_Export_Direct_SQL( $config );

			// Perform export
			$result = $export->export();

			if ( $result['success'] ) {
				wp_send_json_success(
					[
						'csv'     => $result['csv'],
						'count'   => $result['count'],
						'total'   => $result['total'],
						'message' => $result['message'],
						'session' => $export->get_export_session(),
					]
				);
			} else {
				wp_send_json_error( $result['message'] );
			}
		} catch ( Exception $e ) {
			error_log( '[Direct SQL Export AJAX] Error: ' . $e->getMessage() );
			wp_send_json_error( 'Export failed: ' . $e->getMessage() );
		}
	}
}

// Initialize the AJAX handler
new Swift_CSV_AJAX_Export_Direct_SQL();
