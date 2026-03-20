<?php
/**
 * AJAX Export Batch Planner
 *
 * Provides shared logic for calculating total posts count and determining batch size.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Export Batch Planner Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Export_Batch_Planner {

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
		$batch_size = 1000;

		if ( $total_count > 10000 ) {
			$batch_size = 2000;
		} elseif ( $total_count > 5000 ) {
			$batch_size = 1500;
		} elseif ( $total_count > 1000 ) {
			$batch_size = 1000;
		} else {
			$batch_size = 500;
		}

		return (int) apply_filters( 'swift_csv_export_batch_size', $batch_size, $total_count, $post_type, $config );
	}
}
