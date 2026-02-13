# Swift CSV Hooks

This document describes all available hooks in the Swift CSV plugin that developers can use to extend and customize the import/export functionality.

## Table of Contents

- [Export Hooks](#export-hooks)
- [Import Hooks](#import-hooks)
- [Admin Hooks](#admin-hooks)

---

## Export Hooks

### swift_csv_export_columns

Filters the export columns for custom export scope.

**Parameters:**

- `$columns` (array): Empty array for custom columns
- `$post_type` (string): The post type being exported
- `$include_private_meta` (bool): Whether to include private meta fields

**Example:**

```php
add_filter( 'swift_csv_export_columns', 'my_custom_export_columns', 10, 3 );

function my_custom_export_columns( $columns, $post_type, $include_private_meta ) {
    if ( 'product' === $post_type ) {
        return ['product_name', 'price', 'category', 'stock'];
    }
    return $columns;
}
```

### swift_csv_export_headers

Filters the headers before export.

**Parameters:**

- `$headers` (array): Array of header strings
- `$args` (array): Export arguments including post_type

**Example:**

```php
add_filter( 'swift_csv_export_headers', 'my_custom_headers', 10, 2 );

function my_custom_headers( $headers, $args ) {
    // Add custom prefix to headers
    return array_map( function( $header ) {
        return 'my_prefix_' . $header;
    }, $headers );
}
```

### swift_csv_add_additional_headers

Filters headers to add additional fields.

**Parameters:**

- `$headers` (array): Current headers array
- `$post_type` (string): The post type being exported
- `$args` (array): Additional arguments

**Example:**

```php
add_filter( 'swift_csv_add_additional_headers', 'my_additional_headers', 10, 3 );

function my_additional_headers( $headers, $post_type, $args ) {
    // Add custom fields based on post type
    if ( 'post' === $post_type ) {
        $headers[] = 'reading_time';
        $headers[] = 'difficulty_level';
    }
    return $headers;
}
```

### swift_csv_generate_all_headers

Filters complete headers for "All" export scope.

**Parameters:**

- `$headers` (array): Complete headers array
- `$post_type` (string): The post type being exported
- `$include_private_meta` (bool): Whether to include private meta fields

**Example:**

```php
add_filter( 'swift_csv_generate_all_headers', 'my_all_headers_filter', 10, 3 );

function my_all_headers_filter( $headers, $post_type, $include_private_meta ) {
    // Exclude certain meta fields
    $excluded_fields = ['_edit_lock', '_wp_old_slug'];
    return array_diff( $headers, $excluded_fields );
}
```

### swift_csv_export_query_args

Filters the WP_Query arguments for export.

**Parameters:**

- `$query_args` (array): WP_Query arguments
- `$args` (array): Export arguments

**Example:**

```php
add_filter( 'swift_csv_export_query_args', 'my_custom_query_args', 10, 2 );

function my_custom_query_args( $query_args, $args ) {
    // Only export posts from specific category
    if ( 'post' === $args['post_type'] ) {
        $query_args['category_name'] = 'featured';
    }

    // Add custom meta field filter
    $query_args['meta_query'] = array(
        array(
            'key' => 'featured_product',
            'value' => 'yes'
        )
    );

    return $query_args;
}
```

### swift_csv_process_custom_field_value

Filters custom field values during export.

**Parameters:**

- `$value` (string): The field value (empty by default for unknown fields)
- `$header` (string): The field name/header
- `$post_id` (int): The post ID
- `$post_type` (string): The post type

**Example:**

```php
add_filter( 'swift_csv_process_custom_field_value', 'my_custom_field_value', 10, 4 );

function my_custom_field_value( $value, $header, $post_id, $post_type ) {
    // Handle special custom fields
    if ( 'product_price' === $header ) {
        $price = get_post_meta( $post_id, 'product_price', true );
        return '$' . number_format( $price, 2 );
    }

    return $value;
}
```

---

## Import Hooks

### swift_csv_process_custom_fields

Fires when processing custom fields for a post.

**Parameters:**

- `$post_id` (int): The ID of the created/updated post
- `$meta_fields` (array): Array of meta fields to process

**Example:**

```php
add_action( 'swift_csv_process_custom_fields', 'my_custom_field_processing', 10, 2 );

function my_custom_field_processing( $post_id, $meta_fields ) {
    // Process special meta fields
    if ( isset( $meta_fields['special_field'] ) ) {
        // Custom processing logic
        update_post_meta( $post_id, '_processed_special', 'yes' );
    }

    // Log processing
    error_log( '[Swift CSV] Processing custom fields for post ' . $post_id );
}
```

---

## Admin Hooks

### swift_csv_settings_tabs

Fires when rendering settings tabs.

**Parameters:**

- `$tab` (string): Currently active tab

**Example:**

```php
add_action( 'swift_csv_settings_tabs', 'my_custom_tab', 10, 1 );

function my_custom_tab( $tab ) {
    // Add custom tab content
    if ( 'my_tab' === $tab ) {
        echo '<div class="my-custom-tab-content">';
        echo 'Custom tab content here';
        echo '</div>';
    }
}
```

### swift_csv_settings_tabs_content

Fires when rendering tab content.

**Parameters:**

- `$tab` (string): Currently active tab
- `$import_results` (array): Import results data (for import tab)

**Example:**

```php
add_action( 'swift_csv_settings_tabs_content', 'my_tab_content', 10, 2 );

function my_tab_content( $tab, $import_results ) {
    // Add content to specific tabs
    if ( 'export' === $tab ) {
        echo '<p>Custom export instructions here.</p>';
    }

    if ( 'import' === $tab && ! empty( $import_results ) ) {
        echo '<div class="import-summary">';
        echo 'Last import: ' . $import_results['created'] . ' created, ' . $import_results['updated'] . ' updated';
        echo '</div>';
    }
}
```

---

## Hook Usage Examples

### Custom Export Processing

```php
// Add custom export columns for specific post type
add_filter( 'swift_csv_export_columns', 'my_product_columns', 10, 3 );

function my_product_columns( $columns, $post_type, $include_private_meta ) {
    if ( 'product' === $post_type ) {
        return ['sku', 'price', 'stock_quantity', 'weight'];
    }
    return $columns;
}

// Process custom field values
add_filter( 'swift_csv_process_custom_field_value', 'format_product_data', 10, 4 );

function format_product_data( $value, $header, $post_id, $post_type ) {
    if ( 'price' === $header ) {
        return number_format( floatval( $value ), 2 );
    }
    return $value;
}
```

### Custom Import Processing

```php
// Handle special field processing during import
add_action( 'swift_csv_process_custom_fields', 'process_import_data', 10, 2 );

function process_import_data( $post_id, $meta_fields ) {
    // Generate featured image from URL if provided
    if ( isset( $meta_fields['image_url'] ) ) {
        $image_url = esc_url( $meta_fields['image_url'] );
        $image_id = attachment_url_to_postid( $image_url );

        if ( $image_id ) {
            set_post_thumbnail( $post_id, $image_id );
        }
    }

    // Set default category for imported posts
    wp_set_object_terms( $post_id, array( 'imported' ), 'category' );
}
```

### Custom Admin Interface

```php
// Add custom admin tab
add_action( 'swift_csv_settings_tabs', 'add_analytics_tab', 10, 1 );

function add_analytics_tab( $tab ) {
    echo '<a href="#analytics" class="nav-tab ' . ($tab === 'analytics' ? 'nav-tab-active' : '') . '">Analytics</a>';
}

add_action( 'swift_csv_settings_tabs_content', 'show_analytics_content', 10, 2 );

function show_analytics_content( $tab, $import_results ) {
    if ( 'analytics' === $tab ) {
        echo '<div class="analytics-dashboard">';
        // Display import/export statistics
        echo '</div>';
    }
}
```

## Notes

- All hooks are available in the current AJAX-based implementation
- Batch processing is handled automatically by the plugin
- Use `WP_DEBUG` to see hook execution in debug logs
- Custom fields are automatically detected and processed
  'key' => '\_export_enabled',
  'value' => '1'
  )
  );
  // Order by custom field for products
  if ( 'product' === $args['post_type'] ) {
  $query_args['orderby'] = 'meta_value_num';
  $query_args['meta_key'] = '\_price';
  $query_args['order'] = 'DESC';
  }

          return $query_args;

    }

````

---

### swift_csv_export_headers

Filters the CSV column headers.

**Parameters:**
- `$headers` (array): CSV column headers
- `$args` (array): Export arguments

**Example:**
```php
add_filter( 'swift_csv_export_headers', 'my_custom_headers', 10, 2 );

function my_custom_headers( $headers, $args ) {
    // Add custom columns for products
    if ( 'product' === $args['post_type'] ) {
        $headers[] = 'cf_sku';
        $headers[] = 'cf_price';
        $headers[] = 'cf_stock_status';
    }

    // Remove unnecessary columns
    $headers = array_diff( $headers, array( 'post_content_filtered' ) );

    // Reorder headers
    $desired_order = array( 'ID', 'post_title', 'cf_sku', 'cf_price', 'post_content' );
    $headers = array_merge( array_intersect( $desired_order, $headers ), array_diff( $headers, $desired_order ) );

    return $headers;
}
````

---

### swift_csv_export_row

Filters a single row data before writing to CSV.

**Parameters:**

- `$row` (array): Row data to be exported
- `$post_id` (int): Post ID
- `$args` (array): Export arguments

**Example:**

```php
add_filter( 'swift_csv_export_row', 'my_custom_row_data', 10, 3 );

function my_custom_row_data( $row, $post_id, $args ) {
    // Format price for products
    if ( 'product' === $args['post_type'] ) {
        $price_index = array_search( 'cf_price', array_keys( $row ) );
        if ( $price_index !== false ) {
            $row[$price_index] = number_format( floatval( $row[$price_index] ), 2 );
        }
    }

    // Add calculated fields
    $post = get_post( $post_id );
    $row[] = get_permalink( $post_id ); // Add permalink
    $row[] = get_the_author_meta( 'display_name', $post->post_author ); // Add author name

    // Sanitize data
    $row = array_map( function( $value ) {
        return is_string( $value ) ? sanitize_text_field( $value ) : $value;
    }, $row );

    return $row;
}
```

---

### swift_csv_after_export

Fires after completing the export process.

**Parameters:**

- `$file_path` (string): Path to the generated CSV file
- `$args` (array): Export arguments

**Example:**

```php
add_action( 'swift_csv_after_export', 'my_export_cleanup', 10, 2 );

function my_export_cleanup( $file_path, $args ) {
    // Log successful export
    error_log( 'Export completed: ' . $file_path );

    // Send notification email
    wp_mail( 'admin@example.com', 'Export Complete', 'Your CSV export is ready: ' . basename( $file_path ) );

    // Create backup copy
    $backup_path = dirname( $file_path ) . '/backup_' . basename( $file_path );
    copy( $file_path, $backup_path );

    // Update export statistics
    update_option( 'last_export_time', current_time( 'mysql' ) );
}
```

---

## Import Hooks

### swift_csv_before_import

Fires before starting the import process.

**Parameters:**

- `$file_path` (string): Path to the CSV file being imported
- `$args` (array): Import arguments

**Example:**

```php
add_action( 'swift_csv_before_import', 'my_import_preparation', 10, 2 );

function my_import_preparation( $file_path, $args ) {
    // Validate file
    if ( ! file_exists( $file_path ) ) {
        throw new Exception( 'Import file not found' );
    }

    // Check file size
    $file_size = filesize( $file_path );
    if ( $file_size > 10 * 1024 * 1024 ) { // 10MB limit
        wp_die( 'File too large for import' );
    }

    // Create backup of existing data
    if ( 'product' === $args['post_type'] ) {
        $backup_file = wp_upload_dir()['path'] . '/product_backup_' . date( 'Y-m-d_H-i-s' ) . '.json';
        // Create backup logic here
    }

    // Set import configuration
    update_option( 'import_start_time', current_time( 'mysql' ) );
    update_option( 'import_status', 'processing' );
}
```

---

### swift_csv_before_process_row

Fires before processing each row during import.

**Parameters:**

- `$row` (array): Row data from CSV
- `$row_num` (int): Row number (0-indexed)
- `$args` (array): Import arguments

**Example:**

```php
add_action( 'swift_csv_before_process_row', 'my_row_validation', 10, 3 );

function my_row_validation( $row, $row_num, $args ) {
    // Skip empty rows
    if ( empty( array_filter( $row ) ) ) {
        throw new Exception( 'Row ' . ($row_num + 2) . ' is empty' );
    }

    // Validate required fields for products
    if ( 'product' === $args['post_type'] ) {
        $title_index = array_search( 'post_title', array_keys( $row ) );
        if ( empty( $row[$title_index] ) ) {
            throw new Exception( 'Row ' . ($row_num + 2) . ': Product title is required' );
        }
    }

    // Log processing start
    error_log( 'Processing row ' . ($row_num + 2) . ' for ' . $args['post_type'] );
}
```

---

### swift_csv_import_row

Filters the row data before processing.

**Parameters:**

- `$row` (array): Row data from CSV
- `$row_num` (int): Row number (0-indexed)
- `$args` (array): Import arguments
  **Return:** Modified row data

**Example:**

```php
add_filter( 'swift_csv_import_row', 'my_import_row_filter', 10, 3 );

function my_import_row_filter( $row, $row_num, $args ) {
    // Auto-generate slug from title if empty
    if ( empty( $row['post_name'] ) && ! empty( $row['post_title'] ) ) {
        $row['post_name'] = sanitize_title( $row['post_title'] );
    }

    // Format price values
    if ( isset( $row['cf_price'] ) ) {
        $row['cf_price'] = floatval( str_replace( ',', '', $row['cf_price'] ) );
    }

    // Set default values
    $title_index = array_search( 'post_title', array_keys( $row ) );
    if ( $title_index !== false && empty( $row[$title_index] ) ) {
        $row[$title_index] = 'Untitled ' . ($row_num + 2);
    }

    return $row;
}
```

---

### swift_csv_after_process_row

Fires after processing each row during import.

**Parameters:**

- `$row` (array): Row data from CSV
- `$row_num` (int): Row number (0-indexed)
- `$result` (array): Processing result containing success status and post ID
- `$args` (array): Import arguments

**Example:**

```php
add_action( 'swift_csv_after_process_row', 'my_after_row_handler', 10, 4 );

function my_after_row_handler( $row, $row_num, $result, $args ) {
    if ( $result['created'] ) {
        error_log( "Row " . ($row_num + 2) . ": Successfully created post {$result['post_id']}" );

        // Do additional processing for new posts
        if ( 'product' === $args['post_type'] ) {
            update_post_meta( $result['post_id'], '_import_date', current_time( 'mysql' ) );
        }
    } elseif ( $result['updated'] ) {
        error_log( "Row " . ($row_num + 2) . ": Successfully updated post {$result['post_id']}" );
    } else {
        error_log( "Row " . ($row_num + 2) . ": Failed to process - {$result['message']}" );
    }
}
```

---

### swift_csv_after_import

Fires after completing the import process.

**Parameters:**

- `$file_path` (string): Path to the imported CSV file
- `$results` (array): Import results including success/failure counts
- `$args` (array): Import arguments

**Example:**

```php
add_action( 'swift_csv_after_import', 'my_import_completion', 10, 3 );

function my_import_completion( $file_path, $results, $args ) {
    // Log import results
    error_log( 'Import completed: ' . $results['imported'] . ' imported, ' . $results['updated'] . ' updated' );

    // Send import report
    $message = sprintf(
        'Import completed for %s. Results: %d imported, %d updated, %d errors',
        $args['post_type'],
        $results['imported'],
        $results['updated'],
        count( $results['errors'] )
    );
    wp_mail( 'admin@example.com', 'Import Complete', $message );

    // Update statistics
    update_option( 'last_import_results', $results );
    update_option( 'import_status', 'completed' );

    // Trigger post-import processes
    if ( 'product' === $args['post_type'] ) {
        wp_schedule_single_event( time(), 'update_product_inventory' );
    }
}
```

---

## Best Practices

1. **Always validate parameters** before using them in your hook functions
2. **Use proper error handling** with try-catch blocks where appropriate
3. **Log important events** for debugging and monitoring
4. **Clean up resources** in after hooks to prevent memory leaks
5. **Check user capabilities** before performing sensitive operations
6. **Use nonces and security checks** when processing user data

## Version History

- **0.9.5**: Initial implementation of all hooks
- Future versions may add additional hooks based on user feedback
