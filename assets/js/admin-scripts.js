/**
 * Swift CSV Admin Scripts
 *
 * @package SwiftCSV
 */

// WordPress i18n API
const { __ } = window.wp.i18n || { __: text => text };

document.addEventListener('DOMContentLoaded', function () {
	// Batch export functionality
	const exportBtn = document.querySelector('#export-csv-btn');
	if (exportBtn) {
		exportBtn.addEventListener('click', function (e) {
			e.preventDefault();
			const postType = document.querySelector('#post_type')?.value;
			const postsPerPage = document.querySelector('#posts_per_page')?.value;

			if (!postType || !postsPerPage || postsPerPage < 1) {
				alert(__('An error occurred. Please try again.', 'swift-csv'));
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
});

function handleAjaxExport(e) {
	e.preventDefault();

	const postType = document.querySelector('#ajax_export_post_type')?.value;
	const exportScope =
		document.querySelector('input[name="export_scope"]:checked')?.value || 'basic';
	const includePrivateMeta = document.querySelector('input[name="include_private_meta"]')?.checked
		? '1'
		: '0';
	const exportLimit = document.querySelector('#export_limit')?.value || '';
	const progressContainer = document.querySelector('#swift-csv-ajax-export-progress');

	if (!progressContainer) {
		console.error('Progress element not found!');
		return;
	}

	const exportBtn = document.querySelector('#ajax-export-csv-btn');
	const cancelBtn = document.querySelector('#ajax-export-cancel-btn');
	const startTime = Date.now();
	let isCancelled = false;

	// Show progress
	progressContainer.style.display = 'block';
	if (exportBtn) {
		exportBtn.disabled = true;
		exportBtn.value = __('Exporting...', 'swift-csv');
	}
	if (cancelBtn) {
		cancelBtn.style.display = 'inline-block';
		console.log('Cancel button found and shown:', cancelBtn);
	} else {
		console.log('Cancel button not found!');
	}

	let csvContent = '';

	// Cancel functionality
	if (cancelBtn) {
		cancelBtn.addEventListener('click', function () {
			isCancelled = true;
			if (exportBtn) {
				exportBtn.disabled = false;
				exportBtn.value = swiftCSV.messages.exportCsv;
			}
			if (cancelBtn) {
				cancelBtn.style.display = 'none';
			}
			const statusEl = progressContainer.querySelector('.progress-status');
			if (statusEl) {
				statusEl.textContent = swiftCSV.messages.cancelled;
			}
		});
	}

	function processChunk(startRow = 0) {
		if (isCancelled) return;

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
				updateAjaxProgress(progressContainer, data, startTime);

				// Continue processing or complete
				if (data.continue && !isCancelled) {
					setTimeout(() => processChunk(data.processed), 100);
				} else {
					completeAjaxExport(
						progressContainer,
						csvContent,
						exportBtn,
						cancelBtn,
						postType
					);
				}
			})
			.catch(error => {
				console.error('Export error:', error);
				const statusEl = progressContainer.querySelector('.progress-status');
				if (statusEl) {
					statusEl.textContent = swiftCSV.messages.failed + ': ' + error.message;
				}
				if (exportBtn) {
					exportBtn.disabled = false;
					exportBtn.value = swiftCSV.messages.exportCsv;
				}
			});
	}

	// Start processing
	processChunk();
}

function updateAjaxProgress(container, data, startTime) {
	const progressFill = container.querySelector('.progress-bar-fill');
	const progressText = container.querySelector('.progress-text');
	const processedEl = container.querySelector('.progress-processed');
	const totalEl = container.querySelector('.progress-total');
	const statusEl = container.querySelector('.progress-status');
	const timeEl = container.querySelector('.progress-time');

	// Update progress bar
	if (progressFill) {
		progressFill.style.width = data.progress + '%';
	}

	// Update progress text on the bar
	if (progressText) {
		progressText.textContent = data.progress + '%';
	}

	// Update processed count
	if (processedEl) {
		processedEl.textContent = data.processed;
	}

	// Update total count
	if (totalEl) {
		totalEl.textContent = data.total;
	}

	// Update status
	if (statusEl && data.status) {
		if (data.status === 'completed') {
			statusEl.textContent = swiftCSV.messages.completed;
		} else {
			statusEl.textContent = swiftCSV.messages.processing;
		}
	}

	// Update time display - update only numbers with improved calculation
	if (timeEl && data.processed > 0) {
		const elapsed = Date.now() - startTime;

		// Use average rate for better accuracy (ignore first few batches)
		const minBatchesForAverage = 3;
		let rate;

		if (data.processed >= minBatchesForAverage * 50) {
			// Assuming 50 items per batch
			rate = data.processed / elapsed;
		} else {
			// For early batches, use a more conservative estimate
			rate = Math.min(data.processed / elapsed, 0.002); // Cap at reasonable rate
		}

		const remaining = (data.total - data.processed) / rate;
		const elapsedMinutes = Math.floor(elapsed / 60000);
		const remainingMinutes = Math.max(0, Math.floor(remaining / 60000));

		console.log('Time calculation debug:');
		console.log('- Processed:', data.processed);
		console.log('- Total:', data.total);
		console.log('- Elapsed (ms):', elapsed);
		console.log('- Rate:', rate);
		console.log('- Remaining (ms):', remaining);
		console.log('- Elapsed minutes:', elapsedMinutes);
		console.log('- Remaining minutes:', remainingMinutes);

		// Update only the number spans
		const elapsedEl = container.querySelector('.progress-elapsed');
		const remainingEl = container.querySelector('.progress-remaining');

		if (elapsedEl) {
			elapsedEl.textContent = elapsedMinutes;
		}
		if (remainingEl) {
			remainingEl.textContent = remainingMinutes;
		}

		console.log('Updated elapsed:', elapsedMinutes, 'remaining:', remainingMinutes);
	}
}

function completeAjaxExport(container, csvContent, exportBtn, cancelBtn, postType) {
	const statusEl = container.querySelector('.progress-status');
	const downloadLink = document.querySelector('#ajax-export-download-link');

	if (statusEl) {
		statusEl.textContent = swiftCSV.messages.completed;
	}

	if (exportBtn) {
		exportBtn.disabled = false;
		exportBtn.value = swiftCSV.messages.exportCsv;
	}

	if (cancelBtn) {
		cancelBtn.style.display = 'none';
	}

	if (csvContent && downloadLink) {
		const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
		const url = URL.createObjectURL(blob);
		const link = downloadLink.querySelector('a');

		if (link) {
			link.href = url;
			const timestamp = new Date().toISOString().slice(0, 19).replace(/[:-]/g, '');
			link.download = `swiftcsv_export_${postType}_${timestamp}.csv`;
		}

		downloadLink.style.display = 'block';
	}
}

function startBatchExport(postType, postsPerPage) {
	const exportBtn = document.querySelector('#export-csv-btn');
	const progressContainer = document.querySelector('#swift-csv-export-progress');

	if (exportBtn) {
		exportBtn.disabled = true;
		exportBtn.textContent = swiftCSV.messages.exporting;
	}

	if (progressContainer) {
		progressContainer.style.display = 'block';
	}

	const formData = new URLSearchParams({
		action: 'swift_csv_start_export',
		post_type: postType,
		posts_per_page: postsPerPage,
		nonce: swiftCSV.nonce,
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
			if (data.success) {
				pollExportProgress(data.batch_id);
			} else {
				throw new Error(data.data || 'Export failed');
			}
		})
		.catch(error => {
			console.error('Export error:', error);
			alert(swiftCSV.messages.error);
			resetExportButton();
		});
}

function pollExportProgress(batchId) {
	const interval = setInterval(() => {
		const formData = new URLSearchParams({
			action: 'swift_csv_check_progress',
			batch_id: batchId,
			nonce: swiftCSV.nonce,
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
				if (data.success) {
					updateExportProgress(data);

					if (data.status === 'completed') {
						clearInterval(interval);
						showDownloadLink(data.download_url);
						resetExportButton();
					} else if (data.status === 'error') {
						clearInterval(interval);
						alert(swiftCSV.messages.error);
						resetExportButton();
					}
				} else {
					throw new Error(data.data || 'Progress check failed');
				}
			})
			.catch(error => {
				console.error('Progress check error:', error);
				clearInterval(interval);
				resetExportButton();
			});
	}, 2000);
}

function updateExportProgress(data) {
	const percentage =
		data.percentage ||
		(data.total_rows > 0 ? Math.round((data.processed_rows / data.total_rows) * 100) : 0);
	const progressFill = document.querySelector('#swift-csv-export-progress .progress-bar-fill');
	const progressText = document.querySelector('#swift-csv-export-progress .progress-text');
	const processedRows = document.querySelector('#swift-csv-export-progress .processed-rows');
	const totalRows = document.querySelector('#swift-csv-export-progress .total-rows');

	if (progressFill) {
		progressFill.style.width = percentage + '%';
	}

	if (progressText) {
		progressText.textContent = percentage + '%';
	}

	if (processedRows) {
		processedRows.textContent = data.processed_rows;
	}

	if (totalRows) {
		totalRows.textContent = data.total_rows;
	}
}

function showDownloadLink(downloadUrl) {
	const downloadLink = document.querySelector('#export-download-link');
	if (downloadLink) {
		const link = downloadLink.querySelector('a');
		if (link) {
			link.href = downloadUrl;
		}
		downloadLink.style.display = 'block';
	}
}

function resetExportButton() {
	const exportBtn = document.querySelector('#export-csv-btn');
	if (exportBtn) {
		exportBtn.disabled = false;
		exportBtn.textContent = swiftCSV.messages.exportCsv;
	}
}
