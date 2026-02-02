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
		add_action( 'swift_csv_process_batch', [ $this, 'process_batch' ] );

		// Export batch processing
		add_action( 'wp_ajax_swift_csv_export_progress', [ $this, 'ajax_export_progress' ] );
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
			batch_id varchar(32) NOT NULL,
			post_type varchar(20) NOT NULL,
			total_rows int(11) NOT NULL,
			processed_rows int(11) NOT NULL DEFAULT 0,
			created_rows int(11) NOT NULL DEFAULT 0,
			updated_rows int(11) NOT NULL DEFAULT 0,
			error_rows int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			file_path varchar(255) NOT NULL,
			errors text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY batch_id (batch_id),
			KEY status (status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
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

		// Schedule first batch processing
		wp_schedule_single_event( time(), 'swift_csv_process_batch', [ $batch_id ] );

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

		// Process 100 rows at a time
		$batch_size = 100;
		$start_row  = $batch->processed_rows + 1; // +1 to skip header
		$end_row    = min( $start_row + $batch_size - 1, $batch->total_rows );

		// Read specific rows from CSV
		$csv_data = $this->read_csv_rows( $batch->file_path, $start_row, $end_row );
		$headers  = $this->read_csv_rows( $batch->file_path, 0, 0 )[0];

		// Create field mapping
		$importer = new Swift_CSV_Importer();
		$mapping  = $importer->create_mapping( $headers );

		// Process rows
		$created = 0;
		$updated = 0;
		$errors  = [];

		foreach ( $csv_data as $row_index => $row ) {
			try {
				$actual_row_index = $start_row + $row_index;
				$result           = $importer->process_row(
					$row,
					$mapping,
					$batch->post_type,
					$batch->update_existing
				);

				if ( $result['created'] ) {
					++$created;
				} elseif ( $result['updated'] ) {
					++$updated;
				}
			} catch ( Exception $e ) {
				$errors[] = '行 ' . ( $actual_row_index + 1 ) . ': ' . $e->getMessage();
			}
		}

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

		// Schedule next batch if not completed
		if ( 'processing' === $status ) {
			wp_schedule_single_event( time() + 5, 'swift_csv_process_batch', [ $batch_id ] );
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
		$progress = $this->get_batch_progress( $batch_id );

		if ( ! $progress ) {
			wp_send_json_error( 'Batch not found' );
		}

		wp_send_json_success( $progress );
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
	 * Read CSV file (copied from importer)
	 *
	 * @since  0.9.0
	 * @param  string $file_path Path to CSV file.
	 * @return array Parsed CSV data.
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
	 * Read specific rows from CSV
	 *
	 * @since  0.9.0
	 * @param  string $file_path Path to CSV file.
	 * @param  int    $start     Start row index.
	 * @param  int    $end       End row index.
	 * @return array CSV rows.
	 */
	private function read_csv_rows( $file_path, $start, $end ) {
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

		for ( $i = $start; $i <= $end && $i < count( $lines ); $i++ ) {
			$line = trim( $lines[ $i ] );
			if ( ! empty( $line ) ) {
				$csv_data[] = str_getcsv( $line );
			}
		}

		return $csv_data;
	}

	/**
	 * Start batch export
	 *
	 * Initiates batch processing for CSV export.
	 *
	 * @since  0.9.3
	 * @param  string $post_type Target post type.
	 * @param  array  $args      Export arguments.
	 * @return string Batch ID for tracking.
	 */
	public function start_export_batch( $post_type, $args = [] ) {
		global $wpdb;

		// Generate unique batch ID
		$batch_id = wp_generate_uuid4();

		// Count total posts
		$total_posts = count(
			get_posts(
				[
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
				]
			)
		);

		// Create temporary file
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/swift-csv/exports';
		wp_mkdir_p( $temp_dir );
		$file_path = $temp_dir . "/{$batch_id}.csv";

		// Insert batch record
		$table_name = $wpdb->prefix . 'swift_csv_batches';
		$wpdb->insert(
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

		// Schedule first batch processing
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

		// Process 100 posts at a time
		$batch_size = 100;
		$offset     = $batch->processed_rows;
		$posts      = get_posts(
			[
				'post_type'      => $batch->post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $batch_size,
				'offset'         => $offset,
			]
		);

		// Export posts to CSV
		$this->export_posts_to_csv( $posts, $batch->file_path, $offset === 0 );

		// Update batch progress
		$new_processed = $batch->processed_rows + count( $posts );
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

		// Schedule next batch if not completed
		if ( 'processing' === $status ) {
			wp_schedule_single_event( time() + 2, 'swift_csv_process_export_batch', [ $batch_id ] );
		}
	}

	/**
	 * Export posts to CSV
	 *
	 * Exports posts to CSV file.
	 *
	 * @since  0.9.3
	 * @param  array  $posts     Posts to export.
	 * @param  string $file_path File path.
	 * @param  bool   $with_header Whether to include header.
	 * @return void
	 */
	private function export_posts_to_csv( $posts, $file_path, $with_header = true ) {
		if ( empty( $posts ) ) {
			return;
		}

		$exporter    = new Swift_CSV_Exporter();
		$csv_content = '';

		// Generate headers for first batch
		if ( $with_header ) {
			$headers      = $exporter->generate_headers( $posts[0]->post_type );
			$csv_content .= implode( ',', $headers ) . "\n";
		}

		// Generate CSV content
		foreach ( $posts as $post ) {
			$row          = $exporter->generate_row( $post );
			$csv_content .= implode( ',', $row ) . "\n";
		}

		// Append to file
		file_put_contents( $file_path, $csv_content, FILE_APPEND | LOCK_EX );
	}

	/**
	 * AJAX handler for export progress
	 *
	 * @since  0.9.3
	 * @return void
	 */
	public function ajax_export_progress() {
		check_ajax_referer( 'swift_csv_nonce', 'nonce' );

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( $_POST['batch_id'] ) : '';
		$progress = $this->get_batch_progress( $batch_id );

		wp_send_json( $progress );
	}

	/**
	 * AJAX handler for starting export
	 *
	 * @since  0.9.3
	 * @return void
	 */
	public function ajax_start_export() {
		check_ajax_referer( 'swift_csv_nonce', 'nonce' );

		$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'post';
		$args      = [];

		$batch_id = $this->start_export_batch( $post_type, $args );

		wp_send_json(
			[
				'success'  => true,
				'batch_id' => $batch_id,
				'message'  => 'Export started',
			]
		);
	}
}
