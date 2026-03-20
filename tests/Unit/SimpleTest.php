<?php
/**
 * Simple standalone tests for Swift CSV plugin
 *
 * @package Swift_CSV\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * Simple functionality tests without WordPress dependency
 *
 * @since 0.9.7
 */
class SimpleTest extends TestCase {

	/**
	 * Test plugin main file exists and is readable
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_plugin_main_file_exists() {
		$plugin_file = dirname( dirname( __DIR__ ) ) . '/swift-csv.php';
		$this->assertTrue( file_exists( $plugin_file ), 'Main plugin file should exist' );
		$this->assertTrue( is_readable( $plugin_file ), 'Main plugin file should be readable' );
	}

	/**
	 * Test plugin header information
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_plugin_header_info() {
		$plugin_file    = dirname( dirname( __DIR__ ) ) . '/swift-csv.php';
		$plugin_content = file_get_contents( $plugin_file );

		// Check for plugin header comments
		$this->assertStringContainsString( 'Plugin Name:', $plugin_content, 'Plugin name header should exist' );
		$this->assertStringContainsString( 'Version:', $plugin_content, 'Version header should exist' );
		$this->assertStringContainsString( 'Swift CSV', $plugin_content, 'Plugin name should be Swift CSV' );
	}

	/**
	 * Test plugin directory structure
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_plugin_directory_structure() {
		$plugin_dir = dirname( dirname( __DIR__ ) );

		$this->assertTrue( is_dir( $plugin_dir . '/includes' ), 'Includes directory should exist' );
		$this->assertTrue( is_dir( $plugin_dir . '/languages' ), 'Languages directory should exist' );
		$this->assertTrue( is_dir( $plugin_dir . '/assets' ), 'Assets directory should exist' );
	}

	/**
	 * Test plugin file syntax
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_plugin_file_syntax() {
		$plugin_file = dirname( dirname( __DIR__ ) ) . '/swift-csv.php';
		$output      = [];
		$return_code = 0;

		// Check PHP syntax
		exec( "php -l \"{$plugin_file}\" 2>&1", $output, $return_code );

		$this->assertEquals( 0, $return_code, 'Plugin file should have valid PHP syntax' );
		$this->assertStringContainsString( 'No syntax errors', $output[0] ?? '' );
	}

	/**
	 * Test includes directory files syntax
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_includes_files_syntax() {
		$includes_dir = dirname( dirname( __DIR__ ) ) . '/includes';
		$php_files    = glob( $includes_dir . '/**/*.php' );

		foreach ( $php_files as $file ) {
			$output      = [];
			$return_code = 0;

			exec( "php -l \"{$file}\" 2>&1", $output, $return_code );

			$this->assertEquals( 0, $return_code, "File {$file} should have valid PHP syntax" );
		}
	}

	/**
	 * Test composer.json structure
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_composer_json_structure() {
		$composer_file = dirname( dirname( __DIR__ ) ) . '/composer.json';
		$this->assertTrue( file_exists( $composer_file ), 'composer.json should exist' );

		$composer_data = json_decode( file_get_contents( $composer_file ), true );
		$this->assertIsArray( $composer_data, 'composer.json should be valid JSON' );
		$this->assertArrayHasKey( 'name', $composer_data );
		$this->assertArrayHasKey( 'require', $composer_data );
		$this->assertArrayHasKey( 'require-dev', $composer_data );
	}

	/**
	 * Test phpunit.xml configuration
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_phpunit_xml_exists() {
		$phpunit_file = dirname( dirname( __DIR__ ) ) . '/phpunit.xml';
		$this->assertTrue( file_exists( $phpunit_file ), 'phpunit.xml should exist' );

		$xml = simplexml_load_file( $phpunit_file );
		$this->assertInstanceOf( 'SimpleXMLElement', $xml, 'phpunit.xml should be valid XML' );
	}
}
