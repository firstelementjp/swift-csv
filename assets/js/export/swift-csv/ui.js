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
			button.disabled = false;
			button.textContent = button.dataset.originalText || swiftCSV.highSpeedExportText;
		},

		/**
		 * Show error state for export button
		 *
		 * @param {HTMLButtonElement} button       - The export button element
		 * @param {string}            errorMessage - The error message to display
		 */
		showError(button, errorMessage) {
			button.disabled = false;
			button.textContent = button.dataset.originalText || swiftCSV.highSpeedExportText;
			alert(swiftCSV.messages.failed + ': ' + errorMessage);
		},
	};
})();
