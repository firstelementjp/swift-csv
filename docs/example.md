# 💡 Examples

## Current Implementation

**Note**: Swift CSV is primarily designed as a WordPress admin interface plugin. The examples below show the internal structure, but actual usage is through the admin interface.

## Admin Interface Usage

### CSV Export via Admin Interface

1. Navigate to **Admin Dashboard → Swift CSV → Export**
2. Select post type from dropdown
3. Set number of posts (large datasets will be processed in batches)
4. Click **Export CSV** to download file

### CSV Import via Admin Interface

1. Navigate to **Admin Dashboard → Swift CSV → Import**
2. Select target post type
3. Choose CSV file (UTF-8, Shift-JIS, EUC-JP, JIS auto-detected)
4. Configure import options
5. Click **Import CSV**

For large files (>1000 rows or >10MB), automatic batch processing with progress tracking will be used.

## CSV Format Examples

### Basic CSV Structure

```csv
post_title,post_content,post_excerpt,post_status
"Sample Post","This is the content","Sample excerpt",publish
"Another Post","More content here","",draft
```

### Custom Fields Example

```csv
post_title,post_content,cf_Name,cf_Email,cf_Phone,cf_Tags
"John Doe","Content about John","John","john@example.com","555-1234","developer|wordpress|php"
"Jane Smith","Content about Jane","Jane","jane@example.com","555-5678","designer|ui|ux"
```

**Note**:

- Custom fields must use `cf_` prefix in the CSV header (e.g., `cf_Name`, `cf_Email`, `cf_Phone`).
- **Multi-value support**: Use `|` (pipe) to separate multiple values (e.g., `cf_Tags` with `developer|wordpress|php`).

### Hierarchical Taxonomies Example

```csv
post_title,post_content,category,post_tag
"Tech Post","About technology","Technology > WordPress > Plugins","tech,wordpress"
"Design Post","About design","Design > UI > Web","design,ui,web"
```

**Note**: Use `>` to separate hierarchy levels in taxonomies (e.g., `Technology > WordPress > Plugins` creates a three-level hierarchy). Multiple taxonomy terms can be comma-separated.

**Auto-creation**: Missing terms are automatically created with unique IDs and URL-friendly slugs (e.g., "Technology" → slug: "technology").

## Internal API Structure

**For Developers**: The following classes handle the core functionality:

- `Swift_CSV_Importer`: Handles CSV import processing
- `Swift_CSV_Exporter`: Handles CSV export generation
- `Swift_CSV_Batch`: Manages batch processing for large files
- `Swift_CSV_Admin`: Provides admin interface

**Note**: Direct API usage is not currently documented as the plugin is designed for admin interface usage.
