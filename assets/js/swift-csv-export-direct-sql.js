/**
 * Direct SQL Export JavaScript
 *
 * Handles frontend interactions for Direct SQL Export functionality.
 *
 * @package Swift_CSV
 * @since 0.9.8
 */

(function ($) {
	'use strict';

	/**
	 * Direct SQL Export Handler
	 */
	const SwiftCSVExportDirectSQL = {
		/**
		 * Initialize Direct SQL Export
		 */
		init: function () {
			this.bindEvents();
			console.log('[Direct SQL Export] Initialized');
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			const self = this;

			// Direct SQL Export button click handler
			$(document).on('click', '#direct-sql-export-btn', function (e) {
				e.preventDefault();
				self.handleDirectSQLExport();
			});
		},

		/**
		 * Handle Direct SQL Export
		 */
		handleDirectSQLExport: function () {
			const $button = $('#direct-sql-export-btn');
			const $progressBar = $('#swift_csv_export_progress');
			const $progressText = $('#swift_csv_export_progress_text');

			// Get form data
			const formData = this.getFormData();

			console.log('[Direct SQL Export] Starting export with config:', formData);

			// Show loading state
			$button.prop('disabled', true).text('Exporting...');
			$progressBar.val(0);
			$progressText.text('0%');

			// Perform AJAX export
			$.post(
				ajaxurl,
				{
					action: 'swift_csv_ajax_export_direct_sql',
					nonce: swiftCSV.nonce,
					...formData,
				},
				function (response) {
					console.log('[Direct SQL Export] Response received:', response);

					if (response.success && response.data && response.data.csv) {
						// Download CSV
						SwiftCSVExportDirectSQL.downloadCSV(response.data.csv, response.data.count);

						// Show success message
						$progressBar.val(100);
						$progressText.text('100% - Complete!');
						$button.prop('disabled', false).text('Export Complete');

						console.log('[Direct SQL Export] Export completed successfully');
					} else {
						// Show error
						const errorMessage = response.data
							? response.data.message
							: 'Unknown error occurred';
						console.error('[Direct SQL Export] Export failed:', errorMessage);

						$progressBar.val(0);
						$progressText.text('Export failed');
						$button.prop('disabled', false).text('Export Failed');

						alert('Export failed: ' + errorMessage);
					}
				}
			)
				.fail(function (xhr, status, error) {
					console.error('[Direct SQL Export] AJAX request failed:', error);

					$progressBar.val(0);
					$progressText.text('Export failed');
					$button.prop('disabled', false).text('Export Failed');

					alert('Export failed: ' + error);
				})
				.always(function () {
					// Reset button after delay
					setTimeout(function () {
						$button.prop('disabled', false).text('Export CSV');
						$progressBar.val(0);
						$progressText.text('0%');
					}, 3000);
				});
		},

		/**
		 * Get form data
		 */
		getFormData: function () {
			// Debug: Check if elements exist
			console.log('[Direct SQL Export] Debug - Elements check:');
			console.log(
				'  post_type element:',
				$('#swift_csv_export_post_type').length,
				$('#swift_csv_export_post_type').val()
			);
			console.log(
				'  post_status element:',
				$('input[name="swift_csv_export_post_status"]:checked').length,
				$('input[name="swift_csv_export_post_status"]:checked').val()
			);
			console.log(
				'  export_scope element:',
				$('input[name="swift_csv_export_scope"]:checked').length,
				$('input[name="swift_csv_export_scope"]:checked').val()
			);

			return {
				post_type: $('#swift_csv_export_post_type').val() || 'post',
				post_status:
					$('input[name="swift_csv_export_post_status"]:checked').val() || 'publish',
				export_scope: $('input[name="swift_csv_export_scope"]:checked').val() || 'all',
				include_private_meta: $('#swift_csv_include_private_meta').is(':checked')
					? '1'
					: '0',
				export_limit: $('#swift_csv_export_limit').val() || '0',
				taxonomy_format: $('#swift_csv_taxonomy_format').val() || 'names',
				enable_logs: $('#swift_csv_export_enable_logs').is(':checked') ? '1' : '0',
			};
		},

		/**
		 * Download CSV file
		 */
		downloadCSV: function (csvContent, recordCount) {
			const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
			const link = document.createElement('a');
			const url = URL.createObjectURL(blob);

			const filename = 'direct-sql-export-' + new Date().toISOString().slice(0, 10) + '.csv';

			link.setAttribute('href', url);
			link.setAttribute('download', filename);
			link.style.visibility = 'hidden';

			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);

			URL.revokeObjectURL(url);

			console.log('[Direct SQL Export] CSV downloaded: ' + recordCount + ' records');
		},
	};

	// Initialize when document is ready
	$(document).ready(function () {
		if (typeof swiftCSV !== 'undefined') {
			SwiftCSVExportDirectSQL.init();
		}
	});

	// Make available globally
	window.SwiftCSVExportDirectSQL = SwiftCSVExportDirectSQL;
})(jQuery);
