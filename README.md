# Swift CSV

![Swift CSV Banner](https://github.com/firstelementjp/swift-csv/blob/main/assets/images/swift-csv-banner.jpeg?raw=true)

[![Version](https://img.shields.io/badge/version-0.9.8-green.svg)](https://github.com/firstelementjp/swift-csv/releases)
[![License](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.6%2B-blue.svg)](https://wordpress.org/)

Swift CSV is a lightweight WordPress plugin for importing and exporting CSV data.
It supports custom post types, custom taxonomies, custom fields, and Gutenberg block content.

This repository contains the plugin source code, tests, and developer resources.
For end-user guides and full documentation, see the links below.

## 📖 Overview

Swift CSV provides:

- CSV import/export for WordPress content
- Support for custom post types, taxonomies, and custom fields
- Batch processing for large CSV files
- Real-time progress logging
- Hook-based extensibility for developers

## ⚡ Quick Links

- **Documentation**: https://firstelementjp.github.io/swift-csv/
- **Installation Guide**: https://firstelementjp.github.io/swift-csv/#/installation
- **Getting Started**: https://firstelementjp.github.io/swift-csv/#/getting-started
- **Configuration**: https://firstelementjp.github.io/swift-csv/#/configuration
- **Examples**: https://firstelementjp.github.io/swift-csv/#/examples
- **Troubleshooting**: https://firstelementjp.github.io/swift-csv/#/troubleshooting
- **Releases**: https://github.com/firstelementjp/swift-csv/releases
- **Issues**: https://github.com/firstelementjp/swift-csv/issues

## ⚙️ Requirements

- WordPress 6.6 or higher
- PHP 8.1 or higher
- OpenSSL extension

## 🚀 Installation

### From WordPress Admin

1. Download the latest release ZIP from the [Releases page](https://github.com/firstelementjp/swift-csv/releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Select the ZIP file and upload it
4. Activate the plugin

### Manual Installation

1. Download the latest release ZIP from the [Releases page](https://github.com/firstelementjp/swift-csv/releases)
2. Extract the ZIP file
3. Upload the `swift-csv/` directory to `/wp-content/plugins/`
4. Activate the plugin from the WordPress admin screen

> [!IMPORTANT]
> Use the release ZIP file for installation, not **Source code (zip)**.

## 💻 Local Development

```bash
git clone https://github.com/firstelementjp/swift-csv.git
cd swift-csv
composer install
```

After installing dependencies, place the plugin in your local WordPress environment and activate it from the admin dashboard.

## 🧪 Testing

Run all tests:

```bash
composer test
```

Run coverage

```bash
composer run test-coverage
```

Run specific test suites

```bash
composer run test-unit
composer run test-integration
```

Coverage reports are generated in:

```
tests/coverage/
```

## 🛠️ Development Commands

```bash
composer test              # Run test suite
composer phpcs             # Check coding standards
composer phpcbf            # Fix coding standards automatically
npm run build              # Build/minify frontend assets
./test-release.sh          # Create a release ZIP locally
```

See the project documentation for detailed development and release workflows.

## 📝 Developer Notes

- Import/export operations are designed to work in batches for large datasets.
- Gutenberg block content is preserved during import/export.
- The plugin provides extensibility through WordPress hooks.

For detailed implementation notes, see:

- [Developer documentation](https://firstelementjp.github.io/swift-csv/#/developer)
- [Architecture overview](https://firstelementjp.github.io/swift-csv/#/architecture)
- [Hooks documentation](https://firstelementjp.github.io/swift-csv/#/hooks)

## 🤝 Contributing

Contributions are welcome.

Basic workflow:

- Fork the repository
- Create a feature branch from develop
- Make changes and run tests
- Open a Pull Request against develop

Please check existing issues before opening a new feature request or bug report.

## 📄 License

GPLv2+

See LICENSE for details.

## 👨‍💻 Authors

- [Daijiro Miyazawa](https://x.com/dxd5001)
- [FirstElement, Inc.](https://www.firstelement.co.jp)
