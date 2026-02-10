# Hook Architecture

> Export header generation uses a three-element merge pattern with strategic hook placement.

## Header Generation Flow

```
1. Post fields     → swift_csv_get_allowed_post_fields($scope)
                      ↳ Hooks: swift_csv_basic_post_fields, swift_csv_additional_post_fields
2. Taxonomies      → get_object_taxonomies($post_type, 'objects')
                      ↳ Hook: swift_csv_filter_taxonomy_objects (object-level filtering)
3. Custom fields   → meta key discovery from sample posts
                      ↳ Hook: swift_csv_filter_custom_field_headers
4. Merge           → array_merge($post_fields, $tax_headers, $cf_headers)
```

## Hook Reference

### Header Hooks

| Hook | Parameters | Purpose |
|------|-----------|---------|
| `swift_csv_basic_post_fields` | `$fields, $scope` | Customize basic export fields (ID, title, content, etc.) |
| `swift_csv_additional_post_fields` | `$fields, $scope` | Customize 'all' scope additional fields |
| `swift_csv_filter_taxonomy_objects` | `$taxonomy_objects, $args` | Filter taxonomy objects before header creation |
| `swift_csv_filter_custom_field_headers` | `$headers, $meta_keys, $args` | Customize custom field headers (Pro: ACF integration) |

### Query Hooks

| Hook | Parameters | Purpose |
|------|-----------|---------|
| `swift_csv_sample_query_args` | `$query_args, $args` | Customize sample post query for meta discovery |
| `swift_csv_export_query_args` | `$query_args, $args` | Customize main export query |

### Import Hooks

| Hook | Parameters | Purpose |
|------|-----------|---------|
| `swift_csv_before_import` | `$args` | Pre-import preparation |
| `swift_csv_import_row` | `$row_data, $args` | Per-row data transformation |
| `swift_csv_after_import` | `$stats, $args` | Post-import cleanup |

## Design Principles

1. **Always execute hooks** — Never wrap `apply_filters()` in conditionals. Pass context as parameters; let hook implementations handle conditions.
2. **Single responsibility** — Each hook handles one data type (post fields OR taxonomies OR custom fields).
3. **Object-level filtering** — Filter taxonomy/field objects before converting to headers, giving hooks access to full object properties.
4. **Context parameters** — Pass `$args` array with `post_type`, `export_scope`, `context`, etc.

## Free/Pro Integration

Pro version extends Free via hooks:

```php
// Pro registers hooks in its constructor
add_filter('swift_csv_filter_custom_field_headers', [$this, 'process_acf_headers'], 10, 3);
add_filter('swift_csv_classify_meta_keys', [$this, 'classify_acf_keys'], 10, 2);
```

Pro uses a three-stage custom field pipeline:
1. **Sample post filtering** — `swift_csv_filter_sample_posts`
2. **Meta key classification** — `swift_csv_classify_meta_keys` (returns `['acf' => [], 'regular' => [], 'private' => []]`)
3. **Header generation** — `swift_csv_generate_custom_field_headers`
