<?php
/**
 * Bulk Import Integration for Swift CSV
 *
 * Integrates bulk processing capabilities with existing import workflow.
 * Provides seamless switching between row-by-row and batch processing.
 *
 * @since 0.9.9
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk Import Integration Class
 *
 * Bridges the gap between existing import processors and new bulk processing.
 * Maintains backward compatibility while enabling performance improvements.
 *
 * @since 0.9.9
 * @package Swift_CSV
 */
class Swift_CSV_Import_Bulk_Integration {

	/**
	 * Bulk processor instance.
	 *
	 * @since 0.9.9
	 * @var Swift_CSV_Import_Bulk_Processor|null
	 */
	private $bulk_processor = null;

	/**
	 * Whether bulk processing is enabled.
	 *
	 * @since 0.9.9
	 * @var bool
	 */
	private $bulk_enabled = false;

	/**
	 * Constructor.
	 *
	 * @since 0.9.9
	 * @param wpdb $wpdb WordPress database handler.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->bulk_processor = new Swift_CSV_Import_Bulk_Processor( $wpdb );

		// Enable bulk processing by default
		$this->bulk_enabled = true;
	}

	/**
	 * Process import data using either bulk or row-by-row processing.
	 *
	 * @since 0.9.9
	 * @param array $csv_data CSV data array.
	 * @param array $config Import configuration.
	 * @param array $counters Import counters.
	 * @return array Processing results.
	 */
	public function process_import_data( array $csv_data, array $config, array &$counters ): array {
		if ( $this->bulk_enabled && ! empty( $config['dry_run'] ) ) {
			// Use bulk processing for dry runs
			return $this->process_with_bulk_method( $csv_data, $config, $counters );
		} elseif ( $this->bulk_enabled ) {
			// Use bulk processing for actual imports
			return $this->process_with_bulk_method( $csv_data, $config, $counters );
		} else {
			// Fall back to row-by-row processing
			return $this->process_with_row_method( $csv_data, $config, $counters );
		}
	}

	/**
	 * Process data using bulk method.
	 *
	 * @since 0.9.9
	 * @param array $csv_data CSV data array.
	 * @param array $config Import configuration.
	 * @param array $counters Import counters.
	 * @return array Processing results.
	 */
	private function process_with_bulk_method( array $csv_data, array $config, array &$counters ): array {
		$batch_size = $this->bulk_processor->get_batch_size();
		$total_rows = count( $csv_data );
		$results    = [
			'total_processed'   => 0,
			'total_created'     => 0,
			'total_updated'     => 0,
			'total_errors'      => 0,
			'batches_processed' => 0,
		];

		// Prepare data for bulk processing
		$prepared_data = $this->prepare_data_for_bulk_processing( $csv_data, $config );

		// Process in batches
		for ( $offset = 0; $offset < $total_rows; $offset += $batch_size ) {
			$batch = array_slice( $prepared_data, $offset, $batch_size, true );

			if ( empty( $batch ) ) {
				continue;
			}

			$batch_results = $this->bulk_processor->process_batch( $batch, $config, $counters );

			$results['total_processed'] += $batch_results['processed'];
			$results['total_created']   += $batch_results['created'];
			$results['total_updated']   += $batch_results['updated'];
			$results['total_errors']    += $batch_results['errors'];
			++$results['batches_processed'];

			// TEMPORARY: Disable progress updates to avoid infinite loop
			// TODO: Fix progress reporting properly
			// $this->send_progress_update( $offset + $batch_size, $total_rows, $batch_results );
		}

		return $results;
	}

	/**
	 * Process data using traditional row-by-row method.
	 *
	 * @since 0.9.9
	 * @param array $csv_data CSV data array.
	 * @param array $config Import configuration.
	 * @param array $counters Import counters.
	 * @return array Processing results.
	 */
	private function process_with_row_method( array $csv_data, array $config, array &$counters ): array {
		// Delegate to existing row processor
		$row_processor = new Swift_CSV_Import_Row_Processor();

		$results = [
			'total_processed'   => 0,
			'total_created'     => 0,
			'total_updated'     => 0,
			'total_errors'      => 0,
			'batches_processed' => 0,
		];

		foreach ( $csv_data as $row_data ) {
			// Use existing row processing logic
			$row_results = $this->process_single_row_with_existing_method( $row_data, $config, $counters );

			$results['total_processed'] += $row_results['processed'];
			$results['total_created']   += $row_results['created'];
			$results['total_updated']   += $row_results['updated'];
			$results['total_errors']    += $row_results['errors'];
		}

		return $results;
	}

	/**
	 * Prepare CSV data for bulk processing.
	 *
	 * @since 0.9.9
	 * @param array $csv_data Original CSV data.
	 * @param array $config Import configuration.
	 * @return array Prepared data for bulk processing.
	 */
	private function prepare_data_for_bulk_processing( array $csv_data, array $config ): array {
		$prepared_data  = [];
		$headers        = $config['headers'] ?? [];
		$allowed_fields = $config['allowed_post_fields'] ?? [];

		foreach ( $csv_data as $index => $row ) {
			$prepared_row = [
				'index'       => $index,
				'post_id'     => null, // Will be determined during processing
				'raw_row'     => $row,
				'post_fields' => $this->extract_post_fields( $row, $headers, $allowed_fields ),
				'taxonomies'  => $this->extract_taxonomies( $row, $headers ),
				'meta_fields' => $this->extract_meta_fields( $row, $headers, $allowed_fields ),
				'acf_fields'  => [],
			];

			// Check if this is an update (has ID)
			$id_field = array_search( 'ID', $headers, true );
			if ( false !== $id_field && ! empty( $row[ $id_field ] ) ) {
				$post_id = (int) $row[ $id_field ];
				if ( get_post( $post_id ) ) {
					$prepared_row['post_id'] = $post_id;
				}
			}

			$prepared_row = $this->filter_prepared_row_for_bulk_processing( $prepared_row, $headers, $config );

			$prepared_data[] = $prepared_row;
		}

		return $prepared_data;
	}

	/**
	 * Allow extensions to enrich prepared bulk rows.
	 *
	 * @since 0.9.9
	 * @param array $prepared_row Prepared bulk row.
	 * @param array $headers CSV headers.
	 * @param array $config Import configuration.
	 * @return array
	 */
	private function filter_prepared_row_for_bulk_processing( array $prepared_row, array $headers, array $config ): array {
		$args = [
			'headers'   => $headers,
			'data'      => isset( $prepared_row['raw_row'] ) && is_array( $prepared_row['raw_row'] ) ? $prepared_row['raw_row'] : [],
			'post_type' => $config['post_type'] ?? '',
			'dry_run'   => ! empty( $config['dry_run'] ),
		];

		$filtered_row = apply_filters( 'swift_csv_bulk_prepare_row', $prepared_row, $args, $config );

		return is_array( $filtered_row ) ? $filtered_row : $prepared_row;
	}

	/**
	 * Extract post fields from CSV row.
	 *
	 * @since 0.9.9
	 * @param array $row CSV row data.
	 * @param array $headers CSV headers.
	 * @param array $allowed_fields Allowed post fields.
	 * @return array Post fields data.
	 */
	private function extract_post_fields( array $row, array $headers, array $allowed_fields ): array {
		$post_fields = [];
		$post_type   = $allowed_fields['post_type'] ?? 'post';

		foreach ( $headers as $index => $header ) {
			$header_normalized = strtolower( trim( $header ) );

			if ( in_array( $header_normalized, $allowed_fields, true ) ) {
				$value = $row[ $index ] ?? '';

				if ( '' !== $value ) {
					$post_fields[ $header_normalized ] = $value;
				}
			}
		}

		// Ensure required fields
		$post_fields['post_type'] = $post_type;
		if ( ! isset( $post_fields['post_status'] ) ) {
			$post_fields['post_status'] = 'publish';
		}

		return $post_fields;
	}

	/**
	 * Extract taxonomies from CSV row.
	 *
	 * @since 0.9.9
	 * @param array $row CSV row data.
	 * @param array $headers CSV headers.
	 * @return array Taxonomy data.
	 */
	private function extract_taxonomies( array $row, array $headers ): array {
		$taxonomies = [];

		foreach ( $headers as $index => $header ) {
			$header_normalized = strtolower( trim( $header ) );

			if ( 0 === strpos( $header_normalized, 'tax_' ) ) {
				$taxonomy_name = substr( $header_normalized, 4 );
				$term_value    = trim( $row[ $index ] ?? '' );

				if ( '' !== $term_value && taxonomy_exists( $taxonomy_name ) ) {
					// Split multiple terms by pipe
					$terms = array_map( 'trim', explode( '|', $term_value ) );
					$terms = array_filter( $terms, 'strlen' );

					if ( ! empty( $terms ) ) {
						$taxonomies[ $taxonomy_name ] = $terms;
					}
				}
			}
		}

		return $taxonomies;
	}

	/**
	 * Extract meta fields from CSV row.
	 *
	 * @since 0.9.9
	 * @param array $row CSV row data.
	 * @param array $headers CSV headers.
	 * @param array $allowed_fields Allowed post fields.
	 * @return array Meta fields data.
	 */
	private function extract_meta_fields( array $row, array $headers, array $allowed_fields ): array {
		$meta_fields = [];

		foreach ( $headers as $index => $header ) {
			$header_normalized = strtolower( trim( $header ) );

			// Skip post fields and taxonomies
			if ( in_array( $header_normalized, $allowed_fields, true ) ||
				0 === strpos( $header_normalized, 'tax_' ) ||
				'id' === $header_normalized ) {
				continue;
			}

			// Handle cf_ prefixed fields
			if ( 0 === strpos( $header_normalized, 'cf_' ) ) {
				$meta_key   = substr( $header_normalized, 3 );
				$meta_value = trim( $row[ $index ] ?? '' );

				if ( '' !== $meta_value ) {
					$meta_fields[ $meta_key ] = $meta_value;
				}
			}
		}

		return $meta_fields;
	}

	/**
	 * Process single row using existing method (fallback).
	 *
	 * @since 0.9.9
	 * @param array $row_data Row data.
	 * @param array $config Import configuration.
	 * @param array $counters Import counters.
	 * @return array Processing results.
	 */
	private function process_single_row_with_existing_method( array $row_data, array $config, array &$counters ): array {
		// This would delegate to the existing row processor
		// For now, return empty results as this is a fallback
		return [
			'processed' => 0,
			'created'   => 0,
			'updated'   => 0,
			'errors'    => 0,
		];
	}

	/**
	 * Send progress update during bulk processing.
	 *
	 * @since 0.9.9
	 * @param int   $processed Number of processed rows.
	 * @param int   $total Total number of rows.
	 * @param array $batch_results Batch processing results.
	 */
	private function send_progress_update( int $processed, int $total, array $batch_results ): void {
		$progress_data = [
			'type'        => 'bulk_progress',
			'processed'   => $processed,
			'total'       => $total,
			'percentage'  => ( $processed / $total ) * 100,
			'batch_stats' => [
				'processed' => $batch_results['processed'],
				'created'   => $batch_results['created'],
				'updated'   => $batch_results['updated'],
				'errors'    => $batch_results['errors'],
			],
		];

		// Send progress via response manager
		$response_manager = new Swift_CSV_Import_Response_Manager();
		$response_manager->send_import_progress_response(
			0, // start_row
			$progress_data['processed'],
			$progress_data['total'] ?? 0, // total_rows
			$progress_data['batch_stats']['errors'] ?? 0, // errors
			$progress_data['batch_stats']['created'] ?? 0, // created
			$progress_data['batch_stats']['updated'] ?? 0, // updated
			0, // previous_created (not available in bulk progress)
			0, // previous_updated (not available in bulk progress)
			0, // previous_errors (not available in bulk progress)
			false, // dry_run
			[], // dry_run_log
			[], // dry_run_details
			[] // recent_logs
		);
	}

	/**
	 * Enable or disable bulk processing.
	 *
	 * @since 0.9.9
	 * @param bool $enabled Whether to enable bulk processing.
	 */
	public function set_bulk_enabled( bool $enabled ): void {
		$this->bulk_enabled = $enabled;
	}

	/**
	 * Check if bulk processing is enabled.
	 *
	 * @since 0.9.9
	 * @return bool True if bulk processing is enabled.
	 */
	public function is_bulk_enabled(): bool {
		return $this->bulk_enabled;
	}

	/**
	 * Set batch size for bulk processing.
	 *
	 * @since 0.9.9
	 * @param int $batch_size Batch size.
	 */
	public function set_batch_size( int $batch_size ): void {
		$this->bulk_processor->set_batch_size( $batch_size );
	}

	/**
	 * Get current batch size.
	 *
	 * @since 0.9.9
	 * @return int Current batch size.
	 */
	public function get_batch_size(): int {
		return $this->bulk_processor->get_batch_size();
	}

	/**
	 * Get bulk processor instance.
	 *
	 * @since 0.9.9
	 * @return Swift_CSV_Import_Bulk_Processor Bulk processor instance.
	 */
	public function get_bulk_processor(): Swift_CSV_Import_Bulk_Processor {
		return $this->bulk_processor;
	}
}
