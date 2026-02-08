# 🔧 Configuration

Detailed configuration settings for Swift CSV.

## Basic Settings

### Import Settings

- **Encoding**: UTF-8 (automatic detection and conversion)
- **Delimiter**: Auto-detected for import (comma, semicolon, tab)
    - **Note**: Simple detection based on first line character count
    - **Limitation**: May have issues with complex CSV data containing delimiters in text
- **Enclosure**: Double quote (fixed)
- **Batch Size**: 10 rows per batch (fixed for memory management)

### Export Settings

- **Output Format**: CSV (fixed)
- **Character Code**: UTF-8 (fixed)
- **BOM**: Not supported
- **Delimiter**: Comma (fixed - PHP fputcsv standard)
- **Export Limit**: User-configurable (default: 1000, 0 = unlimited)
- **Batch Size**: 500 posts per batch (fixed for performance)

## Advanced Settings

### Memory and Performance

**Current Implementation**:

- **Export**:
    - Batch size: 500 posts per chunk
    - Supports unlimited posts with batch processing
    - User-configurable export limit
    - Memory-efficient chunked processing

- **Import**:
    - Batch size: 10 rows per chunk
    - Supports large files over 1000 rows or 10MB
    - Prevents timeouts with chunked processing
    - Auto-detects CSV delimiter

### Field Mapping

**Current Implementation**:

Field mapping is handled automatically during import:

- **First CSV Row**: Used as headers for column identification
- **Delimiter Detection**: Automatically detects comma, semicolon, or tab delimiters
- **Custom Fields**: Automatically detected and exported with 'cf\_' prefix
- **Standard Fields**: Mapped automatically (post_title, post_content, etc.)

**Example**: If your CSV has columns `Name`, `Email`, `Phone`, they will be processed as custom fields and exported as `cf_Name`, `cf_Email`, `cf_Phone`.

### Export Scopes

**Available Export Scopes**:

- **Basic**: Standard WordPress fields (title, content, excerpt, status, etc.)
- **All**: All fields including custom meta fields
- **Custom**: User-defined columns via filter hook

**Private Meta Fields**:

- Option to include/exclude private meta fields
- Controlled by `include_private_meta` checkbox

## Current Limitations

### Import Limitations

- **CSV Format**: Comma, semicolon, or tab-delimited CSV with double quote enclosure
- **Delimiter Detection**: Simple character count-based detection may fail with complex data
- **Character Encoding**: UTF-8 only (automatic conversion from other encodings)
- **Configuration**: No user-configurable delimiter or enclosure for import
- **Batch Size**: Fixed at 10 rows for memory management

### Export Limitations

- **Output Format**: Fixed UTF-8 CSV format
- **BOM**: Not supported
- **Delimiter**: Fixed comma delimiter (PHP fputcsv standard)
- **Enclosure**: Fixed double quote enclosure
- **Configuration**: Limited to scope selection and limits

### Technical Constraints

- **Memory Management**: Fixed batch sizes prevent timeouts
- **File Size**: Large files processed in chunks
- **Database**: Uses WordPress standard post queries
- **Compatibility**: Works with standard WordPress post types
