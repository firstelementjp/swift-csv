<?php
/**
 * Import Log Store
 *
 * Manages transient-based import logs for Swift CSV.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import Log Store Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Import_Log_Store {

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
	 * Build transient key for the given import session.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session identifier.
	 * @return string Transient key.
	 */
	private function get_transient_key( string $import_session ): string {
		return 'swift_csv_import_logs_' . get_current_user_id() . '_' . $import_session;
	}

	/**
	 * Initialize log store.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session identifier.
	 * @return void
	 */
	public function init( string $import_session ): void {
		$transient_key = $this->get_transient_key( $import_session );
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
	 * @param string $import_session Import session identifier.
	 * @return void
	 */
	public function cleanup( string $import_session ): void {
		delete_transient( $this->get_transient_key( $import_session ) );
	}

	/**
	 * Append a log entry.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session identifier.
	 * @param array  $detail Import detail payload.
	 * @return int New log ID.
	 */
	public function append( string $import_session, array $detail ): int {
		$transient_key = $this->get_transient_key( $import_session );
		$store         = get_transient( $transient_key );

		if ( ! is_array( $store ) ) {
			$store = [
				'last_id' => 0,
				'logs'    => [],
			];
		}

		$last_id = isset( $store['last_id'] ) ? (int) $store['last_id'] : 0;
		$new_id  = $last_id + 1;
		$logs    = isset( $store['logs'] ) && is_array( $store['logs'] ) ? $store['logs'] : [];

		$logs[] = [
			'id'     => $new_id,
			'detail' => $detail,
		];

		if ( count( $logs ) > $this->max_entries ) {
			$logs = array_slice( $logs, -1 * $this->max_entries );
		}

		set_transient(
			$transient_key,
			[
				'last_id' => $new_id,
				'logs'    => $logs,
			],
			$this->ttl
		);

		return $new_id;
	}

	/**
	 * Fetch log entries since a given ID.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session identifier.
	 * @param int    $after_id Return entries after this ID.
	 * @param int    $limit Maximum number of entries.
	 * @return array{last_id:int,logs:array<int,array{id:int,detail:array}>} Payload.
	 */
	public function fetch( string $import_session, int $after_id, int $limit ): array {
		$limit = max( 1, min( 200, $limit ) );

		$store = get_transient( $this->get_transient_key( $import_session ) );
		if ( ! is_array( $store ) ) {
			return [
				'last_id' => $after_id,
				'logs'    => [],
			];
		}

		$last_id = isset( $store['last_id'] ) ? (int) $store['last_id'] : 0;
		$logs    = isset( $store['logs'] ) && is_array( $store['logs'] ) ? $store['logs'] : [];

		$filtered = [];
		foreach ( $logs as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
				continue;
			}
			$entry_id = (int) $entry['id'];
			if ( $entry_id <= $after_id ) {
				continue;
			}
			$filtered[] = $entry;
			if ( count( $filtered ) >= $limit ) {
				break;
			}
		}

		return [
			'last_id' => $last_id,
			'logs'    => $filtered,
		];
	}

	/**
	 * Get recent logs by type.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session identifier.
	 * @param string $type Log type: 'created', 'updated', 'errors'.
	 * @param int    $limit Maximum items to return.
	 * @return array{items:array<int,array{row:mixed,title:mixed,details:string}>,total:int}
	 */
	public function get_recent_logs_by_type( string $import_session, string $type, int $limit ): array {
		$all_logs = $this->fetch( $import_session, 0, $this->max_entries );
		$filtered = [];

		foreach ( $all_logs['logs'] as $log_item ) {
			if ( ! isset( $log_item['detail']['status'] ) || ! isset( $log_item['detail']['action'] ) ) {
				continue;
			}

			$detail   = $log_item['detail'];
			$is_match = false;

			switch ( $type ) {
				case 'created':
					$is_match = ( 'success' === $detail['status'] && 'create' === $detail['action'] );
					break;
				case 'updated':
					$is_match = ( 'success' === $detail['status'] && 'update' === $detail['action'] );
					break;
				case 'errors':
					$is_match = ( 'error' === $detail['status'] );
					break;
			}

			if ( $is_match ) {
				$filtered[] = [
					'row'     => $detail['row'] ?? null,
					'title'   => $detail['title'] ?? '',
					'details' => isset( $detail['details'] ) ? (string) $detail['details'] : '',
				];
			}
		}

		return [
			'items' => array_slice( $filtered, -1 * max( 1, $limit ) ),
			'total' => count( $filtered ),
		];
	}
}
