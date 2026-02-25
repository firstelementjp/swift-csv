<?php
/**
 * Export Log Store
 *
 * Manages transient-based export logs for Swift CSV.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export Log Store Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Export_Log_Store {

	/**
	 * Maximum number of log entries to keep.
	 *
	 * @since 0.9.8
	 * @var int
	 */
	private $max_entries;

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
	 * @param int $max_entries Maximum number of entries.
	 * @param int $ttl Transient TTL.
	 */
	public function __construct( int $max_entries = 500, int $ttl = 3600 ) {
		$this->max_entries = max( 1, $max_entries );
		$this->ttl         = max( 1, $ttl );
	}

	/**
	 * Build transient key for the given export session.
	 *
	 * @since 0.9.8
	 * @param string $export_session Export session identifier.
	 * @return string Transient key.
	 */
	private function get_transient_key( string $export_session ): string {
		return 'swift_csv_export_logs_' . get_current_user_id() . '_' . $export_session;
	}

	/**
	 * Initialize log store.
	 *
	 * @since 0.9.8
	 * @param string $export_session Export session identifier.
	 * @return void
	 */
	public function init( string $export_session ): void {
		$transient_key = $this->get_transient_key( $export_session );
		set_transient(
			$transient_key,
			[
				'last_id' => 0,
				'logs'    => [],
			],
			$this->ttl
		);
	}

	/**
	 * Cleanup log store.
	 *
	 * @since 0.9.8
	 * @param string $export_session Export session identifier.
	 * @return void
	 */
	public function cleanup( string $export_session ): void {
		$transient_key = $this->get_transient_key( $export_session );
		delete_transient( $transient_key );
	}

	/**
	 * Append a log entry.
	 *
	 * @since 0.9.8
	 * @param string $export_session Export session identifier.
	 * @param array  $detail Export detail payload.
	 * @return int New log ID.
	 */
	public function append( string $export_session, array $detail ): int {
		$transient_key = $this->get_transient_key( $export_session );
		$store         = get_transient( $transient_key );
		if ( ! is_array( $store ) ) {
			$store = [
				'last_id' => 0,
				'logs'    => [],
			];
		}

		if ( ! isset( $store['logs'] ) || ! is_array( $store['logs'] ) ) {
			$store['logs'] = [];
		}

		++$store['last_id'];
		$store['logs'][] = [
			'id'     => $store['last_id'],
			'detail' => $detail,
		];
		if ( count( $store['logs'] ) > $this->max_entries ) {
			$store['logs'] = array_slice( $store['logs'], -1 * $this->max_entries );
		}

		set_transient( $transient_key, $store, $this->ttl );
		return (int) $store['last_id'];
	}

	/**
	 * Fetch log entries.
	 *
	 * @since 0.9.8
	 * @param string $export_session Export session identifier.
	 * @param int    $after_id Return entries after this ID.
	 * @param int    $limit Maximum number of entries.
	 * @return array{last_id:int,logs:array<int,mixed>} Payload.
	 */
	public function fetch( string $export_session, int $after_id, int $limit ): array {
		$limit = max( 1, min( 200, $limit ) );

		$transient_key = $this->get_transient_key( $export_session );
		$store         = get_transient( $transient_key );

		if ( ! is_array( $store ) ) {
			return [
				'last_id' => $after_id,
				'logs'    => [],
			];
		}

		$logs = [];
		if ( ! empty( $store['logs'] ) && is_array( $store['logs'] ) ) {
			foreach ( $store['logs'] as $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
					continue;
				}
				$entry_id = (int) $entry['id'];
				if ( $entry_id <= $after_id ) {
					continue;
				}
				$logs[] = $entry;
				if ( count( $logs ) >= $limit ) {
					break;
				}
			}
		}

		return [
			'last_id' => (int) ( $store['last_id'] ?? 0 ),
			'logs'    => $logs,
		];
	}
}
