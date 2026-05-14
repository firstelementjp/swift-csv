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
class FE_CSV_Import_Export_Admin_Assets {

	/**
	 * Enqueue admin styles
	 *
	 * @since  0.9.8
	 * @param  string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_styles( $hook ) {
		if ( 'tools_page_fe-csv-import-export' === $hook ) {
			$debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;
			$suffix     = $debug_mode ? '' : '.min';
			$css_path   = 'assets/css/fe-csv-import-export-style' . $suffix . '.css';
			$css_fs     = FE_CSV_IMPORT_EXPORT_PLUGIN_DIR . ltrim( $css_path, '/' );

			if ( file_exists( $css_fs ) ) {
				// Preferred file exists.
				$css_url = FE_CSV_IMPORT_EXPORT_PLUGIN_URL . ltrim( $css_path, '/' );
			} else {
				// Fallback to opposite format.
				if ( '' === $suffix ) {
					// Debug mode: prefer unminified, fallback to minified.
					$css_path = 'assets/css/fe-csv-import-export-style.min.css';
				} else {
					// Production mode: prefer minified, fallback to unminified.
					$css_path = 'assets/css/fe-csv-import-export-style.css';
				}
				$css_url = FE_CSV_IMPORT_EXPORT_PLUGIN_URL . ltrim( $css_path, '/' );
			}

			wp_enqueue_style(
				'fe-csv-import-export-admin',
				$css_url,
				[],
				FE_CSV_IMPORT_EXPORT_VERSION
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
		if ( 'tools_page_fe-csv-import-export' === $hook ) {
			$debug_mode           = defined( 'WP_DEBUG' ) && WP_DEBUG;
			$suffix               = $debug_mode ? '' : '.min';
			$advanced_enable_logs = class_exists( 'FE_CSV_Import_Export_Settings_Helper' )
				? (bool) FE_CSV_Import_Export_Settings_Helper::get( 'advanced', 'enable_logs', true )
				: true;

			$script_url = static function ( $relative_path ) use ( $suffix ) {
				$min_path      = preg_replace( '/\.js$/', $suffix . '.js', $relative_path );
				$preferred_fs  = FE_CSV_IMPORT_EXPORT_PLUGIN_DIR . ltrim( $min_path, '/' );
				$fallback_path = preg_replace( '/\.js$/', '.min.js', $relative_path );
				$fallback_fs   = FE_CSV_IMPORT_EXPORT_PLUGIN_DIR . ltrim( $fallback_path, '/' );

				if ( file_exists( $preferred_fs ) ) {
					return FE_CSV_IMPORT_EXPORT_PLUGIN_URL . ltrim( $min_path, '/' );
				}

				// Fallback: if preferred file doesn't exist, try the opposite format.
				if ( '' === $suffix ) {
					// Debug mode: prefer unminified, fallback to minified.
					if ( file_exists( $fallback_fs ) ) {
						return FE_CSV_IMPORT_EXPORT_PLUGIN_URL . ltrim( $fallback_path, '/' );
					}
				} else {
					// Production mode: prefer minified, fallback to unminified.
					$unmin_path = preg_replace( '/\.js$/', '.js', $relative_path );
					$unmin_fs   = FE_CSV_IMPORT_EXPORT_PLUGIN_DIR . ltrim( $unmin_path, '/' );
					if ( file_exists( $unmin_fs ) ) {
						return FE_CSV_IMPORT_EXPORT_PLUGIN_URL . ltrim( $unmin_path, '/' );
					}
				}

				// Final fallback: return original path (may result in 404).
				return FE_CSV_IMPORT_EXPORT_PLUGIN_URL . ltrim( $relative_path, '/' );
			};

			// Core utilities (must be loaded first).
			wp_register_script(
				'fe-csv-import-export-core',
				$script_url( 'assets/js/fe-csv-import-export-core.js' ),
				[ 'wp-i18n' ],
				FE_CSV_IMPORT_EXPORT_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'fe-csv-import-export-export-unified-module-ajax',
				$script_url( 'assets/js/export/fe-csv-import-export/ajax.js' ),
				[ 'fe-csv-import-export-core' ],
				FE_CSV_IMPORT_EXPORT_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'fe-csv-import-export-export-unified-module-download',
				$script_url( 'assets/js/export/fe-csv-import-export/download.js' ),
				[ 'fe-csv-import-export-core' ],
				FE_CSV_IMPORT_EXPORT_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'fe-csv-import-export-export-unified-module-form',
				$script_url( 'assets/js/export/fe-csv-import-export/form.js' ),
				[ 'fe-csv-import-export-core' ],
				FE_CSV_IMPORT_EXPORT_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'fe-csv-import-export-export-unified-module-ui',
				$script_url( 'assets/js/export/fe-csv-import-export/ui.js' ),
				[ 'fe-csv-import-export-core' ],
				FE_CSV_IMPORT_EXPORT_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'fe-csv-import-export-export-unified-module-logs',
				$script_url( 'assets/js/export/fe-csv-import-export/logs.js' ),
				[ 'fe-csv-import-export-core', 'fe-csv-import-export-export-unified-module-ajax' ],
				FE_CSV_IMPORT_EXPORT_VERSION . '.' . time(),
				true
			);

			wp_register_script(
				'fe-csv-import-export-export-original',
				$script_url( 'assets/js/export/fe-csv-import-export/original.js' ),
				[ 'fe-csv-import-export-core', 'fe-csv-import-export-export-unified' ],
				FE_CSV_IMPORT_EXPORT_VERSION . '.' . time(),
				true
			);

			// Export functionality.
			wp_register_script(
				'fe-csv-import-export-export-unified',
				$script_url( 'assets/js/fe-csv-import-export-export-unified.js' ),
				[
					'fe-csv-import-export-core',
					'fe-csv-import-export-export-unified-module-ajax',
					'fe-csv-import-export-export-unified-module-download',
					'fe-csv-import-export-export-unified-module-form',
					'fe-csv-import-export-export-unified-module-ui',
					'fe-csv-import-export-export-unified-module-logs',
				],
				FE_CSV_IMPORT_EXPORT_VERSION . '.' . time(),
				true
			);

			// Import functionality.
			wp_register_script(
				'fe-csv-import-export-import',
				$script_url( 'assets/js/fe-csv-import-export-import.js' ),
				[ 'fe-csv-import-export-core' ],
				FE_CSV_IMPORT_EXPORT_VERSION . '.' . time(),
				true
			);

			// License functionality.
			wp_register_script(
				'fe-csv-import-export-license',
				$script_url( 'assets/js/fe-csv-import-export-license.js' ),
				[ 'fe-csv-import-export-core' ],
				FE_CSV_IMPORT_EXPORT_VERSION . '.' . time(),
				true
			);

			// Main entry point (must be loaded last).
			wp_register_script(
				'fe-csv-import-export-main',
				$script_url( 'assets/js/fe-csv-import-export-main.js' ),
				[
					'fe-csv-import-export-core',
					'fe-csv-import-export-export-unified',
					'fe-csv-import-export-export-original',
					'fe-csv-import-export-import',
					'fe-csv-import-export-license',
				],
				FE_CSV_IMPORT_EXPORT_VERSION . '.' . time(),
				true
			);

			wp_localize_script(
				'fe-csv-import-export-core',
				'swiftCSV',
				[
					'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
					'ajaxurl'               => admin_url( 'admin-ajax.php' ),
					'nonce'                 => wp_create_nonce( 'fe_csv_import_export_ajax_nonce' ),
					'debug'                 => $debug_mode,
					'advancedSettings'      => [
						'enableLogs' => $advanced_enable_logs,
					],
					'hasProAdmin'           => class_exists( 'FE_CSV_Import_Export_Pro_Admin' ),
					'enableDirectSqlImport' => (bool) apply_filters( 'fe_csv_import_export_enable_direct_sql_import', false ),
					'maxLogEntries'         => apply_filters( 'fe_csv_import_export_max_log_entries', 30 ),
					'exportCompleteText'    => esc_html__( 'Export Complete', 'fe-csv-import-export' ),
					'exportFailedText'      => esc_html__( 'Export failed: ', 'fe-csv-import-export' ),
					'csvContentNotFound'    => esc_html__( 'CSV content not found in response', 'fe-csv-import-export' ),
					'unknownError'          => esc_html__( 'Unknown error', 'fe-csv-import-export' ),
					'highSpeedImportText'   => esc_html__( 'High-Speed Import', 'fe-csv-import-export' ),
					'standardImportText'    => esc_html__( 'Import', 'fe-csv-import-export' ),
					'importCompleteText'    => esc_html__( 'Import Complete', 'fe-csv-import-export' ),
					'importFailedText'      => esc_html__( 'Import Failed', 'fe-csv-import-export' ),
					'messages'              => [
						'exportCsv'               => esc_html__( 'Export CSV', 'fe-csv-import-export' ),
						'startExport'             => esc_html__( 'Start Export', 'fe-csv-import-export' ),
						'importCsv'               => esc_html__( 'Import CSV', 'fe-csv-import-export' ),
						'startImport'             => esc_html__( 'Start Import', 'fe-csv-import-export' ),
						'exporting'               => esc_html__( 'Exporting...', 'fe-csv-import-export' ),
						'importing'               => esc_html__( 'Importing...', 'fe-csv-import-export' ),
						'readyToExport'           => esc_html__( 'Ready to start export...', 'fe-csv-import-export' ),
						'readyToImport'           => esc_html__( 'Ready to start import...', 'fe-csv-import-export' ),
						'importComplete'          => esc_html__( 'Import Complete!', 'fe-csv-import-export' ),
						'importCompleted'         => esc_html__( 'Import completed successfully!', 'fe-csv-import-export' ),
						'error'                   => esc_html__( 'An error occurred. Please try again.', 'fe-csv-import-export' ),
						'success'                 => esc_html__( 'Operation completed successfully!', 'fe-csv-import-export' ),
						'cancelled'               => esc_html__( 'Export cancelled', 'fe-csv-import-export' ),
						'failed'                  => esc_html__( 'Export failed', 'fe-csv-import-export' ),
						'importSettings'          => esc_html__( 'Import Settings', 'fe-csv-import-export' ),
						'dropFileHere'            => esc_html__( 'Drop CSV file here or click to browse', 'fe-csv-import-export' ),
						// translators: %s: Maximum file size (e.g., 10MB).
						'maxFileSize'             => esc_html__( 'Maximum file size: %s', 'fe-csv-import-export' ),
						'custom'                  => esc_html__( 'Custom', 'fe-csv-import-export' ),
						// translators: %1$s: Hook name, %2$s: Documentation link text.
						'customHelp'              => esc_html__( 'Use the %1$s hook to specify custom export items and order. See %2$s for details.', 'fe-csv-import-export' ),
						'documentation'           => esc_html__( 'documentation', 'fe-csv-import-export' ),
						'startingImport'          => esc_html__( 'Starting import process...', 'fe-csv-import-export' ),
						'fileInfo'                => esc_html__( 'File:', 'fe-csv-import-export' ),
						'fileSize'                => esc_html__( 'File Size:', 'fe-csv-import-export' ),
						'postTypeInfo'            => esc_html__( 'Post Type:', 'fe-csv-import-export' ),
						'updateExistingInfo'      => esc_html__( 'Update Existing:', 'fe-csv-import-export' ),
						'processingChunk'         => esc_html__( 'Chunk processing start position (row):', 'fe-csv-import-export' ),
						'processedInfo'           => esc_html__( 'Processed', 'fe-csv-import-export' ),
						'rowsLabel'               => esc_html__( 'rows', 'fe-csv-import-export' ),
						'createdInfo'             => esc_html__( 'Created:', 'fe-csv-import-export' ),
						'updatedInfo'             => esc_html__( 'Updated:', 'fe-csv-import-export' ),
						'errorsInfo'              => esc_html__( 'Errors:', 'fe-csv-import-export' ),
						'syncProgress'            => esc_html__( 'Sync Progress', 'fe-csv-import-export' ),
						'importCancelledByUser'   => esc_html__( 'Import cancelled by user', 'fe-csv-import-export' ),
						'importError'             => esc_html__( 'Import error:', 'fe-csv-import-export' ),
						'dryRunNotice'            => esc_html__( 'Test import without creating posts or modifying data.', 'fe-csv-import-export' ),
						'dryRunCompleted'         => esc_html__( 'Test completed!', 'fe-csv-import-export' ),
						'dryRunCreated'           => esc_html__( 'Created posts:', 'fe-csv-import-export' ),
						'dryRunUpdated'           => esc_html__( 'Updated posts:', 'fe-csv-import-export' ),
						'dryRunErrors'            => esc_html__( 'Errors:', 'fe-csv-import-export' ),
						'dryRunPrefix'            => esc_html__( 'Dry Run', 'fe-csv-import-export' ),
						'importPrefix'            => esc_html__( 'Import', 'fe-csv-import-export' ),
						'createAction'            => esc_html__( 'New', 'fe-csv-import-export' ),
						'updateAction'            => esc_html__( 'Update', 'fe-csv-import-export' ),
						'errorAction'             => esc_html__( 'Error', 'fe-csv-import-export' ),
						'noFileSelected'          => esc_html__( 'No file selected', 'fe-csv-import-export' ),
						'removeFile'              => esc_html__( 'Remove', 'fe-csv-import-export' ),
						'exportScopeBasic'        => esc_html__( 'Basic Fields', 'fe-csv-import-export' ),
						'exportScopeAll'          => esc_html__( 'All Fields', 'fe-csv-import-export' ),
						'startingExport'          => esc_html__( 'Starting export process...', 'fe-csv-import-export' ),
						'postTypeExport'          => esc_html__( 'Post Type:', 'fe-csv-import-export' ),
						'exportContent'           => esc_html__( 'Export Content:', 'fe-csv-import-export' ),
						'includePrivateMeta'      => esc_html__( 'Include Private Meta:', 'fe-csv-import-export' ),
						'exportLimit'             => esc_html__( 'Export Limit:', 'fe-csv-import-export' ),
						'exportCancelledByUser'   => esc_html__( 'Export cancelled by user', 'fe-csv-import-export' ),
						'processedExport'         => esc_html__( 'Processed', 'fe-csv-import-export' ),
						'exportError'             => esc_html__( 'Export error:', 'fe-csv-import-export' ),
						'exportCompleted'         => esc_html__( 'Export completed successfully!', 'fe-csv-import-export' ),
						'downloadReady'           => esc_html__( 'Download ready:', 'fe-csv-import-export' ),
						'batchExportStarted'      => esc_html__( 'Batch export started', 'fe-csv-import-export' ),
						'exportAction'            => esc_html__( 'Exported', 'fe-csv-import-export' ),
						'exportPrefix'            => esc_html__( 'Export', 'fe-csv-import-export' ),
						'startingDirectSqlExport' => esc_html__( 'Starting export process (SQL)...', 'fe-csv-import-export' ),
						'secondsLabel'            => esc_html__( 's', 'fe-csv-import-export' ),
						'rowLabel'                => esc_html__( 'Row', 'fe-csv-import-export' ),
						'yes'                     => esc_html__( 'Yes', 'fe-csv-import-export' ),
						'no'                      => esc_html__( 'No', 'fe-csv-import-export' ),
						'noLimit'                 => esc_html__( 'No limit', 'fe-csv-import-export' ),
						'errorOccurred'           => esc_html__( 'An error occurred. Please try again.', 'fe-csv-import-export' ),
						'totalImported'           => esc_html__( 'Imported', 'fe-csv-import-export' ),
						'totalErrors'             => esc_html__( 'Errors', 'fe-csv-import-export' ),
						'show'                    => esc_html__( 'Show', 'fe-csv-import-export' ),
						'hide'                    => esc_html__( 'Hide', 'fe-csv-import-export' ),
					],
				]
			);

			wp_localize_script(
				'fe-csv-import-export-main',
				'swiftCsvAjax',
				[
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'fe_csv_import_export_ajax_nonce' ),
				]
			);

			wp_enqueue_script( 'fe-csv-import-export-core' );
			wp_enqueue_script( 'fe-csv-import-export-export-unified-module-ajax' );
			wp_enqueue_script( 'fe-csv-import-export-export-unified-module-download' );
			wp_enqueue_script( 'fe-csv-import-export-export-unified-module-form' );
			wp_enqueue_script( 'fe-csv-import-export-export-unified-module-ui' );
			wp_enqueue_script( 'fe-csv-import-export-export-unified-module-logs' );
			wp_enqueue_script( 'fe-csv-import-export-export-unified' );
			wp_enqueue_script( 'fe-csv-import-export-export-original' );
			wp_enqueue_script( 'fe-csv-import-export-import' );
			wp_enqueue_script( 'fe-csv-import-export-license' );
			wp_enqueue_script( 'fe-csv-import-export-main' );
		}
	}
}
