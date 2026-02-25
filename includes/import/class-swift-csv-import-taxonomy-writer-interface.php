<?php
/**
 * Import Taxonomy Writer Interface
 *
 * Defines the taxonomy writing strategy for Swift CSV import.
 *
 * @since 0.9.10
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import taxonomy writer interface.
 *
 * @since 0.9.10
 * @package Swift_CSV
 */
interface Swift_CSV_Import_Taxonomy_Writer_Interface {
	/**
	 * Apply taxonomy terms for a post.
	 *
	 * @since 0.9.10
	 * @param wpdb                              $wpdb WordPress database handler.
	 * @param int                               $post_id Post ID.
	 * @param array<string, array<int, string>> $taxonomies Taxonomy terms map.
	 * @param array                             $context Context values for row processing.
	 * @param array                             $dry_run_log Dry run log.
	 * @return void
	 */
	public function apply_taxonomies_for_post( wpdb $wpdb, int $post_id, array $taxonomies, array $context, array &$dry_run_log ): void;
}
