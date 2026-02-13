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

## #005 Enhanced Debug Configuration (2026-02-07)

**Symptom**: Need better debugging capabilities without affecting production or team repository.

**Cause**: Standard WordPress debug.log is shared across all plugins and lacks detailed context.

**Fix**: Create conditional debug.php with enhanced logging:

1. **Create debug.php** (add to .gitignore):

```php
<?php
// Custom debug functions with detailed context
function swift_csv_debug_log( $message, $level = 'INFO' ) {
    $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
    $caller = isset( $trace[1]['function'] ) ? $trace[1]['function'] : 'unknown';
    $file = isset( $trace[1]['file'] ) ? basename( $trace[1]['file'] ) : 'unknown';
    $line = isset( $trace[1]['line'] ) ? $trace[1]['line'] : '0';

    $log_entry = "[{$timestamp}] [SWIFT-CSV] [{$level}] [{$caller}:{$file}:{$line}] {$message}\n";
    error_log( $log_entry, 3, plugin_dir_path( __FILE__ ) . 'debug.log' );
}

// ACF-specific debugging
function swift_csv_debug_acf_field( $field_name, $post_id, $value = null ) {
    // Detailed ACF field analysis
}
```

2. **Load conditionally** (in swift-csv.php):

```php
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( __DIR__ . '/debug.php' ) ) {
    require_once __DIR__ . '/debug.php';
}
```

3. **Add to .gitignore**:

```
debug.php
```

**Usage examples**:

```php
// Replace basic error_log
swift_csv_debug_log( 'Processing ACF field: ' . $field_name, 'DEBUG' );

// ACF-specific debugging
swift_csv_debug_acf_field( $field_name, $post_id, $value );

// Database query debugging
swift_csv_debug_db_query( $sql, $params, $result );
```

**Lesson**:

- Use conditional loading for development-only features
- Create context-aware logging functions
- Exclude debug configurations from repository
- Provide specialized debugging functions for common patterns

**Debug approach**:

```bash
# Monitor debug log in real-time
tail -f debug.log

# Test debug functions
if ( function_exists( 'swift_csv_debug_log' ) ) {
    swift_csv_debug_log( 'Test message', 'INFO' );
}
```

---

## Quick Reference Table

| #   | Symptom                    | Root Cause                                 | Key File                          |
| --- | -------------------------- | ------------------------------------------ | --------------------------------- |
| 001 | Empty ACF columns          | Missing `$post_id` in `get_field_object()` | `class-swift-csv-ajax-export.php` |
| 002 | Progress element not found | Old DOM selector after UI refactor         | `admin-scripts.js`                |
| 003 | Malformed date in filename | Missing hyphen in string concat            | `admin-scripts.js`                |
| 004 | WP-CLI connection fails    | Database variables quoted, paths unquoted  | `.envrc`                          |
| 005 | Debug logging insufficient | Standard WordPress debug lacks context     | `debug.php`                       |

---

<!-- Add new entries above this line. Use format: ## #NNN Title (YYYY-MM-DD) -->
