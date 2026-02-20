/**
 * Swift CSV Admin Scripts - Export
 *
 * Handles CSV export functionality with AJAX chunked processing.
 * Supports both standard export and Direct SQL export methods.
 *
 */

/**
 * Unified Export Handler
 */
const SwiftCSVExportUnified = {
	/**
	 * Initialize unified export functionality
	 */
	init: function () {
		if (!SwiftCSVExportUnifiedModulesReady) {
			return;
		}
		this.bindEvents();
	},

	/**
	 * Enable download button for export
	 *
	 * @param {string} csvContent CSV content
	 * @param {string} postType Post type
	 */
	enableDownloadButtonForExport: function (csvContent, postType) {
		SwiftCSVExportUnifiedDownload.enableDownloadButtonForExport(csvContent, postType);
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
		const standardBtn = document.getElementById('ajax-export-csv-btn');
		const cancelBtn = document.getElementById('ajax-export-cancel-btn');

		// Disable button and show loading
		button.disabled = true;
		button.textContent = swiftCSV.messages.exporting;

		// Prevent concurrent exports
		if (standardBtn) {
			standardBtn.disabled = true;
		}
		if (cancelBtn) {
			cancelBtn.style.display = 'inline-block';
		}

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
		this.startDirectSqlBatchExport(formData, button, { standardBtn, cancelBtn });
	},

	/**
	 * Start Direct SQL batch export
	 *
	 * @param {Object} formData Form data
	 * @param {HTMLElement} button Button element
	 * @param {Object} ui UI elements
	 */
	startDirectSqlBatchExport: function (formData, button, ui) {
		const startTime = Date.now();
		let csvContent = '';
		let processed = 0;
		let totalPosts = 0;
		let exportSession = '';
		let isCancelled = false;
		let currentAbortController = null;
		let cancelHandler = null;
		let exportLogLastId = 0;
		let exportLogPollingTimer = null;

		const stopExportLogPolling = function () {
			if (exportLogPollingTimer) {
				clearInterval(exportLogPollingTimer);
				exportLogPollingTimer = null;
			}
		};

		const pollExportLogs = function () {
			return SwiftCSVExportUnifiedLogs.pollExportLogs({
				enableLogs: formData.enable_logs,
				exportSession: exportSession,
				afterId: exportLogLastId,
				setAfterId: function (nextAfterId) {
					exportLogLastId = nextAfterId;
				},
				buildLogMessage: function (detail) {
					const statusIcon = detail.status === 'success' ? '✓' : '✗';
					const prefix = `[${swiftCSV.messages.exportPrefix || 'Export'}]`;
					return `${prefix} ${statusIcon} ${detail.title}`;
				},
			});
		};

		const startExportLogPolling = function () {
			if (formData.enable_logs !== '1') return;
			if (exportLogPollingTimer) return;
			pollExportLogs();
			exportLogPollingTimer = setInterval(pollExportLogs, 2000);
		};

		const cleanupUi = function () {
			if (ui && ui.standardBtn) {
				ui.standardBtn.disabled = false;
			}
			if (ui && ui.cancelBtn) {
				ui.cancelBtn.style.display = 'none';
			}
		};

		const sendCancelSignal = function () {
			if (!exportSession) return;
			const cancelFormData = new URLSearchParams({
				action: 'swift_csv_cancel_export',
				nonce: swiftCSV.nonce,
				export_session: exportSession,
			});
			SwiftCSVExportUnifiedAjax.postForm(cancelFormData).catch(() => {
				// ignore
			});
		};

		if (ui && ui.cancelBtn) {
			cancelHandler = () => {
				isCancelled = true;
				stopExportLogPolling();
				sendCancelSignal();
				if (currentAbortController) {
					currentAbortController.abort();
				}
				cleanupUi();
				button.disabled = false;
				button.textContent = swiftCSV.messages.directSqlExport || 'High-Speed Export';
			};
			ui.cancelBtn.addEventListener('click', cancelHandler, { once: true });
		}

		// Process batches
		this.processDirectSqlBatch(formData, 0, exportSession, {
			getIsCancelled: () => isCancelled,
			setAbortController: c => {
				currentAbortController = c;
			},
		})
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
						csvContent,
						{
							getIsCancelled: () => isCancelled,
							setAbortController: c => {
								currentAbortController = c;
							},
						}
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
								cleanupUi();
							});
						}, 100);
					} else {
						// Single batch - complete immediately
						this.completeExport(finalCsv, formData, button);
						cleanupUi();
					}
				} else {
					stopExportLogPolling();
					this.completeExport(finalCsv, formData, button);
					cleanupUi();
				}
			})
			.catch(error => {
				stopExportLogPolling();
				cleanupUi();
				if (isCancelled) {
					return;
				}
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
				button.disabled = false;
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
	 * @param {string} csvContent Current CSV content
	 * @param {Object} control Control object
	 * @return {Promise} CSV content
	 */
	processDirectSqlBatches: function (
		formData,
		exportSession,
		startRow,
		button,
		startTime,
		csvContent = '',
		control
	) {
		return this.processDirectSqlBatch(formData, startRow, exportSession, control).then(
			response => {
				if (!response || !response.success) {
					throw new Error((response && response.data) || 'Batch processing failed');
				}

				processed = Number(response.processed || 0);
				csvContent += response.csv_chunk || '';
				this.updateDirectSqlProgress(
					processed,
					Number(response.total || 0),
					button,
					startTime
				);

				if (response.continue) {
					return this.processDirectSqlBatches(
						formData,
						exportSession,
						processed,
						button,
						startTime,
						csvContent,
						control
					);
				}

				return csvContent;
			}
		);
	},

	/**
	 * Process Direct SQL batch
	 *
	 * @param {Object} formData Form data
	 * @param {number} startRow Starting row
	 * @param {string} exportSession Export session
	 * @param {Object} control Control object
	 * @return {Promise} Batch response
	 */
	processDirectSqlBatch: function (formData, startRow, exportSession, control) {
		if (control && typeof control.getIsCancelled === 'function' && control.getIsCancelled()) {
			return Promise.reject(new Error(swiftCSV.messages.exportCancelledByUser));
		}

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

		const abortController = new AbortController();
		if (control && typeof control.setAbortController === 'function') {
			control.setAbortController(abortController);
		}

		return this.sendAjaxRequest(batchFormData, { signal: abortController.signal });
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
	 * @param {Object} extraOptions Extra options
	 * @return {Promise} Promise with response
	 */
	sendAjaxRequest: function (data, extraOptions) {
		return new Promise((resolve, reject) => {
			console.log('Debug - Sending data:', data);

			const formData = new URLSearchParams(data);
			console.log('Debug - FormData string:', formData.toString());

			SwiftCSVExportUnifiedAjax.postForm(formData, extraOptions)
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
	 * @return {Object} Form data object
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
			include_private_meta: document.querySelector(
				'input[name="swift_csv_include_private_meta"]'
			)?.checked
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
		SwiftCSVExportUnifiedUI.showComplete(button);
	},

	/**
	 * Show error state
	 *
	 * @param {HTMLElement} button Button element
	 * @param {string} errorMessage Error message
	 */
	showError: function (button, errorMessage) {
		SwiftCSVExportUnifiedUI.showError(button, errorMessage);
	},

	/**
	 * Download CSV file
	 *
	 * @param {string} csvContent CSV content
	 * @param {number} recordCount Number of records
	 */
	downloadCSV: function (csvContent, recordCount) {
		SwiftCSVExportUnifiedDownload.downloadCSV(csvContent, recordCount);
	},
};

const getSwiftCSVExportUnifiedModule = function (name) {
	const modules = window.SwiftCSVExportUnifiedModules;
	if (!modules || !modules[name]) {
		throw new Error(
			'Swift CSV export module is missing: ' +
				name +
				'. Make sure assets/js/export/*.js scripts are enqueued.'
		);
	}
	return modules[name];
};

let SwiftCSVExportUnifiedDownload;
let SwiftCSVExportUnifiedAjax;
let SwiftCSVExportUnifiedLogs;
let SwiftCSVExportUnifiedForm;
let SwiftCSVExportUnifiedUI;

let SwiftCSVExportUnifiedModulesReady = false;

try {
	SwiftCSVExportUnifiedDownload = getSwiftCSVExportUnifiedModule('Download');
	SwiftCSVExportUnifiedAjax = getSwiftCSVExportUnifiedModule('Ajax');
	SwiftCSVExportUnifiedLogs = getSwiftCSVExportUnifiedModule('Logs');
	SwiftCSVExportUnifiedForm = getSwiftCSVExportUnifiedModule('Form');
	SwiftCSVExportUnifiedUI = getSwiftCSVExportUnifiedModule('UI');
	SwiftCSVExportUnifiedModulesReady = true;
} catch (e) {
	console.error(e);
}

// Initialize unified export handler
document.addEventListener('DOMContentLoaded', function () {
	if (!SwiftCSVExportUnifiedModulesReady) {
		return;
	}
	SwiftCSVExportUnified.init();
});
