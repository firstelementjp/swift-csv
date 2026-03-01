<?php
/**
 * Standalone tests for Swift CSV plugin (no WordPress dependency)
 *
 * @package Swift_CSV\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * Standalone functionality tests
 *
 * @since 0.9.7
 */
class StandaloneTest extends TestCase {

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
		
		// Check PHP syntax using built-in function
		$this->assertTrue( $this->isValidPhpSyntax( $plugin_file ), 'Plugin file should have valid PHP syntax' );
	}

	/**
	 * Test includes directory files syntax
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_includes_files_syntax() {
		$includes_dir = dirname( dirname( __DIR__ ) ) . '/includes';
		$php_files = glob( $includes_dir . '/**/*.php' );
		
		foreach ( $php_files as $file ) {
			$this->assertTrue( $this->isValidPhpSyntax( $file ), "File {$file} should have valid PHP syntax" );
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

	/**
	 * Test plugin header information
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_plugin_header_info() {
		$plugin_file = dirname( dirname( __DIR__ ) ) . '/swift-csv.php';
		$plugin_content = file_get_contents( $plugin_file );
		
		// Check for plugin header comments
		$this->assertStringContainsString( 'Plugin Name:', $plugin_content, 'Plugin name header should exist' );
		$this->assertStringContainsString( 'Version:', $plugin_content, 'Version header should exist' );
		$this->assertStringContainsString( 'Swift CSV', $plugin_content, 'Plugin name should be Swift CSV' );
	}

	/**
	 * Test required files exist
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_required_files_exist() {
		$plugin_dir = dirname( dirname( __DIR__ ) );
		
		$required_files = [
			'swift-csv.php',
			'composer.json',
			'README.md',
			'LICENSE',
			'uninstall.php'
		];
		
		foreach ( $required_files as $file ) {
			$this->assertTrue( file_exists( $plugin_dir . '/' . $file ), "Required file {$file} should exist" );
		}
	}

	/**
	 * Test README structure
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_readme_structure() {
		$readme_file = dirname( dirname( __DIR__ ) ) . '/README.md';
		$this->assertTrue( file_exists( $readme_file ), 'README.md should exist' );
		
		$readme_content = file_get_contents( $readme_file );
		$this->assertStringContainsString( '# Swift CSV', $readme_content, 'README should have a title' );
	}

	/**
	 * Helper method to check PHP syntax
	 *
	 * @since 0.9.7
	 * @param string $file File path to check.
	 * @return bool True if syntax is valid.
	 */
	private function isValidPhpSyntax( $file ) {
		// Use php -l for syntax checking
		$output = [];
		$return_code = 0;
		
		// Suppress output and just check return code
		exec( "php -l " . escapeshellarg( $file ) . " 2>/dev/null", $output, $return_code );
		
		return 0 === $return_code;
	}
}
