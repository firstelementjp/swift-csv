<?php
/**
 * Post persistence for Swift CSV import.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persist wp_posts rows for CSV import.
 *
 * This class intentionally mirrors the existing behavior of the import handler
 * and is designed to be called from Swift_CSV_Ajax_Import via delegation.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_Import_Persister {
	/**
	 * Persist one CSV row into wp_posts (insert/update) and return a structured result.
	 *
	 * This method makes the return value semantics explicit, avoiding ambiguity
	 * between insert/update operations.
	 *
	 * @since 0.9.0
	 * @param wpdb               $wpdb WordPress database handler.
	 * @param bool               $is_update Whether this row updates an existing post.
	 * @param int|null           $post_id Target post ID (by reference, updated on insert).
	 * @param array              $post_fields_from_csv Post fields collected from CSV.
	 * @param string             $post_type Post type.
	 * @param bool               $dry_run Whether this is a dry run.
	 * @param array<int, string> $dry_run_log Dry run log (by reference).
	 * @return array{success:bool,operation:string,post_id:int|null,db_result:int|false}
	 */
	public function persist_post_row_from_csv_with_result( wpdb $wpdb, bool $is_update, &$post_id, array $post_fields_from_csv, string $post_type, bool $dry_run, array &$dry_run_log ): array {
		$post_data = $this->build_post_data_for_import( $is_update, $post_fields_from_csv, $post_type );
		$result    = $this->execute_post_db_operation( $wpdb, $is_update, $post_id, $post_data, $dry_run, $dry_run_log );

		return [
			'success'   => ( $result !== false ),
			'operation' => $is_update ? 'update' : 'insert',
			'post_id'   => $post_id === null ? null : (int) $post_id,
			'db_result' => $result,
		];
	}

	/**
	 * Build post data array for insert/update during import.
	 *
	 * @since 0.9.0
	 * @param bool   $is_update Whether this row updates an existing post.
	 * @param array  $post_fields_from_csv Post fields collected from CSV.
	 * @param string $post_type Post type.
	 * @return array Post data array for wp_posts insert/update.
	 */
	public function build_post_data_for_import( bool $is_update, array $post_fields_from_csv, string $post_type ): array {
		if ( $is_update ) {
			return $this->build_post_data_for_update( $post_fields_from_csv );
		}

		return $this->build_post_data_for_insert( $post_fields_from_csv, $post_type );
	}

	/**
	 * Persist one CSV row into wp_posts (insert/update).
	 *
	 * @since 0.9.0
	 * @param wpdb               $wpdb WordPress database handler.
	 * @param bool               $is_update Whether this row updates an existing post.
	 * @param int|null           $post_id Target post ID (by reference, updated on insert).
	 * @param array              $post_fields_from_csv Post fields collected from CSV.
	 * @param string             $post_type Post type.
	 * @param bool               $dry_run Whether this is a dry run.
	 * @param array<int, string> $dry_run_log Dry run log (by reference).
	 * @return int|false Result of DB operation (post ID on insert, rows affected on update, or false on failure).
	 */
	public function persist_post_row_from_csv( wpdb $wpdb, bool $is_update, &$post_id, array $post_fields_from_csv, string $post_type, bool $dry_run, array &$dry_run_log ) {
		$result = $this->persist_post_row_from_csv_with_result( $wpdb, $is_update, $post_id, $post_fields_from_csv, $post_type, $dry_run, $dry_run_log );
		return $result['db_result'];
	}

	/**
	 * Execute post insert/update operation during import.
	 *
	 * @since 0.9.0
	 * @param wpdb               $wpdb WordPress database handler.
	 * @param bool               $is_update Whether this row updates an existing post.
	 * @param int                $post_id Target post ID.
	 * @param array              $post_data Post data array for wp_posts insert/update.
	 * @param bool               $dry_run Whether this is a dry run.
	 * @param array<int, string> $dry_run_log Dry run log (by reference).
	 * @return int|false Result of DB operation (post ID on insert, rows affected on update, or false on failure).
	 */
	public function execute_post_db_operation( wpdb $wpdb, bool $is_update, &$post_id, array $post_data, bool $dry_run, array &$dry_run_log ) {
		if ( $is_update ) {
			return $this->execute_post_update( $wpdb, $post_id, $post_data, $dry_run, $dry_run_log );
		}

		return $this->execute_post_insert( $wpdb, $post_data, $dry_run, $dry_run_log, $post_id );
	}

	/**
	 * Build post data for update.
	 *
	 * @since 0.9.0
	 * @param array<string, string> $post_fields_from_csv Post fields.
	 * @return array<string, mixed>
	 */
	public function build_post_data_for_update( $post_fields_from_csv ) {
		return Swift_CSV_Helper::build_post_data_for_update( $post_fields_from_csv );
	}

	/**
	 * Build post data for insert.
	 *
	 * @since 0.9.0
	 * @param array<string, string> $post_fields_from_csv Post fields.
	 * @param string                $post_type Post type.
	 * @return array<string, mixed>
	 */
	public function build_post_data_for_insert( $post_fields_from_csv, $post_type ) {
		return Swift_CSV_Helper::build_post_data_for_insert( $post_fields_from_csv, $post_type );
	}

	/**
	 * Execute post update.
	 *
	 * @since 0.9.0
	 * @param wpdb                 $wpdb WordPress DB instance.
	 * @param int|null             $post_id Post ID.
	 * @param array<string, mixed> $post_data Post data.
	 * @param bool                 $dry_run Dry run flag.
	 * @param array<int, string>   $dry_run_log Dry run log.
	 * @return int|false
	 */
	public function execute_post_update( $wpdb, $post_id, $post_data, $dry_run, &$dry_run_log ) {
		if ( empty( $post_data ) ) {
			return 0;
		}

		$post_data_formats = [];
		foreach ( array_keys( $post_data ) as $key ) {
			$post_data_formats[] = in_array( $key, [ 'post_author', 'post_parent', 'menu_order' ], true ) ? '%d' : '%s';
		}

		if ( $dry_run ) {
			$dry_run_log[] = sprintf(
				/* translators: 1: post ID, 2: post title */
				__( 'Update post: ID=%1$s, title=%2$s', 'swift-csv' ),
				$post_id,
				$post_data['post_title'] ?? 'Untitled'
			);
			return 1;
		}

		return $wpdb->update(
			$wpdb->posts,
			$post_data,
			[ 'ID' => $post_id ],
			$post_data_formats,
			[ '%d' ]
		);
	}

	/**
	 * Execute post insert.
	 *
	 * @since 0.9.0
	 * @param wpdb                 $wpdb WordPress DB instance.
	 * @param array<string, mixed> $post_data Post data.
	 * @param bool                 $dry_run Dry run flag.
	 * @param array<int, string>   $dry_run_log Dry run log.
	 * @param int|null             $post_id Post ID.
	 * @return int|false
	 */
	public function execute_post_insert( $wpdb, $post_data, $dry_run, &$dry_run_log, &$post_id ) {
		$post_data_formats = [];
		foreach ( array_keys( $post_data ) as $key ) {
			$post_data_formats[] = in_array( $key, [ 'post_author', 'post_parent', 'menu_order' ], true ) ? '%d' : '%s';
		}

		if ( $dry_run ) {
			$dry_run_log[] = sprintf(
				/* translators: 1: post title */
				__( 'New post: title=%1$s', 'swift-csv' ),
				$post_data['post_title'] ?? 'Untitled'
			);
			$post_id = 0;
			return 1;
		}

		$result = $wpdb->insert( $wpdb->posts, $post_data, $post_data_formats );
		if ( $result !== false ) {
			$post_id = $wpdb->insert_id;
		}
		return $result;
	}
}
