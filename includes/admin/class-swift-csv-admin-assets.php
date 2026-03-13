<?php
/**
 * Admin assets handler
 *
 * Handles enqueueing scripts and styles for the Swift CSV admin pages.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin assets handler
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Admin_Assets {

	/**
	 * Enqueue admin styles
	 *
	 * @since  0.9.8
	 * @param  string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_styles( $hook ) {
		if ( 'tools_page_swift-csv' === $hook ) {
			$debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;
			$suffix     = $debug_mode ? '' : '.min';
			$css_path   = 'assets/css/swift-csv-style' . $suffix . '.css';
			$css_fs     = SWIFT_CSV_PLUGIN_DIR . ltrim( $css_path, '/' );
			if ( '' === $suffix && ! file_exists( $css_fs ) ) {
				$css_path = 'assets/css/swift-csv-style.min.css';
			}

			wp_enqueue_style(
				'swift-csv-admin',
				SWIFT_CSV_PLUGIN_URL . ltrim( $css_path, '/' ),
				[],
				SWIFT_CSV_VERSION
			);
		}
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @since  0.9.8
	 * @param  string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'tools_page_swift-csv' === $hook ) {
			$debug_mode           = defined( 'WP_DEBUG' ) && WP_DEBUG;
			$suffix               = $debug_mode ? '' : '.min';
			$advanced_enable_logs = class_exists( 'Swift_CSV_Settings_Helper' )
				? (bool) Swift_CSV_Settings_Helper::get( 'advanced', 'enable_logs', true )
				: true;

			$script_url = static function ( $relative_path ) use ( $suffix ) {
				$min_path      = preg_replace( '/\.js$/', $suffix . '.js', $relative_path );
				$preferred_fs  = SWIFT_CSV_PLUGIN_DIR . ltrim( $min_path, '/' );
				$fallback_path = preg_replace( '/\.js$/', '.min.js', $relative_path );
				$fallback_fs   = SWIFT_CSV_PLUGIN_DIR . ltrim( $fallback_path, '/' );

				if ( file_exists( $preferred_fs ) ) {
					return SWIFT_CSV_PLUGIN_URL . ltrim( $min_path, '/' );
				}

				if ( '' === $suffix && file_exists( $fallback_fs ) ) {
					return SWIFT_CSV_PLUGIN_URL . ltrim( $fallback_path, '/' );
				}

				return SWIFT_CSV_PLUGIN_URL . ltrim( $relative_path, '/' );
			};

			// Core utilities (must be loaded first).
			wp_register_script(
				'swift-csv-core',
				$script_url( 'assets/js/swift-csv-core.js' ),
				[ 'wp-i18n' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'swift-csv-export-unified-module-ajax',
				$script_url( 'assets/js/export/swift-csv/ajax.js' ),
				[ 'swift-csv-core' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'swift-csv-export-unified-module-download',
				$script_url( 'assets/js/export/swift-csv/download.js' ),
				[ 'swift-csv-core' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'swift-csv-export-unified-module-form',
				$script_url( 'assets/js/export/swift-csv/form.js' ),
				[ 'swift-csv-core' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'swift-csv-export-unified-module-ui',
				$script_url( 'assets/js/export/swift-csv/ui.js' ),
				[ 'swift-csv-core' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'swift-csv-export-unified-module-logs',
				$script_url( 'assets/js/export/swift-csv/logs.js' ),
				[ 'swift-csv-core', 'swift-csv-export-unified-module-ajax' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'swift-csv-export-original',
				$script_url( 'assets/js/export/swift-csv/original.js' ),
				[ 'swift-csv-core', 'swift-csv-export-unified' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			// Export functionality.
			wp_register_script(
				'swift-csv-export-unified',
				$script_url( 'assets/js/swift-csv-export-unified.js' ),
				[
					'swift-csv-core',
					'swift-csv-export-unified-module-ajax',
					'swift-csv-export-unified-module-download',
					'swift-csv-export-unified-module-form',
					'swift-csv-export-unified-module-ui',
					'swift-csv-export-unified-module-logs',
				],
				'0.9.8',
				true
			);

			// Import functionality.
			wp_register_script(
				'swift-csv-import',
				$script_url( 'assets/js/swift-csv-import.js' ),
				[ 'swift-csv-core' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			// License functionality.
			wp_register_script(
				'swift-csv-license',
				$script_url( 'assets/js/swift-csv-license.js' ),
				[ 'swift-csv-core' ],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			// Main entry point (must be loaded last).
			wp_register_script(
				'swift-csv-main',
				$script_url( 'assets/js/swift-csv-main.js' ),
				[
					'swift-csv-core',
					'swift-csv-export-unified',
					'swift-csv-export-original',
					'swift-csv-import',
					'swift-csv-license',
				],
				SWIFT_CSV_VERSION . '.' . time(),
				true
			);

			wp_localize_script(
				'swift-csv-core',
				'swiftCSV',
				[
					'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
					'ajaxurl'               => admin_url( 'admin-ajax.php' ),
					'nonce'                 => wp_create_nonce( 'swift_csv_ajax_nonce' ),
					'debug'                 => $debug_mode,
					'advancedSettings'      => [
						'enableLogs' => $advanced_enable_logs,
					],
					'hasProAdmin'           => class_exists( 'Swift_CSV_Pro_Admin' ),
					'enableDirectSqlImport' => (bool) apply_filters( 'swift_csv_enable_direct_sql_import', false ),
					'maxLogEntries'         => apply_filters( 'swift_csv_max_log_entries', 30 ),
					'highSpeedExportText'   => esc_html__( 'High-Speed Export', 'swift-csv' ),
					'standardExportText'    => esc_html__( 'Standard Export (WP Compatible)', 'swift-csv' ),
					'exportCompleteText'    => esc_html__( 'Export Complete', 'swift-csv' ),
					'exportFailedText'      => esc_html__( 'Export Failed', 'swift-csv' ),
					'csvContentNotFound'    => esc_html__( 'CSV content not found in response', 'swift-csv' ),
					'unknownError'          => esc_html__( 'Unknown error', 'swift-csv' ),
					'highSpeedImportText'   => esc_html__( 'High-Speed Import', 'swift-csv' ),
					'standardImportText'    => esc_html__( 'Standard Import (WP Compatible)', 'swift-csv' ),
					'importCompleteText'    => esc_html__( 'Import Complete', 'swift-csv' ),
					'importFailedText'      => esc_html__( 'Import Failed', 'swift-csv' ),
					'messages'              => [
						'exportCsv'               => esc_html__( 'Export CSV', 'swift-csv' ),
						'startExport'             => esc_html__( 'Start Export', 'swift-csv' ),
						'importCsv'               => esc_html__( 'Import CSV', 'swift-csv' ),
						'startImport'             => esc_html__( 'Start Import', 'swift-csv' ),
						'exporting'               => esc_html__( 'Exporting...', 'swift-csv' ),
						'importing'               => esc_html__( 'Importing...', 'swift-csv' ),
						'readyToExport'           => esc_html__( 'Ready to start export...', 'swift-csv' ),
						'readyToImport'           => esc_html__( 'Ready to start import...', 'swift-csv' ),
						'importComplete'          => esc_html__( 'Import Complete!', 'swift-csv' ),
						'importCompleted'         => esc_html__( 'Import completed successfully!', 'swift-csv' ),
						'error'                   => esc_html__( 'An error occurred. Please try again.', 'swift-csv' ),
						'success'                 => esc_html__( 'Operation completed successfully!', 'swift-csv' ),
						'cancelled'               => esc_html__( 'Export cancelled', 'swift-csv' ),
						'failed'                  => esc_html__( 'Export failed', 'swift-csv' ),
						'importSettings'          => esc_html__( 'Import Settings', 'swift-csv' ),
						'dropFileHere'            => esc_html__( 'Drop CSV file here or click to browse', 'swift-csv' ),
						'maxFileSize'             => esc_html__( 'Maximum file size: %s', 'swift-csv' ),
						'custom'                  => esc_html__( 'Custom', 'swift-csv' ),
						'customHelp'              => esc_html__( 'Use the %1$s hook to specify custom export items and order. See %2$s for details.', 'swift-csv' ),
						'documentation'           => esc_html__( 'documentation', 'swift-csv' ),
						'startingImport'          => esc_html__( 'Starting import process...', 'swift-csv' ),
						'fileInfo'                => esc_html__( 'File:', 'swift-csv' ),
						'fileSize'                => esc_html__( 'File Size:', 'swift-csv' ),
						'postTypeInfo'            => esc_html__( 'Post Type:', 'swift-csv' ),
						'updateExistingInfo'      => esc_html__( 'Update Existing:', 'swift-csv' ),
						'processingChunk'         => esc_html__( 'Chunk processing start position (row):', 'swift-csv' ),
						'processedInfo'           => esc_html__( 'Processed', 'swift-csv' ),
						'rowsLabel'               => esc_html__( 'rows', 'swift-csv' ),
						'createdInfo'             => esc_html__( 'Created:', 'swift-csv' ),
						'updatedInfo'             => esc_html__( 'Updated:', 'swift-csv' ),
						'errorsInfo'              => esc_html__( 'Errors:', 'swift-csv' ),
						'syncProgress'            => esc_html__( 'Sync Progress', 'swift-csv' ),
						'importCancelledByUser'   => esc_html__( 'Import cancelled by user', 'swift-csv' ),
						'importError'             => esc_html__( 'Import error:', 'swift-csv' ),
						'dryRunNotice'            => esc_html__( 'Test import without creating posts or modifying data.', 'swift-csv' ),
						'dryRunCompleted'         => esc_html__( 'Test completed!', 'swift-csv' ),
						'dryRunCreated'           => esc_html__( 'Created posts:', 'swift-csv' ),
						'dryRunUpdated'           => esc_html__( 'Updated posts:', 'swift-csv' ),
						'dryRunErrors'            => esc_html__( 'Errors:', 'swift-csv' ),
						'dryRunPrefix'            => esc_html__( 'Dry Run', 'swift-csv' ),
						'importPrefix'            => esc_html__( 'Import', 'swift-csv' ),
						'createAction'            => esc_html__( 'New', 'swift-csv' ),
						'updateAction'            => esc_html__( 'Update', 'swift-csv' ),
						'errorAction'             => esc_html__( 'Error', 'swift-csv' ),
						'noFileSelected'          => esc_html__( 'No file selected', 'swift-csv' ),
						'removeFile'              => esc_html__( 'Remove', 'swift-csv' ),
						'exportScopeBasic'        => esc_html__( 'Basic Fields', 'swift-csv' ),
						'exportScopeAll'          => esc_html__( 'All Fields', 'swift-csv' ),
						'startingExport'          => esc_html__( 'Starting export process...', 'swift-csv' ),
						'postTypeExport'          => esc_html__( 'Post Type:', 'swift-csv' ),
						'exportContent'           => esc_html__( 'Export Content:', 'swift-csv' ),
						'includePrivateMeta'      => esc_html__( 'Include Private Meta:', 'swift-csv' ),
						'exportLimit'             => esc_html__( 'Export Limit:', 'swift-csv' ),
						'exportCancelledByUser'   => esc_html__( 'Export cancelled by user', 'swift-csv' ),
						'processedExport'         => esc_html__( 'Processed', 'swift-csv' ),
						'exportError'             => esc_html__( 'Export error:', 'swift-csv' ),
						'exportCompleted'         => esc_html__( 'Export completed successfully!', 'swift-csv' ),
						'downloadReady'           => esc_html__( 'Download ready:', 'swift-csv' ),
						'batchExportStarted'      => esc_html__( 'Batch export started', 'swift-csv' ),
						'exportAction'            => esc_html__( 'Exported', 'swift-csv' ),
						'exportPrefix'            => esc_html__( 'Export', 'swift-csv' ),
						'startingDirectSqlExport' => esc_html__( 'Starting export process (High-Speed)...', 'swift-csv' ),
						'secondsLabel'            => esc_html__( 's', 'swift-csv' ),
						'rowLabel'                => esc_html__( 'Row', 'swift-csv' ),
						'yes'                     => esc_html__( 'Yes', 'swift-csv' ),
						'no'                      => esc_html__( 'No', 'swift-csv' ),
						'noLimit'                 => esc_html__( 'No limit', 'swift-csv' ),
						'errorOccurred'           => esc_html__( 'An error occurred. Please try again.', 'swift-csv' ),
						'totalImported'           => esc_html__( 'Imported', 'swift-csv' ),
						'totalErrors'             => esc_html__( 'Errors', 'swift-csv' ),
						'show'                    => esc_html__( 'Show', 'swift-csv' ),
						'hide'                    => esc_html__( 'Hide', 'swift-csv' ),
					],
				]
			);

			wp_localize_script(
				'swift-csv-main',
				'swiftCsvAjax',
				[
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'swift_csv_ajax_nonce' ),
				]
			);

			wp_enqueue_script( 'swift-csv-core' );
			wp_enqueue_script( 'swift-csv-export-unified-module-ajax' );
			wp_enqueue_script( 'swift-csv-export-unified-module-download' );
			wp_enqueue_script( 'swift-csv-export-unified-module-form' );
			wp_enqueue_script( 'swift-csv-export-unified-module-ui' );
			wp_enqueue_script( 'swift-csv-export-unified-module-logs' );
			wp_enqueue_script( 'swift-csv-export-unified' );
			wp_enqueue_script( 'swift-csv-export-original' );
			wp_enqueue_script( 'swift-csv-import' );
			wp_enqueue_script( 'swift-csv-license' );
			wp_enqueue_script( 'swift-csv-main' );
		}
	}
}
