# 🔧 Configuration

This page describes the current behavior of Swift CSV as implemented in v0.9.8.

## Import Configuration

### CSV Format

- The first row is treated as headers.
- The `ID` column is required.
- Use an empty `ID` value for new posts.
- Use an existing `ID` only when updating an existing post.
- Delimiters are auto-detected for common CSV formats.
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

Swift CSV automatically adjusts batch sizes.

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

Swift CSV Free and Swift CSV Pro are integrated through WordPress hooks and capability checks.

Available behavior includes:

- Pro-aware admin messaging
- License activation UI when Pro functionality is available
- Feature flags for optional Pro behavior

## Current Practical Limits

- Very large files still depend on server resources
- Delimiter detection is automatic, but malformed CSV can still fail
- Export format is fixed to CSV
- Import behavior assumes WordPress-style field names and plugin conventions

## Developer Extensibility

Swift CSV includes hook-based extension points for:

- Export headers and rows
- Import permissions and validation
- Import field preparation
- Batch-size tuning
- Feature flags and admin access control

See [Developer Hooks](hooks.md) for the current hook reference.
