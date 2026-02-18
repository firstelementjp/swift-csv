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
			window.SwiftCSVUtils.addLogEntry('Starting Direct SQL export...', 'info', 'export');
		}

		// Send AJAX request
		this.sendAjaxRequest(formData)
			.then(response => {
				console.log('Debug - Direct SQL response:', response);

				if (response.success) {
					// Add success log
					if (
						formData.enable_logs === '1' &&
						window.SwiftCSVUtils &&
						window.SwiftCSVUtils.addLogEntry
					) {
						window.SwiftCSVUtils.addLogEntry(
							swiftCSV.messages.exportCompleted,
							'success',
							'export'
						);
					}

					// Check if csv_content exists
					if (!response.data || !response.data.csv_content) {
						console.error('Debug - Missing csv_content in response:', response);
						throw new Error(swiftCSV.messages.csvContentNotFound);
					}

					// Direct SQL: Immediate download
					this.downloadCSV(response.data.csv_content, response.data.record_count);
					this.showComplete(button);
				} else {
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
								(response.data || swiftCSV.messages.unknownError),
							'error',
							'export'
						);
					}

					this.showError(button, response.data || swiftCSV.messages.failed);
				}
			})
			.catch(error => {
				// Add error log
				if (
					formData.enable_logs === '1' &&
					window.SwiftCSVUtils &&
					window.SwiftCSVUtils.addLogEntry
				) {
					window.SwiftCSVUtils.addLogEntry(
						'Direct SQL export error: ' + error.message,
						'error',
						'export'
					);
				}

				this.showError(button, error.message || 'Network error');
			});
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
