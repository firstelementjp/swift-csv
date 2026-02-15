/**
 * Swift CSV Admin Scripts - Export
 *
 * Handles CSV export functionality with AJAX chunked processing.
 *
 * @package SwiftCSV
 */

/**
 * Handle AJAX export form submission
 *
 * @param {Event} e - Form submission event
 */
function handleAjaxExport(e) {
	e.preventDefault();

	// Clear export log
	clearLog('export');
	addLogEntry(swiftCSV.messages.startingExport, 'info', 'export');

	const postType = document.querySelector('#ajax_export_post_type')?.value;
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
	const exportSession = (Date.now().toString(36) + Math.random().toString(36).slice(2)).replace(
		/\./g,
		''
	);

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
	let currentExportAbortController = null;
	let exportCancelHandler = null;

	// Cancel functionality
	if (cancelBtn) {
		if (exportCancelHandler) {
			cancelBtn.removeEventListener('click', exportCancelHandler);
		}

		exportCancelHandler = function () {
			isCancelled = true;
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
			export_scope: exportScope,
			include_private_meta: includePrivateMeta,
			taxonomy_format: taxonomyFormat,
			export_limit: exportLimit,
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

					// Append final CSV chunk
					csvContent += data.csv_chunk;
					completeAjaxExport(csvContent, exportBtn, cancelBtn, postType);
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

				console.error('Export error:', error);
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
		exportBtn.value = swiftCSV.messages.exportCsv;
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
