<?php
/**
 * Debug script to identify the error
 */

// Enable error reporting
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );

// Include WordPress
require_once( '../../../wp-config.php' );

// Try to load the plugin
try {
	require_once 'swift-csv.php';
	echo "Plugin loaded successfully";
} catch ( Exception $e ) {
	echo "Error: " . $e->getMessage();
} catch ( Error $e ) {
	echo "Error: " . $e->getMessage();
}
?>
