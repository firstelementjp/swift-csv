/**
 * Swift CSV Admin Scripts - Main Entry Point
 *
 * Loads and initializes all Swift CSV modules.
 *
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

	// Initialize modules when available
	const initializeModules = () => {
		// Wait for all modules to be loaded
		const checkModules = () => {
			if (window.SwiftCSVCore && window.SwiftCSVExport && window.SwiftCSVImport) {
				const advancedSaveButton = document.getElementById('swift-csv-save-all-settings');
				if (
					advancedSaveButton &&
					window.swiftCSV &&
					!window.swiftCSV.hasProAdmin &&
					advancedSaveButton.dataset.swiftCsvBound !== 'true'
				) {
					advancedSaveButton.dataset.swiftCsvBound = 'true';
					advancedSaveButton.addEventListener('click', function () {
						const spinner = advancedSaveButton.nextElementSibling;
						const enableLogsCheckbox = document.getElementById(
							'swift_csv_advanced_enable_logs'
						);
						const enableLogs =
							enableLogsCheckbox && enableLogsCheckbox.checked ? '1' : '0';

						advancedSaveButton.disabled = true;
						if (spinner) {
							spinner.style.display = 'inline-block';
						}

						fetch(window.swiftCSV.ajaxUrl, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded',
							},
							body: new URLSearchParams({
								action: 'swift_csv_save_advanced_settings',
								nonce: window.swiftCSV.nonce,
								enable_logs: enableLogs,
							}),
						})
							.then(response => response.json())
							.then(data => {
								if (!data || !data.success) {
									throw new Error(
										(data && data.data && data.data.message) ||
											'Failed to save settings.'
									);
								}

								if (!window.swiftCSV.advancedSettings) {
									window.swiftCSV.advancedSettings = {};
								}
								window.swiftCSV.advancedSettings.enableLogs = enableLogs === '1';
								window.alert(data.data.message);
							})
							.catch(error => {
								window.alert(error.message || 'Failed to save settings.');
							})
							.finally(() => {
								advancedSaveButton.disabled = false;
								if (spinner) {
									spinner.style.display = 'none';
								}
							});
					});
				}

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
 * @param {string} level   - Log level (info, success, warning, error, debug)
 * @param {string} context - Log context (export, import)
 */
function addLogEntry(message, level = 'info', context = 'export') {
	let logContent = null;
	if ('import' === context) {
		logContent = document.querySelector('.swift-csv-logs-area .log-panel.active .log-content');
		if (!logContent) {
			logContent = document.querySelector(
				'.swift-csv-logs-area .log-panel[data-panel="created"] .log-content'
			);
		}
	} else if ('export' === context) {
		// Use new unified structure for export
		logContent = document.querySelector(
			'.swift-csv-logs-area .log-panel[data-panel="export"] .log-content'
		);
	}
	if (!logContent) {
		logContent = document.querySelector(`#${context}-log-content`);
	}
	if (!logContent) return;

	// Get max log entries from localized data (default: 30)
	const maxLogEntries = swiftCSV.maxLogEntries || 30;

	// Keep only latest entries to prevent performance issues
	if (logContent.children.length >= maxLogEntries) {
		logContent.removeChild(logContent.firstChild);
	}

	const escapeHtml = function (str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	};

	const splitMetaAndTitle = function (text) {
		const msg = String(text || '');
		if (msg.startsWith('[')) {
			const closeIndex = msg.indexOf(']');
			if (closeIndex > 0) {
				return {
					meta: msg.slice(0, closeIndex + 1),
					title: msg.slice(closeIndex + 1),
				};
			}
		}
		return { meta: '', title: msg };
	};

	const logEntry = document.createElement('div');
	logEntry.className = `log-entry log-${level} log-${context}`;

	const timestamp = new Date().toLocaleTimeString();
	const parts = splitMetaAndTitle(message);
	const safeMeta = escapeHtml(parts.meta);
	const safeTitle = escapeHtml(parts.title);
	logEntry.innerHTML = `<span class="log-time">[${timestamp}]</span><span class="log-message"><span class="log-meta">${safeMeta}</span><span class="log-title">${safeTitle}</span></span>`;

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
		if ('import' === context) {
			const logContents = document.querySelectorAll(
				'.swift-csv-import-logs .log-panel .log-content'
			);
			if (logContents.length) {
				logContents.forEach(content => (content.innerHTML = ''));
				return;
			}
		}
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
