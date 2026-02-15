# 🔧 Configuration

Detailed configuration settings for Swift CSV.

## Basic Settings

### Import Settings

- **Encoding**: UTF-8 (automatic detection and conversion)
- **Delimiter**: Auto-detected for import (comma, semicolon, tab)
    - **Note**: Simple detection based on first line character count
    - **Limitation**: May have issues with complex CSV data containing delimiters in text
- **Enclosure**: Double quote (fixed)
- **Batch Size**: Adaptive (1-10 rows based on file size and threshold)
    - **Small datasets** (≤100 rows): 1 row per batch for precise progress
    - **Large datasets** (>100 rows): 10 rows per batch for performance
    - **Memory threshold**: 100 rows triggers batch processing

### Export Settings

- **Output Format**: CSV (fixed)
- **Character Code**: UTF-8 (fixed)
- **BOM**: Not supported
- **Delimiter**: Comma (fixed - PHP fputcsv standard)
- **Export Limit**: User-configurable (default: 1000, 0 = unlimited)
- **Batch Size**: Adaptive (1-500 posts based on export limit)
    - **Small exports** (≤1000 items): 1 post per batch for real-time progress
    - **Large exports** (>1000 items): 500 posts per batch for performance
    - **Smart calculation**: `min(actual_export_count, threshold)` determines batch size

## Advanced Settings

### Memory and Performance

**Current Implementation**:

- **Export**:
    - Adaptive batch size: 1-500 posts per chunk based on export limit
    - Real-time progress tracking with detailed status display
    - User-configurable export limit with proper batch calculation
    - Memory-efficient chunked processing with AJAX
    - Shimmer animations during processing

- **Import**:
    - Adaptive batch size: 1-10 rows based on file size and threshold
    - Supports large files over 100 rows
    - Prevents timeouts with chunked processing
    - Auto-detects CSV delimiter
    - Real-time progress tracking with detailed row status

### Progress Tracking

**New in v0.9.7**:

- **Visual Progress Bar**: Animated progress with shimmer effects
- **Real-time Details**: Shows individual post titles during processing
- **Status Indicators**: Processing → Completed states with color transitions
- **Animation Patterns**: 6 different shimmer animations (standard, reverse, alternate, pulse, fast, slow)
- **Natural Language**: Japanese localization for all status messages

### Field Mapping

**Current Implementation**:

Field mapping is handled automatically during import:

- **First CSV Row**: Used as headers for column identification
- **Delimiter Detection**: Automatically detects comma, semicolon, or tab delimiters
- **Custom Fields**: Automatically detected and exported with 'cf\_' prefix
- **Standard Fields**: Mapped automatically (post_title, post_content, etc.)
- **Multi-value Support**: Pipe-separated values (e.g., "value1|value2|value3")

**Example**: If your CSV has columns `Name`, `Email`, `Phone`, they will be processed as custom fields and exported as `cf_Name`, `cf_Email`, `cf_Phone`.

### Export Scopes

**Available Export Scopes**:

- **Basic**: Standard WordPress fields (title, content, excerpt, status, etc.)
- **All**: All fields including custom meta fields
- **Custom**: User-defined columns via filter hook

**Post Status Options**:

- **Published posts only**: Only published posts
- **All statuses**: All post statuses (draft, private, published, etc.)
- **Custom**: User-defined post status filtering via hook

**Private Meta Fields**:

- Option to include/exclude private meta fields
- Controlled by `include_private_meta` checkbox
- Useful for excluding internal/sensitive data

### License Integration

**Pro Version Features**:

- **License Status Detection**: Accurate detection of 3 states:
    1. Not installed: Installation guidance
    2. Installed but inactive: Activation instructions
    3. Active but server unconfigured: Support contact
- **Automatic Updates**: One-click updates from WordPress admin
- **Extended Features**: Advanced export options and custom hooks

## Current Limitations

### Import Limitations

- **CSV Format**: Comma, semicolon, or tab-delimited CSV with double quote enclosure
- **Delimiter Detection**: Simple character count-based detection may fail with complex data
- **Character Encoding**: UTF-8 only (automatic conversion from other encodings)
- **Configuration**: No user-configurable delimiter or enclosure for import
- **Batch Size**: Adaptive but limited by memory constraints

### Export Limitations

- **Output Format**: Fixed UTF-8 CSV format
- **BOM**: Not supported
- **Delimiter**: Fixed comma delimiter (PHP fputcsv standard)
- **Enclosure**: Fixed double quote enclosure
- **Configuration**: Limited to scope selection and limits

### Technical Constraints

- **Memory Management**: Adaptive batch sizes prevent timeouts
- **File Size**: Large files processed in chunks
- **Database**: Uses WordPress standard post queries
- **Compatibility**: Works with standard WordPress post types
- **Performance**: Optimized for both small and large datasets

## Hook System

### Available Hooks

**Export Hooks**:

- `swift_csv_before_export`: Before export starts
- `swift_csv_export_columns`: Define custom export columns
- `swift_csv_export_row`: Process individual rows
- `swift_csv_after_export`: After export completes

**Import Hooks**:

- `swift_csv_before_import`: Before import starts
- `swift_csv_import_row`: Process individual rows
- `swift_csv_import_row_context`: Row context and validation
- `swift_csv_after_import`: After import completes

**Filter Hooks**:

- `swift_csv_export_post_status_query`: Custom post status filtering
- `swift_csv_process_custom_header`: Process custom export headers
- `swift_csv_import_row_data`: Filter and preprocess row data

## Performance Optimization

### Batch Processing Strategy

**Smart Batch Calculation**:

```php
// Export batch size calculation
$actual_export_count = min($total_count, $export_limit);
$batch_size = ($actual_export_count <= $threshold) ? 1 : self::BATCH_SIZE;
```

**Memory Management**:

- **Small datasets**: 1-item batches for precise progress tracking
- **Large datasets**: 10-item batches (import) / 500-item batches (export) for performance
- **Row threshold**: 100 rows triggers batch processing
- **Memory limit**: 128MB+ recommended for large CSV processing

### UI Performance

- **AJAX Processing**: Non-blocking UI during large operations
- **Progress Updates**: Real-time feedback without page refresh
- **Animation Performance**: GPU-accelerated CSS animations
- **Responsive Design**: Mobile-friendly interface
