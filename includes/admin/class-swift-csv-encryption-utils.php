<?php
/**
 * Encryption utilities for Swift CSV
 *
 * Provides secure encryption/decryption for sensitive data like license keys.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encryption utilities class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Encryption_Utils {

	/**
	 * Encryption method
	 *
	 * @var string
	 */
	private const ENCRYPTION_METHOD = 'AES-256-CBC';

	/**
	 * Option name for encryption key
	 *
	 * @var string
	 */
	private const ENCRYPTION_KEY_OPTION = 'swift_csv_encryption_key';

	/**
	 * Get or generate encryption key
	 *
	 * @return string Encryption key
	 */
	private static function get_encryption_key() {
		$key = get_option( self::ENCRYPTION_KEY_OPTION );
		if ( ! $key ) {
			$key = wp_generate_password( 32, false );
			update_option( self::ENCRYPTION_KEY_OPTION, $key );
		}
		return $key;
	}

	/**
	 * Encrypt data
	 *
	 * @param string $data Data to encrypt.
	 * @return string|false Encrypted data or false on failure
	 */
	public static function encrypt( $data ) {
		if ( empty( $data ) || ! function_exists( 'openssl_encrypt' ) ) {
			return false;
		}

		$key = self::get_encryption_key();
		$iv  = openssl_random_pseudo_bytes( 16 );

		if ( false === $iv ) {
			return false;
		}

		$encrypted = openssl_encrypt( $data, self::ENCRYPTION_METHOD, $key, 0, $iv );

		if ( false === $encrypted ) {
			return false;
		}

		// Base64 encode binary data (IV + encrypted) for safe storage.
		// This is NOT for obfuscation - it's for converting binary data to text format.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt data
	 *
	 * @param string $encrypted_data Encrypted data.
	 * @return string|false Decrypted data or false on failure
	 */
	public static function decrypt( $encrypted_data ) {
		if ( empty( $encrypted_data ) || ! function_exists( 'openssl_decrypt' ) ) {
			return false;
		}

		$key = self::get_encryption_key();
		// Base64 decode to convert text format back to binary data (IV + encrypted).
		// This is NOT for obfuscation - it's for reversing the text format conversion.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$data = base64_decode( $encrypted_data );

		if ( false === $data || strlen( $data ) < 16 ) {
			return false;
		}

		$iv        = substr( $data, 0, 16 );
		$encrypted = substr( $data, 16 );
		$decrypted = openssl_decrypt( $encrypted, self::ENCRYPTION_METHOD, $key, 0, $iv );

		return false === $decrypted ? false : $decrypted;
	}

	/**
	 * Check if encryption is available
	 *
	 * @return bool True if encryption is available
	 */
	public static function is_available() {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' );
	}
}
