<?php
/**
 * AJAX Import Batch Planner
 *
 * Provides shared logic for determining batch size.
 *
 * @since 0.9.8
 * @package FE_CSV_Import_Export
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Import Batch Planner Class
 *
 * @since 0.9.8
 * @package FE_CSV_Import_Export
 */
class FE_CSV_Import_Export_Ajax_Import_Batch_Planner {

	/**
	 * Get import batch size.
	 *
	 * @since 0.9.8
	 * @param int   $total_rows Total number of rows.
	 * @param array $config Import configuration.
	 * @return int Batch size.
	 */
	public function get_import_batch_size( int $total_rows, array $config ): int {
		if ( $total_rows <= 0 ) {
			return 1;
		}

		$max_execution_time = $this->get_max_execution_time();
		$safe_time_limit    = $max_execution_time * 0.8;
		$avg_time_per_row   = 0.02;
		$time_based_batch   = max( 1, (int) floor( $safe_time_limit / $avg_time_per_row ) );

		$memory_limit       = $this->convert_memory_limit_to_bytes( (string) ini_get( 'memory_limit' ) );
		$memory_limit       = max( 64 * 1024 * 1024, $memory_limit );
		$available_memory   = max( 16 * 1024 * 1024, $memory_limit - ( 48 * 1024 * 1024 ) );
		$memory_per_row     = 128 * 1024;
		$memory_based_batch = max( 1, (int) floor( $available_memory / $memory_per_row ) );

		$optimized_batch_size = min( $time_based_batch, $memory_based_batch, $total_rows );
		$base_batch_size      = max( 10, min( 100, $optimized_batch_size ) );
		$base_batch_size      = min( $total_rows, $base_batch_size );

		return (int) apply_filters( 'fe_csv_import_export_import_batch_size', $base_batch_size, $total_rows, $config );
	}

	/**
	 * Get max execution time with a safe fallback.
	 *
	 * @since 0.9.8
	 * @return int Max execution time in seconds.
	 */
	private function get_max_execution_time(): int {
		$max_execution_time = ini_get( 'max_execution_time' );
		$max_execution_time = false === $max_execution_time ? 30 : (int) $max_execution_time;

		if ( $max_execution_time <= 0 ) {
			return 120;
		}

		return max( 1, $max_execution_time );
	}

	/**
	 * Convert memory limit string to bytes.
	 *
	 * @since 0.9.8
	 * @param string $memory_limit Memory limit string.
	 * @return int Memory limit in bytes.
	 */
	private function convert_memory_limit_to_bytes( string $memory_limit ): int {
		$memory_limit = strtoupper( trim( $memory_limit ) );

		if ( '-1' === $memory_limit ) {
			return PHP_INT_MAX;
		}

		if ( '' === $memory_limit ) {
			return 128 * 1024 * 1024;
		}

		$unit  = substr( $memory_limit, -1 );
		$value = (int) substr( $memory_limit, 0, -1 );

		switch ( $unit ) {
			case 'G':
				$value *= 1024 * 1024 * 1024;
				break;
			case 'M':
				$value *= 1024 * 1024;
				break;
			case 'K':
				$value *= 1024;
				break;
			default:
				$value = (int) $memory_limit;
		}

		return $value > 0 ? $value : 128 * 1024 * 1024;
	}
}
