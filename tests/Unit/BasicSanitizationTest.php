<?php
/**
 * Basic sanitization functionality tests (without WordPress dependency)
 *
 * @package Swift_CSV\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * Basic sanitization tests
 *
 * @since 0.9.7
 */
class BasicSanitizationTest extends TestCase {

	/**
	 * Test basic text cleaning
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_basic_text_cleaning() {
		$input  = '  Simple text  ';
		$result = trim( $input );

		$this->assertEquals( 'Simple text', $result );
	}

	/**
	 * Test HTML tag removal using strip_tags
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_html_tag_removal() {
		$input  = '<script>alert("xss")</script>Safe content';
		$result = strip_tags( $input );

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
		$result = htmlspecialchars( $input, ENT_QUOTES, 'UTF-8' );

		$this->assertStringContainsString( '&amp;', $result );
		$this->assertStringContainsString( '&lt;', $result );
		$this->assertStringContainsString( '&gt;', $result );
		$this->assertStringContainsString( '&quot;', $result );
	}

	/**
	 * Test numeric value validation
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_numeric_value_validation() {
		$int_input     = '123';
		$float_input   = '123.45';
		$invalid_input = 'abc123';

		$this->assertTrue( is_numeric( $int_input ) );
		$this->assertTrue( is_numeric( $float_input ) );
		$this->assertFalse( is_numeric( $invalid_input ) );

		$this->assertEquals( 123, intval( $int_input ) );
		$this->assertEquals( 123.45, floatval( $float_input ) );
	}

	/**
	 * Test email validation
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_email_validation() {
		$valid_emails = [
			'user@example.com',
			'test.email+tag@domain.co.uk',
			'user123@test-domain.org',
		];

		$invalid_emails = [
			'not-an-email',
			'@domain.com',
			'user@',
			'user..name@domain.com',
		];

		foreach ( $valid_emails as $email ) {
			$this->assertTrue( $this->isValidEmail( $email ), "Email {$email} should be valid" );
		}

		foreach ( $invalid_emails as $email ) {
			$this->assertFalse( $this->isValidEmail( $email ), "Email {$email} should be invalid" );
		}
	}

	/**
	 * Test URL validation
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_url_validation() {
		$valid_urls = [
			'https://example.com',
			'http://domain.org/path',
			'https://sub.domain.co.uk/page?param=value',
		];

		$invalid_urls = [
			'javascript:alert(1)',
			'not-a-url',
		];

		foreach ( $valid_urls as $url ) {
			$this->assertTrue( $this->isValidUrl( $url ), "URL {$url} should be valid" );
		}

		foreach ( $invalid_urls as $url ) {
			$this->assertFalse( $this->isValidUrl( $url ), "URL {$url} should be invalid" );
		}
	}

	/**
	 * Test slug generation
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_slug_generation() {
		$input  = 'Hello World! This is a Test';
		$result = $this->generateSlug( $input );

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
			$sanitized[ $key ] = $this->sanitizeText( $value );
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
			$result = $this->sanitizeText( $input );
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
			$sanitized[ $key ] = $this->sanitizeText( $value );
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
		$short_text = 'Short';
		$long_text  = str_repeat( 'This is a very long text. ', 100 );

		$this->assertLessThan( 50, strlen( $short_text ) );
		$this->assertGreaterThan( 1000, strlen( $long_text ) );

		// Test truncation
		$max_length = 100;
		$truncated  = substr( $long_text, 0, $max_length );
		$this->assertLessThanOrEqual( $max_length, strlen( $truncated ) );
	}

	/**
	 * Helper method to sanitize text
	 *
	 * @since 0.9.7
	 * @param mixed $value Input value.
	 * @return string Sanitized text.
	 */
	private function sanitizeText( $value ) {
		if ( null === $value ) {
			return '';
		}

		// Remove HTML tags
		$text = strip_tags( (string) $value );

		// Trim whitespace
		$text = trim( $text );

		// Convert special characters to HTML entities
		$text = htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );

		return $text;
	}

	/**
	 * Helper method to validate email
	 *
	 * @since 0.9.7
	 * @param string $email Email address.
	 * @return bool True if valid.
	 */
	private function isValidEmail( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
	}

	/**
	 * Helper method to validate URL
	 *
	 * @since 0.9.7
	 * @param string $url URL.
	 * @return bool True if valid.
	 */
	private function isValidUrl( $url ) {
		return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Helper method to generate slug
	 *
	 * @since 0.9.7
	 * @param string $text Input text.
	 * @return string Generated slug.
	 */
	private function generateSlug( $text ) {
		// Convert to lowercase
		$text = strtolower( $text );

		// Replace non-alphanumeric characters with hyphens
		$text = preg_replace( '/[^a-z0-9]+/', '-', $text );

		// Remove leading/trailing hyphens
		$text = trim( $text, '-' );

		return $text;
	}
}
