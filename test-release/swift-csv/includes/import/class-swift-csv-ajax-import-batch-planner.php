<?php
/**
 * AJAX Import Batch Planner
 *
 * Provides shared logic for determining batch size.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Import Batch Planner Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Import_Batch_Planner {

	/**
	 * Get import batch size.
	 *
	 * @since 0.9.8
	 * @param int   $total_rows Total number of rows.
	 * @param array $config Import configuration.
	 * @return int Batch size.
	 */
	public function get_import_batch_size( int $total_rows, array $config ): int {
		$max_execution_time = ini_get( 'max_execution_time' );
		$max_execution_time = $max_execution_time ? (int) $max_execution_time : 30;
		$max_execution_time = max( 1, $max_execution_time );

		$safe_time_limit      = $max_execution_time * 0.5;
		$avg_time_per_row     = 0.35;
		$time_based_batch     = max( 1, (int) floor( $safe_time_limit / $avg_time_per_row ) );
		$optimized_batch_size = max( 10, min( 50, $time_based_batch ) );

		$base_batch_size = max( 1, min( $total_rows, $optimized_batch_size ) );

		return (int) apply_filters( 'swift_csv_import_batch_size', $base_batch_size, $total_rows, $config );
	}
}
