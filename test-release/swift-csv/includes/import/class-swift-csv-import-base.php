<?php
/**
 * Base Import Class for Swift CSV
 *
 * Provides common functionality for WP-compatible and future Direct SQL imports.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base Import Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
abstract class Swift_CSV_Import_Base {

	/**
	 * Import log store.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Log_Store
	 */
	protected $log_store;

	/**
	 * Import CSV store.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Csv_Store
	 */
	protected $csv_store;

	/**
	 * CSV utility.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Csv
	 */
	protected $csv_util;

	/**
	 * CSV parser.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Csv_Parser
	 */
	protected $csv_parser;

	/**
	 * File processor.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_File_Processor
	 */
	protected $file_processor;

	/**
	 * Batch processor.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Batch_Processor
	 */
	protected $batch_processor;

	/**
	 * Response manager.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Response_Manager
	 */
	protected $response_manager;

	/**
	 * Request parser.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Request_Parser
	 */
	protected $request_parser;

	/**
	 * Taxonomy utility.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Taxonomy_Util
	 */
	protected $taxonomy_util;

	/**
	 * Import cancel manager.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Import_Cancel_Manager
	 */
	protected $cancel_manager;

	/**
	 * Constructor.
	 *
	 * @since 0.9.8
	 * @param Swift_CSV_Import_Log_Store|null        $log_store Log store.
	 * @param Swift_CSV_Import_Csv_Store|null        $csv_store CSV store.
	 * @param Swift_CSV_Import_Csv|null              $csv_util CSV utility.
	 * @param Swift_CSV_Import_Csv_Parser|null       $csv_parser CSV parser.
	 * @param Swift_CSV_Import_File_Processor|null   $file_processor File processor.
	 * @param Swift_CSV_Import_Batch_Processor|null  $batch_processor Batch processor.
	 * @param Swift_CSV_Import_Response_Manager|null $response_manager Response manager.
	 * @param Swift_CSV_Import_Request_Parser|null   $request_parser Request parser.
	 * @param Swift_CSV_Import_Taxonomy_Util|null    $taxonomy_util Taxonomy util.
	 * @param Swift_CSV_Import_Cancel_Manager|null   $cancel_manager Import cancel manager.
	 */
	public function __construct(
		?Swift_CSV_Import_Log_Store $log_store = null,
		?Swift_CSV_Import_Csv_Store $csv_store = null,
		?Swift_CSV_Import_Csv $csv_util = null,
		?Swift_CSV_Import_Csv_Parser $csv_parser = null,
		?Swift_CSV_Import_File_Processor $file_processor = null,
		?Swift_CSV_Import_Batch_Processor $batch_processor = null,
		?Swift_CSV_Import_Response_Manager $response_manager = null,
		?Swift_CSV_Import_Request_Parser $request_parser = null,
		?Swift_CSV_Import_Taxonomy_Util $taxonomy_util = null,
		?Swift_CSV_Import_Cancel_Manager $cancel_manager = null
	) {
		$this->taxonomy_util    = $taxonomy_util ?? new Swift_CSV_Import_Taxonomy_Util();
		$this->log_store        = $log_store ?? new Swift_CSV_Import_Log_Store();
		$this->csv_store        = $csv_store ?? new Swift_CSV_Import_Csv_Store();
		$this->csv_util         = $csv_util ?? new Swift_CSV_Import_Csv();
		$this->csv_parser       = $csv_parser ?? new Swift_CSV_Import_Csv_Parser( $this->taxonomy_util );
		$this->file_processor   = $file_processor ?? new Swift_CSV_Import_File_Processor();
		$this->batch_processor  = $batch_processor ?? new Swift_CSV_Import_Batch_Processor( null, null, null, null, null, $this->taxonomy_util );
		$this->response_manager = $response_manager ?? new Swift_CSV_Import_Response_Manager();
		$this->request_parser   = $request_parser ?? new Swift_CSV_Import_Request_Parser();
		$this->cancel_manager   = $cancel_manager ?? new Swift_CSV_Import_Cancel_Manager();
	}

	/**
	 * Handle file upload.
	 *
	 * @since 0.9.8
	 * @return array|null Upload result or null when upload failed (response already sent).
	 */
	protected function upload_file_or_return_null(): ?array {
		return $this->file_processor->handle_upload();
	}

	/**
	 * Get import session key.
	 *
	 * @since 0.9.8
	 * @return string Import session (empty string when missing; error response is sent).
	 */
	protected function get_import_session_or_send_error(): string {
		$import_session = $this->request_parser->parse_import_session();
		if ( '' === $import_session ) {
			Swift_CSV_Ajax_Util::send_error_response( 'Missing import session' );
			return '';
		}
		return $import_session;
	}

	/**
	 * Build append-log callback.
	 *
	 * Initializes log store when needed.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session key.
	 * @param bool   $enable_logs Whether logs are enabled.
	 * @param int    $start_row Start row.
	 * @return callable|null Append callback or null.
	 */
	protected function build_append_log_callback( string $import_session, bool $enable_logs, int $start_row ) {
		$append_log = null;
		if ( $enable_logs && 0 === $start_row ) {
			$this->log_store->init( $import_session );
		}
		if ( $enable_logs ) {
			$append_log = function ( array $detail ) use ( $import_session ): void {
				$this->log_store->append( $import_session, $detail );
			};
		}
		return $append_log;
	}

	/**
	 * Read CSV content for the first request.
	 *
	 * @since 0.9.8
	 * @param string $file_path Uploaded file path.
	 * @param int    $start_row Start row.
	 * @return string CSV content (empty string when not needed).
	 */
	protected function read_csv_content_for_start_row( string $file_path, int $start_row ): string {
		if ( 0 !== $start_row ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$csv_content = (string) file_get_contents( $file_path );
		return str_replace( [ "\r\n", "\r" ], "\n", $csv_content );
	}

	/**
	 * Build normalized import configuration array.
	 *
	 * @since 0.9.8
	 * @param array         $parsed_config Parsed config.
	 * @param string        $file_path Uploaded file path.
	 * @param int           $start_row Start row.
	 * @param string        $csv_content CSV content.
	 * @param string        $import_session Import session key.
	 * @param callable|null $append_log Append log callback.
	 * @return array Import config. Returns empty array if validation fails (response already sent).
	 */
	protected function build_import_config_from_parsed( array $parsed_config, string $file_path, int $start_row, string $csv_content, string $import_session, $append_log ): array {
		$post_type = (string) $parsed_config['post_type'];
		if ( ! post_type_exists( $post_type ) ) {
			Swift_CSV_Ajax_Util::send_error_response( 'Invalid post type: ' . $post_type );
			return [];
		}

		return [
			'file_path'       => $file_path,
			'start_row'       => $start_row,
			'batch_size'      => (int) $parsed_config['batch_size'],
			'post_type'       => $post_type,
			'update_existing' => (string) $parsed_config['update_existing'],
			'taxonomy_format' => (string) $parsed_config['taxonomy_format'],
			'dry_run'         => (bool) $parsed_config['dry_run'],
			'csv_content'     => $csv_content,
			'import_session'  => $import_session,
			'append_log'      => $append_log,
		];
	}

	/**
	 * Ensure parsed CSV data is available for current request.
	 *
	 * @since 0.9.8
	 * @param array|null $csv_data Cached csv data.
	 * @param int        $start_row Start row.
	 * @param string     $file_path Uploaded file path.
	 * @param array      $config Import config.
	 * @param string     $import_session Import session key.
	 * @return array|null Parsed csv data or null when validation fails (response already sent).
	 */
	protected function ensure_csv_data_available( ?array $csv_data, int $start_row, string $file_path, array $config, string $import_session ): ?array {
		if ( 0 === $start_row ) {
			$csv_data = $this->csv_parser->parse_and_validate_csv( (string) $config['csv_content'], $config, $file_path );
			if ( null === $csv_data ) {
				$this->log_store->cleanup( $import_session );
				Swift_CSV_Ajax_Util::send_error_response( 'CSV parsing failed' );
				return null;
			}
			$this->csv_store->set( $import_session, $csv_data );
			return $csv_data;
		}

		if ( null !== $csv_data ) {
			return $csv_data;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$csv_content = (string) file_get_contents( $file_path );
		$csv_content = str_replace( [ "\r\n", "\r" ], "\n", $csv_content );
		$csv_data    = $this->csv_parser->parse_and_validate_csv( $csv_content, $config, $file_path );
		if ( null === $csv_data ) {
			$this->log_store->cleanup( $import_session );
			Swift_CSV_Ajax_Util::send_error_response( 'CSV parsing failed' );
			return null;
		}
		$this->csv_store->set( $import_session, $csv_data );
		return $csv_data;
	}

	/**
	 * Build recent logs array for UI.
	 *
	 * @since 0.9.8
	 * @param bool   $should_continue Whether import continues.
	 * @param string $import_session Import session key.
	 * @return array Recent logs by type.
	 */
	protected function build_recent_logs_if_complete( bool $should_continue, string $import_session ): array {
		if ( $should_continue ) {
			return [];
		}

		$max_entries = (int) apply_filters( 'swift_csv_max_log_entries', 30 );
		$max_entries = max( 1, $max_entries );
		return [
			'created' => $this->log_store->get_recent_logs_by_type( $import_session, 'created', $max_entries ),
			'updated' => $this->log_store->get_recent_logs_by_type( $import_session, 'updated', $max_entries ),
			'errors'  => $this->log_store->get_recent_logs_by_type( $import_session, 'errors', $max_entries ),
		];
	}

	/**
	 * Check whether the current import session is cancelled.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session key.
	 * @return bool True when cancelled.
	 */
	protected function is_import_cancelled( string $import_session ): bool {
		return $this->cancel_manager->is_cancelled( $import_session );
	}

	/**
	 * Cleanup import session artifacts.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session key.
	 * @return void
	 */
	protected function cleanup_import_session( string $import_session ): void {
		$this->csv_store->cleanup( $import_session );
		$this->log_store->cleanup( $import_session );
		$this->cancel_manager->cleanup( $import_session );
	}

	/**
	 * Run the import.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	abstract public function import(): void;
}
