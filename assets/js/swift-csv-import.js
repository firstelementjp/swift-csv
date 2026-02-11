/**
 * Swift CSV Admin Scripts - Import
 *
 * Handles CSV import functionality with AJAX chunked processing.
 *
 * @package SwiftCSV
 */

/**
 * Initialize file upload functionality
 */
function initFileUpload() {
	console.log('initFileUpload() called'); // Debug log

	const uploadArea = document.querySelector('#csv-file-upload');
	const fileInput = document.querySelector('#ajax_csv_file');
	const fileInfo = document.querySelector('#csv-file-info');
	const removeBtn = document.querySelector('#remove-file-btn');

	console.log('Elements found:', {
		// Debug log
		uploadArea: !!uploadArea,
		fileInput: !!fileInput,
		fileInfo: !!fileInfo,
		removeBtn: !!removeBtn,
	});

	if (!uploadArea || !fileInput) {
		console.log('Missing required elements, exiting'); // Debug log
		return;
	}

	// Prevent multiple event listener registrations
	if (uploadArea.dataset.swiftCsvInitialized === 'true') {
		console.log('Already initialized, skipping'); // Debug log
		return;
	}

	// Mark as initialized
	uploadArea.dataset.swiftCsvInitialized = 'true';
	console.log('Marked as initialized'); // Debug log

	// File selection change event first
	fileInput.addEventListener('change', e => {
		console.log('File input change event fired'); // Debug log
		console.log('Selected files:', e.target.files); // Debug log

		if (e.target.files.length > 0) {
			console.log('Calling handleFileSelect from change event'); // Debug log
			handleFileSelect(e.target.files[0]);
		}
	});

	// Click to upload - prevent duplicate clicks
	let isClicking = false;

	uploadArea.addEventListener(
		'click',
		function (e) {
			console.log('Upload area clicked'); // Debug log

			if (isClicking) {
				console.log('Already clicking, ignoring'); // Debug log
				return;
			}

			isClicking = true;
			console.log('Triggering file input click'); // Debug log

			// Use setTimeout to ensure proper timing
			setTimeout(() => {
				try {
					fileInput.click();
					console.log('File input click() called successfully'); // Debug log
				} catch (error) {
					console.error('Error calling fileInput.click():', error);
				} finally {
					// Reset flag after a short delay
					setTimeout(() => {
						isClicking = false;
					}, 100);
				}
			}, 0);
		},
		false
	);

	// Drag and drop
	uploadArea.addEventListener('dragover', e => {
		e.preventDefault();
		console.log('Drag over event fired'); // Debug log
		uploadArea.classList.add('dragover');
	});

	uploadArea.addEventListener('dragleave', () => {
		console.log('Drag leave event fired'); // Debug log
		uploadArea.classList.remove('dragover');
	});

	uploadArea.addEventListener('drop', e => {
		console.log('Drop event fired'); // Debug log
		e.preventDefault();
		uploadArea.classList.remove('dragover');

		const files = e.dataTransfer.files;
		if (files.length > 0) {
			handleFileSelect(files[0]);
		}
	});

	// Remove file
	if (removeBtn) {
		removeBtn.addEventListener('click', () => {
			fileInput.value = '';
			fileInfo.classList.remove('visible'); // ← CSSクラスで非表示
			uploadArea.classList.remove('file-selected');
		});
	}
}

/**
 * Handle file selection
 *
 * @param {File} file Selected file
 */
function handleFileSelect(file) {
	console.log('handleFileSelect() called with file:', file); // Debug log

	const fileInfo = document.querySelector('#csv-file-info');
	const uploadArea = document.querySelector('#csv-file-upload');
	const fileName = document.querySelector('#csv-file-name');
	const fileSize = document.querySelector('#csv-file-size');

	console.log('handleFileSelect elements found:', {
		// Debug log
		fileInfo: !!fileInfo,
		uploadArea: !!uploadArea,
		fileName: !!fileName,
		fileSize: !!fileSize,
	});

	if (fileInfo && uploadArea && fileName && fileSize) {
		console.log('Setting file info...'); // Debug log
		fileName.textContent = file.name;
		fileSize.textContent = SwiftCSVCore.formatFileSize(file.size);
		fileInfo.classList.add('visible'); // ← CSSクラスで表示
		uploadArea.classList.add('file-selected');
		console.log('File info set successfully'); // Debug log
	} else {
		console.log('Missing elements for file display'); // Debug log
	}
}

/**
 * Handle AJAX import form submission
 *
 * @param {Event} e - Form submission event
 */
function handleAjaxImport(e) {
	e.preventDefault();

	// Clear import log
	clearLog('import');
	addLogEntry(swiftCSV.messages.startingImport, 'info', 'import');

	const file = document.querySelector('#ajax_csv_file')?.files[0];

	if (!file) {
		addLogEntry('No file selected', 'error', 'import');
		return;
	}

	const postType = document.querySelector('#import_post_type')?.value || 'post';
	const updateExisting = document.querySelector('input[name="swift_csv_import_update_existing"]')
		?.checked
		? '1'
		: '0';
	const taxonomyFormat =
		document.querySelector('input[name="taxonomy_format"]:checked')?.value || 'name';
	const dryRun = document.querySelector('input[name="swift_csv_import_dry_run"]')?.checked
		? '1'
		: '0';

	// Log import settings
	SwiftCSVCore.swiftCSVLog(swiftCSV.messages.fileInfo + ' ' + file.name, 'debug');
	SwiftCSVCore.swiftCSVLog(
		swiftCSV.messages.fileSize + ' ' + SwiftCSVCore.formatFileSize(file.size),
		'debug'
	);
	SwiftCSVCore.swiftCSVLog(swiftCSV.messages.postTypeInfo + ' ' + postType, 'debug');
	addLogEntry(
		swiftCSV.messages.updateExistingInfo +
			' ' +
			(updateExisting ? swiftCSV.messages.yes : swiftCSV.messages.no),
		'debug',
		'import'
	);

	const importBtn = document.querySelector('#ajax-import-csv-btn');
	const cancelBtn = document.querySelector('#ajax-import-cancel-btn');
	const startTime = Date.now();
	let isCancelled = false;

	// Update button states
	if (importBtn) {
		importBtn.disabled = true;
		importBtn.textContent = swiftCSV.messages.processing;
	}
	if (cancelBtn) {
		cancelBtn.style.display = 'inline-block';
	}

	// Cancel functionality
	if (cancelBtn) {
		cancelBtn.addEventListener('click', function () {
			isCancelled = true;
			addLogEntry(swiftCSV.messages.importCancelledByUser, 'warning', 'import');

			if (importBtn) {
				importBtn.disabled = false;
				importBtn.textContent = swiftCSV.messages.startImport;
			}
			if (cancelBtn) {
				cancelBtn.style.display = 'none';
			}
		});
	}

	// Start with first chunk (original 1-stage approach)
	processImportChunk(
		0,
		0,
		0,
		0,
		file,
		postType,
		updateExisting,
		taxonomyFormat,
		dryRun,
		importBtn,
		cancelBtn,
		startTime,
		isCancelled
	);
}

/**
 * Process import chunk
 *
 * @param {number} startRow Starting row
 * @param {number} cumulativeCreated Cumulative created count
 * @param {number} cumulativeUpdated Cumulative updated count
 * @param {number} cumulativeErrors Cumulative error count
 * @param {File} file Selected file
 * @param {string} postType Post type
 * @param {string} updateExisting Update existing flag
 * @param {string} taxonomyFormat Taxonomy format
 * @param {string} dryRun Dry run flag
 * @param {HTMLElement} importBtn Import button element
 * @param {HTMLElement} cancelBtn Cancel button element
 * @param {number} startTime Start time
 * @param {boolean} isCancelled Cancel flag
 */
function processImportChunk(
	startRow = 0,
	cumulativeCreated = 0,
	cumulativeUpdated = 0,
	cumulativeErrors = 0,
	file,
	postType,
	updateExisting,
	taxonomyFormat,
	dryRun,
	importBtn,
	cancelBtn,
	startTime,
	isCancelled
) {
	if (isCancelled) return;

	SwiftCSVCore.swiftCSVLog(swiftCSV.messages.processingChunk + ' ' + startRow, 'debug');

	const formData = new FormData();
	formData.append('action', 'swift_csv_ajax_import');
	formData.append('nonce', swiftCSV.nonce);
	formData.append('csv_file', file); // ← 直接ファイルを送信
	formData.append('post_type', postType);
	formData.append('update_existing', updateExisting);
	formData.append('taxonomy_format', taxonomyFormat);
	formData.append('dry_run', dryRun);
	formData.append('start_row', startRow);
	formData.append('cumulative_created', cumulativeCreated);
	formData.append('cumulative_updated', cumulativeUpdated);
	formData.append('cumulative_errors', cumulativeErrors);

	fetch(swiftCSV.ajaxUrl, {
		method: 'POST',
		body: formData,
	})
		.then(response => response.json())
		.then(data => {
			// Debug: Log received data
			console.log('Import data received:', data);
			console.log('data.success:', data.success);
			console.log('data.continue:', data.continue);
			console.log('data.success && data.continue:', data.success && data.continue);

			if (data.success && data.continue) {
				// Update progress
				updateImportProgress(data, startTime);

				// Process next chunk
				processImportChunk(
					data.processed,
					data.cumulative_created,
					data.cumulative_updated,
					data.cumulative_errors,
					file, // ← ファイルを渡す
					postType,
					updateExisting,
					taxonomyFormat,
					dryRun,
					importBtn,
					cancelBtn,
					startTime,
					isCancelled
				);
			} else if (data.success) {
				// Import completed - update progress one final time
				updateImportProgress(data, startTime);
				completeAjaxImport(data, importBtn, cancelBtn);
			} else {
				// Handle error
				addLogEntry(swiftCSV.messages.importError + ' ' + data.error, 'error', 'import');

				if (importBtn) {
					importBtn.disabled = false;
					importBtn.textContent = swiftCSV.messages.startImport;
				}
				if (cancelBtn) {
					cancelBtn.style.display = 'none';
				}
			}
		})
		.catch(error => {
			console.error('Import error:', error);

			// Extract the actual error message
			let errorMessage = error.message;
			if (
				errorMessage === 'Import failed' &&
				error.message.includes('Format mismatch detected')
			) {
				errorMessage = error.message;
			}

			addLogEntry(swiftCSV.messages.importError + ' ' + errorMessage, 'error', 'import');

			if (importBtn) {
				importBtn.disabled = false;
				importBtn.textContent = swiftCSV.messages.startImport;
			}
			if (cancelBtn) {
				cancelBtn.style.display = 'none';
			}
		});
}

/**
 * Update AJAX progress for import
 *
 * @param {Object} data Progress data
 * @param {number} startTime Start time
 */
function updateImportProgress(data, startTime) {
	// Debug: Log progress update
	console.log('Updating import progress:', data);

	// Find progress elements in the new UI structure
	const progressContainer = document.querySelector('.swift-csv-progress');
	if (!progressContainer) {
		SwiftCSVCore.swiftCSVLog('Progress container not found');
		return;
	}

	console.log('Progress container found:', progressContainer);

	const progressFill = progressContainer.querySelector('.progress-bar-fill');
	const processedEl = progressContainer.querySelector('.processed-rows');
	const totalEl = progressContainer.querySelector('.total-rows');
	const percentageEl = progressContainer.querySelector('.percentage');

	// Find detail count elements
	const createdEl = progressContainer.querySelector('.created-count');
	const updatedEl = progressContainer.querySelector('.updated-count');
	const errorEl = progressContainer.querySelector('.error-count');

	// Update progress bar
	if (progressFill && data.progress !== undefined) {
		progressFill.style.width = data.progress + '%';
	}

	// Update stats
	if (processedEl && data.processed !== undefined) {
		processedEl.textContent = data.processed;
	}
	if (totalEl && data.total !== undefined) {
		totalEl.textContent = data.total;
	}
	if (percentageEl && data.progress !== undefined) {
		percentageEl.textContent = data.progress;
	}

	// Update detail counts
	if (createdEl && data.created !== undefined) {
		createdEl.textContent = data.created;
	}
	if (updatedEl && data.updated !== undefined) {
		updatedEl.textContent = data.updated;
	}
	if (errorEl && data.errors !== undefined) {
		errorEl.textContent = data.errors;
	}
}

/**
 * Complete AJAX import
 *
 * @param {Object} data Import results
 * @param {HTMLElement} importBtn Import button
 * @param {HTMLElement} cancelBtn Cancel button
 */
function completeAjaxImport(data, importBtn, cancelBtn) {
	// Check if this was a Dry Run
	const isDryRun = data.dry_run || false;

	// Add appropriate completion message
	if (isDryRun) {
		addLogEntry('[Dry Run] ' + swiftCSV.messages.dryRunCompleted, 'success', 'import');
	} else {
		addLogEntry(swiftCSV.messages.importCompleted, 'success', 'import');
	}

	// Log final results - use cumulative values
	if (data.cumulative_created !== undefined) {
		const createdMessage = isDryRun
			? '[Dry Run] ' + swiftCSV.messages.dryRunCreated + ' '
			: swiftCSV.messages.totalImported + ' ';
		addLogEntry(createdMessage + data.cumulative_created, 'success', 'import');
	}
	if (data.cumulative_updated !== undefined) {
		const updatedMessage = isDryRun
			? '[Dry Run] ' + swiftCSV.messages.dryRunUpdated + ' '
			: swiftCSV.messages.totalUpdated + ' ';
		addLogEntry(updatedMessage + data.cumulative_updated, 'success', 'import');
	}
	if (data.cumulative_errors !== undefined) {
		const errorMessage = isDryRun
			? '[Dry Run] ' + swiftCSV.messages.dryRunErrors + ' '
			: swiftCSV.messages.totalErrors + ' ';
		addLogEntry(
			errorMessage + data.cumulative_errors,
			data.cumulative_errors > 0 ? 'warning' : 'success',
			'import'
		);
	}

	// Reset buttons
	if (importBtn) {
		importBtn.disabled = false;
		importBtn.textContent = swiftCSV.messages.startImport;
	}
	if (cancelBtn) {
		cancelBtn.style.display = 'none';
	}

	// Reset file input
	const fileInput = document.querySelector('#ajax_csv_file');
	const uploadArea = document.querySelector('#csv-file-upload');
	const fileInfo = document.querySelector('#csv-file-info');

	if (fileInput) {
		fileInput.value = '';
	}
	if (uploadArea) {
		uploadArea.classList.remove('file-selected');
	}
	if (fileInfo) {
		fileInfo.classList.remove('visible'); // ← CSSクラスで非表示
	}
}

// Export for use in main script
window.SwiftCSVImport = {
	initFileUpload,
	handleFileSelect,
	handleAjaxImport,
	updateImportProgress,
	completeAjaxImport,
};
