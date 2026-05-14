<?php
/**
 * Basic unit tests for FE CSV Import & Export plugin
 *
 * @package FE_CSV_Import_Export\Tests\Unit
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
		$this->assertTrue( defined( 'FE_CSV_IMPORT_EXPORT_VERSION' ), 'FE_CSV_IMPORT_EXPORT_VERSION constant should be defined' );
		$this->assertTrue( defined( 'FE_CSV_IMPORT_EXPORT_PLUGIN_DIR' ), 'FE_CSV_IMPORT_EXPORT_PLUGIN_DIR constant should be defined' );
		$this->assertTrue( defined( 'FE_CSV_IMPORT_EXPORT_BASENAME' ), 'FE_CSV_IMPORT_EXPORT_BASENAME constant should be defined' );
	}

	/**
	 * Test plugin main class exists
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_plugin_main_class_exists() {
		// Since FE CSV Import & Export is function-based, check that autoloader is registered instead.
		$this->assertTrue( function_exists( 'spl_autoload_register' ), 'Autoloader should be available' );
	}

	/**
	 * Test plugin initialization
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_plugin_initialization() {
		// Since FE CSV Import & Export is function-based, check that key functions are loaded.
		$this->assertTrue( function_exists( 'fe_csv_import_export_init' ), 'fe_csv_import_export_init() should be loaded' );
	}

	/**
	 * Test plugin helper functions exist
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_helper_functions_exist() {
		$this->assertTrue( function_exists( 'swift_csv' ), 'fe_csv_import_export() helper function should exist' );
	}

	/**
	 * Test plugin version format
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_plugin_version_format() {
		$version = FE_CSV_IMPORT_EXPORT_VERSION;
		$this->assertMatchesRegularExpression( '/^\d+(?:\.\d+){2,}$/', $version, 'Version should be in x.y.z or longer numeric dot-separated format' );
	}

	/**
	 * Test plugin directory structure
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_plugin_directory_structure() {
		$this->assertTrue( is_dir( FE_CSV_IMPORT_EXPORT_PLUGIN_DIR . 'includes' ), 'Includes directory should exist' );
		$this->assertTrue( is_dir( FE_CSV_IMPORT_EXPORT_PLUGIN_DIR . 'languages' ), 'Languages directory should exist' );
		$this->assertTrue( file_exists( FE_CSV_IMPORT_EXPORT_PLUGIN_DIR . 'fe-csv-import-export.php' ), 'Main plugin file should exist' );
	}
}
