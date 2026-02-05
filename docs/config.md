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
- **Number of posts**: Set the number of posts to export (default: 1000)
    - Large datasets are automatically processed in batches to prevent timeouts

## Advanced Settings

### Memory Limit

Memory settings for large file processing:

**Current Implementation**:

- **Export**: Batch processing supports unlimited posts (processed in chunks)
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
