# 🔧 Configuration

Detailed configuration settings for Swift CSV.

## Basic Settings

### Import Settings

- **Encoding**: UTF-8, Shift-JIS, EUC-JP, JIS (auto-detection and conversion)
- **Delimiter**: Comma (fixed)
- **Enclosure**: Double quote (fixed)

### Export Settings

- **Output Format**: CSV (fixed)
- **Character Code**: UTF-8 (fixed)
- **BOM**: Not supported
- **Number of posts**: Set the number of posts to export (default: 1000, max: 5000)
    - Recommended range: 1000-3000 posts for most servers
    - Larger datasets may timeout due to PHP execution limits
    - For very large datasets, consider batch processing or server optimization

## Advanced Settings

### Memory Limit

Memory settings for large file processing:

**Current Implementation**:

- **Export**: Supports up to 5000 posts (recommended: 1000-3000)
- **Import**: Batch processing prevents timeouts for large files over 1000 rows or 10MB

These limits are hardcoded to prevent memory issues and timeouts.

### Field Mapping

**Current Implementation**:

Field mapping is handled automatically during import:

- **First CSV Row**: Used as headers for column identification
- **Custom Fields**: Automatically detected and exported with 'cf\_' prefix
- **Standard Fields**: Mapped automatically (post_title, post_content, etc.)

**Example**: If your CSV has columns `Name`, `Email`, `Phone`, they will be processed as custom fields and exported as `cf_Name`, `cf_Email`, `cf_Phone`.

## Current Limitations

- **Import**: Only comma-delimited CSV with double quote enclosure
- **Export**: Fixed UTF-8 CSV format
- **Configuration**: No user-configurable options yet
- **Memory**: Batch processing prevents timeouts for large datasets
