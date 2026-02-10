# JavaScript Pitfalls

## #002 Progress Element Not Found (2026-02-06)

**Symptom**: Console error `Progress element not found!` after UI refactor.

**Cause**: JS was looking for old `#swift-csv-ajax-export-progress` element, but the new UI uses `.swift-csv-progress`.

**Fix** (`swift-csv-export.js`):

```javascript
// BEFORE
const progressContainer = document.querySelector('#swift-csv-ajax-export-progress');

// AFTER
const container = document.querySelector('.swift-csv-progress');
if (!container) return;
const progressFill = container.querySelector('.progress-bar-fill');
```

**Lesson**: After any UI structure change, grep JS files for old selectors.

---

## #003 Export Filename Date Format Bug (2026-02-06)

**Symptom**: Filename shows `202602-06` instead of `2026-02-06` (missing hyphen between year and month).

**Cause**: String concatenation was missing `-` after `getFullYear()`.

**Fix** (`swift-csv-export.js`):

```javascript
// BEFORE
const dateStr = now.getFullYear() +
    String(now.getMonth() + 1).padStart(2, '0') + '-' + ...

// AFTER
const dateStr = now.getFullYear() + '-' +
    String(now.getMonth() + 1).padStart(2, '0') + '-' + ...
```

**Lesson**: Always test date formatting with actual output. Easy to miss in concatenation chains.

---

## #006 Export Cancellation Not Working (2026-02-10)

**Symptom**: Export continues running after clicking cancel, or cancellation flags accumulate across sessions.

**Cause**: Three issues:
1. WordPress option cache prevents immediate flag detection
2. Cancellation flags not isolated per export session
3. Multiple cancel handlers registered simultaneously

**Fix** (PHP — `class-swift-csv-ajax-export.php`):

```php
// BEFORE — Option cache issue
$is_cancelled = get_option("swift_csv_export_cancelled_{$user_id}");

// AFTER — Direct DB read to bypass cache
global $wpdb;
$is_cancelled = $wpdb->get_var($wpdb->prepare(
    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
    "swift_csv_export_cancelled_{$user_id}_{$export_session}"
));
```

**Fix** (JS — `swift-csv-export.js`):

```javascript
// BEFORE — Multiple event handlers accumulate
cancelBtn.addEventListener('click', cancelHandler);

// AFTER — Clean up previous handler first
if (exportCancelHandler) {
    cancelBtn.removeEventListener('click', exportCancelHandler);
}
exportCancelHandler = function() { /* ... */ };
cancelBtn.addEventListener('click', exportCancelHandler, { once: true });
```

**Lesson**: Use direct DB reads for time-sensitive flags. Always clean up event listeners. Use session IDs to isolate concurrent operations.

---

## #008 UI Disappears After JS Modularization (2026-02-10)

**Symptom**: Log window, progress bar, and download button disappear on page load. DOM shows `.swift-csv-log` container is empty.

**Cause**: Three compounding issues:
1. `initLoggingSystem()` used `.swift-csv-log` (parent container) instead of `#export-log-content` (child), clearing all children including buttons and progress bar
2. `addLogEntry()` also targeted the wrong container
3. Modules tried to initialize before they were loaded

**Fix** (`swift-csv-core.js` — don't clear parent):

```javascript
// BEFORE — Destroys all child elements
function initLoggingSystem() {
    const logContainer = document.querySelector('.swift-csv-log');
    logContainer.innerHTML = '';
}

// AFTER — Preserve HTML structure
function initLoggingSystem() {
    // Don't clear logs on page load - preserve initial messages
}
```

**Fix** (`swift-csv-main.js` — correct selectors):

```javascript
// BEFORE — Wrong selector
const logContainer = document.querySelector('.swift-csv-log');
logContainer.innerHTML = '';

// AFTER — Target specific child
const logContent = document.querySelector(`#${context}-log-content`);
if (logContent) logContent.innerHTML = '';
```

**Fix** (`swift-csv-main.js` — wait for modules):

```javascript
const checkModules = () => {
    if (window.SwiftCSVCore && window.SwiftCSVExport && window.SwiftCSVImport && window.SwiftCSVLicense) {
        // Safe to initialize
    } else {
        setTimeout(checkModules, 50);
    }
};
```

**Lesson**: Always use specific child selectors for DOM manipulation. Never `innerHTML = ''` on a parent container that holds multiple UI elements. Wait for all module globals before initialization.
