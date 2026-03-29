<?php
/**
 * Import Taxonomy Writer (WP)
 *
 * WP-compatible taxonomy writing strategy for Swift CSV import.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import taxonomy writer (WP compatible).
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Import_Taxonomy_Writer_WP implements Swift_CSV_Import_Taxonomy_Writer_Interface {
	/**
	 * Taxonomy utility instance.
	 *
	 * @since 0.9.8
	 * @var object|null
	 */
	private $taxonomy_util;

	/**
	 * Constructor.
	 *
	 * @since 0.9.8
	 * @param object|null $taxonomy_util Taxonomy util.
	 */
	public function __construct( ?Swift_CSV_Import_Taxonomy_Util $taxonomy_util = null ) {
		$this->taxonomy_util = $taxonomy_util;
	}

	/**
	 * Apply taxonomy terms for a post.
	 *
	 * @since 0.9.8
	 * @param wpdb  $wpdb WordPress database handler.
	 * @param int   $post_id Post ID.
	 * @param array $taxonomies Taxonomy terms map.
	 * @param array $context Context values for row processing.
	 * @param array $dry_run_log Dry run log.
	 * @return void
	 */
	public function apply_taxonomies_for_post(
		wpdb $wpdb,
		int $post_id,
		array $taxonomies,
		array $context,
		array &$dry_run_log
	): void {
		$taxonomy_format            = $context['taxonomy_format'];
		$taxonomy_format_validation = $context['taxonomy_format_validation'];
		$dry_run                    = $context['dry_run'];

		foreach ( $taxonomies as $taxonomy => $terms ) {
			if ( ! is_string( $taxonomy ) || ! is_array( $terms ) || empty( $terms ) ) {
				continue;
			}

			$term_ids = $this->resolve_taxonomy_term_ids( $taxonomy, $terms, $taxonomy_format, $taxonomy_format_validation );
			$this->apply_taxonomy_terms_to_post( $post_id, $taxonomy, $term_ids, $dry_run, $dry_run_log );
		}
	}

	/**
	 * Resolve term IDs for a single taxonomy.
	 *
	 * @since 0.9.8
	 * @param string             $taxonomy Taxonomy name.
	 * @param array<int, string> $terms Term values.
	 * @param string             $taxonomy_format Taxonomy format.
	 * @param array              $taxonomy_format_validation Taxonomy format validation.
	 * @return array<int, int>
	 */
	private function resolve_taxonomy_term_ids(
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
	 * Resolve term IDs from a term value.
	 *
	 * @since 0.9.8
	 * @param string $taxonomy Taxonomy name.
	 * @param string $term_value Term value.
	 * @param string $taxonomy_format Taxonomy format.
	 * @param array  $taxonomy_format_validation Taxonomy format validation.
	 * @return array<int, int>
	 */
	private function resolve_term_ids_for_term_value(
		string $taxonomy,
		string $term_value,
		string $taxonomy_format,
		array $taxonomy_format_validation
	): array {
		return $this->get_taxonomy_util()->resolve_term_ids_from_value(
			$taxonomy,
			$term_value,
			$taxonomy_format,
			$taxonomy_format_validation
		);
	}

	/**
	 * Get taxonomy utility instance.
	 *
	 * @since 0.9.8
	 * @return object
	 */
	private function get_taxonomy_util(): Swift_CSV_Import_Taxonomy_Util {
		if ( null === $this->taxonomy_util ) {
			$this->taxonomy_util = new Swift_CSV_Import_Taxonomy_Util();
		}
		return $this->taxonomy_util;
	}

	/**
	 * Apply taxonomy terms to a post.
	 *
	 * @since 0.9.8
	 * @param int                $post_id Post ID.
	 * @param string             $taxonomy Taxonomy name.
	 * @param array<int, int>    $term_ids Term IDs.
	 * @param bool               $dry_run Dry run flag.
	 * @param array<int, string> $dry_run_log Dry run log.
	 * @return void
	 */
	private function apply_taxonomy_terms_to_post(
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
			$dry_run_log_limit = (int) apply_filters( 'swift_csv_dry_run_log_limit', 50 );
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
					if ( count( $dry_run_log ) > $dry_run_log_limit ) {
						$dry_run_log = array_slice( $dry_run_log, -1 * $dry_run_log_limit );
					}
				}
			}
			return;
		}

		wp_set_post_terms( $post_id, $term_ids, $taxonomy, false );
	}
}
