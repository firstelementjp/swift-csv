<?php
/**
 * PHPUnit Bootstrap for Swift CSV
 *
 * This file sets up the WordPress testing environment for PHPUnit tests.
 */

// Check if we're running in a proper test environment.
if (!defined('ABSPATH')) {
    // Try to locate WordPress installation.
    $wp_dir = false;
    
    // Common WordPress installation paths.
    $possible_paths = [
        '../../../wp',                    // Local Sites structure
        '../../../../wp',                 // Deeper structure
        dirname(dirname(dirname(__DIR__))) . '/wp', // Plugin relative
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path . '/wp-config.php')) {
            $wp_dir = $path;
            break;
        }
    }
    
    if ($wp_dir) {
        define('ABSPATH', $wp_dir . '/');
    } else {
        die('WordPress installation not found. Please set up WP_TESTS_DIR environment variable.');
    }
}

// Load WordPress test environment.
if (false === getenv('WP_TESTS_DIR')) {
    // Fallback to vendor directory.
    $wp_tests_dir = dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit';
} else {
    $wp_tests_dir = getenv('WP_TESTS_DIR');
}

if (!file_exists($wp_tests_dir . '/includes/functions.php')) {
    die('WordPress test files not found. Please run: composer install');
}

// Load test environment.
require_once $wp_tests_dir . '/includes/functions.php';
require_once $wp_tests_dir . '/includes/load.php';
require_once $wp_tests_dir . '/includes/bootstrap.php';

// Load our plugin.
require_once dirname(__DIR__) . '/swift-csv.php';

// Activate our plugin (if not already active).
if (!function_exists('swift_csv_activate')) {
    // Manually call activation functions.
    require_once dirname(__DIR__) . '/includes/class-swift-csv.php';
    $swift_csv = new Swift_CSV();
    $swift_csv->activate();
}

// Global test helper functions.
function swift_csv() {
    static $instance = null;
    if (null === $instance) {
        $instance = new Swift_CSV();
    }
    return $instance;
}

// Clean up after each test.
function swift_csv_cleanup() {
    // Remove any test data created during tests.
    global $wpdb;
    
    // Clean up test posts.
    $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_title LIKE 'test_%'");
    
    // Clean up test post meta.
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'test_%'");
    
    // Clean up test options.
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'swift_csv_test_%'");
}

echo "Swift CSV test environment loaded successfully!\n";
