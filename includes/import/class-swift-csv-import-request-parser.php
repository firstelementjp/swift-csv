<?php
/**
 * Import Request Parser
 *
 * Centralizes parsing and sanitizing of import-related request parameters.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import Request Parser Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Import_Request_Parser {

	/**
	 * Parse import session.
	 *
	 * @since 0.9.8
	 * @return string Import session.
	 */
	public function parse_import_session(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return sanitize_key( $_POST['import_session'] ?? '' );
	}

	/**
	 * Parse import start row.
	 *
	 * @since 0.9.8
	 * @return int Start row.
	 */
	public function parse_start_row(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return isset( $_POST['start_row'] ) ? intval( $_POST['start_row'] ) : 0;
	}

	/**
	 * Parse whether to enable logs.
	 *
	 * @since 0.9.8
	 * @return bool True when logs are enabled.
	 */
	public function parse_enable_logs(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return isset( $_POST['enable_logs'] ) && in_array( (string) $_POST['enable_logs'], [ '1', 'true' ], true );
	}

	/**
	 * Parse import method.
	 *
	 * @since 0.9.8
	 * @return string Import method (wp_compatible/direct_sql).
	 */
	public function parse_import_method(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$import_method = sanitize_key( $_POST['import_method'] ?? 'wp_compatible' );
		if ( '' === $import_method ) {
			$import_method = 'wp_compatible';
		}
		return $import_method;
	}

	/**
	 * Parse import configuration parameters.
	 *
	 * @since 0.9.8
	 * @return array{
	 *   batch_size:      int,
	 *   post_type:       string,
	 *   update_existing: string,
	 *   taxonomy_format: string,
	 *   dry_run:         bool
	 * }
	 */
	public function parse_import_config(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 10;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_type = sanitize_text_field( wp_unslash( $_POST['post_type'] ?? 'post' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$update_existing = sanitize_text_field( wp_unslash( $_POST['update_existing'] ?? 'no' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$taxonomy_format = sanitize_text_field( wp_unslash( $_POST['taxonomy_format'] ?? 'name' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$dry_run = isset( $_POST['dry_run'] ) && in_array( (string) $_POST['dry_run'], [ '1', 'true' ], true );

		return [
			'batch_size'      => $batch_size,
			'post_type'       => $post_type,
			'update_existing' => $update_existing,
			'taxonomy_format' => $taxonomy_format,
			'dry_run'         => $dry_run,
		];
	}

	/**
	 * Parse import log fetch parameters.
	 *
	 * @since 0.9.8
	 * @return array{
	 *   after_id: int,
	 *   limit:    int
	 * }
	 */
	public function parse_log_fetch_params(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$after_id = isset( $_POST['after_id'] ) ? intval( $_POST['after_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 100;
		$limit = max( 1, min( 200, $limit ) );

		return [
			'after_id' => $after_id,
			'limit'    => $limit,
		];
	}
}
