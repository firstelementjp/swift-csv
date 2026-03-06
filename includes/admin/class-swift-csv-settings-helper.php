<?php
/**
 * Settings helper for Swift CSV Free version
 *
 * Centralizes settings storage in a single option array.
 *
 * @since 0.9.15
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		'export' => [
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'scope'                  => 'basic',
			'include_taxonomies'     => true,
			'include_custom_fields'  => true,
			'include_private_meta'   => false,
			'taxonomy_format'        => 'name',
			'limit'                  => 1000,
			'enable_logs'            => true,
		],
		'import' => [
			'post_type'        => 'post',
			'update_existing'  => false,
			'taxonomy_format'  => 'name',
			'enable_logs'      => true,
			'dry_run'          => false,
		],
		'advanced' => [
			'updraft_backup_before_import' => false,
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
		return array_replace_recursive( self::DEFAULTS, $saved );
	}

	/**
	 * Get specific setting section
	 *
	 * @param string $section Section key (export/import/advanced)
	 * @return array
	 */
	public static function get_section( $section ) {
		$all = self::get_all();
		return $all[ $section ] ?? self::DEFAULTS[ $section ] ?? [];
	}

	/**
	 * Get specific setting value
	 *
	 * @param string $section Section key
	 * @param string $key Setting key
	 * @param mixed $default Default value
	 * @return mixed
	 */
	public static function get( $section, $key, $default = null ) {
		$section_data = self::get_section( $section );
		return $section_data[ $key ] ?? $default;
	}

	/**
	 * Update specific setting section
	 *
	 * @param string $section Section key
	 * @param array $data New data
	 * @return bool
	 */
	public static function update_section( $section, $data ) {
		$all = self::get_all();
		$all[ $section ] = array_replace_recursive( $all[ $section ] ?? [], $data );
		return update_option( self::OPTION_NAME, $all );
	}

	/**
	 * Update specific setting value
	 *
	 * @param string $section Section key
	 * @param string $key Setting key
	 * @param mixed $value New value
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
		// Only migrate if new option is empty
		if ( get_option( self::OPTION_NAME ) !== false ) {
			return;
		}

		$migrated = [];

		// Export settings
		$export_keys = [
			'swift_csv_export_post_type'            => 'post_type',
			'swift_csv_export_post_status'          => 'post_status',
			'swift_csv_export_scope'                 => 'scope',
			'swift_csv_include_taxonomies'           => 'include_taxonomies',
			'swift_csv_include_custom_fields'       => 'include_custom_fields',
			'swift_csv_include_private_meta'         => 'include_private_meta',
			'swift_csv_taxonomy_format'              => 'taxonomy_format',
			'swift_csv_export_limit'                 => 'limit',
			'swift_csv_export_enable_logs'           => 'enable_logs',
		];

		foreach ( $export_keys as $old_key => $new_key ) {
			$value = get_option( $old_key );
			if ( $value !== false ) {
				$migrated['export'][ $new_key ] = $value;
				delete_option( $old_key ); // Clean up old option
			}
		}

		// Import settings
		$import_keys = [
			'swift_csv_import_post_type'        => 'post_type',
			'swift_csv_import_update_existing'  => 'update_existing',
			'swift_csv_import_taxonomy_format'  => 'taxonomy_format',
			'swift_csv_import_enable_logs'      => 'enable_logs',
			'swift_csv_import_dry_run'          => 'dry_run',
		];

		foreach ( $import_keys as $old_key => $new_key ) {
			$value = get_option( $old_key );
			if ( $value !== false ) {
				$migrated['import'][ $new_key ] = $value;
				delete_option( $old_key ); // Clean up old option
			}
		}

		// Advanced settings
		$updraft_value = get_option( 'swift_csv_import_updraft_backup_before_import' );
		if ( $updraft_value !== false ) {
			$migrated['advanced']['updraft_backup_before_import'] = $updraft_value;
			delete_option( 'swift_csv_import_updraft_backup_before_import' );
		}

		// Save migrated data if we have any
		if ( ! empty( $migrated ) ) {
			update_option( self::OPTION_NAME, $migrated );
		}
	}
}
