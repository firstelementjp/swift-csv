<?php
/**
 * Row processor for Swift CSV import.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Process per-row import logic.
 *
 * This class handles row-level orchestration while delegating persistence and
 * post-processing back to the caller via callables.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_Import_Row_Processor {
	/**
	 * Apply success side effects for a row (counters and GUID update) without callbacks.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                  $wpdb WordPress database handler.
	 * @param int                                                                                   $post_id Post ID.
	 * @param bool                                                                                  $is_update Whether this row updated an existing post.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>} $counters Counters (by reference).
	 * @return void
	 */
	public function apply_success_counters_and_guid_without_callbacks( wpdb $wpdb, int $post_id, bool $is_update, array &$counters ): void {
		$processed = &$counters['processed'];
		$created   = &$counters['created'];
		$updated   = &$counters['updated'];

		++$processed;
		if ( $is_update ) {
			++$updated;
		} else {
			++$created;
			Swift_CSV_Helper::update_guid_for_new_post( $wpdb, $post_id );
		}
	}

	/**
	 * Handle row result after persisting wp_posts data.
	 *
	 * @since 0.9.0
	 * @param int|false                                                                                                                                                                                                                                                                                               $result DB result.
	 * @param wpdb                                                                                                                                                                                                                                                                                                    $wpdb WordPress database handler.
	 * @param bool                                                                                                                                                                                                                                                                                                    $is_update Whether this row updates an existing post.
	 * @param int                                                                                                                                                                                                                                                                                                     $post_id Post ID.
	 * @param array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array}                                                                                                                     $context Context values for row processing.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                                                                                                                                                                                                   $counters Counters (by reference).
	 * @param callable(wpdb,int,bool,array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array},array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}): void $handle_successful_row_import Success handler.
	 * @return void
	 */
	public function handle_row_result_after_persist(
		$result,
		wpdb $wpdb,
		bool $is_update,
		int $post_id,
		array $context,
		array &$counters,
		callable $handle_successful_row_import
	): void {
		$errors = &$counters['errors'];

		if ( $result !== false ) {
			$handle_successful_row_import( $wpdb, $post_id, $is_update, $context, $counters );
			return;
		}

		++$errors;
	}

	/**
	 * Handle row result after persisting wp_posts data without callbacks.
	 *
	 * This method returns whether the persist step succeeded, and increments the
	 * error counter on failure.
	 *
	 * @since 0.9.0
	 * @param int|false                                                                             $result DB result.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>} $counters Counters (by reference).
	 * @return bool True if persist succeeded.
	 */
	public function handle_row_result_after_persist_without_callbacks( $result, array &$counters ): bool {
		$errors = &$counters['errors'];
		if ( $result !== false ) {
			return true;
		}

		++$errors;
		return false;
	}

	/**
	 * Process an import row context by running per-row import logic.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                                                                                                                                                                                                                                                             $wpdb WordPress database handler.
	 * @param array{data:array<int,string>,post_fields_from_csv:array<string,mixed>,post_id:int|null,is_update:bool}                                                                                                                                                                                                                           $row_context Row context.
	 * @param array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array}                                                                                                                                              $context Context values for row processing.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                                                                                                                                                                                                                            $counters Counters (by reference).
	 * @param callable(wpdb,bool,int|null,array<string,mixed>,array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array},array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}): void $process_single_import_row Process one row.
	 * @return void
	 */
	public function process_row_context( wpdb $wpdb, array $row_context, array $context, array &$counters, callable $process_single_import_row ): void {
		$data                 = $row_context['data'];
		$post_fields_from_csv = $row_context['post_fields_from_csv'];
		$post_id              = $row_context['post_id'];
		$is_update            = $row_context['is_update'];

		$single_row_context         = $context;
		$single_row_context['data'] = $data;

		$process_single_import_row(
			$wpdb,
			$is_update,
			$post_id,
			$post_fields_from_csv,
			$single_row_context,
			$counters
		);
	}

	/**
	 * Process an import row context using a persister instance.
	 *
	 * This is a simplified API that avoids injecting the persisting callable.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                                                                                                                                                                                                                                    $wpdb WordPress database handler.
	 * @param array{data:array<int,string>,post_fields_from_csv:array<string,mixed>,post_id:int|null,is_update:bool}                                                                                                                                                                                                  $row_context Row context.
	 * @param array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array}                                                                                                                     $context Context values for row processing.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                                                                                                                                                                                                   $counters Counters (by reference).
	 * @param Swift_CSV_Import_Persister                                                                                                                                                                                                                                                                              $persister Persister utility.
	 * @param callable(wpdb,int,bool,array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array},array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}): void $handle_successful_row_import Success handler.
	 * @return void
	 */
	public function process_row_context_with_persister( wpdb $wpdb, array $row_context, array $context, array &$counters, Swift_CSV_Import_Persister $persister, callable $handle_successful_row_import ): void {
		$this->process_row_context(
			$wpdb,
			$row_context,
			$context,
			$counters,
			function ( wpdb $wpdb, bool $is_update, &$post_id, array $post_fields_from_csv, array $single_row_context, array &$counters ) use ( $persister, $handle_successful_row_import ): void {
				$this->process_single_import_row_with_persister( $wpdb, $is_update, $post_id, $post_fields_from_csv, $single_row_context, $counters, $persister, $handle_successful_row_import );
			}
		);
	}

	/**
	 * Process one import row including DB persist and success/error handling.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                                                                                                                                                                                                                                              $wpdb WordPress database handler.
	 * @param bool                                                                                                                                                                                                                                                                                                              $is_update Whether this row updates an existing post.
	 * @param int|null                                                                                                                                                                                                                                                                                                          $post_id Post ID (by reference, updated on insert).
	 * @param array<string,mixed>                                                                                                                                                                                                                                                                                               $post_fields_from_csv Post fields collected from CSV.
	 * @param array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array}                                                                                                                               $context Context values for row processing.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                                                                                                                                                                                                             $counters Counters (by reference).
	 * @param callable(wpdb,bool,int|null,array<string,mixed>,string,bool,array<int,string>): (int|false)                                                                                                                                                                                                                       $persist_post_row_from_csv Persist wp_posts row.
	 * @param callable(int|false,wpdb,bool,int,array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array},array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}): void $handle_row_result_after_persist Handle persist result.
	 * @return void
	 */
	public function process_single_import_row(
		wpdb $wpdb,
		bool $is_update,
		&$post_id,
		array $post_fields_from_csv,
		array $context,
		array &$counters,
		callable $persist_post_row_from_csv,
		callable $handle_row_result_after_persist
	): void {
		$errors      = &$counters['errors'];
		$dry_run_log = &$counters['dry_run_log'];

		$post_type = $context['post_type'];
		$dry_run   = $context['dry_run'];

		try {
			$result = $persist_post_row_from_csv( $wpdb, $is_update, $post_id, $post_fields_from_csv, $post_type, $dry_run, $dry_run_log );
			$handle_row_result_after_persist( $result, $wpdb, $is_update, (int) $post_id, $context, $counters );
		} catch ( Exception $e ) {
			++$errors;
		}
	}

	/**
	 * Process one import row using a persister instance.
	 *
	 * This is a simplified API that avoids injecting the persisting callable.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                                                                                                                                                                                                                                    $wpdb WordPress database handler.
	 * @param bool                                                                                                                                                                                                                                                                                                    $is_update Whether this row updates an existing post.
	 * @param int|null                                                                                                                                                                                                                                                                                                $post_id Post ID (by reference, updated on insert).
	 * @param array<string,mixed>                                                                                                                                                                                                                                                                                     $post_fields_from_csv Post fields collected from CSV.
	 * @param array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array}                                                                                                                     $context Context values for row processing.
	 * @param array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}                                                                                                                                                                                                                   $counters Counters (by reference).
	 * @param Swift_CSV_Import_Persister                                                                                                                                                                                                                                                                              $persister Persister utility.
	 * @param callable(wpdb,int,bool,array{post_type:string,dry_run:bool,headers:array<int,string>,data:array<int,string>,allowed_post_fields:array<int,string>,taxonomy_format:string,taxonomy_format_validation:array},array{processed:int,created:int,updated:int,errors:int,dry_run_log:array<int,string>}): void $handle_successful_row_import Success handler.
	 * @return void
	 */
	public function process_single_import_row_with_persister( wpdb $wpdb, bool $is_update, &$post_id, array $post_fields_from_csv, array $context, array &$counters, Swift_CSV_Import_Persister $persister, callable $handle_successful_row_import ): void {
		$this->process_single_import_row(
			$wpdb,
			$is_update,
			$post_id,
			$post_fields_from_csv,
			$context,
			$counters,
			function ( wpdb $wpdb, bool $is_update, &$post_id, array $post_fields_from_csv, string $post_type, bool $dry_run, array &$dry_run_log ) use ( $persister ) {
				return $persister->persist_post_row_from_csv( $wpdb, $is_update, $post_id, $post_fields_from_csv, $post_type, $dry_run, $dry_run_log );
			},
			function ( $result, wpdb $wpdb, bool $is_update, int $post_id, array $context, array &$counters ) use ( $handle_successful_row_import ): void {
				if ( ! $this->handle_row_result_after_persist_without_callbacks( $result, $counters ) ) {
					return;
				}
				$handle_successful_row_import( $wpdb, $post_id, $is_update, $context, $counters );
			}
		);
	}
}
