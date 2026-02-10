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
	 * License server API endpoint.
	 *
	 * @since 0.9.6
	 * @var string
	 */
	private $api_url = 'https://www.firstelement.co.jp/wp-json/license-manager/v1/license';

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
		$api_url = $this->api_url;
		if ( defined( 'SWIFT_CSV_LICENSE_API_URL' ) && is_string( SWIFT_CSV_LICENSE_API_URL ) && ! empty( SWIFT_CSV_LICENSE_API_URL ) ) {
			$api_url = SWIFT_CSV_LICENSE_API_URL;
		}

		$response = wp_remote_post(
			$api_url,
			[
				'body'    => [
					'action'      => $action,
					'license_key' => $license_key,
					'product_id'  => $this->product_id,
					'productId'   => $this->product_id,
					'instance'    => home_url(),
					'site_url'    => home_url(),
					'domain'      => home_url(),
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
