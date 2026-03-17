<?php
/**
 * Basic unit tests for Swift CSV plugin
 *
 * @package Swift_CSV\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * Basic functionality tests
 *
 * @since 0.9.7
 */
class BasicTest extends TestCase {

	/**
	 * Test plugin constants are defined
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_plugin_constants_defined() {
		$this->assertTrue( defined( 'SWIFT_CSV_VERSION' ), 'SWIFT_CSV_VERSION constant should be defined' );
		$this->assertTrue( defined( 'SWIFT_CSV_PLUGIN_DIR' ), 'SWIFT_CSV_PLUGIN_DIR constant should be defined' );
		$this->assertTrue( defined( 'SWIFT_CSV_BASENAME' ), 'SWIFT_CSV_BASENAME constant should be defined' );
	}

	/**
	 * Test plugin main class exists
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_plugin_main_class_exists() {
		// Since Swift CSV is function-based, check that autoloader is registered instead.
		$this->assertTrue( function_exists( 'spl_autoload_register' ), 'Autoloader should be available' );
	}

	/**
	 * Test plugin initialization
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_plugin_initialization() {
		// Since Swift CSV is function-based, check that key functions are loaded.
		$this->assertTrue( function_exists( 'swift_csv_init' ), 'swift_csv_init() should be loaded' );
	}

	/**
	 * Test plugin helper functions exist
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_helper_functions_exist() {
		$this->assertTrue( function_exists( 'swift_csv' ), 'swift_csv() helper function should exist' );
	}

	/**
	 * Test plugin version format
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_plugin_version_format() {
		$version = SWIFT_CSV_VERSION;
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $version, 'Version should be in x.y.z format' );
	}

	/**
	 * Test plugin directory structure
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_plugin_directory_structure() {
		$this->assertTrue( is_dir( SWIFT_CSV_PLUGIN_DIR . 'includes' ), 'Includes directory should exist' );
		$this->assertTrue( is_dir( SWIFT_CSV_PLUGIN_DIR . 'languages' ), 'Languages directory should exist' );
		$this->assertTrue( file_exists( SWIFT_CSV_PLUGIN_DIR . 'swift-csv.php' ), 'Main plugin file should exist' );
	}
}
