<?php
/**
 * Direct SQL AJAX Import Handler
 *
 * Contains the request-scoped import logic for the Direct SQL import method.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Direct SQL AJAX Import Handler Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Ajax_Import_Handler_Direct_SQL {

	/**
	 * Handle Direct SQL import.
	 *
	 * @since 0.9.8
	 * @return void
	 */
	public function handle(): void {
		$importer = new Swift_CSV_Import_Direct_SQL();
		$importer->import();
	}
}
