(function () {
	window.SwiftCSVExportUnifiedModules = window.SwiftCSVExportUnifiedModules || {};

	window.SwiftCSVExportUnifiedModules.UI = {
		showComplete(button) {
			button.disabled = false;
			button.textContent = button.dataset.originalText || swiftCSV.highSpeedExportText;
		},

		showError(button, errorMessage) {
			button.disabled = false;
			button.textContent = button.dataset.originalText || swiftCSV.highSpeedExportText;
			alert(swiftCSV.messages.failed + ': ' + errorMessage);
		},
	};
})();
