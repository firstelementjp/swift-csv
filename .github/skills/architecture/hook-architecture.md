# Hook Architecture

> Export header generation uses a three-element merge pattern with strategic hook placement.
> Import and export requests now enter through unified AJAX handlers, then fan out into planner/handler/pipeline classes with extensibility hooks.

## Export Header Generation Flow

```
1. Post fields     → fe_csv_import_export_get_allowed_post_fields($scope)
                      ↳ Hooks: fe_csv_import_export_basic_post_fields, fe_csv_import_export_additional_post_fields
2. Taxonomies      → get_object_taxonomies($post_type, 'objects')
                      ↳ Hook: fe_csv_import_export_filter_taxonomy_objects (object-level filtering)
3. Custom fields   → meta key discovery from sample posts
                      ↳ Hook: fe_csv_import_export_filter_custom_field_headers
4. Merge           → array_merge($post_fields, $tax_headers, $cf_headers)
```

## Import Processing Flow

```
1. AJAX entry point      → FE_CSV_Import_Export_Ajax_Import_Unified
2. Request parsing       → FE_CSV_Import_Export_Import_Request_Parser
3. File processing       → FE_CSV_Import_Export_Import_File_Processor
4. Streamed CSV parsing  → FE_CSV_Import_Export_Import_CSV_Parser / FE_CSV_Import_Export_Import_CSV_Store
5. Batch execution       → FE_CSV_Import_Export_Import_Batch_Processor
6. Row context           → FE_CSV_Import_Export_Import_Row_Context
7. Row processing        → FE_CSV_Import_Export_Import_Row_Processor
8. Data persistence      → FE_CSV_Import_Export_Import_Persister
9. Taxonomy handling     → FE_CSV_Import_Export_Import_Taxonomy_Writer
10. Response formatting  → FE_CSV_Import_Export_Import_Response_Manager
```

## Export Processing Flow

```
1. AJAX entry point   → FE_CSV_Import_Export_Ajax_Export_Unified
2. Batch planning     → FE_CSV_Import_Export_Ajax_Export_Batch_Planner
3. Method routing     → WP-compatible / Direct SQL handlers
4. Export execution   → FE_CSV_Import_Export_Export_Base descendants
5. Log persistence    → FE_CSV_Import_Export_Export_Log_Store
6. Cancellation check → FE_CSV_Import_Export_Export_Cancel_Manager
```

## Hook Reference

### Header Hooks

| Hook                                    | Parameters                    | Purpose                                                  |
| --------------------------------------- | ----------------------------- | -------------------------------------------------------- |
| `fe_csv_import_export_basic_post_fields`           | `$fields, $scope`             | Customize basic export fields (ID, title, content, etc.) |
| `fe_csv_import_export_additional_post_fields`      | `$fields, $scope`             | Customize 'all' scope additional fields                  |
| `fe_csv_import_export_filter_taxonomy_objects`     | `$taxonomy_objects, $args`    | Filter taxonomy objects before header creation           |
| `fe_csv_import_export_filter_custom_field_headers` | `$headers, $meta_keys, $args` | Customize custom field headers (Pro: enhanced features)  |

### Query Hooks

| Hook                          | Parameters           | Purpose                                        |
| ----------------------------- | -------------------- | ---------------------------------------------- |
| `fe_csv_import_export_sample_query_args` | `$query_args, $args` | Customize sample post query for meta discovery |
| `fe_csv_import_export_export_query_args` | `$query_args, $args` | Customize main export query                    |

### Import Hooks

| Hook                         | Parameters                | Purpose                                |
| ---------------------------- | ------------------------- | -------------------------------------- |
| `fe_csv_import_export_pre_ajax_import`  | `$result, $_POST`         | Preflight import request validation    |
| `fe_csv_import_export_user_can_import`  | `$allowed`                | Override import capability checks      |
| `fe_csv_import_export_before_import`    | `$args`                   | Pre-import preparation                 |
| `fe_csv_import_export_import_row`       | `$row_data, $args`        | Per-row data transformation            |
| `fe_csv_import_export_after_import`     | `$stats, $args`           | Post-import cleanup                    |
| `fe_csv_import_export_cancelled_import` | `$cancelled, $session_id` | Override import cancellation detection |

### Export Hooks

| Hook                         | Parameters                | Purpose                                |
| ---------------------------- | ------------------------- | -------------------------------------- |
| `fe_csv_import_export_pre_ajax_export`  | `$result, $_POST`         | Preflight export request validation    |
| `fe_csv_import_export_user_can_export`  | `$allowed`                | Override export capability checks      |
| `fe_csv_import_export_cancelled_export` | `$cancelled, $session_id` | Override export cancellation detection |

### Import Processing Hooks

| Hook                                 | Parameters                          | Purpose                            |
| ------------------------------------ | ----------------------------------- | ---------------------------------- |
| `fe_csv_import_export_import_row_context`       | `$context, $row_data, $args`        | Customize row context creation     |
| `fe_csv_import_export_import_persist_result`    | `$result, $post_data, $context`     | Modify post persistence result     |
| `fe_csv_import_export_taxonomy_term_resolution` | `$term_ids, $term_value, $taxonomy` | Customize taxonomy term resolution |

## Design Principles

1. **Always execute hooks** — Never wrap `apply_filters()` in conditionals. Pass context as parameters; let hook implementations handle conditions.
2. **Single responsibility** — Each hook handles one data type (post fields OR taxonomies OR custom fields).
3. **Object-level filtering** — Filter taxonomy/field objects before converting to headers, giving hooks access to full object properties.
4. **Context parameters** — Pass `$args` array with `post_type`, `export_scope`, `context`, etc.
5. **Unified handler entry** — AJAX endpoints stay thin; request parsing and execution live in specialized classes.
6. **Interface-based extensibility** — Import/export systems use base classes with WP-compatible implementations for easy extension.

## Free/Pro Integration

Pro version extends Free via hooks:

```php
// Pro registers hooks in its constructor
add_filter('fe_csv_import_export_filter_custom_field_headers', [$this, 'process_enhanced_headers'], 10, 3);
add_filter('fe_csv_import_export_classify_meta_keys', [$this, 'classify_enhanced_keys'], 10, 2);
add_filter('fe_csv_import_export_taxonomy_term_resolution', [$this, 'resolve_taxonomy_terms'], 10, 3);
```

Pro uses a three-stage custom field pipeline:

1. **Sample post filtering** — `fe_csv_import_export_filter_sample_posts`
2. **Meta key classification** — `fe_csv_import_export_classify_meta_keys` (returns `['enhanced' => [], 'regular' => [], 'private' => []]`)
3. **Header generation** — `fe_csv_import_export_generate_custom_field_headers`

## Import/Export Runtime Architecture

The runtime keeps orchestration classes thin and delegates behavior to shared utilities and implementation classes:

```php
// Base classes define the contract
FE_CSV_Import_Export_Import_Base
FE_CSV_Import_Export_Export_Base

// WP-compatible implementations
FE_CSV_Import_Export_Import_WP_Compatible extends FE_CSV_Import_Export_Import_Base
FE_CSV_Import_Export_Export_WP_Compatible extends FE_CSV_Import_Export_Export_Base

// Unified AJAX entry points
FE_CSV_Import_Export_Ajax_Import_Unified
FE_CSV_Import_Export_Ajax_Export_Unified

// Specialized utilities
FE_CSV_Import_Export_Import_Taxonomy_Writer_Interface
FE_CSV_Import_Export_Import_Taxonomy_Writer_WP implements FE_CSV_Import_Export_Import_Taxonomy_Writer_Interface
```

This allows for:

- Easy testing with mock implementations
- Pro version to extend with specialized implementations
- Clear separation of concerns
- Type safety with proper interfaces
