/**
 * FE CSV Import & Export Export Scripts - Form Helper Module
 *
 * Collects export form values, normalizes them, and yields payloads consumed by
 * the unified export AJAX/Direct SQL handlers.
 *
 * @package
 */

(function () {
	/**
	 * @namespace FeCsvImportExportExportUnifiedModules
	 */
	window.FeCsvImportExportExportUnifiedModules = window.FeCsvImportExportExportUnifiedModules || {};

	window.FeCsvImportExportExportUnifiedModules.Form = {
		/**
		 * Collect export form values and normalize them into an AJAX payload.
		 *
		 * @return {Object} Normalized export request payload.
		 * @throws {Error} When the post type field is missing.
		 */
		getFormData() {
			const postTypeElement = document.getElementById('fe_csv_import_export_export_post_type');
			const postType = postTypeElement ? postTypeElement.value : 'post';

			if (!postTypeElement || !postTypeElement.value) {
				console.error('Post type element not found or has no value');
				throw new Error('Post type selection is required');
			}

			return {
				action: 'fe_csv_import_export_ajax_export',
				nonce: feCsvImportExport.nonce,
				post_type: postType,
				post_status:
					document.querySelector('input[name="fe_csv_import_export_export_post_status"]:checked')
						?.value || 'publish',
				export_scope:
					document.querySelector('input[name="fe_csv_import_export_export_scope"]:checked')?.value ||
					'all',
				include_taxonomies: document.querySelector(
					'input[name="fe_csv_import_export_include_taxonomies"]'
				)?.checked
					? '1'
					: '0',
				include_custom_fields: document.querySelector(
					'input[name="fe_csv_import_export_include_custom_fields"]'
				)?.checked
					? '1'
					: '0',
				include_private_meta: document.querySelector(
					'input[name="fe_csv_import_export_include_private_meta"]'
				)?.checked
					? '1'
					: '0',
				taxonomy_hierarchical: document.querySelector(
					'input[name="fe_csv_import_export_taxonomy_hierarchical"]'
				)?.checked
					? '1'
					: '0',
				export_limit: document.getElementById('fe_csv_import_export_export_limit')?.value || '0',
				taxonomy_format:
					document.querySelector('input[name="taxonomy_format"]:checked')?.value ||
					'name',
				enable_logs:
					window.feCsvImportExport &&
					window.feCsvImportExport.advancedSettings &&
					window.feCsvImportExport.advancedSettings.enableLogs
						? '1'
						: '0',
			};
		},
	};
})();
