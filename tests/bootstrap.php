<?php
/**
 * PHPUnit bootstrap for Swift CSV
 *
 * This file prepares a WordPress-aware environment for plugin tests.
 *
 * @package Swift_CSV\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	$wp_dir = false;

	$possible_paths = [
		'../../../wp',
		'../../../../wp',
		dirname( dirname( dirname( __DIR__ ) ) ) . '/wp',
		dirname( dirname( dirname( dirname( __DIR__ ) ) ) ),
	];

	foreach ( $possible_paths as $candidate_path ) {
		if ( file_exists( $candidate_path . '/wp-config.php' ) ) {
			$wp_dir = $candidate_path;
			break;
		}
	}

	if ( $wp_dir ) {
		define( 'ABSPATH', $wp_dir . '/' );
	} else {
		die( 'WordPress installation not found. Please set up WP_TESTS_DIR environment variable.' );
	}
}

if ( false === getenv( 'WP_TESTS_DIR' ) ) {
	$wp_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
} else {
	$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
}

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	die( 'WordPress test files not found. Please run: composer install' );
}

if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', dirname( __DIR__ ) . '/wp-tests-config.php' );
}

require_once $wp_tests_dir . '/includes/functions.php';


/**
 * Load the Swift CSV plugin for the WordPress test bootstrap
 *
 * @return void
 */
function swift_csv_tests_load_plugin() {
	require_once dirname( __DIR__ ) . '/swift-csv.php';
	if ( function_exists( 'swift_csv_init' ) ) {
		swift_csv_init();
	}
}

tests_add_filter( 'muplugins_loaded', 'swift_csv_tests_load_plugin' );

require_once $wp_tests_dir . '/includes/bootstrap.php';

$swift_csv_results_dir = dirname( __DIR__ ) . '/tests/results';

if ( ! is_dir( $swift_csv_results_dir ) ) {
	wp_mkdir_p( $swift_csv_results_dir );
}

if ( ! function_exists( 'swift_csv' ) ) {
	/**
	 * Get a singleton-like Swift CSV instance for tests
	 *
	 * @return \Swift_CSV|null
	 */
	function swift_csv() {
		static $instance = null;
		if ( null === $instance && class_exists( 'Swift_CSV' ) ) {
			$instance = new \Swift_CSV();
		}

		return $instance;
	}
}

if ( ! function_exists( 'swift_csv_cleanup' ) ) {
	/**
	 * Remove transient test data created during runs
	 *
	 * @return void
	 */
	function swift_csv_cleanup() {
		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_title LIKE 'test_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'test_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'swift_csv_test_%'" );
	}
}
