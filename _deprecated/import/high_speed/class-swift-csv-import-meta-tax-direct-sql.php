<?php
/**
 * Meta and taxonomy processing (Direct SQL) for Swift CSV import.
 *
 * @since 0.9.10
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta and taxonomy processing for import rows (Direct SQL).
 *
 * @since 0.9.10
 * @package Swift_CSV
 */
class Swift_CSV_Import_Meta_Tax_Direct_SQL extends Swift_CSV_Import_Meta_Tax {
	/**
	 * Apply meta fields for a post.
	 *
	 * Optimized for Direct SQL by using a multi-row insert for multi-value fields.
	 *
	 * @since 0.9.10
	 * @param wpdb  $wpdb WordPress database handler.
	 * @param int   $post_id Post ID.
	 * @param array $meta_fields Meta fields.
	 * @param array $context Context values.
	 * @param array $dry_run_log Dry run log.
	 * @return void
	 */
	public function apply_meta_fields_for_post( wpdb $wpdb, int $post_id, array $meta_fields, array $context, array &$dry_run_log ): void {
		$dry_run = (bool) ( $context['dry_run'] ?? false );

		foreach ( $meta_fields as $key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}

			if ( ! is_string( $value ) ) {
				$value = maybe_serialize( $value );
			}

			$values = Swift_CSV_Helper::split_pipe_separated_values( (string) $value );
			$values = array_values(
				array_filter(
					array_map( 'trim', $values ),
					static function ( $v ): bool {
						return '' !== $v;
					}
				)
			);

			if ( $dry_run ) {
				if ( count( $values ) > 1 ) {
					foreach ( $values as $single_value ) {
						$dry_run_log[] = sprintf(
							/* translators: 1: meta key, 2: meta value */
							__( 'Custom field (multi-value): %1$s = %2$s', 'swift-csv' ),
							$key,
							$single_value
						);
					}
				} else {
					$dry_run_log[] = sprintf(
						/* translators: 1: meta key, 2: meta value */
						__( 'Custom field: %1$s = %2$s', 'swift-csv' ),
						$key,
						$values[0] ?? (string) $value
					);
				}
				continue;
			}

			// Delete then re-insert to keep overwrite semantics.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
					$post_id,
					$key
				)
			);

			if ( empty( $values ) ) {
				continue;
			}

			if ( 1 === count( $values ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->insert(
					$wpdb->postmeta,
					[
						'post_id'    => $post_id,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_key'   => $key,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						'meta_value' => $values[0],
					],
					[ '%d', '%s', '%s' ]
				);
				continue;
			}

			foreach ( $values as $single_value ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->insert(
					$wpdb->postmeta,
					[
						'post_id'    => $post_id,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_key'   => $key,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						'meta_value' => $single_value,
					],
					[ '%d', '%s', '%s' ]
				);
			}
		}
	}
}
