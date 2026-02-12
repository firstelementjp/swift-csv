<?php
/**
 * Import orchestrator for Swift CSV.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrate the import handler flow.
 *
 * This class delegates the actual work to closures provided by the caller
 * (Swift_CSV_Ajax_Import), keeping behavior unchanged while reducing the size
 * of the handler class.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_Import_Orchestrator {
	/**
	 * Run one import loop iteration.
	 *
	 * @since 0.9.0
	 *
	 * @param wpdb                                                                                                                                                                     $wpdb WordPress database handler.
	 * @param array{file_path:string,start_row:int,batch_size:int,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool}                                         $config Import configuration.
	 * @param array{lines:array<int,string>,delimiter:string,headers:array<int,string>,taxonomy_format_validation:array,total_rows:int}                                                $csv_data Parsed CSV data.
	 * @param array<int, string>                                                                                                                                                       $allowed_post_fields Allowed post fields.
	 * @param int                                                                                                                                                                      $index Row index.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                                                                    $counters Counters (by reference).
	 * @param callable(string,int&): bool                                                                                                                                              $maybe_skip_empty_csv_line Decide whether to skip an empty CSV line.
	 * @param callable(wpdb,array,array,array<int,string>,string,string,array<int,string>,array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}): void $process_row_if_possible Process a row if possible.
	 *
	 * @return void
	 */
	public function run_process_import_loop_iteration(
		wpdb $wpdb,
		array $config,
		array $csv_data,
		array $allowed_post_fields,
		int $index,
		array &$counters,
		callable $maybe_skip_empty_csv_line,
		callable $process_row_if_possible
	): void {
		$line      = $csv_data['lines'][ $index ] ?? '';
		$delimiter = $csv_data['delimiter'] ?? ',';
		$headers   = $csv_data['headers'] ?? [];

		$processed = &$counters['processed'];
		if ( $maybe_skip_empty_csv_line( $line, $processed ) ) {
			return;
		}

		$process_row_if_possible( $wpdb, $config, $csv_data, $allowed_post_fields, $line, $delimiter, $headers, $counters );
	}

	/**
	 * Run the batch import loop.
	 *
	 * @since 0.9.0
	 *
	 * @param wpdb                                                                                                                                         $wpdb WordPress database handler.
	 * @param array{file_path:string,start_row:int,batch_size:int,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool}             $config Import configuration.
	 * @param array{lines:array<int,string>,delimiter:string,headers:array<int,string>,taxonomy_format_validation:array,total_rows:int}                    $csv_data Parsed CSV data.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                                        $counters Counters (by reference).
	 * @param callable(): array<int,string>                                                                                                                $get_allowed_post_fields Get allowed post fields.
	 * @param callable(array<int,string>,string): (int|null)                                                                                               $ensure_id_column_or_send_error_and_cleanup Ensure ID column exists.
	 * @param callable(wpdb,array,array,array<int,string>,int,array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}): void $process_import_loop_iteration Process one loop iteration.
	 *
	 * @return void
	 */
	public function run_process_batch_import(
		wpdb $wpdb,
		array $config,
		array $csv_data,
		array &$counters,
		callable $get_allowed_post_fields,
		callable $ensure_id_column_or_send_error_and_cleanup,
		callable $process_import_loop_iteration
	): void {
		$allowed_post_fields = $get_allowed_post_fields();
		$id_col              = $ensure_id_column_or_send_error_and_cleanup( $csv_data['headers'], $config['file_path'] );
		if ( null === $id_col ) {
			return;
		}

		for ( $i = $config['start_row']; $i < min( $config['start_row'] + $config['batch_size'], $csv_data['total_rows'] ); $i++ ) {
			$process_import_loop_iteration( $wpdb, $config, $csv_data, $allowed_post_fields, $i, $counters );
		}
	}

	/**
	 * Run the import handler flow.
	 *
	 * @since 0.9.0
	 *
	 * @param callable(): (array{nonce:string,file_path:string,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool,start_row:int,cumulative_created:int,cumulative_updated:int,cumulative_errors:int}|null)                                                                                                                                                                                     $prepare_import_environment Prepare environment and return config.
	 * @param callable(string,string): (array{headers:array<int,string>,lines:array<int,string>,taxonomy_format:string,total_rows?:int}|null)                                                                                                                                                                                                                                                                           $parse_and_validate_csv Parse CSV data.
	 * @param callable(array<int,string>): int                                                                                                                                                                                                                                                                                                                                                                          $count_total_rows Count total rows.
	 * @param callable(): array{created:int,updated:int,errors:int}                                                                                                                                                                                                                                                                                                                                                     $get_cumulative_counts Get previous cumulative counts.
	 * @param callable(array{nonce:string,file_path:string,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool,start_row:int,cumulative_created:int,cumulative_updated:int,cumulative_errors:int}, array{headers:array<int,string>,lines:array<int,string>,taxonomy_format:string,total_rows:int}, array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}): void $process_batch_import Process a batch.
	 * @param callable(bool,string): void                                                                                                                                                                                                                                                                                                                                                                               $cleanup_temp_file_if_complete Cleanup temp file.
	 * @param callable(int,int,int,int,int,int,int,int,int,bool,array<int,string>): void                                                                                                                                                                                                                                                                                                                                $send_import_progress_response Send JSON response.
	 *
	 * @return void
	 */
	public function run_import_handler(
		callable $prepare_import_environment,
		callable $parse_and_validate_csv,
		callable $count_total_rows,
		callable $get_cumulative_counts,
		callable $process_batch_import,
		callable $cleanup_temp_file_if_complete,
		callable $send_import_progress_response
	): void {
		$config = $prepare_import_environment();
		if ( null === $config ) {
			return;
		}

		$csv_data = $parse_and_validate_csv( $config['file_path'], $config['taxonomy_format'] );
		if ( null === $csv_data ) {
			return;
		}

		$total_rows             = $count_total_rows( $csv_data['lines'] );
		$csv_data['total_rows'] = $total_rows;

		// Initialize counters.
		$counters = [
			'processed'   => 0,
			'created'     => 0,
			'updated'     => 0,
			'errors'      => 0,
			'dry_run_log' => [],
		];

		$cumulative_counts = $get_cumulative_counts();
		$previous_created  = $cumulative_counts['created'];
		$previous_updated  = $cumulative_counts['updated'];
		$previous_errors   = $cumulative_counts['errors'];

		$process_batch_import( $config, $csv_data, $counters );

		$next_row = $config['start_row'] + $counters['processed'];
		$continue = $next_row < $total_rows;

		$cleanup_temp_file_if_complete( $continue, $config['file_path'] );

		$send_import_progress_response(
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
			$counters['dry_run_log']
		);
	}
}
