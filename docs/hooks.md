# Swift CSV Hooks

This document lists the hooks available in Swift CSV **as implemented in the current codebase**.

It focuses on:

- Accurate hook names and signatures
- Practical usage patterns
- Examples that mirror real-world extensions (WooCommerce, custom business rules, etc.)

## Table of Contents

- [Export Hooks](#export-hooks)
    - [Header generation](#header-generation)
    - [Row generation](#row-generation)
    - [Batch / performance](#batch--performance)
- [Import Hooks](#import-hooks)
    - [Permission and validation](#permission-and-validation)
    - [Field preparation and mapping](#field-preparation-and-mapping)
    - [Batch processing](#batch-processing)
    - [Logging and diagnostics](#logging-and-diagnostics)
- [Admin / UI Hooks](#admin--ui-hooks)
- [Feature Flags](#feature-flags)
- [Best Practices](#best-practices)

---

## Export Hooks

### Header generation

#### `swift_csv_export_filter_taxonomy_objects`

Filter taxonomy objects used for building `tax_{taxonomy}` headers.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_export_filter_taxonomy_objects', array $taxonomies, array $args ): array
```

**Parameters:**

- `$taxonomies` (`array`) Taxonomy objects returned by `get_object_taxonomies( $post_type, 'objects' )`.
- `$args` (`array`) Contextual arguments.
    - `post_type` (`string`)
    - `export_scope` (`string`)
    - `include_private_meta` (`bool`)
    - `context` (`string`) Currently `taxonomy_objects_filter`

**Example:** (exclude internal taxonomies)

```php
add_filter( 'swift_csv_export_filter_taxonomy_objects', 'my_swiftcsv_filter_taxonomies', 10, 2 );

function my_swiftcsv_filter_taxonomies( $taxonomies, $args ) {
    // English comments only.
    if ( ! is_array( $taxonomies ) ) {
        return [];
    }

    foreach ( $taxonomies as $key => $tax ) {
        if ( ! isset( $tax->name ) ) {
            continue;
        }

        // Example: hide a taxonomy from exports.
        if ( 'post_format' === $tax->name ) {
            unset( $taxonomies[ $key ] );
        }
    }

    return $taxonomies;
}
```

#### `swift_csv_export_sample_query_args`

Filter the WP query args used to pick a "sample post" for meta key discovery.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_export_sample_query_args', array $query_args, array $args ): array
```

**Parameters:**

- `$query_args` (`array`) The WP query args used to fetch sample IDs.
- `$args` (`array`) Contextual arguments.
    - `post_type` (`string`)
    - `context` (`string`) Currently `meta_discovery`

**Example:** (prefer recent posts with meta)

```php
add_filter( 'swift_csv_export_sample_query_args', 'my_swiftcsv_sample_query_args', 10, 2 );

function my_swiftcsv_sample_query_args( $query_args, $args ) {
    // Example: prefer posts that are likely to have custom fields.
    $query_args['orderby'] = 'modified';
    $query_args['order'] = 'DESC';
    return $query_args;
}
```

#### `swift_csv_export_classify_meta_keys`

Classify meta keys discovered from the sample post.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_export_classify_meta_keys', array $all_meta_keys, array $args ): array
```

**Parameters:**

- `$all_meta_keys` (`array<string>`) Raw discovered meta keys.
- `$args` (`array`) Context.
    - `post_type` (`string`)
    - `export_scope` (`string`)
    - `include_private_meta` (`bool`)
    - `context` (`string`) Currently `meta_key_classification`

**Expected return:**

```php
[
  'regular' => array<string>,
  'private' => array<string>,
]
```

**Example:** (exclude noisy keys)

```php
add_filter( 'swift_csv_export_classify_meta_keys', 'my_swiftcsv_classify_meta_keys', 10, 2 );

function my_swiftcsv_classify_meta_keys( $all_meta_keys, $args ) {
    $regular = [];
    $private = [];

    foreach ( (array) $all_meta_keys as $key ) {
        $key = (string) $key;
        if ( '' === $key ) {
            continue;
        }

        // Example: drop WordPress internal keys.
        if ( in_array( $key, [ '_edit_lock', '_edit_last' ], true ) ) {
            continue;
        }

        if ( 0 === strpos( $key, '_' ) ) {
            $private[] = $key;
        } else {
            $regular[] = $key;
        }
    }

    return [
        'regular' => $regular,
        'private' => $private,
    ];
}
```

#### `swift_csv_export_generate_custom_field_headers`

Generate custom-field (meta) headers from classified meta keys.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_export_generate_custom_field_headers', array $headers, array $classified_meta_keys, array $args ): array
```

**Parameters:**

- `$headers` (`array<string>`) Starts as an empty array.
- `$classified_meta_keys` (`array`) Result of `swift_csv_export_classify_meta_keys`.
- `$args` (`array`) Context.
    - `post_type` (`string`)
    - `export_scope` (`string`)
    - `include_private_meta` (`bool`)
    - `context` (`string`) Currently `custom_field_headers_generation`

**Example:** (only allow-listed meta keys)

```php
add_filter( 'swift_csv_export_generate_custom_field_headers', 'my_swiftcsv_custom_field_headers', 10, 3 );

function my_swiftcsv_custom_field_headers( $headers, $classified_meta_keys, $args ) {
    $allow = [ 'price', 'color', 'size' ];
    $out = [];

    foreach ( (array) ( $classified_meta_keys['regular'] ?? [] ) as $meta_key ) {
        $meta_key = (string) $meta_key;
        if ( in_array( $meta_key, $allow, true ) ) {
            $out[] = 'cf_' . $meta_key;
        }
    }

    return $out;
}
```

#### `swift_csv_export_headers`

Filter the final header list.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_export_headers', array $headers, array $config, string $context ): array
```

**Parameters:**

- `$headers` (`array<string>`) Final headers.
- `$config` (`array`) Export config.
- `$context` (`string`) Currently `standard` (WP compatible) or `direct_sql`.

**Example:** (add a custom computed column)

```php
add_filter( 'swift_csv_export_headers', 'my_swiftcsv_add_custom_header', 10, 3 );

function my_swiftcsv_add_custom_header( $headers, $config, $context ) {
    // Add a non-standard header. Its value will be provided by swift_csv_export_process_custom_header.
    $headers[] = 'my_permalink';
    return $headers;
}
```

#### `swift_csv_export_phase_headers`

Action fired after headers are finalized.

**Type:** action

**Signature:**

```php
do_action( 'swift_csv_export_phase_headers', array $headers, array $config, string $context ): void
```

**Example:** (log headers)

```php
add_action( 'swift_csv_export_phase_headers', 'my_swiftcsv_log_headers', 10, 3 );

function my_swiftcsv_log_headers( $headers, $config, $context ) {
    error_log( '[Swift CSV] Export headers finalized (' . $context . '): ' . implode( ',', (array) $headers ) );
}
```

### Row generation

#### `swift_csv_export_row`

Filter each row during export generation.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_export_row', array $row, int $post_id, array $config, string $context ): array
```

**Parameters:**

- `$row` (`array`) Row data.
    - For WP compatible export: typically an indexed array aligned to headers.
    - For Direct SQL export: an associative array is often used before converting to CSV.
- `$post_id` (`int`) Post ID.
- `$config` (`array`) Export config.
- `$context` (`string`) `wp_compatible`, `direct_sql`, or a scope value.

**Example:** (format product prices)

```php
add_filter( 'swift_csv_export_row', 'my_swiftcsv_export_row_format', 10, 4 );

function my_swiftcsv_export_row_format( $row, $post_id, $config, $context ) {
    // WP compatible export usually passes an indexed row aligned to headers.
    // Direct SQL export often passes an associative row.

    if ( 'direct_sql' === $context && is_array( $row ) && isset( $row['cf_price'] ) ) {
        $row['cf_price'] = number_format( (float) $row['cf_price'], 2, '.', '' );
        return $row;
    }

    // Example: for wp_compatible, update by index (keep the shape intact).
    // If you need header-aware edits here, also hook swift_csv_export_headers to locate indexes.
    if ( 'wp_compatible' === $context && is_array( $row ) && isset( $row[0] ) ) {
        // No-op example: return row as-is.
        return $row;
    }

    return $row;
}
```

#### `swift_csv_export_process_custom_header`

Provide a value for custom headers that are not standard `post_*`, `tax_*`, `cf_*`, or `ID`.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_export_process_custom_header', string $value, string $header, int $post_id, array $args ): string
```

**Parameters:**

- `$value` (`string`) Default empty.
- `$header` (`string`) Header name.
- `$post_id` (`int`) Post ID.
- `$args` (`array`) Context.
    - `post_type` (`string`)
    - `context` (`string`) Currently `export_data_processing`

**Example:** (implement `my_permalink` header)

```php
add_filter( 'swift_csv_export_process_custom_header', 'my_swiftcsv_custom_header_value', 10, 4 );

function my_swiftcsv_custom_header_value( $value, $header, $post_id, $args ) {
    if ( 'my_permalink' === $header ) {
        return (string) get_permalink( $post_id );
    }

    return (string) $value;
}
```

### Direct SQL export query customization

These hooks are primarily used by `Swift_CSV_Export_Direct_SQL`.

#### `swift_csv_export_query_spec`

Provide a unified query spec (tax_query/meta_query style) that can be applied to exports.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_export_query_spec', array $query_spec, array $config, string $context ): array
```

**Parameters:**

- `$query_spec` (`array`) Default empty.
- `$config` (`array`) Export config.
- `$context` (`string`) Currently `direct_sql`.

**Example:** (export only items with a meta flag)

```php
add_filter( 'swift_csv_export_query_spec', 'my_swiftcsv_export_query_spec', 10, 3 );

function my_swiftcsv_export_query_spec( $query_spec, $config, $context ) {
    if ( 'direct_sql' !== $context ) {
        return $query_spec;
    }

    // Example: only export posts where meta key "export_enabled" is "1".
    return [
        'meta_query' => [
            [
                'key'     => 'export_enabled',
                'compare' => '=',
                'value'   => '1',
            ],
        ],
    ];
}
```

#### `swift_csv_export_data_query_args`

Filter the argument array used by the Direct SQL data query.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_export_data_query_args', array $query_args, array $args ): array
```

**Parameters:**

- `$query_args` (`array`) Direct SQL query args (post_type, post_status, limit, offset, etc.).
- `$args` (`array`) Context.
    - `post_type` (`string`)
    - `export_limit` (`int`)
    - `context` (`string`) `direct_sql`

**Example:** (override ordering)

```php
add_filter( 'swift_csv_export_data_query_args', 'my_swiftcsv_export_data_query_args', 10, 2 );

function my_swiftcsv_export_data_query_args( $query_args, $args ) {
    // Currently Direct SQL export builds ORDER BY internally.
    // This hook is a stable place to pass additional control flags in the future.
    $query_args['my_custom_flag'] = true;
    return $query_args;
}
```

#### `swift_csv_export_direct_sql_query_args`

Direct SQL specific filter for query args.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_export_direct_sql_query_args', array $query_args, array $config ): array
```

**Example:** (cap the limit)

```php
add_filter( 'swift_csv_export_direct_sql_query_args', 'my_swiftcsv_cap_direct_sql_limit', 10, 2 );

function my_swiftcsv_cap_direct_sql_limit( $query_args, $config ) {
    if ( isset( $query_args['limit'] ) ) {
        $query_args['limit'] = min( (int) $query_args['limit'], 500 );
    }
    return $query_args;
}
```

#### `swift_csv_export_direct_sql_query_parts`

Filter SQL and params right before `$wpdb->prepare()`.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_export_direct_sql_query_parts', array $query_parts, array $config, string $context ): array
```

**Parameters:**

- `$query_parts` (`array`)
    - `sql` (`string`)
    - `params` (`array`)
- `$config` (`array`) Export config.
- `$context` (`string`) A label such as `posts_batch`.

**Example:** (inject additional WHERE clause)

```php
add_filter( 'swift_csv_export_direct_sql_query_parts', 'my_swiftcsv_direct_sql_query_parts', 10, 3 );

function my_swiftcsv_direct_sql_query_parts( $query_parts, $config, $context ) {
    if ( ! is_array( $query_parts ) || empty( $query_parts['sql'] ) ) {
        return $query_parts;
    }

    // Example: only export posts with non-empty titles.
    // Replace only the first WHERE to avoid breaking nested subqueries.
    $query_parts['sql'] = preg_replace( '/\\bWHERE\\b/', 'WHERE post_title <> "" AND', (string) $query_parts['sql'], 1 );
    return $query_parts;
}
```

#### `swift_csv_export_batch_data`

Filter the entire batch data for Direct SQL export before CSV generation.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_export_batch_data', array $batch_data, array $post_ids, array $config, string $context ): array
```

**Example:** (append computed field to each row)

```php
add_filter( 'swift_csv_export_batch_data', 'my_swiftcsv_export_batch_data', 10, 4 );

function my_swiftcsv_export_batch_data( $batch_data, $post_ids, $config, $context ) {
    foreach ( (array) $batch_data as $i => $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }

        // Example: add a computed field.
        $row['my_batch_marker'] = '1';
        $batch_data[ $i ] = $row;
    }

    return $batch_data;
}
```

### Batch / performance

#### `swift_csv_export_batch_size`

Filter the export batch size.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_export_batch_size', int $batch_size, int $total_count, string $post_type, array $config ): int
```

**Example:**

```php
add_filter( 'swift_csv_export_batch_size', 'my_swiftcsv_export_batch_size', 10, 4 );

function my_swiftcsv_export_batch_size( $batch_size, $total_count, $post_type, $config ) {
    // Example: reduce batch size for heavy post types.
    if ( 'product' === $post_type ) {
        return max( 100, min( (int) $batch_size, 500 ) );
    }
    return (int) $batch_size;
}
```

---

## Import Hooks

### Permission and validation

#### `swift_csv_user_can_import`

Filter user permission to perform imports.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_user_can_import', bool $can_import ): bool
```

**Parameters:**

- `$can_import` (`bool`) Default permission based on `current_user_can('import')`.

**Example:** (require custom capability)

```php
add_filter( 'swift_csv_user_can_import', 'my_swiftcsv_import_permission', 10, 1 );

function my_swiftcsv_import_permission( $can_import ) {
    return current_user_can( 'manage_options' ) || current_user_can( 'import_csv' );
}
```

#### `swift_csv_pre_ajax_import`

Filter pre-import validation result.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_pre_ajax_import', bool|WP_Error $result, array $post_data ): bool|WP_Error
```

**Parameters:**

- `$result` (`bool|WP_Error`) Validation result.
- `$post_data` (`array`) POST data from import request.

**Example:** (validate business hours)

```php
add_filter( 'swift_csv_pre_ajax_import', 'my_swiftcsv_business_hours_check', 10, 2 );

function my_swiftcsv_business_hours_check( $result, $post_data ) {
    $hour = (int) date( 'H' );
    if ( $hour < 9 || $hour > 17 ) {
        return new WP_Error( 'business_hours', 'Import only allowed during business hours (9AM-5PM)' );
    }
    return $result;
}
```

### Field preparation and mapping

#### `swift_csv_prepare_import_fields`

Filter prepared meta fields before import.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_prepare_import_fields', array $meta_fields, int $post_id, array $args ): array
```

**Parameters:**

- `$meta_fields` (`array`) Prepared meta fields.
- `$post_id` (`int`) Post ID being imported.
- `$args` (`array`) Context arguments including post_type and context.

**Example:** (process custom field formats)

```php
add_filter( 'swift_csv_prepare_import_fields', 'my_swiftcsv_process_custom_fields', 10, 3 );

function my_swiftcsv_process_custom_fields( $meta_fields, $post_id, $args ) {
    foreach ( $meta_fields as $key => $value ) {
        if ( strpos( $key, 'price_' ) === 0 ) {
            $meta_fields[$key] = floatval( $value );
        }
    }
    return $meta_fields;
}
```

#### `swift_csv_import_phase_map_prepared`

Action fired after field mapping is prepared.

**Type:** action

**Signature:**

```php
do_action( 'swift_csv_import_phase_map_prepared', int $post_id, array $prepared_fields, array $args ): void
```

#### `swift_csv_import_phase_post_persist`

Action fired after post data is persisted.

**Type:** action

**Signature:**

```php
do_action( 'swift_csv_import_phase_post_persist', int $post_id, array $prepared_fields, array $args ): void
```

### Batch processing

#### `swift_csv_import_batch_size`

Filter import batch size.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_import_batch_size', int $batch_size, int $total_rows, array $config ): int
```

**Parameters:**

- `$batch_size` (`int`) Calculated batch size.
- `$total_rows` (`int`) Total rows to process.
- `$config` (`array`) Import configuration.

**Example:** (optimize for server performance)

```php
add_filter( 'swift_csv_import_batch_size', 'my_swiftcsv_optimize_batch_size', 10, 3 );

function my_swiftcsv_optimize_batch_size( $batch_size, $total_rows, $config ) {
    // Reduce batch size for memory-constrained servers
    $memory_limit = ini_get( 'memory_limit' );
    if ( $memory_limit === '128M' ) {
        return min( 5, $batch_size );
    }
    return $batch_size;
}
```

### Logging and diagnostics

#### `swift_csv_max_log_entries`

Filter maximum log entries to store.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_max_log_entries', int $max_entries ): int
```

**Parameters:**

- `$max_entries` (`int`) Default 30.

**Example:** (increase logging for debugging)

```php
add_filter( 'swift_csv_max_log_entries', 'my_swiftcsv_increase_logging', 10, 1 );

function my_swiftcsv_increase_logging( $max_entries ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        return 100;
    }
    return $max_entries;
}
```

### Phased import actions

Swift CSV import exposes a phased model via `do_action`.

- `swift_csv_import_phase_normalize`
- `swift_csv_import_phase_validate`
- `swift_csv_import_phase_map`
- `swift_csv_import_phase_map_prepared`
- `swift_csv_import_phase_post_persist`

These are intended for observability and integrations.

#### `swift_csv_import_phase_normalize`

**Type:** action

**Signature (current implementation):**

```php
do_action( 'swift_csv_import_phase_normalize', array $filtered_data, array $context ): void
```

**Example:**

```php
add_action( 'swift_csv_import_phase_normalize', 'my_swiftcsv_phase_normalize', 10, 2 );

function my_swiftcsv_phase_normalize( $filtered_data, $context ) {
    error_log( '[Swift CSV] normalize phase for post_type=' . (string) ( $context['post_type'] ?? '' ) );
}
```

#### `swift_csv_import_phase_validate`

**Type:** action

**Signature (current implementation):**

```php
do_action( 'swift_csv_import_phase_validate', array $row_validation, array $row_context, array $context ): void
```

**Example:**

```php
add_action( 'swift_csv_import_phase_validate', 'my_swiftcsv_phase_validate', 10, 3 );

function my_swiftcsv_phase_validate( $row_validation, $row_context, $context ) {
    if ( ! empty( $row_validation['errors'] ) ) {
        error_log( '[Swift CSV] validation errors: ' . implode( '; ', (array) $row_validation['errors'] ) );
    }
}
```

#### `swift_csv_import_phase_map`

**Type:** action

**Signature (current implementation):**

```php
do_action( 'swift_csv_import_phase_map', array $collected_fields, array $headers, array $data ): void
```

**Example:**

```php
add_action( 'swift_csv_import_phase_map', 'my_swiftcsv_phase_map', 10, 3 );

function my_swiftcsv_phase_map( $collected_fields, $headers, $data ) {
    // Example: observe field mapping.
    if ( isset( $collected_fields['meta_fields'] ) ) {
        error_log( '[Swift CSV] mapped meta keys: ' . implode( ',', array_keys( (array) $collected_fields['meta_fields'] ) ) );
    }
}
```

#### `swift_csv_import_phase_map_prepared`

**Type:** action

**Signature (current implementation):**

```php
do_action( 'swift_csv_import_phase_map_prepared', int $post_id, array $prepared_meta_fields, array $prepare_args ): void
```

#### `swift_csv_import_phase_post_persist`

**Type:** action

**Signature (current implementation):**

```php
do_action( 'swift_csv_import_phase_post_persist', int $post_id, array $prepared_meta_fields, array $prepare_args ): void
```

### Row validation and normalization

#### `swift_csv_import_row_validation`

Row-level validation filter.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_import_row_validation', array $row_validation, array $row_context, array $context ): array
```

**Example:** (require `post_title` for creates)

```php
add_filter( 'swift_csv_import_row_validation', 'my_swiftcsv_validate_row', 10, 3 );

function my_swiftcsv_validate_row( $row_validation, $row_context, $context ) {
    $post_fields = (array) ( $row_context['post_fields_from_csv'] ?? [] );
    $is_update = ! empty( $row_context['is_update'] );

    if ( ! $is_update && empty( $post_fields['post_title'] ) ) {
        $row_validation['valid'] = false;
        $row_validation['errors'][] = 'post_title is required for new posts.';
    }

    return $row_validation;
}
```

#### `swift_csv_import_data_filter`

Normalize/filter raw parsed row data.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_import_data_filter', array $filtered_data, array $original_data, array $context ): array
```

**Example:** (trim fields)

```php
add_filter( 'swift_csv_import_data_filter', 'my_swiftcsv_import_data_filter', 10, 3 );

function my_swiftcsv_import_data_filter( $filtered_data, $original_data, $context ) {
    $data = (array) ( $filtered_data['data'] ?? [] );
    foreach ( $data as $i => $value ) {
        $data[ $i ] = is_string( $value ) ? trim( $value ) : $value;
    }

    $filtered_data['data'] = $data;
    return $filtered_data;
}
```

### Field mapping and post-persist

#### `swift_csv_import_field_mapping`

Filter collected meta/taxonomy fields derived from headers and row values.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_import_field_mapping', array $collected_fields, array $headers, array $data, array $allowed_post_fields ): array
```

**Example:** (rename a meta key)

```php
add_filter( 'swift_csv_import_field_mapping', 'my_swiftcsv_import_field_mapping', 10, 4 );

function my_swiftcsv_import_field_mapping( $collected_fields, $headers, $data, $allowed_post_fields ) {
    if ( isset( $collected_fields['meta_fields']['old_key'] ) ) {
        $collected_fields['meta_fields']['new_key'] = $collected_fields['meta_fields']['old_key'];
        unset( $collected_fields['meta_fields']['old_key'] );
    }

    return $collected_fields;
}
```

#### `swift_csv_prepare_import_fields`

Prepare meta fields right before the post-persist phase.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_prepare_import_fields', array $meta_fields, int $post_id, array $args ): array
```

**Example:** (sanitize a field)

```php
add_filter( 'swift_csv_prepare_import_fields', 'my_swiftcsv_prepare_import_fields', 10, 3 );

function my_swiftcsv_prepare_import_fields( $meta_fields, $post_id, $args ) {
    if ( isset( $meta_fields['price'] ) ) {
        $meta_fields['price'] = (string) (float) str_replace( ',', '', (string) $meta_fields['price'] );
    }
    return $meta_fields;
}
```

#### `swift_csv_process_custom_fields`

Legacy-compatible action called after field preparation and post-persist.

**Type:** action

**Signature:**

```php
do_action( 'swift_csv_process_custom_fields', int $post_id, array $prepared_meta_fields ): void
```

**Example:** (ACF update)

```php
add_action( 'swift_csv_process_custom_fields', 'my_swiftcsv_process_custom_fields', 10, 2 );

function my_swiftcsv_process_custom_fields( $post_id, $prepared_meta_fields ) {
    // Example: integrate with ACF safely.
    if ( function_exists( 'update_field' ) && isset( $prepared_meta_fields['my_acf_field'] ) ) {
        update_field( 'my_acf_field', $prepared_meta_fields['my_acf_field'], $post_id );
    }
}
```

### Batch / performance

#### `swift_csv_import_batch_size`

Filter import batch size.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_import_batch_size', int $batch_size, int $total_rows, array $config ): int
```

**Example:**

```php
add_filter( 'swift_csv_import_batch_size', 'my_swiftcsv_import_batch_size', 10, 3 );

function my_swiftcsv_import_batch_size( $batch_size, $total_rows, $config ) {
    // Example: very small batches for complex imports.
    if ( isset( $config['post_type'] ) && 'product' === $config['post_type'] ) {
        return max( 1, min( (int) $batch_size, 10 ) );
    }
    return (int) $batch_size;
}
```

---

## Admin / UI Hooks

#### `swift_csv_settings_tabs`

Action fired when rendering settings tabs.

**Type:** action

**Signature:**

```php
do_action( 'swift_csv_settings_tabs', string $tab ): void
```

#### `swift_csv_settings_tabs_content`

Action fired when rendering tab content.

**Type:** action

**Signature:**

```php
do_action( 'swift_csv_settings_tabs_content', string $tab, array $import_results ): void
```

#### `swift_csv_export_form_action`

Filter the form action URL for the export form.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_export_form_action', string $action_url ): string
```

**Parameters:**

- `$action_url` (`string`) Empty by default (uses current page).

**Example:** (redirect to custom handler)

```php
add_filter( 'swift_csv_export_form_action', 'my_swiftcsv_custom_export_handler', 10, 1 );

function my_swiftcsv_custom_export_handler( $action_url ) {
    return 'https://my-api.com/handle-swift-csv-export';
}
```

#### `swift_csv_tools_page_capability`

Filter the capability required to access the Swift CSV admin page.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_tools_page_capability', string $capability ): string
```

**Parameters:**

- `$capability` (`string`) Default `manage_options`.

**Example:** (allow editors to access)

```php
add_filter( 'swift_csv_tools_page_capability', 'my_swiftcsv_tools_capability', 10, 1 );

function my_swiftcsv_tools_capability( $capability ) {
    return 'edit_posts'; // Allow any user who can edit posts
}
```

---

## Feature Flags / Diagnostics

#### `swift_csv_enable_direct_sql_import`

Feature flag to enable Direct SQL import.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_enable_direct_sql_import', bool $enabled ): bool
```

#### `swift_csv_max_log_entries`

Controls how many log entries are stored/displayed.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_max_log_entries', int $max_entries ): int
```

#### `swift_csv_user_can_export`

Filter user permission to perform exports.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_user_can_export', bool $can_export ): bool
```

**Parameters:**

- `$can_export` (`bool`) Default permission based on `current_user_can('export')`.

**Example:** (require custom capability)

```php
add_filter( 'swift_csv_user_can_export', 'my_swiftcsv_export_permission', 10, 1 );

function my_swiftcsv_export_permission( $can_export ) {
    return current_user_can( 'manage_options' ) || current_user_can( 'export_csv' );
}
```

#### `swift_csv_pre_ajax_export`

Filter pre-export validation result.

**Type:** filter

**Signature:**

```php
apply_filters( 'swift_csv_pre_ajax_export', bool|WP_Error $result, array $post_data ): bool|WP_Error
```

**Parameters:**

- `$result` (`bool|WP_Error`) Validation result.
- `$post_data` (`array`) POST data from export request.

**Example:** (validate export limits)

```php
add_filter( 'swift_csv_pre_ajax_export', 'my_swiftcsv_export_limits', 10, 2 );

function my_swiftcsv_export_limits( $result, $post_data ) {
    $user_id = get_current_user_id();
    $today_exports = get_transient( 'swift_csv_exports_' . $user_id ) ?: 0;

    if ( $today_exports >= 10 ) {
        return new WP_Error( 'daily_limit', 'Daily export limit reached (10 exports per day)' );
    }

    return $result;
}
```

---

## Best Practices

1. Validate the `$context` or `$config` before applying changes.
2. Keep hook logic fast; heavy processing should be deferred.
3. Sanitize output and inputs (`sanitize_text_field`, `absint`, etc.).
4. For integrations (ACF/WooCommerce), guard with `function_exists()`.
5. Prefer idempotent logic (a filter might run multiple times per request).
