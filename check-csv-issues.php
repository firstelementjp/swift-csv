<?php
/**
 * CSV Issues Detection Script
 * 
 * @since 0.9.5
 */

$inputFile = '/Users/fe_miyazawa/Downloads/d706e9c1-9178-4ee5-b809-7c3fbed4a198.csv';

// Specific rows to check
$checkRows = [87842, 87843, 87844, 87845, 87850, 87851, 87852, 87853, 87854, 9695, 9696];

echo "=== CSV Issues Detection ===\n\n";

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
        
        echo "Line: $lineNum (" . count($fields) . " fields)\n";
        
        // Check each field for potential issues
        foreach ($fields as $index => $field) {
            $fieldNum = $index + 1;
            $length = strlen($field);
            
            // Check for potential issues
            $issues = [];
            
            // Unescaped quotes
            if (substr_count($field, '"') % 2 !== 0) {
                $issues[] = 'Unmatched quotes';
            }
            
            // Excessive backslashes
            if (substr_count($field, '\\') > 10) {
                $issues[] = 'Excessive backslashes: ' . substr_count($field, '\\');
            }
            
            // Newlines in field
            if (strpos($field, "\n") !== false || strpos($field, "\r") !== false) {
                $issues[] = 'Contains newlines';
            }
            
            // Very long fields
            if ($length > 1000) {
                $issues[] = 'Very long: ' . $length . ' chars';
            }
            
            // Empty fields that shouldn't be
            if ($fieldNum === 4 && empty($field)) {
                $issues[] = 'Empty excerpt field';
            }
            
            if (!empty($issues)) {
                echo "  Field $fieldNum: " . implode(', ', $issues) . "\n";
                echo "    Preview: " . substr($field, 0, 100) . (strlen($field) > 100 ? '...' : '') . "\n";
            }
        }
        
        // Check the raw line structure
        echo "\nRaw line analysis:\n";
        
        // Count commas outside quotes
        $commaCount = 0;
        $inQuotes = false;
        $escaped = false;
        
        for ($i = 0; $i < strlen($line); $i++) {
            $char = $line[$i];
            
            if ($escaped) {
                $escaped = false;
                continue;
            }
            
            if ($char === '\\') {
                $escaped = true;
                continue;
            }
            
            if ($char === '"') {
                $inQuotes = !$inQuotes;
                continue;
            }
            
            if ($char === ',' && !$inQuotes) {
                $commaCount++;
            }
        }
        
        echo "  Commas outside quotes: $commaCount (should be " . (count($fields) - 1) . ")\n";
        echo "  Quote balance: " . ($inQuotes ? 'UNBALANCED' : 'OK') . "\n";
        
        echo "\n";
    } else {
        echo "Could not find row $rowId\n\n";
    }
}

echo "Issues detection completed.\n";
