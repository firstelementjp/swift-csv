/**
 * FE CSV Import & Export Admin Scripts - License
 *
 * Handles license activation and deactivation functionality.
 *
 */

/**
 * Initialize license UI interactions (visibility toggle and activation actions).
 *
 * Sets up password visibility toggle and handles activation/deactivation requests
 * via delegated click listeners.
 */
function initLicense() {
	// License key visibility toggle (Show/Hide password).
	const licenseInput = document.getElementById('fe_csv_import_export_pro_license_key_input');
	const licenseToggle = document.getElementById('fe_csv_import_export_pro_license_toggle_visibility');
	if (licenseInput && licenseToggle) {
		licenseToggle.addEventListener('click', () => {
			const isPassword = licenseInput.type === 'password';
			licenseInput.type = isPassword ? 'text' : 'password';
			licenseToggle.textContent = isPassword
				? feCsvImportExport.messages.hide
				: feCsvImportExport.messages.show;
		});
	}

	// We must use event delegation on the document body, because the license tab
	// is part of the main settings form, not a separate Pro feature.
	/**
	 * Handle activation/deactivation button clicks within the license form.
	 *
	 * @param {MouseEvent} e Click event dispatched on the document body.
	 */
	document.body.addEventListener('click', async e => {
		const button = e.target.closest('.fe-csv-import-export-license-button');
		if (!button) {
			return;
		}

		const action = button.dataset.action;
		if (!action) {
			return;
		}

		const licenseKeyInput = document.getElementById('fe_csv_import_export_pro_license_key_input');
		const licenseKey = licenseKeyInput ? licenseKeyInput.value : '';
		const spinner = button.closest('div')
			? button.closest('div').querySelector('.spinner')
			: null;

		if (!licenseKey && action === 'activate') {
			alert(FeCsvImportExportCore.__('Please enter a license key.', 'fe-csv-import-export-pro'));
			return;
		}

		button.disabled = true;
		if (spinner) {
			spinner.style.display = 'inline-block';
		}

		try {
			// Use the wpPost helper function from core
			const response = await FeCsvImportExportCore.wpPost('fe_csv_import_export_pro_manage_license', {
				nonce: feCsvImportExport.nonce,
				license_key: licenseKey,
				license_action: action,
			});

			// Parse JSON response (FeCsvImportExportCore.wpPost returns raw Response object)
			const data = await response.json();

			if (!data.success) {
				// Throw an error so that the catch block can show the backend message.
				throw new Error(
					data.data?.message ||
						data.message ||
						FeCsvImportExportCore.__('License operation failed.', 'fe-csv-import-export-pro')
				);
			}

			// Always reload the page to show the new status.
			location.reload();
		} catch (error) {
			console.error('License error:', error);
			// eslint-disable-next-line no-alert
			alert(
				error.message ||
					FeCsvImportExportCore.__(
						'An error occurred while processing your request. Please try again.',
						'fe-csv-import-export-pro'
					)
			);
		} finally {
			button.disabled = false;
			if (spinner) {
				spinner.style.display = 'none';
			}
		}
	});
}

// Export for use in main script
window.FeCsvImportExportLicense = {
	initLicense,
};
