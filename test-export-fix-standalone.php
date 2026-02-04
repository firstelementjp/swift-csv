<?php
/**
 * Test Export Fix Script (Standalone)
 * 
 * Tests the improved CSV export functionality with problematic data
 * without requiring WordPress environment.
 * 
 * @since 0.9.5
 */

echo "=== Testing Improved CSV Export Fix (Standalone) ===\n\n";

// Extract the normalized methods for testing
function normalize_backslashes($field) {
    // Reduce excessive consecutive backslashes (3+ backslashes)
    $field = preg_replace('/\\\\{3,}/', '\\', $field);
    
    // Fix double backslashes followed by quote (common over-escaping)
    $field = preg_replace('/\\\\\\\\"/', '"', $field);
    
    return $field;
}

function normalize_quotes($field) {
    // Fix double escaped quotes that are already properly escaped
    $field = preg_replace('/\\\\"\\\\"/', '""', $field);
    
    // Convert remaining escaped quotes to regular quotes
    // fputcsv will handle proper CSV escaping
    $field = str_replace('\\"', '"', $field);
    
    // Handle edge case of literal backslash before quote
    // Preserve intentional \" patterns
    $field = preg_replace('/\\\\\\\\(")/', '\\\\"$1', $field);
    
    return $field;
}

function clean_csv_field($field) {
    if (null === $field || '' === $field) {
        return '';
    }
    
    $field = (string) $field;
    
    // Fix over-escaping issues from data preprocessing
    $field = normalize_backslashes($field);
    $field = normalize_quotes($field);
    
    return $field;
}

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
    ],
    'real_problem_case_1' => [
        'input' => '移動スーパー【とくし丸】の車両を所有し、地域のスーパーマーケットが取り扱う生鮮食品や生活雑貨等の移動販売を行っていただきます。買い物にお困りの方（＝買い物難民）が非常に多く、社会課題となっております。そんな方々のもとを軽トラックで一軒一軒訪問し、自分の目で見て買い物をする機会を提供する仕事です。拠点となる母店の協力で、仕入れは「0（ゼロ）」。いわば「販売代行」を行っていただく、という仕組みです。',
        'expected_issues' => ['unmatched quotes']
    ]
];

echo "Testing clean_csv_field method (new design):\n";
echo str_repeat("=", 50) . "\n\n";

$success_count = 0;
$total_tests = count($test_cases);

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
    
    $input_issues = [];
    if ($backslash_count > 5) {
        $input_issues[] = 'excessive backslashes';
        echo "  ⚠️  Excessive backslashes detected\n";
    }
    if ($quote_count % 2 !== 0) {
        $input_issues[] = 'unmatched quotes';
        echo "  ⚠️  Unmatched quotes detected\n";
    }
    
    // Apply the fix
    $fixed = clean_csv_field($input);
    
    echo "\nAfter fix:\n";
    echo "Output length: " . strlen($fixed) . " chars\n";
    echo "Output preview: " . substr($fixed, 0, 100) . (strlen($fixed) > 100 ? '...' : '') . "\n";
    
    // Analyze output
    $fixed_backslash_count = substr_count($fixed, '\\');
    $fixed_quote_count = substr_count($fixed, '"');
    
    echo "Output analysis:\n";
    echo "  Backslashes: $fixed_backslash_count (reduced by " . ($backslash_count - $fixed_backslash_count) . ")\n";
    echo "  Quotes: $fixed_quote_count\n";
    
    $output_issues = [];
    if ($fixed_quote_count % 2 === 0) {
        echo "  ✅ Quotes are now balanced\n";
    } else {
        $output_issues[] = 'unmatched quotes';
        echo "  ❌ Quotes still unbalanced\n";
    }
    
    if ($fixed_backslash_count <= 2) {
        echo "  ✅ Backslashes normalized\n";
    } else {
        echo "  ⚠️  Still has many backslashes\n";
    }
    
    // Check if issues were resolved
    $resolved_issues = array_diff($input_issues, $output_issues);
    $remaining_issues = array_intersect($input_issues, $output_issues);
    
    if (empty($remaining_issues)) {
        echo "  ✅ All issues resolved!\n";
        $success_count++;
    } else {
        echo "  ⚠️  Remaining issues: " . implode(', ', $remaining_issues) . "\n";
    }
    
    echo "\n";
}

echo "=== Summary ===\n";
echo "Tests passed: $success_count/$total_tests\n";
echo "Success rate: " . round(($success_count / $total_tests) * 100, 1) . "%\n\n";

// Test CSV generation simulation
echo "Testing CSV generation simulation:\n";
echo str_repeat("=", 50) . "\n";

function simulate_csv_row($id, $title, $content, $excerpt, $status, $slug, $date) {
    // Apply the clean_csv_field to content fields
    $title = clean_csv_field($title);
    $content = clean_csv_field($content);
    $excerpt = clean_csv_field($excerpt);
    
    $row = [$id, $title, $content, $excerpt, $status, $slug, $date];
    
    // Simulate fputcsv behavior
    $output = fopen('php://temp', 'r+');
    fputcsv($output, $row);
    rewind($output);
    $csv_line = stream_get_contents($output);
    fclose($output);
    
    return trim($csv_line);
}

// Test with problematic data
$test_rows = [
    [
        'id' => 87842,
        'title' => '移動スーパー「とくし丸」ルートセールス（個人事業主）※未経験歓迎！',
        'content' => '移動スーパー【とくし丸」の車両を所有し、地域のスーパーマーケットが取り扱う生鮮食品や生活雑貨等の移動販売を行っていただきます。買い物にお困りの方（＝買い物難民）が非常に多く、社会課題となっております。',
        'excerpt' => '',
        'status' => 'publish',
        'slug' => 'mobile-supermarket-route-sales',
        'date' => '2023-11-27 10:42:56'
    ],
    [
        'id' => 9695,
        'title' => '日本語研究員',
        'content' => 'Bright and inquisitive telephone researchers required. A \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\"can-do\\\\\\\\\\\\\\\\\\\\\\\\\\\" attitude and positive outlook.',
        'excerpt' => '',
        'status' => 'publish',
        'slug' => 'japanese-researcher',
        'date' => '2011-03-22 21:25:57'
    ]
];

echo "Generated CSV rows:\n";
foreach ($test_rows as $i => $row) {
    $csv_line = simulate_csv_row($row['id'], $row['title'], $row['content'], $row['excerpt'], $row['status'], $row['slug'], $row['date']);
    $fields = str_getcsv($csv_line);
    
    echo "Row " . ($i + 1) . ": " . count($fields) . " fields\n";
    
    // Check content field (index 2)
    if (isset($fields[2])) {
        $content = $fields[2];
        $backslash_count = substr_count($content, '\\');
        $quote_count = substr_count($content, '"');
        
        echo "  Content: $backslash_count backslashes, $quote_count quotes\n";
        
        if ($quote_count % 2 === 0) {
            echo "  ✅ Quotes balanced\n";
        } else {
            echo "  ❌ Quotes unbalanced\n";
        }
    }
    
    echo "  CSV: " . substr($csv_line, 0, 150) . (strlen($csv_line) > 150 ? '...' : '') . "\n\n";
}

echo "=== Test completed ===\n";
