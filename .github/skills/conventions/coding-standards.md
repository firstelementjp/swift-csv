# Coding Standards

## PHP

### WordPress Coding Standards (WPCS)

- Spaces inside parentheses: `if ( $condition ) {`
- Yoda notation **required** for `true`/`false`/`null`: `if ( true === $var )`
- Yoda notation **optional** for strings/numbers: `if ( $scope === 'custom' )` is acceptable
- Use `wp_verify_nonce()` for all AJAX handlers
- Use `current_user_can()` for permission checks
- Sanitize all input: `sanitize_text_field()`, `wp_unslash()`, `absint()`

### PHPDoc

All classes, functions, and methods must have PHPDoc blocks:

```php
/**
 * Process export chunk
 *
 * @since  0.9.3
 * @param  int    $start_row Starting row offset.
 * @param  string $post_type Post type to export.
 * @return array  Export result with data and continuation flag.
 */
```

### AJAX Response Format

```php
// Success
wp_send_json_success([
    'processed' => $count,
    'total'     => $total,
    'continue'  => $has_more,
]);

// Error
wp_send_json_error([
    'message' => __( 'Error description', 'swift-csv' ),
]);
```

## JavaScript

### Module Pattern

Each JS file exports via `window.*` global:

```javascript
// At end of file
window.SwiftCSVExport = {
    handleAjaxExport,
    // ...
};
```

### Comments

- Code comments must be in **English** (user rule)
- Use JSDoc for function documentation

### DOM Selectors

- Use specific child selectors: `#export-log-content`, not `.swift-csv-log`
- Always null-check: `if (!element) return;`
- Use `document.querySelector()` for single elements, `querySelectorAll()` for collections

### Event Listeners

- Remove previous listeners before adding new ones to prevent accumulation
- Use `{ once: true }` for one-shot handlers

## CSS

- File: `assets/css/swift-csv-style.css`
- BEM-like naming: `.swift-csv-{component}`, `.swift-csv-{component}-{element}`
- No `!important` unless overriding WordPress admin styles

## General

- **Language**: Response output always in Japanese (user rule)
- **Code comments**: Always in English (user rule)
- **No emojis** in code unless explicitly requested
