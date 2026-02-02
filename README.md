# Swift CSV

![Swift CSV Banner](https://github.com/firstelementjp/swift-csv/blob/main/assets/images/swift-csv-banner.jpeg?raw=true)

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

- 🌐 **Internationalization** - Multi-language support including Japanese
- 📊 **Hierarchical Taxonomy Support** - Export/import parent-child relationships
- 🎨 **Block Editor Compatible** - Preserves Gutenberg block structure completely
- 📝 **Batch Processing** - Handle large CSV files without timeouts
- 🔄 **Auto Updates** - One-click updates from WordPress admin
- 📱 **Responsive UI** - Mobile-friendly admin interface

## 🚀 Installation

### Install from WordPress.org (Recommended)

1. Go to **Admin Dashboard → Plugins → Add New**
2. Search for "Swift CSV"
3. Click **Install Now**

### Manual Installation

1. [Download swift-csv-0.9.2.zip](https://github.com/firstelementjp/swift-csv/releases/download/v0.9.2/swift-csv-0.9.2.zip) ⭐ **Recommended**
2. Extract the ZIP file to get `swift-csv/` folder
3. Upload to `/wp-content/plugins/` directory
4. Activate the plugin from admin dashboard

⚠️ **Important**: Use the manual ZIP file above, not "Source code (zip)" for proper installation.

## 📖 Usage

### CSV Export

1. Go to **Admin Dashboard → Swift CSV → Export**
2. Select post type
3. Set number of posts (large datasets will be processed in batches)
4. Click **Export CSV**

### CSV Import

1. Go to **Admin Dashboard → Swift CSV → Import**
2. Select target post type
3. Choose UTF-8 encoded CSV file
4. Configure import options
5. Click **Import CSV**

## 📖 Documentation

For detailed documentation, API reference, and examples:

📚 **[Complete Documentation](https://firstelementjp.github.io/swift-csv/)**

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

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Memory**: 128MB+ (for large CSV processing)

## 🌍 Internationalization

Currently supported languages:

- 🇯🇵 Japanese
- 🇺🇸 English (default)

Interested in helping with translations? Contact us on [GitHub](https://github.com/firstelementjp/swift-csv).

## 🤝 Contributing

Bug reports and feature requests are welcome through [GitHub Issues](https://github.com/firstelementjp/swift-csv/issues).

## 📄 License

GPLv2+ - See [LICENSE](LICENSE) file for details

## 👨‍💻 Developer

[FirstElement,Inc.](https://www.firstelement.co.jp/), [Daijiro Miyazawa](https://x.com/dxd5001)

---

⭐ Please consider leaving a review if you find this plugin helpful!
