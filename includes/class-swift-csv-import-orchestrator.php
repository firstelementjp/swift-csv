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
	 * Build the per-row processing context.
	 *
	 * @since 0.9.0
	 * @param array{file_path:string,start_row:int,batch_size:int,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool} $config Import configuration.
	 * @param array{lines:array<int,string>,delimiter:string,headers:array<int,string>,taxonomy_format_validation:array,total_rows:int}        $csv_data Parsed CSV data.
	 * @param array<int, string>                                                                                                               $headers CSV headers.
	 * @param array<int, string>                                                                                                               $allowed_post_fields Allowed post fields.
	 * @return array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array}
	 */
	public function run_build_row_processing_context( array $config, array $csv_data, array $headers, array $allowed_post_fields ): array {
		return [
			'post_type'                  => (string) ( $config['post_type'] ?? 'post' ),
			'dry_run'                    => (bool) ( $config['dry_run'] ?? false ),
			'headers'                    => $headers,
			'data'                       => [],
			'allowed_post_fields'        => $allowed_post_fields,
			'taxonomy_format'            => (string) ( $config['taxonomy_format'] ?? 'name' ),
			'taxonomy_format_validation' => $csv_data['taxonomy_format_validation'] ?? [],
		];
	}

	/**
	 * Process one CSV row if it can be converted to a valid row context.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                                                                                                                                                                                                                                                                                                                             $wpdb WordPress database handler.
	 * @param array{file_path:string,start_row:int,batch_size:int,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool}                                                                                                                                                                                                                                                                 $config Import configuration.
	 * @param array{lines:array<int,string>,delimiter:string,headers:array<int,string>,taxonomy_format_validation:array,total_rows:int}                                                                                                                                                                                                                                                                        $csv_data Parsed CSV data.
	 * @param array<int, string>                                                                                                                                                                                                                                                                                                                                                                               $allowed_post_fields Allowed post fields.
	 * @param string                                                                                                                                                                                                                                                                                                                                                                                           $line Raw CSV line.
	 * @param string                                                                                                                                                                                                                                                                                                                                                                                           $delimiter CSV delimiter.
	 * @param array<int, string>                                                                                                                                                                                                                                                                                                                                                                               $headers CSV headers.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                                                                                                                                                                                                                                                                                            $counters Counters (by reference).
	 * @param callable(wpdb,array,string,string,array<int,string>,array<int,string>): (?array{data:array<int,string>,post_fields_from_csv:array<string,mixed>,post_id:int,is_update:bool})                                                                                                                                                                                                                     $build_import_row_context_from_config Build row context.
	 * @param callable(wpdb,array{data:array<int,string>,post_fields_from_csv:array<string,mixed>,post_id:int,is_update:bool},array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array},array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}): void $process_row_context Process row context.
	 *
	 * @return void
	 */
	public function run_process_row_if_possible(
		wpdb $wpdb,
		array $config,
		array $csv_data,
		array $allowed_post_fields,
		string $line,
		string $delimiter,
		array $headers,
		array &$counters,
		callable $build_import_row_context_from_config,
		callable $process_row_context
	): void {
		$row_context = $build_import_row_context_from_config( $wpdb, $config, $line, $delimiter, $headers, $allowed_post_fields );
		if ( null === $row_context ) {
			return;
		}

		$process_row_context(
			$wpdb,
			$row_context,
			$this->run_build_row_processing_context( $config, $csv_data, $headers, $allowed_post_fields ),
			$counters
		);
	}

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
}
