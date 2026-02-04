<?php
/**
 * CSV Fix Script - Memory Efficient Version
 *
 * Fixes two issues in the exported CSV:
 * 1. Missing comma between column 4 and 5 in specific rows
 * 2. Removes single quotes from ID values
 *
 * @since 0.9.5
 */

// Input and output files
$inputFile  = '/Users/fe_miyazawa/Downloads/d706e9c1-9178-4ee5-b809-7c3fbed4a198.csv';
$outputFile = '/Users/fe_miyazawa/Downloads/d706e9c1-9178-4ee5-b809-7c3fbed4a198-fixed.csv';

// Problematic row IDs (based on user's report)
$problematicRows = [ 87842, 87843, 87844, 87845, 87850, 87851, 87852, 87853, 87854 ];

echo "Starting CSV fix process...\n";
echo "Input file: $inputFile\n";
echo "Output file: $outputFile\n\n";

// Read input file
if ( ! file_exists( $inputFile ) ) {
	die( "Input file not found: $inputFile\n" );
}

$inputHandle  = fopen( $inputFile, 'r' );
$outputHandle = fopen( $outputFile, 'w' );

if ( ! $inputHandle || ! $outputHandle ) {
	die( "Cannot open files\n" );
}

$lineNumber   = 0;
$fixedLines   = 0;
$idFixedLines = 0;

// Process line by line to save memory
while ( ( $line = fgets( $inputHandle ) ) !== false ) {
	++$lineNumber;
	$originalLine = trim( $line );

	// Fix 1: Remove single quotes from ID at the beginning of the line
	if ( preg_match( '/^\'(\d+)/', $originalLine, $matches ) ) {
		$originalLine = $matches[1] . substr( $originalLine, strlen( $matches[0] ) );
		++$idFixedLines;
	}

	// Fix 2: Add missing comma for problematic rows
	// Extract ID from the beginning of the line
	if ( preg_match( '/^(\d+)/', $originalLine, $matches ) ) {
		$rowId = intval( $matches[1] );

		// Check if this is a problematic row
		if ( in_array( $rowId, $problematicRows ) ) {
			// Look for pattern: "publish",slug (missing comma)
			if ( preg_match( '/("publish",)([^"\n])/', $originalLine, $matches ) ) {
				// Insert comma after publish
				$originalLine = str_replace( $matches[0], $matches[1] . ',' . $matches[2], $originalLine );
				++$fixedLines;
				echo "Fixed missing comma in row $rowId (line $lineNumber)\n";
			}
		}
	}

	// Write fixed line
	fwrite( $outputHandle, $originalLine . "\n" );

	// Progress indicator
	if ( $lineNumber % 5000 === 0 ) {
		echo "Processed $lineNumber lines\n";
	}
}

fclose( $inputHandle );
fclose( $outputHandle );

echo "\n=== Fix Summary ===\n";
echo "Total lines processed: $lineNumber\n";
echo "ID fixes: $idFixedLines\n";
echo "Missing comma fixes: $fixedLines\n";
echo "Output saved to: $outputFile\n\n";

// Quick verification of specific problematic rows
echo "=== Verification ===\n";
$verifyHandle     = fopen( $outputFile, 'r' );
$verifyLineNumber = 0;

while ( ( $line = fgets( $verifyHandle ) ) !== false && $verifyLineNumber < 42330 ) {
	++$verifyLineNumber;
	$line = trim( $line );

	// Extract ID
	if ( preg_match( '/^(\d+)/', $line, $matches ) ) {
		$rowId = intval( $matches[1] );

		if ( in_array( $rowId, $problematicRows ) ) {
			$fields = str_getcsv( $line );
			echo "Row $rowId: " . ( count( $fields ) >= 12 ? '✓ Fixed (' . count( $fields ) . ' columns)' : '✗ Still has issues (' . count( $fields ) . ' columns)' ) . "\n";
		}
	}
}

fclose( $verifyHandle );

echo "\nCSV fix process completed!\n";
echo "Please check the output file: $outputFile\n";
