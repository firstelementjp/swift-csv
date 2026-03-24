# Developer Guide

This guide is the public entry point for developers who want to understand or extend Swift CSV.

It complements the following documents:

- [Architecture](architecture.md)
- [Developer Hooks](hooks.md)
- [Configuration](config.md)
- [Contributing](contribute.md)
- [Troubleshooting](help.md)

## Overview

Swift CSV is a WordPress plugin for CSV import and export with support for:

- Custom post types
- Taxonomies
- Custom fields
- Chunked processing for large datasets
- Free and Pro extension points through WordPress hooks

### Current Baseline

- **WordPress**: 6.6 or higher
- **PHP**: 8.1 or higher
- **JavaScript**: Vanilla JavaScript, modular structure
- **Build**: esbuild-based asset bundling and minification
- **Localization**: English source strings with Japanese translations

## Repository Layout

```text
swift-csv/
├── assets/
│   ├── css/                 # Admin styles
│   └── js/                  # Import/export/admin scripts
├── docs/                    # Docs site content
├── includes/                # PHP classes and runtime logic
├── languages/               # Translation files
├── tests/                   # PHPUnit tests
├── swift-csv.php            # Plugin bootstrap
└── uninstall.php            # Cleanup on uninstall
```

### Key Areas

- **`includes/admin/`**
    - Admin page registration
    - Asset loading
    - License and updater integration

- **`includes/export/`**
    - Export request handling
    - Batch processing
    - CSV row generation
    - Export logging and download flow

- **`includes/import/`**
    - Request parsing
    - CSV parsing
    - Row context creation
    - Row processing and persistence
    - Taxonomy resolution and writing

- **`assets/js/`**
    - Import and export UI behavior
    - AJAX request orchestration
    - Progress display and log updates

## Core Components

### Export System

The export system builds CSV output in batches to avoid timeouts and memory spikes.

Typical responsibilities include:

- Building export headers from post fields, taxonomies, and custom fields
- Discovering meta keys from sample content
- Executing chunked export requests
- Streaming progress and logs back to the admin UI
- Preparing the final download file

### Import System

The import system processes uploaded CSV data in batches and maps rows into WordPress content.

Typical responsibilities include:

- Parsing request parameters and uploaded files
- Validating CSV headers
- Building a row context for each record
- Determining whether a row creates or updates a post
- Persisting post fields, meta, and taxonomy data
- Returning progress and error information to the UI

### Hook-Based Extensibility

Swift CSV is designed so that Free and Pro functionality can coexist without tightly coupling implementations.

Common extension patterns include:

- Modifying export headers
- Adjusting export queries
- Transforming import row data
- Custom taxonomy resolution
- Post-processing import and export results

See [Developer Hooks](hooks.md) for the authoritative hook reference.

## Development Rules

### AJAX Responses

WordPress AJAX responses must clearly indicate success or failure.

Use:

- `wp_send_json_success()`
- `wp_send_json_error()`

When changing AJAX flows, confirm that frontend code still receives the expected response structure.

### Batch-Safe Design

Both import and export are designed for chunked processing.

When making changes:

- Avoid assumptions that all rows or posts are processed in one request
- Preserve resumable state between requests
- Keep progress counters and logs consistent across batches

### UI and DOM Safety

The admin UI depends on specific containers for logs, progress, and status updates.

When refactoring JavaScript:

- Target specific elements instead of clearing broad parent containers
- Preserve log containers and progress elements
- Recheck selectors after UI changes

### Temporary File Cleanup

Import flows rely on temporary uploaded files.

When editing import behavior:

- Clean up temporary files on success
- Clean up temporary files on error paths
- Avoid leaving stale files behind after interrupted processing

### Localization

User-facing strings should be written in English and localized through WordPress translation functions and translation files.

## Build and Test Commands

```bash
composer test
composer phpcs
composer phpcbf
npm run build
npm run dev
```

Use the commands that match the area you changed:

- PHP changes: run coding standards and relevant tests
- JS/CSS changes: rebuild assets
- Documentation changes: verify links and page structure

## Common Pitfalls

### Missing AJAX Success Flag

A response that looks correct can still break the UI if the expected success shape is missing.

### Broken DOM Selectors After Refactors

UI regressions often come from selectors no longer matching the rendered markup.

### Incomplete Cleanup on Error Paths

Import processing can leave files or state behind if error paths are not handled fully.

### Hook Name Drift

If Free and Pro diverge on hook or method names, extension behavior can silently break.

## Recommended Reading Order

If you are new to the codebase:

1. Read [Architecture](architecture.md)
2. Review [Developer Hooks](hooks.md)
3. Check [Contributing](contribute.md)
4. Use [Troubleshooting](help.md) when debugging behavior

## Related Documentation

- [Architecture](architecture.md)
- [Developer Hooks](hooks.md)
- [Configuration](config.md)
- [Contributing](contribute.md)
- [Troubleshooting](help.md)
