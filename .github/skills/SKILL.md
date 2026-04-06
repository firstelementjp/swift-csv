# SKILL.md — Swift CSV Development Guide

> Top-level overview for AI assistants and developers.
> Detailed documentation is in subdirectories — only load what you need.

## Project Overview

**Swift CSV** is a WordPress plugin for CSV import/export with full support for custom post types, taxonomies, and custom fields. It ships as a Free version with an optional Pro add-on.

| Item           | Value                                |
| -------------- | ------------------------------------ |
| **Type**       | WordPress Plugin (Free + Pro add-on) |
| **WordPress**  | 6.6+                                 |
| **PHP**        | 8.1+                                 |
| **JavaScript** | Vanilla JS, modular (no framework)   |
| **Build**      | esbuild for JS/CSS minification      |
| **i18n**       | English (default), Japanese          |

## Repository Layout

```
swift-csv/                          # Free version (this repo)
├── swift-csv.php                   # Plugin entry point and bootstrap constants
├── uninstall.php                   # Plugin uninstallation cleanup
├── includes/
│   ├── admin/
│   │   ├── class-swift-csv-admin.php            # Admin bootstrap/orchestration
│   │   ├── class-swift-csv-admin-assets.php     # Script/style enqueue and localized data
│   │   ├── class-swift-csv-admin-page.php       # Admin page rendering
│   │   ├── class-swift-csv-admin-settings.php   # Settings registration and persistence
│   │   ├── class-swift-csv-admin-ajax.php       # Admin-side AJAX endpoints
│   │   ├── class-swift-csv-admin-util.php       # Admin helper utilities
│   │   ├── class-swift-csv-encryption-utils.php # Encryption helpers for stored settings
│   │   ├── class-swift-csv-license-handler.php  # License validation/activation
│   │   ├── class-swift-csv-settings-helper.php  # Settings helper methods
│   │   └── class-swift-csv-updater.php          # Plugin update system
│   ├── export/
│   │   ├── class-swift-csv-ajax-export-unified.php          # Unified export entry point
│   │   ├── class-swift-csv-ajax-export-batch-planner.php    # Export batch planning
│   │   ├── class-swift-csv-ajax-export-handler-direct-sql.php # Direct SQL export handler
│   │   ├── class-swift-csv-ajax-export-handler-wp-compatible.php # WP compatible export handler
│   │   ├── class-swift-csv-export-base.php                  # Base export flow
│   │   ├── class-swift-csv-export-wp-compatible.php         # WP compatible export implementation
│   │   ├── class-swift-csv-export-direct-sql.php            # Direct SQL export implementation
│   │   ├── class-swift-csv-export-cancel-manager.php        # Export cancellation state
│   │   └── class-swift-csv-export-log-store.php             # Export log persistence
│   ├── import/
│   │   ├── class-swift-csv-ajax-import-unified.php             # Unified import entry point
│   │   ├── class-swift-csv-ajax-import-batch-planner.php       # Import batch planning
│   │   ├── class-swift-csv-ajax-import-handler-direct-sql.php  # Direct SQL import handler
│   │   ├── class-swift-csv-ajax-import-handler-wp-compatible.php # WP compatible import handler
│   │   ├── class-swift-csv-import-base.php                     # Shared import orchestration
│   │   ├── class-swift-csv-import-wp-compatible.php            # WP compatible import implementation
│   │   ├── class-swift-csv-import-direct-sql.php               # Direct SQL import implementation
│   │   ├── class-swift-csv-import-batch-processor.php          # Main batch execution pipeline
│   │   ├── class-swift-csv-import-batch-processor-base.php     # Shared batch processor helpers
│   │   ├── class-swift-csv-import-cancel-manager.php           # Import cancellation state
│   │   ├── class-swift-csv-import-csv-parser.php               # Streamed CSV parsing and validation
│   │   ├── class-swift-csv-import-csv-store.php                # Batch CSV state/cache store
│   │   ├── class-swift-csv-import-csv.php                      # CSV utility helpers
│   │   ├── class-swift-csv-import-file-processor.php           # Uploaded file handling / temp file setup
│   │   ├── class-swift-csv-import-log-store.php                # Import log persistence
│   │   ├── class-swift-csv-import-meta-tax.php                 # Meta and taxonomy processing
│   │   ├── class-swift-csv-import-persister.php                # Post persistence helpers
│   │   ├── class-swift-csv-import-request-parser.php           # Request parsing and sanitization
│   │   ├── class-swift-csv-import-response-manager.php         # Import JSON/progress responses
│   │   ├── class-swift-csv-import-row-context.php              # Per-row context construction
│   │   ├── class-swift-csv-import-row-processor.php            # Per-row processing flow
│   │   ├── class-swift-csv-import-taxonomy-util.php            # Taxonomy parsing utilities
│   │   ├── class-swift-csv-import-taxonomy-writer-interface.php # Taxonomy writer interface
│   │   └── class-swift-csv-import-taxonomy-writer-wp.php       # WP taxonomy writer
│   ├── class-swift-csv-ajax-util.php        # Shared AJAX utilities and response helpers
│   ├── class-swift-csv-file-util.php        # Filesystem helper methods
│   └── class-swift-csv-helper.php           # Shared CSV/format helper methods
├── assets/
│   ├── js/
│   │   ├── swift-csv-core.js       # Shared utilities (AJAX, logging, formatting)
│   │   ├── swift-csv-export-unified.js # Export UI and AJAX logic
│   │   ├── swift-csv-import.js     # Import UI, file upload, polling, AJAX logic
│   │   ├── swift-csv-license.js    # License activation/deactivation UI
│   │   ├── swift-csv-main.js       # Admin entry point and module initializer
│   │   ├── export/
│   │   │   └── swift-csv/          # Export modules (6 files)
│   │   │       ├── ajax.js         # Export AJAX handling
│   │   │       ├── download.js     # File download logic
│   │   │       ├── form.js         # Export form handling
│   │   │       ├── logs.js         # Export log management
│   │   │       ├── original.js     # Original export logic
│   │   │       └── ui.js           # Export UI components
│   │   └── *.min.js                # Minified versions (distribution only)
│   └── css/
│       ├── swift-csv-style.css     # Admin styles
│       └── swift-csv-style.min.css # Minified (distribution only)
├── languages/                      # Translation files (.po/.mo)
├── docs/                           # Markdown documentation set
├── tests/                          # PHPUnit tests (Unit + Integration)
│   ├── Unit/                       # Unit tests
│   ├── Integration/                # Integration test directory
│   ├── bootstrap.php               # Test environment setup
│   └── results/                    # Test result artifacts (local only)
├── _deprecated/                    # Deprecated code (kept for reference)
│   ├── export/                     # Legacy export code
│   └── import/                     # Legacy import code
└── .github/skills/                 # This directory
    ├── SKILL.md                    # ← You are here
    ├── architecture/               # Design decisions & hook API
    ├── troubleshooting/            # Pitfall catalog by category
    └── conventions/                # Coding standards, environment setup & patterns

swift-csv-pro/                      # Pro add-on (separate repo)
└── Extends Free version via WordPress hooks (enhanced features)
```

## Key Architecture Decisions

- **JS modules via `window.*` globals** — Browser code is authored as plain modular files and exposed through `window.SwiftCSV*`; build output is generated with `esbuild`, but runtime integration still uses globals.
- **WP_DEBUG-aware assets** — Debug mode loads unminified JS/CSS; production loads `.min` versions.
- **Unified AJAX entry points** — Import/export route requests through unified AJAX handlers, then delegate to batch planners and WP-compatible/direct-SQL handlers.
- **Chunked AJAX processing** — Both import and export use chunked requests to avoid PHP timeouts.
- **Streamed import batches** — Large CSV imports read logical lines incrementally and reuse cached offsets instead of retaining the entire CSV payload in memory.
- **Import pipeline split by responsibility** — Request parsing, file processing, CSV parsing, row context creation, row persistence, log storage, and response formatting are separated into dedicated classes.
- **Session-based export cancellation** — Uses `wp_options` with direct DB reads (bypasses cache) and per-session flags.
- **Temporary file security** — Import temp files are cleaned up on completion/error, protected by `.htaccess`, and purged after 24h.
- **Interface-based architecture** — Export/Import use base classes with WP-compatible implementations for extensibility.
- **Object-type PHPDoc** — Use `object` in PHPDoc for IDE compatibility while maintaining strict type hints in signatures.

## Critical Rules (Always Follow)

1. **WordPress AJAX responses** must include `'success' => true/false`. Use `wp_send_json_success()` / `wp_send_json_error()`.
2. **PHPDoc object types** — Use `object` in @param/@return for IDE compatibility, specific classes in method signatures.
3. **DOM manipulation** — Target specific child elements (`#export-log-content`), never clear parent containers (`.swift-csv-log`).
4. **Temporary files** — Call `wp_delete_file()` on ALL error paths, not just success.
5. **Module loading** — Wait for all `window.SwiftCSV*` globals before calling module functions.
6. **Build after JS/CSS changes** — Run `npm run build` (CSS + JS) or `npm run dev` for watch mode.
7. **CSV parsing behavior** — Treat RFC 4180 double-quote escaping (`""`) as the baseline; do not rely on backslash escaping in `str_getcsv()`.
8. **Release artifacts** — `test-release/` and generated minified files are build artifacts, not source-controlled files.

## Build Commands

```bash
npm run build          # Build all minified assets
npm run dev            # Watch mode for JS + CSS
composer phpcs         # Run PHP CodeSniffer
composer phpcbf        # Auto-fix PHP style
npm run lint:js        # ESLint
npm run format         # Prettier
./test-release.sh      # Build a local release ZIP and clean artifacts afterward
```

## Pitfall Quick Reference

| #   | Category | Symptom                               | Root Cause                                       | Detail                                                              |
| --- | -------- | ------------------------------------- | ------------------------------------------------ | ------------------------------------------------------------------- |
| 001 | PHP      | IDE static analysis warnings          | Specific class names in PHPDoc                   | Use `object` type in @param/@return, specific classes in signatures |
| 002 | JS       | Progress element not found            | Old DOM selector after UI refactor               | Check element existence before DOM manipulation                     |
| 003 | JS       | Malformed date in filename            | Missing hyphen in string concat                  | Use proper date formatting in filename generation                   |
| 004 | Env      | WP-CLI connection fails               | DB vars quoted, paths unquoted                   | Check environment configuration syntax                              |
| 005 | PHP      | AJAX error after success              | Missing `success: true` in response              | Always include success flag in AJAX responses                       |
| 006 | JS       | Export cancellation broken            | Option cache, session isolation, race conditions | Use proper session management and caching                           |
| 007 | Security | Temp CSV files persist                | Cleanup missing on one or more execution paths   | Ensure cleanup on success, error, exception, and cancellation paths |
| 008 | JS       | UI disappears after JS modularization | Wrong DOM selectors, over-aggressive cleanup     | Test DOM selectors after UI changes                                 |
| 009 | CSV      | Quoted fields parse inconsistently    | Assuming backslash escapes are supported         | Follow RFC 4180 expectations and verify source CSV escaping         |
| 010 | Release  | Repo gets dirty after local packaging | Build artifacts remain in the working tree       | Use `test-release.sh` cleanup behavior and do not re-track outputs  |

## Detailed Documentation

- **[Architecture](architecture/)** — Hook API design, unified import/export flow, and Free/Pro extension points
- **[Troubleshooting](troubleshooting/)** — Categorized pitfall catalog for environment, PHP, JS, and security issues
- **[Conventions](conventions/)** — WordPress coding standards, build workflow, and local environment setup
