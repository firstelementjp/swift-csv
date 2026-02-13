# SKILL.md — Swift CSV Development Guide

> Top-level overview for AI assistants and developers.
> Detailed documentation is in subdirectories — only load what you need.

## Project Overview

**Swift CSV** is a WordPress plugin for CSV import/export with full support for custom post types, taxonomies, and custom fields. It ships as a Free version with an optional Pro add-on.

| Item           | Value                                |
| -------------- | ------------------------------------ |
| **Type**       | WordPress Plugin (Free + Pro add-on) |
| **WordPress**  | 6.0+                                 |
| **PHP**        | 8.0+                                 |
| **JavaScript** | Vanilla JS, modular (no framework)   |
| **Build**      | esbuild for JS/CSS minification      |
| **i18n**       | English (default), Japanese          |

## Repository Layout

```
swift-csv/                          # Free version (this repo)
├── swift-csv.php                   # Plugin entry point, activation/deactivation hooks
├── uninstall.php                   # Plugin uninstallation cleanup
├── includes/
│   ├── class-swift-csv-admin.php   # Admin UI, script/style enqueue, settings
│   ├── class-swift-csv-ajax-export.php  # AJAX export handler (chunked)
│   ├── class-swift-csv-ajax-import.php  # AJAX import handler (chunked)
│   ├── class-swift-csv-license-handler.php  # License validation/activation
│   └── class-swift-csv-updater.php  # Plugin update system
├── assets/
│   ├── js/
│   │   ├── swift-csv-core.js       # Shared utilities (__(), wpPost, logging)
│   │   ├── swift-csv-export.js     # Export UI and AJAX logic
│   │   ├── swift-csv-import.js     # Import UI, file upload, AJAX logic
│   │   ├── swift-csv-license.js    # License activation/deactivation
│   │   ├── swift-csv-main.js       # Entry point, module initializer
│   │   └── *.min.js                # Minified versions (production)
│   └── css/
│       ├── swift-csv-style.css     # Admin styles
│       └── swift-csv-style.min.css # Minified (production)
├── languages/                      # Translation files (.po/.mo)
├── docs/                           # Docsify documentation site
└── .github/skills/                 # This directory
    ├── SKILL.md                    # ← You are here
    ├── architecture/               # Design decisions & hook API
    ├── troubleshooting/            # Pitfall catalog by category
    └── conventions/                # Coding standards & patterns

swift-csv-pro/                      # Pro add-on (separate repo)
└── Extends Free version via WordPress hooks (ACF support, license system)
```

## Key Architecture Decisions

- **JS modules via `window.*` globals** — No bundler; each file exports to `window.SwiftCSVCore`, `window.SwiftCSVExport`, etc. Main entry waits for all modules before initializing.
- **WP_DEBUG-aware assets** — Debug mode loads unminified JS/CSS; production loads `.min` versions.
- **Chunked AJAX processing** — Both import and export use chunked requests to avoid PHP timeouts.
- **Session-based export cancellation** — Uses `wp_options` with direct DB reads (bypasses cache) and per-session flags.
- **Temporary file security** — Import temp files are cleaned up on completion/error, protected by `.htaccess`, and purged after 24h.

## Critical Rules (Always Follow)

1. **WordPress AJAX responses** must include `'success' => true/false`. Use `wp_send_json_success()` / `wp_send_json_error()`.
2. **ACF functions** always require `$post_id`. Never call `get_field_object()` without it.
3. **DOM manipulation** — Target specific child elements (`#export-log-content`), never clear parent containers (`.swift-csv-log`).
4. **Temporary files** — Call `unlink()` on ALL error paths, not just success.
5. **Module loading** — Wait for all `window.SwiftCSV*` globals before calling module functions.
6. **Build after JS/CSS changes** — Run `npm run build:modules` (JS) or `npm run build:css` (CSS).

## Build Commands

```bash
npm run build            # Build all (CSS + JS modules)
npm run build:modules    # Build all JS modules
npm run build:css        # Build CSS only
npm run build:core       # Build single module
```

## Pitfall Quick Reference

| #   | Category | Symptom                               | Root Cause                                       | Detail                                                             |
| --- | -------- | ------------------------------------- | ------------------------------------------------ | ------------------------------------------------------------------ |
| 001 | PHP      | Empty ACF columns in export           | Missing `$post_id` in `get_field_object()`       | [→ php-pitfalls.md](troubleshooting/php-pitfalls.md#001)           |
| 002 | JS       | Progress element not found            | Old DOM selector after UI refactor               | [→ js-pitfalls.md](troubleshooting/js-pitfalls.md#002)             |
| 003 | JS       | Malformed date in filename            | Missing hyphen in string concat                  | [→ js-pitfalls.md](troubleshooting/js-pitfalls.md#003)             |
| 004 | Env      | WP-CLI connection fails               | DB vars quoted, paths unquoted                   | [→ env-pitfalls.md](troubleshooting/env-pitfalls.md#004)           |
| 005 | PHP      | AJAX error after success              | Missing `success: true` in response              | [→ php-pitfalls.md](troubleshooting/php-pitfalls.md#005)           |
| 006 | JS       | Export cancellation broken            | Option cache, session isolation, race conditions | [→ js-pitfalls.md](troubleshooting/js-pitfalls.md#006)             |
| 007 | Security | Temp CSV files persist                | No cleanup on error paths                        | [→ security-pitfalls.md](troubleshooting/security-pitfalls.md#007) |
| 008 | JS       | UI disappears after JS modularization | Wrong DOM selectors, over-aggressive cleanup     | [→ js-pitfalls.md](troubleshooting/js-pitfalls.md#008)             |

## Detailed Documentation

- **[Architecture](architecture/)** — Hook API design, Free/Pro integration, three-element merge pattern
- **[Troubleshooting](troubleshooting/)** — Categorized pitfall catalog with fix patterns
- **[Conventions](conventions/)** — WordPress coding standards, Yoda conditions, naming rules
