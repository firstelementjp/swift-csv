# Hook Architecture

> Export header generation uses a three-element merge pattern with strategic hook placement.
> Import processing uses interface-based architecture with extensibility hooks.

## Export Header Generation Flow

```
1. Post fields     → swift_csv_get_allowed_post_fields($scope)
                      ↳ Hooks: swift_csv_basic_post_fields, swift_csv_additional_post_fields
2. Taxonomies      → get_object_taxonomies($post_type, 'objects')
                      ↳ Hook: swift_csv_filter_taxonomy_objects (object-level filtering)
3. Custom fields   → meta key discovery from sample posts
                      ↳ Hook: swift_csv_filter_custom_field_headers
4. Merge           → array_merge($post_fields, $tax_headers, $cf_headers)
```

## Import Processing Flow

```
1. Request parsing  → Swift_CSV_Import_Request Parser
2. Row context     → Swift_CSV_Import_Row_Context
3. Row processing  → Swift_CSV_Import_Row_Processor
4. Data persistence → Swift_CSV_Import_Persister
5. Taxonomy handling → Swift_CSV_Import_Taxonomy_Writer
```

## Hook Reference

### Header Hooks

| Hook                                    | Parameters                    | Purpose                                                  |
| --------------------------------------- | ----------------------------- | -------------------------------------------------------- |
| `swift_csv_basic_post_fields`           | `$fields, $scope`             | Customize basic export fields (ID, title, content, etc.) |
| `swift_csv_additional_post_fields`      | `$fields, $scope`             | Customize 'all' scope additional fields                  |
| `swift_csv_filter_taxonomy_objects`     | `$taxonomy_objects, $args`    | Filter taxonomy objects before header creation           |
| `swift_csv_filter_custom_field_headers` | `$headers, $meta_keys, $args` | Customize custom field headers (Pro: enhanced features)  |

### Query Hooks

| Hook                          | Parameters           | Purpose                                        |
| ----------------------------- | -------------------- | ---------------------------------------------- |
| `swift_csv_sample_query_args` | `$query_args, $args` | Customize sample post query for meta discovery |
| `swift_csv_export_query_args` | `$query_args, $args` | Customize main export query                    |

### Import Hooks

| Hook                      | Parameters         | Purpose                     |
| ------------------------- | ------------------ | --------------------------- |
| `swift_csv_before_import` | `$args`            | Pre-import preparation      |
| `swift_csv_import_row`    | `$row_data, $args` | Per-row data transformation |
| `swift_csv_after_import`  | `$stats, $args`    | Post-import cleanup         |

### Import Processing Hooks

| Hook                                 | Parameters                          | Purpose                            |
| ------------------------------------ | ----------------------------------- | ---------------------------------- |
| `swift_csv_import_row_context`       | `$context, $row_data, $args`        | Customize row context creation     |
| `swift_csv_import_persist_result`    | `$result, $post_data, $context`     | Modify post persistence result     |
| `swift_csv_taxonomy_term_resolution` | `$term_ids, $term_value, $taxonomy` | Customize taxonomy term resolution |

## Design Principles

1. **Always execute hooks** — Never wrap `apply_filters()` in conditionals. Pass context as parameters; let hook implementations handle conditions.
2. **Single responsibility** — Each hook handles one data type (post fields OR taxonomies OR custom fields).
3. **Object-level filtering** — Filter taxonomy/field objects before converting to headers, giving hooks access to full object properties.
4. **Context parameters** — Pass `$args` array with `post_type`, `export_scope`, `context`, etc.
5. **Interface-based extensibility** — Import system uses base classes with WP-compatible implementations for easy extension.

## Free/Pro Integration

Pro version extends Free via hooks:

```php
// Pro registers hooks in its constructor
add_filter('swift_csv_filter_custom_field_headers', [$this, 'process_enhanced_headers'], 10, 3);
add_filter('swift_csv_classify_meta_keys', [$this, 'classify_enhanced_keys'], 10, 2);
add_filter('swift_csv_taxonomy_term_resolution', [$this, 'resolve_taxonomy_terms'], 10, 3);
```

Pro uses a three-stage custom field pipeline:

1. **Sample post filtering** — `swift_csv_filter_sample_posts`
2. **Meta key classification** — `swift_csv_classify_meta_keys` (returns `['enhanced' => [], 'regular' => [], 'private' => []]`)
3. **Header generation** — `swift_csv_generate_custom_field_headers`

## Import Architecture

The import system uses interface-based architecture:

```php
// Base classes define the contract
Swift_CSV_Import_Base
Swift_CSV_Export_Base

// WP-compatible implementations
Swift_CSV_Import_WP_Compatible extends Swift_CSV_Import_Base
Swift_CSV_Export_WP_Compatible extends Swift_CSV_Export_Base

// Specialized utilities
Swift_CSV_Import_Taxonomy_Writer_Interface
Swift_CSV_Import_Taxonomy_Writer_WP implements Swift_CSV_Import_Taxonomy_Writer_Interface
```

This allows for:

- Easy testing with mock implementations
- Pro version to extend with specialized implementations
- Clear separation of concerns
- Type safety with proper interfaces
