/**
 * Swift CSV Admin Scripts - Core
 *
 * Common utilities and functions shared across all modules.
 *
 */

/**
 * WordPress i18n fallback function
 *
 * Provides fallback for WordPress i18n when not available.
 *
 * @param {string} text - The text to translate
 * @return {string} The translated text or original text
 */
function __(text) {
	if (window.wp && window.wp.i18n && window.wp.i18n.__) {
		return window.wp.i18n.__('swift-csv', text)
	}
	// Fallback: return original text (will be translated by PHP if needed)
	return text
}

/**
 * Debug logging function
 *
 * Logs messages to console when debug mode is enabled.
 *
 * @param {string} message - The message to log
 * @param {string} type    - Log type (info, warn, error)
 */
function swiftCSVLog(message, type = 'info') {
	if (window.swiftCSV && window.swiftCSV.debug) {
		const timestamp = new Date().toISOString()
		const logMessage = `[Swift CSV] ${message}`

		switch (type) {
		case 'warn':
			console.warn(logMessage, timestamp)
			break
		case 'error':
			console.error(logMessage, timestamp)
			break
		default:
			console.info(logMessage, timestamp)
		}
	}
}

/**
 * Initialize logging system
 *
 * Sets up logging functionality for import/export operations.
 * Preserves initial messages while clearing old entries.
 */
function initLoggingSystem() {
	// Don't clear logs on page load - preserve initial messages
	// The initial messages are hardcoded in HTML and should remain visible
}

/**
 * Format file size in human readable format
 *
 * @param {number} bytes File size in bytes
 * @return {string} Formatted file size
 */
function formatFileSize(bytes) {
	if (bytes === 0) return '0 Bytes'

	const k = 1024
	const sizes = ['Bytes', 'KB', 'MB', 'GB']
	const i = Math.floor(Math.log(bytes) / Math.log(k))

	return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

/**
 * WordPress POST request helper
 *
 * @param {string} action - WordPress AJAX action
 * @param {Object} data   - Data to send
 * @return {Promise} Response promise
 */
async function wpPost(action, data) {
	const formData = new URLSearchParams({
		action,
		...data,
	})

	return fetch(swiftCSV.ajaxUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		body: formData,
	})
}

// Export for use in other modules
window.SwiftCSVCore = {
	__,
	swiftCSVLog,
	initLoggingSystem,
	formatFileSize,
	wpPost,
}
