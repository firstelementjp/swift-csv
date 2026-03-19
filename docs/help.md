# 🐛 Troubleshooting

Common issues and solutions for the current Swift CSV implementation.

## Quick Checks

1. Enable `WP_DEBUG` and `WP_DEBUG_LOG`
2. Check the browser console for JavaScript errors
3. Test with a small CSV first
4. Confirm your CSV headers match the expected format
5. Confirm you are using **Tools → Swift CSV**

## Common Issues

### Import Fails Immediately

Check the following:

- The CSV includes an `ID` column
- The first row contains headers
- The file is a valid CSV
- The selected post type is correct
- Nonce or capability checks are not being blocked by custom code

### New Posts Are Not Created

If you are creating new posts:

- Keep the `ID` column present
- Leave the `ID` value empty for new rows
- Do not use dummy numeric IDs for new posts

### Existing Posts Are Not Updated

For updates:

- Use a real existing WordPress post ID
- Enable the update-existing option in the import UI
- Confirm the selected post type matches the existing post

### Custom Fields Do Not Import

Check the following:

- Custom field headers start with `cf_`
- Multi-value custom fields use `|`
- CSV values are quoted correctly when they contain commas

### Taxonomy Values Do Not Import Correctly

Check the following:

- Hierarchical taxonomy values use `>`
- Multiple taxonomy values use `|`
- The taxonomy exists for the selected post type

### Progress UI Does Not Update

Check the following:

- Admin JavaScript loaded correctly
- Browser console has no fatal errors
- AJAX requests are completing successfully
- You waited for the request cycle to finish before navigating away

### Large Files Feel Slow

Swift CSV already uses automatic batch processing.

If processing is still slow:

- Test with a smaller dataset first
- Check server memory and execution limits
- Reduce the export limit if you only need a subset
- Use dry run to validate import files before full processing

### Text Looks Garbled

Check the following:

- Save the CSV as UTF-8
- Confirm `mbstring` is enabled
- Confirm WordPress/database charset is `utf8mb4`
- Confirm translation files are present if localized UI text is expected

### Pro License UI Looks Incorrect

Check the following:

- Swift CSV Pro is installed if you expect Pro features
- Swift CSV Pro is activated
- License-related server configuration is valid

## Debug Logging

Add to `wp-config.php` if needed:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Useful checks:

```bash
wp core version
php -v
wp plugin status swift-csv
tail -f wp-content/debug.log | grep "Swift CSV"
```

## When Reporting an Issue

Include:

- WordPress version
- PHP version
- Swift CSV version
- Browser and version
- Exact error messages
- A small sample CSV when possible
- Whether the issue occurs on import, export, or both

## Related Pages

- [Installation](install.md)
- [Configuration](config.md)
- [Examples](example.md)
- [Developer Hooks](hooks.md)
