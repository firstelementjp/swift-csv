<?php
/**
 * Settings helper for Swift CSV Free version
 *
 * Centralizes settings storage in a single option array.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings helper for Swift CSV Free version
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Settings_Helper {

	/**
	 * Option name for centralized settings storage
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'swift_csv_settings';

	/**
	 * Default settings structure
	 *
	 * @var array
	 */
	private const DEFAULTS = [
		'advanced' => [
			'enable_logs'               => true,
			'uninstall_remove_all_data' => true,
		],
	];

	/**
	 * Get all settings
	 *
	 * @return array
	 */
	public static function get_all() {
		$saved = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		$has_advanced_enable_logs = isset( $saved['advanced'] ) && is_array( $saved['advanced'] )
			&& array_key_exists( 'enable_logs', $saved['advanced'] );

		if ( ! $has_advanced_enable_logs ) {
			$legacy_export_logs = isset( $saved['export'] ) && is_array( $saved['export'] )
				&& array_key_exists( 'enable_logs', $saved['export'] )
				? (bool) $saved['export']['enable_logs']
				: null;
			$legacy_import_logs = isset( $saved['import'] ) && is_array( $saved['import'] )
				&& array_key_exists( 'enable_logs', $saved['import'] )
				? (bool) $saved['import']['enable_logs']
				: null;

			if ( null !== $legacy_export_logs || null !== $legacy_import_logs ) {
				if ( ! isset( $saved['advanced'] ) || ! is_array( $saved['advanced'] ) ) {
					$saved['advanced'] = [];
				}

				$saved['advanced']['enable_logs'] = (bool) $legacy_export_logs || (bool) $legacy_import_logs;
			}
		}

		return array_replace_recursive( self::DEFAULTS, $saved );
	}

	/**
	 * Get specific setting section
	 *
	 * @param string $section Section key (export/import/advanced).
	 * @return array
	 */
	public static function get_section( $section ) {
		$all = self::get_all();
		return $all[ $section ] ?? self::DEFAULTS[ $section ] ?? [];
	}

	/**
	 * Get specific setting value
	 *
	 * @param string $section  Section key.
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Default value.
	 * @return mixed
	 */
	public static function get( $section, $key, $fallback = null ) {
		$section_data = self::get_section( $section );
		return $section_data[ $key ] ?? $fallback;
	}

	/**
	 * Update specific setting section
	 *
	 * @param string $section Section key.
	 * @param array  $data    New data.
	 * @return bool
	 */
	public static function update_section( $section, $data ) {
		$all          = self::get_all();
		$next_section = array_replace_recursive( $all[ $section ] ?? [], $data );

		if ( ( $all[ $section ] ?? [] ) === $next_section ) {
			return true;
		}

		$all[ $section ] = $next_section;

		return update_option( self::OPTION_NAME, $all );
	}

	/**
	 * Update specific setting value
	 *
	 * @param string $section Section key.
	 * @param string $key     Setting key.
	 * @param mixed  $value   New value.
	 * @return bool
	 */
	public static function update( $section, $key, $value ) {
		return self::update_section( $section, [ $key => $value ] );
	}

	/**
	 * Migrate legacy individual options to centralized format
	 *
	 * @return void
	 */
	public static function migrate_legacy_options() {
		// Only migrate if new option is empty.
		if ( false !== get_option( self::OPTION_NAME ) ) {
			return;
		}

		$migrated           = [];
		$legacy_enable_logs = null;

		// Export logs setting.
		$legacy_export_enable_logs = get_option( 'swift_csv_export_enable_logs' );
		if ( false !== $legacy_export_enable_logs ) {
			$legacy_enable_logs = (bool) $legacy_export_enable_logs;
			delete_option( 'swift_csv_export_enable_logs' );
		}

		// Import logs setting.
		$legacy_import_enable_logs = get_option( 'swift_csv_import_enable_logs' );
		if ( false !== $legacy_import_enable_logs ) {
			$legacy_enable_logs = ( null === $legacy_enable_logs )
				? (bool) $legacy_import_enable_logs
				: ( (bool) $legacy_enable_logs || (bool) $legacy_import_enable_logs );
			delete_option( 'swift_csv_import_enable_logs' );
		}

		// Advanced settings.
		if ( null !== $legacy_enable_logs ) {
			$migrated['advanced']['enable_logs'] = $legacy_enable_logs;
		}

		// Save migrated data if we have any.
		if ( ! empty( $migrated ) ) {
			update_option( self::OPTION_NAME, $migrated );
		}
	}
}
