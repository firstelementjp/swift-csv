<?php
/**
 * Ajax Import Handler (WP Compatible)
 *
 * Handles asynchronous CSV import with WordPress-compatible processing.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax Import Handler (WP Compatible) Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Import_Handler_WP_Compatible extends Swift_CSV_Ajax_Import_Handler_Base {

	/**
	 * Handle import request.
	 *
	 * @since 0.9.10
	 * @return void
	 */
	public function handle(): void {
		$file_result = $this->upload_file_or_return_null();
		if ( null === $file_result ) {
			return;
		}
		$file_path = $file_result['file_path'];

		$import_session = $this->get_import_session_or_send_error();
		if ( '' === $import_session ) {
			return;
		}

		$start_row   = $this->request_parser->parse_start_row();
		$enable_logs = $this->request_parser->parse_enable_logs();
		$append_log  = $this->build_append_log_callback( $import_session, $enable_logs, $start_row );

		$csv_data    = $this->csv_store->get( $import_session );
		$csv_content = $this->read_csv_content_for_start_row( $file_path, $start_row );

		$parsed_config = $this->request_parser->parse_import_config( $csv_content );
		$config        = $this->build_import_config_from_parsed( $parsed_config, $file_path, $start_row, $csv_content, $import_session, $append_log );
		if ( empty( $config ) ) {
			return;
		}

		$csv_data = $this->ensure_csv_data_available( $csv_data, $start_row, $file_path, $config, $import_session );
		if ( null === $csv_data ) {
			return;
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

		$next_row        = $config['start_row'] + $counters['processed'];
		$should_continue = $next_row < $total_rows;

		$this->response_manager->cleanup_temp_file_if_complete( $should_continue, $config['file_path'] );
		if ( ! $should_continue ) {
			$this->csv_store->cleanup( $import_session );
		}
		$recent_logs = $this->build_recent_logs_if_complete( $should_continue, $import_session );

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
			(bool) $config['dry_run'],
			$counters['dry_run_log'],
			$counters['dry_run_details'],
			$recent_logs
		);
	}
}
