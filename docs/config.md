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

- Standard WordPress post fields such as `post_title`, `post_content`, `post_status`
- Custom fields using the `cf_` prefix
- Taxonomy fields such as `category`, `post_tag`, and custom taxonomy names

### Multi-value and Taxonomy Rules

- Multi-value fields use `|`
- Hierarchical taxonomy values use `>`
- Taxonomy parsing and custom field parsing are handled automatically

## Export Configuration

### Export Options

- **Post type**: Select the post type to export
- **Post status**: Filter exported rows by status
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

### Export

- Export batches are calculated automatically
- Current implementation uses **500-2000 posts** per batch depending on dataset size

### Why This Matters

This helps reduce:

- Request timeouts
- Memory spikes
- Large single-request processing loads

## UI Behavior

During import and export, the admin UI provides:

- Real-time progress updates
- Processing details while a job is running
- Localized interface text through WordPress translations

## License and Pro Integration

The Free and Pro versions work together. When the Pro version is installed, the following features become available:

- License key activation and deactivation UI
- Access to Pro-only features
- Pro-aware admin messages

## Current Practical Limits

- Very large files still depend on server resources
- Delimiter detection is automatic, but malformed CSV can still fail
- Export format is fixed to CSV
- CSV file header rows for import require exact WordPress database column names (post_title, post_content, etc.) and custom field names

## Developer Extensibility

FE CSV Import & Export includes hook-based extension points for:

- CSV header customization and row data transformation (export)
- Permission checks and data validation before import
- Field value transformation and processing before import
- Customization of rows processed per batch (batch size)
- Pro feature activation and admin access control

See [Developer Hooks](hooks.md) for the current hook reference.
