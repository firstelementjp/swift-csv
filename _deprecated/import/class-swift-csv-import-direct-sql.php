<?php
/**
 * Direct SQL Import Class for FE CSV Import & Export
 *
 * Legacy placeholder for Direct SQL import functionality.
 * This feature has been moved to FE CSV Import & Export Pro.
 * The class exists for compatibility but shows error messages.
 *
 * @since 0.9.8
 * @package FE_CSV_Import_Export
 * @deprecated This functionality is now available in FE CSV Import & Export Pro.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Direct SQL Import Class
 *
 * Legacy compatibility class for Direct SQL import.
 * All methods throw exceptions or show errors directing users to FE CSV Import & Export Pro.
 * This class maintains API compatibility while preventing usage.
 *
 * @since 0.9.8
 * @package FE_CSV_Import_Export
 * @deprecated Use FE CSV Import & Export Pro for Direct SQL import functionality.
 */
class Swift_CSV_Import_Direct_SQL extends Swift_CSV_Import_Base {

	/**
	 * Run the import.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function import(): void {
		Swift_CSV_Ajax_Util::send_error_response(
			esc_html__( 'High-Speed Import (Direct SQL) is unimplemented.', 'swift-csv' )
		);
	}
}
