<?php
/**
 * Direct SQL Export Class for Swift CSV
 *
 * High-performance CSV export using direct SQL queries.
 * Extends base export class with SQL-based data retrieval.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Direct SQL Export Class
 *
 * Handles high-performance CSV export using direct SQL queries.
 * This class bypasses WordPress functions for maximum performance.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Export_Direct_SQL extends Swift_CSV_Export_Base {

	/**
	 * Cached posts data to avoid multiple database queries
	 *
	 * @since 0.9.8
	 * @var array
	 */
	private $cached_posts_data = null;

	/**
	 * Get posts data using direct SQL with taxonomy optimization
	 *
	 * @since 0.9.8
	 * @return array Posts data with taxonomy information.
	 */
	protected function get_posts_data() {
		global $wpdb;

		// Return cached data if available
		if ( null !== $this->cached_posts_data ) {
			return $this->cached_posts_data;
		}

		$limit_clause = '';
		if ( $this->config['export_limit'] > 0 ) {
			$limit_clause = $wpdb->prepare( 'LIMIT %d', $this->config['export_limit'] );
		}

		// Build query based on status type.
		$statuses = $this->config['post_status'];

		if ( is_array( $statuses ) ) {
			// Multiple statuses - disable taxonomy JOIN to restore server.
			$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$query               = "SELECT p.ID, p.post_title, p.post_content, p.post_status, 
							p.post_date, p.post_modified, p.post_name, p.post_excerpt,
							p.post_author, p.comment_count, p.menu_order, p.post_type,
							p.post_parent, p.comment_status, p.ping_status, p.post_password
						FROM {$wpdb->posts} p
						WHERE p.post_type = %s 
						AND p.post_status IN ({$status_placeholders})
						ORDER BY p.post_date DESC
						{$limit_clause}";

			// Use call_user_func_array to pass all status parameters.
			$params = array_merge( [ $this->config['post_type'] ], $statuses );
			$query  = call_user_func_array( [ $wpdb, 'prepare' ], array_merge( [ $query ], $params ) );
		} else {
			// Single status - disable taxonomy JOIN to restore server.
			$query = "SELECT p.ID, p.post_title, p.post_content, p.post_status, 
							p.post_date, p.post_modified, p.post_name, p.post_excerpt,
							p.post_author, p.comment_count, p.menu_order, p.post_type,
							p.post_parent, p.comment_status, p.ping_status, p.post_password
						FROM {$wpdb->posts} p
						WHERE p.post_type = %s 
						AND p.post_status = %s
						ORDER BY p.post_date DESC
						{$limit_clause}";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $wpdb->prepare( $query, $this->config['post_type'], $statuses );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$posts_data = $wpdb->get_results( $query, ARRAY_A );

		// Step 2: Get taxonomy data for these posts.
		$taxonomy_data = $this->get_taxonomy_data_for_posts( $posts_data );

		// Step 3: Merge posts and taxonomy data.
		$merged_data = $this->merge_posts_with_taxonomy( $posts_data, $taxonomy_data );

		// Cache the result
		$this->cached_posts_data = $merged_data;

		return $merged_data;
	}

	/**
	 * Get cached posts data
	 *
	 * @since 0.9.8
	 * @return array Cached posts data.
	 */
	private function get_cached_posts_data() {
		if ( null === $this->cached_posts_data ) {
			$this->cached_posts_data = $this->get_posts_data();
		}
		return $this->cached_posts_data;
	}

	/**
	 * Get taxonomy data for posts using separate query
	 *
	 * @since 0.9.8
	 * @param array $posts_data Posts data from step 1.
	 * @return array Taxonomy data indexed by post ID.
	 */
	private function get_taxonomy_data_for_posts( $posts_data ) {
		global $wpdb;

		if ( empty( $posts_data ) ) {
			return [];
		}

		// Extract post IDs
		$post_ids     = array_column( $posts_data, 'ID' );
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		// Get taxonomy data with GROUP_CONCAT and DISTINCT
		$query = "SELECT p.ID,
				       GROUP_CONCAT(DISTINCT
				         CONCAT_WS(':', tt.taxonomy, 
				           CASE 
				             WHEN %s = 'name' THEN t.name
				             ELSE CAST(t.term_id AS CHAR)
				           END
				         ) 
				         ORDER BY tt.taxonomy, t.name
				         SEPARATOR '|'
				       ) as taxonomy_data
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE p.ID IN ({$placeholders})
				GROUP BY p.ID";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$params = array_merge( [ $this->config['taxonomy_format'] ?? 'name' ], $post_ids );
		$query  = call_user_func_array( [ $wpdb, 'prepare' ], array_merge( [ $query ], $params ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query, ARRAY_A );

		// Convert to indexed array.
		$taxonomy_data = [];
		foreach ( $results as $row ) {
			$taxonomy_data[ $row['ID'] ] = $row['taxonomy_data'] ? explode( '|', $row['taxonomy_data'] ) : [];
		}

		return $taxonomy_data;
	}

	/**
	 * Merge posts data with taxonomy data
	 *
	 * @since 0.9.8
	 * @param array $posts_data Posts data from step 1.
	 * @param array $taxonomy_data Taxonomy data from step 2.
	 * @return array Merged data ready for CSV export.
	 */
	private function merge_posts_with_taxonomy( $posts_data, $taxonomy_data ) {
		$merged_data = [];

		foreach ( $posts_data as $post ) {
			$row = $post;

			// Add taxonomy columns.
			if ( isset( $taxonomy_data[ $post['ID'] ] ) && ! empty( $taxonomy_data[ $post['ID'] ] ) ) {
				$taxonomies = [];
				foreach ( $taxonomy_data[ $post['ID'] ] as $taxonomy_item ) {
					if ( ! empty( $taxonomy_item ) ) {
						$parts = explode( ':', $taxonomy_item, 2 );
						if ( count( $parts ) === 2 ) {
							list( $taxonomy, $term )   = $parts;
							$taxonomies[ $taxonomy ][] = $term;
						}
					}
				}

				// Convert taxonomy arrays to pipe-separated strings.
				foreach ( $taxonomies as $taxonomy => $terms ) {
					$row[ "tax_{$taxonomy}" ] = implode( '|', $terms );
				}
			}

			$merged_data[] = $row;
		}

		return $merged_data;
	}

	/**
	 * Get CSV headers including taxonomy columns
	 *
	 * @since 0.9.8
	 * @return array CSV headers.
	 */
	protected function get_csv_headers() {
		// Get base headers from parent (respects export scope for post fields)
		$headers = parent::get_csv_headers();

		// Always add taxonomy headers (follows traditional implementation)
		// Use cached posts data to extract all taxonomy columns
		$all_sample_data   = $this->get_cached_posts_data();
		$all_taxonomy_keys = [];
		foreach ( $all_sample_data as $sample_row ) {
			foreach ( $sample_row as $key => $value ) {
				if ( strpos( $key, 'tax_' ) === 0 ) {
					$all_taxonomy_keys[ $key ] = $key;
				}
			}
		}

		// Add unique taxonomy headers
		foreach ( $all_taxonomy_keys as $taxonomy_key ) {
			$headers[] = $taxonomy_key;
		}

		/**
		 * Filter CSV headers
		 *
		 * @since 0.9.0
		 * @param array $headers CSV headers.
		 * @param array $config Export configuration.
		 */
		return apply_filters( 'swift_csv_export_headers', $headers, $this->config );
	}

	/**
	 * Get CSV row data for Direct SQL export
	 *
	 * @since 0.9.8
	 * @param array $post Post data with taxonomy information.
	 * @return array CSV row data.
	 */
	protected function get_csv_row( $post ) {
		$row_data = [];

		// Get headers to match order
		$headers = $this->get_csv_headers();

		// Convert each field to CSV format
		foreach ( $headers as $header ) {
			$value = $post[ $header ] ?? '';

			// Handle text fields with CSV escaping
			if ( in_array( $header, [ 'post_title', 'post_content', 'post_excerpt' ], true ) ) {
				$value = '"' . str_replace( '"', '""', wp_strip_all_tags( $value ) ) . '"';
			} elseif ( strpos( $header, 'tax_' ) === 0 ) {
				// Taxonomy fields (already pipe-separated)
				$value = '"' . str_replace( '"', '""', $value ) . '"';
			} else {
				// Numeric and other fields
				$value = $value;
			}

			$row_data[] = $value;
		}

		return apply_filters( 'swift_csv_export_row', $row_data, $post, 'direct_sql' );
	}
}
