# 🐛 Troubleshooting

Common issues and solutions for Swift CSV plugin (v0.9.7).

## Quick Debug Steps

1. **Enable Debug Mode**: Set `WP_DEBUG` to true in `wp-config.php`
2. **Check Browser Console**: Look for JavaScript errors
3. **Review Error Logs**: Check `[Swift CSV]` prefixed messages
4. **Test with Small Files**: Start with simple CSV files
5. **Monitor Progress**: Watch real-time progress bars during processing

## Common Issues

### Import/Export Not Working

**Symptoms**:

- AJAX requests failing
- Progress bars not updating
- Shimmer animations not working
- "Import failed" or "Export failed" messages

**Solutions**:

1. Check WordPress AJAX nonce: verify nonce is properly passed
2. Enable debug logging: set `WP_DEBUG` to true
3. Check browser console for JavaScript errors
4. Verify file permissions on upload directory
5. Test with smaller datasets first

### Progress Bar Issues

**Symptoms**:

- Progress bar not showing
- No shimmer animations
- Progress stuck at 0% or 100%
- No real-time details displayed

**Current Implementation (v0.9.7)**:

- **Small datasets** (≤100 items): 1-item batches for precise progress
- **Large datasets** (>100 items): Batch processing for performance
- **Visual feedback**: 6 different shimmer animation patterns
- **Natural language**: Japanese status messages

**Solutions**:

1. Check if JavaScript files are loading correctly
2. Verify CSS animations are not blocked by browser settings
3. Test with different batch sizes
4. Check browser console for animation errors

### Character Encoding Issues

**Symptoms**:

- Japanese characters display incorrectly
- CSV files show garbled text
- Progress messages in wrong language

**Solutions**:

1. Ensure CSV files are saved as UTF-8
2. Check `mbstring` extension is enabled
3. Verify database charset is `utf8mb4`
4. Check translation files are loaded correctly

### Large File Processing

**Symptoms**:

- Timeouts with large files
- Memory errors
- Incomplete imports/exports
- Progress bar not updating

**Current Implementation (v0.9.7)**:

- **Import**: Adaptive batch processing (1-10 rows based on threshold)
- **Export**: Adaptive batch processing (1-500 posts based on threshold)
- **Smart calculation**: `min(actual_export_count, threshold)` determines batch size
- **Memory threshold**: 100 rows triggers batch processing

**Solutions**:

1. Files are automatically processed in chunks
2. Monitor progress bars for completion
3. Check server limits if issues persist
4. Verify batch size calculations are working

### License Status Issues

**Symptoms**:

- Incorrect license status messages
- License activation not working
- Server configuration errors

**Current Implementation (v0.9.7)**:

- **3 States Detected**: Not installed, Inactive, Server unconfigured
- **Natural Japanese**: Context-aware messaging
- **HTML Tags**: Proper `<code>` tag rendering in help text

**Common Messages**:

```
Swift CSV Pro is not installed. Please install Swift CSV Pro to use license features.
Swift CSV Pro is installed but not activated. Please activate Swift CSV Pro to use license features.
License server is not configured. Please contact support.
```

**Solutions**:

1. Check if Swift CSV Pro plugin is installed and activated
2. Verify license server configuration
3. Check translation files for proper message display
4. Test license activation with valid key

### Delimiter Detection Issues

**Symptom**: CSV columns not parsed correctly

**Cause**: Simple character count-based detection may fail with complex data

**Solutions**:

1. Use standard CSV format (comma, semicolon, or tab delimiters)
2. Ensure first row contains headers
3. Test with simple CSV files first
4. Consider manual delimiter specification for complex data

### UI/UX Issues

**Symptoms**:

- Buttons not responding
- Forms not submitting
- Visual elements misaligned
- Animations not working

**Current Implementation (v0.9.7)**:

- **Responsive Design**: Mobile-friendly interface
- **GPU Acceleration**: CSS animations optimized
- **Real-time Updates**: AJAX-based progress tracking
- **Natural Language**: Japanese localization

**Solutions**:

1. Check CSS files are loading correctly
2. Verify JavaScript modules are not blocked
3. Test on different browsers
4. Check for CSS conflicts with other plugins

## Debug Logging

### Enable Debug Mode

Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Common Debug Messages

**Progress Tracking**:

```
[Swift CSV] Export started: 1000 posts total
[Swift CSV] Row 1 processed: "Sample Post Title"
[Swift CSV] Batch completed: 500/1000 posts
[Swift CSV] Export completed successfully
```

**License Status**:

```
[Swift CSV] License check: Pro plugin not installed
[Swift CSV] License activation: Server not configured
[Swift CSV] License validation: Invalid key format
```

**Error Handling**:

```
[Swift CSV] Import error: Invalid CSV format
[Swift CSV] Export error: Memory limit exceeded
[Swift CSV] AJAX error: Invalid nonce
```

## Performance Optimization

### Recommended Settings

**Small Datasets** (<100 items):

- Real-time progress tracking
- 1-item batches for precision
- Full detail display

**Large Datasets** (>100 items):

- Batch processing for performance
- 10-item (import) / 500-item (export) batches
- Summary progress display

### Server Requirements

- **WordPress**: 6.0 or higher
- **PHP**: 8.0 or higher
- **Memory**: 128MB+ for large CSV processing
- **Extensions**: `mbstring`, `curl` for license features

## Getting Help

### Support Channels

1. **GitHub Issues**: Report bugs and feature requests
2. **Documentation**: Check docs/ directory for detailed guides
3. **Debug Logs**: Provide `[Swift CSV]` prefixed log messages
4. **Test Cases**: Include sample CSV files for reproduction

### Information to Include

When reporting issues, please include:

1. **WordPress Version**: Current WordPress version
2. **PHP Version**: Current PHP version
3. **Plugin Version**: Swift CSV v0.9.7
4. **Error Messages**: Full error messages from browser console
5. **Debug Logs**: Relevant `[Swift CSV]` log entries
6. **Sample Data**: Small sample CSV file for testing
7. **Browser Information**: Browser and version being used

### Common Debug Commands

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

1. Use simple CSV files for reliable auto-detection
2. Ensure consistent delimiter usage throughout file
3. Test with small sample files first

## Development Environment Issues

### WP-CLI Connection Problems

**Symptoms**:

- `wp db query` fails
- Environment variables not working

**Solutions**:

```bash
# Check database variables (unquoted)
export DB_HOST=localhost
export DB_NAME=your_db_name

# Check paths with spaces (quoted)
export WP_PATH="/path with spaces"
```

### CSS/JS Not Loading

**Symptoms**:

- Styles not applied
- JavaScript not working

**Solutions**:

1. Run `npm run build` to generate minified assets
2. Check file permissions
3. Verify assets are properly enqueued

## Error Messages

### AJAX Errors

**"Import failed" or "Export failed"**:

1. Check browser console for details
2. Look for `[Swift CSV]` debug logs
3. Verify nonce is valid

### File Upload Errors

**"File size exceeds limit"**:

1. Check PHP `upload_max_filesize` and `post_max_size`
2. Split large CSV files into smaller chunks
3. Verify file format is valid CSV

### Progress Bar Issues

**"Progress element not found"**:

1. Clear browser cache
2. Check for JavaScript conflicts
3. Verify UI elements are properly loaded

## Debug Information

### Enable Debug Logging

Add to `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

### Check Debug Logs

```bash
# View WordPress debug logs
tail -f /path/to/wordpress/wp-content/debug.log | grep "\[Swift CSV\]"
```

### Browser Console Debugging

1. Open browser developer tools
2. Check Console tab for errors
3. Monitor Network tab for AJAX requests
4. Look for `[Swift CSV]` log messages

## Getting Help

### Self-Service Resources

1. **SKILL.md**: Detailed troubleshooting knowledge base
2. **Configuration Guide**: Check docs/config.md
3. **Changelog**: Review recent changes in docs/changes.md

### Report Issues

When reporting issues, include:

1. **WordPress Version**: e.g., 6.4.2
2. **PHP Version**: e.g., 8.1.0
3. **Plugin Version**: e.g., 0.9.5
4. **Error Logs**: `[Swift CSV]` prefixed messages
5. **Steps to Reproduce**: Detailed reproduction steps
6. **Sample Data**: Small CSV file that demonstrates issue

### Support Channels

- **GitHub Issues**: [Create new issue](https://github.com/firstelementjp/swift-csv/issues)
- **Documentation**: Check docs/ directory for detailed guides
- **Community**: Review existing issues for similar problems
