<?php
/**
 * WP Compatible Export Class for Swift CSV
 *
 * Implements Swift_CSV_Export_Base using WordPress core functions.
 * This class is intended to be the standard (WP-compatible) counterpart
 * to the Direct SQL exporter.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Compatible Export Class
 *
 * Uses WP_Query and WordPress APIs to retrieve data.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Export_WP_Compatible extends Swift_CSV_Export_Base {

	private $term_path_cache = [];

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
			$batch_posts = $this->wp_compatible_batch_fetch_posts( $offset, $batch_size );
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
	 * Get post field headers for public access (WP compatible)
	 *
	 * @since 0.9.8
	 * @return string[] Post field headers.
	 */
	public function wp_compatible_get_post_headers(): array {
		return $this->get_complete_headers( $this->config, [], 'wp_compatible' );
	}

	/**
	 * Fetch posts batch for export (WP compatible)
	 *
	 * @since 0.9.8
	 * @param int $offset Starting offset.
	 * @param int $batch_size Batch size.
	 * @return array<int, array<string, mixed>> Posts data.
	 */
	public function wp_compatible_batch_fetch_posts( int $offset, int $batch_size ): array {
		$post_type   = $this->config['post_type'] ?? 'post';
		$post_status = $this->config['post_status'] ?? 'publish';

		$limit = $batch_size;
		if ( ! empty( $this->config['export_limit'] ) && (int) $this->config['export_limit'] > 0 ) {
			$remaining = (int) $this->config['export_limit'] - $offset;
			if ( $remaining <= 0 ) {
				return [];
			}
			$limit = min( $batch_size, $remaining );
		}

		$query_args = [
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => (int) $limit,
			'offset'         => (int) $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		];

		$posts = get_posts( $query_args );
		if ( empty( $posts ) ) {
			return [];
		}

		$post_ids = array_values(
			array_filter(
				array_map(
					static function ( $post ): int {
						return ( $post instanceof \WP_Post ) ? (int) $post->ID : 0;
					},
					(array) $posts
				)
			)
		);

		if ( empty( $post_ids ) ) {
			return [];
		}

		$headers               = $this->get_complete_headers( $this->config, [], 'wp_compatible' );
		$custom_field_headers  = [];
		$taxonomy_data         = [];
		$taxonomy_names        = [];
		$taxonomy_format       = $this->config['taxonomy_format'] ?? 'name';
		$taxonomy_hierarchical = 'name' === $taxonomy_format && ! empty( $this->config['taxonomy_hierarchical'] );

		if ( ! empty( $this->config['include_custom_fields'] ) ) {
			update_meta_cache( 'post', $post_ids );

			foreach ( (array) $headers as $header ) {
				if ( ! is_string( $header ) || 0 !== strpos( $header, 'cf_' ) ) {
					continue;
				}
				$custom_field_headers[] = $header;
			}
		}

		if ( ! empty( $this->config['include_taxonomies'] ) ) {
			$post_type_for_tax = $this->config['post_type'] ?? 'post';
			$taxonomy_names    = get_object_taxonomies( $post_type_for_tax, 'names' );
			$taxonomy_names    = is_array( $taxonomy_names ) ? array_values( array_filter( array_map( 'sanitize_key', $taxonomy_names ) ) ) : [];

			if ( ! empty( $taxonomy_names ) ) {
				update_object_term_cache( $post_ids, $post_type_for_tax );

				$terms = wp_get_object_terms(
					$post_ids,
					$taxonomy_names,
					[
						'fields' => 'all_with_object_id',
					]
				);

				if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
					$term_lookup = $taxonomy_hierarchical ? $this->build_term_lookup( $terms, $taxonomy_names ) : [];
					$term_groups = $taxonomy_hierarchical ? $this->group_terms_by_object_and_taxonomy( $terms ) : [];
					foreach ( $terms as $term ) {
						$object_id = isset( $term->object_id ) ? (int) $term->object_id : 0;
						if ( 0 === $object_id ) {
							continue;
						}

						$taxonomy = isset( $term->taxonomy ) ? (string) $term->taxonomy : '';
						if ( '' === $taxonomy ) {
							continue;
						}

						if ( $taxonomy_hierarchical && $this->should_skip_parent_term_in_hierarchical_output( $term, $term_groups ) ) {
							continue;
						}

						$term_value = 'id' === $taxonomy_format
							? (string) $term->term_id
							: ( $taxonomy_hierarchical ? $this->build_term_path( $term, $term_lookup ) : (string) $term->name );
						if ( '' === $term_value ) {
							continue;
						}

						if ( ! isset( $taxonomy_data[ $object_id ] ) ) {
							$taxonomy_data[ $object_id ] = [];
						}

						if ( ! isset( $taxonomy_data[ $object_id ][ $taxonomy ] ) ) {
							$taxonomy_data[ $object_id ][ $taxonomy ] = [];
						}

						$taxonomy_data[ $object_id ][ $taxonomy ][] = $term_value;
					}
				}
			}
		}

		/**
		 * Allow integrations to preload batch-scoped export data.
		 *
		 * @since 0.9.15
		 *
		 * @param array<int>                 $post_ids Post IDs included in the batch.
		 * @param array<int, \WP_Post>       $posts Raw post objects included in the batch.
		 * @param array<int, string>         $headers Complete export headers.
		 * @param array<string, mixed>       $config Export configuration.
		 * @param string                     $context Export context.
		 */
		do_action( 'swift_csv_export_batch_prepare', $post_ids, $posts, $headers, $this->config, 'wp_compatible' );

		$rows = [];
		foreach ( (array) $posts as $post ) {
			if ( ! ( $post instanceof \WP_Post ) ) {
				continue;
			}

			$post_id = (int) $post->ID;
			if ( 0 === $post_id ) {
				continue;
			}

			$row = [
				'ID'             => $post_id,
				'post_title'     => (string) $post->post_title,
				'post_content'   => (string) $post->post_content,
				'post_excerpt'   => (string) $post->post_excerpt,
				'post_status'    => (string) $post->post_status,
				'post_date'      => (string) $post->post_date,
				'post_modified'  => (string) $post->post_modified,
				'post_name'      => (string) $post->post_name,
				'post_parent'    => (int) $post->post_parent,
				'menu_order'     => (int) $post->menu_order,
				'post_author'    => (int) $post->post_author,
				'comment_count'  => (int) $post->comment_count,
				'post_type'      => (string) $post->post_type,
				'comment_status' => (string) $post->comment_status,
				'ping_status'    => (string) $post->ping_status,
				'post_password'  => (string) $post->post_password,
				'post_sticky'    => is_sticky( $post_id ) ? '1' : '0',
			];

			if ( ! empty( $this->config['include_custom_fields'] ) ) {
				$post_meta = get_post_meta( $post_id );

				foreach ( $custom_field_headers as $header ) {
					$meta_key = substr( $header, 3 );
					if ( ! is_string( $meta_key ) || '' === $meta_key ) {
						continue;
					}
					if ( empty( $this->config['include_private_meta'] ) && 0 === strpos( $meta_key, '_' ) ) {
						continue;
					}

					$values         = $post_meta[ $meta_key ] ?? [];
					$values         = is_array( $values ) ? array_values(
						array_filter(
							array_map(
								static function ( $value ): string {
									if ( is_scalar( $value ) || null === $value ) {
										return (string) $value;
									}
									return (string) maybe_serialize( $value );
								},
								$values
							)
						)
					) : [];
					$row[ $header ] = Swift_CSV_Helper::join_pipe_separated_values( $values );
				}
			}

			if ( ! empty( $this->config['include_taxonomies'] ) ) {
				foreach ( $taxonomy_names as $taxonomy ) {
					$values                    = $taxonomy_data[ $post_id ][ $taxonomy ] ?? [];
					$values                    = array_values( array_unique( array_filter( array_map( 'strval', $values ) ) ) );
					$row[ 'tax_' . $taxonomy ] = Swift_CSV_Helper::join_pipe_separated_values( $values );
				}
			}

			$row    = apply_filters( 'swift_csv_export_row', $row, $post_id, $this->config, 'wp_compatible' );
			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Generate CSV batch (WP compatible)
	 *
	 * @since 0.9.8
	 * @param array<int, array<string, mixed>> $posts_data Posts data.
	 * @return string CSV content.
	 */
	public function wp_compatible_generate_csv_batch( array $posts_data ): string {
		$csv     = '';
		$headers = $this->get_complete_headers( $this->config, [], 'wp_compatible' );

		foreach ( $posts_data as $post_data ) {
			if ( ! is_array( $post_data ) ) {
				continue;
			}
			$row  = $this->build_csv_row_from_headers( $post_data, $headers );
			$csv .= implode( ',', $row ) . "\n";
		}

		return $csv;
	}

	private function build_term_lookup( array $terms, array $taxonomy_names ): array {
		$lookup             = [];
		$pending_parent_ids = [];

		foreach ( $terms as $term ) {
			if ( ! ( $term instanceof \WP_Term ) ) {
				continue;
			}

			$key            = $this->get_term_cache_key( (string) $term->taxonomy, (int) $term->term_id );
			$lookup[ $key ] = $term;

			$parent_id = isset( $term->parent ) ? (int) $term->parent : 0;
			$taxonomy  = isset( $term->taxonomy ) ? (string) $term->taxonomy : '';
			if ( $parent_id <= 0 || '' === $taxonomy ) {
				continue;
			}

			$parent_key = $this->get_term_cache_key( $taxonomy, $parent_id );
			if ( ! isset( $lookup[ $parent_key ] ) ) {
				$pending_parent_ids[ $parent_key ] = [
					'taxonomy' => $taxonomy,
					'term_id'  => $parent_id,
				];
			}
		}

		while ( ! empty( $pending_parent_ids ) ) {
			$grouped_ids = [];
			foreach ( $pending_parent_ids as $pending_item ) {
				$taxonomy = isset( $pending_item['taxonomy'] ) ? (string) $pending_item['taxonomy'] : '';
				$term_id  = isset( $pending_item['term_id'] ) ? (int) $pending_item['term_id'] : 0;
				if ( '' === $taxonomy || $term_id <= 0 || ! in_array( $taxonomy, $taxonomy_names, true ) ) {
					continue;
				}

				if ( ! isset( $grouped_ids[ $taxonomy ] ) ) {
					$grouped_ids[ $taxonomy ] = [];
				}

				$grouped_ids[ $taxonomy ][] = $term_id;
			}

			$pending_parent_ids = [];
			foreach ( $grouped_ids as $taxonomy => $term_ids ) {
				$term_ids = array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );
				if ( empty( $term_ids ) ) {
					continue;
				}

				$parent_terms = get_terms(
					[
						'taxonomy'   => $taxonomy,
						'include'    => $term_ids,
						'hide_empty' => false,
					]
				);

				if ( is_wp_error( $parent_terms ) || ! is_array( $parent_terms ) ) {
					continue;
				}

				foreach ( $parent_terms as $parent_term ) {
					if ( ! ( $parent_term instanceof \WP_Term ) ) {
						continue;
					}

					$parent_key            = $this->get_term_cache_key( $taxonomy, (int) $parent_term->term_id );
					$lookup[ $parent_key ] = $parent_term;

					$grand_parent_id = isset( $parent_term->parent ) ? (int) $parent_term->parent : 0;
					if ( $grand_parent_id <= 0 ) {
						continue;
					}

					$grand_parent_key = $this->get_term_cache_key( $taxonomy, $grand_parent_id );
					if ( ! isset( $lookup[ $grand_parent_key ] ) ) {
						$pending_parent_ids[ $grand_parent_key ] = [
							'taxonomy' => $taxonomy,
							'term_id'  => $grand_parent_id,
						];
					}
				}
			}
		}

		return $lookup;
	}

	/**
	 * Group terms by object ID and taxonomy
	 *
	 * @since 0.9.17
	 * @param array $terms Term list.
	 * @return array Grouped terms.
	 */
	private function group_terms_by_object_and_taxonomy( array $terms ): array {
		$groups = [];

		foreach ( $terms as $term ) {
			if ( ! ( $term instanceof \WP_Term ) ) {
				continue;
			}

			$object_id = isset( $term->object_id ) ? (int) $term->object_id : 0;
			$taxonomy  = isset( $term->taxonomy ) ? (string) $term->taxonomy : '';
			if ( 0 === $object_id || '' === $taxonomy ) {
				continue;
			}

			if ( ! isset( $groups[ $object_id ] ) ) {
				$groups[ $object_id ] = [];
			}

			if ( ! isset( $groups[ $object_id ][ $taxonomy ] ) ) {
				$groups[ $object_id ][ $taxonomy ] = [];
			}

			$groups[ $object_id ][ $taxonomy ][] = $term;
		}

		return $groups;
	}

	/**
	 * Determine whether a parent term should be skipped in hierarchical output
	 *
	 * @since 0.9.17
	 * @param \WP_Term $term Current term.
	 * @param array    $term_groups Grouped terms.
	 * @return bool True when the term should be skipped.
	 */
	private function should_skip_parent_term_in_hierarchical_output( \WP_Term $term, array $term_groups ): bool {
		$object_id = isset( $term->object_id ) ? (int) $term->object_id : 0;
		$taxonomy  = isset( $term->taxonomy ) ? (string) $term->taxonomy : '';
		$term_id   = (int) $term->term_id;
		if ( 0 === $object_id || '' === $taxonomy || 0 === $term_id ) {
			return false;
		}

		$taxonomy_terms = $term_groups[ $object_id ][ $taxonomy ] ?? [];
		foreach ( $taxonomy_terms as $candidate_term ) {
			if ( ! ( $candidate_term instanceof \WP_Term ) ) {
				continue;
			}

			$parent_id = isset( $candidate_term->parent ) ? (int) $candidate_term->parent : 0;
			if ( $parent_id === $term_id ) {
				return true;
			}
		}

		return false;
	}

	private function build_term_path( \WP_Term $term, array $term_lookup ): string {
		$cache_key = $this->get_term_cache_key( (string) $term->taxonomy, (int) $term->term_id );
		if ( isset( $this->term_path_cache[ $cache_key ] ) ) {
			return $this->term_path_cache[ $cache_key ];
		}

		$path_segments = [ (string) $term->name ];
		$parent_id     = isset( $term->parent ) ? (int) $term->parent : 0;
		$guard         = 0;

		while ( $parent_id > 0 && $guard < 100 ) {
			++$guard;
			$parent_key = $this->get_term_cache_key( (string) $term->taxonomy, $parent_id );
			if ( ! isset( $term_lookup[ $parent_key ] ) || ! ( $term_lookup[ $parent_key ] instanceof \WP_Term ) ) {
				break;
			}

			$parent_term     = $term_lookup[ $parent_key ];
			$path_segments[] = (string) $parent_term->name;
			$parent_id       = isset( $parent_term->parent ) ? (int) $parent_term->parent : 0;
		}

		$path                                = implode( ' > ', array_reverse( $path_segments ) );
		$this->term_path_cache[ $cache_key ] = $path;

		return $path;
	}

	private function get_term_cache_key( string $taxonomy, int $term_id ): string {
		return $taxonomy . ':' . $term_id;
	}
}
