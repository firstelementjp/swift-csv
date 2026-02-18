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
	 * Get CSV headers for Direct SQL export
	 *
	 * Extends base headers to include taxonomy columns when enabled.
	 *
	 * @since 0.9.8
	 * @return array CSV headers.
	 */
	protected function get_csv_headers() {
		$headers = parent::get_csv_headers();

		// Always include taxonomies for Direct SQL
		$post_type   = $this->config['post_type'] ?? 'post';
		$taxonomies  = get_object_taxonomies( $post_type, 'objects' );
		$tax_headers = [];
		foreach ( $taxonomies as $taxonomy ) {
			$tax_headers[] = 'tax_' . $taxonomy->name;
		}
		$headers = array_merge( $headers, $tax_headers );

		return $headers;
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

		$query = "SELECT tr.object_id AS object_id, tt.taxonomy AS taxonomy, t.term_id AS term_id, t.name AS term_name
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE tr.object_id IN ({$placeholders})";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = call_user_func_array( [ $wpdb, 'prepare' ], array_merge( [ $query ], $post_ids ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query, ARRAY_A );

		$taxonomy_format = $this->config['taxonomy_format'] ?? 'name';
		$taxonomy_data   = [];
		foreach ( $results as $row ) {
			$post_id  = (int) ( $row['object_id'] ?? 0 );
			$taxonomy = (string) ( $row['taxonomy'] ?? '' );
			if ( 0 === $post_id || '' === $taxonomy ) {
				continue;
			}

			$term_value = ( 'id' === $taxonomy_format ) ? (string) ( $row['term_id'] ?? '' ) : (string) ( $row['term_name'] ?? '' );
			if ( '' === $term_value ) {
				continue;
			}

			$taxonomy_data[ $post_id ][] = $taxonomy . ':' . $term_value;
		}

		$post_type      = $this->config['post_type'] ?? 'post';
		$taxonomy_names = get_object_taxonomies( $post_type, 'names' );
		$taxonomy_names = is_array( $taxonomy_names ) ? array_values( array_filter( array_map( 'sanitize_key', $taxonomy_names ) ) ) : [];
		if ( ! empty( $taxonomy_names ) ) {
			$taxonomy_format = $this->config['taxonomy_format'] ?? 'name';
			$terms           = wp_get_object_terms(
				$post_ids,
				$taxonomy_names,
				[
					'fields' => 'all_with_object_id',
				]
			);

			if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$object_id = isset( $term->object_id ) ? (int) $term->object_id : 0;
					if ( 0 === $object_id ) {
						continue;
					}
					$taxonomy = isset( $term->taxonomy ) ? (string) $term->taxonomy : '';
					if ( '' === $taxonomy ) {
						continue;
					}
					$term_value = ( 'id' === $taxonomy_format ) ? (string) $term->term_id : (string) $term->name;
					if ( '' === $term_value ) {
						continue;
					}
					$taxonomy_data[ $object_id ][] = $taxonomy . ':' . $term_value;
				}
			}

			foreach ( $post_ids as $post_id ) {
				$post_id = (int) $post_id;
				if ( 0 === $post_id ) {
					continue;
				}
				if ( ! empty( $taxonomy_data[ $post_id ] ) ) {
					continue;
				}
				$post_terms = wp_get_object_terms( $post_id, $taxonomy_names, [ 'fields' => 'all' ] );
				if ( is_wp_error( $post_terms ) || ! is_array( $post_terms ) ) {
					continue;
				}
				foreach ( $post_terms as $term ) {
					$taxonomy = isset( $term->taxonomy ) ? (string) $term->taxonomy : '';
					if ( '' === $taxonomy ) {
						continue;
					}
					$term_value = ( 'id' === $taxonomy_format ) ? (string) $term->term_id : (string) $term->name;
					if ( '' === $term_value ) {
						continue;
					}
					$taxonomy_data[ $post_id ][] = $taxonomy . ':' . $term_value;
				}
			}
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
					$terms                    = array_values( array_unique( array_filter( array_map( 'strval', (array) $terms ) ) ) );
					$row[ "tax_{$taxonomy}" ] = implode( '|', $terms );
				}
			}

			$merged_data[] = $row;
		}

		return $merged_data;
	}

	/**
	 * Get posts batch for export
	 *
	 * @since 0.9.8
	 * @param int $offset Starting offset.
	 * @param int $batch_size Batch size.
	 * @return array Posts data.
	 */
	public function get_posts_batch( $offset, $batch_size ) {
		global $wpdb;

		// Get post type and status from config
		$post_type   = $this->config['post_type'] ?? 'post';
		$post_status = $this->config['post_status'] ?? 'publish';

		// Apply export limit if specified
		$limit = $batch_size;
		if ( ! empty( $this->config['export_limit'] ) && $this->config['export_limit'] > 0 ) {
			// Adjust batch size if it would exceed the export limit
			$remaining = $this->config['export_limit'] - $offset;
			if ( $remaining <= 0 ) {
				return []; // Export limit reached
			}
			$limit = min( $batch_size, $remaining );
		}

		// Build query for batch.
		$all_status_values = [ 'any', 'all', 'all_status', 'all_statuses', 'all-statuses', '*' ];
		$table_sql         = $wpdb->posts;
		$base_select_sql   = 'SELECT ID, post_title, post_content, post_excerpt, post_status, post_date, post_modified, post_name, post_parent, menu_order, post_author, comment_count, post_type, comment_status, ping_status, post_password FROM ' . $table_sql;
		$order_by_sql      = ' ORDER BY post_date DESC, ID DESC';

		if ( is_array( $post_status ) ) {
			$post_status = array_values( array_filter( array_map( 'sanitize_key', $post_status ) ) );
			if ( empty( $post_status ) ) {
				$sql    = $base_select_sql . ' WHERE post_type = %s' . $order_by_sql . ' LIMIT %d OFFSET %d';
				$params = [ $post_type, $limit, $offset ];
			} else {
				$placeholders = implode( ',', array_fill( 0, count( $post_status ), '%s' ) );
				$sql          = $base_select_sql . ' WHERE post_type = %s AND post_status IN (' . $placeholders . ')' . $order_by_sql . ' LIMIT %d OFFSET %d';
				$params       = array_merge( [ $post_type ], $post_status, [ $limit, $offset ] );
			}
		} elseif ( empty( $post_status ) || in_array( (string) $post_status, $all_status_values, true ) ) {
			$sql    = $base_select_sql . ' WHERE post_type = %s' . $order_by_sql . ' LIMIT %d OFFSET %d';
			$params = [ $post_type, $limit, $offset ];
		} else {
			$sql    = $base_select_sql . ' WHERE post_type = %s AND post_status = %s' . $order_by_sql . ' LIMIT %d OFFSET %d';
			$params = [ $post_type, $post_status, $limit, $offset ];
		}

		$query = call_user_func_array( [ $wpdb, 'prepare' ], array_merge( [ $sql ], $params ) );

		$posts = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $posts ) ) {
			return [];
		}

		// Get post meta for all posts in batch
		$post_ids  = wp_list_pluck( $posts, 'ID' );
		$headers   = $this->get_csv_headers();
		$meta_keys = [];
		foreach ( $headers as $header ) {
			if ( ! is_string( $header ) || '' === $header ) {
				continue;
			}
			if ( 0 === strpos( $header, 'tax_' ) ) {
				continue;
			}
			if ( array_key_exists( $header, $posts[0] ) ) {
				continue;
			}
			$meta_keys[] = $header;
		}
		$meta_keys = array_values( array_unique( array_filter( $meta_keys ) ) );

		$meta_data = $this->get_batch_post_meta( $post_ids, $meta_keys );

		$sticky_posts = get_option( 'sticky_posts', [] );
		$sticky_map   = is_array( $sticky_posts ) ? array_flip( array_map( 'intval', $sticky_posts ) ) : [];

		$taxonomy_data = [];
		// Always include taxonomies for Direct SQL
		$taxonomy_data = $this->get_taxonomy_data_for_posts( $posts );

		// Merge post data with meta data.
		$merged_data = [];
		foreach ( $posts as $post ) {
			$post_meta          = isset( $meta_data[ $post['ID'] ] ) ? (array) $meta_data[ $post['ID'] ] : [];
			$row                = array_merge( $post, $post_meta );
			$row['post_sticky'] = isset( $sticky_map[ (int) $post['ID'] ] ) ? '1' : '0';

			// Add taxonomy data if needed.
			if ( ! empty( $taxonomy_data ) && isset( $taxonomy_data[ $post['ID'] ] ) && ! empty( $taxonomy_data[ $post['ID'] ] ) ) {
				$taxonomies = [];
				foreach ( $taxonomy_data[ $post['ID'] ] as $taxonomy_item ) {
					if ( empty( $taxonomy_item ) ) {
						continue;
					}
					$parts = explode( ':', $taxonomy_item, 2 );
					if ( count( $parts ) !== 2 ) {
						continue;
					}
					list( $taxonomy, $term )   = $parts;
					$taxonomies[ $taxonomy ][] = $term;
				}

				foreach ( $taxonomies as $taxonomy => $terms ) {
					$terms                     = array_values( array_unique( array_filter( array_map( 'strval', (array) $terms ) ) ) );
					$row[ 'tax_' . $taxonomy ] = implode( '|', $terms );
				}
			}

			$merged_data[] = $row;
		}

		return $merged_data;
	}

	/**
	 * Generate CSV batch
	 *
	 * @since 0.9.8
	 * @param array $posts_data Posts data.
	 * @return string CSV content.
	 */
	public function generate_csv_batch( $posts_data ) {
		$csv = '';

		foreach ( $posts_data as $post_data ) {
			$row = [];

			// Get headers order.
			$headers = $this->get_csv_headers();

			foreach ( $headers as $header ) {
				$value = isset( $post_data[ $header ] ) ? $post_data[ $header ] : '';
				$row[] = $this->escape_csv_field( $value );
			}

			$csv .= implode( ',', $row ) . "\n";
		}

		return $csv;
	}

	/**
	 * Get post meta for batch
	 *
	 * @since 0.9.8
	 * @param array $post_ids Post IDs.
	 * @param array $meta_keys Meta keys to fetch.
	 * @return array Post meta data indexed by post ID.
	 */
	private function get_batch_post_meta( $post_ids, $meta_keys = [] ) {
		global $wpdb;

		if ( empty( $post_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		$where_meta_key_sql = '';
		$params             = $post_ids;
		if ( ! empty( $meta_keys ) ) {
			$meta_keys = array_values( array_filter( array_map( 'sanitize_key', $meta_keys ) ) );
			if ( ! empty( $meta_keys ) ) {
				$meta_key_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
				$where_meta_key_sql    = " AND meta_key IN ({$meta_key_placeholders})";
				$params                = array_merge( $params, $meta_keys );
			}
		}

		$where_private_sql = '';
		if ( empty( $this->config['include_private_meta'] ) ) {
			$where_private_sql = " AND meta_key NOT LIKE '\\_%'";
		}

		$sql = "SELECT post_id, meta_key, meta_value
			FROM {$wpdb->postmeta}
			WHERE post_id IN ({$placeholders}){$where_private_sql}{$where_meta_key_sql}";

		$query = call_user_func_array( [ $wpdb, 'prepare' ], array_merge( [ $sql ], $params ) );

		$meta_results = $wpdb->get_results( $query, ARRAY_A );

		$meta_data = [];
		foreach ( $meta_results as $meta ) {
			$meta_data[ $meta['post_id'] ][ $meta['meta_key'] ] = $meta['meta_value'];
		}

		return $meta_data;
	}

	/**
	 * Escape CSV field
	 *
	 * @since 0.9.8
	 * @param string $field Field value.
	 * @return string Escaped field.
	 */
	private function escape_csv_field( $field ) {
		// Remove newlines and tabs
		$field = str_replace( [ "\n", "\r", "\t" ], ' ', $field );

		// Escape quotes
		$field = str_replace( '"', '""', $field );

		return '"' . $field . '"';
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
