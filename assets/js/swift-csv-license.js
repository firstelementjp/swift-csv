/**
 * Swift CSV Admin Scripts - License
 *
 * Handles license activation and deactivation functionality.
 *
 * @package SwiftCSV
 */

/**
 * Initialize license functionality
 */
function initLicense() {
	// License key visibility toggle (Show/Hide password).
	const licenseInput = document.getElementById('swift_csv_pro_license_key_input');
	const licenseToggle = document.getElementById('swift_csv_pro_license_toggle_visibility');
	if (licenseInput && licenseToggle) {
		licenseToggle.addEventListener('click', () => {
			const isPassword = licenseInput.type === 'password';
			licenseInput.type = isPassword ? 'text' : 'password';
			licenseToggle.textContent = isPassword
				? swiftCSV.messages.hide
				: swiftCSV.messages.show;
		});
	}

	// We must use event delegation on the document body, because the license tab
	// is part of the main settings form, not a separate Pro feature.
	document.body.addEventListener('click', async e => {
		const button = e.target.closest('.swift-csv-license-button');
		if (!button) {
			return;
		}

		const action = button.dataset.action;
		if (!action) {
			return;
		}

		const licenseKeyInput = document.getElementById('swift_csv_pro_license_key_input');
		const licenseKey = licenseKeyInput ? licenseKeyInput.value : '';
		const spinner = button.closest('div')
			? button.closest('div').querySelector('.spinner')
			: null;

		if (!licenseKey && action === 'activate') {
			alert(SwiftCSVCore.__('Please enter a license key.', 'swift-csv-pro'));
			return;
		}

		button.disabled = true;
		if (spinner) {
			spinner.style.display = 'inline-block';
		}

		try {
			// Use the wpPost helper function from core
			const response = await SwiftCSVCore.wpPost('swift_csv_pro_manage_license', {
				nonce: swiftCSV.nonce,
				license_key: licenseKey,
				license_action: action,
			});

			// Parse JSON response (SwiftCSVCore.wpPost returns raw Response object)
			const data = await response.json();

			if (!data.success) {
				// Throw an error so that the catch block can show the backend message.
				throw new Error(
					data.data?.message ||
						data.message ||
						SwiftCSVCore.__('License operation failed.', 'swift-csv-pro')
				);
			}

			// Always reload the page to show the new status.
			location.reload();
		} catch (error) {
			console.error('License error:', error);
			// eslint-disable-next-line no-alert
			alert(
				error.message ||
					SwiftCSVCore.__(
						'An error occurred while processing your request. Please try again.',
						'swift-csv-pro'
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
window.SwiftCSVLicense = {
	initLicense,
};
