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
	 * Product IDs for each paid add-on.
	 *
	 * These IDs should be kept in sync with the
	 * products used by the license server.
	 */
	public const PRODUCT_ID_PRO = 328;

	/**
	 * Static cache for license data
	 *
	 * @since 0.9.7
	 * @var array|null
	 */
	private static $license_cache = null;

	/**
	 * Product ID for Swift CSV Pro.
	 *
	 * @since 0.9.6
	 * @var int (deprecated, use PRODUCT_ID_PRO instead)
	 */
	private $product_id = self::PRODUCT_ID_PRO;

	/**
	 * Activate license.
	 *
	 * @since 0.9.6
	 * @param string $license_key The license key to activate.
	 * @return array The response from the license server.
	 */
	public function activate( $license_key ) {
		$result = $this->call_license_api( 'activate', $license_key );

		// Clear cache after license change
		if ( $result['success'] ?? false ) {
			self::clear_cache();
		}

		return $result;
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
			self::clear_cache();

			return [
				'success'    => true,
				'message'    => __( 'License deactivated locally. Server-side activation remains active.', 'swift-csv' ),
				'local_only' => true,
			];
		}

		$result = $this->call_license_api( 'deactivate', $license_key );

		// Clear cache after license change
		self::clear_cache();

		return $result;
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
				'message' => __( 'License server not configured.', 'swift-csv' ),
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

	/**
	 * Returns all product license entries indexed by productId.
	 *
	 * @since 0.9.7
	 * @return array<int, array{
	 *     key: string,
	 *     status: string,
	 *     data: array
	 * }>
	 */
	public static function get_products() {
		if ( null === self::$license_cache ) {
			self::$license_cache = self::fetch_license_data();
		}

		$products = self::$license_cache['products'] ?? [];
		return is_array( $products ) ? $products : [];
	}

	/**
	 * Returns normalized license information.
	 *
	 * @since 0.9.7
	 * @return array{
	 *     status: string,
	 *     data: array,
	 *     product_id: int,
	 *     is_active: bool
	 * }
	 */
	public static function get_info() {
		$products = self::get_products();

		if ( empty( $products ) ) {
			return [
				'status'     => 'inactive',
				'data'       => [],
				'product_id' => self::PRODUCT_ID_PRO,
				'is_active'  => false,
			];
		}

		// Prefer the Pro product entry; fall back to the first available.
		if ( isset( $products[ self::PRODUCT_ID_PRO ] ) ) {
			$entry      = $products[ self::PRODUCT_ID_PRO ];
			$product_id = self::PRODUCT_ID_PRO;
		} else {
			$entry      = reset( $products );
			$product_id = (int) key( $products );
		}

		$status = isset( $entry['status'] ) ? (string) $entry['status'] : 'inactive';
		$data   = isset( $entry['data'] ) && is_array( $entry['data'] ) ? $entry['data'] : [];

		return [
			'status'     => $status,
			'data'       => $data,
			'product_id' => $product_id,
			'is_active'  => ( 'active' === $status ),
		];
	}

	/**
	 * Checks whether the given product license is active.
	 *
	 * @since 0.9.7
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function is_product_active( $product_id ) {
		$products = self::get_products();

		if ( ! isset( $products[ $product_id ] ) ) {
			return false;
		}

		$status = isset( $products[ $product_id ]['status'] ) ? (string) $products[ $product_id ]['status'] : 'inactive';

		return ( 'active' === $status );
	}

	/**
	 * Convenience wrapper for checking the Pro license status.
	 *
	 * @since 0.9.7
	 * @return bool
	 */
	public static function is_pro_active() {
		return self::is_product_active( self::PRODUCT_ID_PRO );
	}

	/**
	 * Fetch license data from database
	 *
	 * @since 0.9.7
	 * @return array License data structure
	 */
	private static function fetch_license_data() {
		return get_option( 'swift_csv_pro_license', [] );
	}

	/**
	 * Clear static cache
	 *
	 * Useful after license activation/deactivation or testing
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public static function clear_cache() {
		self::$license_cache = null;
	}

	/**
	 * Check if Pro license is active (legacy method for backward compatibility)
	 *
	 * @since 0.9.7
	 * @return bool True if Pro license is active and valid
	 */
	public function is_license_active() {
		return self::is_pro_active();
	}

	/**
	 * Check if Pro version is active and licensed (legacy static method)
	 *
	 * @since 0.9.7
	 * @deprecated Use is_pro_active() instead
	 * @return bool True if Pro license is active and valid
	 */
	public static function is_pro_version_licensed() {
		return self::is_pro_active();
	}
}
