<?php
/**
 * Import Taxonomy Writer (Direct SQL)
 *
 * Direct SQL taxonomy writing strategy for Swift CSV import.
 *
 * @since 0.9.10
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import taxonomy writer (Direct SQL).
 *
 * @since 0.9.10
 * @package Swift_CSV
 */
class Swift_CSV_Import_Taxonomy_Writer_Direct_SQL implements Swift_CSV_Import_Taxonomy_Writer_Interface {
	/**
	 * Apply taxonomy terms for a post.
	 *
	 * Behavior:
	 * - Resolve existing terms by taxonomy + name (or ID mode).
	 * - Create missing terms (and term_taxonomy rows).
	 * - Overwrite relationships for the taxonomy.
	 * - Update term counts.
	 *
	 * @since 0.9.10
	 * @param wpdb  $wpdb WordPress database handler.
	 * @param int   $post_id Post ID.
	 * @param array $taxonomies Taxonomy terms map.
	 * @param array $context Context values for row processing.
	 * @param array $dry_run_log Dry run log.
	 * @return void
	 */
	public function apply_taxonomies_for_post( wpdb $wpdb, int $post_id, array $taxonomies, array $context, array &$dry_run_log ): void {
		$taxonomy_format            = (string) ( $context['taxonomy_format'] ?? 'name' );
		$taxonomy_format_validation = (array) ( $context['taxonomy_format_validation'] ?? [] );
		$dry_run                    = (bool) ( $context['dry_run'] ?? false );

		foreach ( $taxonomies as $taxonomy => $terms ) {
			if ( ! is_string( $taxonomy ) || '' === $taxonomy || ! is_array( $terms ) || empty( $terms ) ) {
				continue;
			}
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$term_taxonomy_ids = $this->resolve_or_create_term_taxonomy_ids(
				$wpdb,
				$taxonomy,
				$terms,
				$taxonomy_format,
				$taxonomy_format_validation,
				$dry_run,
				$dry_run_log
			);

			if ( $dry_run ) {
				continue;
			}

			$this->overwrite_term_relationships_for_taxonomy( $wpdb, $post_id, $taxonomy, $term_taxonomy_ids );
			$this->update_term_taxonomy_counts( $wpdb, $taxonomy, $term_taxonomy_ids );
		}
	}

	/**
	 * Resolve (and create if missing) term_taxonomy_ids for a taxonomy.
	 *
	 * @since 0.9.10
	 * @param wpdb   $wpdb WordPress database handler.
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $terms Term values.
	 * @param string $taxonomy_format Taxonomy format.
	 * @param array  $taxonomy_format_validation Validation map.
	 * @param bool   $dry_run Whether this is dry run.
	 * @param array  $dry_run_log Dry run log.
	 * @return array<int,int> term_taxonomy_id list.
	 */
	private function resolve_or_create_term_taxonomy_ids(
		wpdb $wpdb,
		string $taxonomy,
		array $terms,
		string $taxonomy_format,
		array $taxonomy_format_validation,
		bool $dry_run,
		array &$dry_run_log
	): array {
		$term_taxonomy_ids = [];

		foreach ( $terms as $term_value ) {
			$term_value = trim( (string) $term_value );
			if ( '' === $term_value ) {
				continue;
			}

			$resolved = $this->resolve_term_taxonomy_id_from_value( $wpdb, $taxonomy, $term_value, $taxonomy_format, $taxonomy_format_validation );
			if ( $resolved > 0 ) {
				$term_taxonomy_ids[] = $resolved;
				continue;
			}

			if ( $dry_run ) {
				$dry_run_log[] = sprintf(
					/* translators: 1: term name, 2: taxonomy name */
					__( 'New term will be created: %1$s (taxonomy: %2$s)', 'swift-csv' ),
					$term_value,
					$taxonomy
				);
				continue;
			}

			$created_term_taxonomy_id = $this->create_term_and_taxonomy( $wpdb, $taxonomy, $term_value );
			if ( $created_term_taxonomy_id > 0 ) {
				$term_taxonomy_ids[] = $created_term_taxonomy_id;
			}
		}

		$term_taxonomy_ids = array_values( array_unique( array_filter( array_map( 'intval', $term_taxonomy_ids ) ) ) );
		return $term_taxonomy_ids;
	}

	/**
	 * Resolve term_taxonomy_id from input value.
	 *
	 * @since 0.9.10
	 * @param wpdb   $wpdb WordPress database handler.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $term_value Term value.
	 * @param string $taxonomy_format Taxonomy format.
	 * @param array  $taxonomy_format_validation Validation map.
	 * @return int term_taxonomy_id or 0.
	 */
	private function resolve_term_taxonomy_id_from_value(
		wpdb $wpdb,
		string $taxonomy,
		string $term_value,
		string $taxonomy_format,
		array $taxonomy_format_validation
	): int {
		if ( 'id' === $taxonomy_format ) {
			$term_id = (int) $term_value;
			if ( 0 === $term_id ) {
				$validation = $taxonomy_format_validation[ $taxonomy ] ?? null;
				if ( is_array( $validation ) && ! empty( $validation['mixed'] ) ) {
					return $this->find_term_taxonomy_id_by_name( $wpdb, $taxonomy, $term_value );
				}
				return 0;
			}
			return $this->find_term_taxonomy_id_by_term_id( $wpdb, $taxonomy, $term_id );
		}

		return $this->find_term_taxonomy_id_by_name( $wpdb, $taxonomy, $term_value );
	}

	/**
	 * Find term_taxonomy_id by term name.
	 *
	 * @since 0.9.10
	 * @param wpdb   $wpdb WordPress database handler.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $name Term name.
	 * @return int term_taxonomy_id or 0.
	 */
	private function find_term_taxonomy_id_by_name( wpdb $wpdb, string $taxonomy, string $name ): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tt.term_taxonomy_id
					FROM {$wpdb->terms} t
					INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
					WHERE tt.taxonomy = %s AND t.name = %s
					LIMIT 1",
				$taxonomy,
				$name
			)
		);
		return (int) $found;
	}

	/**
	 * Find term_taxonomy_id by term_id.
	 *
	 * @since 0.9.10
	 * @param wpdb   $wpdb WordPress database handler.
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $term_id Term ID.
	 * @return int term_taxonomy_id or 0.
	 */
	private function find_term_taxonomy_id_by_term_id( wpdb $wpdb, string $taxonomy, int $term_id ): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tt.term_taxonomy_id
					FROM {$wpdb->term_taxonomy} tt
					WHERE tt.taxonomy = %s AND tt.term_id = %d
					LIMIT 1",
				$taxonomy,
				$term_id
			)
		);
		return (int) $found;
	}

	/**
	 * Create a term and its term_taxonomy row.
	 *
	 * Uses sanitize_title() for slug generation and adds numeric suffix to avoid
	 * collisions within the taxonomy.
	 *
	 * @since 0.9.10
	 * @param wpdb   $wpdb WordPress database handler.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $name Term name.
	 * @return int term_taxonomy_id or 0.
	 */
	private function create_term_and_taxonomy( wpdb $wpdb, string $taxonomy, string $name ): int {
		$name = trim( $name );
		if ( '' === $name ) {
			return 0;
		}

		$base_slug = sanitize_title( $name );
		if ( '' === $base_slug ) {
			$base_slug = 'term';
		}

		$slug = $this->generate_unique_slug_for_taxonomy( $wpdb, $taxonomy, $base_slug );

		// Insert into wp_terms.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$wpdb->terms,
			[
				'name'       => $name,
				'slug'       => $slug,
				'term_group' => 0,
			],
			[ '%s', '%s', '%d' ]
		);

		if ( false === $inserted ) {
			return 0;
		}

		$term_id = (int) $wpdb->insert_id;
		if ( $term_id <= 0 ) {
			return 0;
		}

		// Insert into wp_term_taxonomy.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted_tax = $wpdb->insert(
			$wpdb->term_taxonomy,
			[
				'term_id'     => $term_id,
				'taxonomy'    => $taxonomy,
				'description' => '',
				'parent'      => 0,
				'count'       => 0,
			],
			[ '%d', '%s', '%s', '%d', '%d' ]
		);

		if ( false === $inserted_tax ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Generate a unique slug within a taxonomy.
	 *
	 * @since 0.9.10
	 * @param wpdb   $wpdb WordPress database handler.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $base_slug Base slug.
	 * @return string Unique slug.
	 */
	private function generate_unique_slug_for_taxonomy( wpdb $wpdb, string $taxonomy, string $base_slug ): string {
		$slug    = $base_slug;
		$suffix  = 2;
		$max_try = 100;

		while ( $this->slug_exists_in_taxonomy( $wpdb, $taxonomy, $slug ) ) {
			$slug = $base_slug . '-' . $suffix;
			++$suffix;
			--$max_try;
			if ( $max_try <= 0 ) {
				break;
			}
		}

		return $slug;
	}

	/**
	 * Check if a slug exists within a taxonomy.
	 *
	 * @since 0.9.10
	 * @param wpdb   $wpdb WordPress database handler.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $slug Slug.
	 * @return bool True if exists.
	 */
	private function slug_exists_in_taxonomy( wpdb $wpdb, string $taxonomy, string $slug ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1
					FROM {$wpdb->terms} t
					INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
					WHERE tt.taxonomy = %s AND t.slug = %s
					LIMIT 1",
				$taxonomy,
				$slug
			)
		);
		return ! empty( $found );
	}

	/**
	 * Overwrite term relationships for a taxonomy.
	 *
	 * @since 0.9.10
	 * @param wpdb           $wpdb WordPress database handler.
	 * @param int            $post_id Post ID.
	 * @param string         $taxonomy Taxonomy name.
	 * @param array<int,int> $term_taxonomy_ids Term taxonomy IDs.
	 * @return void
	 */
	private function overwrite_term_relationships_for_taxonomy( wpdb $wpdb, int $post_id, string $taxonomy, array $term_taxonomy_ids ): void {
		// Delete existing relationships for this taxonomy.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE tr
					FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					WHERE tr.object_id = %d AND tt.taxonomy = %s",
				$post_id,
				$taxonomy
			)
		);

		if ( empty( $term_taxonomy_ids ) ) {
			return;
		}

		foreach ( $term_taxonomy_ids as $term_taxonomy_id ) {
			$term_taxonomy_id = (int) $term_taxonomy_id;
			if ( $term_taxonomy_id <= 0 ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$wpdb->term_relationships,
				[
					'object_id'        => $post_id,
					'term_taxonomy_id' => $term_taxonomy_id,
					'term_order'       => 0,
				],
				[ '%d', '%d', '%d' ]
			);
		}
	}

	/**
	 * Update term_taxonomy counts for the affected term_taxonomy_ids.
	 *
	 * @since 0.9.10
	 * @param wpdb           $wpdb WordPress database handler.
	 * @param string         $taxonomy Taxonomy name.
	 * @param array<int,int> $term_taxonomy_ids Term taxonomy IDs.
	 * @return void
	 */
	private function update_term_taxonomy_counts( wpdb $wpdb, string $taxonomy, array $term_taxonomy_ids ): void {
		$term_taxonomy_ids = array_values( array_unique( array_filter( array_map( 'intval', $term_taxonomy_ids ) ) ) );
		if ( empty( $term_taxonomy_ids ) ) {
			return;
		}

		foreach ( $term_taxonomy_ids as $term_taxonomy_id ) {
			$term_taxonomy_id = (int) $term_taxonomy_id;
			if ( $term_taxonomy_id <= 0 ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->term_taxonomy} tt
						SET tt.count = (
							SELECT COUNT(*) FROM {$wpdb->term_relationships} tr
							WHERE tr.term_taxonomy_id = tt.term_taxonomy_id
						)
						WHERE tt.taxonomy = %s AND tt.term_taxonomy_id = %d",
					$taxonomy,
					$term_taxonomy_id
				)
			);
		}
	}
}
