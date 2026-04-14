# 🚀 Swift CSV

![Banner](https://github.com/firstelementjp/swift-csv/blob/main/assets/images/swift-csv-banner.jpeg?raw=true)

> CSV import/export for WordPress with localized admin UI, automatic batch processing, and developer extensibility.

[![License](https://img.shields.io/badge/license-GPL--2.0%2B-blue.svg)](legal.md)
[![Version](https://img.shields.io/badge/version-0.9.9.2-green.svg)](https://github.com/firstelementjp/swift-csv/releases)
[![WordPress](https://img.shields.io/badge/WordPress-6.6%2B-blue.svg)](https://wordpress.org/)

Swift CSV is a WordPress plugin for importing and exporting post data as CSV from the admin area.

## ✨ Features

- Real-time import/export progress display
- Automatic batch processing for large datasets
- Support for custom fields with the `cf_` prefix
- Support for taxonomy columns including hierarchical values
- Localized UI with English base strings and Japanese translations
- Hook-based extensibility for developers
- Separate free/pro integration through WordPress hooks

## 📋 Requirements

- **WordPress**: 6.6 or higher
- **PHP**: 8.1 or higher
- **Memory**: 128MB or higher recommended
- **Extensions**: `mbstring`, `zip`

## 📥 Download

[Download swift-csv-0.9.9.2.zip](https://github.com/firstelementjp/swift-csv/releases/download/v0.9.9.2/swift-csv-0.9.9.2.zip){: .download-btn }

## 🚀 Quick Start

1. Download `swift-csv-0.9.9.2.zip`
2. Upload and activate the plugin in WordPress
3. Open **Tools → Swift CSV**
4. Run a small test import or export first

For the import format, note that the `ID` column is required. Use an empty value for new posts.

## 📖 Documentation

- [Getting Started](start.md) - Quick introduction and basics
- [Installation](install.md) - Detailed installation instructions
- [Configuration](config.md) - Configuration options and settings
- [Examples](example.md) - Implementation examples and use cases
- [Troubleshooting](help.md) - Common issues and solutions
- [Developer Hooks](hooks.md) - Developer reference and customization
- [Contributing](contribute.md) - Development guidelines
- [Changelog](changes.md) - Version history and updates
- [License](legal.md) - License and legal information

## 🤝 Contributing

Contributions are welcome! See the [Contributing Guide](contribute.md) for details.

## 📄 License

This plugin is provided under the GPL-2.0+ license. See [legal.md](legal.md) for details.

---

<div style="text-align: center; margin-top: 40px;">
  <p>
    <a href="https://github.com/firstelementjp/swift-csv" target="_blank">🔧 GitHub</a>
  </p>
</div>
