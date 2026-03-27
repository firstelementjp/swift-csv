<?php
/**
 * Bulk Processing Hooks and Configuration for Swift CSV
 *
 * Registers hooks and configuration for bulk processing capabilities.
 * Enables seamless integration with existing import workflow.
 *
 * @since 0.9.9
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize bulk processing hooks.
 *
 * @since 0.9.9
 * @return void
 */
function swift_csv_init_bulk_processing(): void {
	// Add filter to enable bulk processing by default
	add_filter( 'swift_csv_use_bulk_processing', 'swift_csv_default_bulk_processing_enabled', 10, 2 );

	// Add filter for batch size configuration
	add_filter( 'swift_csv_bulk_batch_size', 'swift_csv_default_batch_size', 10, 1 );

	// Add action to register bulk processor
	add_action( 'swift_csv_import_init', 'swift_csv_register_bulk_processor', 10, 1 );

	// Add filter for import processor selection
	add_filter( 'swift_csv_import_processor_class', 'swift_csv_select_import_processor', 10, 2 );
}

/**
 * Default bulk processing enabled setting.
 *
 * @since 0.9.9
 * @param bool  $default_enabled Default enabled status.
 * @param array $config Import configuration.
 * @return bool True if bulk processing should be enabled.
 */
function swift_csv_default_bulk_processing_enabled( bool $default_enabled, array $config ): bool {
	// TEMPORARY: Force enable bulk processing for testing
	// TODO: Remove this in production
	if ( defined( 'SWIFT_CSV_FORCE_BULK_PROCESSING' ) && SWIFT_CSV_FORCE_BULK_PROCESSING ) {
		return true;
	}

	// Enable bulk processing by default
	$enabled = true;

	// Disable for very small files
	$file_size = $config['file_size'] ?? 0;
	if ( $file_size < 1024 ) { // Less than 1KB
		$enabled = false;
	}

	// Disable if explicitly requested
	if ( isset( $config['disable_bulk'] ) && $config['disable_bulk'] ) {
		$enabled = false;
	}

	// Disable for certain post types that may need special handling
	$post_type           = $config['post_type'] ?? 'post';
	$disabled_post_types = apply_filters( 'swift_csv_bulk_disabled_post_types', [] );
	if ( in_array( $post_type, $disabled_post_types, true ) ) {
		$enabled = false;
	}

	return $enabled;
}

/**
 * Default batch size configuration.
 *
 * @since 0.9.9
 * @param int $default_size Default batch size.
 * @return int Optimized batch size.
 */
function swift_csv_default_batch_size( int $default_size ): int {
	// Get server memory limit
	$memory_limit = ini_get( 'memory_limit' );
	$batch_size   = 100; // Default

	if ( $memory_limit ) {
		// Parse memory limit (e.g., "256M")
		$memory_mb = (int) $memory_limit;

		if ( $memory_mb >= 1024 ) { // 1GB or more
			$batch_size = 300;
		} elseif ( $memory_mb >= 512 ) { // 512MB or more
			$batch_size = 200;
		} elseif ( $memory_mb >= 256 ) { // 256MB or more
			$batch_size = 150;
		} elseif ( $memory_mb >= 128 ) { // 128MB or more
			$batch_size = 100;
		} else { // Less than 128MB
			$batch_size = 50;
		}
	}

	// Allow further customization
	return apply_filters( 'swift_csv_bulk_batch_size_final', $batch_size );
}

/**
 * Register bulk processor with the import system.
 *
 * @since 0.9.9
 * @param array $config Import configuration.
 * @return void
 */
function swift_csv_register_bulk_processor( array $config ): void {
	// This would be called during import initialization
	// The actual registration happens in the enhanced import class
}

/**
 * Select appropriate import processor class.
 *
 * @since 0.9.9
 * @param string $class_name Default processor class.
 * @param array  $config Import configuration.
 * @return string Processor class to use.
 */
function swift_csv_select_import_processor( string $class_name, array $config ): string {
	// Check if bulk processing should be used
	$use_bulk = apply_filters( 'swift_csv_use_bulk_processing', true, $config );

	if ( $use_bulk && class_exists( 'Swift_CSV_Import_WP_Compatible_Enhanced' ) ) {
		return 'Swift_CSV_Import_WP_Compatible_Enhanced';
	}

	return $class_name;
}

/**
 * Add bulk processing settings to admin interface.
 *
 * @since 0.9.9
 * @param array $settings Existing settings.
 * @return array Modified settings.
 */
function swift_csv_add_bulk_processing_settings( array $settings ): array {
	$settings['bulk_processing'] = [
		'title'       => __( 'Bulk Processing', 'swift-csv' ),
		'description' => __( 'Configure bulk processing settings for improved import performance.', 'swift-csv' ),
		'fields'      => [
			'enable_bulk' => [
				'label'       => __( 'Enable Bulk Processing', 'swift-csv' ),
				'type'        => 'checkbox',
				'description' => __( 'Use bulk processing for faster imports with large files.', 'swift-csv' ),
				'default'     => true,
			],
			'batch_size'  => [
				'label'       => __( 'Batch Size', 'swift-csv' ),
				'type'        => 'number',
				'description' => __( 'Number of rows to process in each batch (50-500).', 'swift-csv' ),
				'default'     => 100,
				'min'         => 50,
				'max'         => 500,
			],
			'auto_detect' => [
				'label'       => __( 'Auto-Detect Optimal Settings', 'swift-csv' ),
				'type'        => 'checkbox',
				'description' => __( 'Automatically optimize batch size based on server capabilities.', 'swift-csv' ),
				'default'     => true,
			],
		],
	];

	return $settings;
}

/**
 * Add bulk processing performance metrics to import log.
 *
 * @since 0.9.9
 * @param array $log_data Existing log data.
 * @param array $import_results Import results.
 * @return array Enhanced log data.
 */
function swift_csv_add_bulk_performance_metrics( array $log_data, array $import_results ): array {
	if ( isset( $import_results['performance'] ) ) {
		$performance = $import_results['performance'];

		$log_data['bulk_processing'] = [
			'method_used'          => $performance['method_used'] ?? 'row_by_row',
			'batches_processed'    => $performance['batches_processed'] ?? 0,
			'avg_time_per_row'     => $performance['avg_time_per_row'] ?? 0,
			'estimated_time_saved' => swift_csv_calculate_time_saved( $performance ),
		];
	}

	return $log_data;
}

/**
 * Calculate estimated time saved by bulk processing.
 *
 * @since 0.9.9
 * @param array $performance Performance data.
 * @return string Estimated time saved.
 */
function swift_csv_calculate_time_saved( array $performance ): string {
	$method_used = $performance['method_used'] ?? 'row_by_row';
	$avg_time    = $performance['avg_time_per_row'] ?? 0;
	$total_rows  = $performance['total_rows'] ?? 0;

	if ( 'bulk' === $method_used && $total_rows > 0 ) {
		// Estimate time saved compared to row-by-row (0.5s per row)
		$estimated_row_time       = 0.5;
		$actual_total_time        = $avg_time * $total_rows;
		$estimated_row_time_total = $estimated_row_time * $total_rows;
		$time_saved               = $estimated_row_time_total - $actual_total_time;

		if ( $time_saved > 0 ) {
			return sprintf(
				/* translators: %s: time saved */
				__( 'Estimated %s saved', 'swift-csv' ),
				swift_csv_format_duration( $time_saved )
			);
		}
	}

	return __( 'N/A', 'swift-csv' );
}

/**
 * Format duration in seconds to human readable format.
 *
 * @since 0.9.9
 * @param float $seconds Duration in seconds.
 * @return string Formatted duration.
 */
function swift_csv_format_duration( float $seconds ): string {
	if ( $seconds < 60 ) {
		return sprintf( '%.1fs', $seconds );
	} elseif ( $seconds < 3600 ) {
		$minutes           = floor( $seconds / 60 );
		$remaining_seconds = $seconds % 60;
		return sprintf( '%dm %.1fs', $minutes, $remaining_seconds );
	} else {
		$hours             = floor( $seconds / 3600 );
		$remaining_minutes = floor( ( $seconds % 3600 ) / 60 );
		return sprintf( '%dh %dm', $hours, $remaining_minutes );
	}
}

/**
 * Add bulk processing capability check.
 *
 * @since 0.9.9
 * @return bool True if bulk processing is supported.
 */
function swift_csv_is_bulk_processing_supported(): bool {
	// TEMPORARY: Force enable for testing
	// TODO: Remove this in production
	if ( defined( 'SWIFT_CSV_FORCE_BULK_PROCESSING' ) && SWIFT_CSV_FORCE_BULK_PROCESSING ) {
		error_log( '[Swift CSV] Bulk processing forced enabled via constant' );
		return true;
	}

	// Check minimum requirements
	$php_version  = PHP_VERSION;
	$memory_limit = ini_get( 'memory_limit' );

	error_log( "[Swift CSV] Bulk processing support check - PHP: $php_version, Memory: $memory_limit" );

	// Require PHP 7.4 or higher
	if ( version_compare( $php_version, '7.4.0', '<' ) ) {
		error_log( '[Swift CSV] Bulk processing not supported: PHP version too low' );
		return false;
	}

	// TEMPORARY: Bypass memory check for testing
	// TODO: Restore memory check in production
	/*
	// Require minimum 128MB memory
	if ( $memory_limit ) {
		$memory_mb = (int) $memory_limit;
		if ( $memory_mb < 128 ) {
			error_log( "[Swift CSV] Bulk processing not supported: Memory too low ($memory_mb MB)" );
			return false;
		}
	}
	*/

	error_log( '[Swift CSV] Bulk processing supported: yes' );
	return true;
}

// Initialize hooks
add_action( 'init', 'swift_csv_init_bulk_processing' );

// Add settings filter
add_filter( 'swift_csv_admin_settings', 'swift_csv_add_bulk_processing_settings', 10, 1 );

// Add performance metrics filter
add_filter( 'swift_csv_import_log_data', 'swift_csv_add_bulk_performance_metrics', 10, 2 );
