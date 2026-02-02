/**
 * Swift CSV Admin JavaScript
 *
 * Handles batch export functionality with progress tracking.
 *
 * @since 0.9.3
 */

jQuery(document).ready(function ($) {
	// Export batch handling
	$('#export-csv-btn').on('click', function (e) {
		e.preventDefault();
		
		console.log('Export button clicked'); // Debug log

		const postType = $('#post_type').val();
		const postsPerPage = $('#posts_per_page').val();

		// Validate input
		if (!postType || !postsPerPage || postsPerPage < 1) {
			alert(swiftCSV.messages.error);
			return;
		}

		console.log('Starting batch export'); // Debug log

		// Start batch export
		startBatchExport(postType, postsPerPage);
	});

	/**
	 * Check for download after sync export
	 */
	function checkForDownload() {
		// Check for download link after a short delay
		setTimeout(function() {
			$.ajax({
				url: swiftCSV.ajaxUrl,
				type: 'POST',
				data: {
					action: 'swift_csv_check_download',
					nonce: swiftCSV.nonce,
				},
				success: function(response) {
					if (response && response.download_url) {
						showDownloadLink(response.download_url);
					} else {
						// Try to construct download URL
						const downloadUrl = '/wp-content/uploads/swift-csv/exports/export.csv';
						showDownloadLink(downloadUrl);
					}
				},
				error: function() {
					// Fallback download URL
					const downloadUrl = '/wp-content/uploads/swift-csv/exports/export.csv';
					showDownloadLink(downloadUrl);
				},
			});
		}, 2000);
	}

	/**
	 * Start batch export process
	 */
	function startBatchExport(postType, postsPerPage) {
		console.log('startBatchExport called'); // Debug log
		
		// Show loading indicator for sync export
		console.log('Updating button text'); // Debug log
		console.log('Button element:', $('#export-csv-btn')); // Debug log
		console.log('Button length:', $('#export-csv-btn').length); // Debug log
		
		// Try different methods to update button
		$('#export-csv-btn').prop('disabled', true);
		$('#export-csv-btn').val('Exporting...');
		$('#export-csv-btn').attr('value', 'Exporting...');
		$('#export-csv-btn').text('Exporting...');
		
		console.log('Button text after update:', $('#export-csv-btn').text()); // Debug log
		console.log('Button value after update:', $('#export-csv-btn').val()); // Debug log
		console.log('Button attr value after update:', $('#export-csv-btn').attr('value')); // Debug log
		console.log('Button updated'); // Debug log

		// Start batch export via AJAX
		$.ajax({
			url: swiftCSV.ajaxUrl,
			type: 'POST',
			data: {
				action: 'swift_csv_start_export',
				nonce: swiftCSV.nonce,
				post_type: postType,
				posts_per_page: postsPerPage,
			},
			success: function (response) {
				if (response && (response.batch_id || response.success)) {
					if (response.batch_id) {
						// Start polling for progress (batch mode)
						pollProgress(response.batch_id);
					} else if (response.completed) {
						// Sync export completed - show download immediately
						if (response.download_url) {
							showDownloadLink(response.download_url);
						}
						// Reset button
						$('#export-csv-btn').prop('disabled', false).text('Export CSV');
					} else {
						// Sync export completed - check for download
						checkForDownload();
					}
				} else {
					showError(response.message || swiftCSV.messages.error);
				}
			},
			error: function () {
				// Reset button on error
				$('#export-csv-btn').prop('disabled', false).text('Export CSV');
				showError(swiftCSV.messages.error);
			},
		});
	}

	/**
	 * Poll for export progress
	 */
	function pollProgress(batchId) {
		const interval = setInterval(function () {
			$.ajax({
				url: swiftCSV.ajaxUrl,
				type: 'POST',
				data: {
					action: 'swift_csv_export_progress',
					nonce: swiftCSV.nonce,
					batch_id: batchId,
				},
				success: function (response) {
					console.log('Progress Response:', response); // Debug log
					console.log('Response Type:', typeof response); // Debug log
					console.log('Response === null:', response === null); // Debug log

					// Handle different response formats
					if (response === null || response === undefined) {
						console.log('Response is null/undefined');
						clearInterval(interval);
						showError('サーバーエラーが発生しました。');
						return;
					}

					if (response.success && response.data) {
						updateProgress(response.data);

						// Check if completed
						if (response.data.status === 'completed') {
							clearInterval(interval);
							showComplete(response.data);
						} else if (response.data.status === 'error') {
							clearInterval(interval);
							showError(response.data.message || swiftCSV.messages.error);
						}
					} else if (response.batch_id && response.status) {
						// Handle direct response format
						updateProgress(response);

						// Check if completed
						if (response.status === 'completed') {
							clearInterval(interval);
							showComplete(response);
						} else if (response.status === 'error') {
							clearInterval(interval);
							showError(response.message || swiftCSV.messages.error);
						}
					} else {
						clearInterval(interval);
						showError('進捗確認でエラーが発生しました。');
					}
				},
				error: function (xhr, status, error) {
					clearInterval(interval);
					showError(swiftCSV.messages.error);
				},
			});
		}, 2000); // Poll every 2 seconds
	}

	/**
	 * Show progress UI
	 */
	function showProgress() {
		$('#swift-csv-export-progress').show();
		$('#swift-csv-export-form').addClass('swift-csv-exporting');
		updateProgressBar(0);
		updateStatus(swiftCSV.messages.preparing);
		hideDownloadLink();
	}

	/**
	 * Update progress display
	 */
	function updateProgress(data) {
		const percentage = Math.round((data.processed_rows / data.total_rows) * 100);
		updateProgressBar(percentage);
		updateStatus(
			`${swiftCSV.messages.processing} (${data.processed_rows} / ${data.total_rows})`
		);
	}

	/**
	 * Update progress bar
	 */
	function updateProgressBar(percentage) {
		$('.progress-fill').css('width', percentage + '%');
		$('.progress-text').text(percentage + '%');
	}

	/**
	 * Hide progress bar
	 */
	function hideProgressBar() {
		$('.progress-bar').hide();
	}

	/**
	 * Update status message
	 */
	function updateStatus(message) {
		$('.progress-status').text(message);
	}

	/**
	 * Show completion status
	 */
	function showComplete(data) {
		updateStatus(swiftCSV.messages.complete);
		hideProgressBar();

		// Show download link if available
		if (data.download_url) {
			showDownloadLink(data.download_url);
		} else {
			// Try to construct download URL from batch_id
			const downloadUrl = '/wp-content/uploads/swift-csv/exports/' + data.batch_id + '.csv';
			showDownloadLink(downloadUrl);
		}
	}

	/**
	 * Show error state
	 */
	function showError(message) {
		updateStatus(message);
		$('.progress-bar').addClass('error');

		// Re-enable form
		$('#swift-csv-export-form').removeClass('swift-csv-exporting');
	}

	/**
	 * Show download link
	 */
	function showDownloadLink(url) {
		const $link = $('#export-download-link a');
		$link.attr('href', url);
		$('#export-download-link').show();
	}

	/**
	 * Hide download link
	 */
	function hideDownloadLink() {
		$('#export-download-link').hide();
	}
});
