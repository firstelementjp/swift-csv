/**
 * FE CSV Import & Export Export Scripts - Download Helper Module
 *
 * Handles enabling the Export UI download button and provides utility helpers
 * for triggering CSV downloads using data generated during export.
 *
 * @namespace FeCsvImportExportExportUnifiedModules
 */

(function () {
	/**
	 * @namespace FeCsvImportExportExportUnifiedModules
	 */
	window.FeCsvImportExportExportUnifiedModules =
		window.FeCsvImportExportExportUnifiedModules || {};

	window.FeCsvImportExportExportUnifiedModules.Download = {
		/**
		 * Enable the download button once CSV export is ready and attach file metadata.
		 *
		 * @param {string} csvContent CSV text content returned from export.
		 * @param {string} postType   Target post type slug used for filename.
		 */
		enableDownloadButtonForExport(csvContent, postType) {
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

			const filename = `fe_csv_export_${postType}_${dateStr}.csv`;

			const downloadBtn = document.querySelector('#export-download-btn');
			if (downloadBtn) {
				downloadBtn.href = url;
				downloadBtn.download = filename;
				downloadBtn.classList.add('enabled');
			}

			if (window.FeCsvImportExportUtils && window.FeCsvImportExportUtils.addLogEntry) {
				window.FeCsvImportExportUtils.addLogEntry(
					feCsvImportExport.messages.downloadReady + ' ' + filename,
					'info',
					'export'
				);
			}
		},

		/**
		 * Trigger immediate CSV download using a temporary anchor element.
		 *
		 * @param {string} csvContent CSV text content to download.
		 */
		downloadCSV(csvContent) {
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
