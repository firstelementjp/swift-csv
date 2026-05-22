/**
 * FE CSV Import & Export Export Unified UI Module
 *
 * Provides UI management functions for the unified export system,
 * including button state management and user feedback.
 *
 * @module FeCsvImportExportExportUnifiedModules.UI
 */
(function () {
	window.FeCsvImportExportExportUnifiedModules = window.FeCsvImportExportExportUnifiedModules || {};

	window.FeCsvImportExportExportUnifiedModules.UI = {
		/**
		 * Show completion state for export button
		 *
		 * @param {HTMLButtonElement} button - The export button element
		 */
		showComplete(button) {
			// For SQL export button, respect Pro license status
			if (button.id === 'direct-sql-export-btn') {
				button.disabled = !feCsvImportExport.enableDirectSqlExport;
			} else {
				button.disabled = false;
			}
			button.textContent = button.dataset.originalText || feCsvImportExport.highSpeedExportText;
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
				button.disabled = !feCsvImportExport.enableDirectSqlExport;
			} else {
				button.disabled = false;
			}
			button.textContent = button.dataset.originalText || feCsvImportExport.highSpeedExportText;
			const failedLabel =
				feCsvImportExport && feCsvImportExport.messages && feCsvImportExport.messages.failed
					? String(feCsvImportExport.messages.failed)
					: '';
			const prefix = failedLabel ? failedLabel + ':\n' : '';
			let message = String(errorMessage || '');
			// Translate known Pro-only runtime error message.
			if (
				message.indexOf('Direct SQL runtime is available in FE CSV Import & Export Pro only.') !== -1 &&
				feCsvImportExport &&
				feCsvImportExport.directSqlRuntimeUnavailable
			) {
				message = String(feCsvImportExport.directSqlRuntimeUnavailable);
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
