<?php
/**
 * Import CSV Store
 *
 * Manages transient-based parsed CSV data for Swift CSV import.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import CSV Store Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Import_Csv_Store {

	/**
	 * Transient TTL in seconds.
	 *
	 * @since 0.9.8
	 * @var int
	 */
	private $ttl;

	/**
	 * Constructor.
	 *
	 * @since 0.9.8
	 * @param int $ttl Transient TTL.
	 */
	public function __construct( int $ttl = 3600 ) {
		$this->ttl = max( 1, $ttl );
	}

	/**
	 * Build transient key for the given import session.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session identifier.
	 * @return string Transient key.
	 */
	private function get_transient_key( string $import_session ): string {
		return 'swift_csv_import_csv_' . get_current_user_id() . '_' . $import_session;
	}

	/**
	 * Get stored parsed CSV payload.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session identifier.
	 * @return array|null Parsed CSV payload or null when missing.
	 */
	public function get( string $import_session ): ?array {
		$value = get_transient( $this->get_transient_key( $import_session ) );
		return is_array( $value ) ? $value : null;
	}

	/**
	 * Persist parsed CSV payload.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session identifier.
	 * @param array  $csv_data Parsed CSV payload.
	 * @return void
	 */
	public function set( string $import_session, array $csv_data ): void {
		unset( $csv_data['batch_lines'] );
		unset( $csv_data['data'] );
		unset( $csv_data['lines'] );

		set_transient( $this->get_transient_key( $import_session ), $csv_data, $this->ttl );
	}

	/**
	 * Cleanup stored CSV payload.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session identifier.
	 * @return void
	 */
	public function cleanup( string $import_session ): void {
		delete_transient( $this->get_transient_key( $import_session ) );
	}
}
