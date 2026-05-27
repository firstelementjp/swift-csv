/**
 * FE CSV Import & Export Admin Scripts - Main Entry Point
 *
 * Loads and initializes all FE CSV Import & Export modules.
 *
 */

// Import core modules
import './fe-csv-import-export-core.js';

// Import export modules
import './export/fe-csv-import-export/ajax.js';
import './export/fe-csv-import-export/download.js';
import './export/fe-csv-import-export/form.js';
import './export/fe-csv-import-export/ui.js';
import './export/fe-csv-import-export/logs.js';
import './fe-csv-import-export-export-unified.js';
import './export/fe-csv-import-export/original.js';
import './fe-csv-import-export-import.js';
import './fe-csv-import-export-license.js';

/**
 * Bootstrap FE CSV Import & Export admin scripts when the DOM is ready.
 */
document.addEventListener('DOMContentLoaded', function () {
	// Log initialization (debug mode only)
	if (
		window.FeCsvImportExportCore &&
		window.feCsvImportExport &&
		window.feCsvImportExport.debug
	) {
		FeCsvImportExportCore.feCsvImportExportLog('JavaScript initialized');
	}

	// Initialize logging system
	if (window.FeCsvImportExportCore) {
		FeCsvImportExportCore.initLoggingSystem();
	}

	/**
	 * Initialize FE CSV Import & Export modules once dependencies are available.
	 */
	const initializeModules = () => {
		/**
		 * Verify module availability; retry until import/export modules are ready.
		 */
		const checkModules = () => {
			if (
				window.FeCsvImportExportCore &&
				window.FeCsvImportExportExport &&
				window.FeCsvImportExportImport
			) {
				const advancedSaveButton = document.getElementById(
					'fe-csv-import-export-save-all-settings'
				);
				if (
					advancedSaveButton &&
					window.feCsvImportExport &&
					!window.feCsvImportExport.hasProAdmin &&
					advancedSaveButton.dataset.feCsvImportExportBound !== 'true'
				) {
					advancedSaveButton.dataset.feCsvImportExportBound = 'true';
					advancedSaveButton.addEventListener('click', function () {
						const spinner = advancedSaveButton.nextElementSibling;
						const enableLogsCheckbox = document.getElementById(
							'fe_csv_import_export_advanced_enable_logs'
						);
						const enableLogs =
							enableLogsCheckbox && enableLogsCheckbox.checked ? '1' : '0';

						advancedSaveButton.disabled = true;
						if (spinner) {
							spinner.style.display = 'inline-block';
						}

						fetch(window.feCsvImportExport.ajaxUrl, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded',
							},
							body: new URLSearchParams({
								action: 'fe_csv_import_export_save_advanced_settings',
								nonce: window.feCsvImportExport.nonce,
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

								if (!window.feCsvImportExport.advancedSettings) {
									window.feCsvImportExport.advancedSettings = {};
								}
								window.feCsvImportExport.advancedSettings.enableLogs =
									enableLogs === '1';
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
				if (window.FeCsvImportExportImport) {
					window.FeCsvImportExportImport.initFileUpload();
				}

				// Ajax export functionality
				const ajaxExportForm = document.querySelector(
					'#fe-csv-import-export-ajax-export-form'
				);
				if (ajaxExportForm && window.FeCsvImportExportExport) {
					if (
						window.FeCsvImportExportCore &&
						window.feCsvImportExport &&
						window.feCsvImportExport.debug
					) {
						FeCsvImportExportCore.feCsvImportExportLog('Ajax export form initialized');
					}
					ajaxExportForm.addEventListener(
						'submit',
						window.FeCsvImportExportExport.handleAjaxExport
					);
				}

				// Ajax import functionality
				const ajaxImportForm = document.querySelector(
					'#fe-csv-import-export-ajax-import-form'
				);
				if (ajaxImportForm && window.FeCsvImportExportImport) {
					if (
						window.FeCsvImportExportCore &&
						window.feCsvImportExport &&
						window.feCsvImportExport.debug
					) {
						FeCsvImportExportCore.feCsvImportExportLog('Ajax import form initialized');
					}
					ajaxImportForm.addEventListener(
						'submit',
						window.FeCsvImportExportImport.handleAjaxImport
					);
				}

				// License functionality (optional)
				if (window.FeCsvImportExportLicense) {
					window.FeCsvImportExportLicense.initLicense();
				}

				const taxonomyHierarchyCheckbox = document.getElementById(
					'fe_csv_import_export_taxonomy_hierarchical'
				);
				const taxonomyFormatRadios = document.querySelectorAll(
					'input[name="taxonomy_format"]'
				);
				/**
				 * Enable or disable the taxonomy hierarchy checkbox based on active format.
				 */
				const updateTaxonomyHierarchyState = () => {
					if (!taxonomyHierarchyCheckbox) {
						return;
					}
					const activeFormat = document.querySelector(
						'input[name="taxonomy_format"]:checked'
					)?.value;
					const shouldEnable = 'name' === activeFormat;
					taxonomyHierarchyCheckbox.disabled = !shouldEnable;
					if (!shouldEnable) {
						taxonomyHierarchyCheckbox.checked = false;
					}
				};
				if (taxonomyFormatRadios.length > 0 && taxonomyHierarchyCheckbox) {
					taxonomyFormatRadios.forEach(radio => {
						radio.addEventListener('change', updateTaxonomyHierarchyState);
					});
					updateTaxonomyHierarchyState();
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
		logContent = document.querySelector(
			'.fe-csv-import-export-logs-area .log-panel.active .log-content'
		);
		if (!logContent) {
			logContent = document.querySelector(
				'.fe-csv-import-export-logs-area .log-panel[data-panel="created"] .log-content'
			);
		}
	} else if ('export' === context) {
		// Use new unified structure for export
		logContent = document.querySelector(
			'.fe-csv-import-export-logs-area .log-panel[data-panel="export"] .log-content'
		);
	}
	if (!logContent) {
		logContent = document.querySelector(`#${context}-log-content`);
	}
	if (!logContent) {
		return;
	}

	// Get max log entries from localized data (default: 30)
	const maxLogEntries = feCsvImportExport.maxLogEntries || 30;

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
				'.fe-csv-import-export-logs-area .log-panel .log-content'
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
window.addLogEntry = addLogEntry;
window.clearLog = clearLog;
window.FeCsvImportExportUtils = {
	addLogEntry,
	clearLog,
};
