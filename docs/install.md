# 📦 Installation

## Install from WordPress Admin

1. Log in to WordPress admin
2. Navigate to **Plugins** → **Add New**
3. Search for "Swift CSV"
4. Click **Install Now**
5. Click **Activate**

## Manual Installation

1. [Download latest version](https://github.com/firstelementjp/swift-csv/releases/latest)
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

## Initial Setup

### After Activation

1. **Access Plugin**: Navigate to **Swift CSV** in the WordPress admin menu
2. **Configure Settings**: Set default post types and export limits
3. **Test Import**: Try importing a small CSV file first
4. **Test Export**: Export a few posts to verify functionality

### Basic Configuration

1. **Default Post Type**: Choose which post type to work with by default
2. **Export Limit**: Set maximum number of posts per export (0 = unlimited)
3. **Batch Processing**: Enable for large files (automatic for 1000+ items)
4. **Debug Mode**: Enable `WP_DEBUG` for troubleshooting

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
