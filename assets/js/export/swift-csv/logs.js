/**
 * Swift CSV Export Scripts - Logs Helper Module
 *
 * Handles polling of export log entries and exposes helpers for appending them
 * to the unified export UI log panel.
 *
 */

(function () {
	/**
	 * @namespace SwiftCSVExportUnifiedModules
	 */
	window.SwiftCSVExportUnifiedModules = window.SwiftCSVExportUnifiedModules || {};

	window.SwiftCSVExportUnifiedModules.Logs = {
		/**
		 * Poll export logs from the server and append them to the export log panel.
		 *
		 * @param {Object}   options                 Polling options.
		 * @param {string}   options.enableLogs      Whether logs are enabled ('1'/'0').
		 * @param {string}   options.exportSession   Current export session ID.
		 * @param {number}   options.afterId         Last log ID retrieved.
		 * @param {Function} options.setAfterId      Callback to update the log cursor.
		 * @param {Function} options.buildLogMessage Optional log message builder.
		 * @param {Object}   options.requestOptions  Extra fetch options.
		 * @return {Promise<void>} Resolves when polling is complete.
		 */
		pollExportLogs({
			enableLogs,
			exportSession,
			afterId,
			setAfterId,
			buildLogMessage,
			requestOptions,
		}) {
			if (enableLogs !== '1') {
				return Promise.resolve();
			}
			if (!exportSession) {
				return Promise.resolve();
			}

			const logFormData = new URLSearchParams({
				action: 'swift_csv_ajax_export_logs',
				nonce: swiftCSV.nonce,
				export_session: exportSession,
				enable_logs: enableLogs,
				after_id: String(afterId),
				limit: '200',
			});

			const ajax = window.SwiftCSVExportUnifiedModules.Ajax;

			return ajax
				.postForm(logFormData, requestOptions)
				.then(response => response.json())
				.then(data => {
					if (!data || !data.success || !data.data) {
						return;
					}
					const payload = data.data;
					if (payload.last_id !== undefined) {
						const nextAfterId = Number(payload.last_id) || afterId;
						if (typeof setAfterId === 'function') {
							setAfterId(nextAfterId);
						}
					}
					if (
						!payload.logs ||
						!Array.isArray(payload.logs) ||
						payload.logs.length === 0
					) {
						return;
					}
					payload.logs.forEach(item => {
						if (!item || !item.detail) {
							return;
						}
						const detail = item.detail;
						const logMessage =
							typeof buildLogMessage === 'function'
								? buildLogMessage(detail)
								: String(detail.title || '');
						if (window.SwiftCSVUtils && window.SwiftCSVUtils.addLogEntry) {
							window.SwiftCSVUtils.addLogEntry(
								logMessage,
								detail.status === 'success' ? 'success' : 'error',
								'export'
							);
						}
					});
				})
				.catch(() => {
					// Silently handle polling errors.
				});
		},
	};
})();
