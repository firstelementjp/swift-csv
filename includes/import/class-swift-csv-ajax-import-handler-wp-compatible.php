<?php
/**
 * WP Compatible AJAX Import Handler
 *
 * Contains the request-scoped import logic for the WP compatible import method.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Compatible AJAX Import Handler Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Import_Handler_WP_Compatible {

	/**
	 * Handle WP compatible import.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle(): void {
		$importer = new Swift_CSV_Import_WP_Compatible();
		$importer->import();
	}
}
