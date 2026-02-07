# Swift CSV Hooks

This document describes all available hooks in the Swift CSV plugin that developers can use to extend and customize the import/export functionality.

## Table of Contents

- [Export Hooks](#export-hooks)
- [Import Hooks](#import-hooks)

---

## Export Hooks

### swift_csv_export_columns ⭐

**Most Important Hook** - Allows developers to define custom export columns and their order when "Custom" export scope is selected.

**Parameters:**

- `$headers` (array): Default headers array (empty)
- `$post_type` (string): Current post type being exported
- `$include_private_meta` (bool): Whether to include private meta fields

**Return Value:**

- `(array)` Custom headers array for export

**Example:**

```php
add_filter( 'swift_csv_export_columns', 'my_custom_export_columns', 10, 3 );

function my_custom_export_columns( $headers, $post_type, $include_private_meta ) {
    // Only apply to specific post type
    if ( $post_type === 'product' ) {
        return [
            'post_title',
            'post_excerpt',
            'cf_price',                // Custom field (cf_ prefix)
            'cf_sku',                   // Custom field (cf_ prefix)
            'tax_product_category',    // Taxonomy (tax_ prefix)
            'tax_product_tag',         // Taxonomy (tax_ prefix)
            'post_date'
        ];
    }

    // For posts, include specific custom fields and taxonomies
    if ( $post_type === 'post' ) {
        return [
            'post_title',
            'post_excerpt',
            'cf_featured_image',       // Custom field (cf_ prefix)
            'cf_reading_time',         // Custom field (cf_ prefix)
            'tax_category',            // Taxonomy (tax_ prefix)
            'tax_post_tag',            // Taxonomy (tax_ prefix)
            'post_date'
        ];
    }

    return $headers; // Return default if no custom logic
}
```

**Usage Notes:**

- This hook is triggered when "Custom" export scope is selected
- If no custom implementation exists, the system falls back to "Basic Fields"
- The order of headers in the returned array determines the CSV column order
- **ID column is always output as the first column. Any ID entry in your array will be ignored and moved to the first position.**
- **Custom fields**: Use `cf_` prefix for standard custom fields (e.g., `cf_price`, `cf_sku`)
- **Taxonomies**: Use `tax_` prefix for taxonomies (e.g., `tax_category`, `tax_post_tag`, `tax_product_category`)
- **Prefixes are required**: Swift CSV uses these prefixes to properly handle different field types during import/export
- Custom field names should match the actual field keys in WordPress

---

### swift_csv_process_field_value 🔧

**Field Processing Hook** - Allows developers to customize how individual field values are processed during export.

**Parameters:**

- `$value` (mixed): The field value to be processed
- `$header` (string): The field header/column name
- `$post_id` (int): The current post ID being processed
- `$post_type` (string): Current post type

**Return Value:**

- `(mixed)` Processed field value

**Example:**

```php
add_filter( 'swift_csv_process_field_value', 'my_custom_field_processing', 10, 4 );

function my_custom_field_processing( $value, $header, $post_id, $post_type ) {
    // Custom processing for specific fields
    if ( $header === 'cf_price' ) {
        // Format price with currency symbol
        return '$' . number_format( (float) $value, 2 );
    }

    if ( $header === 'cf_featured_image' ) {
        // Return image URL instead of ID
        return wp_get_attachment_url( (int) $value );
    }

    return $value; // Return original value for other fields
}
```

---

### swift_csv_before_export

Fires before starting the export process.

**Parameters:**

- `$args` (array): Export arguments including post_type and posts_per_page

**Example:**

```php
add_action( 'swift_csv_before_export', 'my_custom_export_logic', 10, 1 );

function my_custom_export_logic( $args ) {
    // Log export start
    error_log( 'Starting export for ' . $args['post_type'] . ' with ' . $args['posts_per_page'] . ' items' );

    // Increase memory limit for large exports
    if ( $args['posts_per_page'] > 1000 ) {
        ini_set( 'memory_limit', '512M' );
    }

    // Custom logic based on post type
    if ( 'product' === $args['post_type'] ) {
        // Prepare product-specific export settings
        update_option( 'product_export_mode', 'full' );
    }
}
```

---

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
            'key' => '_export_enabled',
            'value' => '1'
        )
    );

    // Order by custom field for products
    if ( 'product' === $args['post_type'] ) {
        $query_args['orderby'] = 'meta_value_num';
        $query_args['meta_key'] = '_price';
        $query_args['order'] = 'DESC';
    }

    return $query_args;
}
```

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
```

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
