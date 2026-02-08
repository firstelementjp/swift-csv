/**
 * Swift CSV Admin Scripts
 *
 * @package SwiftCSV
 */

// WordPress i18n API with proper fallback
function __(text, domain = 'swift-csv') {
	if (window.wp && window.wp.i18n && window.wp.i18n.__) {
		return window.wp.i18n.__(text, domain);
	}
	// Fallback: return original text (will be translated by PHP if needed)
	return text;
}

document.addEventListener('DOMContentLoaded', function () {
	// Initialize logging system
	initLoggingSystem();

	// Export scope toggle for custom help
	const exportScopeRadios = document.querySelectorAll('input[name="export_scope"]');
	const customHelp = document.getElementById('custom-export-help');

	if (exportScopeRadios.length && customHelp) {
		exportScopeRadios.forEach(radio => {
			radio.addEventListener('change', function () {
				customHelp.style.display = this.value === 'custom' ? 'block' : 'none';
			});
		});
	}

	// Batch export functionality
	const exportBtn = document.querySelector('#export-csv-btn');
	if (exportBtn) {
		exportBtn.addEventListener('click', function (e) {
			e.preventDefault();
			const postType = document.querySelector('#post_type')?.value;
			const postsPerPage = document.querySelector('#posts_per_page')?.value;

			if (!postType || !postsPerPage || postsPerPage < 1) {
				addLogEntry(swiftCSV.messages.errorOccurred, 'error');
				return;
			}

			startBatchExport(postType, postsPerPage);
		});
	}

	// Ajax export functionality
	const ajaxExportForm = document.querySelector('#swift-csv-ajax-export-form');
	if (ajaxExportForm) {
		ajaxExportForm.addEventListener('submit', handleAjaxExport);
	}

	// Ajax import functionality
	const ajaxImportForm = document.querySelector('#swift-csv-ajax-import-form');
	if (ajaxImportForm) {
		ajaxImportForm.addEventListener('submit', handleAjaxImport);
	}

	// File upload functionality
	initFileUpload();
});

/**
 * Initialize logging system
 */
function initLoggingSystem() {
	// Clear logs on page load
	const exportLogContent = document.querySelector('#export-log-content');
	const importLogContent = document.querySelector('#import-log-content');

	if (exportLogContent) {
		exportLogContent.innerHTML =
			'<div class="log-entry log-info">' +
			(swiftCSV.messages.readyToExport || 'Ready to start export...') +
			'</div>';
	}

	if (importLogContent) {
		importLogContent.innerHTML =
			'<div class="log-entry log-info">' +
			(swiftCSV.messages.readyToImport || 'Ready to start import...') +
			'</div>';
	}
}

/**
 * Add log entry to the log area
 *
 * @param {string} message Log message
 * @param {string} level Log level (info, success, warning, error, debug)
 * @param {string} context Log context (export, import)
 */
function addLogEntry(message, level = 'info', context = 'export') {
	const logContent = document.querySelector(`#${context}-log-content`);
	if (!logContent) return;

	const logEntry = document.createElement('div');
	logEntry.className = `log-entry log-${level}`;

	const timestamp = new Date().toLocaleTimeString();
	logEntry.textContent = `[${timestamp}] ${message}`;

	logContent.appendChild(logEntry);

	// Auto-scroll to bottom
	logContent.scrollTop = logContent.scrollHeight;

	// Limit log entries to prevent memory issues
	const maxEntries = 100;
	const entries = logContent.querySelectorAll('.log-entry');
	if (entries.length > maxEntries) {
		entries[0].remove();
	}
}

/**
 * Clear log entries
 *
 * @param {string} context Log context (export, import)
 */
function clearLog(context = 'export') {
	const logContent = document.querySelector(`#${context}-log-content`);
	if (logContent) {
		logContent.innerHTML = '';
	}
}

/**
 * Initialize file upload functionality
 */
function initFileUpload() {
	const uploadArea = document.querySelector('#csv-file-upload');
	const fileInput = document.querySelector('#ajax_csv_file');
	const fileInfo = document.querySelector('#csv-file-info');
	const removeBtn = document.querySelector('#remove-file-btn');

	if (!uploadArea || !fileInput) return;

	// Click to upload
	uploadArea.addEventListener('click', () => {
		fileInput.click();
	});

	// Drag and drop
	uploadArea.addEventListener('dragover', e => {
		e.preventDefault();
		uploadArea.classList.add('dragover');
	});

	uploadArea.addEventListener('dragleave', () => {
		uploadArea.classList.remove('dragover');
	});

	uploadArea.addEventListener('drop', e => {
		e.preventDefault();
		uploadArea.classList.remove('dragover');

		const files = e.dataTransfer.files;
		if (files.length > 0) {
			handleFileSelect(files[0]);
		}
	});

	// File selection
	fileInput.addEventListener('change', e => {
		if (e.target.files.length > 0) {
			handleFileSelect(e.target.files[0]);
		}
	});

	// Remove file
	if (removeBtn) {
		removeBtn.addEventListener('click', () => {
			fileInput.value = '';
			uploadArea.style.display = 'block';
			fileInfo.style.display = 'none';
			addLogEntry(swiftCSV.messages.fileRemoved, 'info', 'import');
		});
	}

	/**
	 * Handle file selection
	 *
	 * @param {File} file Selected file
	 */
	function handleFileSelect(file) {
		// Validate file type
		if (!file.name.toLowerCase().endsWith('.csv')) {
			addLogEntry(swiftCSV.messages.selectCsvFile, 'error', 'import');
			return;
		}

		// Validate file size (10MB limit)
		const maxSize = 10 * 1024 * 1024; // 10MB
		if (file.size > maxSize) {
			addLogEntry(swiftCSV.messages.fileSizeExceedsLimit, 'error', 'import');
			return;
		}

		// Update UI
		const fileName = fileInfo.querySelector('.file-name');
		if (fileName) {
			fileName.textContent = file.name;
		}

		uploadArea.style.display = 'none';
		fileInfo.style.display = 'flex';

		addLogEntry(swiftCSV.messages.fileSelected + ' ' + file.name, 'success', 'import');
	}
}

function handleAjaxExport(e) {
	e.preventDefault();

	// Clear export log
	clearLog('export');
	addLogEntry(swiftCSV.messages.startingExport, 'info', 'export');

	const postType = document.querySelector('#ajax_export_post_type')?.value;
	const exportScope =
		document.querySelector('input[name="export_scope"]:checked')?.value || 'basic';
	const includePrivateMeta = document.querySelector('input[name="include_private_meta"]')?.checked
		? '1'
		: '0';
	const exportLimit = document.querySelector('#export_limit')?.value || '';

	// Log export settings
	addLogEntry(swiftCSV.messages.postTypeExport + ' ' + postType, 'debug', 'export');

	// Translate export scope
	const exportScopeText =
		exportScope === 'basic'
			? swiftCSV.messages.exportScopeBasic
			: swiftCSV.messages.exportScopeAll;
	addLogEntry(swiftCSV.messages.exportScope + ' ' + exportScopeText, 'debug', 'export');

	addLogEntry(
		swiftCSV.messages.includePrivateMeta +
			' ' +
			(includePrivateMeta === '1' ? swiftCSV.messages.yes : swiftCSV.messages.no),
		'debug',
		'export'
	);
	addLogEntry(
		swiftCSV.messages.exportLimit + ' ' + (exportLimit || swiftCSV.messages.noLimit),
		'debug',
		'export'
	);

	const exportBtn = document.querySelector('#ajax-export-csv-btn');
	const cancelBtn = document.querySelector('#ajax-export-cancel-btn');
	const startTime = Date.now();
	let isCancelled = false;

	// Update button states
	if (exportBtn) {
		exportBtn.disabled = true;
		exportBtn.value = swiftCSV.messages.exporting;
	}
	if (cancelBtn) {
		cancelBtn.style.display = 'inline-block';
	}

	// Reset download button to disabled state
	const downloadBtn = document.querySelector('#export-download-btn');
	if (downloadBtn) {
		downloadBtn.classList.remove('enabled');
		downloadBtn.href = '#';
		downloadBtn.removeAttribute('download');
	}

	let csvContent = '';

	// Cancel functionality
	if (cancelBtn) {
		cancelBtn.addEventListener('click', function () {
			isCancelled = true;
			addLogEntry(swiftCSV.messages.exportCancelledByUser, 'warning', 'export');

			if (exportBtn) {
				exportBtn.disabled = false;
				exportBtn.value = swiftCSV.messages.exportCsv;
			}
			if (cancelBtn) {
				cancelBtn.style.display = 'none';
			}
		});
	}

	function processChunk(startRow = 0) {
		if (isCancelled) return;

		addLogEntry(swiftCSV.messages.processingChunk + ' ' + startRow, 'debug', 'import');

		const formData = new URLSearchParams({
			action: 'swift_csv_ajax_export',
			nonce: swiftCSV.nonce,
			post_type: postType,
			export_scope: exportScope,
			include_private_meta: includePrivateMeta,
			export_limit: exportLimit,
			start_row: startRow,
		});

		fetch(swiftCSV.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: formData,
		})
			.then(response => response.json())
			.then(data => {
				if (!data.success) {
					throw new Error(data.data || 'Export failed');
				}

				// Append CSV chunk
				if (data.csv_chunk) {
					csvContent += data.csv_chunk;
				}

				// Update progress
				updateAjaxProgress(data, startTime);

				// Log progress
				if (data.processed && data.total) {
					const percentage = Math.round((data.processed / data.total) * 100);
					addLogEntry(
						swiftCSV.messages.processedExport +
							' ' +
							data.processed +
							'/' +
							data.total +
							' (' +
							percentage +
							'%)',
						'info',
						'export'
					);
				}

				// Continue processing or complete
				if (data.continue && !isCancelled) {
					setTimeout(() => processChunk(data.processed), 100);
				} else {
					completeAjaxExport(csvContent, exportBtn, cancelBtn, postType);
				}
			})
			.catch(error => {
				console.error('Export error:', error);
				addLogEntry(swiftCSV.messages.exportError + ' ' + error.message, 'error', 'export');

				if (exportBtn) {
					exportBtn.disabled = false;
					exportBtn.value = swiftCSV.messages.exportCsv;
				}
				if (cancelBtn) {
					cancelBtn.style.display = 'none';
				}
			});
	}

	// Start processing
	processChunk();
}

function handleAjaxImport(e) {
	e.preventDefault();

	// Clear import log
	clearLog('import');
	addLogEntry(swiftCSV.messages.startingImport, 'info', 'import');

	const fileInput = document.querySelector('#ajax_csv_file');
	const postType = document.querySelector('#ajax_post_type')?.value;
	const updateExisting = document.querySelector('#ajax_update_existing')?.checked;

	// Validate file
	if (!fileInput || !fileInput.files.length) {
		addLogEntry(swiftCSV.messages.selectCsvFile, 'error', 'import');
		return;
	}

	const file = fileInput.files[0];

	// Log import settings
	addLogEntry(swiftCSV.messages.fileInfo + ' ' + file.name, 'debug', 'import');
	addLogEntry(swiftCSV.messages.fileSize + ' ' + formatFileSize(file.size), 'debug', 'import');
	addLogEntry(swiftCSV.messages.postTypeInfo + ' ' + postType, 'debug', 'import');
	addLogEntry(
		swiftCSV.messages.updateExistingInfo +
			' ' +
			(updateExisting ? swiftCSV.messages.yes : swiftCSV.messages.no),
		'debug',
		'import'
	);

	const formData = new FormData();
	formData.append('action', 'swift_csv_ajax_import');
	formData.append('nonce', swiftCSV.nonce);
	formData.append('csv_file', file);
	formData.append('post_type', postType);
	formData.append('update_existing', updateExisting ? '1' : '0');

	const importBtn = e.target.querySelector('button[type="submit"]');
	const cancelBtn = document.querySelector('#ajax-import-cancel-btn');
	const startTime = Date.now();
	let isCancelled = false;

	// Update button states
	if (importBtn) {
		importBtn.disabled = true;
		importBtn.textContent = swiftCSV.messages.importing;
	}
	if (cancelBtn) {
		cancelBtn.style.display = 'inline-block';
	}

	// Cancel functionality
	if (cancelBtn) {
		cancelBtn.addEventListener('click', function () {
			isCancelled = true;
			addLogEntry(swiftCSV.messages.importCancelledByUser, 'warning', 'import');

			if (importBtn) {
				importBtn.disabled = false;
				importBtn.textContent = swiftCSV.messages.startImport;
			}
			if (cancelBtn) {
				cancelBtn.style.display = 'none';
			}
		});
	}

	function processImportChunk(startRow = 0) {
		if (isCancelled) return;

		addLogEntry(swiftCSV.messages.processingChunk + ' ' + startRow, 'debug', 'import');

		formData.set('start_row', startRow);

		fetch(swiftCSV.ajaxUrl, {
			method: 'POST',
			body: formData,
		})
			.then(response => response.json())
			.then(data => {
				if (!data.success) {
					throw new Error(data.data || 'Import failed');
				}

				// Update progress
				updateImportProgress(data, startTime);

				// Log progress
				if (data.processed && data.total) {
					const percentage = Math.round((data.processed / data.total) * 100);
					addLogEntry(
						swiftCSV.messages.processedInfo +
							' ' +
							data.processed +
							'/' +
							data.total +
							' (' +
							percentage +
							'%)',
						'info',
						'import'
					);
				}

				// Log details
				if (data.created !== undefined) {
					addLogEntry(
						swiftCSV.messages.createdInfo + ' ' + data.created,
						'success',
						'import'
					);
				}
				if (data.updated !== undefined) {
					addLogEntry(
						swiftCSV.messages.updatedInfo + ' ' + data.updated,
						'success',
						'import'
					);
				}
				if (data.errors > 0) {
					addLogEntry(
						swiftCSV.messages.errorsInfo + ' ' + data.errors,
						'warning',
						'import'
					);
				}

				// Continue processing or complete
				if (data.continue && !isCancelled) {
					setTimeout(() => processImportChunk(data.processed), 100);
				} else {
					completeAjaxImport(data, importBtn, cancelBtn);
				}
			})
			.catch(error => {
				console.error('Import error:', error);
				addLogEntry(swiftCSV.messages.importError + ' ' + error.message, 'error', 'import');

				if (importBtn) {
					importBtn.disabled = false;
					importBtn.textContent = swiftCSV.messages.startImport;
				}
				if (cancelBtn) {
					cancelBtn.style.display = 'none';
				}
			});
	}

	// Start processing
	processImportChunk();
}

/**
 * Format file size in human readable format
 *
 * @param {number} bytes File size in bytes
 * @return {string} Formatted file size
 */
function formatFileSize(bytes) {
	if (bytes === 0) return '0 Bytes';

	const k = 1024;
	const sizes = ['Bytes', 'KB', 'MB', 'GB'];
	const i = Math.floor(Math.log(bytes) / Math.log(k));

	return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Update AJAX progress for export
 *
 * @param {Object} data Progress data
 * @param {number} startTime Start time
 */
function updateAjaxProgress(data, startTime) {
	// Find progress elements in the new UI structure
	const progressContainer = document.querySelector('.swift-csv-progress');
	if (!progressContainer) {
		console.warn('Progress container not found');
		return;
	}

	const progressFill = progressContainer.querySelector('.progress-bar-fill');
	const processedEl = progressContainer.querySelector('.processed-rows');
	const totalEl = progressContainer.querySelector('.total-rows');
	const percentageEl = progressContainer.querySelector('.percentage');

	// Update progress bar
	if (progressFill && data.progress !== undefined) {
		progressFill.style.width = data.progress + '%';
	}

	// Update stats
	if (processedEl && data.processed !== undefined) {
		processedEl.textContent = data.processed;
	}
	if (totalEl && data.total !== undefined) {
		totalEl.textContent = data.total;
	}
	if (percentageEl && data.progress !== undefined) {
		percentageEl.textContent = data.progress;
	}
}

/**
 * Update AJAX progress for import
 *
 * @param {Object} data Progress data
 * @param {number} startTime Start time
 */
function updateImportProgress(data, startTime) {
	// Find progress elements in the new UI structure
	const progressContainer = document.querySelector('.swift-csv-progress');
	if (!progressContainer) {
		console.warn('Progress container not found');
		return;
	}

	const progressFill = progressContainer.querySelector('.progress-bar-fill');
	const processedEl = progressContainer.querySelector('.processed-rows');
	const totalEl = progressContainer.querySelector('.total-rows');
	const percentageEl = progressContainer.querySelector('.percentage');
	const createdEl = progressContainer.querySelector('.created-count');
	const updatedEl = progressContainer.querySelector('.updated-count');
	const errorEl = progressContainer.querySelector('.error-count');

	// Update progress bar
	if (progressFill && data.progress !== undefined) {
		progressFill.style.width = data.progress + '%';
	}

	// Update stats
	if (processedEl && data.processed !== undefined) {
		processedEl.textContent = data.processed;
	}
	if (totalEl && data.total !== undefined) {
		totalEl.textContent = data.total;
	}
	if (percentageEl && data.progress !== undefined) {
		percentageEl.textContent = data.progress;
	}

	// Update details
	if (createdEl && data.created !== undefined) {
		createdEl.textContent = data.created;
	}
	if (updatedEl && data.updated !== undefined) {
		updatedEl.textContent = data.updated;
	}
	if (errorEl && data.errors !== undefined) {
		errorEl.textContent = data.errors;
	}
}

/**
 * Complete AJAX export
 *
 * @param {string} csvContent CSV content
 * @param {HTMLElement} exportBtn Export button
 * @param {HTMLElement} cancelBtn Cancel button
 * @param {string} postType Post type
 */
function completeAjaxExport(csvContent, exportBtn, cancelBtn, postType) {
	addLogEntry(swiftCSV.messages.exportCompleted, 'success', 'export');

	// Create download link
	const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
	const url = URL.createObjectURL(blob);

	// Format: swiftcsv_export_postType_YYYY-MM-DD_HH-mm-ss.csv
	const now = new Date();
	const dateStr =
		now.getFullYear() +
		'-' +
		String(now.getMonth() + 1).padStart(2, '0') +
		'-' +
		String(now.getDate()).padStart(2, '0') +
		'_' +
		String(now.getHours()).padStart(2, '0') +
		'-' +
		String(now.getMinutes()).padStart(2, '0') +
		'-' +
		String(now.getSeconds()).padStart(2, '0');

	const filename = `swiftcsv_export_${postType}_${dateStr}.csv`;

	// Update download button with new ID and state management
	const downloadBtn = document.querySelector('#export-download-btn');
	if (downloadBtn) {
		downloadBtn.href = url;
		downloadBtn.download = filename;
		downloadBtn.classList.add('enabled'); // Enable the button
	}

	// Reset buttons
	if (exportBtn) {
		exportBtn.disabled = false;
		exportBtn.value = swiftCSV.messages.exportCsv;
	}
	if (cancelBtn) {
		cancelBtn.style.display = 'none';
	}

	addLogEntry(swiftCSV.messages.downloadReady + ' ' + filename, 'info', 'export');
}

/**
 * Complete AJAX import
 *
 * @param {Object} data Import results
 * @param {HTMLElement} importBtn Import button
 * @param {HTMLElement} cancelBtn Cancel button
 */
function completeAjaxImport(data, importBtn, cancelBtn) {
	addLogEntry(swiftCSV.messages.importCompleted, 'success', 'import');

	// Log final results
	if (data.imported !== undefined) {
		addLogEntry(swiftCSV.messages.totalImported + ' ' + data.imported, 'success', 'import');
	}
	if (data.updated !== undefined) {
		addLogEntry(swiftCSV.messages.totalUpdated + ' ' + data.updated, 'success', 'import');
	}
	if (data.errors !== undefined) {
		addLogEntry(
			swiftCSV.messages.totalErrors + ' ' + data.errors,
			data.errors > 0 ? 'warning' : 'success',
			'import'
		);
	}

	// Reset buttons
	if (importBtn) {
		importBtn.disabled = false;
		importBtn.textContent = swiftCSV.messages.startImport;
	}
	if (cancelBtn) {
		cancelBtn.style.display = 'none';
	}

	// Reset file input
	const fileInput = document.querySelector('#ajax_csv_file');
	const uploadArea = document.querySelector('#csv-file-upload');
	const fileInfo = document.querySelector('#csv-file-info');

	if (fileInput) fileInput.value = '';
	if (uploadArea) uploadArea.style.display = 'block';
	if (fileInfo) fileInfo.style.display = 'none';
}

// Legacy functions for compatibility
function startBatchExport(postType, postsPerPage) {
	addLogEntry(swiftCSV.messages.batchExportStarted, 'info', 'export');
	// Implementation would go here
}
