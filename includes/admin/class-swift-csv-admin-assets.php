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
			$debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;
			$suffix     = $debug_mode ? '' : '.min';

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
