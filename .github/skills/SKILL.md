# SKILL.md - Troubleshooting Knowledge Base

> This file documents recurring pitfalls in this repo and the exact fix patterns to apply.
> Append new entries as issues are discovered and resolved.
> Each entry follows the format: Symptom → Cause → Fix → Lesson.

---

## #001 ACF Field Export Issue (2026-02-06)

**Symptom**: All `acf_*` columns are empty in exported CSV. `tax_*` columns work fine.

**Cause**: `get_field_object()` was called without `$post_id`, so it returned `false`.

**Fix** (`class-swift-csv-ajax-export.php`):

```php
// BEFORE — field object is always false
$acf_field_cache[$field_name] = get_field_object( $field_name );

// AFTER — pass post context
$acf_field_cache[$field_name] = get_field_object( $field_name, $post_id, false, false );

// Also add fallback for edge cases
if ( ! $field_object ) {
    $value = get_field( $field_name, $post_id );
}
```

**Lesson**: ACF functions almost always require `$post_id`. Never call them without context.

**Debug approach**:

```php
error_log( 'Processing ACF field: ' . $field_name . ' for post: ' . $post_id );
error_log( 'Field object: ' . print_r( $acf_field_cache[$field_name], true ) );
error_log( 'Value: ' . print_r( get_field( $field_name, $post_id ), true ) );
```

---

## #002 JavaScript Progress Element Not Found (2026-02-06)

**Symptom**: Console error `Progress element not found!` after UI refactor.

**Cause**: JS was looking for old `#swift-csv-ajax-export-progress` element, but the new UI uses `.swift-csv-progress`.

**Fix** (`admin-scripts.js`):

```javascript
// BEFORE — old selector, element doesn't exist
const progressContainer = document.querySelector('#swift-csv-ajax-export-progress');

// AFTER — use new class-based selector with guard
const container = document.querySelector('.swift-csv-progress');
if (!container) return;
const progressFill = container.querySelector('.progress-bar-fill');
```

**Lesson**: After any UI structure change, grep JS files for old selectors.

---

## #003 Export Filename Date Format Bug (2026-02-06)

**Symptom**: Filename shows `202602-06` instead of `2026-02-06` (missing hyphen between year and month).

**Cause**: String concatenation was missing `-` after `getFullYear()`.

**Fix** (`admin-scripts.js`):

```javascript
// BEFORE — missing hyphen
const dateStr = now.getFullYear() +
    String(now.getMonth() + 1).padStart(2, '0') + '-' + ...

// AFTER — added hyphen
const dateStr = now.getFullYear() + '-' +
    String(now.getMonth() + 1).padStart(2, '0') + '-' + ...
```

**Lesson**: Always test date formatting with actual output. Easy to miss in concatenation chains.

---

## #004 direnv Environment Setup Issues (2026-02-07)

**Symptom**: WP-CLI cannot connect to database, or environment variables not working properly.

**Cause**: Two common issues with `.envrc` configuration:

1. Database variables with double quotes cause WP-CLI connection failures
2. Paths with spaces break WP-CLI commands

**Fix** (`.envrc`):

```bash
# BEFORE — problematic
export DB_HOST="localhost"        # ❌ Double quotes break WP-CLI
export DB_NAME="local"           # ❌ Same issue
export WP_PATH=/Users/name/Local Sites/app  # ❌ Spaces break command

# AFTER — working configuration
export DB_HOST=localhost         # ✅ Unquoted for database vars
export DB_NAME=local            # ✅ Unquoted for database vars
export DB_USER=root              # ✅ Unquoted for database vars
export DB_PASSWORD=root          # ✅ Unquoted for database vars
export WP_PATH="/Users/name/Local Sites/app"  # ✅ Quoted for spaces
```

**Test commands**:

```bash
# Test database connection
wp db query "SELECT 1;" --path="$WP_PATH"

# Test environment variables
echo "DB_HOST: $DB_HOST"
echo "WP_PATH: $WP_PATH"
```

**Lesson**:

- Database variables: Use unquoted values (`export DB_HOST=localhost`)
- Paths with spaces: Use quoted values (`export WP_PATH="/path with spaces"`)
- Always test WP-CLI connection after setup

**Debug approach**:

```bash
# Check if direnv is loaded
direnv status

# Check exported variables
env | grep -E "(DB_|WP_)"

# Test WP-CLI with verbose output
wp db query "SELECT 1;" --path="$WP_PATH" --debug
```

---

## #005 WordPress AJAX Success Flag Missing (2026-02-08)

**Symptom**: AJAX request succeeds but JavaScript throws error after 2 seconds. Console shows "Import failed" or similar error, despite server processing completing successfully.

**Cause**: WordPress AJAX responses must include `success: true` flag. JavaScript checks `if (!data.success)` and throws error when flag is missing.

**Fix** (`class-swift-csv-ajax-import.php`):

```php
// BEFORE — Missing success flag, JavaScript treats as error
wp_send_json([
    'processed' => $next_row,
    'total' => $total_rows,
    'continue' => $continue,
    'message' => "Processed $processed rows, $errors errors"
]);

// AFTER — Include success flag for WordPress AJAX compatibility
wp_send_json([
    'success' => true,        // ← REQUIRED for WordPress AJAX
    'processed' => $next_row,
    'total' => $total_rows,
    'continue' => $continue,
    'message' => "Processed $processed rows, $errors errors"
]);
```

**Alternative (WordPress standard)**:

```php
// Use WordPress standard functions when possible
wp_send_json_success($data);     // Automatically adds success: true
wp_send_json_error($message);    // Automatically adds success: false
```

**Lesson**: WordPress AJAX has strict response format. Always include `success` flag in JSON responses. This is WordPress-specific convention, not general JSON standard.

**Debug approach**:

```javascript
// Check actual response in browser Network tab
.then(data => {
    console.log('Response:', data);  // Look for success flag
    if (!data.success) {
        console.error('Missing success flag in response');
        throw new Error(data.data || 'Operation failed');
    }
})
```

**Related patterns**:

- Always validate nonce: `wp_verify_nonce($_POST['nonce'], 'action')`
- Use consistent nonce key between frontend (`nonce`) and backend
- Handle file uploads directly in import handler, not via file paths

---

## #006 WordPress Yoda Conditions Coding Standard (2026-02-09)

**Symptom**: Inconsistent conditional formatting across codebase. Some conditions use Yoda notation, others don't.

**Cause**: Misunderstanding of WordPress coding standards for Yoda conditions.

**Fix** (WordPress Coding Standards):

```php
// ✅ REQUIRED - Yoda notation for true/false/null comparisons
if ( true === $some_condition ) {
    // Process
}

if ( false === $some_condition ) {
    // Process
}

if ( null === $some_variable ) {
    // Process
}

if ( 0 === $some_number ) {
    // Process
}

// ✅ OPTIONAL - Regular notation is acceptable for other comparisons
if ( $export_scope === 'custom' ) {  // String comparison
    // Process
}

if ( $count > 0 ) {  // Numeric comparison
    // Process
}

if ( isset($variable) ) {  // Function call
    // Process
}
```

**Project Standard**:

- **Mandatory**: Use Yoda notation for `true`/`false`/`null` comparisons
- **Recommended**: Use readable format for string/number/function comparisons
- **Consistency**: Follow existing code patterns within the project

**Lesson**: WordPress coding standards require Yoda notation only for boolean/null comparisons, not all comparisons. Focus on readability and consistency.

**Debug approach**:

```php
// Check for proper boolean comparisons
grep -r "if.*===.*true\|false\|null" includes/

// Verify consistent patterns
grep -r "if.*===.*'" includes/
```

**Related patterns**:

- Use `wp_verify_nonce()` for security checks
- Use `current_user_can()` for permission checks
- Use `defined()` for constant checks

---

## #007 Conditional Hook Execution Anti-Pattern (2026-02-09)

**Symptom**: Hooks only execute under specific conditions, making debugging difficult and behavior unpredictable.

**Cause**: Placing `apply_filters()` inside conditional statements instead of executing hooks consistently.

**Fix** (WordPress Best Practice):

```php
// ❌ BEFORE - Conditional hook execution
if ( $export_scope === 'custom' ) {
    $custom_headers = apply_filters( 'swift_csv_export_columns', [], $post_type, $include_private_meta );
    if ( ! empty( $custom_headers ) ) {
        return $custom_headers;
    }
}

// ✅ AFTER - Consistent hook execution with parameters
$custom_headers = apply_filters( 'swift_csv_export_columns', [], $post_type, $export_scope, $include_private_meta );

if ( is_array( $custom_headers ) && ! empty( $custom_headers ) ) {
    return $custom_headers;
}

// Hook implementation handles conditions internally
add_filter( 'swift_csv_export_columns', function( $headers, $post_type, $export_scope, $include_private_meta ) {
    if ( 'custom' === $export_scope ) {  // Condition logic inside hook
        return ['custom_field_1', 'custom_field_2'];
    }
    return $headers;  // Return empty for other scopes
}, 10, 4 );
```

**Benefits of Consistent Hook Execution**:

- **Predictability**: Hooks always execute when expected
- **Debuggability**: Easy to trace hook execution
- **Flexibility**: Developers can implement complex conditional logic
- **Testing**: Hooks can be tested independently of conditions

**Lesson**: Always execute hooks consistently and pass context as parameters. Let hook implementations handle their own conditional logic.

**Debug approach**:

```php
// Add logging to verify hook execution
add_filter( 'swift_csv_export_columns', function( $headers, $post_type, $export_scope, $include_private_meta ) {
    error_log( "Hook executed: scope={$export_scope}, post_type={$post_type}" );
    return $headers;
}, 10, 4 );
```

**Related patterns**:

- Pass all relevant context as hook parameters
- Use priority and argument count for hook flexibility
- Document hook behavior and expected return values

---

## Quick Reference Table

| #   | Symptom                    | Root Cause                                   | Key File                          |
| --- | -------------------------- | -------------------------------------------- | --------------------------------- |
| 001 | Empty ACF columns          | Missing `$post_id` in `get_field_object()`   | `class-swift-csv-ajax-export.php` |
| 002 | Progress element not found | Old DOM selector after UI refactor           | `admin-scripts.js`                |
| 003 | Malformed date in filename | Missing hyphen in string concat              | `admin-scripts.js`                |
| 004 | WP-CLI connection fails    | Database variables quoted, paths unquoted    | `.envrc`                          |
| 005 | AJAX error after success   | Missing `success: true` in JSON response     | `class-swift-csv-ajax-import.php` |
| 006 | Inconsistent conditionals  | Misunderstanding WordPress Yoda notation     | Multiple files                    |
| 007 | Conditional hook execution | Hooks only execute under specific conditions | `class-swift-csv-ajax-export.php` |

---

<!-- Add new entries above this line. Use format: ## #NNN Title (YYYY-MM-DD) -->
