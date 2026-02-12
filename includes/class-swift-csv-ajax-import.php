<?php
/**
 * Ajax Import Handler for Swift CSV
 *
 * Handles asynchronous CSV import with chunked processing for large files.
 * Supports custom post types, taxonomies, and meta fields.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CSV import operations via AJAX requests.
 *
 * This class processes CSV file uploads and chunked imports,
 * supporting custom post types, taxonomies, and meta fields.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Import {
	/**
	 * Constructor: Register AJAX hooks.
	 *
	 * @since 0.9.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_swift_csv_ajax_import', [ $this, 'import_handler' ] );
		add_action( 'wp_ajax_nopriv_swift_csv_ajax_import', [ $this, 'import_handler' ] );
		add_action( 'wp_ajax_swift_csv_ajax_upload', [ $this, 'upload_handler' ] );
		add_action( 'wp_ajax_nopriv_swift_csv_ajax_upload', [ $this, 'upload_handler' ] );
	}

	/**
	 * Handle CSV file upload via AJAX.
	 *
	 * @since 0.9.0
	 * @return void Sends JSON response
	 */
	public function upload_handler() {
		// Verify nonce
		$nonce = $_POST['nonce'] ?? '';
		if ( ! Swift_CSV_Helper::verify_nonce( $nonce ) ) {
			Swift_CSV_Helper::send_security_error();
			return;
		}

		// Validate uploaded file
		$file_validation = Swift_CSV_Helper::validate_upload_file( $_FILES['csv_file'] ?? null );
		if ( ! $file_validation['valid'] ) {
			Swift_CSV_Helper::send_error_response( $file_validation['error'] );
			return;
		}

		$file = $_FILES['csv_file'];

		// Validate file size
		$size_validation = Swift_CSV_Helper::validate_file_size( $file );
		if ( ! $size_validation['valid'] ) {
			Swift_CSV_Helper::send_error_response( $size_validation['error'] );
			return;
		}

		// Create temp directory and file path
		$temp_dir  = Swift_CSV_Helper::create_temp_directory();
		$temp_file = Swift_CSV_Helper::generate_temp_file_path( $temp_dir );

		// Save uploaded file
		if ( Swift_CSV_Helper::save_uploaded_file( $file, $temp_file ) ) {
			wp_send_json( [ 'file_path' => $temp_file ] );
		} else {
			Swift_CSV_Helper::send_error_response( 'Failed to save file' );
		}
	}

	/**
	 * Prepare import environment and validate parameters.
	 *
	 * @since 0.9.0
	 * @return array{file_path:string,start_row:int,batch_size:int,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool}|null
	 */
	private function prepare_import_environment() {
		global $wpdb;

		if ( ! $this->verify_nonce_or_send_error_and_cleanup() ) {
			return null;
		}

		$this->setup_db_session( $wpdb );

		return [
			'file_path'       => sanitize_text_field( wp_unslash( $_POST['file_path'] ?? '' ) ),
			'start_row'       => intval( $_POST['start_row'] ?? 0 ),
			'batch_size'      => 10,
			'post_type'       => sanitize_text_field( wp_unslash( $_POST['post_type'] ?? 'post' ) ),
			'update_existing' => sanitize_text_field( wp_unslash( $_POST['update_existing'] ?? '0' ) ),
			'taxonomy_format' => sanitize_text_field( wp_unslash( $_POST['taxonomy_format'] ?? 'name' ) ),
			'dry_run'         => sanitize_text_field( wp_unslash( $_POST['dry_run'] ?? '0' ) ) === '1',
		];
	}

	/**
	 * Parse and validate CSV file.
	 *
	 * @since 0.9.0
	 * @param string $file_path Temporary file path.
	 * @param string $taxonomy_format Taxonomy format.
	 * @return array{lines:array,delimiter:string,headers:array<int,string>,taxonomy_format_validation:array}|null
	 */
	private function parse_and_validate_csv( $file_path, $taxonomy_format ) {
		$csv_content = $this->read_uploaded_csv_content_or_send_error_and_cleanup( $file_path );
		if ( null === $csv_content ) {
			return null;
		}

		$lines     = $this->parse_csv_lines_preserving_quoted_newlines( $csv_content );
		$delimiter = $this->detect_csv_delimiter( $lines );
		$headers   = $this->read_and_normalize_headers( $lines, $delimiter );

		$taxonomy_format_validation = $this->detect_taxonomy_format_validation_or_send_error_and_cleanup(
			$lines,
			$delimiter,
			$headers,
			$taxonomy_format,
			$file_path
		);
		if ( null === $taxonomy_format_validation ) {
			return null;
		}

		return [
			'lines'                      => $lines,
			'delimiter'                  => $delimiter,
			'headers'                    => $headers,
			'taxonomy_format_validation' => $taxonomy_format_validation,
		];
	}

	/**
	 * Process batch of CSV rows.
	 *
	 * @since 0.9.0
	 * @param array $config Import configuration.
	 * @param array $csv_data Parsed CSV data.
	 * @param array $counters Counters (by reference).
	 * @return void
	 */
	private function process_batch_import( $config, $csv_data, &$counters ) {
		global $wpdb;

		$allowed_post_fields = $this->get_allowed_post_fields();
		$id_col              = $this->ensure_id_column_or_send_error_and_cleanup( $csv_data['headers'], $config['file_path'] );
		if ( null === $id_col ) {
			return;
		}

		$processed   = &$counters['processed'];
		$created     = &$counters['created'];
		$updated     = &$counters['updated'];
		$errors      = &$counters['errors'];
		$dry_run_log = &$counters['dry_run_log'];

		for ( $i = $config['start_row']; $i < min( $config['start_row'] + $config['batch_size'], $csv_data['total_rows'] ); $i++ ) {
			if ( $this->maybe_skip_empty_csv_line( $csv_data['lines'][ $i ], $processed ) ) {
				continue;
			}

			$row_context = $this->build_import_row_context(
				$wpdb,
				$csv_data['lines'][ $i ],
				$csv_data['delimiter'],
				$csv_data['headers'],
				$allowed_post_fields,
				$config['update_existing'],
				$config['post_type']
			);
			if ( null === $row_context ) {
				continue;
			}
			$this->process_row_context(
				$wpdb,
				$row_context,
				$config['post_type'],
				$config['dry_run'],
				$dry_run_log,
				$csv_data['headers'],
				$allowed_post_fields,
				$config['taxonomy_format'],
				$csv_data['taxonomy_format_validation'],
				$processed,
				$created,
				$updated,
				$errors
			);
		}
	}

	/**
	 * Process an import row context by running per-row import logic.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                    $wpdb WordPress database handler.
	 * @param array{data:array,post_fields_from_csv:array,post_id:int,is_update:bool} $row_context Row context.
	 * @param string                                                                  $post_type Post type.
	 * @param bool                                                                    $dry_run Whether this is a dry run.
	 * @param array<int, string>                                                      $dry_run_log Dry run log (by reference).
	 * @param array<int, string>                                                      $headers CSV headers.
	 * @param array<int, string>                                                      $allowed_post_fields Allowed post fields.
	 * @param string                                                                  $taxonomy_format Taxonomy format.
	 * @param array                                                                   $taxonomy_format_validation Taxonomy format validation.
	 * @param int                                                                     $processed Processed count (by reference).
	 * @param int                                                                     $created Created count (by reference).
	 * @param int                                                                     $updated Updated count (by reference).
	 * @param int                                                                     $errors Error count (by reference).
	 * @return void
	 */
	private function process_row_context(
		wpdb $wpdb,
		array $row_context,
		string $post_type,
		bool $dry_run,
		array &$dry_run_log,
		array $headers,
		array $allowed_post_fields,
		string $taxonomy_format,
		$taxonomy_format_validation,
		int &$processed,
		int &$created,
		int &$updated,
		int &$errors
	) {
		$data                 = $row_context['data'];
		$post_fields_from_csv = $row_context['post_fields_from_csv'];
		$post_id              = $row_context['post_id'];
		$is_update            = $row_context['is_update'];

		$this->process_single_import_row(
			$wpdb,
			$is_update,
			$post_id,
			$post_fields_from_csv,
			$post_type,
			$dry_run,
			$dry_run_log,
			$headers,
			$data,
			$allowed_post_fields,
			$taxonomy_format,
			$taxonomy_format_validation,
			$processed,
			$created,
			$updated,
			$errors
		);
	}

	/**
	 * Build per-row import context (parse row, collect fields, resolve existing post, validate).
	 *
	 * @since 0.9.0
	 * @param wpdb               $wpdb WordPress database handler.
	 * @param string             $line Raw CSV line.
	 * @param string             $delimiter CSV delimiter.
	 * @param array<int, string> $headers CSV headers.
	 * @param array<int, string> $allowed_post_fields Allowed post fields.
	 * @param string             $update_existing Update flag from request.
	 * @param string             $post_type Post type.
	 * @return array{data:array,post_fields_from_csv:array,post_id:int,is_update:bool}|null Null means skip this row.
	 */
	private function build_import_row_context( wpdb $wpdb, string $line, string $delimiter, array $headers, array $allowed_post_fields, string $update_existing, string $post_type ) {
		$data             = $this->get_parsed_csv_row( $line, $delimiter );
		$post_id_from_csv = $this->get_post_id_from_csv_row( $data );

		$post_fields_from_csv = $this->get_post_fields_from_csv_row( $headers, $data, $allowed_post_fields );

		$existing  = $this->resolve_existing_post_for_import( $wpdb, $update_existing, $post_type, $post_id_from_csv );
		$post_id   = $existing['post_id'];
		$is_update = $existing['is_update'];

		if ( $this->should_skip_import_row( $update_existing, $post_fields_from_csv ) ) {
			return null;
		}

		return [
			'data'                 => $data,
			'post_fields_from_csv' => $post_fields_from_csv,
			'post_id'              => $post_id,
			'is_update'            => $is_update,
		];
	}

	/**
	 * Get post ID value from parsed CSV row.
	 *
	 * Note: This method intentionally preserves the current behavior.
	 * It performs a lightweight sanity check but does not skip rows.
	 *
	 * @since 0.9.0
	 * @param array $data Parsed CSV row.
	 * @return string Post ID value from CSV (may be empty).
	 */
	private function get_post_id_from_csv_row( array $data ): string {
		$first_col = $data[0] ?? '';

		// First check if this looks like an ID row (first column is numeric ID)
		if ( is_numeric( $first_col ) && strlen( (string) $first_col ) <= 6 ) {
			// This is normal - most rows have ID in first column
			// Don't skip - process the actual data
		} else {
			// Continue processing anyway
		}

		return (string) $first_col;
	}

	/**
	 * Skip empty CSV line and update processed counter.
	 *
	 * @since 0.9.0
	 * @param string $line Raw CSV line.
	 * @param int    $processed Processed count (by reference).
	 * @return bool True if the line was skipped.
	 */
	private function maybe_skip_empty_csv_line( string $line, int &$processed ): bool {
		// Skip empty lines only
		if ( ! $this->is_empty_csv_line( $line ) ) {
			return false;
		}

		++$processed; // Count empty lines as processed to avoid infinite loop
		return true;
	}

	/**
	 * Parse one CSV row string into an array.
	 *
	 * @since 0.9.0
	 * @param string $line Raw CSV line.
	 * @param string $delimiter CSV delimiter.
	 * @return array Parsed row data.
	 */
	private function get_parsed_csv_row( string $line, string $delimiter ): array {
		return $this->parse_csv_row( $line, $delimiter );
	}

	/**
	 * Resolve whether a CSV row should update an existing post.
	 *
	 * @since 0.9.0
	 * @param wpdb   $wpdb WordPress database handler.
	 * @param string $update_existing Update flag from request.
	 * @param string $post_type Post type.
	 * @param string $post_id_from_csv Post ID from CSV (first column).
	 * @return array{post_id:int,is_update:bool}
	 */
	private function resolve_existing_post_for_import( wpdb $wpdb, string $update_existing, string $post_type, string $post_id_from_csv ): array {
		return $this->find_existing_post_for_update( $wpdb, $update_existing, $post_type, $post_id_from_csv );
	}

	/**
	 * Determine whether the current CSV row should be skipped during import.
	 *
	 * @since 0.9.0
	 * @param string $update_existing Update flag from request.
	 * @param array  $post_fields_from_csv Post fields collected from CSV.
	 * @return bool True if the row should be skipped.
	 */
	private function should_skip_import_row( string $update_existing, array $post_fields_from_csv ): bool {
		return $this->should_skip_row_due_to_missing_title( $update_existing, $post_fields_from_csv );
	}

	/**
	 * Get post fields array from a parsed CSV row.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $headers CSV headers.
	 * @param array              $data Parsed CSV row data.
	 * @param array<int, string> $allowed_post_fields Allowed post fields.
	 * @return array<string, mixed> Collected post fields.
	 */
	private function get_post_fields_from_csv_row( array $headers, array $data, array $allowed_post_fields ): array {
		return $this->collect_post_fields_from_csv_row( $headers, $data, $allowed_post_fields );
	}

	/**
	 * Process one import row including DB persist and success/error handling.
	 *
	 * @since 0.9.0
	 * @param wpdb               $wpdb WordPress database handler.
	 * @param bool               $is_update Whether this row updates an existing post.
	 * @param int|null           $post_id Post ID (by reference, updated on insert).
	 * @param array              $post_fields_from_csv Post fields collected from CSV.
	 * @param string             $post_type Post type.
	 * @param bool               $dry_run Whether this is a dry run.
	 * @param array<int, string> $dry_run_log Dry run log (by reference).
	 * @param array<int, string> $headers CSV headers.
	 * @param array              $data CSV row data.
	 * @param array<int, string> $allowed_post_fields Allowed post fields.
	 * @param string             $taxonomy_format Taxonomy format.
	 * @param array              $taxonomy_format_validation Taxonomy format validation.
	 * @param int                $processed Processed count (by reference).
	 * @param int                $created Created count (by reference).
	 * @param int                $updated Updated count (by reference).
	 * @param int                $errors Error count (by reference).
	 * @return void
	 */
	private function process_single_import_row(
		wpdb $wpdb,
		bool $is_update,
		&$post_id,
		array $post_fields_from_csv,
		string $post_type,
		bool $dry_run,
		array &$dry_run_log,
		array $headers,
		array $data,
		array $allowed_post_fields,
		string $taxonomy_format,
		$taxonomy_format_validation,
		int &$processed,
		int &$created,
		int &$updated,
		int &$errors
	) {
		try {
			// Direct SQL insert or update (update only fields provided by CSV)
			$result = $this->persist_post_row_from_csv( $wpdb, $is_update, $post_id, $post_fields_from_csv, $post_type, $dry_run, $dry_run_log );
			$this->handle_row_result_after_persist(
				$result,
				$wpdb,
				$post_id,
				$is_update,
				$headers,
				$data,
				$allowed_post_fields,
				$taxonomy_format,
				$taxonomy_format_validation,
				$dry_run,
				$dry_run_log,
				$processed,
				$created,
				$updated,
				$errors
			);
		} catch ( Exception $e ) {
			++$errors;
		}
	}

	/**
	 * Handle row result after persisting wp_posts data.
	 *
	 * @since 0.9.0
	 * @param int|false          $result DB result.
	 * @param wpdb               $wpdb WordPress database handler.
	 * @param int                $post_id Post ID.
	 * @param bool               $is_update Whether this row updates an existing post.
	 * @param array<int, string> $headers CSV headers.
	 * @param array              $data CSV row data.
	 * @param array<int, string> $allowed_post_fields Allowed post fields.
	 * @param string             $taxonomy_format Taxonomy format.
	 * @param array              $taxonomy_format_validation Taxonomy format validation.
	 * @param bool               $dry_run Whether this is a dry run.
	 * @param array<int, string> $dry_run_log Dry run log (by reference).
	 * @param int                $processed Processed count (by reference).
	 * @param int                $created Created count (by reference).
	 * @param int                $updated Updated count (by reference).
	 * @param int                $errors Error count (by reference).
	 * @return void
	 */
	private function handle_row_result_after_persist(
		$result,
		wpdb $wpdb,
		int $post_id,
		bool $is_update,
		array $headers,
		array $data,
		array $allowed_post_fields,
		string $taxonomy_format,
		$taxonomy_format_validation,
		bool $dry_run,
		array &$dry_run_log,
		int &$processed,
		int &$created,
		int &$updated,
		int &$errors
	) {
		if ( $result !== false ) {
			$this->handle_successful_row_import(
				$wpdb,
				$post_id,
				$is_update,
				$headers,
				$data,
				$allowed_post_fields,
				$taxonomy_format,
				$taxonomy_format_validation,
				$dry_run,
				$dry_run_log,
				$processed,
				$created,
				$updated
			);
			return;
		}

		++$errors;
	}

	/**
	 * Build post data array for insert/update during import.
	 *
	 * @since 0.9.0
	 * @param bool   $is_update Whether this row updates an existing post.
	 * @param array  $post_fields_from_csv Post fields collected from CSV.
	 * @param string $post_type Post type.
	 * @return array Post data array for wp_posts insert/update.
	 */
	private function build_post_data_for_import( bool $is_update, array $post_fields_from_csv, string $post_type ): array {
		if ( $is_update ) {
			return $this->build_post_data_for_update( $post_fields_from_csv );
		}

		return $this->build_post_data_for_insert( $post_fields_from_csv, $post_type );
	}

	/**
	 * Persist one CSV row into wp_posts (insert/update).
	 *
	 * @since 0.9.0
	 * @param wpdb               $wpdb WordPress database handler.
	 * @param bool               $is_update Whether this row updates an existing post.
	 * @param int|null           $post_id Target post ID (by reference, updated on insert).
	 * @param array              $post_fields_from_csv Post fields collected from CSV.
	 * @param string             $post_type Post type.
	 * @param bool               $dry_run Whether this is a dry run.
	 * @param array<int, string> $dry_run_log Dry run log (by reference).
	 * @return int|false Result of DB operation (post ID on insert, rows affected on update, or false on failure).
	 */
	private function persist_post_row_from_csv( wpdb $wpdb, bool $is_update, &$post_id, array $post_fields_from_csv, string $post_type, bool $dry_run, array &$dry_run_log ) {
		$post_data = $this->build_post_data_for_import( $is_update, $post_fields_from_csv, $post_type );
		return $this->execute_post_db_operation( $wpdb, $is_update, $post_id, $post_data, $dry_run, $dry_run_log );
	}

	/**
	 * Execute post insert/update operation during import.
	 *
	 * @since 0.9.0
	 * @param wpdb               $wpdb WordPress database handler.
	 * @param bool               $is_update Whether this row updates an existing post.
	 * @param int                $post_id Target post ID.
	 * @param array              $post_data Post data array for wp_posts insert/update.
	 * @param bool               $dry_run Whether this is a dry run.
	 * @param array<int, string> $dry_run_log Dry run log (by reference).
	 * @return int|false Result of DB operation (post ID on insert, rows affected on update, or false on failure).
	 */
	private function execute_post_db_operation( wpdb $wpdb, bool $is_update, &$post_id, array $post_data, bool $dry_run, array &$dry_run_log ) {
		if ( $is_update ) {
			return $this->execute_post_update( $wpdb, $post_id, $post_data, $dry_run, $dry_run_log );
		}

		return $this->execute_post_insert( $wpdb, $post_data, $dry_run, $dry_run_log, $post_id );
	}

	/**
	 * Handle CSV import processing via AJAX.
	 *
	 * @since 0.9.0
	 * @return void Sends JSON response with import results
	 */
	public function import_handler() {
		$config = $this->prepare_import_environment();
		if ( null === $config ) {
			return;
		}

		$csv_data = $this->parse_and_validate_csv( $config['file_path'], $config['taxonomy_format'] );
		if ( null === $csv_data ) {
			return;
		}

		$total_rows             = $this->count_total_rows( $csv_data['lines'] );
		$csv_data['total_rows'] = $total_rows;

		// Initialize counters
		$counters = [
			'processed'   => 0,
			'created'     => 0,
			'updated'     => 0,
			'errors'      => 0,
			'dry_run_log' => [],
		];

		$cumulative_counts = $this->get_cumulative_counts();
		$previous_created  = $cumulative_counts['created'];
		$previous_updated  = $cumulative_counts['updated'];
		$previous_errors   = $cumulative_counts['errors'];

		$this->process_batch_import( $config, $csv_data, $counters );

		$next_row = $config['start_row'] + $counters['processed']; // Use actual processed count
		$continue = $next_row < $total_rows;

		// Cleanup temporary file when import is complete
		$this->cleanup_temp_file_if_complete( $continue, $config['file_path'] );

		$this->send_import_progress_response(
			$config['start_row'],
			$counters['processed'],
			$total_rows,
			$counters['errors'],
			$counters['created'],
			$counters['updated'],
			$previous_created,
			$previous_updated,
			$previous_errors,
			$config['dry_run'],
			$counters['dry_run_log']
		);
	}

	/**
	 * Send import progress response.
	 *
	 * @since 0.9.0
	 * @param int                $start_row Start row.
	 * @param int                $processed Processed count.
	 * @param int                $total_rows Total rows.
	 * @param int                $errors Errors count.
	 * @param int                $created Created count.
	 * @param int                $updated Updated count.
	 * @param int                $previous_created Previous cumulative created.
	 * @param int                $previous_updated Previous cumulative updated.
	 * @param int                $previous_errors Previous cumulative errors.
	 * @param bool               $dry_run Dry run flag.
	 * @param array<int, string> $dry_run_log Dry run log.
	 * @return void
	 */
	private function send_import_progress_response( $start_row, $processed, $total_rows, $errors, $created, $updated, $previous_created, $previous_updated, $previous_errors, $dry_run, $dry_run_log ) {
		$next_row = $start_row + $processed; // Use actual processed count
		$continue = $next_row < $total_rows;

		wp_send_json(
			[
				'success'            => true,
				'processed'          => $next_row,
				'total'              => $total_rows,
				'batch_processed'    => $processed,
				'batch_errors'       => $errors,
				'created'            => $created,
				'updated'            => $updated,
				'errors'             => $errors,
				'cumulative_created' => $previous_created + $created,
				'cumulative_updated' => $previous_updated + $updated,
				'cumulative_errors'  => $previous_errors + $errors,
				'progress'           => round( ( $next_row / $total_rows ) * 100, 2 ),
				'continue'           => $continue,
				'dry_run'            => $dry_run,
				'dry_run_log'        => $dry_run_log,
			]
		);
	}

	/**
	 * Cleanup temporary file when import is complete.
	 *
	 * @since 0.9.0
	 * @param bool   $continue Whether import continues.
	 * @param string $file_path Temporary file path.
	 * @return void
	 */
	private function cleanup_temp_file_if_complete( $continue, $file_path ) {
		if ( ! $continue && $file_path && file_exists( $file_path ) ) {
			unlink( $file_path );
		}
	}

	/**
	 * Process meta fields and taxonomies for a successful row import.
	 *
	 * @since 0.9.0
	 * @param int                $post_id Post ID.
	 * @param array<int, string> $headers CSV headers.
	 * @param array              $data CSV row data.
	 * @param array<int, string> $allowed_post_fields Allowed post fields.
	 * @param string             $taxonomy_format Taxonomy format.
	 * @param array              $taxonomy_format_validation Taxonomy format validation.
	 * @param bool               $dry_run Dry run flag.
	 * @param array<int, string> $dry_run_log Dry run log.
	 * @return array{meta_fields:array<string,string>,taxonomies:array<string,array<int,string>>}
	 */
	private function process_meta_and_taxonomies_for_row( $post_id, $headers, $data, $allowed_post_fields, $taxonomy_format, $taxonomy_format_validation, $dry_run, &$dry_run_log ) {
		// Process custom fields and taxonomies like original Swift CSV
		$collected_fields = $this->collect_taxonomies_and_meta_fields_from_row( $headers, $data, $allowed_post_fields );
		/**
		 * Filter collected fields before processing.
		 *
		 * Allows extensions to process custom columns (e.g., acf_, custom_) before they are saved.
		 * This hook is called after basic field collection but before database operations.
		 *
		 * @since 0.9.0
		 * @param array{meta_fields:array<string,string>,taxonomies:array<string,array<int,string>>,taxonomy_term_ids:array<string,array<int,int>>} $collected_fields Collected fields.
		 * @param array<int, string> $headers CSV headers.
		 * @param array $data CSV row data.
		 * @param array<int, string> $allowed_post_fields Allowed WP post fields.
		 * @return array{meta_fields:array<string,string>,taxonomies:array<string,array<int,string>>,taxonomy_term_ids:array<string,array<int,int>>} Modified collected fields.
		 */
		$collected_fields = apply_filters( 'swift_csv_filter_collected_fields', $collected_fields, $headers, $data, $allowed_post_fields );
		$taxonomies       = $collected_fields['taxonomies'];
		$meta_fields      = $collected_fields['meta_fields'];

		// Process taxonomies
		$this->apply_taxonomies_for_post( $post_id, $taxonomies, $taxonomy_format, $taxonomy_format_validation, $dry_run, $dry_run_log );

		// Process custom fields with multi-value support
		$this->apply_meta_fields_for_post( $this->get_wpdb(), $post_id, $meta_fields, $dry_run, $dry_run_log );

		return [
			'meta_fields' => $meta_fields,
			'taxonomies'  => $taxonomies,
		];
	}

	/**
	 * Get WordPress database instance.
	 *
	 * @since 0.9.0
	 * @return wpdb
	 */
	private function get_wpdb() {
		global $wpdb;
		return $wpdb;
	}

	/**
	 * Handle successful row import.
	 *
	 * @since 0.9.0
	 * @param wpdb               $wpdb WordPress DB instance.
	 * @param int                $post_id Post ID.
	 * @param bool               $is_update Whether this row updated an existing post.
	 * @param array<int, string> $headers CSV headers.
	 * @param array              $data CSV row data.
	 * @param array<int, string> $allowed_post_fields Allowed post fields.
	 * @param string             $taxonomy_format Taxonomy format.
	 * @param array              $taxonomy_format_validation Taxonomy format validation.
	 * @param bool               $dry_run Dry run flag.
	 * @param array<int, string> $dry_run_log Dry run log.
	 * @param int                $processed Processed count (by reference).
	 * @param int                $created Created count (by reference).
	 * @param int                $updated Updated count (by reference).
	 * @return void
	 */
	private function handle_successful_row_import( $wpdb, $post_id, $is_update, $headers, $data, $allowed_post_fields, $taxonomy_format, $taxonomy_format_validation, $dry_run, &$dry_run_log, &$processed, &$created, &$updated ) {
		$this->increment_row_counters_on_success( $is_update, $processed, $created, $updated );

		// Update GUID for new posts
		if ( ! $is_update ) {
			$this->update_guid_for_new_post( $wpdb, $post_id );
		}

		// Process meta fields and taxonomies
		$result = $this->process_meta_and_taxonomies_for_row( $post_id, $headers, $data, $allowed_post_fields, $taxonomy_format, $taxonomy_format_validation, $dry_run, $dry_run_log );

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
		$this->run_custom_field_processing_hook( $post_id, $result['meta_fields'] );
	}

	/**
	 * Run custom field processing hook for extensions.
	 *
	 * @since 0.9.0
	 * @param int                   $post_id Post ID.
	 * @param array<string, string> $meta_fields Meta fields.
	 * @return void
	 */
	private function run_custom_field_processing_hook( $post_id, $meta_fields ) {
		do_action( 'swift_csv_process_custom_fields', $post_id, $meta_fields );
	}

	/**
	 * Increment counters on successful row import.
	 *
	 * @since 0.9.0
	 * @param bool $is_update Whether this row updated an existing post.
	 * @param int  $processed Processed count (by reference).
	 * @param int  $created Created count (by reference).
	 * @param int  $updated Updated count (by reference).
	 * @return void
	 */
	private function increment_row_counters_on_success( $is_update, &$processed, &$created, &$updated ) {
		++$processed;
		if ( $is_update ) {
			++$updated;
		} else {
			++$created;
		}
	}

	/**
	 * Verify nonce for AJAX request.
	 *
	 * @since 0.9.0
	 * @return bool True if nonce is valid.
	 */
	private function verify_nonce_or_send_error_and_cleanup() {
		$nonce     = $_POST['nonce'] ?? '';
		$file_path = sanitize_text_field( wp_unslash( $_POST['file_path'] ?? '' ) );

		if ( ! Swift_CSV_Helper::verify_nonce( $nonce ) ) {
			Swift_CSV_Helper::send_security_error( $file_path );
			return false;
		}

		return true;
	}

	/**
	 * Read uploaded CSV content from request.
	 *
	 * @since 0.9.0
	 * @param string $file_path Temporary file path for cleanup.
	 * @return string|null CSV content or null on error (sends JSON response).
	 */
	private function read_uploaded_csv_content_or_send_error_and_cleanup( $file_path ) {
		// Handle file upload directly
		if ( ! isset( $_FILES['csv_file'] ) ) {
			return Swift_CSV_Helper::send_error_response_and_return_null( 'No file uploaded', $file_path );
		}

		$file = $_FILES['csv_file'];

		// Validate file
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return Swift_CSV_Helper::send_error_response_and_return_null( 'Upload error: ' . $file['error'], $file_path );
		}

		// Read CSV directly from uploaded file
		$csv_content = file_get_contents( $file['tmp_name'] );
		$csv_content = str_replace( [ "\r\n", "\r" ], "\n", $csv_content ); // Normalize line endings

		return $csv_content;
	}

	/**
	 * Parse CSV content line by line to handle quoted fields with newlines.
	 *
	 * @since 0.9.0
	 * @param string $csv_content CSV content.
	 * @return array<int, string>
	 */
	private function parse_csv_lines_preserving_quoted_newlines( $csv_content ) {
		return Swift_CSV_Helper::parse_csv_lines_preserving_quoted_newlines( $csv_content );
	}

	/**
	 * Detect CSV delimiter from first line.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines.
	 * @return string
	 */
	private function detect_csv_delimiter( $lines ) {
		return Swift_CSV_Helper::detect_csv_delimiter( $lines );
	}

	/**
	 * Read CSV header row and normalize it.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines (will consume the first line).
	 * @param string             $delimiter CSV delimiter.
	 * @return array<int, string>
	 */
	private function read_and_normalize_headers( &$lines, $delimiter ) {
		$headers = str_getcsv( array_shift( $lines ), $delimiter );
		// Normalize headers - remove BOM and control characters
		$headers = array_map(
			function ( $header ) {
				// Remove BOM (UTF-8 BOM is \xEF\xBB\xBF)
				$header = preg_replace( '/^\xEF\xBB\xBF/', '', $header );
				// Remove other control characters
				return preg_replace( '/[\x00-\x1F\x7F]/u', '', trim( $header ?? '' ) );
			},
			$headers
		);

		return $headers;
	}

	/**
	 * Normalize header/field name.
	 *
	 * @since 0.9.0
	 * @param string $name Field name.
	 * @return string
	 */
	private function normalize_field_name( $name ) {
		return Swift_CSV_Helper::normalize_field_name( $name );
	}

	/**
	 * Detect taxonomy format from the first data row and validate UI format consistency.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines.
	 * @param string             $delimiter CSV delimiter.
	 * @param array<int, string> $headers CSV headers.
	 * @param string             $taxonomy_format Taxonomy format selected in UI.
	 * @param string             $file_path Temporary file path for cleanup.
	 * @return array<string, array>|null Taxonomy validation data or null on error (sends JSON response).
	 */
	private function detect_taxonomy_format_validation_or_send_error_and_cleanup( $lines, $delimiter, $headers, $taxonomy_format, $file_path ) {
		$taxonomy_format_validation = [];
		$first_row_processed        = false;

		// Process first row for format detection
		$data = [];
		foreach ( $lines as $line ) {
			$row = str_getcsv( $line, $delimiter );
			if ( count( $row ) !== count( $headers ) ) {
				continue; // Skip malformed rows
			}
			$data[] = $row;

			// Process taxonomies for format detection on first data row only
			if ( ! $first_row_processed ) {
				foreach ( $headers as $j => $header_name ) {
					$header_name_normalized = strtolower( trim( $header_name ) );

					if ( strpos( $header_name_normalized, 'tax_' ) === 0 ) {
						$taxonomy_name = substr( $header_name_normalized, 4 ); // Remove tax_

						// Get taxonomy object to validate
						$taxonomy_obj = get_taxonomy( $taxonomy_name );
						if ( ! $taxonomy_obj ) {
							continue; // Skip invalid taxonomy
						}

						$meta_value = $row[ $j ] ?? '';
						if ( $meta_value !== '' ) {
							$term_values     = array_map( 'trim', explode( ',', $meta_value ) );
							$format_analysis = Swift_CSV_Helper::analyze_term_values_format( $term_values );

							$taxonomy_format_validation[ $taxonomy_name ] = array_merge(
								$format_analysis,
								[
									'sample_values' => $term_values,
									'taxonomy_name' => $taxonomy_name,
								]
							);
						}
					}
				}
				$first_row_processed = true;
			}
		}

		// Validate format consistency
		foreach ( $taxonomy_format_validation as $taxonomy_name => $validation ) {
			$consistency_result = Swift_CSV_Helper::validate_taxonomy_format_consistency( $taxonomy_format, $validation, $file_path );
			if ( ! $consistency_result['valid'] ) {
				Swift_CSV_Helper::send_error_response( $consistency_result['error'] );
				return null;
			}
		}

		return $taxonomy_format_validation;
	}

	/**
	 * Get allowed WP post fields that can be imported from CSV.
	 *
	 * @since 0.9.0
	 * @return array<int, string>
	 */
	private function get_allowed_post_fields() {
		return [
			'post_title',
			'post_content',
			'post_excerpt',
			'post_status',
			'post_name',
			'post_date',
			'post_date_gmt',
			'post_modified',
			'post_modified_gmt',
			'post_author',
			'post_parent',
			'menu_order',
			'guid',
			'comment_status',
			'ping_status',
		];
	}

	/**
	 * Ensure CSV has the required ID column.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $headers CSV headers.
	 * @param string             $file_path Temporary file path for cleanup.
	 * @return int|null ID column index or null on error (sends JSON response).
	 */
	private function ensure_id_column_or_send_error_and_cleanup( $headers, $file_path ) {
		$validation_result = Swift_CSV_Helper::validate_id_column( $headers, $file_path );

		if ( ! $validation_result['valid'] ) {
			Swift_CSV_Helper::send_error_response( $validation_result['error'] );
			return null;
		}

		return $validation_result['id_col'];
	}

	/**
	 * Count actual data rows (exclude empty lines).
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines.
	 * @return int
	 */
	private function count_total_rows( $lines ) {
		return Swift_CSV_Helper::count_data_rows( $lines );
	}

	/**
	 * Get cumulative counts from previous chunks.
	 *
	 * @since 0.9.0
	 * @return array{created:int,updated:int,errors:int}
	 */
	private function get_cumulative_counts() {
		$previous_created = isset( $_POST['cumulative_created'] ) ? intval( $_POST['cumulative_created'] ) : 0;
		$previous_updated = isset( $_POST['cumulative_updated'] ) ? intval( $_POST['cumulative_updated'] ) : 0;
		$previous_errors  = isset( $_POST['cumulative_errors'] ) ? intval( $_POST['cumulative_errors'] ) : 0;

		return [
			'created' => $previous_created,
			'updated' => $previous_updated,
			'errors'  => $previous_errors,
		];
	}

	/**
	 * Parse a CSV row.
	 *
	 * @since 0.9.0
	 * @param string $line CSV line.
	 * @param string $delimiter CSV delimiter.
	 * @return array
	 */
	private function parse_csv_row( $line, $delimiter ) {
		return Swift_CSV_Helper::parse_csv_row( $line, $delimiter );
	}

	/**
	 * Check if a CSV line is empty.
	 *
	 * @since 0.9.0
	 * @param string $line CSV line.
	 * @return bool
	 */
	private function is_empty_csv_line( $line ) {
		return Swift_CSV_Helper::is_empty_csv_line( $line );
	}

	/**
	 * Collect allowed post fields from a CSV row (header-driven).
	 *
	 * @since 0.9.0
	 * @param array<int, string> $headers CSV headers.
	 * @param array              $data CSV row data.
	 * @param array<int, string> $allowed_post_fields Allowed WP post fields.
	 * @return array<string, string>
	 */
	private function collect_post_fields_from_csv_row( $headers, $data, $allowed_post_fields ) {
		$post_fields_from_csv = [];
		for ( $j = 0; $j < count( $headers ); $j++ ) {
			$header = trim( (string) $headers[ $j ] );
			if ( $header === '' || $header === 'ID' ) {
				continue;
			}
			if ( ! in_array( $header, $allowed_post_fields, true ) ) {
				continue;
			}
			if ( ! array_key_exists( $j, $data ) ) {
				continue;
			}
			$value = (string) $data[ $j ];
			if ( $value === '' ) {
				continue;
			}
			if ( str_starts_with( $value, '"' ) && str_ends_with( $value, '"' ) ) {
				$value = substr( $value, 1, -1 );
				$value = str_replace( '""', '"', $value );
			}
			$post_fields_from_csv[ $header ] = $value;
		}
		return $post_fields_from_csv;
	}

	/**
	 * Find existing post ID for update.
	 *
	 * @since 0.9.0
	 * @param wpdb   $wpdb WordPress DB instance.
	 * @param string $update_existing Update flag.
	 * @param string $post_type Post type.
	 * @param string $post_id_from_csv Post ID from CSV.
	 * @return array{post_id:int|null,is_update:bool}
	 */
	private function find_existing_post_for_update( $wpdb, $update_existing, $post_type, $post_id_from_csv ) {
		$post_id   = null;
		$is_update = false;

		if ( $update_existing === '1' && ! empty( $post_id_from_csv ) ) {
			$existing_post_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = %s 
                 AND ID = %d 
                 LIMIT 1",
					$post_type,
					$post_id_from_csv
				)
			);

			if ( $existing_post_id ) {
				$post_id   = (int) $existing_post_id;
				$is_update = true;
			}
		}

		return [
			'post_id'   => $post_id,
			'is_update' => $is_update,
		];
	}

	/**
	 * Determine whether to skip row due to missing title.
	 *
	 * @since 0.9.0
	 * @param string                $update_existing Update flag.
	 * @param array<string, string> $post_fields_from_csv Post fields.
	 * @return bool
	 */
	private function should_skip_row_due_to_missing_title( $update_existing, $post_fields_from_csv ) {
		if ( $update_existing !== '1' && empty( $post_fields_from_csv['post_title'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Build post data for update.
	 *
	 * @since 0.9.0
	 * @param array<string, string> $post_fields_from_csv Post fields.
	 * @return array<string, mixed>
	 */
	private function build_post_data_for_update( $post_fields_from_csv ) {
		return Swift_CSV_Helper::build_post_data_for_update( $post_fields_from_csv );
	}

	/**
	 * Build post data for insert.
	 *
	 * @since 0.9.0
	 * @param array<string, string> $post_fields_from_csv Post fields.
	 * @param string                $post_type Post type.
	 * @return array<string, mixed>
	 */
	private function build_post_data_for_insert( $post_fields_from_csv, $post_type ) {
		return Swift_CSV_Helper::build_post_data_for_insert( $post_fields_from_csv, $post_type );
	}

	/**
	 * Execute post update.
	 *
	 * @since 0.9.0
	 * @param wpdb                 $wpdb WordPress DB instance.
	 * @param int|null             $post_id Post ID.
	 * @param array<string, mixed> $post_data Post data.
	 * @param bool                 $dry_run Dry run flag.
	 * @param array<int, string>   $dry_run_log Dry run log.
	 * @return int|false
	 */
	private function execute_post_update( $wpdb, $post_id, $post_data, $dry_run, &$dry_run_log ) {
		if ( empty( $post_data ) ) {
			return 0;
		}

		$post_data_formats = [];
		foreach ( array_keys( $post_data ) as $key ) {
			$post_data_formats[] = in_array( $key, [ 'post_author', 'post_parent', 'menu_order' ], true ) ? '%d' : '%s';
		}

		if ( $dry_run ) {
			error_log( "[Dry Run] Would update post ID: {$post_id} with title: " . ( $post_data['post_title'] ?? 'Untitled' ) );
			$dry_run_log[] = sprintf(
				/* translators: 1: post ID, 2: post title */
				__( 'Update post: ID=%1$s, title=%2$s', 'swift-csv' ),
				$post_id,
				$post_data['post_title'] ?? 'Untitled'
			);
			return 1;
		}

		return $wpdb->update(
			$wpdb->posts,
			$post_data,
			[ 'ID' => $post_id ],
			$post_data_formats,
			[ '%d' ]
		);
	}

	/**
	 * Execute post insert.
	 *
	 * @since 0.9.0
	 * @param wpdb                 $wpdb WordPress DB instance.
	 * @param array<string, mixed> $post_data Post data.
	 * @param bool                 $dry_run Dry run flag.
	 * @param array<int, string>   $dry_run_log Dry run log.
	 * @param int|null             $post_id Post ID.
	 * @return int|false
	 */
	private function execute_post_insert( $wpdb, $post_data, $dry_run, &$dry_run_log, &$post_id ) {
		$post_data_formats = [];
		foreach ( array_keys( $post_data ) as $key ) {
			$post_data_formats[] = in_array( $key, [ 'post_author', 'post_parent', 'menu_order' ], true ) ? '%d' : '%s';
		}

		if ( $dry_run ) {
			error_log( '[Dry Run] Would create new post with title: ' . ( $post_data['post_title'] ?? 'Untitled' ) );
			$dry_run_log[] = sprintf(
				/* translators: 1: post title */
				__( 'New post: title=%1$s', 'swift-csv' ),
				$post_data['post_title'] ?? 'Untitled'
			);
			$post_id = 0;
			return 1;
		}

		$result = $wpdb->insert( $wpdb->posts, $post_data, $post_data_formats );
		if ( $result !== false ) {
			$post_id = $wpdb->insert_id;
		}
		return $result;
	}

	/**
	 * Collect taxonomy fields from a CSV row.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $headers CSV headers.
	 * @param array              $data CSV row data.
	 * @return array{taxonomies:array<string,array<int,string>>,taxonomy_term_ids:array<string,array<int,int>>}
	 */
	private function collect_taxonomy_fields_from_row( $headers, $data ) {
		$taxonomies        = [];
		$taxonomy_term_ids = [];

		for ( $j = 0; $j < count( $headers ); $j++ ) {
			$header_name            = $headers[ $j ] ?? '';
			$header_name_normalized = $this->normalize_field_name( $header_name );

			// Skip empty headers and non-taxonomy fields
			if ( $header_name_normalized === '' || $header_name_normalized === 'ID' ) {
				continue;
			}
			if ( strpos( $header_name_normalized, 'tax_' ) !== 0 ) {
				continue;
			}

			if ( empty( trim( $data[ $j ] ?? '' ) ) ) {
				continue; // Skip empty fields
			}

			$meta_value = $data[ $j ];

			// Handle taxonomy (pipe-separated) - this is for article-taxonomy relationship
			$terms = array_map( 'trim', explode( '|', $meta_value ) );
			// Store by actual taxonomy name (without tax_ prefix)
			$taxonomy_name                = substr( $header_name_normalized, 4 ); // Remove 'tax_'
			$taxonomies[ $taxonomy_name ] = $terms;

			// Extract taxonomy name from header (remove tax_ prefix)
			if ( taxonomy_exists( $taxonomy_name ) ) {
				// Get term_ids for reuse
				$term_ids = [];
				foreach ( $terms as $term_name ) {
					if ( ! empty( $term_name ) ) {
						// Find existing term by name in the specific taxonomy
						$term = get_term_by( 'name', $term_name, $taxonomy_name );
						if ( $term ) {
							$term_ids[] = $term->term_id;
						}
					}
				}
				if ( ! empty( $term_ids ) ) {
					$taxonomy_term_ids[ $taxonomy_name ] = $term_ids;
				}
			}
		}

		return [
			'taxonomies'        => $taxonomies,
			'taxonomy_term_ids' => $taxonomy_term_ids,
		];
	}

	/**
	 * Collect meta fields from a CSV row.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $headers CSV headers.
	 * @param array              $data CSV row data.
	 * @param array<int, string> $allowed_post_fields Allowed WP post fields.
	 * @return array<string, string>
	 */
	private function collect_meta_fields_from_row( $headers, $data, $allowed_post_fields ) {
		$meta_fields = [];

		for ( $j = 0; $j < count( $headers ); $j++ ) {
			$header_name            = $headers[ $j ] ?? '';
			$header_name_normalized = $this->normalize_field_name( $header_name );

			// Skip empty headers and post fields
			if ( $header_name_normalized === '' || $header_name_normalized === 'ID' ) {
				continue;
			}
			if ( in_array( $header_name_normalized, $allowed_post_fields, true ) ) {
				continue;
			}

			if ( empty( trim( $data[ $j ] ?? '' ) ) ) {
				continue; // Skip empty fields
			}

			$meta_value = $data[ $j ];

			// Handle regular custom fields (cf_<field> => <field>) ONLY
			// Skip all other fields - only process cf_ prefixed fields
			if ( strpos( $header_name_normalized, 'cf_' ) !== 0 ) {
				continue; // Skip non-cf_ fields
			}

			$clean_field_name                 = substr( $header_name_normalized, 3 ); // Remove cf_
			$meta_fields[ $clean_field_name ] = (string) $meta_value;
		}

		return $meta_fields;
	}

	/**
	 * Collect taxonomies and meta fields from a CSV row.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $headers CSV headers.
	 * @param array              $data CSV row data.
	 * @param array<int, string> $allowed_post_fields Allowed WP post fields.
	 * @return array{meta_fields:array<string,string>,taxonomies:array<string,array<int,string>>,taxonomy_term_ids:array<string,array<int,int>>}
	 */
	private function collect_taxonomies_and_meta_fields_from_row( $headers, $data, $allowed_post_fields ) {
		// Collect taxonomy fields
		$taxonomy_data = $this->collect_taxonomy_fields_from_row( $headers, $data );

		// Collect meta fields
		$meta_fields = $this->collect_meta_fields_from_row( $headers, $data, $allowed_post_fields );

		return [
			'meta_fields'       => $meta_fields,
			'taxonomies'        => $taxonomy_data['taxonomies'],
			'taxonomy_term_ids' => $taxonomy_data['taxonomy_term_ids'],
		];
	}

	/**
	 * Resolve term IDs for a single taxonomy.
	 *
	 * @since 0.9.0
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $terms Term values.
	 * @param string $taxonomy_format Taxonomy format.
	 * @param array  $taxonomy_format_validation Taxonomy format validation.
	 * @return array<int, int>
	 */
	private function resolve_taxonomy_term_ids( $taxonomy, $terms, $taxonomy_format, $taxonomy_format_validation ) {
		$term_ids = [];
		foreach ( $terms as $term_value ) {
			$term_value = trim( (string) $term_value );
			if ( $term_value === '' ) {
				continue;
			}

			$resolved_term_ids = $this->resolve_term_ids_for_term_value( $taxonomy, $term_value, $taxonomy_format, $taxonomy_format_validation );
			foreach ( $resolved_term_ids as $resolved_term_id ) {
				$term_ids[] = $resolved_term_id;
			}
		}
		return $term_ids;
	}

	/**
	 * Apply taxonomy terms to a post.
	 *
	 * @since 0.9.0
	 * @param int    $post_id Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $term_ids Term IDs.
	 * @param bool   $dry_run Dry run flag.
	 * @param array  $dry_run_log Dry run log.
	 * @return void
	 */
	private function apply_taxonomy_terms_to_post( $post_id, $taxonomy, $term_ids, $dry_run, &$dry_run_log ) {
		if ( empty( $term_ids ) ) {
			return;
		}

		if ( $dry_run ) {
			error_log( "[Dry Run] Would set terms for post {$post_id}: " . implode( ', ', $term_ids ) );
			// Log each term for Dry Run
			foreach ( $term_ids as $term_id ) {
				$term = get_term( $term_id, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$dry_run_log[] = sprintf(
						/* translators: 1: term name, 2: term ID, 3: taxonomy name */
						__( 'Existing term: %1$s (ID: %2$s, taxonomy: %3$s)', 'swift-csv' ),
						$term->name,
						$term_id,
						$taxonomy
					);
				}
			}
		} else {
			wp_set_post_terms( $post_id, $term_ids, $taxonomy, false );
			error_log( "[Swift CSV] Set terms for post {$post_id}: " . implode( ', ', $term_ids ) );
		}
	}

	/**
	 * Apply taxonomy terms for a post.
	 *
	 * @since 0.9.0
	 * @param int                  $post_id Post ID.
	 * @param array<string, array> $taxonomies Taxonomy terms map.
	 * @param string               $taxonomy_format Taxonomy format.
	 * @param array                $taxonomy_format_validation Validation result.
	 * @param bool                 $dry_run Dry run flag.
	 * @param array<int, string>   $dry_run_log Dry run log.
	 * @return void
	 */
	private function apply_taxonomies_for_post( $post_id, $taxonomies, $taxonomy_format, $taxonomy_format_validation, $dry_run, &$dry_run_log ) {
		foreach ( $taxonomies as $taxonomy => $terms ) {
			if ( ! empty( $terms ) ) {
				// Debug log for taxonomy processing
				error_log( "[Swift CSV] Processing taxonomy: {$taxonomy}, format: {$taxonomy_format}" );

				// Resolve term IDs for this taxonomy
				$term_ids = $this->resolve_taxonomy_term_ids( $taxonomy, $terms, $taxonomy_format, $taxonomy_format_validation );

				// Apply terms to post
				$this->apply_taxonomy_terms_to_post( $post_id, $taxonomy, $term_ids, $dry_run, $dry_run_log );
			}
		}
	}

	/**
	 * Resolve term IDs from a term value.
	 *
	 * @since 0.9.0
	 * @param string $taxonomy Taxonomy name.
	 * @param string $term_value Term value.
	 * @param string $taxonomy_format Taxonomy format.
	 * @param array  $taxonomy_format_validation Taxonomy format validation.
	 * @return array<int, int>
	 */
	private function resolve_term_ids_for_term_value( $taxonomy, $term_value, $taxonomy_format, $taxonomy_format_validation ) {
		error_log( "[Swift CSV] Processing term value: '{$term_value}' with format: {$taxonomy_format}" );

		$term_ids = Swift_CSV_Helper::resolve_term_ids_from_value( $taxonomy, $term_value, $taxonomy_format, $taxonomy_format_validation );

		error_log( '[Swift CSV] Resolved ' . count( $term_ids ) . " term IDs for value '{$term_value}'" );

		return $term_ids;
	}

	/**
	 * Apply meta fields for a post.
	 *
	 * @since 0.9.0
	 * @param wpdb                  $wpdb WordPress DB instance.
	 * @param int                   $post_id Post ID.
	 * @param array<string, string> $meta_fields Meta fields.
	 * @param bool                  $dry_run Dry run flag.
	 * @param array<int, string>    $dry_run_log Dry run log.
	 * @return void
	 */
	private function apply_meta_fields_for_post( $wpdb, $post_id, $meta_fields, $dry_run, &$dry_run_log ) {
		foreach ( $meta_fields as $key => $value ) {
			// Skip empty values
			if ( $value === '' || $value === null ) {
				continue;
			}

			if ( $dry_run ) {
				error_log( "[Dry Run] Would process custom field: {$key} = {$value}" );

				// Handle multi-value custom fields (pipe-separated)
				if ( strpos( $value, '|' ) !== false ) {
					// Add each value separately
					$values = array_map( 'trim', explode( '|', $value ) );
					foreach ( $values as $single_value ) {
						if ( $single_value !== '' ) {
							$dry_run_log[] = sprintf(
								/* translators: 1: field name, 2: field value */
								__( 'Custom field (multi-value): %1$s = %2$s', 'swift-csv' ),
								$key,
								$single_value
							);
						}
					}
				} else {
					// Single value (including serialized strings)
					$dry_run_log[] = sprintf(
						/* translators: 1: field name, 2: field value */
						__( 'Custom field: %1$s = %2$s', 'swift-csv' ),
						$key,
						$value
					);
				}
			} else {
				// Always replace existing meta for this key to ensure update works even if meta row doesn't exist.
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->postmeta} 
	                         WHERE post_id = %d 
	                         AND meta_key = %s",
						$post_id,
						$key
					)
				);

				// Handle multi-value custom fields (pipe-separated)
				if ( strpos( $value, '|' ) !== false ) {
					// Add each value separately
					$values = array_map( 'trim', explode( '|', $value ) );
					foreach ( $values as $single_value ) {
						if ( $single_value !== '' ) {
							$wpdb->insert(
								$wpdb->postmeta,
								[
									'post_id'    => $post_id,
									'meta_key'   => $key,
									'meta_value' => $single_value,
								],
								[ '%d', '%s', '%s' ]
							);
						}
					}
				} else {
					// Single value (including serialized strings)
					$wpdb->insert(
						$wpdb->postmeta,
						[
							'post_id'    => $post_id,
							'meta_key'   => $key,
							'meta_value' => is_string( $value ) ? $value : maybe_serialize( $value ),
						],
						[ '%d', '%s', '%s' ]
					);
				}
			}
		}
	}

	/**
	 * Setup DB session for import process.
	 *
	 * @since 0.9.0
	 * @param wpdb $wpdb WordPress DB instance.
	 * @return void
	 */
	private function setup_db_session( $wpdb ) {
		// Disable locks
		$wpdb->query( 'SET SESSION autocommit = 1' );
		$wpdb->query( 'SET SESSION innodb_lock_wait_timeout = 1' );
	}

	/**
	 * Update GUID for newly inserted posts.
	 *
	 * @since 0.9.0
	 * @param wpdb $wpdb WordPress DB instance.
	 * @param int  $post_id Post ID.
	 * @return void
	 */
	private function update_guid_for_new_post( $wpdb, $post_id ) {
		$wpdb->update(
			$wpdb->posts,
			[ 'guid' => get_permalink( $post_id ) ],
			[ 'ID' => $post_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Parse PHP ini size string to bytes.
	 *
	 * @since 0.9.0
	 * @param string $size The size string (e.g., '10M', '1G')
	 * @return int Size in bytes
	 */
	private function parse_ini_size( $size ) {
		$unit  = strtoupper( substr( $size, -1 ) );
		$value = (int) substr( $size, 0, -1 );

		switch ( $unit ) {
			case 'G':
				return $value * 1024 * 1024 * 1024;
			case 'M':
				return $value * 1024 * 1024;
			case 'K':
				return $value * 1024;
			default:
				return (int) $size;
		}
	}
}
