# 🐛 Troubleshooting

Common issues and solutions for Swift CSV plugin.

## Quick Debug Steps

1. **Enable Debug Mode**: Set `WP_DEBUG` to true in `wp-config.php`
2. **Check Browser Console**: Look for JavaScript errors
3. **Review Error Logs**: Check `[Swift CSV]` prefixed messages
4. **Test with Small Files**: Start with simple CSV files

## Common Issues

### Import/Export Not Working

**Symptoms**:

- AJAX requests failing
- Progress bars not updating
- "Import failed" or "Export failed" messages

**Solutions**:

1. Check WordPress AJAX nonce: verify nonce is properly passed
2. Enable debug logging: set `WP_DEBUG` to true
3. Check browser console for JavaScript errors
4. Verify file permissions on upload directory

### Character Encoding Issues

**Symptoms**:

- Japanese characters display incorrectly
- CSV files show garbled text

**Solutions**:

1. Ensure CSV files are saved as UTF-8
2. Check `mbstring` extension is enabled
3. Verify database charset is `utf8mb4`

### Large File Processing

**Symptoms**:

- Timeouts with large files
- Memory errors
- Incomplete imports/exports

**Current Implementation**:

- **Import**: Automatic batch processing (10 rows per batch)
- **Export**: Automatic batch processing (500 posts per batch)

**Solutions**:

1. Files are automatically processed in chunks
2. Monitor progress bars for completion
3. Check server limits if issues persist

### Delimiter Detection Issues

**Symptom**: CSV columns not parsed correctly

**Cause**: Simple character count-based detection may fail with complex data

**Solutions**:

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
