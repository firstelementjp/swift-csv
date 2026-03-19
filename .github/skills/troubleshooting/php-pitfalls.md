# PHP Pitfalls

## #001 IDE Static Analysis Warnings (2026-03-19)

**Symptom**: IDE shows static analysis warnings for PHPDoc @param/@return types with specific class names.

**Cause**: IDE cannot resolve class names in PHPDoc while method signatures use strict types.

**Fix** (All import classes):

```php
// BEFORE — Specific class names in PHPDoc
/**
 * @param Swift_CSV_Import_Persister $persister Persister utility.
 * @return Swift_CSV_Import_Row_Context
 */

// AFTER — Use object type in PHPDoc
/**
 * @param object $persister Persister utility.
 * @return object
 */
public function method( Swift_CSV_Import_Persister $persister ): Swift_CSV_Import_Row_Context {
    // Implementation
}
```

**Lesson**: Use `object` in PHPDoc for IDE compatibility while maintaining strict type hints in method signatures.

---

## #005 WordPress AJAX Success Flag Missing (2026-02-08)

**Symptom**: AJAX request succeeds but JavaScript throws error. Console shows "Import failed" despite server processing completing successfully.

**Cause**: WordPress AJAX responses must include `success: true` flag. JavaScript checks `if (!data.success)` and throws error when flag is missing.

**Fix** (AJAX handlers):

```php
// BEFORE — Missing success flag
wp_send_json([
    'processed' => $next_row,
    'total' => $total_rows,
]);

// AFTER — Include success flag
wp_send_json([
    'success' => true,
    'processed' => $next_row,
    'total' => $total_rows,
]);

// Or use WordPress standard functions
wp_send_json_success($data);
wp_send_json_error($message);
```

**Lesson**: Always include `success` flag in WordPress AJAX JSON responses. Prefer `wp_send_json_success()` / `wp_send_json_error()`.

---

## #006 Import File Cleanup Issues (2026-03-19)

**Symptom**: Temporary import files persist after errors or completion, causing disk space issues.

**Cause**: Missing cleanup on error paths or exception handling.

**Fix** (Import classes):

```php
// BEFORE — No cleanup on error
try {
    $result = $this->process_import( $file_path );
} catch ( Exception $e ) {
    wp_send_json_error( $e->getMessage() );
    // File not cleaned up!
}

// AFTER — Cleanup on all paths
try {
    $result = $this->process_import( $file_path );
} catch ( Exception $e ) {
    if ( $file_path && file_exists( $file_path ) ) {
        wp_delete_file( $file_path );
    }
    wp_send_json_error( $e->getMessage() );
}

// Always cleanup on completion
if ( $file_path && file_exists( $file_path ) ) {
    wp_delete_file( $file_path );
}
```

**Lesson**: Always include cleanup on ALL execution paths, not just success paths.
