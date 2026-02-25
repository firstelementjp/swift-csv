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
			'fields'         => 'ids',
			'no_found_rows'  => true,
		];

		$post_ids = get_posts( $query_args );
		if ( empty( $post_ids ) ) {
			return [];
		}

		$headers = $this->get_complete_headers( $this->config, [], 'wp_compatible' );

		$rows = [];
		foreach ( (array) $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( 0 === $post_id ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
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
				foreach ( (array) $headers as $header ) {
					if ( ! is_string( $header ) || 0 !== strpos( $header, 'cf_' ) ) {
						continue;
					}
					$meta_key = substr( $header, 3 );
					if ( ! is_string( $meta_key ) || '' === $meta_key ) {
						continue;
					}
					if ( empty( $this->config['include_private_meta'] ) && 0 === strpos( $meta_key, '_' ) ) {
						continue;
					}
					$values         = get_post_meta( $post_id, $meta_key, false );
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
				$post_type_for_tax = $this->config['post_type'] ?? 'post';
				$taxonomy_names    = get_object_taxonomies( $post_type_for_tax, 'names' );
				$taxonomy_names    = is_array( $taxonomy_names ) ? array_values( array_filter( array_map( 'sanitize_key', $taxonomy_names ) ) ) : [];
				$taxonomy_format   = $this->config['taxonomy_format'] ?? 'name';

				foreach ( $taxonomy_names as $taxonomy ) {
					$terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'all' ] );
					if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
						continue;
					}
					$values = [];
					foreach ( $terms as $term ) {
						$values[] = ( 'id' === $taxonomy_format ) ? (string) $term->term_id : (string) $term->name;
					}
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
}
