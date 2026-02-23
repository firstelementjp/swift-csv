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
	 * Get posts data
	 *
	 * @since 0.9.8
	 * @return array Posts data.
	 */
	protected function get_posts_data() {
		$export_limit = (int) ( $this->config['export_limit'] ?? 0 );
		$batch_size   = 1000;
		$offset       = 0;
		$posts_data   = [];

		while ( true ) {
			$batch_posts = $this->get_posts_batch( $offset, $batch_size );
			if ( empty( $batch_posts ) ) {
				break;
			}

			$posts_data = array_merge( $posts_data, $batch_posts );
			$offset    += count( $batch_posts );

			if ( $export_limit > 0 && $offset >= $export_limit ) {
				break;
			}
		}

		return $posts_data;
	}

	/**
	 * Build additional WHERE conditions from query spec
	 *
	 * @since 0.9.11
	 * @param array $query_spec Query spec.
	 * @param array $params Query params.
	 * @return string Additional WHERE SQL beginning with ' AND'.
	 */
	private function build_query_spec_where_sql( array $query_spec, array &$params ): string {
		$where_sql = '';

		if ( isset( $query_spec['tax_query'] ) && is_array( $query_spec['tax_query'] ) ) {
			$where_sql .= $this->build_tax_query_where_sql( $query_spec['tax_query'], $params );
		}

		if ( isset( $query_spec['meta_query'] ) && is_array( $query_spec['meta_query'] ) ) {
			$where_sql .= $this->build_meta_query_where_sql( $query_spec['meta_query'], $params );
		}

		return $where_sql;
	}

	/**
	 * Build taxonomy WHERE conditions using EXISTS subqueries
	 *
	 * Supported operators:
	 * - IN
	 * - NOT IN
	 *
	 * Supported fields:
	 * - term_id
	 * - slug
	 *
	 * @since 0.9.11
	 * @param array $tax_query Tax query.
	 * @param array $params Query params.
	 * @return string Additional WHERE SQL beginning with ' AND'.
	 */
	private function build_tax_query_where_sql( array $tax_query, array &$params ): string {
		global $wpdb;

		$conditions = [];
		$relation   = isset( $tax_query['relation'] ) && is_string( $tax_query['relation'] ) ? strtoupper( $tax_query['relation'] ) : 'AND';
		$relation   = in_array( $relation, [ 'AND', 'OR' ], true ) ? $relation : 'AND';

		foreach ( $tax_query as $clause ) {
			if ( ! is_array( $clause ) ) {
				continue;
			}
			$taxonomy = isset( $clause['taxonomy'] ) && is_string( $clause['taxonomy'] ) ? sanitize_key( $clause['taxonomy'] ) : '';
			if ( '' === $taxonomy ) {
				continue;
			}

			$field    = isset( $clause['field'] ) && is_string( $clause['field'] ) ? strtolower( $clause['field'] ) : 'term_id';
			$field    = in_array( $field, [ 'term_id', 'slug' ], true ) ? $field : 'term_id';
			$operator = isset( $clause['operator'] ) && is_string( $clause['operator'] ) ? strtoupper( $clause['operator'] ) : 'IN';
			$operator = in_array( $operator, [ 'IN', 'NOT IN' ], true ) ? $operator : 'IN';
			$terms    = isset( $clause['terms'] ) ? (array) $clause['terms'] : [];
			$terms    = array_values( array_filter( array_map( 'strval', $terms ) ) );
			if ( empty( $terms ) ) {
				continue;
			}

			$exists_operator = 'IN' === $operator ? 'EXISTS' : 'NOT EXISTS';
			$placeholders    = implode( ',', array_fill( 0, count( $terms ), '%s' ) );

			if ( 'slug' === $field ) {
				$subquery = "{$exists_operator} (\n" .
					"\tSELECT 1\n" .
					"\tFROM {$wpdb->term_relationships} tr\n" .
					"\tINNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id\n" .
					"\tINNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id\n" .
					"\tWHERE tr.object_id = ID\n" .
					"\tAND tt.taxonomy = %s\n" .
					"\tAND t.slug IN ({$placeholders})\n" .
					')';
				$params[] = $taxonomy;
				foreach ( $terms as $term ) {
					$params[] = $term;
				}
				$conditions[] = $subquery;
				continue;
			}

			$subquery = "{$exists_operator} (\n" .
				"\tSELECT 1\n" .
				"\tFROM {$wpdb->term_relationships} tr\n" .
				"\tINNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id\n" .
				"\tWHERE tr.object_id = ID\n" .
				"\tAND tt.taxonomy = %s\n" .
				"\tAND tt.term_id IN ({$placeholders})\n" .
				')';
			$params[] = $taxonomy;
			foreach ( $terms as $term ) {
				$params[] = $term;
			}
			$conditions[] = $subquery;
		}

		if ( empty( $conditions ) ) {
			return '';
		}

		return ' AND (' . implode( " {$relation} ", $conditions ) . ')';
	}

	/**
	 * Build meta WHERE conditions using EXISTS subqueries
	 *
	 * Supported compare:
	 * - =
	 * - !=
	 * - LIKE
	 * - NOT LIKE
	 *
	 * @since 0.9.11
	 * @param array $meta_query Meta query.
	 * @param array $params Query params.
	 * @return string Additional WHERE SQL beginning with ' AND'.
	 */
	private function build_meta_query_where_sql( array $meta_query, array &$params ): string {
		global $wpdb;

		$conditions = [];
		$relation   = isset( $meta_query['relation'] ) && is_string( $meta_query['relation'] ) ? strtoupper( $meta_query['relation'] ) : 'AND';
		$relation   = in_array( $relation, [ 'AND', 'OR' ], true ) ? $relation : 'AND';

		foreach ( $meta_query as $clause ) {
			if ( ! is_array( $clause ) ) {
				continue;
			}

			$key = isset( $clause['key'] ) && is_string( $clause['key'] ) ? (string) $clause['key'] : '';
			if ( '' === $key ) {
				continue;
			}

			$compare = isset( $clause['compare'] ) && is_string( $clause['compare'] ) ? strtoupper( $clause['compare'] ) : '=';
			$compare = in_array( $compare, [ '=', '!=', 'LIKE', 'NOT LIKE' ], true ) ? $compare : '=';
			$value   = isset( $clause['value'] ) ? (string) $clause['value'] : '';

			$exists_operator = in_array( $compare, [ '!=', 'NOT LIKE' ], true ) ? 'NOT EXISTS' : 'EXISTS';
			$sql_compare     = in_array( $compare, [ '!=', 'NOT LIKE' ], true ) ? ( '!=' === $compare ? '=' : 'LIKE' ) : $compare;
			$sql_value       = in_array( $compare, [ 'LIKE', 'NOT LIKE' ], true ) ? '%' . $wpdb->esc_like( $value ) . '%' : $value;

			$subquery = "{$exists_operator} (\n" .
				"\tSELECT 1\n" .
				"\tFROM {$wpdb->postmeta} pm\n" .
				"\tWHERE pm.post_id = ID\n" .
				"\tAND pm.meta_key = %s\n" .
				"\tAND pm.meta_value {$sql_compare} %s\n" .
				')';

			$params[]     = $key;
			$params[]     = $sql_value;
			$conditions[] = $subquery;
		}

		if ( empty( $conditions ) ) {
			return '';
		}

		return ' AND (' . implode( " {$relation} ", $conditions ) . ')';
	}

	/**
	 * Get CSV headers for Direct SQL export
	 *
	 * Uses the unified header generation from base class.
	 *
	 * @since 0.9.8
	 * @return array CSV headers.
	 */
	protected function get_csv_headers() {
		// Get query_spec for consistent sample selection.
		$query_spec = [];
		if ( isset( $this->config['query_spec'] ) && is_array( $this->config['query_spec'] ) ) {
			$query_spec = $this->config['query_spec'];
		}

		return $this->get_complete_headers( $this->config, $query_spec, 'direct_sql' );
	}

	/**
	 * Get CSV headers for public access
	 *
	 * Provides public access to header generation for external callers.
	 *
	 * @since 0.9.9
	 * @return string[] CSV headers.
	 */
	public function get_csv_headers_public() {
		return $this->get_post_headers();
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

		// Extract post IDs.
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

		// Get post type and status from config.
		$post_type   = $this->config['post_type'] ?? 'post';
		$post_status = $this->config['post_status'] ?? 'publish';

		// Apply export limit if specified.
		$limit = $batch_size;
		if ( ! empty( $this->config['export_limit'] ) && $this->config['export_limit'] > 0 ) {
			// Adjust batch size if it would exceed the export limit.
			$remaining = $this->config['export_limit'] - $offset;
			if ( $remaining <= 0 ) {
				return []; // Export limit reached.
			}
			$limit = min( $batch_size, $remaining );
		}

		$direct_sql_query_args = [
			'post_type'   => $post_type,
			'post_status' => $post_status,
			'limit'       => (int) $limit,
			'offset'      => (int) $offset,
			'context'     => 'direct_sql',
		];

		/**
		 * Filter export query spec for data retrieval
		 *
		 * This filter provides a unified query spec format that can be used by both
		 * standard and Direct SQL export routes.
		 *
		 * @since 0.9.11
		 * @param array  $query_spec Query spec.
		 * @param array  $config Export configuration.
		 * @param string $context Export context. (direct_sql)
		 * @return array Modified query spec.
		 */
		$query_spec = apply_filters( 'swift_csv_export_query_spec', [], $this->config, 'direct_sql' );
		if ( is_array( $query_spec ) && ! empty( $query_spec ) ) {
			$direct_sql_query_args['query_spec'] = $query_spec;
		}

		/**
		 * Filter export query arguments for data retrieval
		 *
		 * This is the Direct SQL equivalent of the standard export hook.
		 *
		 * @since 0.9.11
		 * @param array $query_args Export query arguments.
		 * @param array $args Export arguments including context.
		 * @return array Modified query arguments.
		 */
		$direct_sql_query_args = apply_filters(
			'swift_csv_export_data_query_args',
			$direct_sql_query_args,
			[
				'post_type'    => $post_type,
				'export_limit' => (int) ( $this->config['export_limit'] ?? 0 ),
				'context'      => 'direct_sql',
			]
		);

		/**
		 * Filter Direct SQL export query arguments for data retrieval
		 *
		 * @since 0.9.11
		 * @param array $query_args Direct SQL export query arguments.
		 * @param array $config Export configuration.
		 * @return array Modified query arguments.
		 */
		$direct_sql_query_args = apply_filters( 'swift_csv_export_direct_sql_query_args', $direct_sql_query_args, $this->config );

		$post_type   = isset( $direct_sql_query_args['post_type'] ) ? (string) $direct_sql_query_args['post_type'] : $post_type;
		$post_status = $direct_sql_query_args['post_status'] ?? $post_status;
		$limit       = isset( $direct_sql_query_args['limit'] ) ? (int) $direct_sql_query_args['limit'] : (int) $limit;
		$offset      = isset( $direct_sql_query_args['offset'] ) ? (int) $direct_sql_query_args['offset'] : (int) $offset;
		$query_spec  = isset( $direct_sql_query_args['query_spec'] ) && is_array( $direct_sql_query_args['query_spec'] ) ? $direct_sql_query_args['query_spec'] : [];

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

		if ( ! empty( $query_spec ) ) {
			$spec_where_sql = $this->build_query_spec_where_sql( $query_spec, $params );
			if ( is_string( $spec_where_sql ) && '' !== $spec_where_sql ) {
				$sql = str_replace( $order_by_sql, $spec_where_sql . $order_by_sql, $sql );
			}
		}

		/**
		 * Filter Direct SQL query parts before preparing
		 *
		 * @since 0.9.11
		 * @param array  $query_parts Query parts.
		 * @param array  $config Export configuration.
		 * @param string $context Export context.
		 * @return array Modified query parts.
		 */
		$query_parts = apply_filters(
			'swift_csv_export_direct_sql_query_parts',
			[
				'sql'    => $sql,
				'params' => $params,
			],
			$this->config,
			'posts_batch'
		);

		$sql    = isset( $query_parts['sql'] ) ? (string) $query_parts['sql'] : $sql;
		$params = isset( $query_parts['params'] ) ? (array) $query_parts['params'] : $params;

		$query = call_user_func_array( [ $wpdb, 'prepare' ], array_merge( [ $sql ], $params ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$posts = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $posts ) ) {
			return [];
		}

		$post_ids = wp_list_pluck( $posts, 'ID' );
		$headers  = $this->get_csv_headers();

		$meta_key_map = [];
		$meta_data    = [];
		if ( ! empty( $this->config['include_custom_fields'] ) ) {
			foreach ( (array) $headers as $header ) {
				if ( ! is_string( $header ) || '' === $header ) {
					continue;
				}
				if ( 0 !== strpos( $header, 'cf_' ) ) {
					continue;
				}
				$meta_key = substr( $header, 3 );
				if ( is_string( $meta_key ) && '' !== $meta_key ) {
					$meta_key_map[ $header ] = [ $meta_key ];
				}
			}

			$meta_keys = [];
			foreach ( $meta_key_map as $candidate_keys ) {
				foreach ( (array) $candidate_keys as $candidate_key ) {
					if ( is_string( $candidate_key ) && '' !== $candidate_key ) {
						$meta_keys[] = $candidate_key;
					}
				}
			}
			$meta_keys = array_values( array_unique( array_filter( $meta_keys ) ) );
			$meta_data = $this->get_batch_post_meta( $post_ids, $meta_keys );
		}

		$sticky_posts = get_option( 'sticky_posts', [] );
		$sticky_map   = is_array( $sticky_posts ) ? array_flip( array_map( 'intval', $sticky_posts ) ) : [];

		$taxonomy_data = [];
		if ( ! empty( $this->config['include_taxonomies'] ) ) {
			$taxonomy_data = $this->get_taxonomy_data_for_posts( $posts );
		}

		// Merge post data with meta data.
		$merged_data = [];
		foreach ( $posts as $post ) {
			$post_id           = (int) ( $post['ID'] ?? 0 );
			$post_meta_values  = isset( $meta_data[ $post_id ] ) ? (array) $meta_data[ $post_id ] : [];
			$post_meta_columns = [];

			foreach ( $meta_key_map as $column_header => $candidate_meta_keys ) {
				if ( ! is_string( $column_header ) || '' === $column_header ) {
					continue;
				}
				$candidate_meta_keys = (array) $candidate_meta_keys;
				$meta_values         = null;
				foreach ( $candidate_meta_keys as $candidate_key ) {
					if ( ! is_string( $candidate_key ) || '' === $candidate_key ) {
						continue;
					}
					if ( isset( $post_meta_values[ $candidate_key ] ) ) {
						$meta_values = $post_meta_values[ $candidate_key ];
						break;
					}
				}
				if ( null === $meta_values ) {
					$post_meta_columns[ $column_header ] = '';
					continue;
				}
				if ( is_array( $meta_values ) ) {
					$meta_values                         = array_values( array_filter( array_map( 'strval', (array) $meta_values ) ) );
					$post_meta_columns[ $column_header ] = count( $meta_values ) > 1 ? implode( '|', $meta_values ) : ( $meta_values[0] ?? '' );
				} else {
					$post_meta_columns[ $column_header ] = (string) $meta_values;
				}
			}

			$row                = array_merge( $post, $post_meta_columns );
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

			/**
			 * Filter individual row data for Direct SQL export
			 *
			 * Allows developers to modify individual row data before CSV generation.
			 * This filter is applied to each row after all data merging is complete.
			 *
			 * @since 0.9.9
			 * @param array  $row Complete row data including post fields, meta, and taxonomy.
			 * @param int    $post_id Post ID.
			 * @param array  $config Export configuration.
			 * @param string $context Export context (direct_sql).
			 * @return array Modified row data.
			 */
			$row = apply_filters( 'swift_csv_export_row', $row, $post_id, $this->config, 'direct_sql' );

			$merged_data[] = $row;
		}

		/**
		 * Filter complete batch data for Direct SQL export
		 *
		 * Allows developers to modify the entire batch data before CSV generation.
		 * This filter is applied after all individual row processing is complete.
		 *
		 * @since 0.9.9
		 * @param array  $merged_data Complete batch data.
		 * @param array  $post_ids Post IDs in this batch.
		 * @param array  $config Export configuration.
		 * @param string $context Export context (direct_sql).
		 * @return array Modified batch data.
		 */
		$merged_data = apply_filters( 'swift_csv_export_batch_data', $merged_data, $post_ids, $this->config, 'direct_sql' );

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
		$csv     = '';
		$headers = $this->get_csv_headers();

		foreach ( (array) $posts_data as $post_data ) {
			if ( ! is_array( $post_data ) ) {
				continue;
			}
			$row  = $this->build_csv_row_from_headers( $post_data, $headers );
			$csv .= implode( ',', $row ) . "\n";
		}

		return $csv;
	}

	/**
	 * Build a CSV row by iterating headers (standard-compatible flow)
	 *
	 * @since 0.9.9
	 * @param array    $post_data Post data for a single row.
	 * @param string[] $headers CSV headers.
	 * @return string[] Escaped CSV row values.
	 */
	private function build_csv_row_from_headers( array $post_data, array $headers ) {
		$row     = [];
		$post_id = isset( $post_data['ID'] ) ? (int) $post_data['ID'] : 0;

		foreach ( $headers as $header ) {
			if ( ! is_string( $header ) || '' === $header ) {
				$row[] = $this->escape_csv_field( '' );
				continue;
			}

			$value = '';

			if ( 'ID' === $header ) {
				$value = $post_id;
			} elseif ( 'post_author' === $header ) {
				$author_id = isset( $post_data['post_author'] ) ? (int) $post_data['post_author'] : 0;
				$author    = $author_id ? get_user_by( 'id', $author_id ) : false;
				$value     = $author ? (string) $author->display_name : '';
			} elseif ( array_key_exists( $header, $post_data ) && 0 !== strpos( $header, 'tax_' ) && 0 !== strpos( $header, 'cf_' ) ) {
				$value = $post_data[ $header ];
			} elseif ( 0 === strpos( $header, 'tax_' ) ) {
				$value = $post_data[ $header ] ?? '';
			} elseif ( 0 === strpos( $header, 'cf_' ) ) {
				$value = $post_data[ $header ] ?? '';
			} else {
				$custom_args = [
					'post_type' => $this->config['post_type'] ?? 'post',
					'context'   => 'export_data_processing',
				];
				$value       = apply_filters( 'swift_csv_process_custom_header', '', $header, $post_id, $custom_args );
			}

			$row[] = $this->escape_csv_field( (string) $value );
		}

		return $row;
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
			$meta_keys = array_values(
				array_filter(
					array_map(
						static function ( $key ) {
							return is_string( $key ) ? $key : '';
						},
						(array) $meta_keys
					)
				)
			);
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$meta_results = $wpdb->get_results( $query, ARRAY_A );

		$meta_data = [];
		foreach ( (array) $meta_results as $meta ) {
			$post_id  = isset( $meta['post_id'] ) ? (int) $meta['post_id'] : 0;
			$meta_key = isset( $meta['meta_key'] ) ? (string) $meta['meta_key'] : '';
			if ( 0 === $post_id || '' === $meta_key ) {
				continue;
			}
			$meta_value                           = isset( $meta['meta_value'] ) ? (string) $meta['meta_value'] : '';
			$meta_data[ $post_id ][ $meta_key ][] = $meta_value;
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
		// Escape quotes.
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

		// Get headers to match order.
		$headers = $this->get_csv_headers();

		// Convert each field to CSV format.
		foreach ( $headers as $header ) {
			$value = $post[ $header ] ?? '';

			// Handle text fields with CSV escaping.
			if ( in_array( $header, [ 'post_title', 'post_content', 'post_excerpt' ], true ) ) {
				$value = '"' . str_replace( '"', '""', $value ) . '"';
			} elseif ( strpos( $header, 'tax_' ) === 0 ) {
				// Taxonomy fields (already pipe-separated).
				$value = '"' . str_replace( '"', '""', $value ) . '"';
			} else {
				// Numeric and other fields.
				$value = $value;
			}

			$row_data[] = $value;
		}

		return apply_filters( 'swift_csv_export_row', $row_data, $post, 'direct_sql' );
	}
}
