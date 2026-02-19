(function () {
	window.SwiftCSVExportUnifiedModules = window.SwiftCSVExportUnifiedModules || {};

	window.SwiftCSVExportUnifiedModules.UI = {
		showComplete: function (button) {
			button.disabled = false;
			button.textContent = swiftCSV.exportCompleteText;

			setTimeout(function () {
				button.textContent = swiftCSV.highSpeedExportText;
			}, 3000);
		},

		showError: function (button, errorMessage) {
			button.disabled = false;
			button.textContent = swiftCSV.exportFailedText;
			alert(swiftCSV.messages.failed + ': ' + errorMessage);

			setTimeout(function () {
				button.textContent = swiftCSV.highSpeedExportText;
			}, 3000);
		},
	};
})();
