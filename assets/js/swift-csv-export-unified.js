/**
 * Swift CSV Unified Export Scripts
 *
 * Unified export functionality supporting both standard and Direct SQL methods.
 * Automatically selects appropriate export method based on data size and user preference.
 *
 * @package SwiftCSV
 */

(function ($) {
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
			$(document).on('click', '#ajax-export-csv-btn', function (e) {
				e.preventDefault();
				self.handleExport('standard');
			});

			// Direct SQL Export button click handler
			$(document).on('click', '#direct-sql-export-btn', function (e) {
				e.preventDefault();
				self.handleExport('direct_sql');
			});
		},

		/**
		 * Handle export with specified method
		 *
		 * @param {string} exportMethod Export method ('standard' or 'direct_sql')
		 */
		handleExport: function (exportMethod) {
			const $button =
				exportMethod === 'direct_sql'
					? $('#direct-sql-export-btn')
					: $('#ajax-export-csv-btn');

			// Show cancel button
			this.showCancelButton($button);

			// Disable export button and show loading
			$button.prop('disabled', true).text('Exporting...');

			const formData = this.getFormData();
			formData.export_method = exportMethod;

			// Start export
			this.processExport(formData, $button, exportMethod);
		},

		/**
		 * Show cancel button
		 *
		 * @param {jQuery} $exportButton Export button
		 */
		showCancelButton: function ($exportButton) {
			// Create or show cancel button
			let $cancelBtn = $('#swift-csv-cancel-btn');
			if ($cancelBtn.length === 0) {
				$cancelBtn = $('<button>')
					.attr('id', 'swift-csv-cancel-btn')
					.attr('type', 'button')
					.addClass('button button-secondary')
					.text('Cancel')
					.css('margin-left', '10px');
				$exportButton.after($cancelBtn);
			}
			$cancelBtn.show();

			// Bind cancel handler
			$cancelBtn.off('click').on('click', () => {
				this.cancelExport($exportButton);
			});
		},

		/**
		 * Cancel export
		 *
		 * @param {jQuery} $exportButton Export button
		 */
		cancelExport: function ($exportButton) {
			// Hide cancel button
			$('#swift-csv-cancel-btn').hide();

			// Reset export button
			$exportButton
				.prop('disabled', false)
				.text(
					$exportButton.is('#direct-sql-export-btn')
						? 'High-Speed Export (Direct SQL)'
						: 'Standard Export (WP Functions)'
				);

			// Send cancel request
			const formData = new URLSearchParams({
				action: 'swift_csv_cancel_export',
				nonce: swiftCSV.nonce,
				export_session: this.exportSession,
			});

			fetch(swiftCSV.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: formData,
			}).catch(() => {
				// Ignore cancel errors
			});
		},

		/**
		 * Process export with chunked handling
		 *
		 * @param {Object} formData Form data
		 * @param {jQuery} $button Export button
		 * @param {string} exportMethod Export method
		 */
		processExport: function (formData, $button, exportMethod) {
			const self = this;
			let csvContent = '';
			let startRow = 0;

			function processChunk() {
				const chunkData = new URLSearchParams({
					...formData,
					action: 'swift_csv_ajax_export',
					nonce: swiftCSV.nonce,
					start_row: startRow,
					export_session: self.exportSession || '',
				});

				fetch(swiftCSV.ajaxUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: chunkData,
				})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							// Store session if first response
							if (!self.exportSession && data.export_session) {
								self.exportSession = data.export_session;
							}

							// Append CSV content
							if (data.csv_chunk) {
								csvContent += data.csv_chunk;
							}

							// Update progress
							self.updateProgress(data.processed, data.total, data.progress);

							// Continue processing or complete
							if (data.continue) {
								startRow = data.processed;
								processChunk();
							} else {
								// Export completed
								self.completeExport(csvContent, $button, exportMethod);
							}
						} else {
							throw new Error(data.data || 'Export failed');
						}
					})
					.catch(error => {
						self.showError($button, error.message);
					});
			}

			processChunk();
		},

		/**
		 * Update progress display
		 *
		 * @param {number} processed Processed count
		 * @param {number} total Total count
		 * @param {number} progress Progress percentage
		 */
		updateProgress: function (processed, total, progress) {
			const $progressBar = $('.swift-csv-progress .progress-bar');
			const $progressText = $('.swift-csv-progress .progress-text');

			if ($progressBar.length) {
				$progressBar.val(progress);
			}
			if ($progressText.length) {
				$progressText.text(`${progress}% (${processed}/${total})`);
			}
		},

		/**
		 * Complete export and enable download
		 *
		 * @param {string} csvContent CSV content
		 * @param {jQuery} $button Export button
		 * @param {string} exportMethod Export method
		 */
		completeExport: function (csvContent, $button, exportMethod) {
			// Hide cancel button
			$('#swift-csv-cancel-btn').hide();

			// Create download
			this.downloadCSV(csvContent, csvContent.split('\n').length - 1);

			// Reset button
			$button
				.prop('disabled', false)
				.text(
					$button.is('#direct-sql-export-btn')
						? 'High-Speed Export (Direct SQL)'
						: 'Standard Export (WP Functions)'
				);

			// Show completion message
			const $progressBar = $('.swift-csv-progress .progress-bar');
			const $progressText = $('.swift-csv-progress .progress-text');
			if ($progressBar.length) {
				$progressBar.val(100);
			}
			if ($progressText.length) {
				$progressText.text('100% - Complete!');
			}
		},

		/**
		 * Handle batch export for standard method
		 *
		 * @param {string} exportSession Export session identifier
		 * @param {jQuery} $button Button element
		 * @param {jQuery} $progressBar Progress bar element
		 * @param {jQuery} $progressText Progress text element
		 */
		handleBatchExport: function (exportSession, $button, $progressBar, $progressText) {
			const self = this;

			// Update button text
			$button.text('Processing...');

			// Start polling for progress
			self.pollProgress(exportSession, $button, $progressBar, $progressText);
		},

		/**
		 * Poll export progress
		 *
		 * @param {string} exportSession Export session identifier
		 * @param {jQuery} $button Button element
		 * @param {jQuery} $progressBar Progress bar element
		 * @param {jQuery} $progressText Progress text element
		 */
		pollProgress: function (exportSession, $button, $progressBar, $progressText) {
			const self = this;

			const poll = function () {
				$.post(
					ajaxurl,
					{
						action: 'swift_csv_ajax_export_logs',
						nonce: swiftCSV.nonce,
						export_session: exportSession,
					},
					function (response) {
						if (response.success && response.data) {
							const logs = response.data.logs || [];
							const lastLog = logs[logs.length - 1];

							if (lastLog) {
								// Update progress
								const progress = self.extractProgress(lastLog.message);
								$progressBar.val(progress);
								$progressText.text(`${progress}% - ${lastLog.message}`);

								// Check if completed
								if (lastLog.message.includes('Export completed')) {
									self.showComplete($button, $progressBar, $progressText);
									return;
								}

								// Check for errors
								if (
									lastLog.message.includes('Error') ||
									lastLog.message.includes('Failed')
								) {
									self.showError(
										$button,
										$progressBar,
										$progressText,
										lastLog.message
									);
									return;
								}
							}

							// Continue polling
							setTimeout(poll, 500);
						} else {
							// Error getting logs
							self.showError(
								$button,
								$progressBar,
								$progressText,
								'Failed to get export progress'
							);
						}
					}
				).fail(function () {
					self.showError(
						$button,
						$progressBar,
						$progressText,
						'Failed to get export progress'
					);
				});
			};

			// Start polling
			poll();
		},

		/**
		 * Extract progress percentage from log message
		 *
		 * @param {string} message Log message
		 * @returns {number} Progress percentage
		 */
		extractProgress: function (message) {
			const match = message.match(/(\d+)%/);
			return match ? parseInt(match[1], 10) : 0;
		},

		/**
		 * Show complete state
		 *
		 * @param {jQuery} $button Button element
		 * @param {jQuery} $progressBar Progress bar element
		 * @param {jQuery} $progressText Progress text element
		 */
		showComplete: function ($button, $progressBar, $progressText) {
			$progressBar.val(100);
			$progressText.text('100% - Complete!');
			$button.prop('disabled', false).text('Export Complete');

			// Reset after delay
			setTimeout(function () {
				$button.text(
					$button.is('#direct-sql-export-btn')
						? 'High-Speed Export (Direct SQL)'
						: 'Standard Export (WP Functions)'
				);
				$progressBar.val(0);
				$progressText.text('0%');
			}, 3000);
		},

		/**
		 * Show error state
		 *
		 * @param {jQuery} $button Button element
		 * @param {string} errorMessage Error message
		 */
		showError: function ($button, errorMessage) {
			// Hide cancel button
			$('#swift-csv-cancel-btn').hide();

			// Reset button
			$button.prop('disabled', false).text('Export Failed');

			// Show alert
			alert('Export failed: ' + errorMessage);

			// Reset after delay
			setTimeout(function () {
				$button.text(
					$button.is('#direct-sql-export-btn')
						? 'High-Speed Export (Direct SQL)'
						: 'Standard Export (WP Functions)'
				);
			}, 3000);
		},

		/**
		 * Get form data
		 *
		 * @returns {Object} Form data object
		 */
		getFormData: function () {
			return {
				post_type: $('#swift_csv_export_post_type').val() || 'post',
				post_status:
					$('input[name="swift_csv_export_post_status"]:checked').val() || 'publish',
				export_scope: $('input[name="swift_csv_export_scope"]:checked').val() || 'all',
				include_private_meta: $('#swift_csv_include_private_meta').is(':checked')
					? '1'
					: '0',
				export_limit: $('#swift_csv_export_limit').val() || '0',
				taxonomy_format: $('input[name="taxonomy_format"]:checked').val() || 'name',
				enable_logs: $('#swift_csv_export_enable_logs').is(':checked') ? '1' : '0',
			};
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

			URL.revokeObjectURL(url);
		},
	};

	// Initialize when document is ready
	$(document).ready(function () {
		if (typeof swiftCSV !== 'undefined') {
			SwiftCSVExportUnified.init();
		}
	});
})(jQuery);
