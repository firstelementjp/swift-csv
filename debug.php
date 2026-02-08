<?php
/**
 * Simple Debug configuration for Swift CSV plugin
 *
 * Lightweight debug logging for development.
 * This file is disabled by default to avoid performance issues.
 *
 * @since 0.9.5
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Simple debug log path
$log_file = plugin_dir_path( __FILE__ ) . 'debug.log';
ini_set( 'error_log', $log_file );

// Simple debug logging function
function swift_csv_debug_log( $message, $level = 'INFO' ) {
	// Only log when WP_DEBUG is enabled
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		return;
	}

	$timestamp = date( 'Y-m-d H:i:s' );
	$log_entry = "[{$timestamp}] [SWIFT-CSV] [{$level}] {$message}\n";
	error_log( $log_entry, 3, plugin_dir_path( __FILE__ ) . 'debug.log' );
}

// Log plugin initialization (only once)
if ( ! defined( 'SWIFT_CSV_DEBUG_INITIALIZED' ) ) {
	swift_csv_debug_log( 'Simple debug mode enabled' );
	define( 'SWIFT_CSV_DEBUG_INITIALIZED', true );
}
