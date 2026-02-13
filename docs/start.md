# 🚀 Getting Started

Welcome to Swift CSV! This plugin makes CSV data management simple on WordPress sites.

## What is Swift CSV?

Swift CSV is a lightweight yet powerful WordPress plugin that simplifies CSV import and export operations. Whether you need to bulk import products, export user data, or manage custom post types, Swift CSV handles it efficiently with an intuitive interface.

## Key Features

- 🌐 **Internationalization**: Full Japanese and English support
- 📊 **Batch Processing**: Handle large files without timeouts
- 🎨 **Modern UI**: Responsive admin interface
- 🎯 **Block Editor Compatible**: Preserves Gutenberg content
- 🛑 **Cancel Operations**: Safe interruption of long processes
- 🔧 **Custom Fields**: Full support for custom meta fields

## Quick Start

### 1. Installation

If you haven't installed the plugin yet, see the [Installation Guide](install.md) for detailed instructions.

### 2. First Import

1. **Navigate to Admin Panel**: Go to **Swift CSV** → **Import**
2. **Upload CSV File**: Click "Choose File" and select your CSV
3. **Configure Settings**:
    - Select post type (posts, pages, or custom post types)
    - Choose import scope (basic or all fields)
    - Set batch processing options for large files
4. **Preview Data**: Review the detected headers and data mapping
5. **Start Import**: Click "Import CSV" and monitor progress

### 3. First Export

1. **Navigate to Export**: Go to **Swift CSV** → **Export**
2. **Configure Settings**:
    - Select post type to export
    - Choose export scope (basic or all fields)
    - Set export limit (0 for unlimited)
    - Toggle private meta fields inclusion
3. **Generate CSV**: Click "Export CSV" and download the file

## Understanding CSV Structure

### Basic CSV Format

```csv
post_title,post_content,post_status,post_excerpt
"Sample Post","This is the content","publish","Brief excerpt"
"Another Post","More content here","publish","Another excerpt"
```

### Custom Fields

Use the `cf_` prefix for custom fields:

```csv
post_title,post_content,cf_price,cf_color,cf_size
"Product A","Description",19.99,red,L
"Product B","Description",29.99,blue,M
```

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
