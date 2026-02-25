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
class Swift_CSV_Ajax_Import_Handler_Direct_SQL extends Swift_CSV_Ajax_Import_Handler_Base {

	/**
	 * Handle import request.
	 *
	 * @since 0.9.10
	 * @return void
	 */
	public function handle(): void {
		Swift_CSV_Helper::send_error_response( 'High-Speed Import (Direct SQL) is unimplemented' );
	}
}
