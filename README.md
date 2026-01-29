# Swift CSV

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

1. [Download the latest version](https://github.com/firstelementjp/swift-csv/releases/latest)
2. Extract the ZIP file
3. Upload to `/wp-content/plugins/` directory
4. Activate the plugin from admin dashboard

## 📖 Usage

### CSV Export

1. Go to **Admin Dashboard → Swift CSV → Export**
2. Select post type
3. Set number of posts (max 10,000)
4. Click **Export CSV**

### CSV Import

1. Go to **Admin Dashboard → Swift CSV → Import**
2. Select target post type
3. Choose UTF-8 encoded CSV file
4. Configure import options
5. Click **Import CSV**

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

[FirstElement,Inc.](https://www.firstelement.co.jp/)

---

⭐ Please consider leaving a review if you find this plugin helpful!
