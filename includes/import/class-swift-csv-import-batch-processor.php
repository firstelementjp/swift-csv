<?php
/**
 * Import Batch Processor for Swift CSV
 *
 * Handles batch processing operations for CSV import.
 * Extracted from Swift_CSV_Ajax_Import for better separation of concerns.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles batch processing operations for CSV import
 *
 * This class is responsible for:
 * - Batch size calculation
 * - Row-by-row processing
 * - Progress tracking
 * - Import result management
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Import_Batch_Processor {
	/**
	 * Batch planner.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Ajax_Import_Batch_Planner|null
	 */
	private $batch_planner;

	/**
	 * Row context utility instance
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Row_Context|null
	 */
	private $row_context_util;

	/**
	 * Meta/taxonomy utility instance
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Meta_Tax|null
	 */
	private $meta_tax_util;

	/**
	 * Persister utility instance
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Persister|null
	 */
	private $persister_util;

	/**
	 * Row processor utility instance
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Row_Processor|null
	 */
	private $row_processor_util;

	/**
	 * Calculate batch size
	 *
	 * Determines optimal batch size based on total rows and configuration.
	 * Extracted from import_handler() for better separation of concerns.
	 *
	 * @since 0.9.8
	 * @param int   $total_rows Total number of rows.
	 * @param array $config Import configuration.
	 * @return int Batch size.
	 */
	public function calculate_batch_size( int $total_rows, array $config ): int {
		return $this->get_batch_planner()->get_import_batch_size( $total_rows, $config );
	}

	/**
	 * Get batch planner.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Ajax_Import_Batch_Planner
	 */
	private function get_batch_planner(): Swift_CSV_Ajax_Import_Batch_Planner {
		if ( null === $this->batch_planner ) {
			$this->batch_planner = new Swift_CSV_Ajax_Import_Batch_Planner();
		}
		return $this->batch_planner;
	}

	/**
	 * Process batch import
	 *
	 * Main batch processing method extracted from import_handler().
	 * Handles the core import loop with proper error handling and progress tracking.
	 *
	 * @since 0.9.8
	 * @param array $config Import configuration.
	 * @param array $csv_data Parsed CSV data.
	 * @return array Processing results.
	 */
	public function process_batch( array $config, array $csv_data ): array {
		$counters = [
			'processed'       => 0,
			'created'         => 0,
			'updated'         => 0,
			'errors'          => 0,
			'dry_run_log'     => [],
			'dry_run_details' => [],
		];

		// Delegate to Ajax_Import's original method.
		// This maintains all existing logic without modification.
		$this->process_batch_import( $config, $csv_data, $counters );

		return $counters;
	}

	/**
	 * Batch import processing logic
	 *
	 * This method contains the exact same logic as the original Ajax_Import::process_batch_import
	 * to ensure no behavior changes during refactoring.
	 *
	 * @since 0.9.8
	 * @param array $config Import configuration.
	 * @param array $csv_data Parsed CSV data.
	 * @param array $counters Counters (by reference).
	 * @return void
	 */
	private function process_batch_import( array $config, array $csv_data, array &$counters ): void {
		global $wpdb;

		$allowed_post_fields = $this->get_allowed_post_fields();

		// Validate ID column only on first batch to prevent performance issues.
		if ( 0 === $config['start_row'] ) {
			// Use Swift_CSV_Helper directly for ID column validation.
			$validation_result = Swift_CSV_Helper::validate_id_column( $csv_data['headers'], $config['file_path'] );
			if ( ! $validation_result['valid'] ) {
				Swift_CSV_Helper::send_error_response( $validation_result['error'] );
				return;
			}
			$id_col = $validation_result['id_col'];
		} else {
			// For subsequent batches, get ID column from headers directly.
			$id_col = array_search( 'ID', $csv_data['headers'], true );
			if ( false === $id_col ) {
				$id_col = null;
			}
		}

		// Calculate end row once to avoid function calls in loop test.
		$end_row = min( $config['start_row'] + $config['batch_size'], $csv_data['total_rows'] );
		for ( $i = $config['start_row']; $i < $end_row; $i++ ) {
			$this->process_import_loop_iteration( $wpdb, $config, $csv_data, $allowed_post_fields, $i, $counters );
		}
	}

	/**
	 * Import loop iteration logic
	 *
	 * @since 0.9.8
	 * @param wpdb  $wpdb WordPress database handler.
	 * @param array $config Import configuration.
	 * @param array $csv_data Parsed CSV data.
	 * @param array $allowed_post_fields Allowed post fields.
	 * @param int   $index Row index.
	 * @param array $counters Counters (by reference).
	 * @return void
	 */
	private function process_import_loop_iteration( wpdb $wpdb, array $config, array $csv_data, array $allowed_post_fields, int $index, array &$counters ): void {
		$lines     = $csv_data['lines'];
		$delimiter = $csv_data['delimiter'];
		$headers   = $csv_data['headers'];

		$line = $lines[ $index ] ?? '';
		if ( empty( trim( $line ) ) ) {
			++$counters['processed'];
			return;
		}

		// Use Ajax_Import's original row processing logic.
		$row_context = $this->build_import_row_context_from_config( $config, $line, $delimiter, $headers, $allowed_post_fields );
		if ( null === $row_context ) {
			return;
		}

		$context = $this->build_row_processing_context( $config, $csv_data, $headers, $allowed_post_fields );

		// Use the original row processor utility.
		$this->get_row_processor_util()->process_row_context_with_persister(
			$wpdb,
			$row_context,
			$context,
			$counters,
			$this->get_persister_util(),
			function ( wpdb $wpdb, int $post_id, bool $is_update, array $context, array &$counters ): void {
				$this->handle_successful_row_import( $wpdb, $post_id, $is_update, $context, $counters );
			}
		);
	}

	/**
	 * Row context building logic
	 *
	 * @since 0.9.8
	 * @param array  $config Import configuration.
	 * @param string $line CSV line.
	 * @param string $delimiter CSV delimiter.
	 * @param array  $headers CSV headers.
	 * @param array  $allowed_post_fields Allowed post fields.
	 * @return array|null Row context or null if invalid.
	 */
	private function build_import_row_context_from_config( array $config, string $line, string $delimiter, array $headers, array $allowed_post_fields ): ?array {
		return $this->get_row_context_util()->build_import_row_context_from_config(
			$GLOBALS['wpdb'],
			$config,
			$line,
			$delimiter,
			$headers,
			$allowed_post_fields
		);
	}

	/**
	 * Row processing context building logic
	 *
	 * @since 0.9.8
	 * @param array $config Import configuration.
	 * @param array $csv_data Parsed CSV data.
	 * @param array $headers CSV headers.
	 * @param array $allowed_post_fields Allowed post fields.
	 * @return array Row processing context.
	 */
	private function build_row_processing_context( array $config, array $csv_data, array $headers, array $allowed_post_fields ): array {
		return [
			'post_type'                  => (string) ( $config['post_type'] ?? 'post' ),
			'dry_run'                    => (bool) ( $config['dry_run'] ?? false ),
			'start_row'                  => (int) ( $config['start_row'] ?? 0 ),
			'headers'                    => $headers,
			'data'                       => [],
			'allowed_post_fields'        => $allowed_post_fields,
			'taxonomy_format'            => (string) ( $config['taxonomy_format'] ?? 'name' ),
			'taxonomy_format_validation' => $csv_data['taxonomy_format_validation'] ?? [],
			'import_session'             => (string) ( $config['import_session'] ?? '' ),
			'append_log'                 => $config['append_log'] ?? null,
		];
	}

	/**
	 * Successful row import handling logic.
	 *
	 * @since 0.9.8
	 * @param wpdb  $wpdb WordPress database object.
	 * @param int   $post_id Post ID.
	 * @param bool  $is_update Whether this was an update.
	 * @param array $context Row processing context.
	 * @param array $counters Counters (by reference).
	 * @return void
	 */
	private function handle_successful_row_import( wpdb $wpdb, int $post_id, bool $is_update, array $context, array &$counters ): void {
		$headers                    = $context['headers'];
		$data                       = $context['data'];
		$allowed_post_fields        = $context['allowed_post_fields'];
		$taxonomy_format            = $context['taxonomy_format'];
		$taxonomy_format_validation = $context['taxonomy_format_validation'];
		$dry_run                    = $context['dry_run'];
		$should_generate_logs       = isset( $context['append_log'] ) && is_callable( $context['append_log'] );

		if ( $should_generate_logs ) {
			$row_number = $context['start_row'] + $counters['processed'] + 1; // Correct row number from context.

			$post_title  = 'Untitled';
			$title_index = array_search( 'post_title', $headers, true );
			if ( false !== $title_index && isset( $data[ $title_index ] ) ) {
				$post_title = $data[ $title_index ];
			}

			$action = $is_update ? 'update' : 'create';

			$status  = 'success';
			$details = 'Processed successfully';

			$detail = [
				'row'     => $row_number,
				'action'  => $action,
				'title'   => $post_title,
				'post_id' => $post_id,
				'status'  => $status,
				'details' => $details,
			];

			call_user_func( $context['append_log'], $detail );
		}

		$this->get_row_processor_util()->apply_success_counters_and_guid_without_callbacks(
			$wpdb,
			$post_id,
			$is_update,
			$counters
		);

		$result = $this->get_meta_tax_util()->process_meta_and_taxonomies_for_row_with_args(
			$wpdb,
			$post_id,
			$headers,
			$data,
			$allowed_post_fields,
			$taxonomy_format,
			$taxonomy_format_validation,
			$dry_run,
			$counters
		);

		$prepare_args         = [
			'headers'   => $headers,
			'data'      => $data,
			'context'   => 'import_field_preparation',
			'post_type' => $context['post_type'] ?? 'post',
		];
		$prepared_meta_fields = apply_filters( 'swift_csv_prepare_import_fields', $result['meta_fields'], $post_id, $prepare_args );

		do_action( 'swift_csv_process_custom_fields', $post_id, $prepared_meta_fields );
	}

	/**
	 * Ensure CSV has the required ID column.
	 *
	 * @since 0.9.8
	 * @param array  $headers CSV headers.
	 * @param string $file_path Temporary file path for cleanup.
	 * @return int|null ID column index or null on error (sends JSON response).
	 */
	private function ensure_id_column_or_send_error_and_cleanup( array $headers, string $file_path ): ?int {
		$validation_result = Swift_CSV_Helper::validate_id_column( $headers, $file_path );

		if ( ! $validation_result['valid'] ) {
			Swift_CSV_Helper::send_error_response( $validation_result['error'] );
			return null;
		}

		return $validation_result['id_col'];
	}

	/**
	 * Get row context utility instance.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Row_Context
	 */
	private function get_row_context_util(): Swift_CSV_Import_Row_Context {
		if ( null === $this->row_context_util ) {
			$this->row_context_util = new Swift_CSV_Import_Row_Context( new Swift_CSV_Import_Csv() );
		}
		return $this->row_context_util;
	}

	/**
	 * Get meta/taxonomy utility instance.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Meta_Tax
	 */
	private function get_meta_tax_util(): Swift_CSV_Import_Meta_Tax {
		if ( null === $this->meta_tax_util ) {
			$this->meta_tax_util = new Swift_CSV_Import_Meta_Tax();
		}
		return $this->meta_tax_util;
	}

	/**
	 * Get persister utility instance.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Persister
	 */
	private function get_persister_util(): Swift_CSV_Import_Persister {
		if ( null === $this->persister_util ) {
			$this->persister_util = new Swift_CSV_Import_Persister();
		}
		return $this->persister_util;
	}

	/**
	 * Get row processor utility instance.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Row_Processor
	 */
	private function get_row_processor_util(): Swift_CSV_Import_Row_Processor {
		if ( null === $this->row_processor_util ) {
			$this->row_processor_util = new Swift_CSV_Import_Row_Processor();
		}
		return $this->row_processor_util;
	}

	/**
	 * Get allowed post fields.
	 *
	 * @since 0.9.8
	 * @return array Allowed post fields.
	 */
	private function get_allowed_post_fields(): array {
		return [
			'post_title',
			'post_content',
			'post_excerpt',
			'post_status',
			'post_name',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_modified',
			'post_modified_gmt',
			'post_parent',
			'menu_order',
			'comment_status',
			'ping_status',
		];
	}
}
