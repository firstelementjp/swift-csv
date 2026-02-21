/**
 * Swift CSV Admin Scripts - Import
 *
 * Handles CSV import functionality with AJAX chunked processing.
 *
 */

/**
 * Initialize file upload functionality
 */
function initFileUpload() {
	const uploadArea = document.querySelector('#csv-file-upload');
	const fileInput = document.querySelector('#ajax_csv_file');

	if (!uploadArea || !fileInput) {
		return;
	}

	// Prevent multiple event listener registrations
	if (uploadArea.dataset.swiftCsvInitialized === 'true') {
		return;
	}

	// Mark as initialized
	uploadArea.dataset.swiftCsvInitialized = 'true';

	const fileInfo = document.querySelector('#csv-file-info');
	const removeBtn = document.querySelector('#remove-file-btn');

	// File selection change event first
	fileInput.addEventListener('change', e => {
		if (e.target.files.length > 0) {
			handleFileSelect(e.target.files[0]);
		}
	});

	// Click to upload - prevent duplicate clicks
	let isClicking = false;

	uploadArea.addEventListener(
		'click',
		function () {
			if (isClicking) {
				return;
			}

			isClicking = true;

			// Use setTimeout to ensure proper timing
			setTimeout(() => {
				try {
					fileInput.click();
				} catch (error) {
					console.error('Error calling fileInput.click():', error);
				} finally {
					// Reset flag after a short delay
					setTimeout(() => {
						isClicking = false;
					}, 100);
				}
			}, 0);
		},
		false
	);

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

	// Remove file
	if (removeBtn) {
		removeBtn.addEventListener('click', () => {
			fileInput.value = '';
			fileInfo.classList.remove('visible');
			uploadArea.classList.remove('file-selected');
		});
	}
}

let importSession = '';
let currentDryRun = '0';
let currentEnableLogs = '0';
let importLogLastId = 0;
let importLogPollingTimer = null;
let importLogPollingAbortController = null;
let importLogPollingPromise = null;
let isImportCancelled = false;

function appendImportRealtimeLog({ message, level, action, status }) {
	const panelsRoot = document.querySelector('.swift-csv-import-logs');
	if (!panelsRoot) {
		addLogEntry(message, level, 'import');
		return;
	}

	let panelKey = 'created';
	if ('error' === status) {
		panelKey = 'errors';
	} else if ('update' === action) {
		panelKey = 'updated';
	}

	const logContent = document.querySelector(
		`.swift-csv-import-logs .log-panel[data-panel="${panelKey}"] .log-content`
	);
	if (!logContent) {
		addLogEntry(message, level, 'import');
		return;
	}

	const maxLogEntries = swiftCSV.maxLogEntries || 30;
	if (logContent.children.length >= maxLogEntries) {
		logContent.removeChild(logContent.firstChild);
	}

	const logEntry = document.createElement('div');
	logEntry.className = `log-entry log-${level} log-import`;
	const timestamp = new Date().toLocaleTimeString();
	logEntry.innerHTML = `<span class="log-time">[${timestamp}]</span> <span class="log-message">${message}</span>`;
	logContent.appendChild(logEntry);
	logContent.scrollTop = logContent.scrollHeight;
}

function stopImportLogPolling({ abortRequest = true } = {}) {
	if (importLogPollingTimer) {
		clearInterval(importLogPollingTimer);
		importLogPollingTimer = null;
	}

	if (abortRequest && importLogPollingAbortController) {
		try {
			importLogPollingAbortController.abort();
		} catch (e) {
			// ignore
		}
	}
	importLogPollingAbortController = null;
	importLogPollingPromise = null;
}

function pollImportLogs() {
	if (isImportCancelled) return Promise.resolve();

	if (currentEnableLogs !== '1') return Promise.resolve();

	if (!importSession) return Promise.resolve();

	if (importLogPollingAbortController) {
		return importLogPollingPromise || Promise.resolve();
	}

	importLogPollingAbortController = new AbortController();
	const logFormData = new URLSearchParams({
		action: 'swift_csv_ajax_import_logs',
		nonce: swiftCSV.nonce,
		import_session: importSession,
		enable_logs: currentEnableLogs,
		after_id: String(importLogLastId),
		limit: '200',
	});

	importLogPollingPromise = fetch(swiftCSV.ajaxUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		body: logFormData,
		signal: importLogPollingAbortController.signal,
	})
		.then(response => response.json())
		.then(data => {
			importLogPollingAbortController = null;
			importLogPollingPromise = null;
			if (!data || !data.success || !data.data) {
				return;
			}

			const payload = data.data;
			if (payload.last_id !== undefined) {
				importLogLastId = Number(payload.last_id) || importLogLastId;
			}

			if (payload.logs && Array.isArray(payload.logs) && payload.logs.length > 0) {
				payload.logs.forEach(item => {
					if (!item || !item.detail) return;
					const detail = item.detail;
					const statusIcon = detail.status === 'success' ? '✓' : '✗';
					const actionText =
						detail.action === 'create'
							? swiftCSV.messages.createAction
							: swiftCSV.messages.updateAction;
					const prefix =
						currentDryRun === '1'
							? `[${swiftCSV.messages.dryRunPrefix}]`
							: `[${swiftCSV.messages.importPrefix}]`;
					const logMessage = `${prefix} ${statusIcon} ${swiftCSV.messages.rowLabel}${detail.row}: ${actionText} - "${detail.title}"`;
					appendImportRealtimeLog({
						message: logMessage,
						level: detail.status === 'success' ? 'success' : 'error',
						action: detail.action,
						status: detail.status,
					});
				});
			}
		})
		.catch(() => {
			importLogPollingAbortController = null;
			importLogPollingPromise = null;
		});

	return importLogPollingPromise;
}

function flushImportLogsAfterComplete({ attempts = 3, delayMs = 300 } = {}) {
	let chain = Promise.resolve();
	for (let i = 0; i < attempts; i++) {
		chain = chain
			.then(() => pollImportLogs())
			.then(
				() =>
					new Promise(resolve => {
						setTimeout(resolve, delayMs);
					})
			);
	}
	return chain;
}

/**
 * Handle file selection
 *
 * @param {File} file Selected file
 */
function handleFileSelect(file) {
	const fileInfo = document.querySelector('#csv-file-info');
	const uploadArea = document.querySelector('#csv-file-upload');
	const fileName = document.querySelector('#csv-file-name');
	const fileSize = document.querySelector('#csv-file-size');

	if (fileInfo && uploadArea && fileName && fileSize) {
		fileName.textContent = file.name;
		fileSize.textContent = SwiftCSVCore.formatFileSize(file.size);
		fileInfo.classList.add('visible');
		uploadArea.classList.add('file-selected');
	}
}

/**
 * Handle AJAX import form submission
 *
 * @param {Event} e - Form submission event
 */
function handleAjaxImport(e) {
	e.preventDefault();

	// Reset flags for new import
	window.swiftCSVLogsDisplayed = false;
	window.swiftCSVLogTabsInitialized = false;

	// Clear import log
	clearLog('import');
	addLogEntry(swiftCSV.messages.startingImport, 'info', 'import');

	// Start progress bar animation immediately
	const progressContainer = document.querySelector('.swift-csv-progress');
	if (progressContainer) {
		const progressBar = progressContainer.querySelector('.progress-bar');
		if (progressBar) {
			progressBar.classList.add('processing');
			progressBar.classList.remove('completed');
		}
	}

	const file = document.querySelector('#ajax_csv_file')?.files[0];

	if (!file) {
		addLogEntry(swiftCSV.messages.noFileSelected, 'error', 'import');
		return;
	}

	const postType = document.querySelector('#import_post_type')?.value || 'post';
	const updateExisting = document.querySelector('input[name="swift_csv_import_update_existing"]')
		?.checked
		? '1'
		: '0';
	const taxonomyFormat =
		document.querySelector('input[name="swift_csv_import_taxonomy_format"]:checked')?.value ||
		'name';
	const dryRun = document.querySelector('input[name="swift_csv_import_dry_run"]')?.checked
		? '1'
		: '0';
	const enableLogs = document.querySelector('input[name="swift_csv_import_enable_logs"]')?.checked
		? '1'
		: '0';

	importSession = Math.random().toString(36).slice(2, 14) + Date.now().toString(36);
	currentDryRun = dryRun;
	currentEnableLogs = enableLogs;
	importLogLastId = 0;
	stopImportLogPolling({ abortRequest: true });
	if (enableLogs === '1') {
		pollImportLogs();
	}

	// Log import settings
	SwiftCSVCore.swiftCSVLog(swiftCSV.messages.fileInfo + ' ' + file.name, 'debug');
	SwiftCSVCore.swiftCSVLog(
		swiftCSV.messages.fileSize + ' ' + SwiftCSVCore.formatFileSize(file.size),
		'debug'
	);
	SwiftCSVCore.swiftCSVLog(swiftCSV.messages.postTypeInfo + ' ' + postType, 'debug');
	addLogEntry(
		swiftCSV.messages.updateExistingInfo +
			' ' +
			(updateExisting === '1' ? swiftCSV.messages.yes : swiftCSV.messages.no),
		'debug',
		'import'
	);

	const importBtn = document.querySelector('#ajax-import-csv-btn');
	const cancelBtn = document.querySelector('#ajax-import-cancel-btn');
	const startTime = Date.now();
	const abortController = new AbortController();
	isImportCancelled = false;

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
			isImportCancelled = true;
			stopImportLogPolling({ abortRequest: true });
			abortController.abort(); // Cancel the fetch request

			// Reset progress bar
			const cancelProgressContainer = document.querySelector('.swift-csv-progress');
			if (cancelProgressContainer) {
				const progressFill = progressContainer.querySelector('.progress-bar-fill');

				if (progressFill) {
					progressFill.style.width = '0%';
				}
				// Keep the processed numbers as they are for user reference
				// Don't reset: "3 / 10 rows processed (30%)" should remain
			}

			if (importBtn) {
				importBtn.disabled = false;
				importBtn.textContent = swiftCSV.messages.startImport;
			}
			if (cancelBtn) {
				cancelBtn.style.display = 'none';
			}
		});
	}

	// Start with first chunk (original 1-stage approach)
	processImportChunk(
		0,
		0,
		0,
		0,
		file,
		postType,
		updateExisting,
		taxonomyFormat,
		dryRun,
		enableLogs,
		importBtn,
		cancelBtn,
		startTime,
		abortController
	);
}

/**
 * Process import chunk
 *
 * @param {number}          startRow          Starting row
 * @param {number}          cumulativeCreated Cumulative created count
 * @param {number}          cumulativeUpdated Cumulative updated count
 * @param {number}          cumulativeErrors  Cumulative error count
 * @param {File}            file              Selected file
 * @param {string}          postType          Post type
 * @param {string}          updateExisting    Update existing flag
 * @param {string}          taxonomyFormat    Taxonomy format
 * @param {string}          dryRun            Dry run flag
 * @param {string}          enableLogs        Enable logs flag
 * @param {HTMLElement}     importBtn         Import button element
 * @param {HTMLElement}     cancelBtn         Cancel button element
 * @param {number}          startTime         Start time
 * @param {AbortController} abortController   Abort controller for cancelling fetch
 */
function processImportChunk(
	startRow = 0,
	cumulativeCreated = 0,
	cumulativeUpdated = 0,
	cumulativeErrors = 0,
	file,
	postType,
	updateExisting,
	taxonomyFormat,
	dryRun,
	enableLogs,
	importBtn,
	cancelBtn,
	startTime,
	abortController
) {
	if (isImportCancelled) return;

	SwiftCSVCore.swiftCSVLog(swiftCSV.messages.processingChunk + ' ' + startRow, 'debug');

	const formData = new FormData();
	formData.append('action', 'swift_csv_ajax_import');
	formData.append('nonce', swiftCSV.nonce);
	formData.append('import_session', importSession);

	// Only send file on first request (start_row = 0)
	if (startRow === 0) {
		formData.append('csv_file', file);
	}

	formData.append('post_type', postType);
	formData.append('update_existing', updateExisting);
	formData.append('taxonomy_format', taxonomyFormat);
	formData.append('dry_run', dryRun);
	formData.append('enable_logs', enableLogs);
	formData.append('start_row', startRow);
	formData.append('cumulative_created', cumulativeCreated);
	formData.append('cumulative_updated', cumulativeUpdated);
	formData.append('cumulative_errors', cumulativeErrors);

	fetch(swiftCSV.ajaxUrl, {
		method: 'POST',
		body: formData,
		signal: abortController.signal,
	})
		.then(response => response.json())
		.then(data => {
			// Always show batch processing logs (both continuing and final batches)
			if (data.success) {
				// Update progress
				updateImportProgress(data, startTime);
				if (enableLogs === '1') {
					pollImportLogs();
				}

				if (data.continue) {
					// Process next chunk
					processImportChunk(
						data.processed,
						data.cumulative_created,
						data.cumulative_updated,
						data.cumulative_errors,
						file,
						postType,
						updateExisting,
						taxonomyFormat,
						dryRun,
						enableLogs,
						importBtn,
						cancelBtn,
						startTime,
						abortController
					);
				} else if (enableLogs === '1') {
					flushImportLogsAfterComplete({ attempts: 3, delayMs: 300 }).finally(() => {
						stopImportLogPolling({ abortRequest: false });
						if (data.recent_logs && !window.swiftCSVLogsDisplayed) {
							displayImportLogs(data.recent_logs);
							window.swiftCSVLogsDisplayed = true;
						}
						completeAjaxImport(data, importBtn, cancelBtn);
					});
				} else {
					stopImportLogPolling({ abortRequest: false });
					if (data.recent_logs && !window.swiftCSVLogsDisplayed) {
						displayImportLogs(data.recent_logs);
						window.swiftCSVLogsDisplayed = true;
					}
					completeAjaxImport(data, importBtn, cancelBtn);
				}
			} else {
				// Handle error
				addLogEntry(swiftCSV.messages.importError + ' ' + data.error, 'error', 'import');
				stopImportLogPolling({ abortRequest: true });

				if (importBtn) {
					importBtn.disabled = false;
					importBtn.textContent = swiftCSV.messages.startImport;
				}
				if (cancelBtn) {
					cancelBtn.style.display = 'none';
				}
			}
		})
		.catch(error => {
			// Handle fetch errors including cancellation
			if (error.name === 'AbortError') {
				// This is expected when user cancels - don't show as error
				addLogEntry(swiftCSV.messages.importCancelledByUser, 'warning', 'import');
				stopImportLogPolling({ abortRequest: true });
				return;
			}

			// Handle other errors
			const errorMessage = error.message || 'Unknown error occurred';
			addLogEntry(swiftCSV.messages.importError + ' ' + errorMessage, 'error', 'import');
			stopImportLogPolling({ abortRequest: true });

			if (importBtn) {
				importBtn.disabled = false;
				importBtn.textContent = swiftCSV.messages.startImport;
			}
			if (cancelBtn) {
				cancelBtn.style.display = 'none';
			}
		});
}

/**
 * Update AJAX progress for import
 *
 * @param {Object} data      Progress data
 * @param {number} startTime Start time
 */
function updateImportProgress(data, startTime) {
	// Find progress elements in the new UI structure
	const progressContainer = document.querySelector('.swift-csv-progress');
	if (!progressContainer) {
		SwiftCSVCore.swiftCSVLog('Progress container not found');
		return;
	}

	const progressBar = progressContainer.querySelector('.progress-bar');
	const progressFill = progressContainer.querySelector('.progress-bar-fill');
	const progressStats = progressContainer.querySelector('.progress-stats');

	// Add processing state animation
	if (progressBar && data.status === 'processing') {
		progressBar.classList.add('processing');
		progressBar.classList.remove('completed');
	} else if (progressBar && data.status === 'completed') {
		progressBar.classList.remove('processing');
		progressBar.classList.add('completed');
	}

	// Update progress bar
	if (progressFill && data.progress !== undefined) {
		progressFill.style.width = data.progress + '%';
	}

	// Update stats
	if (progressStats && data.processed !== undefined && data.total !== undefined) {
		let percentage;
		if (data.progress !== undefined) {
			percentage = Math.round(Number(data.progress));
		} else if (data.total > 0) {
			percentage = Math.round((data.processed / data.total) * 100);
		} else {
			percentage = 0;
		}
		// Ensure startTime is valid, fallback to current time if needed
		const effectiveStartTime =
			startTime && typeof startTime === 'number' ? startTime : Date.now();
		const elapsedSeconds = Math.floor((Date.now() - effectiveStartTime) / 1000);
		const processedLabel = swiftCSV.messages.processedInfo || 'Processed';
		const rowsLabel = swiftCSV.messages.rowsLabel || 'rows';
		const secondsLabel = swiftCSV.messages.secondsLabel || 's';
		progressStats.textContent = `${percentage}% ${processedLabel} ${data.processed}/${data.total} ${rowsLabel} (${elapsedSeconds}${secondsLabel})`;
	}

	// Update tab counts in logs section
	const logCreatedEl = document.querySelector('.swift-csv-import-logs .created-count');
	const logUpdatedEl = document.querySelector('.swift-csv-import-logs .updated-count');
	const logErrorEl = document.querySelector('.swift-csv-import-logs .error-count');

	if (logCreatedEl && data.created !== undefined) {
		logCreatedEl.textContent =
			data.cumulative_created !== undefined ? data.cumulative_created : data.created;
	}
	if (logUpdatedEl && data.updated !== undefined) {
		logUpdatedEl.textContent =
			data.cumulative_updated !== undefined ? data.cumulative_updated : data.updated;
	}
	if (logErrorEl && data.errors !== undefined) {
		logErrorEl.textContent =
			data.cumulative_errors !== undefined ? data.cumulative_errors : data.errors;
	}

	// Initialize tabs if not already done
	if (!window.swiftCSVLogTabsInitialized) {
		initializeLogTabs();
		window.swiftCSVLogTabsInitialized = true;
	}
}

/**
 * Complete AJAX import
 *
 * @param {Object}      data      Import results
 * @param {HTMLElement} importBtn Import button
 * @param {HTMLElement} cancelBtn Cancel button
 */
function completeAjaxImport(data, importBtn, cancelBtn) {
	// Check if this was a Dry Run
	const isDryRun = data.dry_run || false;

	// If the tabbed import log UI exists, completion/summary logs are rendered by displayImportLogs.
	const hasTabbedImportLogs = !!document.querySelector('.swift-csv-import-logs');

	// Set progress bar to completed state
	const progressContainer = document.querySelector('.swift-csv-progress');
	if (progressContainer) {
		const progressBar = progressContainer.querySelector('.progress-bar');
		if (progressBar) {
			progressBar.classList.remove('processing');
			progressBar.classList.add('completed');
		}
	}

	// Add appropriate completion message
	if (!hasTabbedImportLogs) {
		if (isDryRun) {
			addLogEntry('[Dry Run] ' + swiftCSV.messages.dryRunCompleted, 'success', 'import');
		} else {
			addLogEntry(swiftCSV.messages.importCompleted, 'success', 'import');
		}
	}

	// Log final results - use cumulative values
	if (!hasTabbedImportLogs) {
		if (data.cumulative_created !== undefined) {
			const createdMessage = isDryRun
				? '[Dry Run] ' + swiftCSV.messages.dryRunCreated + ' '
				: (
						swiftCSV.messages.dryRunCreated ||
						swiftCSV.messages.createdInfo ||
						swiftCSV.messages.totalImported ||
						''
					).replace(/:\s*$/, '') + ' ';
			addLogEntry(createdMessage + data.cumulative_created, 'success', 'import');
		}
		if (data.cumulative_updated !== undefined) {
			const updatedMessage = isDryRun
				? '[Dry Run] ' + swiftCSV.messages.dryRunUpdated + ' '
				: swiftCSV.messages.totalUpdated + ' ';
			addLogEntry(updatedMessage + data.cumulative_updated, 'success', 'import');
		}
		if (data.cumulative_errors !== undefined) {
			const errorMessage = isDryRun
				? '[Dry Run] ' + swiftCSV.messages.dryRunErrors + ' '
				: swiftCSV.messages.totalErrors + ' ';
			addLogEntry(
				errorMessage + data.cumulative_errors,
				data.cumulative_errors > 0 ? 'warning' : 'success',
				'import'
			);
		}
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

	if (fileInput) {
		fileInput.value = '';
	}
	if (uploadArea) {
		uploadArea.classList.remove('file-selected');
	}
	if (fileInfo) {
		fileInfo.classList.remove('visible');
	}
}

/**
 * Display import logs in tabs
 *
 * @param {Object} recentLogs Recent logs by type
 */
function displayImportLogs(recentLogs) {
	const createdPanel = document.querySelector('.log-panel[data-panel="created"] .log-content');
	const updatedPanel = document.querySelector('.log-panel[data-panel="updated"] .log-content');
	const errorsPanel = document.querySelector('.log-panel[data-panel="errors"] .log-content');

	function sanitizeSummaryLabel(label) {
		return String(label || '').replace(/[：:]\s*$/, '');
	}

	const dryRunPrefixText = swiftCSV.messages.dryRunPrefix || 'テスト実行';
	const importPrefixText = swiftCSV.messages.importPrefix || 'インポート';
	const rowLabelText = swiftCSV.messages.rowLabel || '行';
	const dryRunCompleteText = swiftCSV.messages.dryRunComplete || 'テスト完了！';
	const importCompleteText = swiftCSV.messages.importComplete || 'インポート完了！';
	const createdLabelText = sanitizeSummaryLabel(swiftCSV.messages.dryRunCreated || '新規作成');
	const updatedLabelText = sanitizeSummaryLabel(swiftCSV.messages.dryRunUpdated || '更新');
	const errorsLabelText = sanitizeSummaryLabel(swiftCSV.messages.dryRunErrors || 'エラー');

	// Clear all panels first
	if (createdPanel) createdPanel.innerHTML = '';
	if (updatedPanel) updatedPanel.innerHTML = '';
	if (errorsPanel) errorsPanel.innerHTML = '';

	// Check if this is dry run by checking the AJAX response data
	const isDryRun =
		recentLogs.created?.items?.[0]?.details === 'Processed successfully' ||
		recentLogs.updated?.items?.[0]?.details === 'Processed successfully' ||
		recentLogs.errors?.items?.[0]?.details === 'Processed successfully';

	// Helper function to create log entry HTML
	function createLogEntry(message, level = 'success') {
		const timestamp = new Date().toLocaleTimeString();
		const className = `log-entry log-${level} log-import`;
		return `<div class="${className}"><span class="log-time">[${timestamp}]</span> <span class="log-message">${message}</span></div>`;
	}

	// Get update checkbox state
	const updateExisting = document.querySelector(
		'input[name="swift_csv_import_update_existing"]'
	)?.checked;

	// Add start log to appropriate panel
	if (isDryRun) {
		const startMessage = `[${dryRunPrefixText}] ${swiftCSV.messages.startingImport || ''}`;

		if (updateExisting) {
			// When update is checked, put logs in updated panel
			if (updatedPanel) {
				updatedPanel.innerHTML = createLogEntry(startMessage, 'info');
			}
		} else if (createdPanel) {
			// When update is not checked, put logs in created panel
			createdPanel.innerHTML = createLogEntry(startMessage, 'info');
		}
	} else {
		const startMessage = `[${importPrefixText}] ${swiftCSV.messages.startingImport || ''}`;

		if (updateExisting) {
			if (updatedPanel) {
				updatedPanel.innerHTML = createLogEntry(startMessage, 'info');
			}
		} else if (createdPanel) {
			createdPanel.innerHTML = createLogEntry(startMessage, 'info');
		}
	}

	// Render created logs
	if (createdPanel && recentLogs.created && recentLogs.created.items) {
		const createdLogs = recentLogs.created.items
			.map(log => {
				const prefix = isDryRun ? `[${dryRunPrefixText}]` : `[${importPrefixText}]`;
				const message = `${prefix} ✓ ${rowLabelText}${log.row}: Create - "${log.title}"`;
				return createLogEntry(message, 'success');
			})
			.join('');

		// Append to existing content
		createdPanel.innerHTML += createdLogs;
	}

	// Render updated logs
	if (updatedPanel && recentLogs.updated && recentLogs.updated.items) {
		const updatedLogs = recentLogs.updated.items
			.map(log => {
				const prefix = isDryRun ? `[${dryRunPrefixText}]` : `[${importPrefixText}]`;
				const message = `${prefix} ✓ ${rowLabelText}${log.row}: Update - "${log.title}"`;
				return createLogEntry(message, 'success');
			})
			.join('');

		// Append to existing content
		updatedPanel.innerHTML += updatedLogs;
	}

	// Render error logs with "other X items" indicator
	if (errorsPanel && recentLogs.errors) {
		const items = recentLogs.errors.items || [];
		const total = recentLogs.errors.total || 0;
		const otherCount = total - items.length;

		const errorLogs = items
			.map(log => {
				const prefix = isDryRun ? `[${dryRunPrefixText}]` : `[${importPrefixText}]`;
				const message = `${prefix} ✗ ${rowLabelText}${log.row}: Error - "${log.title}" - ${log.details}`;
				return createLogEntry(message, 'error');
			})
			.join('');

		errorsPanel.innerHTML = errorLogs;

		if (otherCount > 0) {
			const prefix = isDryRun ? `[${dryRunPrefixText}]` : `[${importPrefixText}]`;
			const message = `${prefix} + ${otherCount} more errors`;
			errorsPanel.innerHTML += createLogEntry(message, 'error');
		}
	}

	// Add completion log and summary to appropriate panel
	const summaryPrefix = isDryRun ? `[${dryRunPrefixText}]` : `[${importPrefixText}]`;

	// Add completion message
	const completeMessage = isDryRun
		? `${summaryPrefix} ${dryRunCompleteText}`
		: `${summaryPrefix} ${importCompleteText}`;

	if (updateExisting) {
		if (updatedPanel) {
			updatedPanel.innerHTML += createLogEntry(completeMessage, 'info');
		}
	} else if (createdPanel) {
		createdPanel.innerHTML += createLogEntry(completeMessage, 'info');
	}

	// Add summary logs to respective panels
	if (recentLogs.created && recentLogs.created.total > 0) {
		const createdSummary = `${summaryPrefix} ${createdLabelText}: ${recentLogs.created.total}`;
		if (createdPanel) {
			createdPanel.innerHTML += createLogEntry(createdSummary, 'info');
		}
	}

	if (recentLogs.updated && recentLogs.updated.total > 0) {
		const updatedSummary = `${summaryPrefix} ${updatedLabelText}: ${recentLogs.updated.total}`;
		if (updatedPanel) {
			updatedPanel.innerHTML += createLogEntry(updatedSummary, 'info');
		}
	}

	if (recentLogs.errors && recentLogs.errors.total > 0) {
		const errorSummary = `${summaryPrefix} ${errorsLabelText}: ${recentLogs.errors.total}`;
		if (errorsPanel) {
			errorsPanel.innerHTML += createLogEntry(
				errorSummary,
				recentLogs.errors.total > 0 ? 'warning' : 'info'
			);
		}
	}
}

/**
 * Initialize log tabs functionality
 */
function initializeLogTabs() {
	const tabs = document.querySelectorAll('.swift-csv-import-logs .log-tab');
	const panels = document.querySelectorAll('.swift-csv-import-logs .log-panel');

	// Function to set active tab
	function setActiveTab(targetTab) {
		// Remove active class from all tabs and panels
		tabs.forEach(tab => tab.classList.remove('active'));
		panels.forEach(panel => panel.classList.remove('active'));

		// Add active class to target tab and corresponding panel
		targetTab.classList.add('active');
		const targetPanelKey = targetTab.dataset.tab;
		const targetPanel = document.querySelector(`.log-panel[data-panel="${targetPanelKey}"]`);
		if (targetPanel) {
			targetPanel.classList.add('active');
		}
	}

	// Set default active tab based on update checkbox state
	const updateExisting = document.querySelector(
		'input[name="swift_csv_import_update_existing"]'
	)?.checked;
	const defaultTab = document.querySelector(
		updateExisting ? '.log-tab[data-tab="updated"]' : '.log-tab[data-tab="created"]'
	);
	if (defaultTab) {
		setActiveTab(defaultTab);
	}

	// Add click event listeners to tabs
	tabs.forEach(tab => {
		tab.addEventListener('click', () => {
			setActiveTab(tab);
		});
	});

	// Add change listener to update checkbox to switch default tab
	const updateCheckbox = document.querySelector('input[name="swift_csv_import_update_existing"]');
	if (updateCheckbox) {
		updateCheckbox.addEventListener('change', () => {
			const newDefaultTab = document.querySelector(
				updateCheckbox.checked
					? '.log-tab[data-tab="updated"]'
					: '.log-tab[data-tab="created"]'
			);
			if (newDefaultTab) {
				setActiveTab(newDefaultTab);
			}
		});
	}
}

// Export for use in main script
window.SwiftCSVImport = {
	initFileUpload,
	handleFileSelect,
	handleAjaxImport,
	updateImportProgress,
	completeAjaxImport,
};
