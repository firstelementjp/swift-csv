/**
 * Swift CSV Export Scripts - AJAX Helper Module
 *
 * Provides a thin wrapper for posting export requests to the WordPress AJAX
 * endpoint, shared by the unified export UI modules.
 *
 */

(function () {
	/**
	 * @namespace SwiftCSVExportUnifiedModules
	 */
	window.SwiftCSVExportUnifiedModules = window.SwiftCSVExportUnifiedModules || {};

	window.SwiftCSVExportUnifiedModules.Ajax = {
		/**
		 * Send a POST request with form data to the WordPress AJAX endpoint.
		 *
		 * @param {URLSearchParams|string|FormData} formData       Form payload to submit.
		 * @param {Object}                          [extraOptions] Optional overrides for fetch options.
		 * @return {Promise<Response>} Fetch promise resolving to the AJAX response.
		 */
		postForm(formData, extraOptions) {
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
			);
		},
	};
})();
