<?php
/**
 * Admin AJAX handler
 *
 * Handles AJAX endpoints for the Swift CSV admin pages.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin AJAX handler
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Admin_Ajax {

	/**
	 * AJAX handler for advanced settings save
	 *
	 * Saves free plugin advanced settings from the advanced settings tab.
	 *
	 * @since 0.9.15
	 * @return void
	 */
	public function ajax_save_advanced_settings() {
		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		if ( ! class_exists( 'Swift_CSV_Settings_Helper' ) ) {
			wp_send_json_error( [ 'message' => 'Settings helper is unavailable.' ] );
		}

		$enable_logs = isset( $_POST['enable_logs'] )
			&& in_array( (string) wp_unslash( $_POST['enable_logs'] ), [ '1', 'true' ], true );

		$result = Swift_CSV_Settings_Helper::update_section(
			'advanced',
			[
				'enable_logs' => $enable_logs,
			]
		);

		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to save advanced settings.', 'swift-csv' ) ] );
		}

		wp_send_json_success( [ 'message' => __( 'Advanced settings saved successfully!', 'swift-csv' ) ] );
	}

	/**
	 * AJAX handler for license management
	 *
	 * Handles license activation and deactivation requests.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function ajax_manage_license() {
		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		$action      = isset( $_POST['license_action'] ) ? sanitize_key( wp_unslash( $_POST['license_action'] ) ) : '';

		if ( empty( $license_key ) || empty( $action ) ) {
			wp_send_json_error( [ 'message' => 'Missing license key or action.' ] );
		}

		// Perform the license action (handled by Free version).
		$handler = new Swift_CSV_License_Handler();
		$result  = ( 'activate' === $action ) ? $handler->activate( $license_key ) : $handler->deactivate( $license_key );

		// Determine the product ID from the remote response.
		$product_id = 328;
		if ( isset( $result['data']['data']['productId'] ) ) {
			$product_id = (int) $result['data']['data']['productId'];
		} elseif ( isset( $result['data']['productId'] ) ) {
			$product_id = (int) $result['data']['productId'];
		}

		$all_licenses = get_option( 'swift_csv_pro_license', [] );

		// Ensure we have a proper array.
		if ( ! is_array( $all_licenses ) ) {
			$all_licenses = [];
		}

		if ( ! isset( $all_licenses['products'] ) || ! is_array( $all_licenses['products'] ) ) {
			$all_licenses['products'] = [];
		}

		if ( $result && $result['success'] ) {
			// Determine the local license status based on the requested action.
			$local_status = ( 'activate' === $action ) ? 'active' : 'inactive';

			if ( $product_id > 0 ) {
				$all_licenses['products'][ $product_id ] = [
					'key'    => $license_key,
					'status' => $local_status,
					'data'   => $result['data'] ?? [],
				];
			}

			update_option( 'swift_csv_pro_license', $all_licenses );
			delete_transient( 'swift_csv_pro_license_error' );
			wp_send_json_success( [ 'message' => $result['message'] ] );
		}

		if ( $product_id > 0 ) {
			$all_licenses['products'][ $product_id ] = [
				'key'    => $license_key,
				'status' => 'inactive',
				'data'   => $result['data'] ?? [],
			];
		}

		update_option( 'swift_csv_pro_license', $all_licenses );
		set_transient( 'swift_csv_pro_license_error', $result['message'], 60 );
		wp_send_json_error( [ 'message' => $result['message'] ] );
	}
}
