<?php
/**
 * Test Export Fix Script
 * 
 * Tests the improved CSV export functionality with problematic data.
 * 
 * @since 0.9.5
 */

// Define ABSPATH if not defined (for standalone testing)
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../');
}

// Include WordPress
require_once(ABSPATH . 'wp-config.php');

// Include the exporter class
require_once('includes/class-swift-csv-exporter.php');

echo "=== Testing Improved CSV Export Fix ===\n\n";

// Create exporter instance
$exporter = new Swift_CSV_Exporter();

// Test problematic content samples
$test_cases = [
    'excessive_backslashes' => [
        'input' => 'A \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\"can-do\\\\\\\\\\\\\\\\\\\\\\\\\\\" attitude and positive outlook',
        'expected_issues' => ['excessive backslashes', 'unmatched quotes']
    ],
    'unmatched_quotes' => [
        'input' => '移動スーパー【とくし丸」の車両を所有し、地域のスーパーマーケットが取り扱う生鮮食品',
        'expected_issues' => ['unmatched quotes']
    ],
    'mixed_escaping' => [
        'input' => 'Bright and inquisitive telephone researchers required for Investment firm based in the West End of London. A \\"can-do\\" attitude and positive outlook',
        'expected_issues' => ['mixed escaping']
    ],
    'normal_content' => [
        'input' => 'これは正常なコンテンツです。引用符"もバックスラッシュ\\も適切に処理されるはずです。',
        'expected_issues' => []
    ]
];

echo "Testing fix_over_escaping method:\n";
echo str_repeat("=", 50) . "\n\n";

foreach ($test_cases as $case_name => $test_case) {
    echo "Test Case: $case_name\n";
    echo str_repeat("-", 30) . "\n";
    
    $input = $test_case['input'];
    $expected_issues = $test_case['expected_issues'];
    
    echo "Input length: " . strlen($input) . " chars\n";
    echo "Input preview: " . substr($input, 0, 100) . (strlen($input) > 100 ? '...' : '') . "\n";
    
    // Analyze input
    $backslash_count = substr_count($input, '\\');
    $quote_count = substr_count($input, '"');
    
    echo "Input analysis:\n";
    echo "  Backslashes: $backslash_count\n";
    echo "  Quotes: $quote_count\n";
    
    if ($backslash_count > 5) {
        echo "  ⚠️  Excessive backslashes detected\n";
    }
    if ($quote_count % 2 !== 0) {
        echo "  ⚠️  Unmatched quotes detected\n";
    }
    
    // Apply the fix
    $fixed = $exporter->fix_over_escaping($input);
    
    echo "\nAfter fix:\n";
    echo "Output length: " . strlen($fixed) . " chars\n";
    echo "Output preview: " . substr($fixed, 0, 100) . (strlen($fixed) > 100 ? '...' : '') . "\n";
    
    // Analyze output
    $fixed_backslash_count = substr_count($fixed, '\\');
    $fixed_quote_count = substr_count($fixed, '"');
    
    echo "Output analysis:\n";
    echo "  Backslashes: $fixed_backslash_count (reduced by " . ($backslash_count - $fixed_backslash_count) . ")\n";
    echo "  Quotes: $fixed_quote_count\n";
    
    if ($fixed_quote_count % 2 === 0) {
        echo "  ✅ Quotes are now balanced\n";
    } else {
        echo "  ❌ Quotes still unbalanced\n";
    }
    
    if ($fixed_backslash_count <= 2) {
        echo "  ✅ Backslashes normalized\n";
    } else {
        echo "  ⚠️  Still has many backslashes\n";
    }
    
    echo "\n";
}

// Test full CSV generation with problematic data
echo "Testing full CSV generation:\n";
echo str_repeat("=", 50) . "\n";

// Create mock post data with problematic content
$mock_posts = [
    (object)[
        'ID' => 87842,
        'post_title' => '移動スーパー「とくし丸」ルートセールス（個人事業主）※未経験歓迎！',
        'post_content' => '移動スーパー【とくし丸」の車両を所有し、地域のスーパーマーケットが取り扱う生鮮食品や生活雑貨等の移動販売を行っていただきます。買い物にお困りの方（＝買い物難民）が非常に多く、社会課題となっております。そんな方々のもとを軽トラックで一軒一軒訪問し、自分の目で見て買い物をする機会を提供する仕事です。',
        'post_excerpt' => '',
        'post_status' => 'publish',
        'post_name' => 'mobile-supermarket-route-sales',
        'post_date' => '2023-11-27 10:42:56'
    ],
    (object)[
        'ID' => 9695,
        'post_title' => '日本語研究員',
        'post_content' => 'Bright and inquisitive telephone researchers required for Investment firm based in the West End of London. A \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\"can-do\\\\\\\\\\\\\\\\\\\\\\\\\\\" attitude and positive outlook. Experience of conducting depth telephone interviews with a variety of audiences, preferably both consumer and B2B.',
        'post_excerpt' => '',
        'post_status' => 'publish',
        'post_name' => 'japanese-researcher',
        'post_date' => '2011-03-22 21:25:57'
    ]
];

// Test CSV generation
$headers = ['ID', 'title', 'content', 'excerpt', 'status', 'slug', 'date'];
$taxonomies = []; // Empty for this test
$custom_fields = []; // Empty for this test

// Use reflection to access private method for testing
$reflection = new ReflectionClass($exporter);
$method = $reflection->getMethod('generate_csv_content');
$method->setAccessible(true);

$csv_content = $method->invoke($exporter, $mock_posts, $headers, $taxonomies, $custom_fields);

echo "Generated CSV with problematic data:\n";
echo "Total length: " . strlen($csv_content) . " chars\n";

// Parse and verify the generated CSV
$lines = explode("\n", trim($csv_content));
echo "Lines generated: " . count($lines) . "\n";

foreach ($lines as $line_num => $line) {
    if ($line_num === 0) {
        echo "Header: $line\n";
        continue;
    }
    
    $fields = str_getcsv($line);
    echo "Row $line_num: " . count($fields) . " fields\n";
    
    // Check content field (index 2)
    if (isset($fields[2])) {
        $content = $fields[2];
        $backslash_count = substr_count($content, '\\');
        $quote_count = substr_count($content, '"');
        
        echo "  Content field: $backslash_count backslashes, $quote_count quotes\n";
        
        if ($quote_count % 2 === 0) {
            echo "  ✅ Quotes balanced\n";
        } else {
            echo "  ❌ Quotes unbalanced\n";
        }
        
        if ($backslash_count <= 2) {
            echo "  ✅ Backslashes normalized\n";
        } else {
            echo "  ⚠️  Still has excessive backslashes\n";
        }
    }
}

echo "\n=== Test completed ===\n";
