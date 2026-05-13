=== FE CSV Import & Export ===
Contributors: dxd5001, firstelement
Tags: csv, import, export, custom post types, custom fields
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.9.9.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

FE CSV Import & Export is a powerful CSV import/export plugin for WordPress. Supports posts, custom post types, taxonomies, custom fields with batch processing.

== Description ==

FE CSV Import & Export helps you import and export WordPress content as CSV from the admin area.
It is designed for site owners who want a stress-free CSV workflow for daily operations and for developers who need extensibility through WordPress hooks.

Main features:

* Import and export WordPress content as CSV
* Fast export of approximately 10,000 posts in about 15 seconds (depending on site configuration and server environment)
* Support for posts, pages, and custom post types
* Option to include or exclude custom taxonomies
* Support for term ID or term name input/output
* Proper handling of hierarchical terms
* Import of unregistered terms (currently uses term name as slug)
* Option to include or exclude custom fields
* Option to include or exclude private meta
* New custom field items can be registered with the "cf_" prefix
* Option to target published status or all post statuses
* Option to export only frequently edited "basic fields" or "all fields"
* Option to update (overwrite) existing posts with the same ID or create new ones
* Dry run functionality
* Batch processing for large CSV files
* Real-time progress display in the admin interface
* Option to enable/disable logging (speed priority)
* Extensibility through filter/action hooks for custom workflows
* WordPress-compatible mode using WP_Query class

FE CSV Import & Export is suitable for tasks such as:

* Bulk updating existing posts from spreadsheet data
* Exporting content for reporting or external processing
* Importing structured content into custom post types
* Migrating content between sites

For large imports, this plugin processes data in batches to reduce memory usage and improve reliability. It automatically adjusts batch size based on server settings (max_execution_time, memory_limit) to efficiently process large datasets without timeout.

Using filter hooks, you can output only necessary columns for external editing (e.g., title and content only) or add custom processing when exporting ACF (Advanced Custom Fields) related custom fields.

== Pro Features ==

FE CSV Import & Export Pro extends the free version with advanced features for power users and developers:

* **Direct SQL Export Mode** - Customize export queries directly for advanced workflows and integrations
* **UpdraftPlus Database Backup** - Automatic database backup before import operations for data safety
* **Enhanced Security** - Access control for the tools page and execution permissions
* **Dedicated Support** - Priority customer support from the development team
* **Automatic Updates** - Seamless updates from our update server

Pro features are available through the separate FE CSV Import & Export Pro plugin, which integrates seamlessly with the free version via WordPress hooks.

Note:

For custom field output, by default, the plugin picks up fields associated with the first post (first row). If the first post does not contain all necessary custom fields, those fields will be ignored. To avoid this, consider these two approaches:

Pattern A)
Create a dummy post immediately before export and fill it with dummy values for all necessary custom field items (this ensures all necessary custom field items are expanded as CSV columns)

Pattern B)
Use a filter hook to specify a sample post ID for extracting custom field items (by specifying a post ID that contains all custom field items, you ensure all necessary custom field items are expanded as CSV columns)

Documentation and support resources:

* Documentation: https://firstelementjp.github.io/swift-csv/
* GitHub Repository: https://github.com/firstelementjp/swift-csv
* Issue Tracker: https://github.com/firstelementjp/swift-csv/issues

Pro Version:

This plugin works completely on its own with the free version and provides sufficient functionality, but when used with the Pro version, you can access these convenient features:

* ACF (Advanced Custom Fields) checkbox, select box, and other custom field items can be expanded to editable values for export. ACF data can also be edited/updated in spreadsheets.
* When used with the backup plugin 'Updraft Plus', automatic SQL backup is executed during import and import begins after completion. If problems are discovered after import, you can immediately restore from backup.
* Import/export execution permissions can be assigned to editors in addition to administrators.
* You can set a shared password for import/export execution.
* You can require users to enter their login password for import/export execution.

Pro Version: https://www.firstelement.co.jp/en/products/swift-csv-import-export-plugin/

== External Services ==

FE CSV Import & Export Pro uses an external license API service for license validation and activation. This service is used solely for:

* License key validation
* License activation and deactivation
* License status checks

**Privacy Policy**: https://www.firstelement.co.jp/privacy/
**Terms of Service**: https://www.firstelement.co.jp/terms/

The license API service is operated by FirstElement Co., Ltd. and is used exclusively for license management purposes. No content data is transmitted to this service.

== Installation ==

= From WordPress Admin =

1. Search for the plugin from the WordPress admin plugin add screen, or upload the plugin ZIP file.
2. Activate the plugin.
3. Open the FE CSV Import & Export screen from the WordPress admin area.
4. Run a small import or export test first.

= Manual Installation =

1. Upload the `swift-csv` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the WordPress Plugins screen.
3. Open the FE CSV Import & Export screen from the admin area.

== Frequently Asked Questions ==

= What content can I import or export? =

This plugin supports standard WordPress posts and can also work with custom post types, taxonomies, and custom fields.

= Can I use this plugin for large CSV files? =

Yes. This plugin uses batch processing to improve reliability and reduce memory usage during large imports and exports.

= Does it support updating existing posts? =

Yes. CSV workflows can be used for both creating new content and updating existing content depending on your data and mapping.

= Is this plugin developer-friendly? =

Yes. This plugin provides WordPress hooks so developers can customize import/export behavior.

= Is the Pro version required? =

No. The free version works on its own. A Pro version is available separately for advanced use cases.

== Screenshots ==

1. Export interface with post type and field selection
2. Import interface with CSV upload and configuration options
3. Additional settings for import/export configuration
4. License management and Pro version features

== Changelog ==

= 0.9.9.3 =

* Removed load_plugin_textdomain() as WordPress 4.6+ handles text domain loading automatically.
* Renamed image files to remove special characters for WordPress.org compliance.
* Updated image references in PHP and CSS files.
* Added README.md to .gitattributes export-ignore for release ZIP.
* Refactored uninstall.php to use WordPress API instead of direct database calls.
* Removed hidden files (.DS_Store, .eslintignore, .prettierignore) from distribution.

= 0.9.9.2 =

* Maintenance release for the latest public package.

= 0.9.9 =

* Reduced memory usage for large CSV imports by streaming batch reads and reusing file offsets.
* Reduced duplicate CSV parsing within the same import batch.
* Improved import profiling and diagnostics for debugging large imports.
* Updated CSV quote parsing behavior for better RFC 4180 compatibility.
* Improved import log stability and cleanup behavior in the admin UI.

= 0.9.8 =

* Improved architecture for import handling.
* Enhanced taxonomy handling and utilities.
* Updated documentation and development workflow.
* Improved WordPress 6.6+ and PHP 8.1+ compatibility.

== Upgrade Notice ==

= 0.9.9.2 =

Recommended maintenance update for current users.
