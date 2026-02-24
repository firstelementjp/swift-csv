<?php
/**
 * Direct SQL AJAX Export Handler
 *
 * Contains the request-scoped export logic for the Direct SQL export method.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Direct SQL AJAX Export Handler Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Export_Handler_Direct_SQL {

	/**
	 * Export log store.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Export_Log_Store
	 */
	private $log_store;

	/**
	 * Export cancel manager.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Export_Cancel_Manager
	 */
	private $cancel_manager;

	/**
	 * Batch planner.
	 *
	 * @since 0.9.8
	 * @var Swift_CSV_Ajax_Export_Batch_Planner
	 */
	private $batch_planner;

	/**
	 * Constructor.
	 *
	 * @since 0.9.8
	 * @param Swift_CSV_Export_Log_Store      $log_store Export log store.
	 * @param Swift_CSV_Export_Cancel_Manager $cancel_manager Export cancel manager.
	 */
	public function __construct( Swift_CSV_Export_Log_Store $log_store, Swift_CSV_Export_Cancel_Manager $cancel_manager ) {
		$this->log_store      = $log_store;
		$this->cancel_manager = $cancel_manager;
		$this->batch_planner  = new Swift_CSV_Ajax_Export_Batch_Planner();
	}

	/**
	 * Handle Direct SQL export.
	 *
	 * @since 0.9.8
	 * @param array $config Export configuration.
	 * @return array Export result.
	 * @throws Exception When export fails.
	 */
	public function handle( array $config ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		try {
			$start_row      = intval( $_POST['start_row'] ?? 0 );
			$export_session = isset( $_POST['export_session'] ) ? sanitize_text_field( wp_unslash( $_POST['export_session'] ) ) : '';

			// Initialize session if first request.
			if ( '' === $export_session ) {
				$export_session = 'direct_sql_export_' . gmdate( 'Ymd_His' ) . '_' . wp_generate_uuid4();
			}

			// Initialize on first batch.
			if ( 0 === $start_row ) {
				$headers_key  = 'swift_csv_csv_headers_' . get_current_user_id() . '_' . $export_session;
				$headers_line = get_transient( $headers_key );
				if ( ! is_string( $headers_line ) || '' === $headers_line ) {
					$export       = new Swift_CSV_Export_Direct_SQL( $config );
					$headers      = $export->direct_sql_get_post_headers();
					$headers_line = implode( ',', array_map( [ $this, 'escape_csv_field' ], $headers ) );
					set_transient( $headers_key, $headers_line, HOUR_IN_SECONDS );
				}

				// Initialize log store if logging enabled.
				$enable_logs = isset( $_POST['enable_logs'] ) && in_array( (string) $_POST['enable_logs'], [ '1', 'true' ], true );
				if ( $enable_logs ) {
					$this->log_store->init( $export_session );
				}
			}

			// Check for cancellation.
			if ( $this->cancel_manager->is_cancelled( $export_session ) ) {
				wp_send_json_error( 'Export cancelled by user' );
				return [];
			}

			$transient_key = 'swift_csv_unified_export_config_' . get_current_user_id() . '_' . $export_session;
			$export_config = get_transient( $transient_key );

			if ( 0 === $start_row || ! is_array( $export_config ) ) {
				$total_posts = $this->batch_planner->get_total_posts_count( $config );
				$batch_size  = $this->batch_planner->get_export_batch_size( $total_posts, (string) ( $config['post_type'] ?? 'post' ), $config );

				$export_config = [
					'total_posts' => $total_posts,
					'batch_size'  => $batch_size,
				];

				set_transient( $transient_key, $export_config, HOUR_IN_SECONDS );
			} else {
				$total_posts = isset( $export_config['total_posts'] ) ? (int) $export_config['total_posts'] : 0;
				$batch_size  = isset( $export_config['batch_size'] ) ? (int) $export_config['batch_size'] : 0;
			}

			// Create Direct SQL Export instance.
			$export = isset( $export ) && $export instanceof Swift_CSV_Export_Direct_SQL ? $export : new Swift_CSV_Export_Direct_SQL( $config );

			// Get posts for current batch.
			$posts_data = $export->direct_sql_batch_fetch_posts( $start_row, $batch_size );

			if ( empty( $posts_data ) ) {
				return [
					'success'        => true,
					'export_session' => $export_session,
					'processed'      => $start_row,
					'total'          => $total_posts,
					'continue'       => false,
					'progress'       => 100,
					'status'         => 'completed',
					'csv_chunk'      => '',
				];
			}

			// Generate CSV for this batch.
			$csv_chunk = $export->direct_sql_generate_csv_batch( $posts_data );
			if ( 0 === $start_row ) {
				$headers_key  = 'swift_csv_csv_headers_' . get_current_user_id() . '_' . $export_session;
				$headers_line = get_transient( $headers_key );
				if ( is_string( $headers_line ) && '' !== $headers_line ) {
					$csv_chunk = $headers_line . "\n" . $csv_chunk;
				}
			}

			// Add log entry if logging enabled.
			if ( isset( $_POST['enable_logs'] ) && in_array( (string) $_POST['enable_logs'], [ '1', 'true' ], true ) ) {
				$batch_number = floor( $start_row / $batch_size ) + 1;
				$message      = sprintf(
				/* translators: 1: Batch number, 2: Number of posts processed */
					__( 'Batch %1$d: Bulk export %2$d posts', 'swift-csv' ),
					$batch_number,
					count( $posts_data )
				);
				$this->log_store->append(
					$export_session,
					[
						'row'    => $start_row,
						'status' => 'success',
						'title'  => $message,
						'time'   => current_time( 'mysql' ),
					]
				);
			}

			$next_row = $start_row + count( $posts_data );
			$continue = $next_row < $total_posts;
			$progress = $total_posts > 0 ? round( ( $next_row / $total_posts ) * 100, 2 ) : 100;
			$progress = min( 100, max( 0, $progress ) );

			if ( ! $continue ) {
				$headers_key = 'swift_csv_csv_headers_' . get_current_user_id() . '_' . $export_session;
				delete_transient( $headers_key );
			}

			return [
				'success'        => true,
				'export_session' => $export_session,
				'processed'      => $next_row,
				'total'          => $total_posts,
				'continue'       => $continue,
				'progress'       => $progress,
				'status'         => $continue ? 'processing' : 'completed',
				'csv_chunk'      => $csv_chunk,
			];
		} catch ( Exception $e ) {
			throw new Exception( 'Direct SQL export failed: ' . esc_html( $e->getMessage() ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Escape CSV field.
	 *
	 * @since 0.9.8
	 * @param string $field Field value.
	 * @return string Escaped field.
	 */
	private function escape_csv_field( $field ): string {
		$field = (string) $field;
		$field = str_replace( '"', '""', $field );
		return '"' . $field . '"';
	}
}
