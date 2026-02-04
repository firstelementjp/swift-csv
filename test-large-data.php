<?php
/**
 * Large Data Test Script for Swift CSV Plugin
 *
 * This script simulates large-scale export/import operations
 * to test performance and memory usage.
 */

// Increase memory limit for testing
ini_set( 'memory_limit', '1G' );
set_time_limit( 300 ); // 5 minutes

// WordPress environment simulation
define( 'WP_DEBUG', true );

class SwiftCSV_LargeData_Test {

	private $test_data = [];
	private $start_time;
	private $memory_start;

	public function __construct() {
		$this->start_time   = microtime( true );
		$this->memory_start = memory_get_usage();
	}

	/**
	 * Generate test data for large scale testing
	 */
	public function generate_test_data( $count = 44000 ) {
		echo "🔄 Generating {$count} test records...\n";

		$categories = [ 'Technology', 'Business', 'Lifestyle', 'Sports', 'Entertainment' ];
		$tags       = [ 'trending', 'popular', 'featured', 'new', 'updated' ];

		for ( $i = 1; $i <= $count; $i++ ) {
			$this->test_data[] = [
				'ID'              => $i,
				'post_title'      => "Test Post {$i}: " . $this->generate_random_title(),
				'post_content'    => $this->generate_random_content( $i ),
				'post_excerpt'    => "Excerpt for test post {$i}",
				'post_status'     => 'publish',
				'post_name'       => "test-post-{$i}",
				'post_date'       => date( 'Y-m-d H:i:s', strtotime( "-{$i} minutes" ) ),
				'tax_category'    => $categories[ array_rand( $categories ) ],
				'tax_post_tag'    => $this->get_random_tags( $tags ),
				'cf_sku'          => 'SKU-' . str_pad( $i, 6, '0', STR_PAD_LEFT ),
				'cf_price'        => rand( 100, 50000 ) / 100,
				'cf_stock_status' => rand( 0, 1 ) ? 'instock' : 'outofstock',
				'cf_views'        => rand( 0, 10000 ),
				'cf_rating'       => rand( 10, 50 ) / 10,
			];

			// Progress indicator
			if ( $i % 1000 === 0 ) {
				$progress = round( ( $i / $count ) * 100, 1 );
				echo "  Progress: {$progress}% ({$i}/{$count})\n";
				$this->log_memory_usage( "Generation - {$i} records" );
			}
		}

		echo "✅ Test data generation completed!\n";
		$this->log_performance( 'Data Generation' );
		return $this->test_data;
	}

	/**
	 * Simulate CSV export process
	 */
	public function simulate_export( $data ) {
		echo "\n📤 Simulating CSV export for " . count( $data ) . " records...\n";

		$csv_content = '';
		$start_time  = microtime( true );

		// Add CSV headers
		$headers      = array_keys( $data[0] );
		$csv_content .= implode( ',', $headers ) . "\n";

		// Process each row
		foreach ( $data as $index => $row ) {
			// Simulate data cleaning (like in Swift_CSV_Exporter)
			$cleaned_row  = array_map( [ $this, 'clean_csv_field' ], $row );
			$csv_content .= implode( ',', $cleaned_row ) . "\n";

			// Progress indicator
			if ( $index % 1000 === 0 ) {
				$progress = round( ( $index / count( $data ) ) * 100, 1 );
				echo "  Export Progress: {$progress}% ({$index}/" . count( $data ) . ")\n";
				$this->log_memory_usage( "Export - {$index} records" );
			}
		}

		$export_time = microtime( true ) - $start_time;
		$file_size   = strlen( $csv_content );

		echo "✅ Export simulation completed!\n";
		echo '   Export Time: ' . round( $export_time, 2 ) . " seconds\n";
		echo '   File Size: ' . $this->format_bytes( $file_size ) . "\n";
		echo '   Records/Second: ' . round( count( $data ) / $export_time ) . "\n";

		$this->log_performance( 'CSV Export' );
		return $csv_content;
	}

	/**
	 * Simulate CSV import process
	 */
	public function simulate_import( $csv_content ) {
		echo "\n📥 Simulating CSV import...\n";

		$lines   = explode( "\n", trim( $csv_content ) );
		$headers = str_getcsv( array_shift( $lines ) );

		$imported   = 0;
		$updated    = 0;
		$errors     = 0;
		$start_time = microtime( true );

		foreach ( $lines as $line_index => $line ) {
			try {
				if ( empty( $line ) ) {
					continue;
				}

				$row = str_getcsv( $line );
				if ( count( $row ) !== count( $headers ) ) {
					// Skip malformed rows
					++$errors;
					continue;
				}

				$data = array_combine( $headers, $row );

				// Simulate slug processing
				if ( empty( $data['post_name'] ) ) {
					$data['post_name'] = sanitize_title( $data['post_title'] );
					$data['post_name'] = $this->make_slug_unique( $data['post_name'] );
				}

				// Simulate post creation/update
				if ( rand( 1, 10 ) <= 8 ) { // 80% new, 20% update
					++$imported;
				} else {
					++$updated;
				}

				// Progress indicator
				if ( $line_index % 1000 === 0 ) {
					$progress = round( ( $line_index / count( $lines ) ) * 100, 1 );
					echo "  Import Progress: {$progress}% ({$line_index}/" . count( $lines ) . ")\n";
					$this->log_memory_usage( "Import - {$line_index} records" );
				}
			} catch ( Exception $e ) {
				++$errors;
			}
		}

		$import_time = microtime( true ) - $start_time;

		echo "✅ Import simulation completed!\n";
		echo '   Import Time: ' . round( $import_time, 2 ) . " seconds\n";
		echo "   Imported: {$imported}\n";
		echo "   Updated: {$updated}\n";
		echo "   Errors: {$errors}\n";
		echo '   Records/Second: ' . round( count( $lines ) / $import_time ) . "\n";

		$this->log_performance( 'CSV Import' );
		return [ 'imported' => $imported, 'updated' => $updated, 'errors' => $errors ];
	}

	/**
	 * Test memory usage and performance
	 */
	public function run_performance_test() {
		echo "\n🚀 Starting Large Data Performance Test\n";
		echo "=====================================\n";

		// Generate test data
		$data = $this->generate_test_data( 44000 );

		// Test export
		$csv_content = $this->simulate_export( $data );

		// Test import
		$results = $this->simulate_import( $csv_content );

		// Final performance report
		$this->generate_final_report( $results );
	}

	/**
	 * Get random tags from array
	 */
	private function get_random_tags( $tags ) {
		$count         = rand( 1, min( 3, count( $tags ) ) );
		$selected_keys = array_rand( $tags, $count );
		if ( ! is_array( $selected_keys ) ) {
			$selected_keys = [ $selected_keys ];
		}
		$selected_tags = array_intersect_key( $tags, array_flip( $selected_keys ) );
		return implode( '|', $selected_tags );
	}

	/**
	 * Helper functions
	 */
	private function generate_random_title() {
		$words    = [ 'Advanced', 'Modern', 'Digital', 'Smart', 'Innovative', 'Professional', 'Premium', 'Elite' ];
		$subjects = [ 'Solution', 'Platform', 'System', 'Framework', 'Technology', 'Service', 'Product', 'Tool' ];
		return $words[ array_rand( $words ) ] . ' ' . $subjects[ array_rand( $subjects ) ];
	}

	private function generate_random_content( $index ) {
		return "This is test content for post number {$index}. " .
				'It contains multiple sentences to simulate real content. ' .
				'The content is generated randomly for testing purposes. ' .
				'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
	}

	private function clean_csv_field( $field ) {
		if ( null === $field || '' === $field ) {
			return '';
		}

		if ( is_array( $field ) ) {
			$field = implode( '|', array_map( 'strval', $field ) );
		} elseif ( is_bool( $field ) ) {
			$field = $field ? '1' : '0';
		} else {
			$field = (string) $field;
		}

		// Convert newlines to spaces
		$field = preg_replace( '/\r\n|\r|\n/', ' ', $field );
		return trim( $field );
	}

	private function make_slug_unique( $slug ) {
		// Simple simulation - in real implementation this would check database
		if ( rand( 1, 100 ) <= 5 ) { // 5% chance of duplicate
			return $slug . '-' . rand( 2, 99 );
		}
		return $slug;
	}

	private function log_memory_usage( $context ) {
		$current = memory_get_usage();
		$peak    = memory_get_peak_usage();
		$used_mb = round( $current / 1024 / 1024, 2 );
		$peak_mb = round( $peak / 1024 / 1024, 2 );

		echo "    Memory: {$used_mb}MB (Peak: {$peak_mb}MB) - {$context}\n";
	}

	private function log_performance( $operation ) {
		$time   = microtime( true ) - $this->start_time;
		$memory = memory_get_usage() - $this->memory_start;

		echo "\n📊 {$operation} Performance:\n";
		echo '   Time: ' . round( $time, 2 ) . " seconds\n";
		echo '   Memory: ' . $this->format_bytes( $memory ) . "\n";
	}

	private function generate_final_report( $results ) {
		$total_time   = microtime( true ) - $this->start_time;
		$total_memory = memory_get_peak_usage();

		echo "\n📈 FINAL PERFORMANCE REPORT\n";
		echo "========================\n";
		echo 'Total Time: ' . round( $total_time, 2 ) . " seconds\n";
		echo 'Peak Memory: ' . round( $total_memory / 1024 / 1024, 2 ) . " MB\n";
		echo "Records Processed: 44,000\n";
		echo 'Records/Second: ' . round( 44000 / $total_time ) . "\n";
		echo 'Memory per Record: ' . round( $total_memory / 44000 ) . " bytes\n";

		// Performance assessment
		echo "\n🎯 Performance Assessment:\n";
		if ( $total_time < 60 ) {
			echo "   ✅ Excellent performance (< 60 seconds)\n";
		} elseif ( $total_time < 120 ) {
			echo "   ⚠️  Good performance (60-120 seconds)\n";
		} else {
			echo "   ❌ Needs optimization (> 120 seconds)\n";
		}

		if ( $total_memory < 256 * 1024 * 1024 ) { // 256MB
			echo "   ✅ Excellent memory usage (< 256MB)\n";
		} elseif ( $total_memory < 512 * 1024 * 1024 ) { // 512MB
			echo "   ⚠️  Good memory usage (256-512MB)\n";
		} else {
			echo "   ❌ High memory usage (> 512MB)\n";
		}

		echo "\n📝 Test Results:\n";
		echo "   Imported: {$results['imported']}\n";
		echo "   Updated: {$results['updated']}\n";
		echo "   Errors: {$results['errors']}\n";
		echo '   Success Rate: ' . round( ( ( $results['imported'] + $results['updated'] ) / 44000 ) * 100, 1 ) . "%\n";
	}

	private function format_bytes( $bytes ) {
		$units = [ 'B', 'KB', 'MB', 'GB' ];
		$bytes = max( $bytes, 0 );
		$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );

		$bytes /= pow( 1024, $pow );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
}

// Run the test
$test = new SwiftCSV_LargeData_Test();
$test->run_performance_test();

echo "\n🎉 Large Data Test Completed!\n";
