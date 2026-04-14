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
 * @author     FirstElement K.K. <info@firstelement.co.jp>
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
	 * Cron hook name for license resync.
	 *
	 * @since 0.9.8
	 * @var string
	 */
	public const CRON_HOOK_RESYNC_LICENSE = 'swift_csv_resync_license';

	/**
	 * Product IDs for each paid add-on.
	 *
	 * These IDs should be kept in sync with the
	 * products used by the license server.
	 */
	public const PRODUCT_ID_PRO = 3551;

	/**
	 * Legacy product ID that was stored locally by mistake.
	 *
	 * @since 0.9.8
	 * @var int
	 */
	private const LEGACY_PRODUCT_ID_PRO = 328;

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

		// Clear cache after license change.
		if ( $result['success'] ?? false ) {
			self::clear_cache();
		}

		return $result;
	}

	/**
	 * Fetch current license status from the license server.
	 *
	 * @since 0.9.8
	 * @param string $license_key The license key.
	 * @return array The response from the license server.
	 */
	public function fetch_status( $license_key ) {
		return $this->call_license_api( 'status', $license_key );
	}

	/**
	 * Deactivate license.
	 *
	 * @since 0.9.6
	 * @param string $license_key The license key to deactivate.
	 * @return array The response from the license server.
	 */
	public function deactivate( $license_key ) {
		// If Pro version is not available, deactivate locally only.
		if ( ! defined( 'SWIFT_CSV_LICENSE_API_URL' ) || empty( SWIFT_CSV_LICENSE_API_URL ) ) {
			// Remove local license data.
			delete_option( 'swift_csv_pro_license' );
			self::clear_cache();

			return [
				'success'    => true,
				'message'    => __( 'License deactivated locally. Server-side activation remains active.', 'swift-csv' ),
				'local_only' => true,
			];
		}

		$result = $this->call_license_api( 'deactivate', $license_key, self::get_saved_activation_token(), self::get_saved_remote_product_id() );

		// Clear cache after license change.
		self::clear_cache();

		return $result;
	}

	/**
	 * Call license API.
	 *
	 * @since 0.9.6
	 * @param string $action      The action to perform (activate/deactivate/status).
	 * @param string $license_key The license key.
	 * @param string $token       Activation token.
	 * @param int    $product_id  Product ID to send to the remote server.
	 * @return array The response from the license server.
	 */
	private function call_license_api( $action, $license_key, $token = '', $product_id = 0 ) {
		if ( ! defined( 'SWIFT_CSV_LICENSE_API_URL' ) || empty( SWIFT_CSV_LICENSE_API_URL ) ) {
			// Check if Swift CSV Pro is installed and active.
			$pro_plugin_path = 'swift-csv-pro/swift-csv-pro.php';
			$is_pro_active   = is_plugin_active( $pro_plugin_path );

			// Check if Pro plugin files exist.
			$pro_plugin_exists = file_exists( WP_PLUGIN_DIR . '/' . $pro_plugin_path );

			if ( ! $pro_plugin_exists ) {
				// Pro plugin is not installed at all.
				$message = __( 'Swift CSV Pro is not installed. Please install Swift CSV Pro to use license features.', 'swift-csv' );
			} elseif ( ! $is_pro_active ) {
				// Pro plugin exists but is not active.
				$message = __( 'Swift CSV Pro is installed but not activated. Please activate Swift CSV Pro to use license features.', 'swift-csv' );
			} else {
				// Pro plugin is active but license server is not configured.
				$message = __( 'License server is not configured. Please contact support.', 'swift-csv' );
			}

			return [
				'success' => false,
				'message' => $message,
			];
		}

		if ( $product_id <= 0 ) {
			$product_id = self::get_pro_product_id();
		}

		$response = wp_remote_post(
			SWIFT_CSV_LICENSE_API_URL,
			[
				'body'    => [
					'action'      => $action,
					'license_key' => $license_key,
					'productId'   => $product_id,
					'product_id'  => $product_id,
					'instance'    => home_url(),
					'token'       => $token,
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
	 * Get the saved activation token for the current Pro product.
	 *
	 * @since 0.9.8
	 * @return string
	 */
	public static function get_saved_activation_token() {
		$products = self::get_products();
		$entry    = $products[ self::get_pro_product_id() ] ?? [];

		if ( ! is_array( $entry ) ) {
			return '';
		}

		if ( isset( $entry['token'] ) && is_string( $entry['token'] ) ) {
			return $entry['token'];
		}

		return self::extract_activation_token( $entry['data'] ?? [] );
	}

	/**
	 * Get the saved remote product ID for the current Pro license.
	 *
	 * This keeps local storage normalized to the configured Pro slot while still
	 * allowing remote operations such as deactivation to target the purchased
	 * translated product ID when LMFWC returns it.
	 *
	 * @since 0.9.8
	 * @return int
	 */
	public static function get_saved_remote_product_id() {
		$products = self::get_products();
		$entry    = $products[ self::get_pro_product_id() ] ?? [];

		if ( ! is_array( $entry ) ) {
			return self::get_pro_product_id();
		}

		$data = $entry['data'] ?? [];
		if ( ! is_array( $data ) ) {
			return self::get_pro_product_id();
		}

		if ( isset( $data['data']['productId'] ) && absint( $data['data']['productId'] ) > 0 ) {
			return absint( $data['data']['productId'] );
		}

		if ( isset( $data['productId'] ) && absint( $data['productId'] ) > 0 ) {
			return absint( $data['productId'] );
		}

		return self::get_pro_product_id();
	}

	/**
	 * Decrypt a stored license key when possible.
	 *
	 * Falls back to the original value when the key is already plaintext or when
	 * decryption is unavailable.
	 *
	 * @since 0.9.8
	 * @param string $license_key Stored license key value.
	 * @return string
	 */
	public static function maybe_decrypt_license_key( $license_key ) {
		$license_key = is_string( $license_key ) ? $license_key : '';

		if ( '' === $license_key ) {
			return '';
		}

		if ( ! class_exists( 'Swift_CSV_Encryption_Utils' ) || ! Swift_CSV_Encryption_Utils::is_available() ) {
			return $license_key;
		}

		$decrypted_key = Swift_CSV_Encryption_Utils::decrypt( $license_key );

		return false !== $decrypted_key ? $decrypted_key : $license_key;
	}

	/**
	 * Encrypt a license key for storage when possible.
	 *
	 * Falls back to plaintext only when encryption is unavailable or fails.
	 *
	 * @since 0.9.8
	 * @param string $license_key License key value.
	 * @return string
	 */
	public static function prepare_license_key_for_storage( $license_key ) {
		$license_key = is_string( $license_key ) ? $license_key : '';

		if ( '' === $license_key ) {
			return '';
		}

		if ( ! class_exists( 'Swift_CSV_Encryption_Utils' ) || ! Swift_CSV_Encryption_Utils::is_available() ) {
			return $license_key;
		}

		$encrypted_key = Swift_CSV_Encryption_Utils::encrypt( $license_key );

		return ! empty( $encrypted_key ) ? $encrypted_key : $license_key;
	}

	/**
	 * Extract the activation token from a license API response payload.
	 *
	 * @since 0.9.8
	 * @param array $data License API response payload.
	 * @return string
	 */
	public static function extract_activation_token( $data ) {
		if ( ! is_array( $data ) ) {
			return '';
		}

		if ( isset( $data['token'] ) && is_string( $data['token'] ) ) {
			return $data['token'];
		}

		if ( isset( $data['activationData'] ) && is_array( $data['activationData'] ) && isset( $data['activationData']['token'] ) && is_string( $data['activationData']['token'] ) ) {
			return $data['activationData']['token'];
		}

		if ( isset( $data['data'] ) && is_array( $data['data'] ) && isset( $data['data']['token'] ) && is_string( $data['data']['token'] ) ) {
			return $data['data']['token'];
		}

		if ( isset( $data['data'] ) && is_array( $data['data'] ) && isset( $data['data']['activationData'] ) && is_array( $data['data']['activationData'] ) && isset( $data['data']['activationData']['token'] ) && is_string( $data['data']['activationData']['token'] ) ) {
			return $data['data']['activationData']['token'];
		}

		return '';
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
				'product_id' => self::get_pro_product_id(),
				'is_active'  => false,
			];
		}

		// Prefer the Pro product entry; fall back to the first available.
		if ( isset( $products[ self::get_pro_product_id() ] ) ) {
			$entry      = $products[ self::get_pro_product_id() ];
			$product_id = self::get_pro_product_id();
		} else {
			$entry      = reset( $products );
			$product_id = (int) key( $products );
		}

		$status     = isset( $entry['status'] ) ? (string) $entry['status'] : 'inactive';
		$data       = isset( $entry['data'] ) && is_array( $entry['data'] ) ? $entry['data'] : [];
		$is_expired = self::is_entry_expired( $entry );

		return [
			'status'     => $status,
			'data'       => $data,
			'product_id' => $product_id,
			'is_active'  => ( 'active' === $status ) && ! $is_expired,
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

		$entry  = $products[ $product_id ];
		$status = isset( $entry['status'] ) ? (string) $entry['status'] : 'inactive';
		if ( 'active' !== $status ) {
			return false;
		}
		if ( self::is_entry_expired( $entry ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Check if the given product license entry is expired.
	 *
	 * @since 0.9.8
	 * @param array $entry Product license entry.
	 * @return bool True if expired.
	 */
	private static function is_entry_expired( $entry ) {
		if ( ! is_array( $entry ) ) {
			return false;
		}

		$data = $entry['data'] ?? [];
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		$expires_at = '';
		if ( isset( $data['expiresAt'] ) ) {
			$expires_at = (string) $data['expiresAt'];
		} elseif ( isset( $data['expires_at'] ) ) {
			$expires_at = (string) $data['expires_at'];
		} elseif ( isset( $data['data']['expiresAt'] ) ) {
			$expires_at = (string) $data['data']['expiresAt'];
		} elseif ( isset( $data['data']['expires_at'] ) ) {
			$expires_at = (string) $data['data']['expires_at'];
		}

		if ( '' === $expires_at ) {
			return false;
		}

		$expires_ts = strtotime( $expires_at );
		if ( ! $expires_ts ) {
			return false;
		}

		return time() > $expires_ts;
	}

	/**
	 * Register wp-cron hook for license resync.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public static function register_license_resync_cron() {
		add_action( self::CRON_HOOK_RESYNC_LICENSE, [ __CLASS__, 'cron_resync_license' ] );
	}

	/**
	 * Schedule daily license resync if not already scheduled.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public static function maybe_schedule_license_resync() {
		if ( wp_next_scheduled( self::CRON_HOOK_RESYNC_LICENSE ) ) {
			return;
		}
		wp_schedule_event( time(), 'daily', self::CRON_HOOK_RESYNC_LICENSE );
	}

	/**
	 * Clear scheduled license resync.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public static function clear_license_resync_schedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK_RESYNC_LICENSE );
	}

	/**
	 * Cron callback: resync license state from server and update local option.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public static function cron_resync_license() {
		self::resync_license_from_server();
	}

	/**
	 * Resync Pro license state from server and update local cached option.
	 *
	 * @since 0.9.8
	 * @return array Result.
	 */
	public static function resync_license_from_server() {
		$license_data = get_option( 'swift_csv_pro_license', [] );
		if ( ! is_array( $license_data ) ) {
			$license_data = [];
		}
		$products = $license_data['products'] ?? [];
		if ( ! is_array( $products ) ) {
			$products = [];
		}
		$entry = $products[ self::get_pro_product_id() ] ?? [];
		if ( ! is_array( $entry ) ) {
			$entry = [];
		}
		$license_key = $entry['key'] ?? '';
		$license_key = is_string( $license_key ) ? $license_key : '';

		$license_key = self::maybe_decrypt_license_key( $license_key );

		if ( '' === $license_key ) {
			return [
				'success' => false,
				'message' => 'Missing license key.',
			];
		}

		if ( ! defined( 'SWIFT_CSV_LICENSE_API_URL' ) || empty( SWIFT_CSV_LICENSE_API_URL ) ) {
			return [
				'success' => false,
				'message' => 'License server is not configured.',
			];
		}

		$handler = new self();
		$result  = $handler->fetch_status( $license_key );
		if ( ! ( $result['success'] ?? false ) ) {
			set_transient( 'swift_csv_pro_license_error', (string) ( $result['message'] ?? '' ), 60 );
			return is_array( $result ) ? $result : [
				'success' => false,
				'message' => 'Unknown error.',
			];
		}

		$product_id        = self::get_pro_product_id();
		$remote_product_id = 0;
		if ( isset( $result['data']['data']['productId'] ) ) {
			$remote_product_id = (int) $result['data']['data']['productId'];
		} elseif ( isset( $result['data']['productId'] ) ) {
			$remote_product_id = (int) $result['data']['productId'];
		}

		$license_data['products']                = $products;
		$license_data['products'][ $product_id ] = [
			'key'    => self::prepare_license_key_for_storage( $license_key ),
			'status' => (string) ( $result['status'] ?? 'inactive' ),
			'data'   => $result['data'] ?? [],
			'token'  => self::extract_activation_token( $result['data'] ?? [] ),
		];

		if ( $remote_product_id > 0 && $remote_product_id !== $product_id && isset( $license_data['products'][ $remote_product_id ] ) ) {
			unset( $license_data['products'][ $remote_product_id ] );
		}

		update_option( 'swift_csv_pro_license', $license_data );
		delete_transient( 'swift_csv_pro_license_error' );
		self::clear_cache();

		return $result;
	}

	/**
	 * Convenience wrapper for checking the Pro license status.
	 *
	 * @since 0.9.7
	 * @return bool
	 */
	public static function is_pro_active() {
		return self::is_product_active( self::get_pro_product_id() );
	}

	/**
	 * Get the current Pro product ID.
	 *
	 * Uses the Pro main-file constant when available so migrations can be
	 * managed from a single obvious place.
	 *
	 * @since 0.9.8
	 * @return int
	 */
	public static function get_pro_product_id() {
		if ( defined( 'SWIFT_CSV_PRO_PRODUCT_ID' ) && absint( SWIFT_CSV_PRO_PRODUCT_ID ) > 0 ) {
			return absint( SWIFT_CSV_PRO_PRODUCT_ID );
		}

		return self::PRODUCT_ID_PRO;
	}

	/**
	 * Fetch license data from database
	 *
	 * @since 0.9.7
	 * @return array License data structure
	 */
	private static function fetch_license_data() {
		$license_data = get_option( 'swift_csv_pro_license', [] );

		if ( ! is_array( $license_data ) ) {
			return [];
		}

		if ( ! isset( $license_data['products'] ) || ! is_array( $license_data['products'] ) ) {
			return $license_data;
		}

		$products = $license_data['products'];

		$current_product_id = self::get_pro_product_id();

		if ( isset( $products[ self::LEGACY_PRODUCT_ID_PRO ] ) && ! isset( $products[ $current_product_id ] ) ) {
			$products[ $current_product_id ] = $products[ self::LEGACY_PRODUCT_ID_PRO ];
		}

		if ( isset( $products[ self::LEGACY_PRODUCT_ID_PRO ] ) ) {
			unset( $products[ self::LEGACY_PRODUCT_ID_PRO ] );
		}

		$license_data['products'] = $products;

		return $license_data;
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
