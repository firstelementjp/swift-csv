<?php
/**
 * Bulk Import Processor for Swift CSV
 *
 * Implements high-performance bulk processing for large CSV imports.
 * Processes posts, taxonomies, and meta fields in batches to minimize database queries.
 *
 * @since 0.9.9
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk Import Processor Class
 *
 * Provides batch processing capabilities for improved import performance.
 * Targets 2.5-3x speed improvement over row-by-row processing.
 *
 * @since 0.9.9
 * @package Swift_CSV
 */
class Swift_CSV_Import_Bulk_Processor {

	/**
	 * Database handler.
	 *
	 * @since 0.9.9
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Batch size for processing.
	 *
	 * @since 0.9.9
	 * @var int
	 */
	private $batch_size = 100;

	/**
	 * Taxonomy utility instance.
	 *
	 * @since 0.9.9
	 * @var Swift_CSV_Import_Taxonomy_Util|null
	 */
	private $taxonomy_util = null;

	/**
	 * Constructor.
	 *
	 * @since 0.9.9
	 * @param wpdb $wpdb WordPress database handler.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Process a batch of import rows using bulk operations.
	 *
	 * @since 0.9.9
	 * @param array $batch_data Batch of prepared row data.
	 * @param array $config Import configuration.
	 * @param array $counters Import counters.
	 * @return array Processing results.
	 */
	public function process_batch( array $batch_data, array $config, array &$counters ): array {
		$start_time = microtime( true );

		$results = [
			'processed' => 0,
			'created'   => 0,
			'updated'   => 0,
			'errors'    => 0,
			'post_ids'  => [],
		];

		// Debug: Log batch processing start
		error_log( '[Swift CSV] Bulk Processing - Starting batch with ' . count( $batch_data ) . ' rows' );
		$this->log_batch_verification_summary( $batch_data );

		// Start transaction for data integrity
		$this->wpdb->query( 'START TRANSACTION' );

		try {
			// Step 1: Bulk process posts
			$post_results        = $this->bulk_process_posts( $batch_data, $config );
			$results['post_ids'] = $post_results['post_ids'];
			$results['created'] += $post_results['created'];
			$results['updated'] += $post_results['updated'];

			// Debug: Log post processing results
			error_log( '[Swift CSV] Bulk Processing - Posts: created=' . $post_results['created'] . ', updated=' . $post_results['updated'] );

			// Step 2: Bulk process taxonomies
			if ( ! empty( $config['include_taxonomies'] ) ) {
				$this->bulk_process_taxonomies( $post_results['post_ids'], $batch_data, $config );
			}

			// Step 3: Bulk process meta fields
			if ( ! empty( $config['include_custom_fields'] ) ) {
				$this->bulk_process_meta_fields( $post_results['post_ids'], $batch_data, $config );
			}

			do_action( 'swift_csv_bulk_import_persist_additional_fields', $post_results['post_ids'], $batch_data, $config );

			// Commit transaction
			$this->wpdb->query( 'COMMIT' );

			$results['processed']   = count( $batch_data );
			$counters['processed'] += $results['processed'];
			$counters['created']   += $results['created'];
			$counters['updated']   += $results['updated'];

		} catch ( Exception $e ) {
			// Rollback on error
			$this->wpdb->query( 'ROLLBACK' );
			$results['errors'] = 1;
			++$counters['errors'];

			error_log( '[Swift CSV] Bulk processing error: ' . $e->getMessage() );
		}

		$processing_time = microtime( true ) - $start_time;
		error_log( "[Swift CSV] Batch processed: {$results['processed']} rows in " . round( $processing_time, 3 ) . 's' );

		return $results;
	}

	/**
	 * Log concise verification data for the current batch.
	 *
	 * @since 0.9.9
	 * @param array $batch_data Batch of prepared row data.
	 * @return void
	 */
	private function log_batch_verification_summary( array $batch_data ): void {
		if ( empty( $batch_data ) ) {
			return;
		}

		$post_field_keys = [];
		$meta_keys       = [];
		$acf_keys        = [];
		$taxonomy_keys   = [];
		$post_ids        = [];

		foreach ( $batch_data as $row_data ) {
			if ( ! empty( $row_data['post_id'] ) ) {
				$post_ids[] = (int) $row_data['post_id'];
			}

			if ( ! empty( $row_data['post_fields'] ) && is_array( $row_data['post_fields'] ) ) {
				$post_field_keys = array_merge( $post_field_keys, array_keys( $row_data['post_fields'] ) );
			}

			if ( ! empty( $row_data['meta_fields'] ) && is_array( $row_data['meta_fields'] ) ) {
				$meta_keys = array_merge( $meta_keys, array_keys( $row_data['meta_fields'] ) );
			}

			if ( ! empty( $row_data['acf_fields'] ) && is_array( $row_data['acf_fields'] ) ) {
				$acf_keys = array_merge( $acf_keys, array_keys( $row_data['acf_fields'] ) );
			}

			if ( ! empty( $row_data['taxonomies'] ) && is_array( $row_data['taxonomies'] ) ) {
				$taxonomy_keys = array_merge( $taxonomy_keys, array_keys( $row_data['taxonomies'] ) );
			}
		}

		$post_field_keys = array_values( array_unique( $post_field_keys ) );
		$meta_keys       = array_values( array_unique( $meta_keys ) );
		$acf_keys        = array_values( array_unique( $acf_keys ) );
		$taxonomy_keys   = array_values( array_unique( $taxonomy_keys ) );
		$post_ids        = array_values( array_unique( $post_ids ) );

		error_log(
			'[Swift CSV] Bulk Verify - Batch summary: post_ids=' . $this->implode_for_log( array_slice( $post_ids, 0, 10 ) ) .
			', post_field_keys=' . $this->implode_for_log( $post_field_keys ) .
			', meta_keys=' . $this->implode_for_log( $meta_keys ) .
			', acf_keys=' . $this->implode_for_log( $acf_keys ) .
			', taxonomy_keys=' . $this->implode_for_log( $taxonomy_keys )
		);

		foreach ( $this->get_verification_sample_rows( $batch_data ) as $sample_row ) {
			error_log(
				'[Swift CSV] Bulk Verify - Row sample: target_post_id=' . ( isset( $sample_row['post_id'] ) && null !== $sample_row['post_id'] ? (string) $sample_row['post_id'] : 'new' ) .
				', post_fields=' . $this->implode_for_log( array_keys( $sample_row['post_fields'] ?? [] ) ) .
				', meta_fields=' . $this->implode_for_log( array_keys( $sample_row['meta_fields'] ?? [] ) ) .
				', acf_fields=' . $this->implode_for_log( array_keys( $sample_row['acf_fields'] ?? [] ) ) .
				', taxonomies=' . $this->implode_for_log( array_keys( $sample_row['taxonomies'] ?? [] ) )
			);
		}
	}

	/**
	 * Build sample rows for verification logging.
	 *
	 * @since 0.9.9
	 * @param array $batch_data Batch of prepared row data.
	 * @return array
	 */
	private function get_verification_sample_rows( array $batch_data ): array {
		$count = count( $batch_data );
		if ( 0 === $count ) {
			return [];
		}

		$indexes = [ 0 ];
		if ( $count > 2 ) {
			$indexes[] = (int) floor( $count / 2 );
		}
		if ( $count > 1 ) {
			$indexes[] = $count - 1;
		}

		$indexes = array_values( array_unique( $indexes ) );
		$samples = [];

		foreach ( $indexes as $index ) {
			if ( isset( $batch_data[ $index ] ) ) {
				$samples[] = $batch_data[ $index ];
			}
		}

		return $samples;
	}

	/**
	 * Convert log values into a compact string.
	 *
	 * @since 0.9.9
	 * @param array $values Values to convert.
	 * @return string
	 */
	private function implode_for_log( array $values ): string {
		if ( empty( $values ) ) {
			return '(none)';
		}

		return implode( '|', array_map( 'strval', $values ) );
	}

	/**
	 * Bulk process posts using direct database operations.
	 *
	 * @since 0.9.9
	 * @param array $batch_data Batch of row data.
	 * @param array $config Import configuration.
	 * @return array Post processing results.
	 */
	private function bulk_process_posts( array $batch_data, array $config ): array {
		$post_ids = [];
		$created  = 0;
		$updated  = 0;

		// Separate new posts from updates
		$new_posts    = [];
		$update_posts = [];

		foreach ( $batch_data as $row_data ) {
			if ( empty( $row_data['post_id'] ) ) {
				$new_posts[] = $row_data;
			} else {
				$update_posts[] = $row_data;
			}
		}

		// Bulk insert new posts
		if ( ! empty( $new_posts ) ) {
			$insert_results = $this->bulk_insert_posts( $new_posts );
			$post_ids       = array_merge( $post_ids, $insert_results['post_ids'] );
			$created       += $insert_results['count'];
		}

		// Bulk update existing posts
		if ( ! empty( $update_posts ) ) {
			$update_results = $this->bulk_update_posts( $update_posts );
			$post_ids       = array_merge( $post_ids, $update_results['post_ids'] );
			$updated       += $update_results['count'];
		}

		return [
			'post_ids' => $post_ids,
			'created'  => $created,
			'updated'  => $updated,
		];
	}

	/**
	 * Bulk insert new posts.
	 *
	 * @since 0.9.9
	 * @param array $new_posts Array of new post data.
	 * @return array Insert results with post IDs.
	 */
	private function bulk_insert_posts( array $new_posts ): array {
		if ( empty( $new_posts ) ) {
			return [
				'post_ids' => [],
				'count'    => 0,
			];
		}

		$values  = [];
		$formats = [];
		$now     = current_time( 'mysql' );
		$now_gmt = current_time( 'mysql', 1 );

		foreach ( $new_posts as $post_data ) {
			$post_fields = $post_data['post_fields'];

			$values[] = $this->wpdb->prepare(
				'(%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)',
				$post_fields['post_author'] ?? get_current_user_id(),
				$post_fields['post_date'] ?? $now,
				$post_fields['post_date_gmt'] ?? $now_gmt,
				$post_fields['post_content'] ?? '',
				$post_fields['post_title'] ?? '',
				$post_fields['post_excerpt'] ?? '',
				$post_fields['post_status'] ?? 'draft',
				$post_fields['comment_status'] ?? 'open',
				$post_fields['ping_status'] ?? 'open',
				$post_fields['post_password'] ?? '',
				$post_fields['post_name'] ?? sanitize_title( $post_fields['post_title'] ?? '' ),
				$post_fields['to_ping'] ?? '',
				$post_fields['pinged'] ?? '',
				$post_fields['post_modified'] ?? $now,
				$post_fields['post_modified_gmt'] ?? $now_gmt,
				$post_fields['post_content_filtered'] ?? '',
				$post_fields['post_parent'] ?? 0,
				$post_fields['menu_order'] ?? 0,
				$post_fields['post_type'] ?? 'post',
				$post_fields['post_mime_type'] ?? ''
			);
		}

		$sql = "INSERT INTO {$this->wpdb->posts} (
			post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
			post_status, comment_status, ping_status, post_password, post_name, to_ping,
			pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent,
			menu_order, post_type, post_mime_type
		) VALUES " . implode( ', ', $values );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->query( $sql );

		if ( false === $result ) {
			throw new Exception( 'Failed to bulk insert posts' );
		}

		// Get inserted post IDs
		$inserted_count    = $this->wpdb->rows_affected;
		$first_inserted_id = $this->wpdb->insert_id;
		$post_ids          = range( $first_inserted_id, $first_inserted_id + $inserted_count - 1 );

		return [
			'post_ids' => $post_ids,
			'count'    => $inserted_count,
		];
	}

	/**
	 * Bulk update existing posts.
	 *
	 * @since 0.9.9
	 * @param array $update_posts Array of update post data.
	 * @return array Update results with post IDs.
	 */
	private function bulk_update_posts( array $update_posts ): array {
		$updated_count = 0;
		$post_ids      = [];

		foreach ( $update_posts as $post_data ) {
			$post_id     = $post_data['post_id'];
			$post_fields = $post_data['post_fields'];

			// Add default fields if not present
			$post_fields['post_modified']     = $post_fields['post_modified'] ?? current_time( 'mysql' );
			$post_fields['post_modified_gmt'] = $post_fields['post_modified_gmt'] ?? current_time( 'mysql', 1 );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $this->wpdb->update(
				$this->wpdb->posts,
				$post_fields,
				[ 'ID' => $post_id ],
				array_fill( 0, count( $post_fields ), '%s' ),
				[ '%d' ]
			);

			if ( false !== $result ) {
				++$updated_count;
				$post_ids[] = $post_id;
			}
		}

		return [
			'post_ids' => $post_ids,
			'count'    => $updated_count,
		];
	}

	/**
	 * Bulk process taxonomy relationships.
	 *
	 * @since 0.9.9
	 * @param array $post_ids Array of post IDs.
	 * @param array $batch_data Original batch data.
	 * @param array $config Import configuration.
	 */
	private function bulk_process_taxonomies( array $post_ids, array $batch_data, array $config ): void {
		// Collect all taxonomy relationships
		$relationships              = [];
		$taxonomy_format            = $config['taxonomy_format'] ?? 'name';
		$taxonomy_format_validation = $config['taxonomy_format_validation'] ?? [];

		foreach ( $batch_data as $index => $row_data ) {
			if ( ! isset( $post_ids[ $index ] ) ) {
				continue;
			}

			$post_id    = $post_ids[ $index ];
			$taxonomies = $row_data['taxonomies'] ?? [];

			foreach ( $taxonomies as $taxonomy => $terms ) {
				if ( ! is_array( $terms ) || empty( $terms ) ) {
					continue;
				}

				$term_ids = $this->resolve_taxonomy_term_ids( $taxonomy, $terms, $taxonomy_format, $taxonomy_format_validation );

				foreach ( $term_ids as $term_id ) {
					// Get term_taxonomy_id
					$term_taxonomy_id = $this->get_term_taxonomy_id( $term_id, $taxonomy );
					if ( $term_taxonomy_id ) {
						$relationships[] = [
							'object_id'        => $post_id,
							'term_taxonomy_id' => $term_taxonomy_id,
							'term_order'       => 0,
						];
					}
				}
			}
		}

		// Bulk insert relationships
		if ( ! empty( $relationships ) ) {
			$this->bulk_insert_term_relationships( $relationships );
		}
	}

	/**
	 * Bulk process meta fields.
	 *
	 * @since 0.9.9
	 * @param array $post_ids Array of post IDs.
	 * @param array $batch_data Original batch data.
	 * @param array $config Import configuration.
	 */
	private function bulk_process_meta_fields( array $post_ids, array $batch_data, array $config ): void {
		// Collect all meta data
		$meta_values = [];

		foreach ( $batch_data as $index => $row_data ) {
			if ( ! isset( $post_ids[ $index ] ) ) {
				continue;
			}

			$post_id     = $post_ids[ $index ];
			$meta_fields = $row_data['meta_fields'] ?? [];

			foreach ( $meta_fields as $meta_key => $meta_value ) {
				if ( '' === $meta_value || null === $meta_value ) {
					continue;
				}

				// Handle multi-value fields
				$values = $this->split_pipe_separated_values( $meta_value );
				foreach ( $values as $value ) {
					$value = trim( $value );
					if ( '' !== $value ) {
						$meta_values[] = [
							'post_id'    => $post_id,
							'meta_key'   => $meta_key,
							'meta_value' => $value,
						];
					}
				}
			}
		}

		// Bulk insert meta values
		if ( ! empty( $meta_values ) ) {
			$this->bulk_insert_postmeta( $meta_values );
		}
	}

	/**
	 * Bulk insert term relationships.
	 *
	 * @since 0.9.9
	 * @param array $relationships Array of relationship data.
	 */
	private function bulk_insert_term_relationships( array $relationships ): void {
		$values = [];

		foreach ( $relationships as $rel ) {
			$values[] = $this->wpdb->prepare(
				'(%d, %d, %d)',
				$rel['object_id'],
				$rel['term_taxonomy_id'],
				$rel['term_order']
			);
		}

		$sql = "INSERT INTO {$this->wpdb->term_relationships} (object_id, term_taxonomy_id, term_order) VALUES " . implode( ', ', $values );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->query( $sql );

		if ( false === $result ) {
			throw new Exception( 'Failed to bulk insert term relationships' );
		}
	}

	/**
	 * Bulk insert post meta.
	 *
	 * @since 0.9.9
	 * @param array $meta_values Array of meta data.
	 */
	private function bulk_insert_postmeta( array $meta_values ): void {
		$values = [];

		foreach ( $meta_values as $meta ) {
			$values[] = $this->wpdb->prepare(
				'(%d, %s, %s)',
				$meta['post_id'],
				$meta['meta_key'],
				is_string( $meta['meta_value'] ) ? $meta['meta_value'] : maybe_serialize( $meta['meta_value'] )
			);
		}

		$sql = "INSERT INTO {$this->wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ', ', $values );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->query( $sql );

		if ( false === $result ) {
			throw new Exception( 'Failed to bulk insert post meta' );
		}
	}

	/**
	 * Resolve term IDs for taxonomy terms.
	 *
	 * @since 0.9.9
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $terms Term values.
	 * @param string $taxonomy_format Taxonomy format.
	 * @param array  $taxonomy_format_validation Validation data.
	 * @return array Array of term IDs.
	 */
	private function resolve_taxonomy_term_ids( string $taxonomy, array $terms, string $taxonomy_format, array $taxonomy_format_validation ): array {
		if ( null === $this->taxonomy_util ) {
			$this->taxonomy_util = new Swift_CSV_Import_Taxonomy_Util();
		}

		$term_ids = [];
		foreach ( $terms as $term_value ) {
			$term_value = trim( (string) $term_value );
			if ( '' === $term_value ) {
				continue;
			}

			$resolved_ids = $this->taxonomy_util->resolve_term_ids_from_value(
				$taxonomy,
				$term_value,
				$taxonomy_format,
				$taxonomy_format_validation
			);

			foreach ( $resolved_ids as $resolved_id ) {
				$term_ids[] = $resolved_id;
			}
		}

		return array_unique( $term_ids );
	}

	/**
	 * Get term taxonomy ID for a term.
	 *
	 * @since 0.9.9
	 * @param int    $term_id Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return int|null Term taxonomy ID or null if not found.
	 */
	private function get_term_taxonomy_id( int $term_id, string $taxonomy ): ?int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$term_taxonomy_id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT term_taxonomy_id FROM {$this->wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
				$term_id,
				$taxonomy
			)
		);

		return $term_taxonomy_id ? (int) $term_taxonomy_id : null;
	}

	/**
	 * Split pipe-separated values.
	 *
	 * @since 0.9.9
	 * @param string $value Pipe-separated value.
	 * @return array Array of individual values.
	 */
	private function split_pipe_separated_values( string $value ): array {
		$values = explode( '|', $value );
		return array_map( 'trim', $values );
	}

	/**
	 * Set batch size.
	 *
	 * @since 0.9.9
	 * @param int $batch_size Batch size.
	 */
	public function set_batch_size( int $batch_size ): void {
		$this->batch_size = max( 1, min( 1000, $batch_size ) );
	}

	/**
	 * Get current batch size.
	 *
	 * @since 0.9.9
	 * @return int Current batch size.
	 */
	public function get_batch_size(): int {
		return $this->batch_size;
	}
}
