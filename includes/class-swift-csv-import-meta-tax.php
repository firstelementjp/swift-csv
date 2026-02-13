<?php
/**
 * Meta and taxonomy processing for Swift CSV import.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta and taxonomy processing for import rows.
 *
 * This class intentionally mirrors the existing behavior of the import handler
 * and is designed to be called from Swift_CSV_Ajax_Import via delegation.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_Import_Meta_Tax {
	/**
	 * Process meta fields and taxonomies for a post using explicit arguments.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                  $wpdb WordPress database handler.
	 * @param int                                                                                   $post_id Post ID.
	 * @param array<int, string>                                                                    $headers CSV headers.
	 * @param array<int, string>                                                                    $data CSV row data.
	 * @param array<int, string>                                                                    $allowed_post_fields Allowed WP post fields.
	 * @param string                                                                                $taxonomy_format Taxonomy format.
	 * @param array                                                                                 $taxonomy_format_validation Taxonomy format validation.
	 * @param bool                                                                                  $dry_run Dry run flag.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>} $counters Counters (by reference).
	 * @return array{meta_fields:array<string,string>,taxonomies:array<string,array<int,string>>}
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

		// Process custom fields and taxonomies like original Swift CSV
		$collected_fields = $this->collect_taxonomies_and_meta_fields_from_row( $headers, $data, $allowed_post_fields );
		/**
		 * Filter collected fields before processing.
		 *
		 * Allows extensions to process custom columns (e.g., acf_, custom_) before they are saved.
		 * This hook is called after basic field collection but before database operations.
		 *
		 * @since 0.9.0
		 * @param array{meta_fields:array<string,string>,taxonomies:array<string,array<int,string>>,taxonomy_term_ids:array<string,array<int,int>>} $collected_fields Collected fields.
		 * @param array<int, string> $headers CSV headers.
		 * @param array $data CSV row data.
		 * @param array<int, string> $allowed_post_fields Allowed WP post fields.
		 * @return array{meta_fields:array<string,string>,taxonomies:array<string,array<int,string>>,taxonomy_term_ids:array<string,array<int,int>>} Modified collected fields.
		 */
		$collected_fields = apply_filters( 'swift_csv_filter_collected_fields', $collected_fields, $headers, $data, $allowed_post_fields );
		$taxonomies       = $collected_fields['taxonomies'];
		$meta_fields      = $collected_fields['meta_fields'];

		$context = [
			'post_id'                    => $post_id,
			'dry_run'                    => $dry_run,
			'headers'                    => $headers,
			'data'                       => $data,
			'allowed_post_fields'        => $allowed_post_fields,
			'taxonomy_format'            => $taxonomy_format,
			'taxonomy_format_validation' => $taxonomy_format_validation,
		];

		// Process taxonomies
		$this->apply_taxonomies_for_post( $post_id, $taxonomies, $context, $dry_run_log );

		// Process custom fields with multi-value support
		$this->apply_meta_fields_for_post( $wpdb, $post_id, $meta_fields, $context, $dry_run_log );

		return [
			'meta_fields' => $meta_fields,
			'taxonomies'  => $taxonomies,
		];
	}

	/**
	 * Process meta fields and taxonomies for a post.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                                                                                                                            $wpdb WordPress database handler.
	 * @param array{post_id:int,post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array} $context Context values for row processing.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                                                                                           $counters Counters (by reference).
	 * @return array{meta_fields:array<string,string>,taxonomies:array<string,array<int,string>>}
	 */
	public function process_meta_and_taxonomies_for_row( wpdb $wpdb, array $context, array &$counters ): array {
		return $this->process_meta_and_taxonomies_for_row_with_args(
			$wpdb,
			$context['post_id'],
			$context['headers'],
			$context['data'],
			$context['allowed_post_fields'],
			$context['taxonomy_format'],
			$context['taxonomy_format_validation'],
			$context['dry_run'],
			$counters
		);
	}

	/**
	 * Collect taxonomy fields from a CSV row.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $headers CSV headers.
	 * @param array<int, string> $data CSV row data.
	 * @return array{taxonomies:array<string,array<int,string>>,taxonomy_term_ids:array<string,array<int,int>>}
	 */
	public function collect_taxonomy_fields_from_row( array $headers, array $data ): array {
		$taxonomies        = [];
		$taxonomy_term_ids = [];

		for ( $j = 0; $j < count( $headers ); $j++ ) {
			$header_name            = $headers[ $j ] ?? '';
			$header_name_normalized = $this->normalize_field_name( (string) $header_name );

			// Skip empty headers and ID
			if ( $header_name_normalized === '' || $header_name_normalized === 'ID' ) {
				continue;
			}

			if ( strpos( $header_name_normalized, 'tax_' ) !== 0 ) {
				continue;
			}

			if ( empty( trim( $data[ $j ] ?? '' ) ) ) {
				continue; // Skip empty fields
			}

			$meta_value = (string) ( $data[ $j ] ?? '' );

			// Handle taxonomy (pipe-separated)
			$terms                        = array_values( array_filter( array_map( 'trim', explode( '|', $meta_value ) ), 'strlen' ) );
			$taxonomy_name                = substr( $header_name_normalized, 4 ); // Remove 'tax_'
			$taxonomies[ $taxonomy_name ] = $terms;

			if ( taxonomy_exists( $taxonomy_name ) ) {
				$term_ids = [];
				foreach ( $terms as $term_name ) {
					if ( ! empty( $term_name ) ) {
						$term = get_term_by( 'name', $term_name, $taxonomy_name );
						if ( $term ) {
							$term_ids[] = $term->term_id;
						}
					}
				}
				if ( ! empty( $term_ids ) ) {
					$taxonomy_term_ids[ $taxonomy_name ] = $term_ids;
				}
			}
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

		for ( $j = 0; $j < count( $headers ); $j++ ) {
			$header_name            = $headers[ $j ] ?? '';
			$header_name_normalized = $this->normalize_field_name( (string) $header_name );

			// Skip empty headers and post fields
			if ( $header_name_normalized === '' || $header_name_normalized === 'ID' ) {
				continue;
			}
			if ( in_array( $header_name_normalized, $allowed_post_fields, true ) ) {
				continue;
			}

			if ( empty( trim( $data[ $j ] ?? '' ) ) ) {
				continue; // Skip empty fields
			}

			$meta_value = (string) ( $data[ $j ] ?? '' );

			// Handle regular custom fields (cf_<field> => <field>) ONLY
			if ( strpos( $header_name_normalized, 'cf_' ) !== 0 ) {
				continue; // Skip non-cf_ fields
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
	 * @return array{meta_fields:array<string,string>,taxonomies:array<string,array<int,string>>,taxonomy_term_ids:array<string,array<int,int>>}
	 */
	public function collect_taxonomies_and_meta_fields_from_row( array $headers, array $data, array $allowed_post_fields ): array {
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
	public function resolve_taxonomy_term_ids( string $taxonomy, array $terms, string $taxonomy_format, array $taxonomy_format_validation ): array {
		$term_ids = [];
		foreach ( $terms as $term_value ) {
			$term_value = trim( (string) $term_value );
			if ( $term_value === '' ) {
				continue;
			}

			$resolved_term_ids = $this->resolve_term_ids_for_term_value( $taxonomy, $term_value, $taxonomy_format, $taxonomy_format_validation );
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
	public function apply_taxonomy_terms_to_post( int $post_id, string $taxonomy, array $term_ids, bool $dry_run, array &$dry_run_log ): void {
		if ( empty( $term_ids ) ) {
			return;
		}

		if ( $dry_run ) {
			error_log( "[Dry Run] Would set terms for post {$post_id}: " . implode( ', ', $term_ids ) );
			foreach ( $term_ids as $term_id ) {
				$term = get_term( $term_id, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$dry_run_log[] = sprintf(
						/* translators: 1: term name, 2: term ID, 3: taxonomy name */
						__( 'Existing term: %1$s (ID: %2$s, taxonomy: %3$s)', 'swift-csv' ),
						$term->name,
						$term_id,
						$taxonomy
					);
				}
			}
			return;
		}

		wp_set_post_terms( $post_id, $term_ids, $taxonomy, false );
		error_log( "[Swift CSV] Set terms for post {$post_id}: " . implode( ', ', $term_ids ) );
	}

	/**
	 * Apply taxonomy terms for a post.
	 *
	 * @since 0.9.0
	 * @param int                                                                                                                                                                                             $post_id Post ID.
	 * @param array<string, array<int, string>>                                                                                                                                                               $taxonomies Taxonomy terms map.
	 * @param array{post_id:int,post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array} $context Context values for row processing.
	 * @param array<int, string>                                                                                                                                                                              $dry_run_log Dry run log.
	 * @return void
	 */
	public function apply_taxonomies_for_post( int $post_id, array $taxonomies, array $context, array &$dry_run_log ): void {
		$taxonomy_format            = $context['taxonomy_format'];
		$taxonomy_format_validation = $context['taxonomy_format_validation'];
		$dry_run                    = $context['dry_run'];

		foreach ( $taxonomies as $taxonomy => $terms ) {
			if ( ! is_string( $taxonomy ) || ! is_array( $terms ) || empty( $terms ) ) {
				continue;
			}

			if ( ! empty( $terms ) ) {
				error_log( "[Swift CSV] Processing taxonomy: {$taxonomy}, format: {$taxonomy_format}" );

				$term_ids = $this->resolve_taxonomy_term_ids( $taxonomy, $terms, $taxonomy_format, $taxonomy_format_validation );
				$this->apply_taxonomy_terms_to_post( $post_id, $taxonomy, $term_ids, $dry_run, $dry_run_log );
			}
		}
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
	public function resolve_term_ids_for_term_value( string $taxonomy, string $term_value, string $taxonomy_format, array $taxonomy_format_validation ): array {
		error_log( "[Swift CSV] Processing term value: '{$term_value}' with format: {$taxonomy_format}" );

		$term_ids = Swift_CSV_Helper::resolve_term_ids_from_value( $taxonomy, $term_value, $taxonomy_format, $taxonomy_format_validation );

		error_log( '[Swift CSV] Resolved ' . count( $term_ids ) . " term IDs for value '{$term_value}'" );

		return $term_ids;
	}

	/**
	 * Apply meta fields for a post.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                                                                                                                            $wpdb WordPress DB instance.
	 * @param int                                                                                                                                                                                             $post_id Post ID.
	 * @param array<string, mixed>                                                                                                                                                                            $meta_fields Meta fields.
	 * @param array{post_id:int,post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array} $context Context values for row processing.
	 * @param array<int, string>                                                                                                                                                                              $dry_run_log Dry run log.
	 * @return void
	 */
	public function apply_meta_fields_for_post( wpdb $wpdb, int $post_id, array $meta_fields, array $context, array &$dry_run_log ): void {
		$dry_run = $context['dry_run'];

		foreach ( $meta_fields as $key => $value ) {
			// Skip empty values
			if ( $value === '' || $value === null ) {
				continue;
			}

			if ( ! is_string( $value ) ) {
				$value = maybe_serialize( $value );
			}

			if ( $dry_run ) {
				error_log( "[Dry Run] Would process custom field: {$key} = {$value}" );

				// Handle multi-value custom fields (pipe-separated)
				if ( strpos( $value, '|' ) !== false ) {
					$values = array_map( 'trim', explode( '|', $value ) );
					foreach ( $values as $single_value ) {
						if ( $single_value !== '' ) {
							$dry_run_log[] = sprintf(
								/* translators: 1: field name, 2: field value */
								__( 'Custom field (multi-value): %1$s = %2$s', 'swift-csv' ),
								$key,
								$single_value
							);
						}
					}
				} else {
					$dry_run_log[] = sprintf(
						/* translators: 1: field name, 2: field value */
						__( 'Custom field: %1$s = %2$s', 'swift-csv' ),
						$key,
						$value
					);
				}
				continue;
			}

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta} 
	                         WHERE post_id = %d 
	                         AND meta_key = %s",
					$post_id,
					$key
				)
			);

			if ( strpos( $value, '|' ) !== false ) {
				$values = array_map( 'trim', explode( '|', $value ) );
				foreach ( $values as $single_value ) {
					if ( $single_value !== '' ) {
						$wpdb->insert(
							$wpdb->postmeta,
							[
								'post_id'    => $post_id,
								'meta_key'   => $key,
								'meta_value' => $single_value,
							],
							[ '%d', '%s', '%s' ]
						);
					}
				}
				continue;
			}

			$wpdb->insert(
				$wpdb->postmeta,
				[
					'post_id'    => $post_id,
					'meta_key'   => $key,
					'meta_value' => is_string( $value ) ? $value : maybe_serialize( $value ),
				],
				[ '%d', '%s', '%s' ]
			);
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
		return Swift_CSV_Helper::normalize_field_name( $name );
	}
}
