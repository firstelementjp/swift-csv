<?php
/**
 * Batch processing class for handling large CSV imports
 *
 * This file contains the batch processing functionality for the Swift CSV plugin,
 * including WP-Cron integration, database table management, and progress tracking.
 *
 * @since  0.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include exporter class
require_once SWIFT_CSV_PLUGIN_DIR . 'includes/class-swift-csv-exporter.php';

class Swift_CSV_Batch {

	/**
	 * Constructor
	 *
	 * Sets up WordPress hooks for batch processing.
	 *
	 * @since  0.9.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'create_batch_table' ] );
		add_action( 'wp_ajax_swift_csv_batch_progress', [ $this, 'ajax_batch_progress' ] );
		add_action( 'wp_ajax_swift_csv_start_batch', [ $this, 'ajax_start_batch' ] );
		add_action( 'wp_ajax_swift_csv_process_batch', [ $this, 'ajax_process_batch' ] );
		add_action( 'swift_csv_process_batch', [ $this, 'process_batch' ] );

		// Export batch processing
		add_action( 'wp_ajax_swift_csv_check_progress', [ $this, 'ajax_export_progress' ] );
		add_action( 'wp_ajax_swift_csv_start_export', [ $this, 'ajax_start_export' ] );
		add_action( 'swift_csv_process_export_batch', [ $this, 'process_export_batch' ] );
	}

	/**
	 * Create batch processing table
	 *
	 * Creates custom table for tracking batch import progress.
	 *
	 * @since  0.9.0
	 * @return void
	 */
	public function create_batch_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'swift_csv_batches';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			batch_id varchar(36) NOT NULL,
			post_type varchar(20) NOT NULL,
			total_rows int(11) NOT NULL,
			processed_rows int(11) NOT NULL DEFAULT 0,
			created_rows int(11) NOT NULL DEFAULT 0,
			updated_rows int(11) NOT NULL DEFAULT 0,
			error_rows int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			file_path varchar(255) NOT NULL,
			update_existing tinyint(1) NOT NULL DEFAULT 0,
			errors text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY batch_id (batch_id),
			KEY status (status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Check if column needs to be updated
		$column_info = $wpdb->get_row( "SHOW COLUMNS FROM $table_name LIKE 'batch_id'" );
		if ( $column_info && $column_info->Type === 'varchar(32)' ) {
			// Update existing column to varchar(36)
			$wpdb->query( "ALTER TABLE $table_name MODIFY COLUMN batch_id varchar(36) NOT NULL" );
		}

		// Check if update_existing column exists
		$update_existing_column = $wpdb->get_row( "SHOW COLUMNS FROM $table_name LIKE 'update_existing'" );
		if ( ! $update_existing_column ) {
			// Add update_existing column
			$wpdb->query( "ALTER TABLE $table_name ADD COLUMN update_existing tinyint(1) NOT NULL DEFAULT 0 AFTER file_path" );
		}
	}

	/**
	 * Start batch import
	 *
	 * Initiates batch processing for uploaded CSV file.
	 *
	 * @since  0.9.0
	 * @param  string $file_path       Path to CSV file.
	 * @param  string $post_type       Target post type.
	 * @param  bool   $update_existing Whether to update existing posts.
	 * @return string Batch ID for tracking.
	 */
	public function start_batch( $file_path, $post_type, $update_existing ) {
		global $wpdb;

		// Generate unique batch ID
		$batch_id = wp_generate_uuid4();

		// Read CSV and count rows
		$csv_data   = $this->read_csv_file( $file_path );
		$total_rows = count( $csv_data ) - 1; // Exclude header

		// Save CSV data to temporary file
		$temp_file = wp_upload_dir()['basedir'] . "/swift-csv/{$batch_id}.csv";
		wp_mkdir_p( dirname( $temp_file ) );
		copy( $file_path, $temp_file );

		// Insert batch record
		$table_name = $wpdb->prefix . 'swift_csv_batches';
		$wpdb->insert(
			$table_name,
			[
				'batch_id'        => $batch_id,
				'post_type'       => $post_type,
				'total_rows'      => $total_rows,
				'status'          => 'pending',
				'file_path'       => $temp_file,
				'update_existing' => $update_existing ? 1 : 0,
			],
			[ '%s', '%s', '%d', '%s', '%s', '%d' ]
		);

		// Schedule first batch processing - temporarily disabled to avoid cron locks
		// wp_schedule_single_event( time(), 'swift_csv_process_batch', [ $batch_id ] );
		error_log( 'Swift CSV: Cron scheduling disabled to avoid locks' );

		return $batch_id;
	}

	/**
	 * Process batch
	 *
	 * Processes a batch of CSV rows (100 rows at a time).
	 *
	 * @since  0.9.0
	 * @param  string $batch_id Batch ID to process.
	 * @return void
	 */
	public function process_batch( $batch_id ) {
		global $wpdb;

		// Debug log
		error_log( 'Processing batch: ' . $batch_id );

		$table_name = $wpdb->prefix . 'swift_csv_batches';
		$batch      = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE batch_id = %s",
				$batch_id
			)
		);

		if ( ! $batch || 'completed' === $batch->status ) {
			return;
		}

		// Update status to processing
		$wpdb->update(
			$table_name,
			[ 'status' => 'processing' ],
			[ 'batch_id' => $batch_id ],
			[ '%s' ],
			[ '%s' ]
		);

		// Process 50 rows at a time (optimized for speed)
		$batch_size = 50; // Reduced from 100 for better performance
		$start_row  = $batch->processed_rows + 1; // +1 to skip header
		$end_row    = min( $start_row + $batch_size - 1, $batch->total_rows );

		error_log( "Batch processing: rows $start_row to $end_row (processed: {$batch->processed_rows})" );

		// Read specific rows from CSV
		$csv_data = $this->read_csv_rows( $batch->file_path, $start_row, $end_row );
		$headers  = $this->read_csv_rows( $batch->file_path, 0, 0 )[0];

		error_log( 'CSV data count: ' . count( $csv_data ) );
		error_log( 'Headers count: ' . count( $headers ) );

		// Create field mapping
		$importer = new Swift_CSV_Importer();

		// Optimize WordPress for bulk processing
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );

		// Start database transaction for data integrity
		$wpdb->query( 'START TRANSACTION' );

		// Use reflection to access private methods
		$reflection = new ReflectionClass( $importer );

		// Get create_mapping method
		$create_mapping_method = $reflection->getMethod( 'create_mapping' );
		$create_mapping_method->setAccessible( true );
		$mapping = $create_mapping_method->invoke( $importer, $headers );

		// Get process_row method
		$process_row_method = $reflection->getMethod( 'process_row' );
		$process_row_method->setAccessible( true );

		// Process rows
		$created = 0;
		$updated = 0;
		$errors  = [];

		foreach ( $csv_data as $row_index => $row ) {
			try {
				$actual_row_index = $start_row + $row_index;
				$result           = $process_row_method->invoke(
					$importer,
					$row,
					$mapping,
					$batch->post_type,
					$batch->update_existing
				);

				if ( $result['updated'] ) {
					++$updated;
				} else {
					++$created;
				}
			} catch ( Exception $e ) {
				$errors[] = '行 ' . ( $actual_row_index + 1 ) . ': ' . $e->getMessage();
				error_log( 'Batch processing error: ' . $e->getMessage() );

				// Rollback transaction on error
				$wpdb->query( 'ROLLBACK' );
				wp_defer_term_counting( false );
				wp_defer_comment_counting( false );

				// Update batch with error status
				$wpdb->update(
					$table_name,
					[
						'status'     => 'error',
						'error_rows' => $batch->error_rows + 1,
					],
					[ 'batch_id' => $batch_id ],
					[ '%s', '%d' ],
					[ '%s' ]
				);

				return;
			}
		}

		// Commit transaction if successful
		$wpdb->query( 'COMMIT' );

		// Update batch progress
		$new_processed = $batch->processed_rows + count( $csv_data );
		$new_created   = $batch->created_rows + $created;
		$new_updated   = $batch->updated_rows + $updated;
		$new_errors    = $batch->error_rows + count( $errors );

		$status = ( $new_processed >= $batch->total_rows ) ? 'completed' : 'processing';

		$wpdb->update(
			$table_name,
			[
				'processed_rows' => $new_processed,
				'created_rows'   => $new_created,
				'updated_rows'   => $new_updated,
				'error_rows'     => $new_errors,
				'status'         => $status,
				'errors'         => serialize( $errors ),
			],
			[ 'batch_id' => $batch_id ],
			[ '%d', '%d', '%d', '%d', '%s', '%s' ],
			[ '%s' ]
		);

		// Schedule next batch if not completed - temporarily disabled to avoid cron locks
		if ( 'processing' === $status ) {
			// wp_schedule_single_event( time() + 5, 'swift_csv_process_batch', [ $batch_id ] );
			error_log( 'Swift CSV: Next batch cron scheduling disabled to avoid locks' );
		} else {
			// Restore WordPress optimization when completed
			wp_defer_term_counting( false );
			wp_defer_comment_counting( false );
		}
	}

	/**
	 * Get batch progress
	 *
	 * Returns current progress of batch processing.
	 *
	 * @since  0.9.0
	 * @param  string $batch_id Batch ID.
	 * @return array Progress data.
	 */
	public function get_batch_progress( $batch_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'swift_csv_batches';
		$batch      = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE batch_id = %s",
				$batch_id
			)
		);

		if ( ! $batch ) {
			return null;
		}

		$percentage = $batch->total_rows > 0
		? round( ( $batch->processed_rows / $batch->total_rows ) * 100, 2 )
		: 0;

		$errors = ! empty( $batch->errors ) ? unserialize( $batch->errors ) : [];

		return [
			'batch_id'       => $batch->batch_id,
			'status'         => $batch->status,
			'total_rows'     => $batch->total_rows,
			'processed_rows' => $batch->processed_rows,
			'created_rows'   => $batch->created_rows,
			'updated_rows'   => $batch->updated_rows,
			'error_rows'     => $batch->error_rows,
			'percentage'     => $percentage,
			'errors'         => $errors,
		];
	}

	/**
	 * AJAX handler for batch progress
	 *
	 * @since  0.9.0
	 * @return void
	 */
	public function ajax_batch_progress() {
		check_ajax_referer( 'swift_csv_batch_nonce', 'nonce' );

		$batch_id = sanitize_text_field( $_POST['batch_id'] );

		// Process one batch before getting progress
		$this->process_batch( $batch_id );

		$progress = $this->get_batch_progress( $batch_id );

		if ( ! $progress ) {
			wp_send_json_error( 'Batch not found' );
		}

		wp_send_json_success( $progress );
	}

	/**
	 * AJAX handler for processing batch directly
	 *
	 * @since  0.9.0
	 * @return void
	 */
	public function ajax_process_batch() {
		check_ajax_referer( 'swift_csv_batch_nonce', 'nonce' );

		$batch_id = sanitize_text_field( $_POST['batch_id'] );
		$this->process_batch( $batch_id );

		wp_send_json_success( [ 'processed' => true ] );
	}

	/**
	 * AJAX handler for starting batch
	 *
	 * @since  0.9.0
	 * @return void
	 */
	public function ajax_start_batch() {
		check_ajax_referer( 'swift_csv_batch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$file_path       = sanitize_text_field( $_POST['file_path'] );
		$post_type       = sanitize_text_field( $_POST['post_type'] );
		$update_existing = isset( $_POST['update_existing'] );

		$batch_id = $this->start_batch( $file_path, $post_type, $update_existing );

		wp_send_json_success( [ 'batch_id' => $batch_id ] );
	}

	/**
	 * Read CSV file with proper encoding
	 *
	 * @since  0.9.3
	 * @param  string $file_path Path to CSV file.
	 * @return array  Parsed CSV data.
	 */
	private function read_csv_file( $file_path ) {
		$content = file_get_contents( $file_path );

		// Remove BOM if present
		if ( substr( $content, 0, 3 ) === "\xEF\xBB\xBF" ) {
			$content = substr( $content, 3 );
		}

		// Convert to UTF-8 if needed
		$content = mb_convert_encoding( $content, 'UTF-8', 'UTF-8, SJIS, EUC-JP, JIS' );

		// Parse CSV
		$lines    = explode( "\n", $content );
		$csv_data = [];

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			$csv_data[] = str_getcsv( $line );
		}

		return $csv_data;
	}

	/**
	 * Read specific rows from CSV file
	 *
	 * @since  0.9.3
	 * @param  string $file_path Path to CSV file.
	 * @param  int    $start_row Starting row (0-indexed).
	 * @param  int    $end_row   Ending row (0-indexed).
	 * @return array  Parsed CSV data for specified rows.
	 */
	private function read_csv_rows( $file_path, $start_row, $end_row ) {
		$csv_data  = [];
		$row_count = 0;

		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return $csv_data;
		}

		// Skip to start row
		while ( $row_count < $start_row && ! feof( $handle ) ) {
			fgets( $handle );
			++$row_count;
		}

		// Read required rows
		while ( $row_count <= $end_row && ! feof( $handle ) ) {
			$line = fgets( $handle );
			if ( $line !== false ) {
				$csv_data[] = str_getcsv( $line );
			}
			++$row_count;
		}

		fclose( $handle );
		return $csv_data;
	}

	/**
	 * Start export batch
	 *
	 * Starts a new export batch for CSV export.
	 *
	 * @since  0.9.3
	 * @param  string $post_type Post type to export.
	 * @param  array  $args      Export arguments.
	 * @return string Batch ID for tracking.
	 */
	public function start_export_batch( $post_type, $args = [] ) {
		global $wpdb;

		// Ensure table exists
		$this->create_batch_table();

		// Generate unique batch ID
		$batch_id = wp_generate_uuid4();

		$posts_per_page = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 0;
		if ( $posts_per_page < 1 ) {
			$posts_per_page = 0;
		}

		// Count total posts without loading all posts into memory
		$post_counts = wp_count_posts( $post_type );
		$total_posts = 0;
		if ( $post_counts && isset( $post_counts->publish ) ) {
			$total_posts = (int) $post_counts->publish;
		}

		if ( $posts_per_page > 0 ) {
			$total_posts = min( $total_posts, $posts_per_page );
		}

		// Create temporary file with consistent naming
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/swift-csv/exports';
		wp_mkdir_p( $temp_dir );

		// Generate consistent filename: swiftcsv_export_{post_type}_{date}.csv (using local time)
		$filename  = 'swiftcsv_export_' . $post_type . '_' . date_i18n( 'Y-m-d_H-i-s' ) . '.csv';
		$file_path = $temp_dir . "/{$filename}";

		// Insert batch record
		$table_name = $wpdb->prefix . 'swift_csv_batches';
		$result     = $wpdb->insert(
			$table_name,
			[
				'batch_id'   => $batch_id,
				'post_type'  => $post_type,
				'total_rows' => $total_posts,
				'status'     => 'pending',
				'file_path'  => $file_path,
			],
			[ '%s', '%s', '%d', '%s', '%s' ]
		);

		// Schedule cron for batch processing
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Swift CSV] Scheduling export batch: ' . $batch_id );
		}
		wp_schedule_single_event( time(), 'swift_csv_process_export_batch', [ $batch_id ] );

		return $batch_id;
	}

	/**
	 * Process export batch
	 *
	 * Processes a batch of posts for CSV export.
	 *
	 * @since  0.9.3
	 * @param  string $batch_id Batch ID to process.
	 * @return void
	 */
	public function process_export_batch( $batch_id ) {
		global $wpdb;
		$started_at = microtime( true );
		$start_mem  = function_exists( 'memory_get_usage' ) ? memory_get_usage( true ) : 0;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Swift CSV] Processing export batch: ' . $batch_id );
		}

		// Disable object cache for this process to prevent memory issues
		wp_suspend_cache_addition( true );

		$table_name = $wpdb->prefix . 'swift_csv_batches';
		$batch      = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE batch_id = %s",
				$batch_id
			)
		);

		if ( ! $batch ) {
			return;
		}

		if ( 'completed' === $batch->status ) {
			return;
		}

		// Update status to processing
		$wpdb->update(
			$table_name,
			[ 'status' => 'processing' ],
			[ 'batch_id' => $batch_id ],
			[ '%s' ],
			[ '%s' ]
		);

		$batch_size = 10;
		$offset     = $batch->processed_rows;
		$remaining  = max( 0, (int) $batch->total_rows - (int) $offset );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Swift CSV] Batch details: processed_rows=' . $batch->processed_rows . ', total_rows=' . $batch->total_rows . ', offset=' . $offset );
		}

		if ( $remaining <= 0 ) {
			return;
		}
		$limit = min( $batch_size, $remaining );
		$posts = get_posts(
			[
				'post_type'              => $batch->post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		// Apply filter to query arguments (same as main exporter)
		$posts = apply_filters(
			'swift_csv_export_batch_query_args',
			$posts,
			[
				'post_type' => $batch->post_type,
				'limit'     => $limit,
				'offset'    => $offset,
				'batch_id'  => $batch_id,
			]
		);

		// Load full posts
		$full_posts = [];
		foreach ( $posts as $post_id ) {
			$full_posts[] = get_post( $post_id );
		}

		// Export posts to CSV
		$with_header = ( $offset === 0 || $offset === '0' );
		error_log( '[Swift CSV] Export batch: offset=' . $offset . ', with_header=' . ( $with_header ? 'true' : 'false' ) );
		error_log( '[Swift CSV] Offset type: ' . gettype( $offset ) . ', value: "' . $offset . '"' );
		$this->export_posts_to_csv( $full_posts, $batch->file_path, $with_header, $batch_id );

		// Update batch progress
		$new_processed = $batch->processed_rows + count( $full_posts );
		$status        = ( $new_processed >= $batch->total_rows ) ? 'completed' : 'processing';

		$wpdb->update(
			$table_name,
			[
				'processed_rows' => $new_processed,
				'status'         => $status,
			],
			[ 'batch_id' => $batch_id ],
			[ '%d', '%s' ],
			[ '%s' ]
		);

		$elapsed   = microtime( true ) - $started_at;
		$peak_mem  = function_exists( 'memory_get_peak_usage' ) ? memory_get_peak_usage( true ) : 0;
		$end_mem   = function_exists( 'memory_get_usage' ) ? memory_get_usage( true ) : 0;
		$mem_delta = $end_mem - $start_mem;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// Log batch progress (one line per chunk)
			error_log(
				sprintf(
					'[Swift CSV] Export batch %s: processed %d/%d posts (chunk %.3fs, mem %+d, peak %d)',
					$batch_id,
					(int) $new_processed,
					(int) $batch->total_rows,
					$elapsed,
					(int) $mem_delta,
					(int) $peak_mem
				)
			);
		}
	}

	/**
	 * AJAX handler for export progress (Cron-less / polling-driven)
	 *
	 * Advances the export by one chunk on each poll.
	 *
	 * @since  0.9.3
	 * @return void
	 */
	public function ajax_export_progress() {
		check_ajax_referer( 'swift_csv_nonce', 'nonce' );

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( $_POST['batch_id'] ) : '';
		if ( empty( $batch_id ) ) {
			wp_send_json_error( esc_html__( 'Invalid batch ID.', 'swift-csv' ) );
		}

		// Advance one chunk on each poll (Cron-less)
		$this->process_export_batch( $batch_id );

		global $wpdb;
		$table_name = $wpdb->prefix . 'swift_csv_batches';
		$batch      = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE batch_id = %s",
				$batch_id
			)
		);
		if ( ! $batch ) {
			wp_send_json_error( esc_html__( 'Batch not found.', 'swift-csv' ) );
		}

		$percentage = 0;
		if ( $batch->total_rows > 0 ) {
			$percentage = round( ( $batch->processed_rows / $batch->total_rows ) * 100, 2 );
		}

		$progress = [
			'success'        => true,
			'batch_id'       => $batch_id,
			'total_rows'     => (int) $batch->total_rows,
			'processed_rows' => (int) $batch->processed_rows,
			'percentage'     => $percentage,
			'status'         => $batch->status,
			'completed'      => ( 'completed' === $batch->status ),
		];

		if ( 'completed' === $batch->status ) {
			$upload_dir = wp_upload_dir();
			$file_url   = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $batch->file_path );
			if ( file_exists( $batch->file_path ) ) {
				$progress['download_url'] = $file_url;
			}
		}

		wp_send_json( $progress );
	}

	/**
	 * Export posts to CSV
	 *
	 * Exports posts to CSV file.
	 *
	 * @since  0.9.3
	 * @param  array  $posts     Posts to export.
	 * @param  string $file_path Path to CSV file.
	 * @param  bool   $with_header Whether to include headers.
	 * @param  string $batch_id  Batch ID (for logging).
	 * @return void
	 */
	private function export_posts_to_csv( $posts, $file_path, $with_header = false, $batch_id = '' ) {
		if ( empty( $posts ) ) {
			return;
		}

		// Increase memory limit for large exports
		$current_limit = ini_get( 'memory_limit' );
		if ( $current_limit !== '-1' ) {
			$memory_limit = max( wp_convert_hr_to_bytes( $current_limit ), 256 * 1024 * 1024 );
			@ini_set( 'memory_limit', '1G' ); // Increased to 1GB
		}

		$exporter  = new Swift_CSV_Exporter();
		$post_type = $posts[0]->post_type;

		$reflection       = new ReflectionClass( $exporter );
		$clean_method     = $reflection->getMethod( 'clean_csv_field' );
		$term_path_method = $reflection->getMethod( 'get_term_hierarchy_path' );
		$clean_method->setAccessible( true );
		$term_path_method->setAccessible( true );

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );

		$transient_key = 'swift_csv_export_custom_fields_' . md5( $file_path );
		error_log( '[Swift CSV Export] Transient key: ' . $transient_key );
		error_log( '[Swift CSV Export] File path: ' . $file_path );

		// Initialize headers always to avoid undefined variable errors
		$headers = [ 'ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name', 'post_date' ];
		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->public ) {
				$headers[] = 'tax_' . $taxonomy->name;
			}
		}

		error_log( '[Swift CSV Export] Starting export_posts_to_csv with_header=' . ( $with_header ? 'true' : 'false' ) );

		if ( $with_header ) {
			// Use the same ACF detection logic as the main exporter
			$custom_fields = [];
			$acf_fields    = [];
			$sample_posts  = array_slice( $posts, 0, 5 );

			// First, get all ACF field keys from wp_posts
			global $wpdb;
			$acf_field_keys = $wpdb->get_col(
				"SELECT post_name FROM {$wpdb->posts} 
				 WHERE post_type = 'acf-field'"
			);

			error_log( '[Swift CSV Export] Found ACF field keys: ' . print_r( $acf_field_keys, true ) );

			// Get field key to field name mapping
			$acf_field_mapping = [];
			foreach ( $acf_field_keys as $field_key ) {
				$field_config = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT post_content FROM {$wpdb->posts} 
					 WHERE post_name = %s 
					 AND post_type = 'acf-field'",
						$field_key
					)
				);
				if ( $field_config ) {
					$field_data = maybe_unserialize( $field_config );
					if ( is_array( $field_data ) && isset( $field_data['name'] ) ) {
						$acf_field_mapping[ $field_data['name'] ] = $field_key;
						error_log( '[Swift CSV Export] Found ACF field mapping: ' . $field_data['name'] . ' -> ' . $field_key );
					}
				}
			}

			error_log( '[Swift CSV Export] ACF field mapping: ' . print_r( $acf_field_mapping, true ) );

			foreach ( $sample_posts as $post ) {
				$fields = get_post_meta( $post->ID );
				error_log( '[Swift CSV Export] Processing post ID ' . $post->ID . ' with ' . count( $fields ) . ' meta fields' );
				foreach ( $fields as $key => $value ) {
					if ( ! str_starts_with( $key, '_' ) && ! in_array( $key, $custom_fields, true ) && ! in_array( $key, $acf_fields, true ) ) {
						// Check if this is an ACF field by looking up in our mapping
						if ( isset( $acf_field_mapping[ $key ] ) ) {
							// This is an ACF field
							$acf_fields[] = $key;
							error_log( '[Swift CSV Export] Detected ACF field: ' . $key );
						} else {
							// This is a regular custom field
							$custom_fields[] = $key;
							error_log( '[Swift CSV Export] Detected custom field: ' . $key );
						}
					}
				}
			}

			error_log( '[Swift CSV Export] Final custom fields: ' . print_r( $custom_fields, true ) );
			error_log( '[Swift CSV Export] Final ACF fields: ' . print_r( $acf_fields, true ) );

			// Store both custom fields and ACF fields
			$transient_data = [ 'custom' => $custom_fields, 'acf' => $acf_fields ];
			$set_result     = set_transient( $transient_key, $transient_data, DAY_IN_SECONDS );
			error_log( '[Swift CSV Export] Stored transient data. Result: ' . ( $set_result ? 'success' : 'failed' ) );
			error_log( '[Swift CSV Export] Transient data stored: ' . print_r( $transient_data, true ) );
		} else {
			error_log( '[Swift CSV Export] Loading field data from transient' );
			$field_data = get_transient( $transient_key );
			error_log( '[Swift CSV Export] Transient data: ' . print_r( $field_data, true ) );
			if ( is_array( $field_data ) ) {
				$custom_fields = $field_data['custom'] ?? [];
				$acf_fields    = $field_data['acf'] ?? [];
			} else {
				error_log( '[Swift CSV Export] No transient data found, using empty arrays' );
				$custom_fields = [];
				$acf_fields    = [];
			}
		}

		error_log( '[Swift CSV Export] Custom fields count: ' . count( $custom_fields ) );
		error_log( '[Swift CSV Export] ACF fields count: ' . count( $acf_fields ) );

		// Add custom field headers with 'cf_' prefix
		foreach ( $custom_fields as $field ) {
			$headers[] = 'cf_' . $field;
		}

		// Add ACF field headers with 'acf_' prefix
		foreach ( $acf_fields as $field ) {
			$headers[] = 'acf_' . $field;
		}

		error_log( '[Swift CSV Export] Generated headers: ' . print_r( $headers, true ) );

		// Apply filter to headers (same as main exporter)
		$headers = apply_filters( 'swift_csv_export_headers', $headers, [ 'post_type' => $post_type ] );

		// Extract custom field names from headers (including those added by hooks)
		$all_custom_fields = [];
		$all_acf_fields    = [];
		foreach ( $headers as $header ) {
			if ( str_starts_with( $header, 'cf_' ) ) {
				$all_custom_fields[] = substr( $header, 3 ); // Remove 'cf_' prefix
			} elseif ( str_starts_with( $header, 'acf_' ) ) {
				$all_acf_fields[] = substr( $header, 4 ); // Remove 'acf_' prefix
			}
		}

		$fh = fopen( $file_path, 'ab' );
		if ( false === $fh ) {
			return;
		}

		// Check if file exists and is empty, or if this is the first chunk
		$should_write_header = $with_header || ( ! file_exists( $file_path ) || filesize( $file_path ) === 0 );

		if ( $should_write_header ) {
			fputcsv( $fh, $headers );
		}

		$timing_taxonomy  = 0.0;
		$timing_meta      = 0.0;
		$timing_clean     = 0.0;
		$timing_write     = 0.0;
		$timing_total_sta = microtime( true );

		$post_ids = array_map(
			function ( $p ) {
				return $p->ID;
			},
			$posts
		);

		$terms_by_post = [];
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! $taxonomy->public ) {
				continue;
			}

			$tax_started      = microtime( true );
			$terms            = wp_get_object_terms(
				$post_ids,
				$taxonomy->name,
				[
					'orderby' => 'name',
					'order'   => 'ASC',
					'fields'  => 'all_with_object_id',
				]
			);
			$timing_taxonomy += ( microtime( true ) - $tax_started );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				if ( empty( $term->object_id ) ) {
					continue;
				}
				if ( ! isset( $terms_by_post[ $term->object_id ] ) ) {
					$terms_by_post[ $term->object_id ] = [];
				}
				if ( ! isset( $terms_by_post[ $term->object_id ][ $taxonomy->name ] ) ) {
					$terms_by_post[ $term->object_id ][ $taxonomy->name ] = [];
				}
				$terms_by_post[ $term->object_id ][ $taxonomy->name ][] = $term;
			}
		}

		foreach ( $posts as $post ) {
			$row           = [];
			$row[]         = $post->ID;
			$clean_started = microtime( true );
			$row[]         = $clean_method->invoke( $exporter, $post->post_title );
			$row[]         = $clean_method->invoke( $exporter, $post->post_content );
			$row[]         = $clean_method->invoke( $exporter, $post->post_excerpt );
			$timing_clean += ( microtime( true ) - $clean_started );
			$row[]         = $post->post_status;
			$row[]         = $post->post_name;
			$row[]         = $post->post_date;

			$meta_started = microtime( true );
			$all_meta     = get_post_meta( $post->ID );
			$timing_meta += ( microtime( true ) - $meta_started );

			foreach ( $taxonomies as $taxonomy ) {
				if ( ! $taxonomy->public ) {
					continue;
				}
				$terms = [];
				if ( isset( $terms_by_post[ $post->ID ][ $taxonomy->name ] ) ) {
					$terms = $terms_by_post[ $post->ID ][ $taxonomy->name ];
				}
				if ( empty( $terms ) ) {
					$row[] = '';
					continue;
				}
				$term_names = array_map(
					function ( $term ) use ( $exporter, $taxonomy, $term_path_method ) {
						return $term_path_method->invoke( $exporter, $term, $taxonomy->name );
					},
					$terms
				);
				$row[]      = implode( '|', $term_names );
			}

			// Process custom fields (cf_ prefix)
			foreach ( $all_custom_fields as $field ) {
				$values = [];
				if ( isset( $all_meta[ $field ] ) ) {
					$values = $all_meta[ $field ];
				}

				if ( ! is_array( $values ) ) {
					$values = [ $values ];
				}

				if ( is_array( $values ) && count( $values ) > 1 ) {
					$clean_started  = microtime( true );
					$cleaned_values = array_map(
						function ( $v ) use ( $exporter, $clean_method ) {
							return $clean_method->invoke( $exporter, $v );
						},
						$values
					);
					$timing_clean  += ( microtime( true ) - $clean_started );
					$row[]          = implode( '|', $cleaned_values );
				} elseif ( is_array( $values ) && count( $values ) === 1 ) {
					$clean_started = microtime( true );
					$row[]         = $clean_method->invoke( $exporter, $values[0] );
					$timing_clean += ( microtime( true ) - $clean_started );
				} else {
					$row[] = '';
				}
			}

			// Process ACF fields (acf_ prefix)
			foreach ( $all_acf_fields as $field ) {
				$values = [];
				if ( isset( $all_meta[ $field ] ) ) {
					$values = $all_meta[ $field ];
				}

				if ( ! is_array( $values ) ) {
					$values = [ $values ];
				}

				if ( is_array( $values ) && count( $values ) > 1 ) {
					$clean_started  = microtime( true );
					$cleaned_values = array_map(
						function ( $v ) use ( $exporter, $clean_method ) {
							return $clean_method->invoke( $exporter, $v );
						},
						$values
					);
					$timing_clean  += ( microtime( true ) - $clean_started );
					$row[]          = implode( '|', $cleaned_values );
				} elseif ( is_array( $values ) && count( $values ) === 1 ) {
					$clean_started = microtime( true );
					$row[]         = $clean_method->invoke( $exporter, $values[0] );
					$timing_clean += ( microtime( true ) - $clean_started );
				} else {
					$row[] = '';
				}
			}

			// Apply filter to row data before adding to CSV (same as main exporter)
			$row = apply_filters( 'swift_csv_export_row', $row, $post->ID, [ 'post_type' => $post_type ] );

			$write_started = microtime( true );
			fputcsv( $fh, $row );
			$timing_write += ( microtime( true ) - $write_started );
		}

		fclose( $fh );

		$timing_total = microtime( true ) - $timing_total_sta;
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'[Swift CSV] Export batch %s: csv chunk timing posts=%d total=%.3fs tax=%.3fs meta=%.3fs clean=%.3fs write=%.3fs',
					(string) $batch_id,
					count( $posts ),
					$timing_total,
					$timing_taxonomy,
					$timing_meta,
					$timing_clean,
					$timing_write
				)
			);
		}

		unset( $posts, $exporter, $taxonomies, $custom_fields, $headers );

		// Restore original memory limit
		if ( isset( $current_limit ) && $current_limit !== '-1' ) {
			@ini_set( 'memory_limit', $current_limit );
		}
	}

	/**
	 * AJAX handler for starting export
	 *
	 * @since  0.9.3
	 * @return void
	 */
	public function ajax_start_export() {
		// Debug log at the very beginning
		check_ajax_referer( 'swift_csv_nonce', 'nonce' );

		$post_type      = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'post';
		$posts_per_page = isset( $_POST['posts_per_page'] ) ? intval( $_POST['posts_per_page'] ) : 100;

		// Create batch
		$batch_id = $this->start_export_batch( $post_type, [ 'posts_per_page' => $posts_per_page ] );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// Debug log
			error_log( '[Swift CSV] Batch creation result: ' . ( $batch_id ? "SUCCESS - $batch_id" : 'FAILED' ) );
		}

		if ( $batch_id ) {
			// Start first batch processing directly (not via cron)
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "[Swift CSV] Starting batch processing for: $batch_id" );
			}
			$this->process_export_batch( $batch_id );

			wp_send_json(
				[
					'success'  => true,
					'batch_id' => $batch_id,
					'message'  => esc_html__( 'Export started', 'swift-csv' ),
				]
			);
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "[Swift CSV] Batch creation failed for post type: $post_type" );
			}
			wp_send_json_error( esc_html__( 'Failed to create export batch', 'swift-csv' ) );
		}
	}
}
