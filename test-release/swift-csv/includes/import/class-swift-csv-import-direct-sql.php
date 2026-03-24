<?php
/**
 * Direct SQL Import Class for Swift CSV
 *
 * Legacy placeholder for Direct SQL import functionality.
 * This feature has been moved to Swift CSV Pro.
 * The class exists for compatibility but shows error messages.
 *
 * @since 0.9.8
 * @package Swift_CSV
 * @deprecated This functionality is now available in Swift CSV Pro.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Direct SQL Import Class
 *
 * Legacy compatibility class for Direct SQL import.
 * All methods throw exceptions or show errors directing users to Swift CSV Pro.
 * This class maintains API compatibility while preventing usage.
 *
 * @since 0.9.8
 * @package Swift_CSV
 * @deprecated Use Swift CSV Pro for Direct SQL import functionality.
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
