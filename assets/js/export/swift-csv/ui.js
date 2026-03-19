/**
 * Swift CSV Export Unified UI Module
 *
 * Provides UI management functions for the unified export system,
 * including button state management and user feedback.
 *
 * @module SwiftCSVExportUnifiedModules.UI
 */
(function () {
	window.SwiftCSVExportUnifiedModules = window.SwiftCSVExportUnifiedModules || {};

	window.SwiftCSVExportUnifiedModules.UI = {
		/**
		 * Show completion state for export button
		 *
		 * @param {HTMLButtonElement} button - The export button element
		 */
		showComplete(button) {
			// For SQL export button, respect Pro license status
			if (button.id === 'direct-sql-export-btn') {
				button.disabled = !swiftCSV.enableDirectSqlExport;
			} else {
				button.disabled = false;
			}
			button.textContent = button.dataset.originalText || swiftCSV.highSpeedExportText;
		},

		/**
		 * Show error state for export button
		 *
		 * @param {HTMLButtonElement} button       - The export button element
		 * @param {string}            errorMessage - The error message to display
		 */
		showError(button, errorMessage) {
			// For SQL export button, respect Pro license status
			if (button.id === 'direct-sql-export-btn') {
				button.disabled = !swiftCSV.enableDirectSqlExport;
			} else {
				button.disabled = false;
			}
			button.textContent = button.dataset.originalText || swiftCSV.highSpeedExportText;
			const failedLabel =
				swiftCSV && swiftCSV.messages && swiftCSV.messages.failed
					? String(swiftCSV.messages.failed)
					: '';
			const prefix = failedLabel ? failedLabel + ':\n' : '';
			let message = String(errorMessage || '');
			// Translate known Pro-only runtime error message.
			if (
				message.indexOf('Direct SQL runtime is available in Swift CSV Pro only.') !== -1 &&
				swiftCSV &&
				swiftCSV.directSqlRuntimeUnavailable
			) {
				message = String(swiftCSV.directSqlRuntimeUnavailable);
			}
			if (
				failedLabel &&
				(message.indexOf(failedLabel + ':\n') === 0 ||
					message.indexOf(failedLabel + ': ') === 0 ||
					message.indexOf(failedLabel + ':') === 0)
			) {
				alert(message);
				return;
			}
			alert(prefix + message);
		},
	};
})();
