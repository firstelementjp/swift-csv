<?php
/**
 * WP Compatible Import Class for Swift CSV
 *
 * Implements Swift_CSV_Import_Base using WordPress core functions.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Compatible Import Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Import_WP_Compatible extends Swift_CSV_Import_Base {

	/**
	 * Run the import.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function import(): void {
		Swift_CSV_Ajax_Util::set_stage( 'wp_compatible:start' );

		Swift_CSV_Ajax_Util::set_stage( 'wp_compatible:upload_file' );
		$file_result = $this->upload_file_or_return_null();
		if ( null === $file_result ) {
			if ( ! Swift_CSV_Ajax_Util::has_sent_response() ) {
				Swift_CSV_Ajax_Util::send_error_response( 'Upload failed' );
			}
			return;
		}
		$file_path = $file_result['file_path'];

		Swift_CSV_Ajax_Util::set_stage( 'wp_compatible:get_import_session' );
		$import_session = $this->get_import_session_or_send_error();
		if ( '' === $import_session ) {
			if ( ! Swift_CSV_Ajax_Util::has_sent_response() ) {
				Swift_CSV_Ajax_Util::send_error_response( 'Missing import session' );
			}
			return;
		}

		if ( $this->is_import_cancelled( $import_session ) ) {
			$this->cleanup_import_session( $import_session );
			Swift_CSV_Ajax_Util::send_error_response( 'Import cancelled by user' );
			return;
		}

		Swift_CSV_Ajax_Util::set_stage( 'wp_compatible:parse_request' );
		$start_row   = $this->request_parser->parse_start_row();
		$enable_logs = $this->request_parser->parse_enable_logs();
		$append_log  = $this->build_append_log_callback( $import_session, $enable_logs, $start_row );

		$csv_data    = $this->csv_store->get( $import_session );
		$csv_content = $this->read_csv_content_for_start_row( $file_path, $start_row );

		$parsed_config = $this->request_parser->parse_import_config( $csv_content );
		$config        = $this->build_import_config_from_parsed(
			$parsed_config,
			$file_path,
			$start_row,
			$csv_content,
			$import_session,
			$append_log
		);
		error_log(
			sprintf(
				'[Swift CSV][Import Request Debug] %s',
				wp_json_encode(
					[
						'start_row'           => $start_row,
						'import_session'      => $import_session,
						'post_type'           => (string) ( $config['post_type'] ?? '' ),
						'update_existing'     => (string) ( $config['update_existing'] ?? '' ),
						'dry_run'             => (bool) ( $config['dry_run'] ?? false ),
						'enable_logs'         => (bool) $enable_logs,
						'raw_post_type'       => isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : null,
						'raw_update_existing' => isset( $_POST['update_existing'] ) ? sanitize_text_field( wp_unslash( $_POST['update_existing'] ) ) : null,
						'raw_dry_run'         => isset( $_POST['dry_run'] ) ? sanitize_text_field( wp_unslash( $_POST['dry_run'] ) ) : null,
					]
				)
			)
		);
		if ( empty( $config ) ) {
			if ( ! Swift_CSV_Ajax_Util::has_sent_response() ) {
				Swift_CSV_Ajax_Util::send_error_response( 'Invalid import config' );
			}
			return;
		}

		Swift_CSV_Ajax_Util::set_stage( 'wp_compatible:ensure_csv_data' );
		$csv_data = $this->ensure_csv_data_available( $csv_data, $start_row, $file_path, $config, $import_session );
		if ( null === $csv_data ) {
			if ( ! Swift_CSV_Ajax_Util::has_sent_response() ) {
				Swift_CSV_Ajax_Util::send_error_response( 'CSV parsing failed' );
			}
			return;
		}

		$sample_filter_args = [
			'post_type' => $config['post_type'],
			'context'   => 'import_field_detection',
		];
		apply_filters( 'swift_csv_filter_sample_posts', [], $sample_filter_args );

		Swift_CSV_Ajax_Util::set_stage( 'wp_compatible:count_rows' );
		$total_rows             = $this->csv_util->count_total_rows( $csv_data['lines'] );
		$csv_data['total_rows'] = $total_rows;

		$batch_size           = $this->batch_processor->calculate_batch_size( $total_rows, $config );
		$config['batch_size'] = $batch_size;

		$cumulative_counts = $this->response_manager->get_cumulative_counts();

		$previous_created = $cumulative_counts['created'];
		$previous_updated = $cumulative_counts['updated'];
		$previous_errors  = $cumulative_counts['errors'];

		$counters = $this->batch_processor->process_batch( $config, $csv_data );

		if ( $this->is_import_cancelled( $import_session ) ) {
			$this->response_manager->cleanup_temp_file_if_complete( false, $config['file_path'] );
			$this->cleanup_import_session( $import_session );
			Swift_CSV_Ajax_Util::send_error_response( 'Import cancelled by user' );
			return;
		}

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
			$this->cleanup_import_session( $import_session );
		}
		$recent_logs = $this->build_recent_logs_if_complete( $should_continue, $import_session );

		Swift_CSV_Ajax_Util::set_stage( 'wp_compatible:send_response' );
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
