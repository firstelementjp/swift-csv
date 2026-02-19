(function () {
	window.SwiftCSVExportUnifiedModules = window.SwiftCSVExportUnifiedModules || {};

	window.SwiftCSVExportUnifiedModules.Download = {
		enableDownloadButtonForExport: function (csvContent, postType) {
			const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
			const url = URL.createObjectURL(blob);

			const now = new Date();
			const dateStr =
				String(now.getFullYear()) +
				'-' +
				String(now.getMonth() + 1).padStart(2, '0') +
				'-' +
				String(now.getDate()).padStart(2, '0') +
				'-' +
				String(now.getHours()).padStart(2, '0') +
				'-' +
				String(now.getMinutes()).padStart(2, '0') +
				'-' +
				String(now.getSeconds()).padStart(2, '0');

			const filename = `swiftcsv_export_${postType}_${dateStr}.csv`;

			const downloadBtn = document.querySelector('#export-download-btn');
			if (downloadBtn) {
				downloadBtn.href = url;
				downloadBtn.download = filename;
				downloadBtn.classList.add('enabled');
			}

			if (window.SwiftCSVUtils && window.SwiftCSVUtils.addLogEntry) {
				window.SwiftCSVUtils.addLogEntry(
					swiftCSV.messages.downloadReady + ' ' + filename,
					'info',
					'export'
				);
			}
		},

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
})();
