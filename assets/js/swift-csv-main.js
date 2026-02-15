/**
 * Swift CSV Admin Scripts - Main Entry Point
 *
 * Loads and initializes all Swift CSV modules.
 *
 * @package SwiftCSV
 */

document.addEventListener('DOMContentLoaded', function () {
	// Log initialization (debug mode only)
	if (window.SwiftCSVCore && window.swiftCSV && window.swiftCSV.debug) {
		SwiftCSVCore.swiftCSVLog('JavaScript initialized');
	}

	// Initialize logging system
	if (window.SwiftCSVCore) {
		SwiftCSVCore.initLoggingSystem();
	}

	// Export scope toggle for custom help
	const exportScopeRadios = document.querySelectorAll('input[name="swift_csv_export_scope"]');
	const customHelp = document.getElementById('custom-export-help');

	if (exportScopeRadios.length && customHelp) {
		if (window.SwiftCSVCore && window.swiftCSV && window.swiftCSV.debug) {
			SwiftCSVCore.swiftCSVLog('Export scope toggle initialized');
		}
		exportScopeRadios.forEach(radio => {
			radio.addEventListener('change', function () {
				customHelp.style.display = this.value === 'custom' ? 'block' : 'none';
			});
		});
	}

	// Post status toggle for custom help
	const postStatusRadios = document.querySelectorAll(
		'input[name="swift_csv_export_post_status"]'
	);
	const postStatusHelp = document.getElementById('custom-post-status-help');

	if (postStatusRadios.length && postStatusHelp) {
		if (window.SwiftCSVCore && window.swiftCSV && window.swiftCSV.debug) {
			SwiftCSVCore.swiftCSVLog('Post status toggle initialized');
		}
		postStatusRadios.forEach(radio => {
			radio.addEventListener('change', function () {
				postStatusHelp.style.display = this.value === 'custom' ? 'block' : 'none';
			});
		});
	}

	// Initialize modules when available
	const initializeModules = () => {
		// Wait for all modules to be loaded
		const checkModules = () => {
			if (window.SwiftCSVCore && window.SwiftCSVExport && window.SwiftCSVImport) {
				// All modules are available, initialize them
				if (window.SwiftCSVImport) {
					window.SwiftCSVImport.initFileUpload();
				}

				// Ajax export functionality
				const ajaxExportForm = document.querySelector('#swift-csv-ajax-export-form');
				if (ajaxExportForm && window.SwiftCSVExport) {
					if (window.SwiftCSVCore && window.swiftCSV && window.swiftCSV.debug) {
						SwiftCSVCore.swiftCSVLog('Ajax export form initialized');
					}
					ajaxExportForm.addEventListener(
						'submit',
						window.SwiftCSVExport.handleAjaxExport
					);
				}

				// Ajax import functionality
				const ajaxImportForm = document.querySelector('#swift-csv-ajax-import-form');
				if (ajaxImportForm && window.SwiftCSVImport) {
					if (window.SwiftCSVCore && window.swiftCSV && window.swiftCSV.debug) {
						SwiftCSVCore.swiftCSVLog('Ajax import form initialized');
					}
					ajaxImportForm.addEventListener(
						'submit',
						window.SwiftCSVImport.handleAjaxImport
					);
				}

				// License functionality (optional)
				if (window.SwiftCSVLicense) {
					window.SwiftCSVLicense.initLicense();
				}
			} else {
				// Modules not ready, retry after short delay
				setTimeout(checkModules, 50);
			}
		};

		checkModules();
	};

	// Wait for modules to be loaded
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeModules);
	} else {
		// Fallback: try immediately
		setTimeout(initializeModules, 100);
	}
});

/**
 * Add log entry to the log display
 *
 * @param {string} message - The log message
 * @param {string} level - Log level (info, success, warning, error, debug)
 * @param {string} context - Log context (export, import)
 */
function addLogEntry(message, level = 'info', context = 'export') {
	const logContent = document.querySelector(`#${context}-log-content`);
	if (!logContent) return;

	const logEntry = document.createElement('div');
	logEntry.className = `log-entry log-${level} log-${context}`;

	const timestamp = new Date().toLocaleTimeString();
	logEntry.innerHTML = `<span class="log-time">[${timestamp}]</span> <span class="log-message">${message}</span>`;

	logContent.appendChild(logEntry);
	logContent.scrollTop = logContent.scrollHeight;
}

/**
 * Clear log entries
 *
 * @param {string} context - Log context to clear (export, import, or all)
 */
function clearLog(context = 'all') {
	if (context === 'all') {
		const logContents = document.querySelectorAll('.log-content');
		logContents.forEach(content => (content.innerHTML = ''));
	} else {
		const logContent = document.querySelector(`#${context}-log-content`);
		if (logContent) {
			logContent.innerHTML = '';
		}
	}
}

// Export utility functions for modules
window.SwiftCSVUtils = {
	addLogEntry,
	clearLog,
};
