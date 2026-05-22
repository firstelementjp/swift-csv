# Deprecated Code

This directory contains code that has been deprecated and removed from the active codebase. These files are kept for reference and historical context.

## Deprecation History

All files in this directory were deprecated during the v0.9.9.3 release cycle (WordPress.org submission preparation).

### Deprecated Files and Replacements

#### Updater
- **File**: `class-swift-csv-updater.php`
- **Deprecated**: v0.9.9.3
- **Reason**: Disabled for WordPress.org repository submission. Updates are handled through the official WordPress.org repository.
- **Replacement**: None (updates handled by WordPress.org)

#### Direct SQL Export
- **Files**:
  - `export/class-swift-csv-export-direct-sql.php`
  - `export/class-swift-csv-ajax-export-handler-direct-sql.php`
- **Deprecated**: v0.9.9.3
- **Reason**: Migrated to Pro plugin for performance optimization
- **Replacement**: Pro plugin implementation with hook points (`fe_csv_import_export_export_direct_sql`)

#### Updraft Plus Backup
- **Files**: (removed, replaced with hooks)
- **Deprecated**: v0.9.9.3
- **Reason**: Replaced with hook-based architecture for better extensibility
- **Replacement**: Hook points (`fe_csv_import_export_before_import_backup`)

#### Legacy Import Handlers
- **Files**:
  - `import/legacy/class-swift-csv-ajax-import.php`
  - `import/legacy/class-swift-csv-import-environment-manager.php`
  - `import/class-swift-csv-ajax-import-handler-base.php`
  - `import/class-swift-csv-ajax-import-handler-direct-sql.php`
  - `import/class-swift-csv-ajax-import-handler-wp-compatible.php`
- **Deprecated**: v0.9.8 - v0.9.9
- **Reason**: Refactored to unified AJAX handler architecture with batch processing
- **Replacement**: 
  - `includes/import/class-fe-csv-import-export-import-batch-processor.php`
  - `includes/import/class-fe-csv-import-export-import-csv.php`
  - `includes/class-fe-csv-import-export-ajax-util.php`

#### Legacy Export Handler
- **File**: `export/legacy/class-swift-csv-ajax-export.php`
- **Deprecated**: v0.9.5
- **Reason**: Migrated to modern AJAX-based batch export system
- **Replacement**: `includes/export/class-fe-csv-import-export-export-batch-processor.php`

#### Direct SQL Import (High Speed)
- **Files**:
  - `import/high_speed/class-swift-csv-import-meta-tax-direct-sql.php`
  - `import/high_speed/class-swift-csv-import-persister-direct-sql.php`
  - `import/high_speed/class-swift-csv-import-taxonomy-writer-direct-sql.php`
  - `import/class-swift-csv-import-direct-sql.php`
- **Deprecated**: v0.9.8
- **Reason**: Migrated to Pro plugin for performance optimization
- **Replacement**: Pro plugin implementation with hook points (`fe_csv_import_export_import_direct_sql`)

## Migration Guide

For developers who need to migrate from deprecated code:

1. **Direct SQL Export**: Use the `fe_csv_import_export_export_direct_sql` hook in the Pro plugin
2. **Updraft Plus Backup**: Use the `fe_csv_import_export_before_import_backup` hook
3. **Legacy Import Handlers**: Use the new unified AJAX handler at `fe_csv_import_export_ajax_import`
4. **Legacy Export Handler**: Use the new batch export system at `fe_csv_import_export_ajax_export`

## Notes

- These files are not loaded in production
- File names retain original naming for Git history tracking
- Refer to Git commit history for detailed migration information
- Current implementation uses hook-based architecture for better extensibility

## Related Commits

- `7343d91` - Move Direct SQL export code to _deprecated and replace with hooks
- `c5133b3` - Remove Updraft Plus backup code and replace with hooks
- `a0da471` - Add deprecated updater and update README.md for developers
- `1c89f6a` - Add deprecated legacy import/export classes
- `87266d6` - Move deprecated ajax import handlers to _deprecated
- `1361a7d` - Track _deprecated directory
