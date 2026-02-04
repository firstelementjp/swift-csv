<?php
/**
 * CSV Fix Verification Script
 * 
 * @since 0.9.5
 */

$inputFile = '/Users/fe_miyazawa/Downloads/d706e9c1-9178-4ee5-b809-7c3fbed4a198.csv';
$fixedFile = '/Users/fe_miyazawa/Downloads/d706e9c1-9178-4ee5-b809-7c3fbed4a198-fixed.csv';

// Problematic row IDs to check
$problematicRows = [87842, 87843, 87844, 87845, 87850, 87851, 87852, 87853, 87854];

echo "=== CSV Fix Verification ===\n\n";

foreach ($problematicRows as $rowId) {
    echo "Checking Row ID: $rowId\n";
    echo str_repeat("-", 50) . "\n";
    
    // Find the line in original file
    $originalHandle = fopen($inputFile, 'r');
    $originalLine = null;
    $originalLineNum = 0;
    
    while (($line = fgets($originalHandle)) !== false) {
        $originalLineNum++;
        if (preg_match('/^' . $rowId . '/', $line)) {
            $originalLine = trim($line);
            break;
        }
    }
    fclose($originalHandle);
    
    // Find the line in fixed file
    $fixedHandle = fopen($fixedFile, 'r');
    $fixedLine = null;
    $fixedLineNum = 0;
    
    while (($line = fgets($fixedHandle)) !== false) {
        $fixedLineNum++;
        if (preg_match('/^' . $rowId . '/', $line)) {
            $fixedLine = trim($line);
            break;
        }
    }
    fclose($fixedHandle);
    
    if ($originalLine && $fixedLine) {
        $originalFields = str_getcsv($originalLine);
        $fixedFields = str_getcsv($fixedLine);
        
        echo "Original line: $originalLineNum (" . count($originalFields) . " fields)\n";
        echo "Fixed line:    $fixedLineNum (" . count($fixedFields) . " fields)\n\n";
        
        echo "Field comparison:\n";
        $maxFields = max(count($originalFields), count($fixedFields));
        
        for ($i = 0; $i < $maxFields; $i++) {
            $orig = isset($originalFields[$i]) ? $originalFields[$i] : '[MISSING]';
            $fixed = isset($fixedFields[$i]) ? $fixedFields[$i] : '[MISSING]';
            
            // Show first 50 chars to avoid too long output
            $origDisplay = strlen($orig) > 50 ? substr($orig, 0, 50) . '...' : $orig;
            $fixedDisplay = strlen($fixed) > 50 ? substr($fixed, 0, 50) . '...' : $fixed;
            
            $status = ($orig === $fixed) ? '✓' : '✗';
            echo sprintf("  [%2d] %s %-50s | %-50s\n", $i + 1, $status, $origDisplay, $fixedDisplay);
        }
        
        // Focus on the area around "publish" (field 5)
        if (count($originalFields) >= 5 && count($fixedFields) >= 5) {
            echo "\nFocus on fields around 'publish':\n";
            for ($i = 3; $i <= 6; $i++) {
                if (isset($originalFields[$i]) && isset($fixedFields[$i])) {
                    echo "  Field " . ($i + 1) . ": '{$originalFields[$i]}' -> '{$fixedFields[$i]}'\n";
                }
            }
        }
        
        echo "\n";
    } else {
        echo "Could not find row $rowId in one of the files\n\n";
    }
}

echo "Verification completed.\n";
