<?php
/**
 * Import Cancel Manager
 *
 * Manages import cancellation flags for Swift CSV.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import Cancel Manager Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Import_Cancel_Manager {

	/**
	 * Get import cancel option name.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session identifier.
	 * @return string Option name.
	 */
	public function get_cancel_option_name( string $import_session ): string {
		return 'swift_csv_import_cancelled_' . get_current_user_id() . '_' . $import_session;
	}

	/**
	 * Mark an import session as cancelled.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session identifier.
	 * @return void
	 */
	public function cancel( string $import_session ): void {
		$cancel_option_name = $this->get_cancel_option_name( $import_session );
		update_option( $cancel_option_name, time(), false );
	}

	/**
	 * Check if an import session is cancelled.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session identifier.
	 * @return bool True if cancelled.
	 */
	public function is_cancelled( string $import_session ): bool {
		if ( '' === $import_session ) {
			return false;
		}

		$cancel_option_name = $this->get_cancel_option_name( $import_session );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$option_value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$cancel_option_name
			)
		);

		return ! empty( $option_value );
	}

	/**
	 * Clear an import cancellation flag.
	 *
	 * @since 0.9.8
	 * @param string $import_session Import session identifier.
	 * @return void
	 */
	public function cleanup( string $import_session ): void {
		if ( '' === $import_session ) {
			return;
		}

		delete_option( $this->get_cancel_option_name( $import_session ) );
	}
}
