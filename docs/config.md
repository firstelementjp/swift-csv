# 🔧 Configuration

This page describes the current behavior of FE CSV Import & Export as implemented in v0.9.8.

## Import Configuration

### CSV Format

- The first row is treated as headers.
- The `ID` column is required.
- If the `ID` column has a value, existing posts will be overwritten.
- Leave the `ID` column empty to create new posts from the CSV file.
- Delimiters (comma, semicolon, or tab) are auto-detected.
- The CSV enclosure is the standard double quote format.

### Supported Header Types

- Standard WordPress post fields such as `post_title`, `post_content`, `post_status` (posts table column names)
- Columns starting with `cf_` are treated as custom fields (e.g., cf_price imports as a custom field named "price")
- Taxonomy names such as `category`, `post_tag`, and custom taxonomy names

### Multi-value and Taxonomy Rules

- Use `|` to separate multiple values in a single column (spaces before/after `|` do not affect functionality)
- Use `>` to specify term hierarchy (spaces before/after `>` do not affect functionality)
- Taxonomy output can be selected as term_id or name

## Export Configuration

### Export Options

- **Post type**: Select the post type to export
- **Post status**: Published posts, or all statuses, or filter exported posts by status
- **Scope**: Export basic fields, all fields, or custom output via hooks
- **Export limit**: Optional limit; `0` means unlimited
- **Private meta**: Optionally include private meta keys

### Output Format

- UTF-8 CSV output
- Standard comma delimiter
- Standard CSV escaping
- No BOM output

## Automatic Batch Processing

FE CSV Import & Export automatically adjusts batch sizes.

### Import

- Import batches are calculated automatically
- Current implementation targets roughly **10-50 rows** per batch depending on environment limits
- Import time will be shortened through efficiency improvements similar to export in the near future

### Export

- Export batches are calculated automatically
- Current implementation uses **500-5000 posts** per batch depending on dataset size

### Why This Matters

This helps reduce:

- Request timeouts
- Memory spikes
- Large single-request processing loads

## UI Behavior

During import and export, the admin UI provides:

- Real-time progress updates
- Processing details while a job is running
- During import, displays a summary of processed posts categorized as new, updated, or errors

## License and Pro Integration

The Free and Pro versions work together. When the Pro version is installed, the following features become available:

- License key activation and deactivation UI
- Access to Pro-only features (Advanced Custom Fields integration, UpdraftPlus integration, execution password settings, etc.)

## Current Practical Limits

- Very large files still depend on server resources
- Delimiter detection is automatic, but malformed CSV can still fail
- Export format is fixed to CSV
- CSV file header rows for import require exact WordPress database column names (post_title, post_content, etc.) and custom field names

## Developer Extensibility

FE CSV Import & Export includes action/filter hooks for:

- CSV header customization and row data transformation (export)
- Specifying sample posts to determine custom field items to export (by default, custom fields to output are determined based on the first row post)
- Permission checks and data validation before import
- Field value transformation and processing before import
- Customization of rows processed per batch (batch size)
- Pro feature activation and adding settings to the admin screen

See [Developer Hooks](hooks.md) for the current hook reference.
