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
	 * CSV utility instance.
	 *
	 * @since 0.9.0
	 * @var Swift_CSV_Import_Csv|null
	 */
	private $csv_util;

	/**
	 * Row context utility instance.
	 *
	 * @since 0.9.0
	 * @var Swift_CSV_Import_Row_Context|null
	 */
	private $row_context_util;

	/**
	 * Meta/taxonomy utility instance.
	 *
	 * @since 0.9.0
	 * @var Swift_CSV_Import_Meta_Tax|null
	 */
	private $meta_tax_util;

	/**
	 * Persister utility instance.
	 *
	 * @since 0.9.0
	 * @var Swift_CSV_Import_Persister|null
	 */
	private $persister_util;

	/**
	 * Row processor utility instance.
	 *
	 * @since 0.9.0
	 * @var Swift_CSV_Import_Row_Processor|null
	 */
	private $row_processor_util;

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
	 * Get CSV utility instance.
	 *
	 * @since 0.9.0
	 * @return Swift_CSV_Import_Csv
	 */
	private function get_csv_util(): Swift_CSV_Import_Csv {
		if ( null === $this->csv_util ) {
			$this->csv_util = new Swift_CSV_Import_Csv();
		}
		return $this->csv_util;
	}

	/**
	 * Get row context utility instance.
	 *
	 * @since 0.9.0
	 * @return Swift_CSV_Import_Row_Context
	 */
	private function get_row_context_util(): Swift_CSV_Import_Row_Context {
		if ( null === $this->row_context_util ) {
			$this->row_context_util = new Swift_CSV_Import_Row_Context( $this->get_csv_util() );
		}
		return $this->row_context_util;
	}

	/**
	 * Get meta/taxonomy utility instance.
	 *
	 * @since 0.9.0
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
	 * @since 0.9.0
	 * @return Swift_CSV_Import_Persister
	 */
	private function get_persister_util(): Swift_CSV_Import_Persister {
		if ( null === $this->persister_util ) {
			$this->persister_util = new Swift_CSV_Import_Persister();
		}
		return $this->persister_util;
	}

	/**
	 * Get row processor instance.
	 *
	 * @since 0.9.0
	 * @return Swift_CSV_Import_Row_Processor
	 */
	private function get_row_processor_util(): Swift_CSV_Import_Row_Processor {
		if ( null === $this->row_processor_util ) {
			$this->row_processor_util = new Swift_CSV_Import_Row_Processor();
		}
		return $this->row_processor_util;
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

		Swift_CSV_Helper::setup_db_session( $wpdb );

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
	 * @param string $file_path Temporary file path for cleanup.
	 * @param string $taxonomy_format Taxonomy format.
	 * @return array{lines:array,delimiter:string,headers:array<int,string>,taxonomy_format_validation:array}|null
	 */
	private function parse_and_validate_csv( string $file_path, string $taxonomy_format ): ?array {
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
	 * @param array{file_path:string,start_row:int,batch_size:int,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool} $config Import configuration.
	 * @param array{lines:array<int,string>,delimiter:string,headers:array<int,string>,taxonomy_format_validation:array,total_rows:int}        $csv_data Parsed CSV data.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                            $counters Counters (by reference).
	 * @return void
	 */
	private function process_batch_import( array $config, array $csv_data, array &$counters ): void {
		global $wpdb;

		$allowed_post_fields = $this->get_allowed_post_fields();
		$id_col              = $this->ensure_id_column_or_send_error_and_cleanup( $csv_data['headers'], $config['file_path'] );
		if ( null === $id_col ) {
			return;
		}

		for ( $i = $config['start_row']; $i < min( $config['start_row'] + $config['batch_size'], $csv_data['total_rows'] ); $i++ ) {
			$this->process_import_loop_iteration( $wpdb, $config, $csv_data, $allowed_post_fields, $i, $counters );
		}
	}

	/**
	 * Process one import loop iteration.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                                                             $wpdb WordPress database handler.
	 * @param array{file_path:string,start_row:int,batch_size:int,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool} $config Import configuration.
	 * @param array{lines:array<int,string>,delimiter:string,headers:array<int,string>,taxonomy_format_validation:array,total_rows:int}        $csv_data Parsed CSV data.
	 * @param array<int, string>                                                                                                               $allowed_post_fields Allowed post fields.
	 * @param int                                                                                                                              $index Row index.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                            $counters Counters (by reference).
	 * @return void
	 */
	private function process_import_loop_iteration(
		wpdb $wpdb,
		array $config,
		array $csv_data,
		array $allowed_post_fields,
		int $index,
		array &$counters
	) {
		$line      = $csv_data['lines'][ $index ] ?? '';
		$delimiter = $csv_data['delimiter'] ?? ',';
		$headers   = $csv_data['headers'] ?? [];

		$processed = &$counters['processed'];
		if ( $this->maybe_skip_empty_csv_line( $line, $processed ) ) {
			return;
		}

		$this->process_row_if_possible( $wpdb, $config, $csv_data, $allowed_post_fields, $line, $delimiter, $headers, $counters );
	}

	/**
	 * Process one CSV row if it can be converted to a valid row context.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                                                             $wpdb WordPress database handler.
	 * @param array{file_path:string,start_row:int,batch_size:int,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool} $config Import configuration.
	 * @param array{lines:array<int,string>,delimiter:string,headers:array<int,string>,taxonomy_format_validation:array,total_rows:int}        $csv_data Parsed CSV data.
	 * @param array<int, string>                                                                                                               $allowed_post_fields Allowed post fields.
	 * @param string                                                                                                                           $line Raw CSV line.
	 * @param string                                                                                                                           $delimiter CSV delimiter.
	 * @param array<int, string>                                                                                                               $headers CSV headers.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                            $counters Counters (by reference).
	 * @return void
	 */
	private function process_row_if_possible( wpdb $wpdb, array $config, array $csv_data, array $allowed_post_fields, string $line, string $delimiter, array $headers, array &$counters ): void {
		$row_context = $this->build_import_row_context_from_config( $wpdb, $config, $line, $delimiter, $headers, $allowed_post_fields );
		if ( null === $row_context ) {
			return;
		}

		$this->process_row_context(
			$wpdb,
			$row_context,
			$this->build_row_processing_context( $config, $csv_data, $headers, $allowed_post_fields ),
			$counters
		);
	}

	/**
	 * Build per-row import context using config values.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                                                             $wpdb WordPress database handler.
	 * @param array{file_path:string,start_row:int,batch_size:int,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool} $config Import configuration.
	 * @param string                                                                                                                           $line Raw CSV line.
	 * @param string                                                                                                                           $delimiter CSV delimiter.
	 * @param array<int, string>                                                                                                               $headers CSV headers.
	 * @param array<int, string>                                                                                                               $allowed_post_fields Allowed post fields.
	 * @return array{data:array<int,string>,post_fields_from_csv:array<string,mixed>,post_id:int,is_update:bool}|null Null means skip this row.
	 */
	private function build_import_row_context_from_config( wpdb $wpdb, array $config, string $line, string $delimiter, array $headers, array $allowed_post_fields ): ?array {
		return $this->get_row_context_util()->build_import_row_context_from_config( $wpdb, $config, $line, $delimiter, $headers, $allowed_post_fields );
	}

	/**
	 * Build the per-row processing context.
	 *
	 * @since 0.9.0
	 * @param array{file_path:string,start_row:int,batch_size:int,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool} $config Import configuration.
	 * @param array{lines:array<int,string>,delimiter:string,headers:array<int,string>,taxonomy_format_validation:array,total_rows:int}        $csv_data Parsed CSV data.
	 * @param array<int, string>                                                                                                               $headers CSV headers.
	 * @param array<int, string>                                                                                                               $allowed_post_fields Allowed post fields.
	 * @return array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array}
	 */
	private function build_row_processing_context( array $config, array $csv_data, array $headers, array $allowed_post_fields ): array {
		return [
			'post_type'                  => (string) ( $config['post_type'] ?? 'post' ),
			'dry_run'                    => (bool) ( $config['dry_run'] ?? false ),
			'headers'                    => $headers,
			'data'                       => [],
			'allowed_post_fields'        => $allowed_post_fields,
			'taxonomy_format'            => (string) ( $config['taxonomy_format'] ?? 'name' ),
			'taxonomy_format_validation' => $csv_data['taxonomy_format_validation'] ?? [],
		];
	}

	/**
	 * Process an import row context by running per-row import logic.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                                                                                                                $wpdb WordPress database handler.
	 * @param array{data:array<int,string>,post_fields_from_csv:array<string,mixed>,post_id:int|null,is_update:bool}                                                                              $row_context Row context.
	 * @param array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array} $context Context values for row processing.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                                                                               $counters Counters (by reference).
	 * @return void
	 */
	private function process_row_context(
		wpdb $wpdb,
		array $row_context,
		array $context,
		array &$counters
	) {
		$this->get_row_processor_util()->process_row_context(
			$wpdb,
			$row_context,
			$context,
			$counters,
			function ( wpdb $wpdb, bool $is_update, &$post_id, array $post_fields_from_csv, array $single_row_context, array &$counters ): void {
				$this->process_single_import_row( $wpdb, $is_update, $post_id, $post_fields_from_csv, $single_row_context, $counters );
			}
		);
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
	 * Process one import row including DB persist and success/error handling.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                                                                                                                $wpdb WordPress database handler.
	 * @param bool                                                                                                                                                                                $is_update Whether this row updates an existing post.
	 * @param int|null                                                                                                                                                                            $post_id Post ID (by reference, updated on insert).
	 * @param array<string,mixed>                                                                                                                                                                 $post_fields_from_csv Post fields collected from CSV.
	 * @param array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array} $context Context values for row processing.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                                                                               $counters Counters (by reference).
	 * @return void
	 */
	private function process_single_import_row(
		wpdb $wpdb,
		bool $is_update,
		&$post_id,
		array $post_fields_from_csv,
		array $context,
		array &$counters
	) {
		$this->get_row_processor_util()->process_single_import_row(
			$wpdb,
			$is_update,
			$post_id,
			$post_fields_from_csv,
			$context,
			$counters,
			function ( wpdb $wpdb, bool $is_update, &$post_id, array $post_fields_from_csv, string $post_type, bool $dry_run, array &$dry_run_log ) {
				return $this->get_persister_util()->persist_post_row_from_csv( $wpdb, $is_update, $post_id, $post_fields_from_csv, $post_type, $dry_run, $dry_run_log );
			},
			function ( $result, wpdb $wpdb, bool $is_update, int $post_id, array $context, array &$counters ): void {
				$this->handle_row_result_after_persist( $result, $wpdb, $is_update, $post_id, $context, $counters );
			}
		);
	}

	/**
	 * Handle row result after persisting wp_posts data.
	 *
	 * @since 0.9.0
	 * @param int|false                                                                                                                                                                           $result DB result.
	 * @param wpdb                                                                                                                                                                                $wpdb WordPress database handler.
	 * @param bool                                                                                                                                                                                $is_update Whether this row updates an existing post.
	 * @param int                                                                                                                                                                                 $post_id Post ID.
	 * @param array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array} $context Context values for row processing.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                                                                               $counters Counters (by reference).
	 * @return void
	 */
	private function handle_row_result_after_persist(
		$result,
		wpdb $wpdb,
		bool $is_update,
		int $post_id,
		array $context,
		array &$counters
	) {
		$this->get_row_processor_util()->handle_row_result_after_persist(
			$result,
			$wpdb,
			$is_update,
			$post_id,
			$context,
			$counters,
			function ( wpdb $wpdb, int $post_id, bool $is_update, array $context, array &$counters ): void {
				$this->handle_successful_row_import( $wpdb, $post_id, $is_update, $context, $counters );
			}
		);
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

		$next_row = $config['start_row'] + $counters['processed'];
		$continue = $next_row < $total_rows;

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
	 * Send JSON response for import progress.
	 *
	 * @since 0.9.0
	 * @param int                $start_row Start row.
	 * @param int                $processed Processed count.
	 * @param int                $total_rows Total rows.
	 * @param int                $errors Error count.
	 * @param int                $created Created count.
	 * @param int                $updated Updated count.
	 * @param int                $previous_created Previous cumulative created.
	 * @param int                $previous_updated Previous cumulative updated.
	 * @param int                $previous_errors Previous cumulative errors.
	 * @param bool               $dry_run Dry run flag.
	 * @param array<int, string> $dry_run_log Dry run log.
	 * @return void
	 */
	private function send_import_progress_response( int $start_row, int $processed, int $total_rows, int $errors, int $created, int $updated, int $previous_created, int $previous_updated, int $previous_errors, bool $dry_run, array $dry_run_log ): void {
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
	private function cleanup_temp_file_if_complete( bool $continue, string $file_path ): void {
		if ( ! $continue && $file_path && file_exists( $file_path ) ) {
			unlink( $file_path );
		}
	}

	/**
	 * Handle successful row import.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                                                                                                    $wpdb WordPress DB instance.
	 * @param int                                                                                                                                                                     $post_id Post ID.
	 * @param bool                                                                                                                                                                    $is_update Whether this row updated an existing post.
	 * @param array{post_type:string,dry_run:bool,headers:array<int,string>,data:array,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array} $context Context values for row processing.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array}                                                                                               $counters Counters (by reference).
	 * @return void
	 */
	private function handle_successful_row_import( wpdb $wpdb, int $post_id, bool $is_update, array $context, array &$counters ) {
		$dry_run_log = &$counters['dry_run_log'];

		$headers                    = $context['headers'];
		$data                       = $context['data'];
		$allowed_post_fields        = $context['allowed_post_fields'];
		$taxonomy_format            = $context['taxonomy_format'];
		$taxonomy_format_validation = $context['taxonomy_format_validation'];
		$dry_run                    = $context['dry_run'];

		$this->get_row_processor_util()->apply_success_counters_and_guid(
			$wpdb,
			$post_id,
			$is_update,
			$counters,
			function ( bool $is_update, int &$processed, int &$created, int &$updated ): void {
				$this->increment_row_counters_on_success( $is_update, $processed, $created, $updated );
			},
			function ( wpdb $wpdb, int $post_id ): void {
				Swift_CSV_Helper::update_guid_for_new_post( $wpdb, $post_id );
			}
		);

		// Process meta fields and taxonomies
		$meta_context            = $context;
		$meta_context['post_id'] = $post_id;
		$result                  = $this->get_meta_tax_util()->process_meta_and_taxonomies_for_row( $wpdb, $meta_context, $counters );

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
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $meta_fields Meta fields.
	 * @return void
	 */
	private function run_custom_field_processing_hook( int $post_id, array $meta_fields ): void {
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
	private function increment_row_counters_on_success( bool $is_update, int &$processed, int &$created, int &$updated ): void {
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
	private function verify_nonce_or_send_error_and_cleanup(): bool {
		$nonce     = (string) ( $_POST['nonce'] ?? '' );
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
	private function read_uploaded_csv_content_or_send_error_and_cleanup( string $file_path ): ?string {
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
		$csv_content = (string) file_get_contents( $file['tmp_name'] );
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
	private function parse_csv_lines_preserving_quoted_newlines( string $csv_content ): array {
		return $this->get_csv_util()->parse_csv_lines_preserving_quoted_newlines( $csv_content );
	}

	/**
	 * Detect CSV delimiter from first line.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines.
	 * @return string
	 */
	private function detect_csv_delimiter( array $lines ): string {
		return $this->get_csv_util()->detect_csv_delimiter( $lines );
	}

	/**
	 * Read CSV header row and normalize it.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines (will consume the first line).
	 * @param string             $delimiter CSV delimiter.
	 * @return array<int, string>
	 */
	private function read_and_normalize_headers( array &$lines, string $delimiter ): array {
		return $this->get_csv_util()->read_and_normalize_headers( $lines, $delimiter );
	}

	/**
	 * Normalize header/field name.
	 *
	 * @since 0.9.0
	 * @param string $name Field name.
	 * @return string
	 */
	private function normalize_field_name( string $name ): string {
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
	private function count_total_rows( array $lines ): int {
		return $this->get_csv_util()->count_total_rows( $lines );
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
	 * @return array<int, string>
	 */
	private function parse_csv_row( string $line, string $delimiter ): array {
		return $this->get_csv_util()->parse_csv_row( $line, $delimiter );
	}

	/**
	 * Check if a CSV line is empty.
	 *
	 * @since 0.9.0
	 * @param string $line CSV line.
	 * @return bool
	 */
	private function is_empty_csv_line( string $line ): bool {
		return $this->get_csv_util()->is_empty_csv_line( $line );
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
	 * Persist post row from CSV.
	 *
	 * @since 0.9.0
	 * @param wpdb   $wpdb WordPress DB instance.
	 * @param bool   $is_update Update flag.
	 * @param int    $post_id Post ID.
	 * @param array  $post_fields_from_csv Post fields.
	 * @param string $post_type Post type.
	 * @param bool   $dry_run Dry run flag.
	 * @param array  $dry_run_log Dry run log.
	 * @return void
	 */
	private function persist_post_row_from_csv( wpdb $wpdb, bool $is_update, &$post_id, array $post_fields_from_csv, string $post_type, bool $dry_run, array &$dry_run_log ) {
		return $this->get_persister_util()->persist_post_row_from_csv( $wpdb, $is_update, $post_id, $post_fields_from_csv, $post_type, $dry_run, $dry_run_log );
	}

	/**
	 * Handle error and cleanup.
	 *
	 * @since 0.9.0
	 * @param string $error_message Error message.
	 * @param string $file_path     Temporary file path for cleanup.
	 * @return void
	 */
	private function handle_error_and_cleanup( string $error_message, string $file_path = '' ): void {
		if ( ! empty( $file_path ) ) {
			@unlink( $file_path );
		}
		Swift_CSV_Helper::send_error_response( $error_message );
	}
}
