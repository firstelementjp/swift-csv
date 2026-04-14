<?php
/**
 * Plugin Updater for Swift CSV
 *
 * Handles automatic updates from GitHub releases with version checking
 * and package download functionality.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Updater class
 *
 * Handles automatic updates from GitHub releases with version checking
 * and package download functionality.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_Updater {

	/**
	 * GitHub repository information
	 *
	 * @since  0.9.0
	 * @var array
	 */
	private $repo_info = [
		'owner' => 'firstelementjp',
		'repo'  => 'swift-csv',
	];

	/**
	 * Plugin file path
	 *
	 * @since  0.9.0
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Constructor
	 *
	 * Sets up WordPress hooks for plugin updates.
	 *
	 * @since  0.9.0
	 * @param  string $plugin_file Plugin file path.
	 * @return void
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_updates' ] );
		add_filter( 'plugins_api', [ $this, 'plugins_api_info' ], 10, 3 );
		add_action( 'upgrader_process_complete', [ $this, 'after_update' ], 10, 2 );
		add_action( 'admin_init', [ $this, 'schedule_update_check' ] );
	}

	/**
	 * Schedule update check
	 *
	 * Sets up cron job for checking updates.
	 *
	 * @since  0.9.0
	 * @return void
	 */
	public function schedule_update_check() {
		if ( ! wp_next_scheduled( 'swift_csv_check_updates' ) ) {
			wp_schedule_event( time(), 'daily', 'swift_csv_check_updates' );
		}

		add_action( 'swift_csv_check_updates', [ $this, 'force_update_check' ] );
	}

	/**
	 * Force update check
	 *
	 * Forces WordPress to check for plugin updates.
	 *
	 * @since  0.9.0
	 * @return void
	 */
	public function force_update_check() {
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
	}

	/**
	 * Check for updates
	 *
	 * Compares current version with latest GitHub release.
	 *
	 * @since  0.9.0
	 * @param  object $transient Update transient.
	 * @return object Modified transient.
	 */
	public function check_for_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Get current plugin data.
		$plugin_data = get_plugin_data( $this->plugin_file );
		$plugin_slug = plugin_basename( $this->plugin_file );

		// Get latest release from GitHub.
		$latest_release = $this->get_latest_release();

		if ( ! $latest_release || ! isset( $latest_release->tag_name ) ) {
			return $transient;
		}

		// Compare versions.
		$current_version = $plugin_data['Version'];
		$latest_version  = ltrim( $latest_release->tag_name, 'v' );
		$package_url     = $this->get_release_package_url( $latest_release );

		if ( $package_url && version_compare( $latest_version, $current_version, '>' ) ) {
			// Update available.
			$transient->response[ $plugin_slug ] = (object) [
				'slug'          => dirname( $plugin_slug ),
				'new_version'   => $latest_version,
				'package'       => $package_url,
				'url'           => $latest_release->html_url,
				'plugin'        => $plugin_slug,
				'tested'        => get_bloginfo( 'version' ),
				'compatibility' => [
					'tested' => [
						'wp' => [
							'min' => '5.0',
							'max' => false,
						],
					],
				],
			];
		}

		return $transient;
	}

	/**
	 * Get latest release from GitHub
	 *
	 * Fetches the latest release information from GitHub API.
	 *
	 * @since  0.9.0
	 * @return object|false Release data or false on failure.
	 */
	private function get_latest_release() {
		$cache_key = 'swift_csv_latest_release';
		$cached    = get_site_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$url = "https://api.github.com/repos/{$this->repo_info['owner']}/{$this->repo_info['repo']}/releases/latest";

		$response = wp_remote_get(
			$url,
			[
				'headers' => [
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'Swift-CSV-Plugin/' . SWIFT_CSV_VERSION,
				],
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return false;
		}

		$body    = wp_remote_retrieve_body( $response );
		$release = json_decode( $body );

		if ( ! $release || ! isset( $release->tag_name ) ) {
			return false;
		}

		// Cache for 12 hours.
		set_site_transient( $cache_key, $release, 12 * HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * Plugins API info
	 *
	 * Provides plugin information for WordPress update screen.
	 *
	 * @since  0.9.0
	 * @param  bool|object $res     The result object.
	 * @param  string      $action   The type of information being requested.
	 * @param  object      $args     Plugin API arguments.
	 * @return bool|object Modified result.
	 */
	public function plugins_api_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $res;
		}

		$plugin_slug = plugin_basename( $this->plugin_file );

		if ( ! isset( $args->slug ) || dirname( $plugin_slug ) !== $args->slug ) {
			return $res;
		}

		$latest_release = $this->get_latest_release();

		if ( ! $latest_release ) {
			return $res;
		}

		$plugin_data = get_plugin_data( $this->plugin_file );
		$package_url = $this->get_release_package_url( $latest_release );

		$res = (object) [
			'name'              => $plugin_data['Name'],
			'slug'              => dirname( $plugin_slug ),
			'version'           => ltrim( $latest_release->tag_name, 'v' ),
			'author'            => $plugin_data['Author'],
			'author_profile'    => $plugin_data['AuthorURI'],
			'last_updated'      => $latest_release->published_at,
			'homepage'          => $plugin_data['PluginURI'],
			'short_description' => $plugin_data['Description'],
			'sections'          => [
				'description' => $plugin_data['Description'],
				'changelog'   => $this->format_changelog( $latest_release->body ),
			],
			'download_link'     => $package_url,
			'banners'           => [
				'high' => 'https://firstelementjp.github.io/swift-csv/assets/images/banner-772x250.png',
				'low'  => 'https://firstelementjp.github.io/swift-csv/assets/images/banner-772x250.png',
			],
			'icons'             => [
				'1x' => 'https://firstelementjp.github.io/swift-csv/assets/images/icon-128x128.png',
				'2x' => 'https://firstelementjp.github.io/swift-csv/assets/images/icon-256x256.png',
			],
		];

		return $res;
	}

	/**
	 * Get release package URL
	 *
	 * Resolves the GitHub release asset used for WordPress updates.
	 *
	 * @since  0.9.9
	 * @param  object $release GitHub release object.
	 * @return string Release package URL.
	 */
	private function get_release_package_url( $release ) {
		if ( isset( $release->assets ) && is_array( $release->assets ) ) {
			$expected_name = 'swift-csv-' . ltrim( $release->tag_name, 'v' ) . '.zip';

			foreach ( $release->assets as $asset ) {
				if ( ! is_object( $asset ) || ! isset( $asset->name, $asset->browser_download_url ) ) {
					continue;
				}

				if ( $expected_name === $asset->name && is_string( $asset->browser_download_url ) ) {
					return $asset->browser_download_url;
				}
			}

			foreach ( $release->assets as $asset ) {
				if ( ! is_object( $asset ) || ! isset( $asset->name, $asset->browser_download_url ) ) {
					continue;
				}

				if ( is_string( $asset->name ) && preg_match( '/^swift-csv-.*\.zip$/', $asset->name ) && is_string( $asset->browser_download_url ) ) {
					return $asset->browser_download_url;
				}
			}
		}

		return $this->get_release_package_fallback_url( $release );
	}

	/**
	 * Get release package fallback URL
	 *
	 * Builds the expected GitHub release asset URL when the API asset list is unavailable.
	 *
	 * @since  0.9.9
	 * @param  object $release GitHub release object.
	 * @return string Release package URL.
	 */
	private function get_release_package_fallback_url( $release ) {
		if ( ! isset( $release->tag_name ) || ! is_string( $release->tag_name ) ) {
			return '';
		}

		return 'https://github.com/' . $this->repo_info['owner'] . '/' . $this->repo_info['repo'] . '/releases/download/' . $release->tag_name . '/swift-csv-' . ltrim( $release->tag_name, 'v' ) . '.zip';
	}

	/**
	 * Format changelog
	 *
	 * Formats GitHub release body as changelog.
	 *
	 * @since  0.9.0
	 * @param  string $body Release body.
	 * @return string Formatted changelog.
	 */
	private function format_changelog( $body ) {
		// Convert markdown to basic HTML.
		$body = make_clickable( esc_html( $body ) );
		$body = nl2br( $body );

		// Convert markdown headers.
		$body = preg_replace( '/^##\s*(.+)$/m', '<h3>$1</h3>', $body );
		$body = preg_replace( '/^#\s*(.+)$/m', '<h2>$1</h2>', $body );

		// Convert markdown lists.
		$body = preg_replace( '/^\*\s+(.+)$/m', '<li>$1</li>', $body );
		$body = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $body );

		return $body;
	}

	/**
	 * After update hook
	 *
	 * Runs after plugin update is completed.
	 *
	 * @since  0.9.0
	 * @param  WP_Upgrader $upgrader Upgrader instance.
	 * @param  array       $options  Update options.
	 * @return void
	 */
	public function after_update( $upgrader, $options ) {
		if ( 'update' !== $options['action'] || 'plugin' !== $options['type'] ) {
			return;
		}

		$plugin_slug = plugin_basename( $this->plugin_file );

		if ( ! isset( $options['plugins'] ) || ! in_array( $plugin_slug, $options['plugins'], true ) ) {
			return;
		}

		// Clear cache and force update check.
		delete_site_transient( 'swift_csv_latest_release' );
		delete_site_transient( 'update_plugins' );

		// Log update.
		// Update successful - plugin is now at the latest version.
	}

	/**
	 * Get update status
	 *
	 * Returns current update status for admin display.
	 *
	 * @since  0.9.0
	 * @return array Update status information.
	 */
	public function get_update_status() {
		$plugin_data     = get_plugin_data( $this->plugin_file );
		$current_version = $plugin_data['Version'];

		$latest_release = $this->get_latest_release();

		if ( ! $latest_release ) {
			return [
				'status'  => 'unknown',
				'current' => $current_version,
				'latest'  => null,
				'message' => esc_html__( 'Failed to get update information.', 'swift-csv' ),
			];
		}

		$latest_version = ltrim( $latest_release->tag_name, 'v' );

		if ( version_compare( $latest_version, $current_version, '>' ) ) {
			return [
				'status'        => 'available',
				'current'       => $current_version,
				'latest'        => $latest_version,
				'message'       => sprintf(
					/* translators: %s: version number */
					esc_html__( 'New version %s is available.', 'swift-csv' ),
					$latest_version
				),
				'release_notes' => $latest_release->body,
				'download_url'  => $latest_release->html_url,
			];
		}

		return [
			'status'  => 'current',
			'current' => $current_version,
			'latest'  => $latest_version,
			'message' => esc_html__( 'Plugin is up to date.', 'swift-csv' ),
		];
	}
}
