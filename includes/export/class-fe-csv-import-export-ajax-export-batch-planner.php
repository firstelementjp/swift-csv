<?php
/**
 * AJAX Export Batch Planner
 *
 * Provides shared logic for calculating total posts count and determining batch size.
 *
 * @since 0.9.8
 * @package FE_CSV_Import_Export
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Export Batch Planner Class
 *
 * @since 0.9.8
 * @package FE_CSV_Import_Export
 */
class FE_CSV_Import_Export_Ajax_Export_Batch_Planner {

	/**
	 * Get total posts count for export.
	 *
	 * @since 0.9.8
	 * @param array $config Export configuration.
	 * @return int Total posts count.
	 */
	public function get_total_posts_count( array $config ): int {
		$args = [
			'post_type'      => $config['post_type'],
			'post_status'    => $config['post_status'],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		];

		if ( ! empty( $config['export_limit'] ) && (int) $config['export_limit'] > 0 ) {
			$args['posts_per_page'] = (int) $config['export_limit'];
		}

		$query = new WP_Query( $args );

		$found_posts  = (int) $query->found_posts;
		$export_limit = isset( $config['export_limit'] ) ? (int) $config['export_limit'] : 0;
		$total_posts  = $export_limit > 0 ? min( $found_posts, $export_limit ) : $found_posts;

		return (int) $total_posts;
	}

	/**
	 * Get export batch size.
	 *
	 * @since 0.9.8
	 * @param int    $total_count Total posts count.
	 * @param string $post_type Post type.
	 * @param array  $config Export configuration.
	 * @return int Batch size.
	 */
	public function get_export_batch_size( int $total_count, string $post_type, array $config ): int {
		// Get memory limit from server settings.
		$memory_limit = ini_get( 'memory_limit' );
		$memory_limit = $this->convert_memory_limit_to_bytes( $memory_limit );
		$memory_limit = max( 64 * 1024 * 1024, $memory_limit ); // Minimum 64MB.

		// Reserve memory for WordPress core and plugin overhead (approximately 32MB).
		$available_memory = $memory_limit - ( 32 * 1024 * 1024 );

		// Estimate memory per post (approximately 50KB per post including meta and taxonomies).
		$memory_per_post    = 50 * 1024;
		$memory_based_batch = max( 1, (int) floor( $available_memory / $memory_per_post ) );

		// Apply reasonable limits based on total count.
		$batch_size = min( $memory_based_batch, $total_count );

		// Set minimum and maximum batch sizes.
		$batch_size = max( 100, min( 5000, $batch_size ) );

		return (int) apply_filters( 'fe_csv_import_export_export_batch_size', $batch_size, $total_count, $post_type, $config );
	}

	/**
	 * Convert memory limit string to bytes.
	 *
	 * @since 0.9.8
	 * @param string $memory_limit Memory limit string (e.g., '128M', '1G').
	 * @return int Memory limit in bytes.
	 */
	private function convert_memory_limit_to_bytes( string $memory_limit ): int {
		$memory_limit = strtoupper( trim( $memory_limit ) );

		if ( '-1' === $memory_limit ) {
			return PHP_INT_MAX;
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
				$value *= 1024 * 1024; // Default to MB if no unit.
		}

		return $value;
	}
}
