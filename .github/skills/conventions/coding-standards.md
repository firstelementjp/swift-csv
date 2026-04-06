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
 * @since  0.9.8
 * @param  int    $start_row Starting row offset.
 * @param  string $post_type Post type to export.
 * @return array  Export result with data and continuation flag.
 */
```

### Object Types for IDE Compatibility

Use `object` in PHPDoc for IDE compatibility while maintaining strict type hints:

```php
/**
 * @param object $util Utility instance.
 * @return object Processed result.
 */
public function method( Specific_Class $util ): Specific_Class {
    // Implementation
}
```

### Interface-Based Architecture

When implementing interfaces, follow proper inheritance:

```php
class Swift_CSV_Import_Taxonomy_Writer_WP implements Swift_CSV_Import_Taxonomy_Writer_Interface {
    public function apply_taxonomies_for_post(
        wpdb $wpdb,
        int $post_id,
        array $taxonomies,
        array $context,
        array &$dry_run_log
    ): void {
        // Implementation
    }
}
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

### Error Handling

Always include proper error handling and cleanup:

```php
try {
    $result = $this->process_data( $data );
} catch ( Exception $e ) {
    if ( $file_path && file_exists( $file_path ) ) {
        wp_delete_file( $file_path );
    }
    wp_send_json_error( [ 'message' => $e->getMessage() ] );
}
```

## JavaScript

### Module Pattern

Browser-facing JS integrates via `window.*` globals, even though assets are built with `esbuild` for distribution:

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

### Modular Structure

The JS is organized into modules:

```javascript
// Core utilities
window.SwiftCSVCore = {
	wpPost,
	addLogEntry,
	clearLog,
};

// Export functionality
window.SwiftCSVExport = {
	init,
};

// Import functionality
window.SwiftCSVImport = {
	init,
};

// License functionality
window.SwiftCSVLicense = {
	init,
};
```

## CSS

- File: `assets/css/swift-csv-style.css`
- BEM-like naming: `.swift-csv-{component}`, `.swift-csv-{component}-{element}`
- No `!important` unless overriding WordPress admin styles

### Two-Column Layout

```css
.swift-csv-layout {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
}

.swift-csv-settings {
	/* Left column - forms */
}

.swift-csv-log {
	/* Right column - progress and logs */
}
```

## General

- **Language**: Base language is English with Japanese translation via po/mo files
- **Code comments**: Always in English (user rule)
- **No emojis** in code unless explicitly requested
- **PHP version**: Target PHP 8.1+ with modern features where appropriate
- **WordPress version**: Target WordPress 6.6+

## Build Process

```bash
# Build all assets
npm run build

# Watch mode for development
npm run dev

# PHP code style
composer phpcs
composer phpcbf

# JS lint
npm run lint:js

# Local release validation
./test-release.sh
```

After any JS/CSS changes, always rebuild minified assets for distribution and verify that build artifacts do not leave the working tree dirty unintentionally.
