# Swift CSV

![Swift CSV Banner](https://github.com/firstelementjp/swift-csv/blob/main/assets/images/swift-csv-banner.jpeg?raw=true)

[![License](https://img.shields.io/badge/license-GPL--2.0%2B-blue.svg)](LICENSE)
[![Version](https://img.shields.io/badge/version-0.9.7-green.svg)](https://github.com/firstelementjp/swift-csv/releases)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)

<!-- WordPress.org badges - Uncomment when plugin is accepted to official directory -->
<!-- [![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/swift-csv.svg?style=flat-square)](https://wordpress.org/plugins/swift-csv/) -->
<!-- [![WordPress Requires At Least](https://img.shields.io/wordpress/plugin/tested/swift-csv.svg?style=flat-square)](https://wordpress.org/plugins/swift-csv/) -->
<!-- [![WordPress Tested Up To](https://img.shields.io/wordpress/plugin/tested/swift-csv.svg?style=flat-square)](https://wordpress.org/plugins/swift-csv/) -->
<!-- [![WordPress Requires PHP](https://img.shields.io/wordpress/plugin/php-version/swift-csv.svg?style=flat-square)](https://wordpress.org/plugins/swift-csv/) -->

[![License](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Contributors](https://img.shields.io/badge/Contributors-firstelement%2C%20dxd5001-blue.svg)](https://github.com/firstelementjp/swift-csv/graphs/contributors)
[![Donate](https://img.shields.io/badge/Donate-PayPal-blue.svg)](https://paypal.me/fejp?country.x=JP&locale.x=ja_JP)

A lightweight and simple CSV import/export plugin for WordPress. Full support for custom post types, custom taxonomies, and custom fields.

## ✨ Features

- **Internationalization** - Multi-language support including Japanese
- **Hierarchical Taxonomy Support** - Export/import parent-child relationships
- **Block Editor Compatible** - Preserves Gutenberg block structure completely
- **Batch Processing** - Handle large CSV files without timeouts
- **Auto Updates** - One-click updates from WordPress admin
- **Responsive UI** - Mobile-friendly admin interface

## 🚀 Installation

### Install from WordPress.org (Recommended)

1. Go to **Admin Dashboard → Plugins → Add New**
2. Search for "Swift CSV"
3. Click **Install Now**

### Manual Installation

1. [Download swift-csv-v0.9.7.zip](https://github.com/firstelementjp/swift-csv/releases/download/v0.9.7/swift-csv-v0.9.7.zip) ⭐ **Recommended**
2. Extract the ZIP file to get `swift-csv/` folder
3. Upload to `/wp-content/plugins/` directory
4. Activate the plugin from admin dashboard

⚠️ **Important**: Use the manual ZIP file above, not "Source code (zip)" for proper installation.

## 📖 Usage

### CSV Export

1. Go to **Admin Dashboard → Swift CSV → Export**
2. Configure export options
    - **Number of posts**: Set the number of posts to export (default: 1000, 0 for unlimited)
3. Select post type
4. Click **Export CSV**

Large datasets are automatically processed in batches to prevent timeouts.

### CSV Import

1. Go to **Admin Dashboard → Swift CSV → Import**
2. Select target post type
3. Choose UTF-8 encoded CSV file
4. Configure import options
5. Click **Import CSV**

## 📖 Documentation

For detailed documentation, API reference, and examples:

📚 **[Complete Documentation](https://firstelementjp.github.io/swift-csv/)**

### Developer Notes

Developer-focused internal notes (not end-user docs):

- **Import AJAX architecture**: [`dev-notes/import-ajax-handler-architecture.md`](dev-notes/import-ajax-handler-architecture.md)

### Quick Links

- [Installation Guide](https://firstelementjp.github.io/swift-csv/#/installation)
- [Getting Started](https://firstelementjp.github.io/swift-csv/#/getting-started)
- [Configuration](https://firstelementjp.github.io/swift-csv/#/configuration)
- [Examples](https://firstelementjp.github.io/swift-csv/#/examples)
- [Troubleshooting](https://firstelementjp.github.io/swift-csv/#/troubleshooting)

## 📋 CSV Format

### Basic Structure

| Column       | Required | Description                        |
| ------------ | -------- | ---------------------------------- |
| post_title   | ✅       | Post title                         |
| post_content | ❌       | Post content (HTML supported)      |
| post_excerpt | ❌       | Post excerpt                       |
| post_status  | ❌       | Post status (publish, draft, etc.) |
| post_name    | ❌       | Post slug                          |

### Hierarchical Taxonomies

```
Category A > Subcategory A > Grandchild
Technology > WordPress > Plugin Development
```

**Multi-value support**: Use `|` (pipe) to separate multiple values:

```
category: "category-a|subcategory-a|grandchild"
```

### Custom Fields

Use `cf_` prefix for custom fields:

```
cf_price, cf_color, cf_size
```

**Multi-value support**: Use `|` (pipe) to separate multiple values:

```
cf_tags: "wordpress|php|developer"
```

## 🔧 Requirements

- **WordPress**: 6.0 or higher
- **PHP**: 8.0 or higher
- **Memory**: 128MB+ (for large CSV processing)

## 🌍 Internationalization

Currently supported languages:

- 🇯🇵 Japanese
- 🇺🇸 English (default)

Interested in helping with translations? Contact us on [GitHub](https://github.com/firstelementjp/swift-csv).

## 🧪 Testing

### Running Tests

#### Standard Environment

```bash
composer test
composer run test-coverage
```

#### Unit/Integration Tests

```bash
composer run test-unit        # Unit tests only
composer run test-integration # Integration tests only
```

#### Local by Flywheel

When testing in Local by Flywheel environment, you may need to set the absolute path to the bootstrap file:

```bash
export PHPUNIT_BOOTSTRAP="/app/public/wp-content/plugins/swift-csv/tests/bootstrap.php"
composer run test-coverage
```

Alternatively, update the `phpunit.xml` bootstrap path to use the absolute path for your Local environment.

### Test Coverage

Coverage reports are generated in `tests/coverage/` directory. Open `tests/coverage/index.html` in your browser to view detailed coverage information.

### Test Structure

- **Unit Tests**: `tests/Unit/` - Isolated component testing
- **Integration Tests**: `tests/Integration/` - WordPress environment testing
- **Bootstrap**: `tests/bootstrap.php` - WordPress test environment setup

### CI/CD

Tests are automatically run in GitHub Actions with full WordPress environment support.

## �� Contributing

We welcome contributions! Please follow our development workflow:

### Development Workflow

1. **Fork** the repository
2. **Create feature branch**: `git checkout -b feature/batch-export-ui`
3. **Make changes** and test thoroughly
4. **Push to develop**: `git push origin develop`
5. **Create Pull Request** to develop branch
6. **Review and merge** to develop
7. **Release**: Merge develop to main with version tag

### Branch Strategy

- **main**: Stable releases (v0.9.4, v0.9.5, etc.)
- **develop**: Development branch with latest features
- **feature/\***: Individual feature development

### Current Development

- **v0.9.7 Released**: Real-time export progress with detailed logging, enhanced license status detection, complete Japanese localization, and adaptive batch processing
- **Future**: Enhanced features and integrations are welcome through [GitHub Issues](https://github.com/firstelementjp/swift-csv/issues).

## 📄 License

GPLv2+ - See [LICENSE](LICENSE) file for details

## 🎯 Hooks for Developers

Swift CSV provides extensive customization options through hooks. For complete documentation, see **[Hooks Documentation](docs/hooks.md)**.

### Popular Hooks

- `swift_csv_export_headers` - Filter export headers
- `swift_csv_export_row` - Filter each export row
- `swift_csv_export_process_custom_header` - Provide values for custom export headers
- `swift_csv_export_phase_headers` - Action fired after export headers are finalized
- `swift_csv_import_row_validation` - Validate an import row
- `swift_csv_import_data_filter` - Normalize/filter raw import row data
- `swift_csv_prepare_import_fields` - Prepare meta fields before persisting
- `swift_csv_import_phase_map_prepared` - Action fired after fields are mapped/prepared
- `swift_csv_import_phase_post_persist` - Action fired after persistence
- `swift_csv_import_batch_size` - Filter import batch size

📚 **[View All Hooks](docs/hooks.md)** - Complete API reference with examples

## 👨‍💻 Developer

[FirstElement,Inc.](https://www.firstelement.co.jp/), [Daijiro Miyazawa](https://x.com/dxd5001)

---

⭐ Please consider leaving a review if you find this plugin helpful!
