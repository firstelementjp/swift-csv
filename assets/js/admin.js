/**
 * Swift CSV Admin JavaScript
 *
 * Handles batch export/import functionality with progress tracking.
 * Written in modern Vanilla JavaScript (no jQuery dependency).
 *
 * @since 0.9.5
 */

/* global swiftCSV */

document.addEventListener('DOMContentLoaded', function () {
	// Export batch handling
	const exportBtn = document.querySelector('#export-csv-btn');
	if (exportBtn) {
		exportBtn.addEventListener('click', function (e) {
			e.preventDefault();

			const postType = document.querySelector('#post_type')?.value;
			const postsPerPage = document.querySelector('#posts_per_page')?.value;

			// Validate input
			if (!postType || !postsPerPage || postsPerPage < 1) {
				alert(swiftCSV.messages.error);
				return;
			}

			// Start batch export
			startBatchExport(postType, postsPerPage);
		});
	}
});

/**
 * Start batch export process
 *
 * @param {string} postType - The post type to export.
 * @param {number} postsPerPage - Number of posts per batch.
 */
function startBatchExport(postType, postsPerPage) {
	const exportBtn = document.querySelector('#export-csv-btn');
	const progressContainer = document.querySelector('#swift-csv-export-progress');

	// Show loading state
	if (exportBtn) {
		exportBtn.disabled = true;
		exportBtn.textContent = 'Exporting...';
	}

	// Show progress container
	if (progressContainer) {
		progressContainer.style.display = 'block';
	}

	// Start batch export via AJAX
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
				// Start polling for progress
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

/**
 * Poll for export progress
 *
 * @param {string} batchId - The batch ID to track.
 */
function pollExportProgress(batchId) {
	const progressInterval = setInterval(() => {
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

					// Stop polling if completed
					if ('completed' === data.status) {
						clearInterval(progressInterval);
						showDownloadLink(data.download_url);
						resetExportButton();
					} else if ('error' === data.status) {
						clearInterval(progressInterval);
						alert(swiftCSV.messages.error);
						resetExportButton();
					}
				} else {
					throw new Error(data.data || 'Progress check failed');
				}
			})
			.catch(error => {
				console.error('Progress check error:', error);
				clearInterval(progressInterval);
				resetExportButton();
			});
	}, 2000); // Check every 2 seconds
}

/**
 * Update export progress UI
 *
 * @param {Object} data - Progress data.
 */
function updateExportProgress(data) {
	// Calculate percentage if not provided
	const percentage = data.percentage || 
		(data.total_rows > 0 ? Math.round((data.processed_rows / data.total_rows) * 100) : 0);

	const progressFill = document.querySelector('#swift-csv-export-progress .progress-fill');
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

/**
 * Show download link
 *
 * @param {string} downloadUrl - The download URL.
 */
function showDownloadLink(downloadUrl) {
	const downloadContainer = document.querySelector('#export-download-link');
	
	if (downloadContainer) {
		const downloadLink = downloadContainer.querySelector('a');
		if (downloadLink) {
			downloadLink.href = downloadUrl;
			downloadContainer.style.display = 'block';
		}
	}
}

/**
 * Reset export button
 */
function resetExportButton() {
	const exportBtn = document.querySelector('#export-csv-btn');
	if (exportBtn) {
		exportBtn.disabled = false;
		exportBtn.textContent = 'Export CSV';
	}
}
