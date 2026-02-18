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
			const $progressBar = $('#swift_csv_export_progress');
			const $progressText = $('#swift_csv_export_progress_text');

			// Get form data
			const formData = this.getFormData();

			// Add export method to form data
			formData.export_method = exportMethod;

			// Show loading state
			$button.prop('disabled', true).text('Exporting...');
			$progressBar.val(0);
			$progressText.text('0%');

			// Perform AJAX export
			$.post(
				ajaxurl,
				{
					action: 'swift_csv_ajax_export',
					nonce: swiftCSV.nonce,
					...formData,
				},
				function (response) {
					if (response.success) {
						if (exportMethod === 'direct_sql') {
							// Direct SQL: Immediate download
							SwiftCSVExportUnified.downloadCSV(
								response.data.csv_content,
								response.data.record_count
							);
							SwiftCSVExportUnified.showComplete(
								$button,
								$progressBar,
								$progressText
							);
						} else {
							// Standard: Start batch processing
							SwiftCSVExportUnified.handleBatchExport(
								response.data.export_session,
								$button,
								$progressBar,
								$progressText
							);
						}
					} else {
						// Show error
						const errorMessage = response.data
							? response.data
							: 'Unknown error occurred';

						SwiftCSVExportUnified.showError(
							$button,
							$progressBar,
							$progressText,
							errorMessage
						);
					}
				}
			).fail(function (xhr, status, error) {
				SwiftCSVExportUnified.showError($button, $progressBar, $progressText, error);
			});
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
		 * @param {jQuery} $progressBar Progress bar element
		 * @param {jQuery} $progressText Progress text element
		 * @param {string} errorMessage Error message
		 */
		showError: function ($button, $progressBar, $progressText, errorMessage) {
			$progressBar.val(0);
			$progressText.text('Export failed');
			$button.prop('disabled', false).text('Export Failed');

			alert('Export failed: ' + errorMessage);

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
