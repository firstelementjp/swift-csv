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

## Quick Reference Table

| #   | Symptom                    | Root Cause                                 | Key File                          |
| --- | -------------------------- | ------------------------------------------ | --------------------------------- |
| 001 | Empty ACF columns          | Missing `$post_id` in `get_field_object()` | `class-swift-csv-ajax-export.php` |
| 002 | Progress element not found | Old DOM selector after UI refactor         | `admin-scripts.js`                |
| 003 | Malformed date in filename | Missing hyphen in string concat            | `admin-scripts.js`                |

---

<!-- Add new entries above this line. Use format: ## #NNN Title (YYYY-MM-DD) -->
