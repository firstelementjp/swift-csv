(function () {
	window.SwiftCSVExportUnifiedModules = window.SwiftCSVExportUnifiedModules || {};

	window.SwiftCSVExportUnifiedModules.Logs = {
		pollExportLogs: function ({
			enableLogs,
			exportSession,
			afterId,
			setAfterId,
			buildLogMessage,
			requestOptions,
		}) {
			if (enableLogs !== '1') return Promise.resolve();
			if (!exportSession) return Promise.resolve();

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
					if (!data || !data.success || !data.data) return;
					const payload = data.data;
					if (payload.last_id !== undefined) {
						const nextAfterId = Number(payload.last_id) || afterId;
						if (typeof setAfterId === 'function') {
							setAfterId(nextAfterId);
						}
					}
					if (!payload.logs || !Array.isArray(payload.logs) || payload.logs.length === 0) {
						return;
					}
					payload.logs.forEach(item => {
						if (!item || !item.detail) return;
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
