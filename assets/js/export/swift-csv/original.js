/**
 * Swift CSV Admin Scripts - Export (Original)
 *
 */

function truncateTitle(title, maxLength = 20) {
	if (!title || title.length <= maxLength) {
		return title;
	}
	return title.substring(0, maxLength) + '...';
}

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

	const exportBtn = document.querySelector('#ajax-export-csv-btn');
	const cancelBtn = document.querySelector('#ajax-export-cancel-btn');

	// Store original button text
	if (exportBtn) {
		exportBtn.dataset.originalText = exportBtn.value;
	}

	const postType = document.querySelector('#swift_csv_export_post_type')?.value;
	const postStatus =
		document.querySelector('input[name="swift_csv_export_post_status"]:checked')?.value ||
		'publish';
	const exportScope =
		document.querySelector('input[name="swift_csv_export_scope"]:checked')?.value || 'basic';
	const includeTaxonomies = document.querySelector('input[name="swift_csv_include_taxonomies"]')
		?.checked
		? '1'
		: '0';
	const includeCustomFields = document.querySelector(
		'input[name="swift_csv_include_custom_fields"]'
	)?.checked
		? '1'
		: '0';
	const includePrivateMeta = document.querySelector(
		'input[name="swift_csv_include_private_meta"]'
	)?.checked
		? '1'
		: '0';
	const taxonomyHierarchical = document.querySelector(
		'input[name="swift_csv_taxonomy_hierarchical"]'
	)?.checked
		? '1'
		: '0';
	const taxonomyFormat =
		document.querySelector('input[name="taxonomy_format"]:checked')?.value || 'name';
	const exportLimit = document.querySelector('#swift_csv_export_limit')?.value || '';
	const enableLogs =
		window.swiftCSV &&
		window.swiftCSV.advancedSettings &&
		window.swiftCSV.advancedSettings.enableLogs
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

	const ajax =
		window.SwiftCSVExportUnifiedModules && window.SwiftCSVExportUnifiedModules.Ajax
			? window.SwiftCSVExportUnifiedModules.Ajax
			: null;

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

		if (!ajax) {
			exportLogPollingAbortController = null;
			exportLogPollingPromise = null;
			return Promise.resolve();
		}

		exportLogPollingPromise = ajax
			.postForm(logFormData, {
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
						const prefixText = swiftCSV.messages.exportPrefix || 'Export';
						const rowLabelText = swiftCSV.messages.rowLabel || 'Row';
						const rowText = `${rowLabelText}${detail.row}`;
						const truncatedTitle = truncateTitle(detail.title, 20);
						const logMessage = `[${prefixText}:${rowText}]${truncatedTitle}`;

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

	const directSqlBtn = document.querySelector('#direct-sql-export-btn');
	const startTime = Date.now();
	let isCancelled = false;

	// Update button states
	if (exportBtn) {
		exportBtn.disabled = true;
		exportBtn.value = swiftCSV.messages.exporting;
	}
	if (directSqlBtn) {
		directSqlBtn.disabled = true;
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
		const existingCancelHandler = cancelBtn._swiftCsvExportCancelHandler;
		if (existingCancelHandler) {
			cancelBtn.removeEventListener('click', existingCancelHandler);
			cancelBtn._swiftCsvExportCancelHandler = null;
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

			if (ajax) {
				ajax.postForm(cancelFormData)
					.then(() => {
						// Ignore response.
					})
					.catch(() => {
						// ignore
					});
			}

			if (exportBtn) {
				exportBtn.disabled = false;
				exportBtn.value = swiftCSV.messages.exportCsv;
			}
			if (directSqlBtn) {
				directSqlBtn.disabled = false;
			}
			if (cancelBtn) {
				cancelBtn.style.display = 'none';
			}
		};

		cancelBtn._swiftCsvExportCancelHandler = exportCancelHandler;
		cancelBtn.addEventListener('click', exportCancelHandler, { once: true });
	}

	/**
	 * Handles chunked export processing for large datasets.
	 *
	 * @param {number} startRow - Starting row number
	 */
	function processChunk(startRow = 0) {
		if (isCancelled) return;

		currentExportAbortController = new AbortController();

		SwiftCSVCore.swiftCSVLog(swiftCSV.messages.processingChunk + ' ' + startRow, 'debug');

		const formData = new URLSearchParams({
			action: 'swift_csv_ajax_export',
			nonce: swiftCSV.nonce,
			export_method: 'wp_compatible',
			post_type: postType,
			post_status: postStatus,
			export_scope: exportScope,
			include_taxonomies: includeTaxonomies,
			include_custom_fields: includeCustomFields,
			include_private_meta: includePrivateMeta,
			taxonomy_hierarchical: taxonomyHierarchical,
			taxonomy_format: taxonomyFormat,
			export_limit: exportLimit,
			enable_logs: enableLogs,
			start_row: startRow,
			export_session: exportSession,
		});

		// Optional: Pro pre-action re-auth token (sent only on first request).
		if (startRow === 0) {
			const reauthTokenEl = document.getElementById('swift-csv-pro-reauth-token-export');
			const reauthToken = reauthTokenEl ? reauthTokenEl.value : '';
			if (reauthToken) {
				formData.append('swift_csv_pro_reauth_token', reauthToken);
			}
			const execTokenEl = document.getElementById('swift-csv-pro-exec-password-token-export');
			const execToken = execTokenEl ? execTokenEl.value : '';
			if (execToken) {
				formData.append('swift_csv_pro_exec_password_token', execToken);
			}
		}

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
				if (directSqlBtn) {
					directSqlBtn.disabled = false;
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
 * @param {Object} data      Progress data
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
		const elapsedSeconds = startTime ? Math.floor((Date.now() - startTime) / 1000) : 0;
		const processedLabel = swiftCSV.messages.processedInfo || 'Processed';
		const rowsLabel = swiftCSV.messages.rowsLabel || 'rows';
		const secondsLabel = swiftCSV.messages.secondsLabel || 's';
		progressStats.textContent = `${percentage}% ${processedLabel} ${data.processed}/${data.total} ${rowsLabel} (${elapsedSeconds}${secondsLabel})`;
	}
}

/**
 * Complete AJAX export
 *
 * @param {string}      csvContent Complete CSV content
 * @param {HTMLElement} exportBtn  Export button
 * @param {HTMLElement} cancelBtn  Cancel button
 * @param {string}      postType   Post type
 */
function completeAjaxExport(csvContent, exportBtn, cancelBtn, postType) {
	if (window.SwiftCSVUtils && window.SwiftCSVUtils.addLogEntry) {
		window.SwiftCSVUtils.addLogEntry(swiftCSV.messages.exportCompleted, 'info', 'export');
	}

	const directSqlBtn = document.querySelector('#direct-sql-export-btn');

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
		exportBtn.value = exportBtn.dataset.originalText || swiftCSV.messages.startExport;
	}
	if (directSqlBtn) {
		directSqlBtn.disabled = false;
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
