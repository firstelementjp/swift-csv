<?php
/**
 * License Handler for Swift CSV.
 *
 * This class handles the communication with the license server
 * and manages license activation/deactivation.
 *
 * @package    swift-csv
 * @subpackage Core
 * @since      0.9.6
 * @author     FirstElement, Inc. <info@firstelement.co.jp>
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License Handler class.
 *
 * @since      0.9.6
 * @package    swift-csv
 * @subpackage Core
 */
class Swift_CSV_License_Handler {

	/**
	 * Product ID for Swift CSV Pro.
	 *
	 * @since 0.9.6
	 * @var int
	 */
	private $product_id = 328;

	/**
	 * Activate license.
	 *
	 * @since 0.9.6
	 * @param string $license_key The license key to activate.
	 * @return array The response from the license server.
	 */
	public function activate( $license_key ) {
		return $this->call_license_api( 'activate', $license_key );
	}

	/**
	 * Deactivate license.
	 *
	 * @since 0.9.6
	 * @param string $license_key The license key to deactivate.
	 * @return array The response from the license server.
	 */
	public function deactivate( $license_key ) {
		// If Pro version is not available, deactivate locally only
		if ( ! defined( 'SWIFT_CSV_LICENSE_API_URL' ) || empty( SWIFT_CSV_LICENSE_API_URL ) ) {
			// Remove local license data
			delete_option( 'swift_csv_pro_license' );

			return [
				'success'    => true,
				'message'    => __( 'License deactivated locally. Server-side activation remains active.', 'swift-csv' ),
				'local_only' => true,
			];
		}

		return $this->call_license_api( 'deactivate', $license_key );
	}

	/**
	 * Call license API.
	 *
	 * @since 0.9.6
	 * @param string $action      The action to perform (activate/deactivate).
	 * @param string $license_key The license key.
	 * @return array The response from the license server.
	 */
	private function call_license_api( $action, $license_key ) {
		if ( ! defined( 'SWIFT_CSV_LICENSE_API_URL' ) || empty( SWIFT_CSV_LICENSE_API_URL ) ) {
			return [
				'success' => false,
				'message' => __( 'License API URL not defined. Please activate Swift CSV Pro.', 'swift-csv' ),
			];
		}

		$response = wp_remote_post(
			SWIFT_CSV_LICENSE_API_URL,
			[
				'body'    => [
					'action'      => $action,
					'license_key' => $license_key,
					'productId'   => $this->product_id,
					'instance'    => home_url(),
				],
				'timeout' => 30,
				'headers' => [
					'Content-Type' => 'application/x-www-form-urlencoded',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
			];
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return [
				'success' => false,
				'message' => __( 'Invalid response from license server.', 'swift-csv' ),
			];
		}

		return $data;
	}
}
