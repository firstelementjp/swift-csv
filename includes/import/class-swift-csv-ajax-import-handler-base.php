<?php
/**
 * Ajax Import Handler Base
 *
 * Contains shared import flow logic for both wp-compatible and direct-sql methods.
 *
 * @since 0.9.10
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax Import Handler Base Class
 *
 * @since 0.9.10
 * @package Swift_CSV
 */
abstract class Swift_CSV_Ajax_Import_Handler_Base {

	/**
	 * Import log store.
	 *
	 * @since 0.9.10
	 * @var Swift_CSV_Import_Log_Store
	 */
	protected $log_store;

	/**
	 * Import CSV store.
	 *
	 * @since 0.9.10
	 * @var Swift_CSV_Import_Csv_Store
	 */
	protected $csv_store;

	/**
	 * CSV utility.
	 *
	 * @since 0.9.10
	 * @var Swift_CSV_Import_Csv
	 */
	protected $csv_util;

	/**
	 * CSV parser.
	 *
	 * @since 0.9.10
	 * @var Swift_CSV_Import_Csv_Parser
	 */
	protected $csv_parser;

	/**
	 * File processor.
	 *
	 * @since 0.9.10
	 * @var Swift_CSV_Import_File_Processor
	 */
	protected $file_processor;

	/**
	 * Batch processor.
	 *
	 * @since 0.9.10
	 * @var Swift_CSV_Import_Batch_Processor
	 */
	protected $batch_processor;

	/**
	 * Response manager.
	 *
	 * @since 0.9.10
	 * @var Swift_CSV_Import_Response_Manager
	 */
	protected $response_manager;

	/**
	 * Request parser.
	 *
	 * @since 0.9.10
	 * @var Swift_CSV_Import_Request_Parser
	 */
	protected $request_parser;

	/**
	 * Constructor.
	 *
	 * @since 0.9.10
	 * @param Swift_CSV_Import_Log_Store        $log_store Log store.
	 * @param Swift_CSV_Import_Csv_Store        $csv_store CSV store.
	 * @param Swift_CSV_Import_Csv              $csv_util CSV utility.
	 * @param Swift_CSV_Import_Csv_Parser       $csv_parser CSV parser.
	 * @param Swift_CSV_Import_File_Processor   $file_processor File processor.
	 * @param Swift_CSV_Import_Batch_Processor  $batch_processor Batch processor.
	 * @param Swift_CSV_Import_Response_Manager $response_manager Response manager.
	 */
	public function __construct(
		Swift_CSV_Import_Log_Store $log_store,
		Swift_CSV_Import_Csv_Store $csv_store,
		Swift_CSV_Import_Csv $csv_util,
		Swift_CSV_Import_Csv_Parser $csv_parser,
		Swift_CSV_Import_File_Processor $file_processor,
		Swift_CSV_Import_Batch_Processor $batch_processor,
		Swift_CSV_Import_Response_Manager $response_manager
	) {
		$this->log_store        = $log_store;
		$this->csv_store        = $csv_store;
		$this->csv_util         = $csv_util;
		$this->csv_parser       = $csv_parser;
		$this->file_processor   = $file_processor;
		$this->batch_processor  = $batch_processor;
		$this->response_manager = $response_manager;
		$this->request_parser   = new Swift_CSV_Import_Request_Parser();
	}

	/**
	 * Handle import request.
	 *
	 * @since 0.9.10
	 * @return void
	 */
	public function handle(): void {
		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		$file_result = $this->file_processor->handle_upload();
		if ( null === $file_result ) {
			return;
		}
		$file_path = $file_result['file_path'];

		$import_session = $this->request_parser->parse_import_session();
		if ( '' === $import_session ) {
			Swift_CSV_Helper::send_error_response( 'Missing import session' );
			return;
		}

		$start_row   = $this->request_parser->parse_start_row();
		$enable_logs = $this->request_parser->parse_enable_logs();
		$append_log  = null;
		if ( $enable_logs && 0 === $start_row ) {
			$this->log_store->init( $import_session );
		}
		if ( $enable_logs ) {
			$append_log = function ( array $detail ) use ( $import_session ): void {
				$this->log_store->append( $import_session, $detail );
			};
		}

		$csv_data = $this->csv_store->get( $import_session );
		if ( 0 === $start_row ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$csv_content = (string) file_get_contents( $file_path );
			$csv_content = str_replace( [ "\r\n", "\r" ], "\n", $csv_content );
		} else {
			$csv_content = '';
		}

		$parsed_config   = $this->request_parser->parse_import_config( $csv_content );
		$batch_size      = (int) $parsed_config['batch_size'];
		$post_type       = (string) $parsed_config['post_type'];
		$update_existing = (string) $parsed_config['update_existing'];
		$taxonomy_format = (string) $parsed_config['taxonomy_format'];
		$dry_run         = (bool) $parsed_config['dry_run'];

		if ( ! post_type_exists( $post_type ) ) {
			Swift_CSV_Helper::send_error_response( 'Invalid post type: ' . $post_type );
			return;
		}

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
			$csv_data = $this->csv_parser->parse_and_validate_csv( $csv_content, $config, $file_path );
			if ( null === $csv_data ) {
				$this->log_store->cleanup( $import_session );
				return;
			}
			$this->csv_store->set( $import_session, $csv_data );
		} elseif ( null === $csv_data ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$csv_content = (string) file_get_contents( $file_path );
			$csv_content = str_replace( [ "\r\n", "\r" ], "\n", $csv_content );
			$csv_data    = $this->csv_parser->parse_and_validate_csv( $csv_content, $config, $file_path );
			if ( null === $csv_data ) {
				$this->log_store->cleanup( $import_session );
				return;
			}
			$this->csv_store->set( $import_session, $csv_data );
		}

		$sample_filter_args = [
			'post_type' => $config['post_type'],
			'context'   => 'import_field_detection',
		];
		apply_filters( 'swift_csv_filter_sample_posts', [], $sample_filter_args );

		$total_rows             = $this->csv_util->count_total_rows( $csv_data['lines'] );
		$csv_data['total_rows'] = $total_rows;

		$batch_size           = $this->batch_processor->calculate_batch_size( $total_rows, $config );
		$config['batch_size'] = $batch_size;

		if ( $config['dry_run'] ) {
			$cumulative_counts = [
				'created' => 0,
				'updated' => 0,
				'errors'  => 0,
			];
		} else {
			$cumulative_counts = $this->response_manager->get_cumulative_counts();
		}

		$previous_created = $cumulative_counts['created'];
		$previous_updated = $cumulative_counts['updated'];
		$previous_errors  = $cumulative_counts['errors'];

		$counters = $this->batch_processor->process_batch( $config, $csv_data );

		if ( $enable_logs && ! empty( $counters['dry_run_details'] ) && is_array( $counters['dry_run_details'] ) ) {
			foreach ( $counters['dry_run_details'] as $detail ) {
				if ( ! is_array( $detail ) ) {
					continue;
				}
				$this->log_store->append( $import_session, $detail );
			}
		}

		$next_row = $config['start_row'] + $counters['processed'];
		$continue = $next_row < $total_rows;

		$this->response_manager->cleanup_temp_file_if_complete( $continue, $config['file_path'] );
		if ( ! $continue ) {
			$this->csv_store->cleanup( $import_session );
		}

		$recent_logs = [];
		if ( ! $continue ) {
			$recent_logs = [
				'created' => $this->log_store->get_recent_logs_by_type( $import_session, 'created', 30 ),
				'updated' => $this->log_store->get_recent_logs_by_type( $import_session, 'updated', 30 ),
				'errors'  => $this->log_store->get_recent_logs_by_type( $import_session, 'errors', 30 ),
			];
		}

		$this->response_manager->send_import_progress_response(
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
}
