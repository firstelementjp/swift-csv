# 🚀 Getting Started

Welcome to Swift CSV v0.9.9. This guide covers the shortest path from installation to your first successful import and export.

## What Swift CSV Does

Swift CSV helps you import and export WordPress content as CSV from the admin interface.

It supports:

- Standard WordPress post fields
- Custom post types
- Custom fields with the `cf_` prefix
- Taxonomy columns including hierarchical values
- Automatic batch processing for large datasets
- Localized admin UI with English base strings and Japanese translations

## Admin Location

After activation, open:

- **Tools → Swift CSV**

## First Import

1. Open **Tools → Swift CSV → Import**
2. Select the target post type
3. Upload a CSV file
4. Choose whether to update existing posts
5. Run a dry run first if you want to validate the file safely
6. Start the import and wait for completion

### Minimum Test CSV

```csv
ID,post_title,post_content,post_status
"","Sample Post","This is the content","publish"
"","Another Post","More content here","draft"
```

Notes:

- The `ID` column is required.
- Use an empty `ID` value for new posts.
- Use an existing post ID only when you intend to update a post.
- Save the file as UTF-8 when possible.

### Import CSV Rules

- The first row must contain headers.
- Custom fields must use the `cf_` prefix.
- Multi-value fields use the `|` separator.
- Hierarchical taxonomy values use `>` between levels.

## First Export

1. Open **Tools → Swift CSV → Export**
2. Select the post type
3. Select the post status filter
4. Select the export scope
5. Optionally set an export limit
6. Start the export and download the generated CSV

## What You Will See

During processing, Swift CSV shows:

- Real-time progress updates
- Animated progress UI
- Per-item log details for current processing
- Completion state when the job finishes

## Common CSV Patterns

### Custom Fields

```csv
ID,post_title,cf_price,cf_color,cf_tags
"","Product A","19.99","red","sale|featured"
"","Product B","29.99","blue","new|featured"
```

### Hierarchical Taxonomies

```csv
ID,post_title,category,post_tag
"","Tech Post","Technology > WordPress > Plugins","tech|wordpress|php"
```

## Batch Processing

You do not need to configure batch processing manually.

Swift CSV automatically adjusts processing size based on:

- Dataset size
- Server execution limits
- Memory constraints
- Import or export context

Current behavior is documented in [Configuration](config.md).

## Good First Checks

After your first import or export, verify:

- The posts were created or updated as expected
- Custom fields were saved correctly
- Taxonomies were assigned correctly
- Exported columns match the expected headers

## Next Steps

- Read [Installation](install.md) for requirements and setup details
- Read [Configuration](config.md) for current behavior and limits
- Read [Examples](example.md) for sample CSV patterns
- Read [Troubleshooting](help.md) if something does not behave as expected
- Read [Developer Hooks](hooks.md) if you want to customize behavior
