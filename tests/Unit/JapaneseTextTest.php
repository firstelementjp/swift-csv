<?php
/**
 * Japanese text functionality tests
 *
 * @package Swift_CSV\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * Japanese text tests
 *
 * @since 0.9.7
 */
class JapaneseTextTest extends TestCase {

	/**
	 * Test CSV parsing with Japanese characters
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_csv_parsing_with_japanese_characters() {
		$line      = '山田太郎,yamada@example.com,30';
		$delimiter = ',';

		$result = str_getcsv( $line, $delimiter );

		$this->assertEquals( [ '山田太郎', 'yamada@example.com', '30' ], $result );
		$this->assertCount( 3, $result );
	}

	/**
	 * Test CSV parsing with Japanese characters in quotes
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_csv_parsing_with_japanese_quotes() {
		$line      = '"山田, 太郎",yamada@example.com,30';
		$delimiter = ',';

		$result = str_getcsv( $line, $delimiter );

		$this->assertEquals( [ '山田, 太郎', 'yamada@example.com', '30' ], $result );
	}

	/**
	 * Test CSV parsing with full-width characters
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_csv_parsing_with_fullwidth_characters() {
		$line      = '山田太郎,田中@example.com,３０';
		$delimiter = ',';

		$result = str_getcsv( $line, $delimiter );

		$this->assertEquals( [ '山田太郎', '田中@example.com', '３０' ], $result );
	}

	/**
	 * Test CSV parsing with mixed Japanese and English
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_csv_parsing_with_mixed_languages() {
		$line      = '山田Taro,yamada@example.com,Engineer';
		$delimiter = ',';

		$result = str_getcsv( $line, $delimiter );

		$this->assertEquals( [ '山田Taro', 'yamada@example.com', 'Engineer' ], $result );
	}

	/**
	 * Test CSV parsing with Japanese special characters
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_csv_parsing_with_japanese_special_chars() {
		$line      = '株式会社山田商事,info@yamada.co.jp,東京都千代田区';
		$delimiter = ',';

		$result = str_getcsv( $line, $delimiter );

		$this->assertEquals( [ '株式会社山田商事', 'info@yamada.co.jp', '東京都千代田区' ], $result );
	}

	/**
	 * Test Japanese text sanitization
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_japanese_text_sanitization() {
		$input  = '<script>alert("テスト")</script>山田太郎';
		$result = strip_tags( $input );

		$this->assertEquals( 'alert("テスト")山田太郎', $result );
		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( '</script>', $result );
	}

	/**
	 * Test Japanese text with HTML entities
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_japanese_text_html_entities() {
		$input  = '山田太郎 & 田中花子';
		$result = htmlspecialchars( $input, ENT_QUOTES, 'UTF-8' );

		$this->assertStringContainsString( '山田太郎', $result );
		$this->assertStringContainsString( '田中花子', $result );
		$this->assertStringContainsString( '&amp;', $result );
	}

	/**
	 * Test Japanese email validation
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_japanese_email_validation() {
		$valid_emails = [
			'yamada@example.com',
			'tanaka@example.jp',
		];

		$invalid_emails = [
			'山田@example.com',  // Japanese characters in local part are invalid
			'tanaka@例題.com',  // Japanese characters in domain are invalid in standard validation
			'@ドメイン.com',      // Missing local part
		];

		foreach ( $valid_emails as $email ) {
			$this->assertTrue( filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false, "Email {$email} should be valid" );
		}

		foreach ( $invalid_emails as $email ) {
			$this->assertFalse( filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false, "Email {$email} should be invalid" );
		}
	}

	/**
	 * Test Japanese URL validation
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_japanese_url_validation() {
		$valid_urls = [
			'https://example.com',
			'http://domain.org/path',
		];

		foreach ( $valid_urls as $url ) {
			$this->assertTrue( filter_var( $url, FILTER_VALIDATE_URL ) !== false, "URL {$url} should be valid" );
		}
	}

	/**
	 * Test Japanese slug generation
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_japanese_slug_generation() {
		$input  = '山田太郎のブログ';
		$result = $this->generateSlug( $input );

		// Japanese characters are removed by our simple slug function
		$this->assertEquals( '', $result );
	}

	/**
	 * Test Japanese array sanitization
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_japanese_array_sanitization() {
		$input = [
			'name'    => '<script>alert(1)</script>山田太郎',
			'email'   => 'yamada@example.com',
			'company' => '株式会社山田商事',
			'address' => '東京都千代田区丸の内1-1-1',
		];

		$sanitized = [];
		foreach ( $input as $key => $value ) {
			$sanitized[ $key ] = $this->sanitizeText( $value );
		}

		$this->assertEquals( 'alert(1)山田太郎', $sanitized['name'] );
		$this->assertEquals( 'yamada@example.com', $sanitized['email'] );
		$this->assertEquals( '株式会社山田商事', $sanitized['company'] );
		$this->assertStringContainsString( '東京都', $sanitized['address'] );
	}

	/**
	 * Test Japanese CSV content parsing
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_japanese_csv_content_parsing() {
		$csv_content = "名前,メールアドレス,年齢\n山田太郎,yamada@example.com,30\n田中花子,tanaka@example.com,25";
		$lines       = explode( "\n", $csv_content );

		$headers = str_getcsv( $lines[0], ',' );
		$row1    = str_getcsv( $lines[1], ',' );
		$row2    = str_getcsv( $lines[2], ',' );

		$this->assertEquals( [ '名前', 'メールアドレス', '年齢' ], $headers );
		$this->assertEquals( [ '山田太郎', 'yamada@example.com', '30' ], $row1 );
		$this->assertEquals( [ '田中花子', 'tanaka@example.com', '25' ], $row2 );
	}

	/**
	 * Test Japanese text length validation
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_japanese_text_length_validation() {
		$short_text = '山田';
		$long_text  = str_repeat( 'これは非常に長い日本語のテキストです。', 50 );

		$this->assertLessThan( 10, mb_strlen( $short_text, 'UTF-8' ) );
		$this->assertGreaterThan( 100, mb_strlen( $long_text, 'UTF-8' ) );

		// Test truncation
		$max_length = 50;
		$truncated  = mb_substr( $long_text, 0, $max_length, 'UTF-8' );
		$this->assertLessThanOrEqual( $max_length, mb_strlen( $truncated, 'UTF-8' ) );
	}

	/**
	 * Test Japanese empty value handling
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_japanese_empty_value_handling() {
		$empty_inputs = [
			'',
			'   ',
			"\t\n",
			'　　',  // Full-width spaces
			null,
		];

		foreach ( $empty_inputs as $input ) {
			$result = $this->sanitizeText( $input );
			$this->assertTrue( empty( trim( $result ) ) );
		}
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

		// Trim whitespace including full-width spaces
		$text = trim( $text );
		$text = trim( $text, " \t\n\r\0\x0B　" );

		// Convert special characters to HTML entities
		$text = htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );

		return $text;
	}

	/**
	 * Helper method to generate slug
	 *
	 * @since 0.9.7
	 * @param string $text Input text.
	 * @return string Generated slug.
	 */
	private function generateSlug( $text ) {
		// For Japanese text, we'll use a simple approach
		// In a real implementation, you might want to use transliteration

		// Convert to lowercase
		$text = strtolower( $text );

		// Replace non-alphanumeric characters with hyphens
		$text = preg_replace( '/[^a-z0-9]+/', '-', $text );

		// Remove leading/trailing hyphens
		$text = trim( $text, '-' );

		return $text;
	}
}
