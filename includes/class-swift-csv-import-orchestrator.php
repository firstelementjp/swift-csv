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
	 * Run the import handler flow.
	 *
	 * @since 0.9.0
	 *
	 * @param callable(): (array{nonce:string,file_path:string,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool,start_row:int,cumulative_created:int,cumulative_updated:int,cumulative_errors:int}|null) $prepare_import_environment Prepare environment and return config.
	 * @param callable(string,string): (array{headers:array<int,string>,lines:array<int,string>,taxonomy_format:string,total_rows?:int}|null)                                                     $parse_and_validate_csv Parse CSV data.
	 * @param callable(array<int,string>): int                                                                                                                                            $count_total_rows Count total rows.
	 * @param callable(): array{created:int,updated:int,errors:int}                                                                                                                       $get_cumulative_counts Get previous cumulative counts.
	 * @param callable(array{nonce:string,file_path:string,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool,start_row:int,cumulative_created:int,cumulative_updated:int,cumulative_errors:int}, array{headers:array<int,string>,lines:array<int,string>,taxonomy_format:string,total_rows:int}, array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}): void $process_batch_import Process a batch.
	 * @param callable(bool,string): void                                                                                                                                                  $cleanup_temp_file_if_complete Cleanup temp file.
	 * @param callable(int,int,int,int,int,int,int,int,int,bool,array<int,string>): void                                                                                                   $send_import_progress_response Send JSON response.
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
