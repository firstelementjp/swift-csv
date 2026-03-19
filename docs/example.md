# 💡 Examples

This page shows practical CSV examples for the current Swift CSV implementation.

## Admin Usage Flow

### Import

1. Open **Admin Dashboard → Tools → Swift CSV → Import**
2. Select the target post type
3. Upload the CSV file
4. Choose update and dry-run options as needed
5. Start the import

### Export

1. Open **Admin Dashboard → Tools → Swift CSV → Export**
2. Select the post type
3. Select post status and export scope
4. Optionally set an export limit
5. Start the export and download the CSV

## CSV Format Basics

### Minimum Import File

```csv
ID,post_title,post_content,post_status
"","Sample Post","This is the content","publish"
"","Another Post","More content here","draft"
```

Notes:

- The `ID` column is required.
- Use an empty `ID` for new posts.
- Use an existing ID only when updating an existing post.

### Custom Fields

```csv
ID,post_title,cf_name,cf_email,cf_tags
"","John Doe","John","john@example.com","developer|wordpress|php"
"","Jane Smith","Jane","jane@example.com","designer|ui|ux"
```

Notes:

- Custom field headers must begin with `cf_`.
- Multi-value custom fields use `|`.

### Hierarchical Taxonomies

```csv
ID,post_title,category,post_tag
"","Tech Post","Technology > WordPress > Plugins","tech|wordpress|php"
"","Design Post","Design > UI > Web","design|ui|ux"
```

Notes:

- Use `>` for hierarchy levels.
- Use `|` for multiple taxonomy values.
- Missing terms may be created during import, depending on the taxonomy context.

## Common Scenarios

### Blog Migration

```csv
ID,post_title,post_content,post_date,post_status,cf_original_id
"","My First Blog Post","Content here","2026-01-15 10:00:00","publish","legacy-123"
"","My Second Post","More content","2026-01-16 10:00:00","draft","legacy-124"
```

Good for:

- Preserving original references
- Migrating content in batches
- Testing with dry run before import

### Product Catalog

```csv
ID,post_title,post_content,post_status,cf_price,cf_sku,cf_stock,cf_color,cf_size
"","Classic T-Shirt","Premium cotton t-shirt","publish","19.99","TS-001","50","black|white|gray","S|M|L"
"","Denim Jeans","Classic fit denim jeans","publish","49.99","DJ-001","25","blue","30|32|34"
```

Good for:

- Importing multiple product attributes
- Storing lists in custom fields
- Exporting product data for external tools

### Team Directory

```csv
ID,post_title,cf_name,cf_title,cf_email,cf_department,cf_skills
"","John Smith","Senior Developer","john@company.com","Engineering","php|wordpress|javascript"
"","Jane Doe","UX Designer","jane@company.com","Design","ux|ui|figma"
```

Good for:

- Managing structured profile data
- Storing skills as pipe-separated values
- Exporting internal directories for review

## Export-Oriented Example

A typical export contains standard fields plus optional custom fields and taxonomy columns.

```csv
ID,post_title,post_content,post_status,category,post_tag,cf_price
"101","Sample Post","This is the content","publish","Technology > WordPress","tech|plugin","19.99"
```

## Validation Tips

Before importing:

- Save the file as UTF-8
- Keep the first row as headers
- Include `ID` as the first column
- Use quotes when values may contain commas
- Start with a small test file

## Related Pages

- [Getting Started](start.md)
- [Installation](install.md)
- [Configuration](config.md)
- [Troubleshooting](help.md)
