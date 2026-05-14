<?php
/**
 * Direct SQL Export Class for FE CSV Import & Export
 *
 * Legacy placeholder for Direct SQL export functionality.
 * This feature has been moved to FE CSV Import & Export Pro.
 * The class exists for compatibility but throws exceptions for all methods.
 *
 * @since 0.9.8
 * @package FE_CSV_Import_Export
 * @deprecated This functionality is now available in FE CSV Import & Export Pro.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Direct SQL Export Class
 *
 * Legacy compatibility class for Direct SQL export.
 * All methods throw exceptions directing users to FE CSV Import & Export Pro.
 * This class maintains API compatibility while preventing usage.
 *
 * @since 0.9.8
 * @package FE_CSV_Import_Export
 * @deprecated Use FE CSV Import & Export Pro for Direct SQL export functionality.
 */
class Swift_CSV_Export_Direct_SQL extends Swift_CSV_Export_Base {

	/**
	 * Build the Direct SQL runtime unavailable exception.
	 *
	 * @since 0.9.8
	 * @return Exception
	 */
	private function get_direct_sql_runtime_exception(): Exception {
		return new Exception( 'Direct SQL runtime is available in FE CSV Import & Export Pro only.' );
	}

	/**
	 * Get posts data
	 *
	 * @since 0.9.8
	 * @throws Exception Always thrown because Direct SQL runtime is owned by FE CSV Import & Export Pro.
	 */
	protected function get_posts_data(): void {
		throw $this->get_direct_sql_runtime_exception();
	}

	/**
	 * Get post field headers for public access
	 *
	 * Provides public access to header generation for external callers.
	 *
	 * @since 0.9.8
	 * @throws Exception Always thrown because Direct SQL runtime is owned by FE CSV Import & Export Pro.
	 */
	public function direct_sql_get_post_headers(): void {
		throw $this->get_direct_sql_runtime_exception();
	}

	/**
	 * Get posts batch for export
	 *
	 * @since 0.9.8
	 * @param int $offset Starting offset.
	 * @param int $batch_size Batch size.
	 * @throws Exception Always thrown because Direct SQL runtime is owned by FE CSV Import & Export Pro.
	 */
	public function direct_sql_batch_fetch_posts( $offset, $batch_size ): void {
		unset( $offset, $batch_size );

		throw $this->get_direct_sql_runtime_exception();
	}

	/**
	 * Generate CSV batch
	 *
	 * @since 0.9.8
	 * @param array $posts_data Posts data.
	 * @throws Exception Always thrown because Direct SQL runtime is owned by FE CSV Import & Export Pro.
	 */
	public function direct_sql_generate_csv_batch( $posts_data ): void {
		unset( $posts_data );

		throw $this->get_direct_sql_runtime_exception();
	}
}
