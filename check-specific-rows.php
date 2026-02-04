<?php
/**
 * Check Specific Problematic Rows
 * 
 * @since 0.9.5
 */

$inputFile = '/Users/fe_miyazawa/Downloads/d706e9c1-9178-4ee5-b809-7c3fbed4a198.csv';

// Specific rows to check
$checkRows = [10388, 11941, 13727, 20014, 29302, 31371, 31817, 31818, 32450, 33511, 33883];

// Range 34530-34540
for ($i = 34530; $i <= 34540; $i++) {
    $checkRows[] = $i;
}
$checkRows[] = 34542;
$checkRows[] = 34543;

echo "=== Specific Problematic Rows Analysis ===\n\n";

$totalIssues = 0;
$backslashIssues = 0;
$quoteIssues = 0;

foreach ($checkRows as $rowId) {
    echo "Checking Row ID: $rowId\n";
    echo str_repeat("-", 50) . "\n";
    
    // Find the line
    $handle = fopen($inputFile, 'r');
    $line = null;
    $lineNum = 0;
    
    while (($readLine = fgets($handle)) !== false) {
        $lineNum++;
        if (preg_match('/^' . $rowId . '/', $readLine)) {
            $line = trim($readLine);
            break;
        }
    }
    fclose($handle);
    
    if ($line) {
        $fields = str_getcsv($line);
        $hasIssues = false;
        
        echo "Line: $lineNum (" . count($fields) . " fields)\n";
        
        // Check content field (field 3) for issues
        if (isset($fields[2])) {
            $content = $fields[2];
            $length = strlen($content);
            $backslashCount = substr_count($content, '\\');
            $quoteCount = substr_count($content, '"');
            
            // Issues detection
            $issues = [];
            
            // Excessive backslashes
            if ($backslashCount > 5) {
                $issues[] = "Excessive backslashes: $backslashCount";
                $backslashIssues++;
            }
            
            // Unmatched quotes
            if ($quoteCount % 2 !== 0) {
                $issues[] = "Unmatched quotes: $quoteCount (odd)";
                $quoteIssues++;
            }
            
            // Very long content
            if ($length > 1500) {
                $issues[] = "Very long: $length chars";
            }
            
            if (!empty($issues)) {
                $hasIssues = true;
                $totalIssues++;
                echo "  CONTENT FIELD ISSUES: " . implode(', ', $issues) . "\n";
                
                // Show backslash patterns
                if ($backslashCount > 0) {
                    echo "  Backslash patterns found:\n";
                    $patterns = [];
                    if (strpos($content, '\\\\') !== false) $patterns[] = 'Double backslash';
                    if (strpos($content, '\\\\"') !== false) $patterns[] = 'Backslash + quote';
                    if (strpos($content, '\\\\\\\\') !== false) $patterns[] = 'Quad backslash';
                    if (preg_match('/\\\\{5,}/', $content)) $patterns[] = '5+ consecutive backslashes';
                    
                    if (!empty($patterns)) {
                        echo "    " . implode(', ', $patterns) . "\n";
                    }
                    
                    // Show sample of problematic area
                    $sample = preg_replace('/\\\\{2,}/', '[BACKSLASHES]', $content);
                    $sample = substr($sample, 0, 200) . (strlen($sample) > 200 ? '...' : '');
                    echo "    Sample: $sample\n";
                }
                
                // Show quote patterns
                if ($quoteCount > 0) {
                    echo "  Quote analysis:\n";
                    $escapedQuotes = substr_count($content, '\\"');
                    $doubleQuotes = substr_count($content, '""');
                    $rawQuotes = $quoteCount - $escapedQuotes - ($doubleQuotes * 2);
                    
                    echo "    Total quotes: $quoteCount\n";
                    echo "    Escaped (\\\" ): $escapedQuotes\n";
                    echo "    Double escaped: $doubleQuotes\n";
                    echo "    Raw quotes: $rawQuotes\n";
                }
            }
        }
        
        if (!$hasIssues) {
            echo "  No issues detected\n";
        }
        
        echo "\n";
    } else {
        echo "Could not find row $rowId\n\n";
    }
}

echo "=== Summary ===\n";
echo "Total problematic rows: $totalIssues\n";
echo "Backslash issues: $backslashIssues\n";
echo "Quote issues: $quoteIssues\n\n";

echo "=== Analysis ===\n";
echo "Based on the patterns found, the issues appear to be:\n";
echo "1. Over-escaping during data processing/preprocessing\n";
echo "2. Multiple layers of backslash escaping\n";
echo "3. Inconsistent quote escaping\n\n";

echo "Recommended fix approach:\n";
echo "1. Normalize backslashes during export\n";
echo "2. Ensure proper CSV quote escaping\n";
echo "3. Handle edge cases for intentional backslashes\n";
