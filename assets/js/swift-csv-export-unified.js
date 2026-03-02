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
	init() {
		if (!SwiftCSVExportUnifiedModulesReady) {
			return;
		}
		this.bindEvents();
	},

	/**
	 * Enable download button for export
	 *
	 * @param {string} csvContent CSV content
	 * @param {string} postType   Post type
	 */
	enableDownloadButtonForExport(csvContent, postType) {
		SwiftCSVExportUnifiedDownload.enableDownloadButtonForExport(csvContent, postType);
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const self = this;

		// Standard Export button click handler
		document.addEventListener('click', function (e) {
			if (e.target && e.target.id === 'ajax-export-csv-btn') {
				e.preventDefault();
				self.handleExport('wp_compatible');
			}
		});

		// Direct SQL Export button click handler
		document.addEventListener('click', function (e) {
			if (e.target && e.target.id === 'direct-sql-export-btn') {
				e.preventDefault();
				self.handleExport('direct_sql');
			}
		});
	},

	/**
	 * Handle export with specified method
	 *
	 * @param {string} exportMethod Export method ('standard' or 'direct_sql')
	 */
	handleExport(exportMethod) {
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
	 * @param {string}      finalCsv Final CSV content
	 * @param {Object}      formData Form data
	 * @param {HTMLElement} button   Export button
	 */
	completeExport(finalCsv, formData, button) {
		// Export completed
		this.enableDownloadButtonForExport(finalCsv, formData.post_type || 'post');
		this.showComplete(button);

		// Add completion log
		if (window.SwiftCSVUtils && window.SwiftCSVUtils.addLogEntry) {
			window.SwiftCSVUtils.addLogEntry(swiftCSV.messages.exportCompleted, 'info', 'export');
		}
	},

	/**
	 * Handle Direct SQL export
	 *
	 * @param {Object} formData Form data
	 */
	handleDirectSqlExport(formData) {
		const button = document.getElementById('direct-sql-export-btn');
		const standardBtn = document.getElementById('ajax-export-csv-btn');
		const cancelBtn = document.getElementById('ajax-export-cancel-btn');

		// Store original button text
		if (button) {
			button.dataset.originalText = button.textContent;
		}

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
	 * @param {Object}      formData Form data
	 * @param {HTMLElement} button   Button element
	 * @param {Object}      ui       UI elements
	 */
	startDirectSqlBatchExport(formData, button, ui) {
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
				exportSession,
				afterId: exportLogLastId,
				setAfterId(nextAfterId) {
					exportLogLastId = nextAfterId;
				},
				buildLogMessage(detail) {
					const prefixText = swiftCSV.messages.exportPrefix || 'Export';
					const rowLabelText = swiftCSV.messages.rowLabel || 'Row';
					const rowText = `${rowLabelText}${detail.row}`;
					const truncatedTitle = SwiftCSVExportUnified.truncateTitle(detail.title, 20);
					return `[${prefixText}:${rowText}]${truncatedTitle}`;
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
			const existingCancelHandler = ui.cancelBtn._swiftCsvDirectSqlCancelHandler;
			if (existingCancelHandler) {
				ui.cancelBtn.removeEventListener('click', existingCancelHandler);
				ui.cancelBtn._swiftCsvDirectSqlCancelHandler = null;
			}

			cancelHandler = () => {
				isCancelled = true;
				stopExportLogPolling();
				if (
					formData.enable_logs === '1' &&
					window.SwiftCSVUtils &&
					window.SwiftCSVUtils.addLogEntry
				) {
					window.SwiftCSVUtils.addLogEntry(
						swiftCSV.messages.exportCancelledByUser,
						'warning',
						'export'
					);
				}
				sendCancelSignal();
				if (currentAbortController) {
					currentAbortController.abort();
				}
				cleanupUi();
				button.disabled = false;
				button.textContent = swiftCSV.messages.directSqlExport || 'High-Speed Export';
			};

			ui.cancelBtn._swiftCsvDirectSqlCancelHandler = cancelHandler;
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
	 * @param {Object}      formData      Form data
	 * @param {string}      exportSession Export session
	 * @param {number}      startRow      Starting row
	 * @param {HTMLElement} button        Button element
	 * @param {number}      startTime     Start time
	 * @param {string}      csvContent    Current CSV content
	 * @param {Object}      control       Control object
	 * @return {Promise} CSV content
	 */
	processDirectSqlBatches(
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

				const processed = Number(response.processed || 0);
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
	 * @param {Object} formData      Form data
	 * @param {number} startRow      Starting row
	 * @param {string} exportSession Export session
	 * @param {Object} control       Control object
	 * @return {Promise} Batch response
	 */
	processDirectSqlBatch(formData, startRow, exportSession, control) {
		if (control && typeof control.getIsCancelled === 'function' && control.getIsCancelled()) {
			return Promise.reject(new Error(swiftCSV.messages.exportCancelledByUser));
		}

		const batchFormData = new FormData();
		batchFormData.append('action', 'swift_csv_ajax_export');
		batchFormData.append('nonce', swiftCSV.nonce);
		batchFormData.append('export_method', 'direct_sql');
		batchFormData.append('start_row', startRow || 0);
		batchFormData.append('export_session', exportSession || '');

		// Optional: Pro pre-action re-auth token (sent only on first request).
		if ((startRow || 0) === 0) {
			const reauthTokenEl = document.getElementById('swift-csv-pro-reauth-token-export');
			const reauthToken = reauthTokenEl ? reauthTokenEl.value : '';
			if (reauthToken) {
				batchFormData.append('swift_csv_pro_reauth_token', reauthToken);
			}
			const execTokenEl = document.getElementById('swift-csv-pro-exec-password-token-export');
			const execToken = execTokenEl ? execTokenEl.value : '';
			if (execToken) {
				batchFormData.append('swift_csv_pro_exec_password_token', execToken);
			}
		}

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
	 * @param {number}      processed Processed count
	 * @param {number}      total     Total count
	 * @param {HTMLElement} button    Button element
	 * @param {number}      startTime Start time
	 */
	updateDirectSqlProgress(processed, total, button, startTime) {
		const percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
		const elapsed = Date.now() - startTime;
		const elapsedSeconds = Math.floor(elapsed / 1000);

		// Keep button text as "Exporting..." (don't update with progress)
		// button.textContent remains as swiftCSV.messages.exporting

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
			const processedLabel = swiftCSV.messages.processedInfo || 'Processed';
			const rowsLabel = swiftCSV.messages.rowsLabel || 'rows';
			const secondsLabel = swiftCSV.messages.secondsLabel || 's';
			progressStats.textContent = `${percentage}% ${processedLabel} ${processed}/${total} ${rowsLabel} (${elapsedSeconds}${secondsLabel})`;
		}
	},

	/**
	 * Send AJAX request
	 *
	 * @param {Object} data         Request data
	 * @param {Object} extraOptions Extra options
	 * @return {Promise} Promise with response
	 */
	sendAjaxRequest(data, extraOptions) {
		return new Promise((resolve, reject) => {
			const formData = new URLSearchParams(data);

			SwiftCSVExportUnifiedAjax.postForm(formData, extraOptions)
				.then(response => response.json())
				.then(responseData => {
					if (responseData.success) {
						resolve(responseData);
					} else {
						reject(new Error(responseData.data || 'Request failed'));
					}
				})
				.catch(error => {
					reject(error);
				});
		});
	},

	/**
	 * Handle Standard export (original functionality)
	 */
	handleStandardExport() {
		// Use original handleAjaxExport function
		handleAjaxExport({ preventDefault: () => {} });
	},

	/**
	 * Get form data
	 *
	 * @return {Object} Form data object
	 */
	getFormData() {
		const postTypeElement = document.getElementById('swift_csv_export_post_type');
		const postType = postTypeElement ? postTypeElement.value : 'post';

		// Check if element exists and has value
		if (!postTypeElement || !postTypeElement.value) {
			console.error('Post type element not found or has no value');
			throw new Error('Post type selection is required');
		}

		const reauthTokenEl = document.getElementById('swift-csv-pro-reauth-token-export');
		const reauthToken = reauthTokenEl ? reauthTokenEl.value : '';
		const execTokenEl = document.getElementById('swift-csv-pro-exec-password-token-export');
		const execToken = execTokenEl ? execTokenEl.value : '';

		return {
			action: 'swift_csv_ajax_export',
			nonce: swiftCSV.nonce,
			swift_csv_pro_reauth_token: reauthToken,
			swift_csv_pro_exec_password_token: execToken,
			post_type: postType,
			post_status:
				document.querySelector('input[name="swift_csv_export_post_status"]:checked')
					?.value || 'publish',
			export_scope:
				document.querySelector('input[name="swift_csv_export_scope"]:checked')?.value ||
				'all',
			include_taxonomies: document.querySelector('input[name="swift_csv_include_taxonomies"]')
				?.checked
				? '1'
				: '0',
			include_custom_fields: document.querySelector(
				'input[name="swift_csv_include_custom_fields"]'
			)?.checked
				? '1'
				: '0',
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
	showComplete(button) {
		SwiftCSVExportUnifiedUI.showComplete(button);
	},

	/**
	 * Show error state
	 *
	 * @param {HTMLElement} button       Button element
	 * @param {string}      errorMessage Error message
	 */
	showError(button, errorMessage) {
		SwiftCSVExportUnifiedUI.showError(button, errorMessage);
	},

	/**
	 * Download CSV file
	 *
	 * @param {string} csvContent  CSV content
	 * @param {number} recordCount Number of records
	 */
	downloadCSV(csvContent, recordCount) {
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
let SwiftCSVExportUnifiedUI;

let SwiftCSVExportUnifiedModulesReady = false;

try {
	SwiftCSVExportUnifiedDownload = getSwiftCSVExportUnifiedModule('Download');
	SwiftCSVExportUnifiedAjax = getSwiftCSVExportUnifiedModule('Ajax');
	SwiftCSVExportUnifiedLogs = getSwiftCSVExportUnifiedModule('Logs');
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
