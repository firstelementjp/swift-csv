/**
 * Swift CSV Admin Scripts - Export
 *
 * Handles CSV export functionality with AJAX chunked processing.
 * Supports both standard export and Direct SQL export methods.
 *
 * @package SwiftCSV
 */

/**
 * Unified Export Handler
 */
const SwiftCSVExportUnified = {
	/**
	 * Initialize unified export functionality
	 */
	init: function () {
		this.bindEvents();
	},

	/**
	 * Enable download button for export
	 *
	 * @param {string} csvContent CSV content
	 * @param {string} postType Post type
	 */
	enableDownloadButtonForExport: function (csvContent, postType) {
		// Create download link
		const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
		const url = URL.createObjectURL(blob);

		// Generate filename with timestamp
		const now = new Date();
		const dateStr =
			String(now.getFullYear()) +
			'-' +
			String(now.getMonth() + 1).padStart(2, '0') +
			'-' +
			String(now.getDate()).padStart(2, '0') +
			'-' +
			String(now.getHours()).padStart(2, '0') +
			'-' +
			String(now.getMinutes()).padStart(2, '0') +
			'-' +
			String(now.getSeconds()).padStart(2, '0');

		const filename = `swiftcsv_export_${postType}_${dateStr}.csv`;

		// Update download button
		const downloadBtn = document.querySelector('#export-download-btn');
		if (downloadBtn) {
			downloadBtn.href = url;
			downloadBtn.download = filename;
			downloadBtn.classList.add('enabled');
		}

		if (window.SwiftCSVUtils && window.SwiftCSVUtils.addLogEntry) {
			window.SwiftCSVUtils.addLogEntry(
				swiftCSV.messages.downloadReady + ' ' + filename,
				'info',
				'export'
			);
		}
	},

	/**
	 * Bind event handlers
	 */
	bindEvents: function () {
		const self = this;

		// Standard Export button click handler
		document.addEventListener('click', function (e) {
			if (e.target && e.target.id === 'ajax-export-csv-btn') {
				e.preventDefault();
				console.log('Debug - Standard Export button clicked');
				self.handleExport('standard');
			}
		});

		// Direct SQL Export button click handler
		document.addEventListener('click', function (e) {
			if (e.target && e.target.id === 'direct-sql-export-btn') {
				e.preventDefault();
				console.log('Debug - Direct SQL Export button clicked');
				self.handleExport('direct_sql');
			}
		});

		// Debug: Check if buttons exist
		const standardBtn = document.getElementById('ajax-export-csv-btn');
		const directSqlBtn = document.getElementById('direct-sql-export-btn');
		console.log('Debug - Standard Export button exists:', !!standardBtn);
		console.log('Debug - Direct SQL Export button exists:', !!directSqlBtn);
	},

	/**
	 * Handle export with specified method
	 *
	 * @param {string} exportMethod Export method ('standard' or 'direct_sql')
	 */
	handleExport: function (exportMethod) {
		// Get form data
		const formData = this.getFormData();
		formData.export_method = exportMethod;

		// Handle based on method
		if (exportMethod === 'direct_sql') {
			this.handleDirectSqlExport(formData);
		} else {
			this.handleStandardExport(formData);
		}
	},

	/**
	 * Complete export process
	 *
	 * @param {string} finalCsv Final CSV content
	 * @param {Object} formData Form data
	 * @param {HTMLElement} button Export button
	 */
	completeExport: function (finalCsv, formData, button) {
		// Export completed
		this.enableDownloadButtonForExport(finalCsv, formData.post_type || 'post');
		this.showComplete(button);

		// Add success log
		if (window.SwiftCSVUtils && window.SwiftCSVUtils.addLogEntry) {
			window.SwiftCSVUtils.addLogEntry(
				swiftCSV.messages.exportCompleted,
				'success',
				'export'
			);
		}
	},

	/**
	 * Handle Direct SQL export
	 *
	 * @param {Object} formData Form data
	 */
	handleDirectSqlExport: function (formData) {
		const button = document.getElementById('direct-sql-export-btn');

		// Disable button and show loading
		button.disabled = true;
		button.textContent = swiftCSV.messages.exporting;

		// Add log entry if logging is enabled
		if (
			formData.enable_logs === '1' &&
			window.SwiftCSVUtils &&
			window.SwiftCSVUtils.addLogEntry
		) {
			// Add export settings log (same as standard export)
			window.SwiftCSVUtils.addLogEntry(
				swiftCSV.messages.postTypeExport + ' ' + formData.post_type,
				'debug',
				'export'
			);

			// Translate export scope
			const exportScopeText =
				formData.export_scope === 'basic'
					? swiftCSV.messages.exportScopeBasic
					: swiftCSV.messages.exportScopeAll;
			window.SwiftCSVUtils.addLogEntry(
				swiftCSV.messages.exportContent + ' ' + exportScopeText,
				'debug',
				'export'
			);

			window.SwiftCSVUtils.addLogEntry(
				swiftCSV.messages.includePrivateMeta +
					' ' +
					(formData.include_private_meta === '1'
						? swiftCSV.messages.yes
						: swiftCSV.messages.no),
				'debug',
				'export'
			);
			window.SwiftCSVUtils.addLogEntry(
				swiftCSV.messages.exportLimit +
					' ' +
					(formData.export_limit || swiftCSV.messages.noLimit),
				'debug',
				'export'
			);

			// Add starting message
			window.SwiftCSVUtils.addLogEntry(
				swiftCSV.messages.startingDirectSqlExport ||
					'Starting export process (High-Speed)...',
				'info',
				'export'
			);
		}

		// Reset download button to disabled state
		const downloadBtn = document.querySelector('#export-download-btn');
		if (downloadBtn) {
			downloadBtn.classList.remove('enabled');
			downloadBtn.href = '#';
			downloadBtn.removeAttribute('download');
		}

		// Start batch processing
		this.startDirectSqlBatchExport(formData, button);
	},

	/**
	 * Start Direct SQL batch export
	 *
	 * @param {Object} formData Form data
	 * @param {HTMLElement} button Button element
	 */
	startDirectSqlBatchExport: function (formData, button) {
		const startTime = Date.now();
		let csvContent = '';
		let processed = 0;
		let totalPosts = 0;
		let exportSession = '';
		let exportLogLastId = 0;
		let exportLogPollingTimer = null;

		const stopExportLogPolling = function () {
			if (exportLogPollingTimer) {
				clearInterval(exportLogPollingTimer);
				exportLogPollingTimer = null;
			}
		};

		const pollExportLogs = function () {
			if (formData.enable_logs !== '1') return Promise.resolve();
			if (!exportSession) return Promise.resolve();

			const logFormData = new URLSearchParams({
				action: 'swift_csv_ajax_export_logs',
				nonce: swiftCSV.nonce,
				export_session: exportSession,
				enable_logs: formData.enable_logs,
				after_id: String(exportLogLastId),
				limit: '200',
			});

			return fetch(swiftCSV.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: logFormData,
			})
				.then(response => response.json())
				.then(data => {
					if (!data || !data.success || !data.data) return;
					const payload = data.data;
					if (payload.last_id !== undefined) {
						exportLogLastId = Number(payload.last_id) || exportLogLastId;
					}
					if (!payload.logs || !Array.isArray(payload.logs) || payload.logs.length === 0)
						return;
					payload.logs.forEach(item => {
						if (!item || !item.detail) return;
						const detail = item.detail;
						const statusIcon = detail.status === 'success' ? '✓' : '✗';
						const prefix = `[${swiftCSV.messages.exportPrefix || 'Export'}]`;
						const logMessage = `${prefix} ${statusIcon} ${detail.title}`;
						if (window.SwiftCSVUtils && window.SwiftCSVUtils.addLogEntry) {
							window.SwiftCSVUtils.addLogEntry(
								logMessage,
								detail.status === 'success' ? 'success' : 'error',
								'export'
							);
						}
					});
				})
				.catch(() => {
					// Silently handle polling errors
				});
		};

		const startExportLogPolling = function () {
			if (formData.enable_logs !== '1') return;
			if (exportLogPollingTimer) return;
			pollExportLogs();
			exportLogPollingTimer = setInterval(pollExportLogs, 2000);
		};

		// Process batches
		this.processDirectSqlBatch(formData, 0, exportSession)
			.then(response => {
				if (!response || !response.success) {
					throw new Error((response && response.data) || 'Direct SQL export failed');
				}

				exportSession = response.export_session || exportSession;
				totalPosts = Number(response.total || 0);
				processed = Number(response.processed || 0);
				csvContent += response.csv_chunk || '';

				this.updateDirectSqlProgress(processed, totalPosts, button, startTime);
				startExportLogPolling();

				if (response.continue) {
					return this.processDirectSqlBatches(
						formData,
						exportSession,
						processed,
						button,
						startTime,
						csvContent
					);
				}

				return csvContent;
			})
			.then(finalCsv => {
				// Final poll to get any remaining logs before stopping
				if (formData.enable_logs === '1' && exportSession) {
					// Stop polling first to avoid race conditions
					stopExportLogPolling();

					// For single batch exports, skip final poll to avoid duplication
					// Only do final poll if we had multiple batches (processed > batch_size)
					const batchSize = 500; // Default batch size
					if (processed > batchSize) {
						// Wait a bit then do final poll for multi-batch exports
						setTimeout(() => {
							pollExportLogs().finally(() => {
								this.completeExport(finalCsv, formData, button);
							});
						}, 100);
					} else {
						// Single batch - complete immediately
						this.completeExport(finalCsv, formData, button);
					}
				} else {
					stopExportLogPolling();
					this.completeExport(finalCsv, formData, button);
				}
			})
			.catch(error => {
				stopExportLogPolling();
				// Add error log
				if (
					formData.enable_logs === '1' &&
					window.SwiftCSVUtils &&
					window.SwiftCSVUtils.addLogEntry
				) {
					window.SwiftCSVUtils.addLogEntry(
						'Direct SQL ' +
							swiftCSV.messages.exportError +
							' ' +
							(error.message || swiftCSV.messages.unknownError),
						'error',
						'export'
					);
				}

				this.showError(button, error.message || swiftCSV.messages.failed);
			});
	},

	/**
	 * Process Direct SQL batches
	 *
	 * @param {Object} formData Form data
	 * @param {string} exportSession Export session
	 * @param {number} startRow Starting row
	 * @param {HTMLElement} button Button element
	 * @param {number} startTime Start time
	 * @return {Promise} CSV content
	 */
	processDirectSqlBatches: function (
		formData,
		exportSession,
		startRow,
		button,
		startTime,
		csvContent = ''
	) {
		return this.processDirectSqlBatch(formData, startRow, exportSession).then(response => {
			if (!response || !response.success) {
				throw new Error((response && response.data) || 'Batch processing failed');
			}

			processed = Number(response.processed || 0);
			csvContent += response.csv_chunk || '';
			this.updateDirectSqlProgress(processed, Number(response.total || 0), button, startTime);

			if (response.continue) {
				return this.processDirectSqlBatches(
					formData,
					exportSession,
					processed,
					button,
					startTime,
					csvContent
				);
			}

			return csvContent;
		});
	},

	/**
	 * Process Direct SQL batch
	 *
	 * @param {Object} formData Form data
	 * @param {number} startRow Starting row
	 * @param {string} exportSession Export session
	 * @return {Promise} Batch response
	 */
	processDirectSqlBatch: function (formData, startRow, exportSession) {
		const batchFormData = new FormData();
		batchFormData.append('action', 'swift_csv_ajax_export');
		batchFormData.append('nonce', swiftCSV.nonce);
		batchFormData.append('export_method', 'direct_sql');
		batchFormData.append('start_row', startRow || 0);
		batchFormData.append('export_session', exportSession || '');

		// Add other form data
		Object.keys(formData).forEach(key => {
			if (
				key !== 'action' &&
				key !== 'nonce' &&
				key !== 'export_method' &&
				key !== 'start_row' &&
				key !== 'export_session'
			) {
				batchFormData.append(key, formData[key]);
			}
		});

		return this.sendAjaxRequest(batchFormData);
	},

	/**
	 * Update Direct SQL progress
	 *
	 * @param {number} processed Processed count
	 * @param {number} total Total count
	 * @param {HTMLElement} button Button element
	 * @param {number} startTime Start time
	 */
	updateDirectSqlProgress: function (processed, total, button, startTime) {
		const percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
		const elapsed = Date.now() - startTime;
		const elapsedSeconds = Math.floor(elapsed / 1000);

		// Update button text
		button.textContent = `${swiftCSV.messages.exporting} (${percentage}% - ${processed}/${total})`;

		// Update progress bar if exists
		const progressContainer = document.querySelector('.swift-csv-progress');
		if (!progressContainer) return;

		const progressFill = progressContainer.querySelector('.progress-bar-fill');
		const progressStats = progressContainer.querySelector('.progress-stats');
		const progressBar = progressContainer.querySelector('.progress-bar');

		if (progressBar) {
			progressBar.classList.add('processing');
			if (percentage >= 100) {
				progressBar.classList.add('completed');
				progressBar.classList.remove('processing');
			} else {
				progressBar.classList.remove('completed');
			}
		}

		if (progressFill) {
			progressFill.style.width = percentage + '%';
		}

		if (progressStats) {
			const secondsLabel = swiftCSV.messages.secondsLabel || 's';
			progressStats.textContent = `${percentage}% - ${swiftCSV.messages.processedInfo} ${processed}/${total} (${elapsedSeconds}${secondsLabel})`;
		}
	},

	/**
	 * Send AJAX request
	 *
	 * @param {Object} data Request data
	 * @returns {Promise} Promise with response
	 */
	sendAjaxRequest: function (data) {
		return new Promise((resolve, reject) => {
			console.log('Debug - Sending data:', data);

			const formData = new URLSearchParams(data);
			console.log('Debug - FormData string:', formData.toString());

			fetch(swiftCSV.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: formData,
			})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						resolve(data);
					} else {
						reject(new Error(data.data || 'Request failed'));
					}
				})
				.catch(error => {
					reject(error);
				});
		});
	},

	/**
	 * Handle Standard export (original functionality)
	 *
	 * @param {Object} formData Form data
	 */
	handleStandardExport: function (formData) {
		console.log('Debug - Starting Standard Export with formData:', formData);

		// Use original handleAjaxExport function
		handleAjaxExport({ preventDefault: () => {} });
	},

	/**
	 * Get form data
	 *
	 * @returns {Object} Form data object
	 */
	getFormData: function () {
		const postTypeElement = document.getElementById('swift_csv_export_post_type');
		const postType = postTypeElement ? postTypeElement.value : 'post';

		console.log('Debug - Post Type Element:', postTypeElement);
		console.log('Debug - Post Type Value:', postType);
		console.log('Debug - All form elements:', {
			postTypeElement,
			postStatusElement: document.querySelector(
				'input[name="swift_csv_export_post_status"]:checked'
			),
			exportScopeElement: document.querySelector(
				'input[name="swift_csv_export_scope"]:checked'
			),
		});

		// Check if element exists and has value
		if (!postTypeElement || !postTypeElement.value) {
			console.error('Post type element not found or has no value');
			throw new Error('Post type selection is required');
		}

		return {
			action: 'swift_csv_ajax_export',
			nonce: swiftCSV.nonce,
			post_type: postType,
			post_status:
				document.querySelector('input[name="swift_csv_export_post_status"]:checked')
					?.value || 'publish',
			export_scope:
				document.querySelector('input[name="swift_csv_export_scope"]:checked')?.value ||
				'all',
			include_private_meta: document.getElementById('swift_csv_include_private_meta')?.checked
				? '1'
				: '0',
			export_limit: document.getElementById('swift_csv_export_limit')?.value || '0',
			taxonomy_format:
				document.querySelector('input[name="taxonomy_format"]:checked')?.value || 'name',
			enable_logs: document.getElementById('swift_csv_export_enable_logs')?.checked
				? '1'
				: '0',
		};
	},

	/**
	 * Show complete state
	 *
	 * @param {HTMLElement} button Button element
	 */
	showComplete: function (button) {
		button.disabled = false;
		button.textContent = swiftCSV.exportCompleteText;

		// Reset after delay
		setTimeout(function () {
			button.textContent = swiftCSV.highSpeedExportText;
		}, 3000);
	},

	/**
	 * Show error state
	 *
	 * @param {HTMLElement} button Button element
	 * @param {string} errorMessage Error message
	 */
	showError: function (button, errorMessage) {
		button.disabled = false;
		button.textContent = swiftCSV.exportFailedText;
		alert(swiftCSV.messages.failed + ': ' + errorMessage);

		// Reset after delay
		setTimeout(function () {
			button.textContent = swiftCSV.highSpeedExportText;
		}, 3000);
	},

	/**
	 * Download CSV file
	 *
	 * @param {string} csvContent CSV content
	 * @param {number} recordCount Number of records
	 */
	downloadCSV: function (csvContent, recordCount) {
		const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
		const link = document.createElement('a');
		const url = URL.createObjectURL(blob);

		const filename = 'unified-export-' + new Date().toISOString().slice(0, 10) + '.csv';

		link.setAttribute('href', url);
		link.setAttribute('download', filename);
		link.style.visibility = 'hidden';
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);

		// Clean up
		URL.revokeObjectURL(url);
	},
};

// Initialize unified export handler
document.addEventListener('DOMContentLoaded', function () {
	SwiftCSVExportUnified.init();
});

/**
 * Handle AJAX export form submission (original functionality)
 *
 * @param {Event} e - Form submission event
 */
function handleAjaxExport(e) {
	e.preventDefault();

	// Start progress bar animation immediately
	const progressContainer = document.querySelector('.swift-csv-progress');
	if (progressContainer) {
		const progressBar = progressContainer.querySelector('.progress-bar');
		if (progressBar) {
			progressBar.classList.add('processing');
			progressBar.classList.remove('completed');
		}
	}

	const postType = document.querySelector('#swift_csv_export_post_type')?.value;
	const postStatus =
		document.querySelector('input[name="swift_csv_export_post_status"]:checked')?.value ||
		'publish';
	const exportScope =
		document.querySelector('input[name="swift_csv_export_scope"]:checked')?.value || 'basic';
	const includePrivateMeta = document.querySelector(
		'input[name="swift_csv_include_private_meta"]'
	)?.checked
		? '1'
		: '0';
	const taxonomyFormat =
		document.querySelector('input[name="swift_csv_export_taxonomy_format"]:checked')?.value ||
		'name';
	const exportLimit = document.querySelector('#swift_csv_export_limit')?.value || '';
	const enableLogs = document.querySelector('input[name="swift_csv_export_enable_logs"]')?.checked
		? '1'
		: '0';

	if (enableLogs === '1') {
		// Clear export log
		clearLog('export');
		addLogEntry(swiftCSV.messages.startingExport, 'info', 'export');
	}
	const exportSession = (Date.now().toString(36) + Math.random().toString(36).slice(2)).replace(
		/\./g,
		''
	);

	let exportLogLastId = 0;
	let exportLogPollingTimer = null;
	let exportLogPollingAbortController = null;
	let exportLogPollingPromise = null;

	function stopExportLogPolling({ abortRequest = true } = {}) {
		if (exportLogPollingTimer) {
			clearInterval(exportLogPollingTimer);
			exportLogPollingTimer = null;
		}
		if (abortRequest && exportLogPollingAbortController) {
			exportLogPollingAbortController.abort();
			exportLogPollingAbortController = null;
		}
	}

	function pollExportLogs() {
		if (isCancelled) return;

		if (enableLogs !== '1') return Promise.resolve();

		if (exportLogPollingAbortController) {
			return exportLogPollingPromise || Promise.resolve();
		}

		exportLogPollingAbortController = new AbortController();
		const logFormData = new URLSearchParams({
			action: 'swift_csv_ajax_export_logs',
			nonce: swiftCSV.nonce,
			export_session: exportSession,
			enable_logs: enableLogs,
			after_id: String(exportLogLastId),
			limit: '200',
		});

		exportLogPollingPromise = fetch(swiftCSV.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: logFormData,
			signal: exportLogPollingAbortController.signal,
		})
			.then(response => response.json())
			.then(data => {
				exportLogPollingAbortController = null;
				exportLogPollingPromise = null;
				if (!data || !data.success || !data.data) {
					return;
				}

				const payload = data.data;
				if (payload.last_id !== undefined) {
					exportLogLastId = Number(payload.last_id) || exportLogLastId;
				}

				if (payload.logs && Array.isArray(payload.logs) && payload.logs.length > 0) {
					payload.logs.forEach(item => {
						if (!item || !item.detail) return;
						const detail = item.detail;
						const statusIcon = detail.status === 'success' ? '✓' : '✗';
						const prefix = `[${swiftCSV.messages.exportPrefix || 'Export'}]`;
						const logMessage = `${prefix} ${statusIcon} ${swiftCSV.messages.rowLabel || 'Row'} ${detail.row}: ${detail.title}`;

						if (window.SwiftCSVUtils && window.SwiftCSVUtils.addLogEntry) {
							window.SwiftCSVUtils.addLogEntry(
								logMessage,
								detail.status === 'success' ? 'success' : 'error',
								'export'
							);
						}
					});
				}
			})
			.catch(() => {
				exportLogPollingAbortController = null;
				exportLogPollingPromise = null;
			});

		return exportLogPollingPromise;
	}

	function startExportLogPolling() {
		if (exportLogPollingTimer) return;
		if (enableLogs !== '1') return;
		pollExportLogs();
		exportLogPollingTimer = setInterval(pollExportLogs, 2000);
	}

	if (enableLogs === '1') {
		// Log export settings
		addLogEntry(swiftCSV.messages.postTypeExport + ' ' + postType, 'debug', 'export');

		// Translate export scope
		const exportScopeText =
			exportScope === 'basic'
				? swiftCSV.messages.exportScopeBasic
				: swiftCSV.messages.exportScopeAll;
		addLogEntry(swiftCSV.messages.exportContent + ' ' + exportScopeText, 'debug', 'export');

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
	}

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
	let currentExportAbortController = null;
	let exportCancelHandler = null;

	// Cancel functionality
	if (cancelBtn) {
		if (exportCancelHandler) {
			cancelBtn.removeEventListener('click', exportCancelHandler);
		}

		exportCancelHandler = function () {
			isCancelled = true;
			stopExportLogPolling({ abortRequest: true });
			if (window.SwiftCSVUtils && window.SwiftCSVUtils.addLogEntry) {
				window.SwiftCSVUtils.addLogEntry(
					swiftCSV.messages.exportCancelledByUser,
					'warning',
					'export'
				);
			}

			if (currentExportAbortController) {
				currentExportAbortController.abort();
			}

			const cancelFormData = new URLSearchParams({
				action: 'swift_csv_cancel_export',
				nonce: swiftCSV.nonce,
				export_session: exportSession,
			});
			fetch(swiftCSV.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: cancelFormData,
			}).catch(() => {
				// ignore
			});

			if (exportBtn) {
				exportBtn.disabled = false;
				exportBtn.value = swiftCSV.messages.exportCsv;
			}
			if (cancelBtn) {
				cancelBtn.style.display = 'none';
			}
		};

		cancelBtn.addEventListener('click', exportCancelHandler, { once: true });
	}

	/**
	 * Handles chunked export processing for large datasets.
	 * @param {number} startRow - Starting row number
	 */
	function processChunk(startRow = 0) {
		if (isCancelled) return;

		currentExportAbortController = new AbortController();

		SwiftCSVCore.swiftCSVLog(swiftCSV.messages.processingChunk + ' ' + startRow, 'debug');

		const formData = new URLSearchParams({
			action: 'swift_csv_ajax_export',
			nonce: swiftCSV.nonce,
			post_type: postType,
			post_status: postStatus,
			export_scope: exportScope,
			include_private_meta: includePrivateMeta,
			taxonomy_format: taxonomyFormat,
			export_limit: exportLimit,
			enable_logs: enableLogs,
			start_row: startRow,
			export_session: exportSession,
		});

		fetch(swiftCSV.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: formData,
			signal: currentExportAbortController.signal,
		})
			.then(response => {
				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}
				return response.json();
			})
			.then(data => {
				if (data.success && data.continue) {
					// Append CSV chunk
					csvContent += data.csv_chunk;

					// Update progress
					updateAjaxProgress(data, startTime);

					// Process next chunk
					processChunk(data.processed);
				} else if (data.success) {
					// Export completed - update progress one final time
					updateAjaxProgress(data, startTime);

					if (enableLogs === '1') {
						Promise.resolve(exportLogPollingPromise)
							.catch(() => {
								// ignore
							})
							.finally(() => pollExportLogs())
							.finally(() => {
								stopExportLogPolling({ abortRequest: false });

								// Append final CSV chunk
								csvContent += data.csv_chunk;
								completeAjaxExport(csvContent, exportBtn, cancelBtn, postType);
							});
					} else {
						stopExportLogPolling({ abortRequest: false });

						csvContent += data.csv_chunk;
						completeAjaxExport(csvContent, exportBtn, cancelBtn, postType);
					}
				} else {
					const serverMessage =
						(data && data.data ? data.data : '') ||
						(data && data.message ? data.message : '') ||
						'';
					throw new Error(serverMessage || swiftCSV.messages.exportError);
				}
			})
			.catch(error => {
				if (error && error.name === 'AbortError') {
					return;
				}
				stopExportLogPolling({ abortRequest: true });

				if (window.SwiftCSVUtils && window.SwiftCSVUtils.addLogEntry) {
					window.SwiftCSVUtils.addLogEntry(
						swiftCSV.messages.exportError + ' ' + error.message,
						'error',
						'export'
					);
				}

				if (exportBtn) {
					exportBtn.disabled = false;
					exportBtn.value = swiftCSV.messages.exportCsv;
				}
				if (cancelBtn) {
					cancelBtn.style.display = 'none';
				}
			});
	}

	startExportLogPolling();
	// Start processing
	processChunk();
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
		if (window.SwiftCSVCore && window.SwiftCSVCore.swiftCSVLog) {
			window.SwiftCSVCore.swiftCSVLog('Progress container not found');
		}
		return;
	}

	const progressBar = progressContainer.querySelector('.progress-bar');
	const progressFill = progressContainer.querySelector('.progress-bar-fill');
	const processedEl = progressContainer.querySelector('.processed-rows');
	const totalEl = progressContainer.querySelector('.total-rows');
	const percentageEl = progressContainer.querySelector('.percentage');

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
 * Complete AJAX export
 *
 * @param {string} csvContent Complete CSV content
 * @param {HTMLElement} exportBtn Export button
 * @param {HTMLElement} cancelBtn Cancel button
 * @param {string} postType Post type
 */
function completeAjaxExport(csvContent, exportBtn, cancelBtn, postType) {
	if (window.SwiftCSVUtils && window.SwiftCSVUtils.addLogEntry) {
		window.SwiftCSVUtils.addLogEntry(swiftCSV.messages.exportCompleted, 'success', 'export');
	}

	// Create download link
	const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
	const url = URL.createObjectURL(blob);

	// Generate filename with timestamp
	const now = new Date();
	const dateStr =
		String(now.getFullYear()) +
		'-' +
		String(now.getMonth() + 1).padStart(2, '0') +
		'-' +
		String(now.getDate()).padStart(2, '0') +
		'-' +
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
		exportBtn.value = swiftCSV.messages.startExport;
	}
	if (cancelBtn) {
		cancelBtn.style.display = 'none';
	}

	if (window.SwiftCSVUtils && window.SwiftCSVUtils.addLogEntry) {
		window.SwiftCSVUtils.addLogEntry(
			swiftCSV.messages.downloadReady + ' ' + filename,
			'info',
			'export'
		);
	}
}

// Export for use in main script
window.SwiftCSVExport = {
	handleAjaxExport,
	updateAjaxProgress,
	completeAjaxExport,
};
