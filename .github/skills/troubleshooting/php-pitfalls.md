# PHP Pitfalls

## #001 ACF Field Export Issue (2026-02-06)

**Symptom**: All `acf_*` columns are empty in exported CSV. `tax_*` columns work fine.

**Cause**: `get_field_object()` was called without `$post_id`, so it returned `false`.

**Fix** (`class-swift-csv-ajax-export.php`):

```php
// BEFORE
$acf_field_cache[$field_name] = get_field_object( $field_name );

// AFTER
$acf_field_cache[$field_name] = get_field_object( $field_name, $post_id, false, false );

if ( ! $field_object ) {
    $value = get_field( $field_name, $post_id );
}
```

**Lesson**: ACF functions almost always require `$post_id`. Never call them without context.

---

## #005 WordPress AJAX Success Flag Missing (2026-02-08)

**Symptom**: AJAX request succeeds but JavaScript throws error. Console shows "Import failed" despite server processing completing successfully.

**Cause**: WordPress AJAX responses must include `success: true` flag. JavaScript checks `if (!data.success)` and throws error when flag is missing.

**Fix** (`class-swift-csv-ajax-import.php`):

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
