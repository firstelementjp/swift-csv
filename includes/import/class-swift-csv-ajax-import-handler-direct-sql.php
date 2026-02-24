<?php
/**
 * Ajax Import Handler (Direct SQL)
 *
 * Placeholder for future high-speed import implementation.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax Import Handler (Direct SQL) Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Import_Handler_Direct_SQL {

	/**
	 * Handle import request.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle(): void {
		check_ajax_referer( 'swift_csv_ajax_nonce', 'nonce' );

		Swift_CSV_Helper::send_error_response( 'Direct SQL import is not implemented yet.' );
	}
}
