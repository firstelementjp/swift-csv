<?php
/**
 * Direct SQL Import Class for Swift CSV
 *
 * Placeholder for future high-speed import implementation.
 *
 * @since 0.9.12
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Direct SQL Import Class
 *
 * @since 0.9.12
 * @package Swift_CSV
 */
class Swift_CSV_Import_Direct_SQL extends Swift_CSV_Import_Base {

	/**
	 * Run the import.
	 *
	 * @since 0.9.12
	 * @return void
	 */
	public function import(): void {
		Swift_CSV_Helper::send_error_response(
			esc_html__( 'High-Speed Import (Direct SQL) is unimplemented.', 'swift-csv' )
		);
	}
}
