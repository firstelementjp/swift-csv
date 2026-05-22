<?php
/**
 * Admin AJAX handler
 *
 * Handles AJAX endpoints for the FE CSV Import & Export admin pages.
 *
 * @since 0.9.8
 * @package FE_CSV_Import_Export
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin AJAX handler
 *
 * @since 0.9.8
 * @package FE_CSV_Import_Export
 */
class FE_CSV_Import_Export_Admin_Ajax {

	/**
	 * AJAX handler for advanced settings save
	 *
	 * Saves free plugin advanced settings from the advanced settings tab.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function ajax_save_advanced_settings() {
		check_ajax_referer( 'fe_csv_import_export_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		if ( ! class_exists( 'FE_CSV_Import_Export_Settings_Helper' ) ) {
			wp_send_json_error( [ 'message' => 'Settings helper is unavailable.' ] );
		}

		$enable_logs = isset( $_POST['enable_logs'] )
			&& in_array( (string) wp_unslash( $_POST['enable_logs'] ), [ '1', 'true' ], true );

		$uninstall_remove_all_data = isset( $_POST['uninstall_remove_all_data'] )
			&& in_array( (string) wp_unslash( $_POST['uninstall_remove_all_data'] ), [ '1', 'true' ], true );

		$updraft_backup_before_import = isset( $_POST['updraft_backup_before_import'] )
			&& in_array( (string) wp_unslash( $_POST['updraft_backup_before_import'] ), [ '1', 'true' ], true );

		$result = FE_CSV_Import_Export_Settings_Helper::update_section(
			'advanced',
			[
				'enable_logs'                  => $enable_logs,
				'uninstall_remove_all_data'    => $uninstall_remove_all_data,
				'updraft_backup_before_import' => $updraft_backup_before_import,
			]
		);

		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to save advanced settings.', 'fe-csv-import-export' ) ] );
		}

		wp_send_json_success( [ 'message' => __( 'Advanced settings saved successfully!', 'fe-csv-import-export' ) ] );
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
		check_ajax_referer( 'fe_csv_import_export_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		$action      = isset( $_POST['license_action'] ) ? sanitize_key( wp_unslash( $_POST['license_action'] ) ) : '';

		if ( empty( $license_key ) || empty( $action ) ) {
			wp_send_json_error( [ 'message' => 'Missing license key or action.' ] );
		}

		// Perform the license action (handled by Free version).
		$handler = new FE_CSV_Import_Export_License_Handler();
		$result  = ( 'activate' === $action ) ? $handler->activate( $license_key ) : $handler->deactivate( $license_key );

		// Always persist under the configured local Pro product slot.
		$product_id        = class_exists( 'FE_CSV_Import_Export_License_Handler' ) ? FE_CSV_Import_Export_License_Handler::get_pro_product_id() : 0;
		$remote_product_id = 0;
		if ( isset( $result['data']['data']['productId'] ) ) {
			$remote_product_id = (int) $result['data']['data']['productId'];
		} elseif ( isset( $result['data']['productId'] ) ) {
			$remote_product_id = (int) $result['data']['productId'];
		}

		$token         = class_exists( 'FE_CSV_Import_Export_License_Handler' ) ? FE_CSV_Import_Export_License_Handler::extract_activation_token( $result['data'] ?? [] ) : '';
		$remote_status = class_exists( 'FE_CSV_Import_Export_License_Handler' )
			? FE_CSV_Import_Export_License_Handler::normalize_license_status_from_result( $result )
			: (string) ( $result['status'] ?? 'inactive' );

		$all_licenses = get_option( 'fe_csv_import_export_pro_license', [] );
		$stored_key   = class_exists( 'FE_CSV_Import_Export_License_Handler' ) ? FE_CSV_Import_Export_License_Handler::prepare_license_key_for_storage( $license_key ) : $license_key;

		// Ensure we have a proper array.
		if ( ! is_array( $all_licenses ) ) {
			$all_licenses = [];
		}

		if ( ! isset( $all_licenses['products'] ) || ! is_array( $all_licenses['products'] ) ) {
			$all_licenses['products'] = [];
		}

		if ( $result && $result['success'] ) {
			$local_status = ( 'deactivate' === $action ) ? 'inactive' : $remote_status;

			if ( $product_id > 0 ) {
				$all_licenses['products'][ $product_id ] = [
					'key'    => $stored_key,
					'status' => $local_status,
					'data'   => $result['data'] ?? [],
					'token'  => $token,
				];

				if ( $remote_product_id > 0 && $remote_product_id !== $product_id && isset( $all_licenses['products'][ $remote_product_id ] ) ) {
					unset( $all_licenses['products'][ $remote_product_id ] );
				}
			}

			update_option( 'fe_csv_import_export_pro_license', $all_licenses );

			if ( 'activate' === $action && 'active' !== $local_status ) {
				$message = isset( $result['message'] ) && is_string( $result['message'] ) && 'The operation was successful.' !== $result['message']
					? $result['message']
					: __( 'License activation failed. Please check that your license key is valid.', 'fe-csv-import-export' );

				set_transient( 'fe_csv_import_export_pro_license_error', $message, 60 );
				wp_send_json_error( [ 'message' => $message ] );
			}

			delete_transient( 'fe_csv_import_export_pro_license_error' );
			wp_send_json_success( [ 'message' => $result['message'] ] );
		}

		if ( $product_id > 0 ) {
			$all_licenses['products'][ $product_id ] = [
				'key'    => $stored_key,
				'status' => 'inactive',
				'data'   => $result['data'] ?? [],
				'token'  => $token,
			];

			if ( $remote_product_id > 0 && $remote_product_id !== $product_id && isset( $all_licenses['products'][ $remote_product_id ] ) ) {
				unset( $all_licenses['products'][ $remote_product_id ] );
			}
		}

		update_option( 'fe_csv_import_export_pro_license', $all_licenses );
		set_transient( 'fe_csv_import_export_pro_license_error', $result['message'], 60 );
		wp_send_json_error( [ 'message' => $result['message'] ] );
	}
}
