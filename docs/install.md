# 📦 Installation

## Install from WordPress Admin

1. Log in to WordPress admin
2. Navigate to **Plugins** → **Add New**
3. Search for "Swift CSV"
4. Click **Install Now**
5. Click **Activate**

## Manual Installation

1. [Download latest version (v0.9.8)](https://github.com/firstelementjp/swift-csv/releases/download/v0.9.8/swift-csv-v0.9.8.zip)
2. Extract the downloaded ZIP file
3. Upload `swift-csv` folder to `/wp-content/plugins/`
4. Navigate to **Plugins** in WordPress admin
5. Find "Swift CSV" and click **Activate**

## Requirements

### System Requirements

- **WordPress**: 6.6 or higher (recommended: 6.6+)
- **PHP**: 8.1 or higher (recommended: 8.1+)
- **Memory Limit**: 128MB or higher (for large CSV processing)
- **Extensions**: `mbstring`, `zip` (for file handling)

### Server Recommendations

- **Upload Max Filesize**: 64MB or higher
- **Post Max Size**: 64MB or higher
- **Max Execution Time**: 300 seconds or higher
- **Output Buffering**: Enabled (for large file processing)

### Browser Compatibility

- **Modern Browsers**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **JavaScript**: Enabled (required for AJAX processing)
- **CSS Animations**: Supported (for progress bar animations)

## Initial Setup

### After Activation

1. **Access Plugin**: Navigate to **Tools** → **Swift CSV** in the WordPress admin menu
2. **Configure Settings**: Set default post types and export limits
3. **Test Import**: Try importing a small CSV file first
4. **Test Export**: Export a few posts to verify functionality
5. **Check Progress**: Watch the progress UI during processing

### Basic Configuration

1. **Default Post Type**: Choose which post type to work with by default
2. **Export Limit**: Set maximum number of posts per export (0 = unlimited)
3. **Batch Processing**: Automatic for all datasets (adaptive: 10-50 import rows, 500-2000 export posts)
4. **Debug Mode**: Enable `WP_DEBUG` for troubleshooting

### Advanced Features

- **Progress Tracking**: Real-time progress during import and export
- **Batch Processing**: Adaptive batch sizes (10-50 import rows, 500-2000 export posts)
- **Localized Interface**: English base strings with Japanese translations
- **License Integration**: Pro version license management
- **Multi-value Fields**: Pipe-separated values support (e.g., "value1|value2|value3")

## Troubleshooting Installation

### Common Issues

**Plugin not found in WordPress directory:**

- Verify the `swift-csv` folder is in `/wp-content/plugins/`
- Check folder permissions (755 recommended)
- Ensure all files are uploaded completely

**Activation fails:**

- Check PHP version compatibility (8.1+ required)
- Verify required PHP extensions are installed
- Check WordPress version compatibility (6.6+ required)

**Memory errors during import/export:**

- Increase PHP memory limit in `wp-config.php`: `define('WP_MEMORY_LIMIT', '256M');`
- Enable batch processing (automatic for all datasets)
- Check server upload limits

**Progress bar not working:**

- Check browser console for JavaScript errors
- Verify CSS files are loading correctly
- Test with smaller datasets first
- Check for plugin conflicts

**Japanese characters not displaying:**

- Ensure CSV files are saved as UTF-8
- Check database charset is `utf8mb4`
- Verify translation files are loaded correctly

**License activation issues:**

- Check if Swift CSV Pro is installed and activated
- Verify license server configuration
- Check translation files for proper message display
- Test with valid license key

## Performance Optimization

### For Large Datasets

**Automatic Optimizations:**

- **Import Batches**: 10-50 rows based on server performance
- **Export Batches**: 500-2000 posts based on dataset size
- **Memory Management**: Automatic cleanup and efficient processing
- **Real-time Updates**: Progress bars with shimmer animations

**No Manual Configuration Required**

The plugin automatically optimizes batch sizes based on:

- Server execution time limits
- Available memory
- Dataset size
- Processing complexity

### Server Configuration

**.htaccess Settings** (if needed):

```apache
# Increase upload limits
php_value upload_max_filesize 64M
php_value post_max_size 64M
php_value max_execution_time 300
php_value memory_limit 256M

# Enable output buffering
php_flag output_buffering on
php_value output_buffering 4096
```

**php.ini Settings** (if accessible):

```ini
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
memory_limit = 256M
output_buffering = 4096
```

## First Steps

### Test Import

1. **Create Test CSV**:

```csv
ID,post_title,post_content,post_status
"","Test Post 1","This is test content","publish"
"","Test Post 2","More test content","draft"
```

**Note**: The `ID` column is required. Use empty values (`""`) for new posts, or actual post IDs for updates.

2. **Import Process**:
    - Navigate to **Tools → Swift CSV → Import**
    - Select post type
    - Upload test CSV
    - Watch progress bar animation
    - Verify results

### Test Export

1. **Export Process**:
    - Navigate to **Tools → Swift CSV → Export**
    - Select post type
    - Set export limit (try 5 posts)
    - Click **Export CSV**
    - Watch real-time progress with post titles
    - Download and verify CSV file

### Verify Features

- **Progress Bar**: Real-time progress with animations during processing
- **Real-time Details**: Individual post titles displayed
- **Localized Interface**: Natural language messages (English/Japanese)
- **Batch Processing**: Automatic for larger datasets
- **Multi-value Support**: Test with pipe-separated values

## Getting Help

### Support Resources

1. **Documentation**: Check `docs/` directory for detailed guides
2. **Troubleshooting**: See `docs/help.md` for common issues
3. **GitHub Issues**: Report bugs and request features at [GitHub Issues](https://github.com/firstelementjp/swift-csv/issues)

### Debug Information

When reporting issues, please include:

- **WordPress Version**: Current WordPress version
- **PHP Version**: Current PHP version
- **Plugin Version**: Swift CSV v0.9.8
- **Browser Information**: Browser and version
- **Error Messages**: Full error messages from browser console
- **Sample Data**: Small sample CSV file for testing

### Quick Debug Commands

```bash
# Check WordPress version
wp core version

# Check PHP version
php -v

# Check plugin status
wp plugin status swift-csv

# Check debug logs
tail -f wp-content/debug.log | grep "Swift CSV"
```

## Uninstallation

### Safe Removal

1. Deactivate Swift CSV from **Plugins**
2. Delete the plugin from the WordPress admin
3. Verify any temporary files or logs you care about before cleanup

## Next Steps

- Read [Getting Started](start.md)
- Review [Configuration](config.md)
- Check [Examples](example.md)
- See [Troubleshooting](help.md) if needed
