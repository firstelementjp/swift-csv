<?php
/**
 * Meta and taxonomy processing for FE CSV Import & Export import.
 *
 * @since 0.9.0
 * @package FE_CSV_Import_Export
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta and taxonomy processing for import rows.
 *
 * This class intentionally mirrors the existing behavior of the import handler
 * and is designed to be called from FE_CSV_Import_Export_Ajax_Import via delegation.
 *
 * @since 0.9.0
 * @package FE_CSV_Import_Export
 */
class FE_CSV_Import_Export_Import_Meta_Tax {
	/**
	 * CSV utility.
	 *
	 * @since 0.9.8
	 * @var object|null
	 */
	private $csv_util;

	/**
	 * Taxonomy utility.
	 *
	 * @since 0.9.8
	 * @var object|null
	 */
	private $taxonomy_util;

	/**
	 * Taxonomy writer strategy.
	 *
	 * @since 0.9.8
	 * @var object
	 */
	private $taxonomy_writer;

	/**
	 * Constructor.
	 *
	 * @since 0.9.8
	 * @param object|null $taxonomy_writer Taxonomy writer.
	 * @param object|null $taxonomy_util Taxonomy util.
	 */
	public function __construct(
		?FE_CSV_Import_Export_Import_Taxonomy_Writer_Interface $taxonomy_writer = null,
		?FE_CSV_Import_Export_Import_Taxonomy_Util $taxonomy_util = null
	) {
		$this->taxonomy_util   = $taxonomy_util;
		$this->taxonomy_writer = $taxonomy_writer ? $taxonomy_writer : new FE_CSV_Import_Export_Import_Taxonomy_Writer_WP( $this->taxonomy_util );
	}

	/**
	 * Get CSV utility instance.
	 *
	 * @since 0.9.8
	 * @return object
	 */
	private function get_csv_util(): object {
		if ( null === $this->csv_util ) {
			$this->csv_util = new FE_CSV_Import_Export_Import_Csv();
		}
		return $this->csv_util;
	}

	/**
	 * Get taxonomy utility instance.
	 *
	 * @since 0.9.8
	 * @return object
	 */
	private function get_taxonomy_util(): object {
		if ( null === $this->taxonomy_util ) {
			$this->taxonomy_util = new FE_CSV_Import_Export_Import_Taxonomy_Util();
		}
		return $this->taxonomy_util;
	}

	/**
	 * Process meta fields and taxonomies for a post using explicit arguments
	 *
	 * @since 0.9.0
	 * @param wpdb   $wpdb WordPress database handler.
	 * @param int    $post_id Post ID.
	 * @param array  $headers CSV headers.
	 * @param array  $data CSV row data.
	 * @param array  $allowed_post_fields Allowed WP post fields.
	 * @param string $taxonomy_format Taxonomy format.
	 * @param array  $taxonomy_format_validation Taxonomy format validation.
	 * @param bool   $dry_run Dry run flag.
	 * @param array  $counters Counters (by reference).
	 * @return array{
	 *   meta_fields: array<string,string>,
	 *   taxonomies:  array<string,array<int,string>>
	 * }
	 */
	public function process_meta_and_taxonomies_for_row_with_args(
		wpdb $wpdb,
		int $post_id,
		array $headers,
		array $data,
		array $allowed_post_fields,
		string $taxonomy_format,
		array $taxonomy_format_validation,
		bool $dry_run,
		array &$counters
	): array {
		$dry_run_log = &$counters['dry_run_log'];

		// Process custom fields and taxonomies like original FE CSV Import & Export.
		$collected_fields = $this->collect_taxonomies_and_meta_fields_from_row( $headers, $data, $allowed_post_fields );

		/**
		 * Filter field mapping before processing.
		 *
		 * This hook is called after basic field collection but before database operations.
		 *
		 * @since 0.9.0
		 * @param array{
		 *   meta_fields:     array<string,string>,
		 *   taxonomies:      array<string,array<int,string>>,
		 *   taxonomy_term_ids: array<string,array<int,int>>
		 * } $collected_fields Collected fields.
		 * @param array<int, string> $headers CSV headers.
		 * @param array $data CSV row data.
		 * @param array<int, string> $allowed_post_fields Allowed WP post fields.
		 * @return array{
		 *   meta_fields:     array<string,string>,
		 *   taxonomies:      array<string,array<int,string>>,
		 *   taxonomy_term_ids: array<string,array<int,int>>
		 * } Modified collected fields.
		 */
		$collected_fields = apply_filters(
			'fe_csv_import_export_import_field_mapping',
			$collected_fields,
			$headers,
			$data,
			$allowed_post_fields
		);
		do_action(
			'fe_csv_import_export_import_phase_map',
			$collected_fields,
			$headers,
			$data,
			$allowed_post_fields
		);
		$taxonomies  = $collected_fields['taxonomies'];
		$meta_fields = $collected_fields['meta_fields'];

		$context = [
			'post_id'                    => $post_id,
			'dry_run'                    => $dry_run,
			'headers'                    => $headers,
			'data'                       => $data,
			'allowed_post_fields'        => $allowed_post_fields,
			'taxonomy_format'            => $taxonomy_format,
			'taxonomy_format_validation' => $taxonomy_format_validation,
		];

		// Process taxonomies.
		$this->apply_taxonomies_for_post( $wpdb, $post_id, $taxonomies, $context, $dry_run_log );

		// Process custom fields with multi-value support.
		$this->apply_meta_fields_for_post( $wpdb, $post_id, $meta_fields, $context, $dry_run_log );

		return [
			'meta_fields' => $meta_fields,
			'taxonomies'  => $taxonomies,
		];
	}

	/**
	 * Prepare meta fields and taxonomies for a post using explicit arguments.
	 *
	 * @since 0.9.0
	 * @param int               $post_id Post ID.
	 * @param array<int,string> $headers CSV headers.
	 * @param array<int,string> $data CSV row data.
	 * @param array<int,string> $allowed_post_fields Allowed WP post fields.
	 * @return array{
	 *   post_id: int,
	 *   meta_fields: array<string,string>,
	 *   taxonomies:  array<string,array<int,string>>,
	 *   context_data: array<int,string>
	 * }
	 */
	public function prepare_meta_and_taxonomies_for_row_with_args(
		int $post_id,
		array $headers,
		array $data,
		array $allowed_post_fields
	): array {
		$collected_fields = $this->collect_taxonomies_and_meta_fields_from_row( $headers, $data, $allowed_post_fields );

		$collected_fields = apply_filters(
			'fe_csv_import_export_import_field_mapping',
			$collected_fields,
			$headers,
			$data,
			$allowed_post_fields
		);
		do_action(
			'fe_csv_import_export_import_phase_map',
			$collected_fields,
			$headers,
			$data,
			$allowed_post_fields
		);

		return [
			'post_id'      => $post_id,
			'meta_fields'  => $collected_fields['meta_fields'],
			'taxonomies'   => $collected_fields['taxonomies'],
			'context_data' => $data,
		];
	}

	/**
	 * Apply prepared meta fields and taxonomies for a batch.
	 *
	 * @since 0.9.0
	 * @param wpdb  $wpdb WordPress database handler.
	 * @param array $items Prepared batch items.
	 * @param array $context Context values for row processing.
	 * @param array $dry_run_log Dry run log.
	 * @return void
	 */
	public function apply_prepared_meta_and_taxonomies_for_batch(
		wpdb $wpdb,
		array $items,
		array $context,
		array &$dry_run_log
	): void {
		$this->apply_prepared_meta_fields_for_batch( $wpdb, $items, $context, $dry_run_log );
		$this->apply_prepared_taxonomies_for_batch( $items, $context, $dry_run_log );
	}

	/**
	 * Collect taxonomy fields from a CSV row.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $headers CSV headers.
	 * @param array<int, string> $data CSV row data.
	 * @return array{
	 *   taxonomies:      array<string,array<int,string>>,
	 *   taxonomy_term_ids: array<string,array<int,int>>
	 * }
	 */
	public function collect_taxonomy_fields_from_row( array $headers, array $data ): array {
		$taxonomies        = [];
		$taxonomy_term_ids = [];

		$headers_count = count( $headers );
		for ( $j = 0; $j < $headers_count; $j++ ) {
			$header_name            = $headers[ $j ] ?? '';
			$header_name_normalized = $this->normalize_field_name( (string) $header_name );

			// Skip empty headers and ID.
			if ( '' === $header_name_normalized || 'ID' === $header_name_normalized ) {
				continue;
			}

			if ( 0 !== strpos( $header_name_normalized, 'tax_' ) ) {
				continue;
			}

			if ( empty( trim( $data[ $j ] ?? '' ) ) ) {
				continue; // Skip empty fields.
			}

			$meta_value = (string) ( $data[ $j ] ?? '' );

			// Handle taxonomy (pipe-separated with escaping).
			$terms_raw                    = $this->get_csv_util()->split_pipe_separated_values( $meta_value );
			$terms                        = array_values( array_filter( array_map( 'trim', (array) $terms_raw ), 'strlen' ) );
			$taxonomy_name                = substr( $header_name_normalized, 4 ); // Remove 'tax_'.
			$taxonomies[ $taxonomy_name ] = $terms;

		}

		return [
			'taxonomies'        => $taxonomies,
			'taxonomy_term_ids' => $taxonomy_term_ids,
		];
	}

	/**
	 * Collect meta fields from a CSV row.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $headers CSV headers.
	 * @param array<int, string> $data CSV row data.
	 * @param array<int, string> $allowed_post_fields Allowed WP post fields.
	 * @return array<string, string>
	 */
	public function collect_meta_fields_from_row( array $headers, array $data, array $allowed_post_fields ): array {
		$meta_fields = [];

		$headers_count = count( $headers );
		for ( $j = 0; $j < $headers_count; $j++ ) {
			$header_name            = $headers[ $j ] ?? '';
			$header_name_normalized = $this->normalize_field_name( (string) $header_name );

			// Skip empty headers and post fields.
			if ( '' === $header_name_normalized || 'ID' === $header_name_normalized ) {
				continue;
			}
			if ( in_array( $header_name_normalized, $allowed_post_fields, true ) ) {
				continue;
			}

			if ( empty( trim( $data[ $j ] ?? '' ) ) ) {
				continue; // Skip empty fields.
			}

			$meta_value = (string) ( $data[ $j ] ?? '' );

			// Handle regular custom fields (cf_<field> => <field>) ONLY.
			if ( 0 !== strpos( $header_name_normalized, 'cf_' ) ) {
				continue; // Skip non-cf_ fields.
			}

			$clean_field_name                 = substr( $header_name_normalized, 3 );
			$meta_fields[ $clean_field_name ] = (string) $meta_value;
		}

		return $meta_fields;
	}

	/**
	 * Collect taxonomies and meta fields from a CSV row.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $headers CSV headers.
	 * @param array<int, string> $data CSV row data.
	 * @param array<int, string> $allowed_post_fields Allowed WP post fields.
	 * @return array{
	 *   meta_fields:     array<string,string>,
	 *   taxonomies:      array<string,array<int,string>>,
	 *   taxonomy_term_ids: array<string,array<int,int>>
	 * }
	 */
	public function collect_taxonomies_and_meta_fields_from_row(
		array $headers,
		array $data,
		array $allowed_post_fields
	): array {
		$taxonomy_data = $this->collect_taxonomy_fields_from_row( $headers, $data );
		$meta_fields   = $this->collect_meta_fields_from_row( $headers, $data, $allowed_post_fields );

		return [
			'meta_fields'       => $meta_fields,
			'taxonomies'        => $taxonomy_data['taxonomies'],
			'taxonomy_term_ids' => $taxonomy_data['taxonomy_term_ids'],
		];
	}

	/**
	 * Resolve term IDs for a single taxonomy.
	 *
	 * @since 0.9.0
	 * @param string             $taxonomy Taxonomy name.
	 * @param array<int, string> $terms Term values.
	 * @param string             $taxonomy_format Taxonomy format.
	 * @param array              $taxonomy_format_validation Taxonomy format validation.
	 * @return array<int, int>
	 */
	public function resolve_taxonomy_term_ids(
		string $taxonomy,
		array $terms,
		string $taxonomy_format,
		array $taxonomy_format_validation
	): array {
		$term_ids = [];
		foreach ( $terms as $term_value ) {
			$term_value = trim( (string) $term_value );
			if ( '' === $term_value ) {
				continue;
			}

			$resolved_term_ids = $this->resolve_term_ids_for_term_value(
				$taxonomy,
				$term_value,
				$taxonomy_format,
				$taxonomy_format_validation
			);
			foreach ( $resolved_term_ids as $resolved_term_id ) {
				$term_ids[] = $resolved_term_id;
			}
		}
		return $term_ids;
	}

	/**
	 * Apply taxonomy terms to a post.
	 *
	 * @since 0.9.0
	 * @param int                $post_id Post ID.
	 * @param string             $taxonomy Taxonomy name.
	 * @param array<int, int>    $term_ids Term IDs.
	 * @param bool               $dry_run Dry run flag.
	 * @param array<int, string> $dry_run_log Dry run log.
	 * @return void
	 */
	public function apply_taxonomy_terms_to_post(
		int $post_id,
		string $taxonomy,
		array $term_ids,
		bool $dry_run,
		array &$dry_run_log
	): void {
		if ( empty( $term_ids ) ) {
			return;
		}

		if ( $dry_run ) {
			foreach ( $term_ids as $term_id ) {
				$term = get_term( $term_id, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$dry_run_log[] = sprintf(
						/* translators: 1: term name, 2: term ID, 3: taxonomy name */
						__( 'Existing term: %1$s (ID: %2$s, taxonomy: %3$s)', 'fe-csv-import-export' ),
						$term->name,
						$term_id,
						$taxonomy
					);
				}
			}
			return;
		}

		wp_set_post_terms( $post_id, $term_ids, $taxonomy, false );
	}

	/**
	 * Apply taxonomy terms for a post.
	 *
	 * @since 0.9.0
	 * @param wpdb                              $wpdb WordPress database handler.
	 * @param int                               $post_id Post ID.
	 * @param array<string, array<int, string>> $taxonomies Taxonomy terms map.
	 * @param array                             $context Context values for row processing.
	 * @param array<int, string>                $dry_run_log Dry run log.
	 * @return void
	 */
	public function apply_taxonomies_for_post(
		wpdb $wpdb,
		int $post_id,
		array $taxonomies,
		array $context,
		array &$dry_run_log
	): void {
		$this->taxonomy_writer->apply_taxonomies_for_post( $wpdb, $post_id, $taxonomies, $context, $dry_run_log );
	}

	/**
	 * Apply prepared taxonomies for a batch.
	 *
	 * @since 0.9.0
	 * @param array $items Prepared batch items.
	 * @param array $context Context values for row processing.
	 * @param array $dry_run_log Dry run log.
	 * @return void
	 */
	private function apply_prepared_taxonomies_for_batch(
		array $items,
		array $context,
		array &$dry_run_log
	): void {
		$dry_run                    = (bool) ( $context['dry_run'] ?? false );
		$taxonomy_format            = (string) ( $context['taxonomy_format'] ?? 'name' );
		$taxonomy_format_validation = isset( $context['taxonomy_format_validation'] ) && is_array( $context['taxonomy_format_validation'] ) ? $context['taxonomy_format_validation'] : [];
		$term_id_cache              = [];

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$post_id    = (int) ( $item['post_id'] ?? 0 );
			$taxonomies = isset( $item['taxonomies'] ) && is_array( $item['taxonomies'] ) ? $item['taxonomies'] : [];
			if ( empty( $taxonomies ) || ( $post_id <= 0 && ! $dry_run ) ) {
				continue;
			}

			foreach ( $taxonomies as $taxonomy => $terms ) {
				if ( ! is_string( $taxonomy ) || ! is_array( $terms ) || empty( $terms ) ) {
					continue;
				}

				$term_ids = $this->resolve_taxonomy_term_ids_with_cache(
					$taxonomy,
					$terms,
					$taxonomy_format,
					$taxonomy_format_validation,
					$term_id_cache
				);

				$this->apply_taxonomy_terms_to_post( $post_id, $taxonomy, $term_ids, $dry_run, $dry_run_log );
			}
		}
	}

	/**
	 * Resolve taxonomy term IDs using a batch-local cache.
	 *
	 * @since 0.9.0
	 * @param string             $taxonomy Taxonomy name.
	 * @param array<int, string> $terms Term values.
	 * @param string             $taxonomy_format Taxonomy format.
	 * @param array              $taxonomy_format_validation Taxonomy format validation.
	 * @param array              $term_id_cache Resolved term ID cache.
	 * @return array<int, int>
	 */
	private function resolve_taxonomy_term_ids_with_cache(
		string $taxonomy,
		array $terms,
		string $taxonomy_format,
		array $taxonomy_format_validation,
		array &$term_id_cache
	): array {
		$term_ids = [];
		foreach ( $terms as $term_value ) {
			$term_value = trim( (string) $term_value );
			if ( '' === $term_value ) {
				continue;
			}

			$cache_key = $taxonomy . "\n" . $taxonomy_format . "\n" . $term_value;
			if ( ! array_key_exists( $cache_key, $term_id_cache ) ) {
				$term_id_cache[ $cache_key ] = $this->resolve_term_ids_for_term_value(
					$taxonomy,
					$term_value,
					$taxonomy_format,
					$taxonomy_format_validation
				);
			}

			foreach ( $term_id_cache[ $cache_key ] as $resolved_term_id ) {
				$term_ids[] = (int) $resolved_term_id;
			}
		}

		return array_values( array_unique( $term_ids ) );
	}

	/**
	 * Resolve term IDs from a term value.
	 *
	 * @since 0.9.0
	 * @param string $taxonomy Taxonomy name.
	 * @param string $term_value Term value.
	 * @param string $taxonomy_format Taxonomy format.
	 * @param array  $taxonomy_format_validation Taxonomy format validation.
	 * @return array<int, int>
	 */
	public function resolve_term_ids_for_term_value(
		string $taxonomy,
		string $term_value,
		string $taxonomy_format,
		array $taxonomy_format_validation
	): array {
		$term_ids = $this->get_taxonomy_util()->resolve_term_ids_from_value(
			$taxonomy,
			$term_value,
			$taxonomy_format,
			$taxonomy_format_validation
		);

		return $term_ids;
	}

	/**
	 * Apply meta fields for a post.
	 *
	 * @since 0.9.0
	 * @param wpdb  $wpdb WordPress DB instance.
	 * @param int   $post_id Post ID.
	 * @param array $meta_fields Meta fields.
	 * @param array $context Context values for row processing.
	 * @param array $dry_run_log Dry run log.
	 * @return void
	 */
	public function apply_meta_fields_for_post(
		wpdb $wpdb,
		int $post_id,
		array $meta_fields,
		array $context,
		array &$dry_run_log
	): void {
		$dry_run = $context['dry_run'];

		foreach ( $meta_fields as $key => $value ) {
			// Skip empty values.
			if ( '' === $value || null === $value ) {
				continue;
			}

			if ( ! is_string( $value ) ) {
				$value = maybe_serialize( $value );
			}

			if ( $dry_run ) {
				$dry_run_log_limit = (int) apply_filters( 'fe_csv_import_export_dry_run_log_limit', 50 );
				// Handle multi-value custom fields (pipe-separated with escaping).
				$values = $this->get_csv_util()->split_pipe_separated_values( $value );
				if ( count( $values ) > 1 ) {
					foreach ( array_map( 'trim', $values ) as $single_value ) {
						if ( '' !== $single_value ) {
							$dry_run_log[] = sprintf(
								/* translators: 1: field name, 2: field value */
								__( 'Custom field (multi-value): %1$s = %2$s', 'fe-csv-import-export' ),
								$key,
								$single_value
							);
							if ( count( $dry_run_log ) > $dry_run_log_limit ) {
								$dry_run_log = array_slice( $dry_run_log, -1 * $dry_run_log_limit );
							}
						}
					}
				} else {
					$single_value  = trim( (string) ( $values[0] ?? '' ) );
					$dry_run_log[] = sprintf(
						/* translators: 1: field name, 2: field value */
						__( 'Custom field: %1$s = %2$s', 'fe-csv-import-export' ),
						$key,
						$single_value
					);
					if ( count( $dry_run_log ) > $dry_run_log_limit ) {
						$dry_run_log = array_slice( $dry_run_log, -1 * $dry_run_log_limit );
					}
				}
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta}
	                         WHERE post_id = %d
	                         AND meta_key = %s",
					$post_id,
					$key
				)
			);

			$values = $this->get_csv_util()->split_pipe_separated_values( $value );
			if ( count( $values ) > 1 ) {
				foreach ( array_map( 'trim', $values ) as $single_value ) {
					if ( '' !== $single_value ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,
						// WordPress.DB.DirectDatabaseQuery.NoCaching,
						// WordPress.DB.SlowDBQuery.slow_db_query_meta_key,
						// WordPress.DB.SlowDBQuery.slow_db_query_meta_value.
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->insert(
							$wpdb->postmeta,
							[
								'post_id'    => $post_id,
								'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
								'meta_value' => $single_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
							],
							[ '%d', '%s', '%s' ]
						);
					}
				}
				continue;
			}

			$value = trim( (string) ( $values[0] ?? '' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,
			// WordPress.DB.DirectDatabaseQuery.NoCaching,
			// WordPress.DB.SlowDBQuery.slow_db_query_meta_key,
			// WordPress.DB.SlowDBQuery.slow_db_query_meta_value.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$wpdb->postmeta,
				[
					'post_id'    => $post_id,
					'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => is_string( $value ) ? $value : maybe_serialize( $value ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				],
				[ '%d', '%s', '%s' ]
			);
		}
	}

	/**
	 * Apply prepared meta fields for a batch.
	 *
	 * @since 0.9.0
	 * @param wpdb  $wpdb WordPress DB instance.
	 * @param array $items Prepared batch items.
	 * @param array $context Context values for row processing.
	 * @param array $dry_run_log Dry run log.
	 * @return void
	 */
	private function apply_prepared_meta_fields_for_batch(
		wpdb $wpdb,
		array $items,
		array $context,
		array &$dry_run_log
	): void {
		$dry_run     = (bool) ( $context['dry_run'] ?? false );
		$delete_keys = [];
		$insert_rows = [];

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$post_id     = (int) ( $item['post_id'] ?? 0 );
			$meta_fields = isset( $item['meta_fields'] ) && is_array( $item['meta_fields'] ) ? $item['meta_fields'] : [];
			if ( empty( $meta_fields ) || ( $post_id <= 0 && ! $dry_run ) ) {
				continue;
			}

			foreach ( $meta_fields as $key => $value ) {
				if ( '' === $value || null === $value ) {
					continue;
				}

				if ( ! is_string( $value ) ) {
					$value = maybe_serialize( $value );
				}

				if ( $dry_run ) {
					$this->append_meta_dry_run_log( (string) $key, (string) $value, $dry_run_log );
					continue;
				}

				$delete_keys[ $post_id . "\n" . $key ] = [
					'post_id'  => $post_id,
					'meta_key' => (string) $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				];

				$values = $this->get_csv_util()->split_pipe_separated_values( $value );
				foreach ( array_map( 'trim', $values ) as $single_value ) {
					if ( '' === $single_value ) {
						continue;
					}
					$insert_rows[] = [
						'post_id'    => $post_id,
						'meta_key'   => (string) $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value' => $single_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					];
				}
			}
		}

		if ( $dry_run || empty( $delete_keys ) ) {
			return;
		}

		foreach ( array_chunk( array_values( $delete_keys ), 200 ) as $delete_chunk ) {
			$where_parts = [];
			$params      = [];
			foreach ( $delete_chunk as $delete_item ) {
				$where_parts[] = '(post_id = %d AND meta_key = %s)';
				$params[]      = (int) $delete_item['post_id'];
				$params[]      = (string) $delete_item['meta_key'];
			}

			$sql = "DELETE FROM {$wpdb->postmeta} WHERE " . implode( ' OR ', $where_parts );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $wpdb->prepare( $sql, $params ) );
		}

		foreach ( array_chunk( $insert_rows, 500 ) as $insert_chunk ) {
			$placeholders = [];
			$params       = [];
			foreach ( $insert_chunk as $insert_row ) {
				$placeholders[] = '(%d, %s, %s)';
				$params[]       = (int) $insert_row['post_id'];
				$params[]       = (string) $insert_row['meta_key'];
				$params[]       = (string) $insert_row['meta_value'];
			}

			if ( empty( $placeholders ) ) {
				continue;
			}

			$sql = "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ', ', $placeholders );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $wpdb->prepare( $sql, $params ) );
		}
	}

	/**
	 * Append meta dry run log.
	 *
	 * @since 0.9.0
	 * @param string $key Meta key.
	 * @param string $value Meta value.
	 * @param array  $dry_run_log Dry run log.
	 * @return void
	 */
	private function append_meta_dry_run_log( string $key, string $value, array &$dry_run_log ): void {
		$dry_run_log_limit = (int) apply_filters( 'fe_csv_import_export_dry_run_log_limit', 50 );
		$values            = $this->get_csv_util()->split_pipe_separated_values( $value );
		if ( count( $values ) > 1 ) {
			foreach ( array_map( 'trim', $values ) as $single_value ) {
				if ( '' === $single_value ) {
					continue;
				}
				$dry_run_log[] = sprintf(
					/* translators: 1: field name, 2: field value */
					__( 'Custom field (multi-value): %1$s = %2$s', 'fe-csv-import-export' ),
					$key,
					$single_value
				);
				if ( count( $dry_run_log ) > $dry_run_log_limit ) {
					$dry_run_log = array_slice( $dry_run_log, -1 * $dry_run_log_limit );
				}
			}
			return;
		}

		$single_value  = trim( (string) ( $values[0] ?? '' ) );
		$dry_run_log[] = sprintf(
			/* translators: 1: field name, 2: field value */
			__( 'Custom field: %1$s = %2$s', 'fe-csv-import-export' ),
			$key,
			$single_value
		);
		if ( count( $dry_run_log ) > $dry_run_log_limit ) {
			$dry_run_log = array_slice( $dry_run_log, -1 * $dry_run_log_limit );
		}
	}

	/**
	 * Normalize header/field name.
	 *
	 * @since 0.9.0
	 * @param string $name Field name.
	 * @return string
	 */
	private function normalize_field_name( string $name ): string {
		return $this->get_csv_util()->normalize_field_name( $name );
	}
}
