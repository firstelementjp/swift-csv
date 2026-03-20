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
	 * Import CSV store.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Csv_Store|null
	 */
	private $csv_store;

	/**
	 * Import log store.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Log_Store|null
	 */
	private $log_store;

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
		// Register only when the hook is not already registered by unified router.
		if ( false === has_action( 'wp_ajax_swift_csv_ajax_import' ) ) {
			add_action( 'wp_ajax_swift_csv_ajax_import', [ $this, 'import_handler' ] );
		}
		if ( false === has_action( 'wp_ajax_swift_csv_ajax_import_logs' ) ) {
			add_action( 'wp_ajax_swift_csv_ajax_import_logs', [ $this, 'handle_ajax_import_logs' ] );
		}
	}

	/**
	 * Get import log store instance.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Log_Store
	 */
	private function get_log_store(): Swift_CSV_Import_Log_Store {
		if ( null === $this->log_store ) {
			$this->log_store = new Swift_CSV_Import_Log_Store();
		}
		return $this->log_store;
	}

	/**
	 * Get import CSV store instance.
	 *
	 * @since 0.9.8
	 * @return Swift_CSV_Import_Csv_Store
	 */
	private function get_csv_store(): Swift_CSV_Import_Csv_Store {
		if ( null === $this->csv_store ) {
			$this->csv_store = new Swift_CSV_Import_Csv_Store();
		}
		return $this->csv_store;
	}



	/**
	 * Handle AJAX request to fetch import logs.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle_ajax_import_logs(): void {
		$router = new Swift_CSV_Ajax_Import_Unified( false );
		$router->handle_ajax_import_logs();
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
		$router = new Swift_CSV_Ajax_Import_Unified( false );
		$router->handle();
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
