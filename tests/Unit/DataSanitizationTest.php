<?php
/**
 * Data sanitization functionality tests
 *
 * @package Swift_CSV\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * Data sanitization tests
 *
 * @since 0.9.7
 */
class DataSanitizationTest extends TestCase {

	/**
	 * Test basic text sanitization
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_basic_text_sanitization() {
		$input  = 'Simple text';
		$result = sanitize_text_field( $input );

		$this->assertEquals( 'Simple text', $result );
	}

	/**
	 * Test HTML tag removal
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_html_tag_removal() {
		$input  = '<script>alert("xss")</script>Safe content';
		$result = sanitize_text_field( $input );

		$this->assertEquals( 'alert("xss")Safe content', $result );
		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( '</script>', $result );
	}

	/**
	 * Test special character handling
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_special_character_handling() {
		$input  = 'Special chars: & < > " \'';
		$result = sanitize_text_field( $input );

		// WordPress sanitize_text_field converts special characters to entities
		$this->assertStringContainsString( '&amp;', $result );
		$this->assertStringContainsString( '&lt;', $result );
		$this->assertStringContainsString( '&gt;', $result );
	}

	/**
	 * Test numeric value sanitization
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_numeric_value_sanitization() {
		$int_input   = '123';
		$float_input = '123.45';

		$int_result   = intval( $int_input );
		$float_result = floatval( $float_input );

		$this->assertEquals( 123, $int_result );
		$this->assertEquals( 123.45, $float_result );
	}

	/**
	 * Test email sanitization
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_email_sanitization() {
		$input  = 'user@example.com';
		$result = sanitize_email( $input );

		$this->assertEquals( 'user@example.com', $result );

		// Invalid email address
		$invalid_input  = 'not-an-email';
		$invalid_result = sanitize_email( $invalid_input );
		$this->assertEquals( 'not-an-email', $invalid_result );
	}

	/**
	 * Test URL sanitization
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_url_sanitization() {
		$input  = 'https://example.com/path?param=value';
		$result = esc_url_raw( $input );

		$this->assertEquals( 'https://example.com/path?param=value', $result );

		// Malicious URL
		$malicious_input  = 'javascript:alert(1)';
		$malicious_result = esc_url_raw( $malicious_input );
		$this->assertEmpty( $malicious_result );
	}

	/**
	 * Test slug generation
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_slug_generation() {
		$input  = 'Hello World! This is a Test';
		$result = sanitize_title( $input );

		$this->assertEquals( 'hello-world-this-is-a-test', $result );
	}

	/**
	 * Test array sanitization
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_array_sanitization() {
		$input = [
			'name'  => '<script>alert(1)</script>John',
			'email' => 'john@example.com',
			'age'   => '30',
			'bio'   => 'Line 1\nLine 2',
		];

		$sanitized = [];
		foreach ( $input as $key => $value ) {
			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} else {
				$sanitized[ $key ] = $value;
			}
		}

		$this->assertEquals( 'alert(1)John', $sanitized['name'] );
		$this->assertEquals( 'john@example.com', $sanitized['email'] );
		$this->assertEquals( '30', $sanitized['age'] );
		$this->assertStringContainsString( 'Line 1', $sanitized['bio'] );
	}

	/**
	 * Test empty value handling
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_empty_value_handling() {
		$empty_inputs = [
			'',
			'   ',
			"\t\n",
			null,
		];

		foreach ( $empty_inputs as $input ) {
			if ( null === $input ) {
				$result = '';
			} else {
				$result = sanitize_text_field( $input );
			}

			// Empty values remain empty or only whitespace is removed
			$this->assertTrue( empty( trim( $result ) ) );
		}
	}

	/**
	 * Test CSV field sanitization
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_csv_field_sanitization() {
		$csv_data = [
			'name'  => 'John "The Rock" Doe',
			'email' => 'john@example.com',
			'bio'   => 'Developer & Designer',
			'notes' => 'Special chars: ,;:"\'',
		];

		$sanitized = [];
		foreach ( $csv_data as $key => $value ) {
			$sanitized[ $key ] = sanitize_text_field( $value );
		}

		$this->assertStringContainsString( 'John', $sanitized['name'] );
		$this->assertStringContainsString( 'The Rock', $sanitized['name'] );
		$this->assertStringContainsString( 'Doe', $sanitized['name'] );
		$this->assertEquals( 'john@example.com', $sanitized['email'] );
		$this->assertStringContainsString( 'Developer', $sanitized['bio'] );
		$this->assertStringContainsString( 'Designer', $sanitized['bio'] );
	}

	/**
	 * Test length validation
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_length_validation() {
		$long_text = str_repeat( 'This is a very long text. ', 100 );
		$sanitized = sanitize_text_field( $long_text );

		// WordPress sanitize_text_field has length limitations
		$this->assertLessThan( strlen( $long_text ), strlen( $sanitized ) );
		$this->assertGreaterThan( 0, strlen( $sanitized ) );
	}
}
