# 📦 Installation

## Install from WordPress Admin

1. Log in to WordPress admin
2. Navigate to **Plugins** → **Add New**
3. Search for "Swift CSV"
4. Click **Install Now**
5. Click **Activate**

## Manual Installation

1. [Download latest version (v0.9.7)](https://github.com/firstelementjp/swift-csv/releases/download/v0.9.7/swift-csv-v0.9.7.zip)
2. Extract the downloaded ZIP file
3. Upload `swift-csv` folder to `/wp-content/plugins/`
4. Navigate to **Plugins** in WordPress admin
5. Find "Swift CSV" and click **Activate**

## Requirements

### System Requirements

- **WordPress**: 6.0 or higher (recommended: 6.4+)
- **PHP**: 8.0 or higher (recommended: 8.1+)
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

1. **Access Plugin**: Navigate to **Swift CSV** in the WordPress admin menu
2. **Configure Settings**: Set default post types and export limits
3. **Test Import**: Try importing a small CSV file first
4. **Test Export**: Export a few posts to verify functionality
5. **Check Progress**: Watch the beautiful progress bar animations during processing

### Basic Configuration

1. **Default Post Type**: Choose which post type to work with by default
2. **Export Limit**: Set maximum number of posts per export (0 = unlimited)
3. **Batch Processing**: Automatic for 100+ items (adaptive: 1-500 posts)
4. **Debug Mode**: Enable `WP_DEBUG` for troubleshooting

### Advanced Features (v0.9.7)

- **Progress Tracking**: Real-time progress with shimmer animations
- **Batch Processing**: Adaptive batch sizes (1-500 posts based on dataset size)
- **Japanese Localization**: Complete Japanese language support
- **License Integration**: Pro version license management
- **Multi-value Fields**: Pipe-separated values support (e.g., "value1|value2|value3")

## Troubleshooting Installation

### Common Issues

**Plugin not found in WordPress directory:**

- Verify the `swift-csv` folder is in `/wp-content/plugins/`
- Check folder permissions (755 recommended)
- Ensure all files are uploaded completely

**Activation fails:**

- Check PHP version compatibility (8.0+ required)
- Verify required PHP extensions are installed
- Check WordPress version compatibility (6.0+ required)

**Memory errors during import/export:**

- Increase PHP memory limit in `wp-config.php`: `define('WP_MEMORY_LIMIT', '256M');`
- Enable batch processing (automatic for 100+ items)
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

**Recommended Settings:**

- **Export Limit**: 1000 posts per batch
- **Memory Limit**: 256MB or higher
- **Processing Time**: 300 seconds or higher
- **Batch Size**: Automatic (1-500 posts based on dataset size)

**Automatic Optimizations:**

- **Small Datasets** (≤100 posts): 1-item batches for precise progress
- **Large Datasets** (>100 posts): 500-item batches for performance
- **Memory Threshold**: 100 posts triggers batch processing
- **Real-time Updates**: Progress bars with shimmer animations

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
post_title,post_content,post_status
"Test Post 1","This is test content","publish"
"Test Post 2","More test content","draft"
```

2. **Import Process**:
    - Navigate to **Swift CSV → Import**
    - Select post type
    - Upload test CSV
    - Watch progress bar animation
    - Verify results

### Test Export

1. **Export Process**:
    - Navigate to **Swift CSV → Export**
    - Select post type
    - Set export limit (try 5 posts)
    - Click **Export CSV**
    - Watch real-time progress with post titles
    - Download and verify CSV file

### Verify Features

- **Progress Bar**: Beautiful shimmer animations during processing
- **Real-time Details**: Individual post titles displayed
- **Japanese Messages**: Natural Japanese status messages
- **Batch Processing**: Automatic for larger datasets
- **Multi-value Support**: Test with pipe-separated values

## Getting Help

### Support Resources

1. **Documentation**: Check `docs/` directory for detailed guides
2. **Troubleshooting**: See `docs/help.md` for common issues
3. **GitHub Issues**: Report bugs at [GitHub Issues](https://github.com/firstelementjp/swift-csv/issues)
4. **Community**: Join discussions at [GitHub Discussions](https://github.com/firstelementjp/swift-csv/discussions)

### Debug Information

When reporting issues, please include:

- **WordPress Version**: Current WordPress version
- **PHP Version**: Current PHP version
- **Plugin Version**: Swift CSV v0.9.7
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

## Version History

### v0.9.7 (Current Release)

**Major Features:**

- Complete progress bar UI with shimmer animations
- Real-time export details with post titles
- Enhanced license status detection
- Complete Japanese localization
- Adaptive batch processing (1-500 posts)
- Multi-value custom field support

**Improvements:**

- Fixed export batch size calculation
- Enhanced error handling and messaging
- Improved performance for large datasets
- Better memory management
- Enhanced internationalization

### Previous Versions

- **v0.9.6**: Import refactoring and code cleanup
- **v0.9.5**: Debug logging system improvements
- **v0.9.4**: AJAX system complete rewrite
- **v0.9.3**: Real-time progress tracking
- **v0.9.2**: Initial beta release

## Upgrade Guide

### From v0.9.6 to v0.9.7

1. **Backup**: Backup current plugin files
2. **Download**: Get v0.9.7 from releases
3. **Replace**: Replace plugin folder with new version
4. **Activate**: Reactivate plugin if needed
5. **Test**: Verify all features work correctly

### Automatic Updates

- **WordPress.org**: Automatic updates available
- **Pro Version**: One-click updates from admin
- **Notifications**: Update notifications in admin dashboard

## Uninstallation

### Safe Removal

1. **Deactivate**: Navigate to **Plugins** → **Swift CSV** → **Deactivate**
2. **Delete**: Click **Delete** to remove plugin files
3. **Data**: Plugin data is automatically cleaned up

### Data Cleanup

The plugin automatically cleans up:

- **Options**: All plugin options are removed
- **Transients**: Temporary data is cleared
- **Sessions**: Import/export sessions are cleaned
- **Logs**: Debug logs are preserved in WordPress logs

## Security Considerations

### File Uploads

- **Validation**: All uploaded files are validated
- **Sanitization**: Data is properly sanitized
- **Permissions**: File permissions are checked
- **Size Limits**: Upload size limits are enforced

### Data Processing

- **Escaping**: All data is properly escaped
- **Validation**: Input validation is performed
- **Sanitization**: WordPress sanitization functions used
- **Security**: Follows WordPress security standards

### Best Practices

- **Regular Updates**: Keep plugin updated
- **Backups**: Regular site backups
- **Monitoring**: Monitor error logs
- **Testing**: Test with sample data first

- Increase `memory_limit` in `php.ini`
- Enable batch processing for large files
- Split large CSV files into smaller chunks

**File upload issues:**

- Check `upload_max_filesize` in `php.ini`
- Verify `post_max_size` is sufficient
- Ensure proper file permissions on upload directory

### Server Configuration

For optimal performance with large CSV files, consider these `php.ini` settings:

```ini
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
max_input_time = 300
```

### WordPress Configuration

Add to `wp-config.php` for debugging:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Upgrade Instructions

### From Previous Versions

1. **Backup**: Export current data before upgrading
2. **Deactivate**: Deactivate the old version
3. **Replace**: Upload new version files
4. **Activate**: Reactivate the plugin
5. **Verify**: Test import/export functionality

### Automatic Updates

- Enable automatic updates in WordPress admin
- Check changelog for breaking changes
- Test functionality after each update

## Getting Help

If you encounter issues during installation:

1. **Check Requirements**: Verify all system requirements are met
2. **Enable Debug**: Set `WP_DEBUG` to true for error details
3. **Review Logs**: Check WordPress debug logs
4. **Visit Support**: [GitHub Issues](https://github.com/firstelementjp/swift-csv/issues)
5. **Documentation**: Review [Troubleshooting Guide](help.md)

## Next Steps

After successful installation:

1. Read the [Getting Started Guide](start.md)
2. Review [Configuration Options](config.md)
3. Check [Examples](example.md) for common use cases
4. Explore [Developer Hooks](hooks.md) for customization
