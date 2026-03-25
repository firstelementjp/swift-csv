<?php
/**
 * Change Detection for Swift CSV Import
 *
 * Optimizes import performance by detecting unchanged data and skipping unnecessary updates.
 *
 * @since 0.9.9
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change Detection Class
 *
 * Detects changes between CSV data and existing post data to optimize import performance.
 *
 * @since 0.9.9
 * @package Swift_CSV
 */
class Swift_CSV_Import_Change_Detection {

	/**
	 * Threshold for enabling change detection optimization.
	 * Only enable for datasets with this many rows or more.
	 *
	 * @since 0.9.9
	 * @var int
	 */
	private const CHANGE_DETECTION_THRESHOLD = 1000;

	/**
	 * Core post fields to compare for changes.
	 *
	 * @since 0.9.9
	 * @var array<int, string>
	 */
	private $core_post_fields = [
		'post_title',
		'post_content',
		'post_excerpt',
		'post_status',
		'post_name',
		'post_author',
		'post_date',
		'post_date_gmt',
		'post_parent',
		'menu_order',
		'comment_status',
		'ping_status',
	];

	/**
	 * Check if change detection should be enabled based on dataset size.
	 *
	 * @since 0.9.9
	 * @param int $total_rows Total number of rows in the dataset.
	 * @return bool True if change detection should be used.
	 */
	public function should_enable_change_detection( int $total_rows ): bool {
		return $total_rows >= self::CHANGE_DETECTION_THRESHOLD;
	}

	/**
	 * Check if post data has changed compared to CSV data.
	 *
	 * This method performs a quick check of commonly changed fields first,
	 * then falls back to detailed comparison if needed.
	 *
	 * @since 0.9.9
	 * @param int   $post_id Post ID.
	 * @param array $csv_data CSV data for the post.
	 * @return bool True if data has changed.
	 */
	public function has_post_data_changed( int $post_id, array $csv_data ): bool {
		// Quick check of commonly changed fields.
		$quick_check_fields = [ 'post_title', 'post_content', 'post_status' ];
		foreach ( $quick_check_fields as $field ) {
			if ( isset( $csv_data[ $field ] ) ) {
				$current_value = get_post_field( $field, $post_id );
				if ( $current_value !== $csv_data[ $field ] ) {
					// Early return - changed detected.
					return true;
				}
			}
		}

		// Detailed comparison of all core fields.
		return $this->detailed_post_field_comparison( $post_id, $csv_data );
	}

	/**
	 * Check if custom fields have changed.
	 *
	 * @since 0.9.9
	 * @param int   $post_id Post ID.
	 * @param array $custom_fields CSV custom fields data.
	 * @return bool True if custom fields have changed.
	 */
	public function has_custom_fields_changed( int $post_id, array $custom_fields ): bool {
		foreach ( $custom_fields as $key => $csv_value ) {
			$current_value = get_post_meta( $post_id, $key, true );

			// Handle multiple values (pipe-separated).
			if ( is_string( $csv_value ) && strpos( $csv_value, '|' ) !== false ) {
				$csv_values     = explode( '|', $csv_value );
				$current_values = get_post_meta( $post_id, $key, false );

				// Sort arrays for comparison.
				sort( $csv_values );
				sort( $current_values );

				if ( $csv_values !== $current_values ) {
					return true;
				}
			} elseif ( $current_value !== $csv_value ) {
					return true;
			}
		}

		return false;
	}

	/**
	 * Perform detailed comparison of all core post fields.
	 *
	 * @since 0.9.9
	 * @param int   $post_id Post ID.
	 * @param array $csv_data CSV data for the post.
	 * @return bool True if any core field has changed.
	 */
	private function detailed_post_field_comparison( int $post_id, array $csv_data ): bool {
		$current_post = get_post( $post_id, ARRAY_A );

		foreach ( $this->core_post_fields as $field ) {
			if ( ! isset( $csv_data[ $field ] ) ) {
				continue;
			}

			$current_value = $current_post[ $field ] ?? '';
			$csv_value     = $csv_data[ $field ];

			// Handle date fields specially.
			if ( in_array( $field, [ 'post_date', 'post_date_gmt' ], true ) ) {
				// Normalize date formats for comparison.
				$current_value = mysql2date( 'Y-m-d H:i:s', $current_value );
				$csv_value     = mysql2date( 'Y-m-d H:i:s', $csv_value );
			}

			if ( $current_value !== $csv_value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the change detection threshold.
	 *
	 * Useful for testing and configuration.
	 *
	 * @since 0.9.9
	 * @return int Threshold value.
	 */
	public function get_threshold(): int {
		return self::CHANGE_DETECTION_THRESHOLD;
	}

	/**
	 * Filter CSV data to only include fields that have changed.
	 *
	 * This can be used to minimize the amount of data sent to the database.
	 *
	 * @since 0.9.9
	 * @param int   $post_id Post ID.
	 * @param array $csv_data Full CSV data for the post.
	 * @return array Filtered data with only changed fields.
	 */
	public function filter_changed_fields_only( int $post_id, array $csv_data ): array {
		$changed_data = [];

		foreach ( $csv_data as $key => $value ) {
			if ( in_array( $key, $this->core_post_fields, true ) ) {
				$current_value = get_post_field( $key, $post_id );
				if ( $current_value !== $value ) {
					$changed_data[ $key ] = $value;
				}
			} else {
				// For custom fields, always include (they'll be checked separately).
				$changed_data[ $key ] = $value;
			}
		}

		return $changed_data;
	}
}
