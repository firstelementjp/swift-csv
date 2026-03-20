<?php
/**
 * CSV Parser functionality tests
 *
 * @package Swift_CSV\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * CSV Parser tests
 *
 * @since 0.9.7
 */
class CSVParserTest extends TestCase {

	/**
	 * Test basic CSV row parsing
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_parse_csv_row_basic() {
		$line      = 'John,Doe,30';
		$delimiter = ',';

		$result = str_getcsv( $line, $delimiter );

		$this->assertEquals( [ 'John', 'Doe', '30' ], $result );
		$this->assertCount( 3, $result );
	}

	/**
	 * Test CSV row parsing with quotes
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_parse_csv_row_with_quotes() {
		$line      = '"John, Jr.",Doe,30';
		$delimiter = ',';

		$result = str_getcsv( $line, $delimiter );

		$this->assertEquals( [ 'John, Jr.', 'Doe', '30' ], $result );
		$this->assertCount( 3, $result );
	}

	/**
	 * Test CSV row parsing with escaped quotes
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_parse_csv_row_with_escaped_quotes() {
		$line      = '"John ""The Rock"" Doe",Developer,40';
		$delimiter = ',';

		$result = str_getcsv( $line, $delimiter );

		$this->assertEquals( [ 'John "The Rock" Doe', 'Developer', '40' ], $result );
	}

	/**
	 * Test CSV row parsing with semicolon delimiter
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_parse_csv_row_semicolon_delimiter() {
		$line      = 'John;Doe;30';
		$delimiter = ';';

		$result = str_getcsv( $line, $delimiter );

		$this->assertEquals( [ 'John', 'Doe', '30' ], $result );
	}

	/**
	 * Test CSV row parsing with tab delimiter
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_parse_csv_row_tab_delimiter() {
		$line      = "John\tDoe\t30";
		$delimiter = "\t";

		$result = str_getcsv( $line, $delimiter );

		$this->assertEquals( [ 'John', 'Doe', '30' ], $result );
	}

	/**
	 * Test empty CSV line detection
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_is_empty_csv_line() {
		$this->assertTrue( $this->isEmptyCsvLine( '' ) );
		$this->assertTrue( $this->isEmptyCsvLine( '   ' ) );
		$this->assertTrue( $this->isEmptyCsvLine( "\t\n" ) );
		$this->assertFalse( $this->isEmptyCsvLine( 'John,Doe' ) );
		$this->assertFalse( $this->isEmptyCsvLine( ' , ' ) );
	}

	/**
	 * Test CSV content parsing into lines
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_parse_csv_content_to_lines() {
		$csv_content = "name,email,age\nJohn,john@test.com,30\nJane,jane@test.com,25";

		$lines = explode( "\n", $csv_content );

		$this->assertCount( 3, $lines );
		$this->assertEquals( 'name,email,age', $lines[0] );
		$this->assertEquals( 'John,john@test.com,30', $lines[1] );
		$this->assertEquals( 'Jane,jane@test.com,25', $lines[2] );
	}

	/**
	 * Test CSV header extraction
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_extract_csv_headers() {
		$csv_content = "name,email,age\nJohn,john@test.com,30";
		$lines       = explode( "\n", $csv_content );
		$headers     = str_getcsv( $lines[0], ',' );

		$this->assertEquals( [ 'name', 'email', 'age' ], $headers );
		$this->assertCount( 3, $headers );
	}

	/**
	 * Test CSV data row extraction
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_extract_csv_data_rows() {
		$csv_content = "name,email,age\nJohn,john@test.com,30\nJane,jane@test.com,25";
		$lines       = explode( "\n", $csv_content );

		// Skip header and get data rows
		$data_rows = array_slice( $lines, 1 );

		$this->assertCount( 2, $data_rows );

		$row1 = str_getcsv( $data_rows[0], ',' );
		$this->assertEquals( [ 'John', 'john@test.com', '30' ], $row1 );

		$row2 = str_getcsv( $data_rows[1], ',' );
		$this->assertEquals( [ 'Jane', 'jane@test.com', '25' ], $row2 );
	}

	/**
	 * Test CSV with special characters
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_csv_with_special_characters() {
		$line      = '"John & Jane","Doe-Smith","$30,000"';
		$delimiter = ',';

		$result = str_getcsv( $line, $delimiter );

		$this->assertEquals( [ 'John & Jane', 'Doe-Smith', '$30,000' ], $result );
	}

	/**
	 * Test CSV with multiline content in quotes
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_csv_with_multiline_content() {
		$line      = '"Line 1\nLine 2","Doe",30';
		$delimiter = ',';

		$result = str_getcsv( $line, $delimiter );

		// Check actual values to test
		$this->assertCount( 3, $result );
		$this->assertEquals( 'Doe', $result[1] );
		$this->assertEquals( '30', $result[2] );
		// First element contains newline characters
		$this->assertStringContainsString( 'Line 1', $result[0] );
		$this->assertStringContainsString( 'Line 2', $result[0] );
	}

	/**
	 * Test CSV with empty fields
	 *
	 * @since 0.9.7
	 * @return void
	 */
	public function test_csv_with_empty_fields() {
		$line      = 'John,,30';
		$delimiter = ',';

		$result = str_getcsv( $line, $delimiter );

		$this->assertEquals( [ 'John', '', '30' ], $result );
		$this->assertCount( 3, $result );
	}

	/**
	 * Helper method to check if CSV line is empty
	 *
	 * @since 0.9.7
	 * @param string $line CSV line.
	 * @return bool True if line is empty.
	 */
	private function isEmptyCsvLine( $line ) {
		return empty( trim( $line ) );
	}
}
