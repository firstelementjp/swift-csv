# Security Pitfalls

## #007 Temporary CSV Files Security Risk (2026-02-10)

**Symptom**: CSV files remain in `wp-content/uploads/swift-csv-temp/` after import errors or completion, creating potential data exposure.

**Cause**: No cleanup mechanism for temporary import files on error paths.

**Fix** (`class-swift-csv-ajax-import.php` — cleanup on all paths):

```php
$file_path = sanitize_text_field(wp_unslash($_POST['file_path'] ?? ''));

// Cleanup on completion
if (!$continue && $file_path && file_exists($file_path)) {
    unlink($file_path);
}

// Cleanup on EVERY error path (before wp_send_json)
if ($file_path && file_exists($file_path)) {
    unlink($file_path);
}
wp_send_json(['success' => false, 'error' => $message]);
```

**Fix** (`swift-csv.php` — activation hook protections):

```php
// .htaccess to deny web access
$htaccess_file = $temp_dir . '/.htaccess';
if (!file_exists($htaccess_file)) {
    file_put_contents($htaccess_file, "Deny from all\n");
}

// Periodic cleanup of files older than 24 hours
$files = glob($temp_dir . '/*.csv');
if ($files) {
    foreach ($files as $file) {
        if (time() - filemtime($file) > 86400) {
            unlink($file);
        }
    }
}
```

**Lesson**: Always implement cleanup for temporary files on ALL code paths (success, error, exception). Add defense-in-depth with `.htaccess` and periodic cleanup.
