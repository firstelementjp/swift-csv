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
 * Handles CSV import operations via AJAX requests
 *
 * This class processes CSV file uploads and chunked imports.
 * supporting custom post types, taxonomies, and meta fields.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Import {
	/**
	 * Maximum number of import logs to store per session.
	 *
	 * @since 0.9.8
	 * @var int
	 */
	private $import_log_store_max = 500;

	/**
	 * Import log transient TTL in seconds.
	 *
	 * @since 0.9.8
	 * @var int
	 */
	private $import_log_store_ttl = 3600;

	/**
	 * CSV utility instance
	 *
	 * @since 0.9.0
	 * @var Swift_CSV_Import_Csv|null
	 */
	private $csv_util;

	/**
	 * Row context utility instance
	 *
	 * @since 0.9.0
	 * @var Swift_CSV_Import_Row_Context|null
	 */
	private $row_context_util;

	/**
	 * Meta/taxonomy utility instance
	 *
	 * @since 0.9.0
	 * @var Swift_CSV_Import_Meta_Tax|null
	 */
	private $meta_tax_util;

	/**
	 * Persister utility instance
	 *
	 * @since 0.9.0
	 * @var Swift_CSV_Import_Persister|null
	 */
	private $persister_util;

	/**
	 * Row processor utility instance
	 *
	 * @since 0.9.0
	 * @var Swift_CSV_Import_Row_Processor|null
	 */
	private $row_processor_util;

	/**
	 * File processor utility instance
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_File_Processor|null
	 */
	private $file_processor_util;

	/**
	 * Batch processor utility instance
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Batch_Processor|null
	 */
	private $batch_processor_util;

	/**
	 * Response manager utility instance
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Response_Manager|null
	 */
	private $response_manager_util;

	/**
	 * CSV parser utility instance
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Csv_Parser|null
	 */
	private $csv_parser_util;

	/**
	 * Environment manager utility instance
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Environment_Manager|null
	 */
	private $environment_manager_util;

	/**
	 * Whether Pro license is active
	 *
	 * @since 0.9.7
	 * @var bool|null
	 */
	private static $pro_license_active = null;

	/**
	 * Constructor: Register AJAX hooks
	 *
	 * @since 0.9.0
	 */
	public function __construct() {
		// Only use import_handler to avoid duplicate file processing.
		add_action( 'wp_ajax_swift_csv_ajax_import', [ $this, 'import_handler' ] );
		add_action( 'wp_ajax_swift_csv_ajax_import_logs', [ $this, 'handle_ajax_import_logs' ] );
	}

	/**
	 * Get the transient key for the import log store.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session ID.
	 * @return string
	 */
	private function get_import_log_transient_key( string $import_session ): string {
		$user_id = get_current_user_id();
		return 'swift_csv_import_logs_' . $user_id . '_' . $import_session;
	}

	/**
	 * Initialize import log store.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session ID.
	 * @return void
	 */
	private function init_import_log_store( string $import_session ): void {
		$transient_key = $this->get_import_log_transient_key( $import_session );
		set_transient(
			$transient_key,
			[
				'last_id' => 0,
				'logs'    => [],
			],
			$this->import_log_store_ttl
		);
	}

	/**
	 * Append an import log entry to the store.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session ID.
	 * @param array  $detail Log detail array.
	 * @return int New log ID.
	 */
	private function append_import_log( string $import_session, array $detail ): int {
		$transient_key = $this->get_import_log_transient_key( $import_session );
		$store         = get_transient( $transient_key );
		if ( ! is_array( $store ) ) {
			$store = [
				'last_id' => 0,
				'logs'    => [],
			];
		}

		$last_id = isset( $store['last_id'] ) ? (int) $store['last_id'] : 0;
		$new_id  = $last_id + 1;
		$logs    = isset( $store['logs'] ) && is_array( $store['logs'] ) ? $store['logs'] : [];
		$logs[]  = [
			'id'     => $new_id,
			'detail' => $detail,
		];

		if ( count( $logs ) > $this->import_log_store_max ) {
			$logs = array_slice( $logs, -1 * $this->import_log_store_max );
		}

		set_transient(
			$transient_key,
			[
				'last_id' => $new_id,
				'logs'    => $logs,
			],
			$this->import_log_store_ttl
		);

		return $new_id;
	}

	/**
	 * Fetch import logs since a given log ID.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session ID.
	 * @param int    $after_id Last seen log ID.
	 * @param int    $limit Maximum logs to return.
	 * @return array{last_id:int,logs:array<int,array{id:int,detail:array}>}
	 */
	private function fetch_import_logs_since( string $import_session, int $after_id, int $limit ): array {
		$transient_key = $this->get_import_log_transient_key( $import_session );
		$store         = get_transient( $transient_key );
		if ( ! is_array( $store ) ) {
			return [
				'last_id' => $after_id,
				'logs'    => [],
			];
		}

		$last_id = isset( $store['last_id'] ) ? (int) $store['last_id'] : 0;
		$logs    = isset( $store['logs'] ) && is_array( $store['logs'] ) ? $store['logs'] : [];

		$filtered = [];
		foreach ( $logs as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$item_id = isset( $item['id'] ) ? (int) $item['id'] : 0;
			if ( $item_id <= $after_id ) {
				continue;
			}
			$filtered[] = $item;
			if ( count( $filtered ) >= $limit ) {
				break;
			}
		}

		return [
			'last_id' => $last_id,
			'logs'    => $filtered,
		];
	}

	/**
	 * Cleanup import log store.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session ID.
	 * @return void
	 */
	private function cleanup_import_log_store( string $import_session ): void {
		delete_transient( $this->get_import_log_transient_key( $import_session ) );
	}

	/**
	 * Get recent logs by type for UI display.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session ID.
	 * @param string $type Log type: 'created', 'updated', 'errors'.
	 * @param int    $limit Maximum logs to return.
	 * @return array{items:array,total:int} Logs and total count.
	 */
	private function get_recent_logs_by_type( string $import_session, string $type, int $limit ): array {
		$all_logs = $this->fetch_import_logs_since( $import_session, 0, 500 );
		$filtered = [];

		foreach ( $all_logs['logs'] as $log_item ) {
			if ( ! isset( $log_item['detail']['status'] ) || ! isset( $log_item['detail']['action'] ) ) {
				continue;
			}

			$detail   = $log_item['detail'];
			$is_match = false;

			switch ( $type ) {
				case 'created':
					$is_match = ( 'success' === $detail['status'] && 'create' === $detail['action'] );
					break;
				case 'updated':
					$is_match = ( 'success' === $detail['status'] && 'update' === $detail['action'] );
					break;
				case 'errors':
					$is_match = ( 'error' === $detail['status'] );
					break;
			}

			if ( $is_match ) {
				$filtered[] = [
					'row'     => $detail['row'],
					'title'   => $detail['title'],
					'details' => $detail['details'] ?? '',
				];
			}
		}

		return [
			'items' => array_slice( $filtered, -1 * $limit ),
			'total' => count( $filtered ),
		];
	}

	/**
	 * Get the transient key for CSV parsed data store.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session ID.
	 * @return string
	 */
	private function get_import_csv_transient_key( string $import_session ): string {
		$user_id = get_current_user_id();
		return 'swift_csv_import_csv_' . $user_id . '_' . $import_session;
	}

	/**
	 * Cleanup CSV parsed data store.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session ID.
	 * @return void
	 */
	private function cleanup_import_csv_store( string $import_session ): void {
		delete_transient( $this->get_import_csv_transient_key( $import_session ) );
	}

	/**
	 * Handle AJAX request to fetch import logs.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle_ajax_import_logs(): void {
		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		$import_session = sanitize_key( $_POST['import_session'] ?? '' );
		if ( '' === $import_session ) {
			wp_send_json_error( 'Missing import session' );
			return;
		}

		$enable_logs = isset( $_POST['enable_logs'] ) && in_array( (string) $_POST['enable_logs'], [ '1', 'true' ], true );
		if ( ! $enable_logs ) {
			wp_send_json_success(
				[
					'last_id' => 0,
					'logs'    => [],
				]
			);
			return;
		}

		$after_id = isset( $_POST['after_id'] ) ? intval( $_POST['after_id'] ) : 0;
		$limit    = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 100;
		$limit    = max( 1, min( 200, $limit ) );

		$result = $this->fetch_import_logs_since( $import_session, $after_id, $limit );
		wp_send_json_success( $result );
	}

	/**
	 * Check if Pro version is licensed (static cached for performance)
	 *
	 * @since 0.9.7
	 * @return bool True if Pro version is available and license is active
	 */
	private function is_pro_version_licensed() {
		if ( null === self::$pro_license_active ) {
			self::$pro_license_active = Swift_CSV_License_Handler::is_pro_active();
		}
		return self::$pro_license_active;
	}

	/**
	 * Get CSV utility instance
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
	 * Get row context utility instance
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
	 * Get meta/taxonomy utility instance
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
	 * Get persister utility instance
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
	 * Get row processor instance
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
	 * Get file processor instance
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_File_Processor
	 */
	private function get_file_processor_util(): Swift_CSV_Import_File_Processor {
		if ( null === $this->file_processor_util ) {
			$this->file_processor_util = new Swift_CSV_Import_File_Processor();
		}
		return $this->file_processor_util;
	}

	/**
	 * Get batch processor instance
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Batch_Processor
	 */
	private function get_batch_processor_util(): Swift_CSV_Import_Batch_Processor {
		if ( null === $this->batch_processor_util ) {
			$this->batch_processor_util = new Swift_CSV_Import_Batch_Processor();
		}
		return $this->batch_processor_util;
	}

	/**
	 * Get response manager instance
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Response_Manager
	 */
	private function get_response_manager_util(): Swift_CSV_Import_Response_Manager {
		if ( null === $this->response_manager_util ) {
			$this->response_manager_util = new Swift_CSV_Import_Response_Manager();
		}
		return $this->response_manager_util;
	}

	/**
	 * Get CSV parser instance
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Csv_Parser
	 */
	private function get_csv_parser_util(): Swift_CSV_Import_Csv_Parser {
		if ( null === $this->csv_parser_util ) {
			$this->csv_parser_util = new Swift_CSV_Import_Csv_Parser();
		}
		return $this->csv_parser_util;
	}

	/**
	 * Get environment manager instance
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Environment_Manager
	 */
	private function get_environment_manager_util(): Swift_CSV_Import_Environment_Manager {
		if ( null === $this->environment_manager_util ) {
			$this->environment_manager_util = new Swift_CSV_Import_Environment_Manager();
		}
		return $this->environment_manager_util;
	}

	/**
	 * Build per-row import context using config values
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
	 * Build the per-row processing context
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
			'start_row'                  => (int) ( $config['start_row'] ?? 0 ),
			'headers'                    => $headers,
			'data'                       => [],
			'allowed_post_fields'        => $allowed_post_fields,
			'taxonomy_format'            => (string) ( $config['taxonomy_format'] ?? 'name' ),
			'taxonomy_format_validation' => $csv_data['taxonomy_format_validation'] ?? [],
		];
	}

	/**
	 * Skip empty CSV line and update processed counter
	 *
	 * @since 0.9.0
	 * @param string $line Raw CSV line.
	 * @param int    $processed Processed count (by reference).
	 * @return bool True if the line was skipped.
	 */
	private function maybe_skip_empty_csv_line( string $line, int &$processed ): bool {
		// Skip empty lines only.
		if ( ! $this->is_empty_csv_line( $line ) ) {
			return false;
		}

		++$processed; // Count empty lines as processed to avoid infinite loop.
		return true;
	}

	/**
	 * Check if a CSV line is empty
	 *
	 * @since 0.9.0
	 * @param string $line CSV line.
	 * @return bool
	 */
	private function is_empty_csv_line( string $line ): bool {
		return $this->get_csv_util()->is_empty_csv_line( $line );
	}

	/**
	 * Handle CSV import processing via AJAX
	 *
	 * @since 0.9.0
	 * @return void Sends JSON response with import results
	 */
	public function import_handler() {
		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		// Get file path from file-processor first.
		$file_processor = new Swift_CSV_Import_File_Processor();
		$file_result    = $file_processor->handle_upload();
		if ( null === $file_result ) {
			return;
		}
		$file_path = $file_result['file_path'];

		$import_session = sanitize_key( $_POST['import_session'] ?? '' );
		if ( '' === $import_session ) {
			Swift_CSV_Helper::send_error_response( 'Missing import session' );
			return;
		}

		$start_row   = isset( $_POST['start_row'] ) ? intval( $_POST['start_row'] ) : 0;
		$enable_logs = isset( $_POST['enable_logs'] ) && in_array( (string) $_POST['enable_logs'], [ '1', 'true' ], true );
		$append_log  = null;
		if ( $enable_logs && 0 === $start_row ) {
			$this->init_import_log_store( $import_session );
		}
		if ( $enable_logs ) {
			$append_log = function ( array $detail ) use ( $import_session ): void {
				$this->append_import_log( $import_session, $detail );
			};
		}

		$csv_store_key = $this->get_import_csv_transient_key( $import_session );
		$csv_data      = get_transient( $csv_store_key );
		if ( 0 === $start_row ) {
			// Read CSV content directly (first request only).
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$csv_content = (string) file_get_contents( $file_path );
			$csv_content = str_replace( [ "\r\n", "\r" ], "\n", $csv_content ); // Normalize line endings.
		} else {
			$csv_content = '';
		}

		// Extract and validate parameters.
		$batch_size      = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 10;
		$post_type       = sanitize_text_field( wp_unslash( $_POST['post_type'] ?? 'post' ) );
		$update_existing = sanitize_text_field( wp_unslash( $_POST['update_existing'] ?? 'no' ) );
		$taxonomy_format = sanitize_text_field( wp_unslash( $_POST['taxonomy_format'] ?? 'name' ) );
		$dry_run         = isset( $_POST['dry_run'] ) && in_array( (string) $_POST['dry_run'], [ '1', 'true' ], true );

		// Validate post type.
		if ( ! post_type_exists( $post_type ) ) {
			Swift_CSV_Helper::send_error_response( 'Invalid post type: ' . $post_type );
			return;
		}

		// Create config array.
		$config = [
			'file_path'       => $file_path,
			'start_row'       => $start_row,
			'batch_size'      => $batch_size,
			'post_type'       => $post_type,
			'update_existing' => $update_existing,
			'taxonomy_format' => $taxonomy_format,
			'dry_run'         => $dry_run,
			'csv_content'     => $csv_content,
			'import_session'  => $import_session,
			'append_log'      => $append_log,
		];

		if ( 0 === $start_row ) {
			$csv_data = $this->get_csv_parser_util()->parse_and_validate_csv( $csv_content, $config, $file_path );
			if ( null === $csv_data ) {
				$this->cleanup_import_log_store( $import_session );
				return;
			}
			set_transient( $csv_store_key, $csv_data, $this->import_log_store_ttl );
		} elseif ( ! is_array( $csv_data ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$csv_content = (string) file_get_contents( $file_path );
			$csv_content = str_replace( [ "\r\n", "\r" ], "\n", $csv_content );
			$csv_data    = $this->get_csv_parser_util()->parse_and_validate_csv( $csv_content, $config, $file_path );
			if ( null === $csv_data ) {
				$this->cleanup_import_log_store( $import_session );
				return;
			}
			set_transient( $csv_store_key, $csv_data, $this->import_log_store_ttl );
		}

		/**
		 * Filter sample posts for better field detection during import
		 *
		 * Allows extensions to modify sample posts used for field detection.
		 * This hook is ideal for Pro versions with ACF integration that need
		 * more sample posts to detect all fields.
		 *
		 * @since 0.9.0
		 * @param array $sample_post_ids Empty array to start (no sample posts needed for import)
		 * @param array $args Import arguments including post type
		 * @return array Modified sample post IDs
		 */
		$sample_filter_args = [
			'post_type' => $config['post_type'],
			'context'   => 'import_field_detection',
		];
		$sample_post_ids    = apply_filters( 'swift_csv_filter_sample_posts', [], $sample_filter_args );

		$total_rows             = $this->get_csv_util()->count_total_rows( $csv_data['lines'] );
		$csv_data['total_rows'] = $total_rows;

		// Calculate batch size using batch processor.
		$batch_size = $this->get_batch_processor_util()->calculate_batch_size( $total_rows, $config );

		// Update config with dynamic batch size.
		$config['batch_size'] = $batch_size;

		$counters = [
			'processed'       => 0,
			'created'         => 0,
			'updated'         => 0,
			'errors'          => 0,
			'dry_run_log'     => [],
			'dry_run_details' => [], // New: detailed row-by-row results.
		];

		// For dry run, reset cumulative counts to prevent log accumulation.
		if ( $config['dry_run'] ) {
			$cumulative_counts = [
				'created' => 0,
				'updated' => 0,
				'errors'  => 0,
			];
		} else {
			$cumulative_counts = $this->get_response_manager_util()->get_cumulative_counts();
		}
		$previous_created = $cumulative_counts['created'];
		$previous_updated = $cumulative_counts['updated'];
		$previous_errors  = $cumulative_counts['errors'];

		// Process batch using batch processor.
		$counters = $this->get_batch_processor_util()->process_batch( $config, $csv_data );

		// Flush any collected per-row details to the log store.
		if ( $enable_logs && ! empty( $counters['dry_run_details'] ) && is_array( $counters['dry_run_details'] ) ) {
			foreach ( $counters['dry_run_details'] as $detail ) {
				if ( ! is_array( $detail ) ) {
					continue;
				}
				$this->append_import_log( $import_session, $detail );
			}
		}

		$next_row = $config['start_row'] + $counters['processed'];
		$continue = $next_row < $total_rows;

		$this->get_response_manager_util()->cleanup_temp_file_if_complete( $continue, $config['file_path'] );
		if ( ! $continue ) {
			$this->cleanup_import_csv_store( $import_session );
		}

		// Prepare recent logs for UI display on completion.
		$recent_logs = [];
		if ( ! $continue ) {
			$recent_logs = [
				'created' => $this->get_recent_logs_by_type( $import_session, 'created', 30 ),
				'updated' => $this->get_recent_logs_by_type( $import_session, 'updated', 30 ),
				'errors'  => $this->get_recent_logs_by_type( $import_session, 'errors', 30 ),
			];
		}

		$this->get_response_manager_util()->send_import_progress_response(
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
			$counters['dry_run_log'],
			[],
			$recent_logs
		);
	}

	/**
	 * Handle successful row import
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

		// Record detailed processing information for both dry run and actual import.
		if ( $dry_run || true ) { // Always record details for UI display.
			$row_number = $context['start_row'] + $counters['processed'] + 1; // Correct row number from context.

			// Get title from data using header index.
			$post_title  = 'Untitled';
			$title_index = array_search( 'post_title', $headers, true );
			if ( false !== $title_index && isset( $data[ $title_index ] ) ) {
				$post_title = $data[ $title_index ];
			}

			$action = $is_update ? 'update' : 'create';

			// Run validation hook for both dry run and actual import.
			$validation_result = [
				'valid'    => true,
				'errors'   => [],
				'warnings' => [],
			];

			/**
			 * Validate data during processing (both dry run and actual import)
			 *
			 * Allows developers to implement custom validation logic for processing.
			 * This hook enables business rule validation, data quality checks,
			 * and custom error reporting for both preview and actual import.
			 *
			 * @since 0.9.7
			 * @param array{valid:bool,errors:array<string>,warnings:array<string>} $validation_result Validation result with errors and warnings.
			 * @param array{row:int,action:string,title:string,post_id:int} $detail Current row processing details.
			 * @param array{headers:array<int,string>,data:array<int,string>,post_type:string,dry_run:bool} $context Processing context including CSV data.
			 * @return array{valid:bool,errors:array<string>,warnings:array<string>} Modified validation result.
			 */
			$validation_result = apply_filters(
				'swift_csv_dry_run_validation',
				$validation_result,
				[
					'row'     => $row_number,
					'action'  => $action,
					'title'   => $post_title,
					'post_id' => $post_id,
				],
				[
					'headers'   => $headers,
					'data'      => $data,
					'post_type' => $context['post_type'] ?? 'post',
					'dry_run'   => $dry_run,
				]
			);

			// Determine status based on validation.
			$status          = 'success';
			$details_message = sprintf(
				$is_update ?
				/* translators: 1: Post ID, 2: Post title */
				__( 'Update post: ID=%1$s, title=%2$s', 'swift-csv' ) :
				/* translators: 1: Post title */
				__( 'New post: title=%1$s, ID will be assigned', 'swift-csv' ),
				$post_id,
				$post_title
			);

			// Handle validation errors.
			if ( ! $validation_result['valid'] ) {
				$status          = 'error';
				$details_message = __( 'Validation failed:', 'swift-csv' ) . ' ' . implode( ', ', $validation_result['errors'] );
			}

			// Add warnings if any.
			if ( ! empty( $validation_result['warnings'] ) ) {
				$details_message .= ' ' . __( 'Warnings:', 'swift-csv' ) . ' ' . implode( ', ', $validation_result['warnings'] );
			}

			$detail = [
				'row'     => $row_number,
				'action'  => $action,
				'title'   => $post_title,
				'post_id' => $post_id,
				'status'  => $status,
				'details' => $details_message,
			];

			$import_session = (string) ( $context['import_session'] ?? '' );
			if ( '' !== $import_session ) {
				$this->append_import_log( $import_session, $detail );
			}
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
		$this->run_custom_field_processing_hook( $post_id, $prepared_meta_fields );
	}

	/**
	 * Run custom field processing hook for extensions
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
	 * Verify nonce for AJAX request
	 *
	 * @since 0.9.0
	 * @return bool True if nonce is valid.
	 */
	private function verify_nonce_or_send_error_and_cleanup(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$nonce     = filter_input( INPUT_POST, 'nonce' );
		$file_path = sanitize_text_field( wp_unslash( $_POST['file_path'] ?? '' ) );

		// Verify nonce for security.
		if ( ! wp_verify_nonce( $nonce, 'swift_csv_ajax_nonce' ) ) {
			Swift_CSV_Helper::send_security_error( $file_path );
			return false;
		}

		return true;
	}

	/**
	 * Get allowed WP post fields that can be imported from CSV
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
}
