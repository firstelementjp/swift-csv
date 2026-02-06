# AGENT.md - AI Assistant Guide for Swift CSV

> Read this file first when working on this project.
> For past bug fixes and troubleshooting knowledge, see `SKILL.md`.

## Project Overview

**Swift CSV** is a WordPress plugin for CSV import/export.
Supports custom post types, custom taxonomies, ACF fields, and custom fields.

- **Version**: 0.9.5
- **License**: GPL-2.0+
- **PHP**: >= 7.4
- **Repository**: https://github.com/firstelementjp/swift-csv

## Architecture

```
swift-csv.php                          # Entry point, constants, bootstrap
includes/
  class-swift-csv-admin.php            # Admin UI (menu, tabs, rendering)
  class-swift-csv-ajax-export.php      # AJAX export handler (chunked)
  class-swift-csv-ajax-import.php      # AJAX import handler (chunked)
  class-swift-csv-batch.php            # Batch processing engine
  class-swift-csv-exporter.php         # Core export logic
  class-swift-csv-importer.php         # Core import logic
  class-swift-csv-sql-importer.php     # SQL-based fast import
  class-swift-csv-updater.php          # Plugin update checker
assets/
  js/admin-scripts.js                  # Frontend JS (logging, AJAX, file upload)
  js/admin-scripts.min.js              # Minified JS (built by esbuild)
  css/admin-style.css                  # Admin CSS (two-column layout)
  css/admin-style.min.css              # Minified CSS (built by esbuild)
languages/                             # i18n files
docs/                                  # Docsify documentation site
```

## CSV Column Prefix Convention

| Prefix | Source              | Example        |
| ------ | ------------------- | -------------- |
| (none) | WP post fields      | `post_title`   |
| `tax_` | Taxonomy terms      | `tax_category` |
| `acf_` | ACF fields          | `acf_area`     |
| `cf_`  | Other custom fields | `cf_my_field`  |

## Build & Dev Commands

```bash
npm run build          # Build all minified assets
npm run dev            # Watch mode for JS + CSS
composer phpcs         # Run PHP CodeSniffer
composer phpcbf        # Auto-fix PHP style
npm run lint:js        # ESLint
npm run format         # Prettier
```

**After editing JS/CSS, always rebuild the minified files.**

## Coding Rules

### PHP

- Follow WordPress Coding Standards (WPCS)
- Add PHPDoc to all classes, functions, and methods
- Comments in English
- Use `sanitize_text_field()`, `intval()`, `check_ajax_referer()` for security
- Use `wp_send_json_success()` / `wp_send_json_error()` for AJAX responses

### JavaScript

- Add JSDoc to all functions
- Comments in English
- Use `addLogEntry(message, level, context)` for user-facing log output
- Always check DOM element existence before use
- Use `fetch()` for AJAX (no jQuery dependency)

### CSS

- Use `.swift-csv-` prefix for all custom classes
- Two-column layout: `.swift-csv-layout` > `.swift-csv-settings` + `.swift-csv-log`

## Critical Constraints

### ACF Integration

**Always pass `$post_id` to ACF functions.** This is the #1 source of bugs.

```php
// WRONG - returns false/null
get_field_object( $field_name );

// CORRECT - requires post context
get_field_object( $field_name, $post_id, false, false );
```

Always implement a fallback when field object is not found:

```php
if ( ! $field_object ) {
    $value = get_field( $field_name, $post_id );
}
```

See `SKILL.md > ACF Field Export Issue` for full details.

### JavaScript DOM Selectors

After any UI structure change, update all JS selectors. Always check existence:

```javascript
const container = document.querySelector('.swift-csv-progress');
if (!container) return;
```

### Export Filename Format

```
swiftcsv_export_{postType}_{YYYY-MM-DD_HH-mm-ss}.csv
```

Use local time, not UTC.

## UI Structure

- **Admin page**: Two-column layout per tab (Export / Import)
- **Left column**: Settings form
- **Right column**: Progress bar + real-time log + action area
- **Log levels**: `info`, `success`, `warning`, `error`, `debug`
- **Log containers**: `#export-log-content`, `#import-log-content`
- **Progress**: `.swift-csv-progress .progress-bar-fill`

## Workflow Checklists

### Adding a New ACF Field Type

1. Update `swift_csv_ajax_export_build_headers()` for header detection
2. Add value processing in the `acf_` branch of the export loop
3. Use `get_field_object($name, $post_id, false, false)` with fallback
4. Test with real data, check `debug.log`
5. Update `SKILL.md` if a new pattern is discovered

### Changing Admin UI

1. Edit PHP template in `class-swift-csv-admin.php`
2. Update CSS in `admin-style.css`
3. Update JS selectors in `admin-scripts.js`
4. Rebuild minified assets: `npm run build`
5. Test all element references in JS

### Debugging Export/Import

1. Add `error_log()` at the suspected point
2. Run the operation, check `debug.log`
3. Look for: field detection → value retrieval → CSV output
4. Fix root cause, not symptoms
5. Document the fix in `SKILL.md`

## Related Files

- `SKILL.md` — Past bug fixes and troubleshooting knowledge base
- `docs/` — User-facing documentation (Docsify)
- `README.md` — Project overview and setup instructions
- `phpcs.xml.dist` — PHP CodeSniffer configuration
