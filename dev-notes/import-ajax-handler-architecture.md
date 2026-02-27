# Import AJAX Architecture (Unified Router → Handlers)

## Goal

Align the Import AJAX entrypoint structure with the existing Export architecture by introducing a `Unified Router → Handler` split.

This improves:

- Maintainability (clear responsibility boundaries)
- Extensibility (Direct SQL import can grow without bloating the router)
- Debuggability (fixed investigation starting points)
- Backward compatibility (keep existing importer classes and AJAX actions)

## Scope

This document describes the internal server-side architecture for handling:

- `wp_ajax_swift_csv_ajax_import` (import execution)
- `wp_ajax_swift_csv_ajax_import_logs` (log retrieval)

It is intentionally developer-focused and is not part of end-user documentation.

## High-level Flow

1. Admin UI triggers AJAX: `swift_csv_ajax_import`
2. `Swift_CSV_Ajax_Import_Unified::handle()`
3. Router validates nonce/capability, parses request, chooses `import_method`
4. Router instantiates the corresponding handler and delegates execution
5. Handler invokes the importer (`Swift_CSV_Import_*`) to perform the actual import

Log retrieval is handled separately via `swift_csv_ajax_import_logs`.

## Components

### Router (Entry Point)

- Class: `Swift_CSV_Ajax_Import_Unified`
- File: `includes/import/class-swift-csv-ajax-import-unified.php`

Responsibilities:

- Nonce verification (`check_ajax_referer`)
- Capability check (`current_user_can( 'import' )`)
- Request parsing via `Swift_CSV_Import_Request_Parser`
- Route selection based on `import_method`
- Safety mechanisms to avoid corrupted/empty JSON responses (`Swift_CSV_Ajax_Util`)
- Enforce a safety toggle for Direct SQL import (`swift_csv_enable_direct_sql_import`)

Non-responsibilities:

- Method-specific import implementation details
- Session/batch/logging/cancellation rules per import method (future responsibility of handlers)

### Handlers (Request-scoped Delegates)

Handlers are the method-specific request executors.

- Class: `Swift_CSV_Ajax_Import_Handler_WP_Compatible`
  - File: `includes/import/class-swift-csv-ajax-import-handler-wp-compatible.php`

- Class: `Swift_CSV_Ajax_Import_Handler_Direct_SQL`
  - File: `includes/import/class-swift-csv-ajax-import-handler-direct-sql.php`

Current responsibilities (minimal implementation):

- Provide a stable "request-scoped" place for method-specific logic
- Delegate to the corresponding importer:
  - `Swift_CSV_Import_WP_Compatible::import()`
  - `Swift_CSV_Import_Direct_SQL::import()`

Future responsibilities (as Direct SQL import is implemented):

- Session management (e.g. `import_session` lifecycle)
- Batch sizing strategy (e.g. `Swift_CSV_Ajax_Import_Batch_Planner`)
- Logging initialization/append policies (`Swift_CSV_Import_Log_Store`)
- Cancellation control (if introduced for import)
- Direct SQL-specific rate limiting / locks (similar to export)

### Importers (Domain / Batch Logic)

Importers are the existing import implementations.

- `Swift_CSV_Import_WP_Compatible`
- `Swift_CSV_Import_Direct_SQL`

Current contract:

- `import()` remains the operational entrypoint

Direction:

- Keep request-scoped concerns in handlers
- Keep domain and per-row/batch processing logic in importers and related utilities

## Autoloading

Swift CSV uses a custom autoloader in `swift-csv.php`.

Key behavior:

- Classes starting with `Swift_CSV_Ajax_Import` are mapped to `includes/import/class-swift-csv-ajax-import-*.php`
- Therefore handler classes require no manual `require_once` as long as naming conventions are followed

## Backward Compatibility Strategy

- Keep AJAX action names unchanged
- Keep importer classes and their public APIs unchanged
- Router behavior remains the same; only internal delegation changed from importer-direct calls to handler calls

## Notes

- Direct SQL import is intentionally disabled by default via:
  - `apply_filters( 'swift_csv_enable_direct_sql_import', false )`

This reduces the risk of data corruption until the Direct SQL import implementation is ready.
