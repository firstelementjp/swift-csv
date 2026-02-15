# 🚀 Getting Started

Welcome to Swift CSV v0.9.7! This plugin makes CSV data management simple and beautiful on WordPress sites.

## What is Swift CSV?

Swift CSV is a lightweight yet powerful WordPress plugin that simplifies CSV import and export operations with stunning visual progress tracking. Whether you need to bulk import products, export user data, or manage custom post types, Swift CSV handles it efficiently with an intuitive interface featuring real-time progress bars and shimmer animations.

## Key Features (v0.9.7)

- � **Progress Bar UI**: Beautiful shimmer animations during processing
- 📊 **Real-time Details**: Individual post titles displayed during processing
- 🌐 **Complete Japanese Localization**: Natural Japanese messaging throughout
- 🔄 **Adaptive Batch Processing**: Smart batch sizes (1-500 posts) based on dataset size
- 🎯 **Enhanced License Detection**: Accurate status for 3 different scenarios
- 🛑 **Cancel Operations**: Safe interruption of long processes
- 🧩 **Multi-value Fields**: Pipe-separated values support (e.g., "value1|value2|value3")
- 📱 **Responsive Design**: Mobile-friendly admin interface
- 🔧 **Custom Fields**: Full support for custom meta fields
- 🎨 **Block Editor Compatible**: Preserves Gutenberg content

## Quick Start

### 1. Installation

If you haven't installed the plugin yet, see the [Installation Guide](install.md) for detailed instructions.

### 2. First Import

1. **Navigate to Admin Panel**: Go to **Swift CSV** → **Import**
2. **Upload CSV File**: Click "Choose File" and select your CSV
3. **Configure Settings**:
    - Select post type (posts, pages, or custom post types)
    - Choose import scope (basic or all fields)
    - Set update existing posts option
    - Enable dry run mode for testing
4. **Preview Data**: Review the detected headers and data mapping
5. **Start Import**: Click "Import CSV" and watch the beautiful progress bar with shimmer animations

**What you'll see**:

- Real-time progress with percentage and animated bar
- Individual post titles being processed
- Natural Japanese status messages
- Completion with green progress bar

### 3. First Export

1. **Navigate to Export**: Go to **Swift CSV** → **Export**
2. **Configure Settings**:
    - Select post type to export
    - Choose post status (Published only, All statuses, or Custom)
    - Select export scope (Basic, All, or Custom)
    - Set export limit (0 for unlimited)
    - Toggle private meta fields inclusion
3. **Generate CSV**: Click "Export CSV" and watch real-time progress
4. **Download File**: Get your CSV with all data properly formatted

**What you'll see**:

- Beautiful shimmer animations during processing
- Real-time post titles being exported
- Progress percentage with visual feedback
- Completion notification with download link

## Understanding CSV Structure

### Basic CSV Format

```csv
post_title,post_content,post_status,post_excerpt
"Sample Post","This is the content","publish","Sample excerpt"
"Another Post","More content here","",draft
```

### Custom Fields Example

```csv
post_title,post_content,cf_Name,cf_Email,cf_Phone,cf_Tags
"John Doe","Content about John","John","john@example.com","555-1234","developer|wordpress|php"
"Jane Smith","Content about Jane","Jane","jane@example.com","555-5678","designer|ui|ux"
```

**Key Points**:

- Custom fields use `cf_` prefix
- Multi-value fields use `|` separator
- First row contains headers
- Use UTF-8 encoding for best results

### Hierarchical Taxonomies

```csv
post_title,post_content,category,post_tag
"Tech Post","About technology","Technology > WordPress > Plugins","tech|wordpress|php"
"Design Post","About design","Design > UI > Web","design|ui|ux"
```

**Key Points**:

- Use `>` for hierarchy levels
- Use `|` for multiple terms
- Missing terms are auto-created
- URL-friendly slugs generated automatically

## Advanced Features

### Progress Tracking (v0.9.7)

**Visual Progress Bar**:

- Beautiful shimmer animations (6 different patterns)
- Real-time percentage updates
- Color transitions (processing → completed)
- Natural language status messages

**Real-time Details**:

- Individual post titles during export/import
- Processing time estimates
- Error reporting with context
- Completion summaries

**Batch Processing**:

- Small datasets (≤100 items): 1-item batches for precision
- Large datasets (>100 items): 500-item batches for performance
- Automatic threshold detection
- Memory-efficient processing

### License Integration

**Status Detection**:

- Not installed: Installation guidance
- Installed but inactive: Activation instructions
- Active but server unconfigured: Support contact

**Natural Japanese Messaging**:

- Context-aware messages
- HTML tag rendering in help text
- User-friendly instructions
- Clear next-step guidance

### Multi-value Custom Fields

**Import Support**:

- Pipe-separated values: `"value1|value2|value3"`
- Automatic splitting and storage
- Individual meta field entries
- Proper sanitization

**Export Support**:

- Automatic joining with `|` separator
- Proper escaping for CSV format
- Consistent with import format
- Backward compatibility

## Common Use Cases

### Blog Migration

**Scenario**: Migrate from another platform

**Steps**:

1. Export from old platform as CSV
2. Map custom fields with `cf_` prefix
3. Import with real-time progress tracking
4. Verify all posts imported correctly

**Features Used**:

- Custom field mapping
- Progress tracking
- Error handling
- Batch processing

### Product Catalog Management

**Scenario**: Manage e-commerce products

**Steps**:

1. Create product CSV with custom fields
2. Use multi-value fields for categories/tags
3. Import with progress monitoring
4. Export for backup or analysis

**Features Used**:

- Multi-value fields
- Custom field support
- Progress tracking
- Batch processing

### Content Management

**Scenario**: Bulk content updates

**Steps**:

1. Export existing content
2. Update in spreadsheet
3. Import with update existing option
4. Monitor progress in real-time

**Features Used**:

- Export functionality
- Update existing posts
- Progress tracking
- Dry run mode

## Tips and Best Practices

### Before You Start

**Test with Small Files**:

- Start with 5-10 rows to test your setup
- Verify field mapping works correctly
- Check encoding and formatting
- Test with your specific post type

**Backup Your Data**:

- Always backup before bulk operations
- Test imports on staging environment
- Keep original CSV files
- Document your field mappings

### During Processing

**Monitor Progress**:

- Watch the beautiful progress bar animations
- Check individual post titles being processed
- Monitor for any error messages
- Ensure completion before navigating away

**Handle Large Files**:

- Trust the automatic batch processing
- Monitor memory usage if needed
- Consider splitting very large files
- Use dry run mode for testing

### After Processing

**Verify Results**:

- Check imported posts in WordPress admin
- Verify custom fields are properly set
- Test exported CSV format
- Validate data integrity

**Troubleshoot Issues**:

- Check browser console for JavaScript errors
- Review debug logs if enabled
- Test with smaller datasets
- Consult [help.md](help.md) for common issues

## Configuration Options

### Import Settings

**Post Type Selection**:

- Standard: posts, pages, attachments
- Custom: Any registered post type
- Auto-detection of available types

**Import Options**:

- Update existing posts: Merge or replace
- Dry run mode: Test without importing
- Batch processing: Automatic for large files
- Error handling: Continue on errors or stop

### Export Settings

**Post Status Options**:

- Published posts only
- All statuses (draft, private, published, etc.)
- Custom filtering via hooks

**Export Scope**:

- Basic: Standard WordPress fields
- All: All fields including custom meta
- Custom: User-defined via hooks

**Limits and Options**:

- Export limit: Maximum posts per export
- Private meta fields: Include/exclude
- Batch size: Automatic optimization
- File format: UTF-8 CSV

## Getting Help

### Documentation

- **[Installation Guide](install.md)**: Detailed setup instructions
- **[Configuration Guide](config.md)**: Advanced configuration options
- **[Hooks Documentation](hooks.md)**: Developer customization
- **[Troubleshooting Guide](help.md)**: Common issues and solutions
- **[Legal Information](legal.md)**: License and compliance

### Community Support

- **GitHub Issues**: Report bugs and request features
- **GitHub Discussions**: Community help and discussions
- **Documentation**: Comprehensive guides in docs/ directory

### Pro Version Support

- **Priority Support**: Faster response times
- **Advanced Features**: Enhanced functionality
- **Custom Development**: Custom solutions available
- **Enterprise Support**: Business-level support

## Next Steps

Now that you understand the basics, explore these advanced topics:

1. **[Configuration Guide](config.md)**: Learn about advanced settings
2. **[Hooks Documentation](hooks.md)**: Customize functionality with hooks
3. **[Examples](example.md)**: See real-world use cases
4. **[Contributing](contribute.md)**: Help improve the plugin

## Version Information

**Current Version**: v0.9.7
**Release Date**: February 15, 2026
**Requirements**: WordPress 6.0+, PHP 8.0+

**What's New in v0.9.7**:

- Complete progress bar UI with shimmer animations
- Real-time export details with post titles
- Enhanced license status detection
- Complete Japanese localization
- Adaptive batch processing
- Multi-value custom field support

Enjoy using Swift CSV! 🎉
"Sample Post","This is the content","publish","Brief excerpt"
"Another Post","More content here","publish","Another excerpt"

````

### Custom Fields

Use the `cf_` prefix for custom fields:

```csv
post_title,post_content,cf_price,cf_color,cf_size
"Product A","Description",19.99,red,L
"Product B","Description",29.99,blue,M
````

### Multi-value Fields

Use pipe (`|`) separator for multiple values:

```csv
post_title,cf_tags,cf_categories
"Post A","wordpress|php|developer","Technology|WordPress"
```

## Common Use Cases

### Blog Posts Import

Import blog posts with categories and tags:

```csv
post_title,post_content,post_status,cf_tags
"My First Post","Content here","publish","wordpress,blogging"
```

### Product Catalog

Import e-commerce products with custom fields:

```csv
post_title,post_content,cf_price,cf_stock,cf_sku
"T-Shirt","Product description",19.99,50,TS-001
```

### User Data Export

Export user-generated content for backup:

1. Select "Posts" as post type
2. Choose "All" export scope
3. Include private meta fields
4. Export all posts

## Best Practices

### File Preparation

- **UTF-8 Encoding**: Save CSV files as UTF-8
- **Header Row**: Include descriptive headers in the first row
- **Consistent Delimiters**: Use commas consistently
- **Data Validation**: Check for special characters and quotes

### Large Files

- **Batch Processing**: Enable automatic batch processing
- **File Size**: Split very large files (>10MB) into smaller chunks
- **Memory**: Ensure sufficient server memory (128MB+ recommended)

### Data Quality

- **Test Imports**: Start with small test files
- **Backup Data**: Always backup before large imports
- **Review Results**: Check import logs for any errors

## Next Steps

### Learn More

- [Configuration](config.md) - Advanced settings and options
- [Examples](example.md) - Detailed implementation examples
- [Troubleshooting](help.md) - Common issues and solutions

### For Developers

- [Developer Hooks](hooks.md) - Extend functionality with custom code
- [Contributing](contribute.md) - Development guidelines

### Stay Updated

- [Changelog](changes.md) - Latest features and improvements
- [GitHub Repository](https://github.com/firstelementjp/swift-csv) - Source code and issues

## Support

If you encounter issues:

1. **Check Troubleshooting**: Review [help.md](help.md) for common solutions
2. **Enable Debug Mode**: Set `WP_DEBUG` to true for detailed error logs
3. **Report Issues**: Create a [GitHub Issue](https://github.com/firstelementjp/swift-csv/issues)
4. **Community**: Join discussions in GitHub Issues for community support

## Tips & Tricks

### Import Tips

- **Dry Run**: Test with small files first
- **Headers**: Use clear, descriptive header names
- **Data Types**: Ensure numeric data doesn't contain commas
- **Empty Fields**: Use empty strings for missing values

### Export Tips

- **Filters**: Use WordPress filters to customize export data
- **Scheduling**: Set up automated exports with cron jobs
- **Format**: Exported CSVs work with Excel, Google Sheets, and most spreadsheet applications

### Performance Tips

- **Server Resources**: Monitor memory usage during large operations
- **Timeout Settings**: Increase PHP execution time for very large files
- **Batch Size**: Adjust batch processing settings based on server capacity

---

🎉 **You're ready to use Swift CSV!** Start with a small test import to familiarize yourself with the interface, then move on to larger datasets.
