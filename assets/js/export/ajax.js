(function () {
	window.SwiftCSVExportUnifiedModules = window.SwiftCSVExportUnifiedModules || {}

	window.SwiftCSVExportUnifiedModules.Ajax = {
		postForm (formData, extraOptions) {
			return fetch(
				swiftCSV.ajaxUrl,
				Object.assign(
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: formData,
					},
					extraOptions || {}
				)
			)
		},
	}
})()
