<?php
/**
 * Debug Export Headers Script
 * 
 * Checks if headers are being properly generated in CSV export.
 * 
 * @since 0.9.5
 */

echo "=== Debug Export Headers ===\n\n";

// Check the generate_csv_content method logic
function simulate_generate_csv_content() {
    // Simulate headers
    $headers = ['ID', 'title', 'content', 'excerpt', 'status', 'slug', 'date'];
    
    // Simulate posts
    $posts = [
        (object)[
            'ID' => 1,
            'post_title' => 'Test Post',
            'post_content' => 'Test content',
            'post_excerpt' => 'Test excerpt',
            'post_status' => 'publish',
            'post_name' => 'test-post',
            'post_date' => '2023-01-01 00:00:00'
        ]
    ];
    
    $taxonomies = [];
    $custom_fields = [];
    
    $csv = array();
    
    // Add header row
    $csv[] = $headers;
    echo "Headers added to CSV array:\n";
    echo "  Count: " . count($csv) . "\n";
    echo "  First element: " . implode(', ', $csv[0]) . "\n\n";
    
    // Add data rows for each post
    foreach ($posts as $post) {
        $row = array();
        
        // Basic post data
        $row[] = $post->ID;
        $row[] = $post->post_title;
        $row[] = $post->post_content;
        $row[] = $post->post_excerpt;
        $row[] = $post->post_status;
        $row[] = $post->post_name;
        $row[] = $post->post_date;
        
        $csv[] = $row;
    }
    
    echo "After adding data rows:\n";
    echo "  Total rows: " . count($csv) . "\n";
    echo "  Row 0 (headers): " . implode(', ', $csv[0]) . "\n";
    echo "  Row 1 (data): " . implode(', ', $csv[1]) . "\n\n";
    
    // Convert array to CSV string using PHP's built-in function
    $output = fopen('php://temp', 'r+');
    foreach ($csv as $row) {
        fputcsv($output, $row);
    }
    rewind($output);
    $content = stream_get_contents($output);
    fclose($output);
    
    echo "Generated CSV content:\n";
    echo "  Total length: " . strlen($content) . " chars\n";
    echo "  First 200 chars: " . substr($content, 0, 200) . "\n\n";
    
    // Parse the generated CSV to verify
    $lines = explode("\n", trim($content));
    echo "Parsed CSV verification:\n";
    echo "  Number of lines: " . count($lines) . "\n";
    
    foreach ($lines as $i => $line) {
        $fields = str_getcsv($line);
        echo "  Line $i: " . count($fields) . " fields - " . $fields[0] . "\n";
    }
    
    return $content;
}

// Test the simulation
$csv_content = simulate_generate_csv_content();

echo "\n=== Check Real Export File ===\n";

// Check if there's a recent export file
$export_dir = '/Users/fe_miyazawa/Downloads/';
$files = glob($export_dir . 'export_*.csv');

if (!empty($files)) {
    $latest_file = end($files);
    echo "Latest export file: " . basename($latest_file) . "\n";
    
    $content = file_get_contents($latest_file);
    $lines = explode("\n", trim($content));
    
    echo "File analysis:\n";
    echo "  Total lines: " . count($lines) . "\n";
    
    if (count($lines) > 0) {
        $first_line = $lines[0];
        $first_fields = str_getcsv($first_line);
        
        echo "  First line fields: " . count($first_fields) . "\n";
        echo "  First line content: " . substr($first_line, 0, 100) . "\n";
        
        // Check if it looks like headers (should contain 'ID', 'title', etc.)
        $header_indicators = ['ID', 'title', 'content', 'excerpt', 'status', 'slug', 'date'];
        $is_header = false;
        
        foreach ($header_indicators as $indicator) {
            if (in_array($indicator, $first_fields)) {
                $is_header = true;
                break;
            }
        }
        
        echo "  Appears to be headers: " . ($is_header ? 'YES' : 'NO') . "\n";
        
        // Check second line if it exists
        if (count($lines) > 1) {
            $second_line = $lines[1];
            $second_fields = str_getcsv($second_line);
            
            echo "  Second line fields: " . count($second_fields) . "\n";
            echo "  Second line first field: " . $second_fields[0] . "\n";
            
            // Check if second line looks like data (should be numeric ID)
            $is_data = is_numeric($second_fields[0]);
            echo "  Appears to be data: " . ($is_data ? 'YES' : 'NO') . "\n";
        }
    }
} else {
    echo "No export files found in Downloads directory.\n";
}

echo "\n=== Debug completed ===\n";
