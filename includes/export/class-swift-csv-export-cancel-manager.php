<?php
/**
 * Export Cancel Manager
 *
 * Manages export cancellation flags for Swift CSV.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export Cancel Manager Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Export_Cancel_Manager {

	/**
	 * Get export cancel option name.
	 *
	 * @since 0.9.8
	 * @param string $export_session Export session identifier.
	 * @return string Option name.
	 */
	public function get_cancel_option_name( string $export_session ): string {
		return 'swift_csv_export_cancelled_' . get_current_user_id() . '_' . $export_session;
	}

	/**
	 * Mark an export session as cancelled.
	 *
	 * @since 0.9.8
	 * @param string $export_session Export session identifier.
	 * @return void
	 */
	public function cancel( string $export_session ): void {
		$cancel_option_name = $this->get_cancel_option_name( $export_session );
		update_option( $cancel_option_name, time(), false );
	}

	/**
	 * Check if an export session is cancelled.
	 *
	 * @since 0.9.8
	 * @param string $export_session Export session identifier.
	 * @return bool True if cancelled.
	 */
	public function is_cancelled( string $export_session ): bool {
		if ( '' === $export_session ) {
			return false;
		}

		$cancel_option_name = $this->get_cancel_option_name( $export_session );

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
}
