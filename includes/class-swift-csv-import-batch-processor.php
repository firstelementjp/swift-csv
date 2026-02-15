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
 * Handles batch processing operations for CSV import.
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
	 * Row context utility instance.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Row_Context|null
	 */
	private $row_context_util;

	/**
	 * Meta/taxonomy utility instance.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Meta_Tax|null
	 */
	private $meta_tax_util;

	/**
	 * Persister utility instance.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Persister|null
	 */
	private $persister_util;

	/**
	 * Row processor utility instance.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Row_Processor|null
	 */
	private $row_processor_util;

	/**
	 * Calculate batch size.
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
		$dry_run = $config['dry_run'] ?? false;

		if ( $dry_run ) {
			// Always use row-by-row processing for dry run
			return 1;
		}

		// Determine threshold for row-by-row vs batch processing
		$row_processing_threshold = 100;

		/**
		 * Filter the threshold for switching between row-by-row and batch processing
		 *
		 * Allows developers to customize when to switch from row-by-row processing
		 * to batch processing based on their specific needs and server capabilities.
		 *
		 * @since 0.9.7
		 * @param int $threshold Number of rows at which to switch to batch processing.
		 * @param int $total_rows Total number of rows in the CSV.
		 * @param array $config Import configuration including post_type, dry_run, etc.
		 * @return int Modified threshold.
		 */
		$row_processing_threshold = apply_filters(
			'swift_csv_import_row_processing_threshold',
			$row_processing_threshold,
			$total_rows,
			$config
		);

		// Determine base batch size
		$base_batch_size = ( $total_rows <= $row_processing_threshold ) ? 1 : 10;

		/**
		 * Filter the batch size for import processing
		 *
		 * Allows developers to customize batch size based on their specific needs,
		 * server capabilities, or data characteristics.
		 *
		 * @since 0.9.7
		 * @param int $batch_size Current batch size (1 for row-by-row, 10 for batch).
		 * @param int $total_rows Total number of rows in the CSV.
		 * @param array $config Import configuration including post_type, dry_run, etc.
		 * @return int Modified batch size.
		 */
		return apply_filters(
			'swift_csv_import_batch_size',
			$base_batch_size,
			$total_rows,
			$config
		);
	}

	/**
	 * Process batch import.
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

		// Delegate to Ajax_Import's original method
		// This maintains all existing logic without modification
		$this->process_batch_import( $config, $csv_data, $counters );

		return $counters;
	}

	/**
	 * Batch import processing logic.
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

		// Use Ajax_Import's original ensure_id_column method
		$id_col = $this->ensure_id_column_or_send_error_and_cleanup( $csv_data['headers'], $config['file_path'] );
		if ( null === $id_col ) {
			return;
		}

		for ( $i = $config['start_row']; $i < min( $config['start_row'] + $config['batch_size'], $csv_data['total_rows'] ); $i++ ) {
			$this->process_import_loop_iteration( $wpdb, $config, $csv_data, $allowed_post_fields, $i, $counters );
		}
	}

	/**
	 * Import loop iteration logic.
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

		// Use Ajax_Import's original row processing logic
		$row_context = $this->build_import_row_context_from_config( $config, $line, $delimiter, $headers, $allowed_post_fields );
		if ( null === $row_context ) {
			return;
		}

		$context = $this->build_row_processing_context( $config, $csv_data, $headers, $allowed_post_fields );

		// Use the original row processor utility
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
	 * Row context building logic.
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
	 * Row processing context building logic.
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
		];
	}

	/**
	 * Successful row import handling logic.
	 *
	 * @since 0.9.8
	 * @param wpdb  $wpdb WordPress database object.
	 * @param int   $post_id Post ID.
	 * @param bool  $is_update Whether this was an update.
	 * @param array $context Processing context.
	 * @param array $counters Counters (by reference).
	 * @return void
	 */
	private function handle_successful_row_import( wpdb $wpdb, int $post_id, bool $is_update, array $context, array &$counters ): void {
		$dry_run_log     = &$counters['dry_run_log'];
		$dry_run_details = &$counters['dry_run_details'];

		$headers                    = $context['headers'];
		$data                       = $context['data'];
		$allowed_post_fields        = $context['allowed_post_fields'];
		$taxonomy_format            = $context['taxonomy_format'];
		$taxonomy_format_validation = $context['taxonomy_format_validation'];
		$dry_run                    = $context['dry_run'];

		// Record detailed processing information for both dry run and actual import
		if ( $dry_run || true ) { // Always record details for UI display
			$row_number = $context['start_row'] + $counters['processed'] + 1; // Correct row number from context

			// Get title from data using header index
			$post_title  = 'Untitled';
			$title_index = array_search( 'post_title', $headers );
			if ( $title_index !== false && isset( $data[ $title_index ] ) ) {
				$post_title = $data[ $title_index ];
			}

			$action = $is_update ? 'update' : 'create';

			// Run validation hook for both dry run and actual import
			$validation_result = [
				'valid'    => true,
				'errors'   => [],
				'warnings' => [],
			];

			/**
			 * Filter validation result for individual row during import
			 *
			 * Allows extensions to validate individual rows during import.
			 * This hook is ideal for custom validation logic or data transformation.
			 *
			 * @since 0.9.0
			 * @param array $validation_result Current validation result.
			 * @param int $post_id Post ID being processed.
			 * @param array $context Processing context including headers and data.
			 * @return array Modified validation result.
			 */
			$validation_result = apply_filters(
				'swift_csv_validate_import_row',
				$validation_result,
				$post_id,
				$context
			);

			// Check if validation failed
			if ( ! $validation_result['valid'] ) {
				$status  = 'error';
				$details = implode( ', ', array_merge( $validation_result['errors'], $validation_result['warnings'] ) );
			} else {
				$status  = 'success';
				$details = empty( $validation_result['warnings'] ) ? 'Processed successfully' : implode( ', ', $validation_result['warnings'] );
			}

			$detail = [
				'row'     => $row_number,
				'action'  => $action,
				'title'   => $post_title,
				'post_id' => $post_id,
				'status'  => $status,
				'details' => $details,
			];

			$dry_run_details[] = $detail;

		}

		// Apply success counters using row processor utility
		$this->get_row_processor_util()->apply_success_counters_and_guid_without_callbacks(
			$wpdb,
			$post_id,
			$is_update,
			$counters
		);

		// Process meta fields and taxonomies
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

		// Prepare import fields for processing (Pro version ACF integration)
		/**
		 * Prepare import fields for processing
		 *
		 * Allows extensions to prepare and modify import fields before processing.
		 * This hook is ideal for Pro versions with ACF integration that need to
		 * prepare ACF field data or modify field values before processing.
		 *
		 * @since 0.9.0
		 * @param array $meta_fields Meta fields to be processed
		 * @param int $post_id Post ID being processed
		 * @param array $args Processing arguments including headers and context
		 * @return array Modified meta fields for processing
		 */
		$prepare_args         = [
			'headers'   => $headers,
			'data'      => $data,
			'context'   => 'import_field_preparation',
			'post_type' => $context['post_type'] ?? 'post',
		];
		$prepared_meta_fields = apply_filters( 'swift_csv_prepare_import_fields', $result['meta_fields'], $post_id, $prepare_args );

		// Custom field processing hook for extensions
		/**
		 * Action for processing custom fields during import
		 *
		 * Allows extensions to process custom fields with their own logic.
		 * This hook is called after basic field processing is complete.
		 *
		 * @since 0.9.0
		 * @param int $post_id The ID of the created/updated post
		 * @param array $meta_fields Array of meta fields to process
		 */
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
