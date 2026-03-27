<?php
/**
 * Enhanced WP Compatible Import with Bulk Processing
 *
 * Extends the existing WP Compatible Import with bulk processing capabilities.
 * Maintains full backward compatibility while enabling performance improvements.
 *
 * @since 0.9.9
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include required classes
require_once __DIR__ . '/class-swift-csv-import-bulk-processor.php';
require_once __DIR__ . '/class-swift-csv-import-bulk-integration.php';

/**
 * Enhanced WP Compatible Import Class
 *
 * Extends Swift_CSV_Import_WP_Compatible with bulk processing support.
 * Automatically switches between bulk and row-by-row processing based on configuration.
 *
 * @since 0.9.9
 * @package Swift_CSV
 */
class Swift_CSV_Import_WP_Compatible_Enhanced extends Swift_CSV_Import_WP_Compatible {

	/**
	 * Bulk integration instance.
	 *
	 * @since 0.9.9
	 * @var Swift_CSV_Import_Bulk_Integration|null
	 */
	private $bulk_integration = null;

	/**
	 * Whether to use bulk processing.
	 *
	 * @since 0.9.9
	 * @var bool
	 */
	private $use_bulk_processing = true;

	/**
	 * Constructor.
	 *
	 * @since 0.9.9
	 */
	public function __construct() {
		parent::__construct();
		global $wpdb;
		$this->bulk_integration = new Swift_CSV_Import_Bulk_Integration( $wpdb );
	}

	/**
	 * Enhanced import method with bulk processing support.
	 *
	 * @since 0.9.9
	 * @return void
	 */
	public function import(): void {
		Swift_CSV_Ajax_Util::set_stage( 'enhanced:start' );

		$file_result = $this->upload_file_or_return_null();
		if ( null === $file_result ) {
			if ( ! Swift_CSV_Ajax_Util::has_sent_response() ) {
				Swift_CSV_Ajax_Util::send_error_response( 'Upload failed' );
			}
			return;
		}
		$file_path = $file_result['file_path'];

		$import_session = $this->get_import_session_or_send_error();
		if ( '' === $import_session ) {
			if ( ! Swift_CSV_Ajax_Util::has_sent_response() ) {
				Swift_CSV_Ajax_Util::send_error_response( 'Missing import session' );
			}
			return;
		}

		$start_row   = 0;
		$enable_logs = true;
		$append_log  = $this->build_append_log_callback( $import_session, $enable_logs, $start_row );
		$csv_data    = $this->csv_store->get( $import_session );
		$csv_content = $this->read_csv_content_for_start_row( $file_path, $start_row );

		// Debug: Check CSV data
		error_log( '[Swift CSV] Debug - CSV data from session: ' . ( is_array( $csv_data ) ? 'found' : 'not found' ) );
		if ( is_array( $csv_data ) ) {
			error_log( '[Swift CSV] Debug - CSV headers count: ' . count( $csv_data['headers'] ?? [] ) );
			error_log( '[Swift CSV] Debug - CSV data rows count: ' . count( $csv_data['data'] ?? [] ) );
		}

		// If CSV data is not in session, parse it directly from file
		if ( ! is_array( $csv_data ) || empty( $csv_data ) ) {
			error_log( '[Swift CSV] Debug - Parsing CSV file directly' );
			$csv_data = $this->parse_csv_content_directly( $csv_content );
			if ( ! is_array( $csv_data ) ) {
				if ( ! Swift_CSV_Ajax_Util::has_sent_response() ) {
					Swift_CSV_Ajax_Util::send_error_response( 'Failed to parse CSV file' );
				}
				return;
			}
		}

		$parsed_config = $this->request_parser->parse_import_config( $csv_content );
		$config        = $this->build_import_config_from_parsed(
			$parsed_config,
			$file_path,
			$start_row,
			$csv_content,
			$import_session,
			$append_log
		);

		$enable_bulk = $this->should_use_bulk_processing( $config );

		// Configure bulk processing
		$this->bulk_integration->set_bulk_enabled( $enable_bulk );
		$this->bulk_integration->set_batch_size( $this->get_optimal_batch_size() );

		Swift_CSV_Ajax_Util::set_stage( 'enhanced:validate_headers' );
		$headers = $csv_data['headers'] ?? [];
		$data    = $csv_data['data'] ?? [];

		$validation = $this->validate_headers( $headers, $config );
		if ( ! $validation['valid'] ) {
			if ( ! Swift_CSV_Ajax_Util::has_sent_response() ) {
				Swift_CSV_Ajax_Util::send_error_response( $validation['error'] );
			}
			return;
		}

		// Add import session to config for bulk processing
		$config = array_merge(
			$config,
			[
				'import_session'      => $import_session,
				'headers'             => $headers,
				'allowed_post_fields' => $this->get_allowed_post_fields(),
			]
		);

		Swift_CSV_Ajax_Util::set_stage( 'enhanced:process_data' );
		$counters = [
			'processed'       => 0,
			'created'         => 0,
			'updated'         => 0,
			'errors'          => 0,
			'dry_run_log'     => [],
			'dry_run_details' => [],
		];

		// Process data using either bulk or row-by-row method
		$results = $this->bulk_integration->process_import_data( $data, $config, $counters );

		Swift_CSV_Ajax_Util::set_stage( 'enhanced:finalize' );

		$cumulative_counts = $this->response_manager->get_cumulative_counts();
		$previous_created  = $cumulative_counts['created'];
		$previous_updated  = $cumulative_counts['updated'];
		$previous_errors   = $cumulative_counts['errors'];
		$total_rows        = count( $data );
		$next_row          = $config['start_row'] + $counters['processed'];
		$should_continue   = $next_row < $total_rows;

		$this->response_manager->cleanup_temp_file_if_complete( $should_continue, $config['file_path'] );
		if ( ! $should_continue ) {
			$this->cleanup_import_session( $import_session );
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

	/**
	 * Determine whether to use bulk processing.
	 *
	 * @since 0.9.9
	 * @param array $config Import configuration.
	 * @return bool True if bulk processing should be used.
	 */
	private function should_use_bulk_processing( array $config ): bool {
		// TEMPORARY: Force enable for testing
		// TODO: Remove this in production
		if ( defined( 'SWIFT_CSV_FORCE_BULK_PROCESSING' ) && SWIFT_CSV_FORCE_BULK_PROCESSING ) {
			error_log( '[Swift CSV] Bulk processing forced enabled in should_use_bulk_processing' );
			return true;
		}

		// Don't use bulk processing for very small datasets
		$file_size = $config['file_size'] ?? 0;
		error_log( "[Swift CSV] Debug - File size: $file_size bytes" );
		if ( $file_size < 1024 ) { // Less than 1KB
			error_log( '[Swift CSV] Debug - File too small for bulk processing' );
			return false;
		}

		// Don't use bulk processing if explicitly disabled
		if ( isset( $config['disable_bulk'] ) && $config['disable_bulk'] ) {
			error_log( '[Swift CSV] Debug - Bulk processing explicitly disabled' );
			return false;
		}

		// Use bulk processing for dry runs and regular imports
		error_log( '[Swift CSV] Debug - Using bulk processing: ' . ( $this->use_bulk_processing ? 'yes' : 'no' ) );
		return $this->use_bulk_processing;
	}

	/**
	 * Get optimal batch size based on system capabilities.
	 *
	 * @since 0.9.9
	 * @return int Optimal batch size.
	 */
	private function get_optimal_batch_size(): int {
		// Base batch size
		$batch_size = 100;

		// Adjust based on available memory
		$memory_limit = ini_get( 'memory_limit' );
		if ( $memory_limit ) {
			$memory_mb = (int) $memory_limit;
			if ( $memory_mb >= 512 ) {
				$batch_size = 200;
			} elseif ( $memory_mb >= 256 ) {
				$batch_size = 150;
			} elseif ( $memory_mb < 128 ) {
				$batch_size = 50;
			}
		}

		// Allow filter to override
		return apply_filters( 'swift_csv_bulk_batch_size', $batch_size );
	}

	/**
	 * Calculate average time per row for performance metrics.
	 *
	 * @since 0.9.9
	 * @param array $results Processing results.
	 * @param array $counters Import counters.
	 * @return float Average time per row in seconds.
	 */
	private function calculate_avg_time_per_row( array $results, array $counters ): float {
		$total_processed = $counters['processed'];

		if ( 0 === $total_processed ) {
			return 0.0;
		}

		// This would be calculated from actual timing data
		// For now, return estimated time based on method
		$method = $results['method_used'] ?? 'row_by_row';

		if ( 'bulk' === $method ) {
			// Bulk processing should be faster
			return 0.15; // Estimated 150ms per row
		} else {
			// Row-by-row processing
			return 0.5; // Estimated 500ms per row
		}
	}

	/**
	 * Get allowed post fields for import.
	 *
	 * @since 0.9.9
	 * @return array Allowed post fields.
	 */
	private function get_allowed_post_fields(): array {
		return [
			'ID',
			'post_title',
			'post_content',
			'post_excerpt',
			'post_status',
			'post_type',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_modified',
			'post_modified_gmt',
			'comment_status',
			'ping_status',
			'post_name',
			'post_parent',
			'menu_order',
			'post_mime_type',
		];
	}

	/**
	 * Enable or disable bulk processing.
	 *
	 * @since 0.9.9
	 * @param bool $enabled Whether to enable bulk processing.
	 */
	public function set_bulk_processing_enabled( bool $enabled ): void {
		$this->use_bulk_processing = $enabled;
	}

	/**
	 * Check if bulk processing is enabled.
	 *
	 * @since 0.9.9
	 * @return bool True if bulk processing is enabled.
	 */
	public function is_bulk_processing_enabled(): bool {
		return $this->use_bulk_processing;
	}

	/**
	 * Get bulk integration instance.
	 *
	 * @since 0.9.9
	 * @return Swift_CSV_Import_Bulk_Integration Bulk integration instance.
	 */
	public function get_bulk_integration(): Swift_CSV_Import_Bulk_Integration {
		return $this->bulk_integration;
	}

	/**
	 * Parse CSV content directly when session data is not available.
	 *
	 * @since 0.9.9
	 * @param string $csv_content CSV content.
	 * @return array|false Parsed CSV data or false on failure.
	 */
	private function parse_csv_content_directly( string $csv_content ) {
		try {
			if ( empty( $csv_content ) ) {
				error_log( '[Swift CSV] Debug - CSV content is empty' );
				return false;
			}

			$csv_util = new Swift_CSV_Import_Csv();
			$lines    = $csv_util->parse_csv_lines_preserving_quoted_newlines( $csv_content );

			if ( empty( $lines ) ) {
				error_log( '[Swift CSV] Debug - No lines parsed from CSV content' );
				return false;
			}

			// Parse headers from first line
			$delimiter = ',';
			$headers   = $csv_util->parse_csv_row( $lines[0], $delimiter );

			// Parse data rows
			$data = [];
			for ( $i = 1; $i < count( $lines ); $i++ ) {
				if ( ! empty( trim( $lines[ $i ] ) ) ) {
					$row = $csv_util->parse_csv_row( $lines[ $i ], $delimiter );
					if ( ! empty( $row ) ) {
						$data[] = $row;
					}
				}
			}

			$result = [
				'headers' => $headers,
				'data'    => $data,
			];

			error_log( '[Swift CSV] Debug - Direct parse successful, headers: ' . count( $headers ) );
			return $result;
		} catch ( Exception $e ) {
			error_log( '[Swift CSV] Debug - Direct parse failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Validate CSV headers with enhanced checks.
	 *
	 * @since 0.9.9
	 * @param array $headers CSV headers.
	 * @param array $config Import configuration.
	 * @return array Validation result.
	 */
	private function validate_headers( array $headers, array $config ): array {
		if ( empty( $headers ) ) {
			return [
				'valid' => false,
				'error' => __( 'CSV file has no headers', 'swift-csv' ),
			];
		}

		// Check for required ID field if updating existing posts
		if ( ! empty( $config['update_existing'] ) ) {
			$id_found = false;
			foreach ( $headers as $header ) {
				if ( 'ID' === strtoupper( trim( $header ) ) ) {
					$id_found = true;
					break;
				}
			}

			if ( ! $id_found ) {
				return [
					'valid' => false,
					'error' => __( 'ID column is required when updating existing posts', 'swift-csv' ),
				];
			}
		}

		return [
			'valid' => true,
			'error' => null,
		];
	}
}
