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
class Swift_CSV_Import_Batch_Processor extends Swift_CSV_Import_Batch_Processor_Base {
	/**
	 * Batch planner.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Ajax_Import_Batch_Planner|null
	 */
	private $batch_planner;

	/**
	 * Taxonomy utility instance.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Taxonomy_Util|null
	 */
	private $taxonomy_util;

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
	 * Constructor.
	 *
	 * Allows injecting dependencies for alternative import methods (e.g., direct SQL)
	 * while keeping the default lazy instantiation behavior.
	 *
	 * @since 0.9.8
	 * @param Swift_CSV_Ajax_Import_Batch_Planner|null $batch_planner Batch planner.
	 * @param Swift_CSV_Import_Row_Context|null        $row_context_util Row context util.
	 * @param Swift_CSV_Import_Meta_Tax|null           $meta_tax_util Meta/tax util.
	 * @param Swift_CSV_Import_Persister|null          $persister_util Persister util.
	 * @param Swift_CSV_Import_Row_Processor|null      $row_processor_util Row processor util.
	 * @param Swift_CSV_Import_Taxonomy_Util|null      $taxonomy_util Taxonomy util.
	 */
	public function __construct(
		?Swift_CSV_Ajax_Import_Batch_Planner $batch_planner = null,
		?Swift_CSV_Import_Row_Context $row_context_util = null,
		?Swift_CSV_Import_Meta_Tax $meta_tax_util = null,
		?Swift_CSV_Import_Persister $persister_util = null,
		?Swift_CSV_Import_Row_Processor $row_processor_util = null,
		?Swift_CSV_Import_Taxonomy_Util $taxonomy_util = null
	) {
		$this->batch_planner      = $batch_planner;
		$this->row_context_util   = $row_context_util;
		$this->meta_tax_util      = $meta_tax_util;
		$this->persister_util     = $persister_util;
		$this->row_processor_util = $row_processor_util;
		$this->taxonomy_util      = $taxonomy_util;
	}

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
	 * Get taxonomy utility instance.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Taxonomy_Util
	 */
	private function get_taxonomy_util(): Swift_CSV_Import_Taxonomy_Util {
		if ( null === $this->taxonomy_util ) {
			$this->taxonomy_util = new Swift_CSV_Import_Taxonomy_Util();
		}
		return $this->taxonomy_util;
	}

	/**
	 * Execute the actual batch processing.
	 *
	 * @since 0.9.8
	 * @param array $config Import configuration.
	 * @param array $csv_data Parsed CSV data.
	 * @param array $counters Counters (by reference).
	 * @return void
	 */
	protected function do_process_batch( array $config, array $csv_data, array &$counters ): void {
		// Delegate to Ajax_Import's original method.
		// This maintains all existing logic without modification.
		$this->process_batch_import( $config, $csv_data, $counters );
	}

	/**
	 * Initialize counters for a batch.
	 *
	 * @since 0.9.8
	 * @return array<string, mixed>
	 */
	protected function initialize_counters(): array {
		return [
			'processed'       => 0,
			'created'         => 0,
			'updated'         => 0,
			'errors'          => 0,
			'dry_run_log'     => [],
			'dry_run_details' => [],
		];
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

		$row_context_util    = $this->get_row_context_util();
		$row_processor_util  = $this->get_row_processor_util();
		$persister_util      = $this->get_persister_util();
		$meta_tax_util       = $this->get_meta_tax_util();
		$allowed_post_fields = $this->get_allowed_post_fields();

		$id_col = $this->validate_id_column_or_send_error( $config, $csv_data );
		if ( false === $id_col ) {
			return;
		}

		$this->process_batch_loop(
			$wpdb,
			$config,
			$csv_data,
			$allowed_post_fields,
			$counters,
			$row_context_util,
			$row_processor_util,
			$persister_util,
			$meta_tax_util
		);
	}

	/**
	 * Validate ID column for the current batch.
	 *
	 * @since 0.9.8
	 * @param array $config Import configuration.
	 * @param array $csv_data Parsed CSV data.
	 * @return int|null|false ID column index, null when missing in non-first batch, or false when validation failed.
	 */
	protected function validate_id_column_or_send_error( array $config, array $csv_data ) {
		// Validate ID column only on first batch to prevent performance issues.
		if ( 0 === (int) ( $config['start_row'] ?? 0 ) ) {
			$validation_result = $this->get_taxonomy_util()->validate_id_column( $csv_data['headers'], $config['file_path'] );
			if ( ! $validation_result['valid'] ) {
				Swift_CSV_Ajax_Util::send_error_response( (string) $validation_result['error'] );
				return false;
			}
			return $validation_result['id_col'];
		}

		// For subsequent batches, get ID column from headers directly.
		$id_col = array_search( 'ID', $csv_data['headers'], true );
		if ( false === $id_col ) {
			return null;
		}
		return $id_col;
	}

	/**
	 * Run the batch loop.
	 *
	 * @since 0.9.8
	 * @param wpdb                           $wpdb WordPress database handler.
	 * @param array                          $config Import configuration.
	 * @param array                          $csv_data Parsed CSV data.
	 * @param array                          $allowed_post_fields Allowed post fields.
	 * @param array                          $counters Counters (by reference).
	 * @param Swift_CSV_Import_Row_Context   $row_context_util Row context util.
	 * @param Swift_CSV_Import_Row_Processor $row_processor_util Row processor util.
	 * @param Swift_CSV_Import_Persister     $persister_util Persister util.
	 * @param Swift_CSV_Import_Meta_Tax      $meta_tax_util Meta/tax util.
	 * @return void
	 */
	protected function process_batch_loop(
		wpdb $wpdb,
		array $config,
		array $csv_data,
		array $allowed_post_fields,
		array &$counters,
		Swift_CSV_Import_Row_Context $row_context_util,
		Swift_CSV_Import_Row_Processor $row_processor_util,
		Swift_CSV_Import_Persister $persister_util,
		Swift_CSV_Import_Meta_Tax $meta_tax_util
	): void {
		// Calculate end row once to avoid function calls in loop test.
		$end_row = min( $config['start_row'] + $config['batch_size'], $csv_data['total_rows'] );
		for ( $i = $config['start_row']; $i < $end_row; $i++ ) {
			$this->process_import_loop_iteration(
				$wpdb,
				$config,
				$csv_data,
				$allowed_post_fields,
				$i,
				$counters,
				$row_context_util,
				$row_processor_util,
				$persister_util,
				$meta_tax_util
			);
		}
	}

	/**
	 * Import loop iteration logic
	 *
	 * @since 0.9.8
	 * @param wpdb                           $wpdb WordPress database handler.
	 * @param array                          $config Import configuration.
	 * @param array                          $csv_data Parsed CSV data.
	 * @param array                          $allowed_post_fields Allowed post fields.
	 * @param int                            $index Row index.
	 * @param array                          $counters Counters (by reference).
	 * @param Swift_CSV_Import_Row_Context   $row_context_util Row context util.
	 * @param Swift_CSV_Import_Row_Processor $row_processor_util Row processor util.
	 * @param Swift_CSV_Import_Persister     $persister_util Persister util.
	 * @param Swift_CSV_Import_Meta_Tax      $meta_tax_util Meta/tax util.
	 * @return void
	 */
	private function process_import_loop_iteration(
		wpdb $wpdb,
		array $config,
		array $csv_data,
		array $allowed_post_fields,
		int $index,
		array &$counters,
		Swift_CSV_Import_Row_Context $row_context_util,
		Swift_CSV_Import_Row_Processor $row_processor_util,
		Swift_CSV_Import_Persister $persister_util,
		Swift_CSV_Import_Meta_Tax $meta_tax_util
	): void {
		$lines     = $csv_data['lines'];
		$delimiter = $csv_data['delimiter'];
		$headers   = $csv_data['headers'];

		$line = $lines[ $index ] ?? '';
		if ( empty( trim( $line ) ) ) {
			++$counters['processed'];
			return;
		}

		// Use Ajax_Import's original row processing logic.
		$row_context = $this->build_import_row_context_from_config( $wpdb, $row_context_util, $config, $line, $delimiter, $headers, $allowed_post_fields );
		if ( null === $row_context ) {
			return;
		}

		$context = $this->build_row_processing_context( $config, $csv_data, $headers, $allowed_post_fields );

		// Use the original row processor utility.
		$row_processor_util->process_row_context_with_persister(
			$wpdb,
			$row_context,
			$context,
			$counters,
			$persister_util,
			function ( wpdb $wpdb, int $post_id, bool $is_update, array $context, array &$counters ) use ( $meta_tax_util ): void {
				$this->handle_successful_row_import( $wpdb, $post_id, $is_update, $context, $counters, $meta_tax_util );
			}
		);
	}

	/**
	 * Row context building logic
	 *
	 * @since 0.9.8
	 * @param wpdb                         $wpdb WordPress database handler.
	 * @param Swift_CSV_Import_Row_Context $row_context_util Row context util.
	 * @param array                        $config Import configuration.
	 * @param string                       $line CSV line.
	 * @param string                       $delimiter CSV delimiter.
	 * @param array                        $headers CSV headers.
	 * @param array                        $allowed_post_fields Allowed post fields.
	 * @return array|null Row context or null if invalid.
	 */
	protected function build_import_row_context_from_config( wpdb $wpdb, Swift_CSV_Import_Row_Context $row_context_util, array $config, string $line, string $delimiter, array $headers, array $allowed_post_fields ): ?array {
		return $row_context_util->build_import_row_context_from_config(
			$wpdb,
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
	protected function build_row_processing_context( array $config, array $csv_data, array $headers, array $allowed_post_fields ): array {
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
	 * @param wpdb                      $wpdb WordPress database object.
	 * @param int                       $post_id Post ID.
	 * @param bool                      $is_update Whether this was an update.
	 * @param array                     $context Row processing context.
	 * @param array                     $counters Counters (by reference).
	 * @param Swift_CSV_Import_Meta_Tax $meta_tax_util Meta/tax util.
	 * @return void
	 */
	protected function handle_successful_row_import( wpdb $wpdb, int $post_id, bool $is_update, array $context, array &$counters, Swift_CSV_Import_Meta_Tax $meta_tax_util ): void {
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

		$result = $meta_tax_util->process_meta_and_taxonomies_for_row_with_args(
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
		do_action( 'swift_csv_import_phase_map_prepared', $post_id, $prepared_meta_fields, $prepare_args );

		do_action( 'swift_csv_import_phase_post_persist', $post_id, $prepared_meta_fields, $prepare_args );
		do_action( 'swift_csv_process_custom_fields', $post_id, $prepared_meta_fields );
	}

	/**
	 * Get row context utility instance.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Row_Context
	 */
	protected function get_row_context_util(): Swift_CSV_Import_Row_Context {
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
	protected function get_meta_tax_util(): Swift_CSV_Import_Meta_Tax {
		if ( null === $this->meta_tax_util ) {
			$this->meta_tax_util = new Swift_CSV_Import_Meta_Tax( null, $this->get_taxonomy_util() );
		}
		return $this->meta_tax_util;
	}

	/**
	 * Get persister utility instance.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Persister
	 */
	protected function get_persister_util(): Swift_CSV_Import_Persister {
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
	protected function get_row_processor_util(): Swift_CSV_Import_Row_Processor {
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
	protected function get_allowed_post_fields(): array {
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
