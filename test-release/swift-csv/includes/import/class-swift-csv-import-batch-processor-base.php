<?php
/**
 * Import Batch Processor Base Class for Swift CSV
 *
 * Defines the template method for batch processing.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for batch processors.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
abstract class Swift_CSV_Import_Batch_Processor_Base {
	/**
	 * Process batch import.
	 *
	 * Template method. Subclasses implement the actual processing via
	 * {@see Swift_CSV_Import_Batch_Processor_Base::do_process_batch()}.
	 *
	 * @since 0.9.8
	 * @param array $config Import configuration.
	 * @param array $csv_data Parsed CSV data.
	 * @return array Processing results.
	 */
	final public function process_batch( array $config, array $csv_data ): array {
		$counters = $this->initialize_counters();
		$this->do_process_batch( $config, $csv_data, $counters );
		return $counters;
	}

	/**
	 * Initialize counters for a batch.
	 *
	 * @since 0.9.8
	 * @return array<string, mixed>
	 */
	protected function initialize_counters(): array {
		return [
			'processed'       => 0,
			'created'         => 0,
			'updated'         => 0,
			'errors'          => 0,
			'dry_run_log'     => [],
			'dry_run_details' => [],
		];
	}

	/**
	 * Execute the actual batch processing.
	 *
	 * @since 0.9.8
	 * @param array $config Import configuration.
	 * @param array $csv_data Parsed CSV data.
	 * @param array $counters Counters (by reference).
	 * @return void
	 */
	abstract protected function do_process_batch( array $config, array $csv_data, array &$counters ): void;
}
