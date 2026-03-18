<?php
/**
 * Direct SQL Export Class for Swift CSV
 *
 * High-performance CSV export using direct SQL queries.
 * Extends base export class with SQL-based data retrieval.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Direct SQL Export Class
 *
 * Handles high-performance CSV export using direct SQL queries.
 * This class bypasses WordPress functions for maximum performance.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Export_Direct_SQL extends Swift_CSV_Export_Base {

	/**
	 * Build the Direct SQL runtime unavailable exception.
	 *
	 * @since 0.9.8
	 * @return Exception
	 */
	private function get_direct_sql_runtime_exception(): Exception {
		return new Exception( 'Direct SQL runtime is available in Swift CSV Pro only.' );
	}

	/**
	 * Get posts data
	 *
	 * @since 0.9.8
	 * @return array Posts data.
	 * @throws Exception Always thrown because Direct SQL runtime is owned by Swift CSV Pro.
	 */
	protected function get_posts_data() {
		throw $this->get_direct_sql_runtime_exception();
	}

	/**
	 * Get post field headers for public access
	 *
	 * Provides public access to header generation for external callers.
	 *
	 * @since 0.9.8
	 * @return string[] Post field headers.
	 * @throws Exception Always thrown because Direct SQL runtime is owned by Swift CSV Pro.
	 */
	public function direct_sql_get_post_headers() {
		throw $this->get_direct_sql_runtime_exception();
	}

	/**
	 * Get posts batch for export
	 *
	 * @since 0.9.8
	 * @param int $offset Starting offset.
	 * @param int $batch_size Batch size.
	 * @return array Posts data.
	 * @throws Exception Always thrown because Direct SQL runtime is owned by Swift CSV Pro.
	 */
	public function direct_sql_batch_fetch_posts( $offset, $batch_size ) {
		unset( $offset, $batch_size );

		throw $this->get_direct_sql_runtime_exception();
	}

	/**
	 * Generate CSV batch
	 *
	 * @since 0.9.8
	 * @param array $posts_data Posts data.
	 * @return string CSV content.
	 * @throws Exception Always thrown because Direct SQL runtime is owned by Swift CSV Pro.
	 */
	public function direct_sql_generate_csv_batch( $posts_data ) {
		unset( $posts_data );

		throw $this->get_direct_sql_runtime_exception();
	}
}
