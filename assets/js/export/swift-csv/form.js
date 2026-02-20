(function () {
	window.SwiftCSVExportUnifiedModules = window.SwiftCSVExportUnifiedModules || {}

	window.SwiftCSVExportUnifiedModules.Form = {
		getFormData () {
			const postTypeElement = document.getElementById('swift_csv_export_post_type')
			const postType = postTypeElement ? postTypeElement.value : 'post'

			if (!postTypeElement || !postTypeElement.value) {
				console.error('Post type element not found or has no value')
				throw new Error('Post type selection is required')
			}

			return {
				action: 'swift_csv_ajax_export',
				nonce: swiftCSV.nonce,
				post_type: postType,
				post_status:
					document.querySelector('input[name="swift_csv_export_post_status"]:checked')
						?.value || 'publish',
				export_scope:
					document.querySelector('input[name="swift_csv_export_scope"]:checked')?.value ||
					'all',
				include_private_meta: document.querySelector(
					'input[name="swift_csv_include_private_meta"]'
				)?.checked
					? '1'
					: '0',
				export_limit: document.getElementById('swift_csv_export_limit')?.value || '0',
				taxonomy_format:
					document.querySelector('input[name="taxonomy_format"]:checked')?.value ||
					'name',
				enable_logs: document.getElementById('swift_csv_export_enable_logs')?.checked
					? '1'
					: '0',
			}
		},
	}
})()
