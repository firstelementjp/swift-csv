<?php
/**
 * Ajax-based CSV Import - No transactions, no locks
 */

add_action('wp_ajax_swift_csv_ajax_import', 'swift_csv_ajax_import_handler');
add_action('wp_ajax_nopriv_swift_csv_ajax_import', 'swift_csv_ajax_import_handler');
add_action('wp_ajax_swift_csv_ajax_upload', 'swift_csv_ajax_upload_handler');
add_action('wp_ajax_nopriv_swift_csv_ajax_upload', 'swift_csv_ajax_upload_handler');

function swift_csv_ajax_upload_handler() {
    if (!isset($_FILES['csv_file'])) {
        wp_send_json(['error' => 'No file uploaded']);
        return;
    }
    
    $file = $_FILES['csv_file'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        wp_send_json(['error' => 'Upload error: ' . $file['error']]);
        return;
    }
    
    if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
        wp_send_json(['error' => 'File too large']);
        return;
    }
    
    // Create temp file
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/swift-csv-temp';
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }
    
    $temp_file = $temp_dir . '/ajax-import-' . time() . '.csv';
    
    if (move_uploaded_file($file['tmp_name'], $temp_file)) {
        wp_send_json(['file_path' => $temp_file]);
    } else {
        wp_send_json(['error' => 'Failed to save file']);
    }
}

function swift_csv_ajax_import_handler() {
    global $wpdb;
    
    // Disable locks
    $wpdb->query('SET SESSION autocommit = 1');
    $wpdb->query('SET SESSION innodb_lock_wait_timeout = 1');
    
    $start_row = intval($_POST['start_row'] ?? 0);
    $batch_size = 10;
    $file_path = $_POST['file_path'] ?? '';
    $post_type = $_POST['post_type'] ?? 'post';
    $update_existing = $_POST['update_existing'] ?? '0';
    
    if (empty($file_path) || !file_exists($file_path)) {
        wp_send_json(['error' => 'File not found']);
        return;
    }
    
    // Read CSV properly handling quoted fields with newlines
    $csv_content = file_get_contents($file_path);
    $csv_content = str_replace(["\r\n", "\r"], "\n", $csv_content); // Normalize line endings
    
    // Parse CSV line by line to handle quoted fields with newlines
    $lines = [];
    $current_line = '';
    $in_quotes = false;
    
    foreach (explode("\n", $csv_content) as $line) {
        // Count quotes to determine if we're inside a quoted field
        $quote_count = substr_count($line, '"');
        
        if ($in_quotes) {
            $current_line .= "\n" . $line;
        } else {
            $current_line = $line;
        }
        
        // Toggle quote state (odd number of quotes means we're inside quotes)
        if ($quote_count % 2 === 1) {
            $in_quotes = !$in_quotes;
        }
        
        // Only add line if we're not inside quotes
        if (!$in_quotes) {
            $lines[] = $current_line;
            $current_line = '';
        }
    }
    
    // Auto-detect delimiter
    $first_line = $lines[0] ?? '';
    $delimiters = [',', ';', "\t"];
    $delimiter = ',';
    
    foreach ($delimiters as $delim) {
        if (substr_count($first_line, $delim) > substr_count($first_line, $delimiter)) {
            $delimiter = $delim;
        }
    }
    
    $debug_logging = (defined('SWIFT_CSV_DEBUG') && SWIFT_CSV_DEBUG) || (defined('WP_DEBUG') && WP_DEBUG);
    $log = function( $message ) use ( $debug_logging ) {
        if ( $debug_logging ) {
            error_log( $message );
        }
    };

    $log("Detected delimiter: '$delimiter'");
    $log("Total lines after proper parsing: " . count($lines));
    
    $headers = str_getcsv(array_shift($lines), $delimiter);
    $log("Headers: " . print_r($headers, true));

    $allowed_post_fields = [
        'post_title',
        'post_content',
        'post_excerpt',
        'post_status',
        'post_name',
        'post_date',
        'post_date_gmt',
        'post_modified',
        'post_modified_gmt',
        'post_author',
        'post_parent',
        'menu_order',
        'guid',
        'comment_status',
        'ping_status',
    ];

    $id_col = array_search('ID', $headers, true);
    if ($id_col !== 0) {
        wp_send_json(['error' => 'Invalid CSV format: ID column must be the first column']);
        return;
    }
    
    $total_rows = count($lines);
    $log("Total data rows: $total_rows");
    
    $processed = 0;
    $errors = 0;
    
    // Process small batch
    $log("Starting batch processing from row $start_row to " . min($start_row + $batch_size, $total_rows));
    
    for ($i = $start_row; $i < min($start_row + $batch_size, $total_rows); $i++) {
        if (empty(trim($lines[$i]))) {
            $log("Skipping empty line $i");
            continue;
        }
        
        $data = str_getcsv($lines[$i], $delimiter);
        
        // First check if this looks like an ID row (first column is numeric ID)
        $first_col = $data[0] ?? '';
        if (is_numeric($first_col) && strlen($first_col) <= 6) {
            $log("Found ID row: $first_col - processing normally");
            // This is normal - most rows have ID in first column
            // Don't skip - process the actual data
        } else {
            $log("Unexpected first column: '$first_col' - might be header or malformed data");
            // Continue processing anyway
        }
        
        $post_id_from_csv = $first_col;

        // Collect post fields from CSV (header-driven)
        $post_fields_from_csv = [];
        for ($j = 0; $j < count($headers); $j++) {
            $header = trim((string) $headers[$j]);
            if ($header === '' || $header === 'ID') {
                continue;
            }
            if (!in_array($header, $allowed_post_fields, true)) {
                continue;
            }
            if (!array_key_exists($j, $data)) {
                continue;
            }
            $value = (string) $data[$j];
            if ($value === '') {
                continue;
            }
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
                $value = str_replace('""', '"', $value);
            }
            $post_fields_from_csv[$header] = $value;
        }
        
        // Check for existing post by CSV ID (only if update_existing is checked)
        $existing_post_id = null;
        $is_update = false;
        
        if ($update_existing === '1' && !empty($post_id_from_csv)) {
            // Use original Swift CSV logic for finding existing posts
            $existing_post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = %s 
                 AND ID = %d 
                 LIMIT 1",
                $post_type,
                $post_id_from_csv
            ));
            
            if ($existing_post_id) {
                $log("Found existing post: $existing_post_id for CSV ID: $post_id_from_csv");
                $post_id = $existing_post_id;
                $is_update = true;
            } else {
                $log("No existing post found for CSV ID: $post_id_from_csv - skipping in update mode");
                continue;
            }
        } else {
            $log("Update existing is disabled or no CSV ID - creating new post");
        }
        
        // Validation
        if ($update_existing !== '1') {
            if (empty($post_fields_from_csv['post_title'])) {
                $log("Skipping row $i - no post_title for insert");
                continue;
            }
        }
        
        try {
            // Direct SQL insert or update (update only fields provided by CSV)
            $post_data = [];

            if ($is_update) {
                // Update only provided post fields
                foreach ($post_fields_from_csv as $key => $value) {
                    $post_data[$key] = $value;
                }
                // Keep modification timestamps consistent
                $post_data['post_modified'] = current_time('mysql');
                $post_data['post_modified_gmt'] = current_time('mysql', true);
            } else {
                // Insert with defaults + provided values
                $post_data = [
                    'post_author' => (int) ( $post_fields_from_csv['post_author'] ?? (get_current_user_id() ?: 1) ),
                    'post_date' => $post_fields_from_csv['post_date'] ?? current_time('mysql'),
                    'post_date_gmt' => $post_fields_from_csv['post_date_gmt'] ?? current_time('mysql', true),
                    'post_content' => $post_fields_from_csv['post_content'] ?? '',
                    'post_title' => $post_fields_from_csv['post_title'] ?? '',
                    'post_excerpt' => $post_fields_from_csv['post_excerpt'] ?? '',
                    'post_status' => $post_fields_from_csv['post_status'] ?? 'publish',
                    'post_name' => $post_fields_from_csv['post_name'] ?? sanitize_title( (string) ($post_fields_from_csv['post_title'] ?? '') ),
                    'post_type' => $post_type,
                    'comment_status' => $post_fields_from_csv['comment_status'] ?? 'closed',
                    'ping_status' => $post_fields_from_csv['ping_status'] ?? 'closed',
                    'post_modified' => current_time('mysql'),
                    'post_modified_gmt' => current_time('mysql', true),
                    'post_parent' => (int) ( $post_fields_from_csv['post_parent'] ?? 0 ),
                    'menu_order' => (int) ( $post_fields_from_csv['menu_order'] ?? 0 ),
                    'post_mime_type' => '',
                    'comment_count' => 0
                ];
            }
            
            if ($is_update) {
                if (empty($post_data)) {
                    $log("No post fields to update for post ID $post_id");
                    $result = 0;
                } else {
                    $post_data_formats = [];
                    foreach (array_keys($post_data) as $key) {
                        $post_data_formats[] = in_array($key, ['post_author', 'post_parent', 'menu_order'], true) ? '%d' : '%s';
                    }
                    $result = $wpdb->update(
                        $wpdb->posts,
                        $post_data,
                        ['ID' => $post_id],
                        $post_data_formats,
                        ['%d']
                    );
                }
                $log("Updated post: $post_id");
            } else {
                $post_data_formats = [];
                foreach (array_keys($post_data) as $key) {
                    $post_data_formats[] = in_array($key, ['post_author', 'post_parent', 'menu_order'], true) ? '%d' : '%s';
                }
                $result = $wpdb->insert($wpdb->posts, $post_data, $post_data_formats);
                if ($result !== false) {
                    $post_id = $wpdb->insert_id;
                    $log("Created new post: $post_id");
                }
            }
            
            if ($result !== false) {
                $processed++;
                
                // Update GUID for new posts
                if (!$is_update) {
                    $wpdb->update(
                        $wpdb->posts,
                        ['guid' => get_permalink($post_id)],
                        ['ID' => $post_id],
                        ['%s'],
                        ['%d']
                    );
                }
                
                // Process custom fields and taxonomies like original Swift CSV
                $log("Processing custom fields for post ID $post_id");
                $meta_fields = [];
                $taxonomies = [];
                $taxonomy_term_ids = []; // Store term_ids for reuse in ACF fields
                $acf_field_keys = []; // Map: field_name => field_XXXX key (from CSV cf__field)
                $acf_field_names = []; // Set of field names that have cf__<field> (used to prevent cf_<field> override)
                $normalize_field_name = function( $name ) {
                    $name = trim( (string) $name );
                    $name = preg_replace( '/^\xEF\xBB\xBF/', '', $name );
                    $name = preg_replace( '/[\x00-\x1F\x7F]/', '', $name );
                    return trim( $name );
                };

                for ( $j = 0; $j < count( $headers ); $j++ ) {
                    $header_name = $headers[ $j ] ?? '';
                    $header_name_normalized = $normalize_field_name( $header_name );
                    if ( $header_name_normalized === '' || $header_name_normalized === 'ID' ) {
                        continue;
                    }
                    if ( in_array( $header_name_normalized, $allowed_post_fields, true ) ) {
                        continue;
                    }

                    if ( empty( trim( $data[ $j ] ?? '' ) ) ) {
                        continue;
                    }

                    $meta_value = $data[ $j ];

                    if ( strpos( $header_name_normalized, 'cf__' ) === 0 ) {
                        $field_name = $normalize_field_name( substr( $header_name_normalized, 4 ) );
                        $field_key = trim( (string) $meta_value );
                        if ( str_starts_with( $field_key, '"' ) && str_ends_with( $field_key, '"' ) ) {
                            $field_key = substr( $field_key, 1, -1 );
                        } elseif ( str_starts_with( $field_key, "'" ) && str_ends_with( $field_key, "'" ) ) {
                            $field_key = substr( $field_key, 1, -1 );
                        }
                        $field_key = preg_replace( '/[\x00-\x1F\x7F]/', '', $field_key );
                        $field_key = trim( $field_key );

                        if ( $field_name !== '' && strpos( $field_key, 'field_' ) === 0 ) {
                            $acf_field_keys[ $field_name ] = $field_key;
                            $acf_field_names[ $field_name ] = true;
                            $meta_fields[ '_' . $field_name ] = $field_key;
                            $log( "Stored ACF field key: _{$field_name} = {$field_key}" );
                        }
                    }
                }
                
                for ($j = 0; $j < count($headers); $j++) {
                    $header_name = $headers[ $j ] ?? '';
                    $header_name_normalized = $normalize_field_name( $header_name );
                    if ( $header_name_normalized === '' || $header_name_normalized === 'ID' ) {
                        continue;
                    }
                    if ( in_array( $header_name_normalized, $allowed_post_fields, true ) ) {
                        continue;
                    }
                    
                    if (empty(trim($data[$j] ?? ''))) {
                        continue; // Skip empty fields
                    }

                    $meta_value = $data[$j];
                    $log("Processing header: '$header_name' with value: '$meta_value'");

                    // Do not store post fields as meta
                    if ( in_array( $header_name_normalized, $allowed_post_fields, true ) ) {
                        continue;
                    }
                    
                    // Check if this is a taxonomy field (tax_ prefix ONLY, not cf_ fields)
                    if (strpos($header_name_normalized, 'tax_') === 0) {
                        // Handle taxonomy (pipe-separated) - this is for article-taxonomy relationship
                        $terms = array_map('trim', explode('|', $meta_value));
                        // Store by actual taxonomy name (without tax_ prefix)
                        $taxonomy_name = substr($header_name_normalized, 4); // Remove 'tax_'
                        $taxonomies[$taxonomy_name] = $terms;
                        
                        // Extract taxonomy name from header (remove tax_ prefix)
                        if (taxonomy_exists($taxonomy_name)) {
                            // Get term_ids for reuse in ACF fields
                            $term_ids = [];
                            foreach ($terms as $term_name) {
                                if (!empty($term_name)) {
                                    // Find existing term by name in the specific taxonomy
                                    $term = get_term_by('name', $term_name, $taxonomy_name);
                                    if ($term) {
                                        $term_ids[] = $term->term_id;
                                        $log("Found existing term '$term_name' (ID: {$term->term_id}) in taxonomy '$taxonomy_name'");
                                    } else {
                                        $log("Term '$term_name' not found in taxonomy '$taxonomy_name' - skipping");
                                    }
                                }
                            }
                            if (!empty($term_ids)) {
                                $taxonomy_term_ids[$taxonomy_name] = $term_ids;
                                $log("Stored term_ids for taxonomy '$taxonomy_name': [" . implode(', ', $term_ids) . "]");
                            }
                        } else {
                            $log("Taxonomy '$taxonomy_name' does not exist - skipping field '$header_name'");
                        }
                        
                        $log("Taxonomy field: '$header_name' with terms: " . implode(', ', $terms));
                    } else {
                        // Handle ACF value columns (acf_<field>)
                        if (strpos($header_name_normalized, 'acf_') === 0) {
                            $field_name = $normalize_field_name( substr($header_name_normalized, 4) );
                            $field_key = $acf_field_keys[$field_name] ?? '';
                            if (is_string($field_key)) {
                                $field_key = preg_replace( '/[\x00-\x1F\x7F]/', '', $field_key );
                                $field_key = trim($field_key);
                            }
                            $log("ACF value column detected: {$field_name} (field_key=" . ($field_key ?: 'missing') . ")");

                            // If field_key is missing/invalid, ignore this ACF column.
                            if ( ! $field_key || strpos( $field_key, 'field_' ) !== 0 ) {
                                continue;
                            }

                            $field_config = $wpdb->get_var($wpdb->prepare(
                                "SELECT post_content FROM {$wpdb->posts} 
                                 WHERE post_name = %s 
                                 AND post_type = 'acf-field'",
                                $field_key
                            ));

                            $field_data = $field_config ? maybe_unserialize($field_config) : null;
                            $field_type = is_array($field_data) && isset($field_data['type']) ? $field_data['type'] : '';
                            $taxonomy = is_array($field_data) && isset($field_data['taxonomy']) ? $field_data['taxonomy'] : '';

                            if ($field_type) {
                                $log("ACF field resolved: {$field_name} type={$field_type} taxonomy={$taxonomy}");
                            } else {
                                $log("ACF field definition not found for {$field_name} (field_key={$field_key})");
                                continue;
                            }

                            switch ($field_type) {
                                case 'taxonomy':
                                    $term_names = array_map('trim', explode('|', (string)$meta_value));
                                    $term_ids = [];
                                    foreach ($term_names as $term_name) {
                                        if ($term_name === '') {
                                            continue;
                                        }
                                        $term = get_term_by('name', $term_name, $taxonomy);
                                        if ($term && !is_wp_error($term)) {
                                            $term_ids[] = (string) $term->term_id;
                                        }
                                    }
                                    $meta_fields[$field_name] = maybe_serialize($term_ids);
                                    break;

                                case 'checkbox':
                                    $values = strpos((string)$meta_value, '|') !== false
                                        ? array_map('trim', explode('|', (string)$meta_value))
                                        : [trim((string)$meta_value)];
                                    $values = array_values(array_filter($values, function($v) { return $v !== ''; }));
                                    $meta_fields[$field_name] = maybe_serialize($values);
                                    break;

                                case 'radio':
                                case 'text':
                                case 'textarea':
                                    $value = (string)$meta_value;
                                    if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                                        $value = substr($value, 1, -1);
                                        $value = str_replace('""', '"', $value);
                                    }
                                    $meta_fields[$field_name] = $value;
                                    break;

                                default:
                                    $meta_fields[$field_name] = (string)$meta_value;
                                    break;
                            }
                        } else {
                            // Handle regular custom field (cf_<field> => <field>)
                            $clean_field_name = $header_name_normalized;
                            if (strpos($header_name_normalized, 'cf_') === 0) {
                                $clean_field_name = substr($header_name_normalized, 3); // Remove cf_
                            }

                            // Prevent overriding ACF fields (e.g. documents/jobtype/pay) with cf_ columns.
                            if (isset($acf_field_names[$clean_field_name])) {
                                continue;
                            }
                            $meta_fields[$clean_field_name] = (string)$meta_value;
                            $log("Custom field: '$clean_field_name' = '$meta_value'");
                        }
                    }
                }
                
                // Process taxonomies
                foreach ($taxonomies as $taxonomy => $terms) {
                    if (!empty($terms)) {
                        // Use WordPress API to set term relationships (handles tt_id and counts)
                        $term_ids = [];
                        foreach ($terms as $term_name) {
                            $term_name = trim((string) $term_name);
                            if ($term_name === '') {
                                continue;
                            }

                            $term = get_term_by('name', $term_name, $taxonomy);
                            if (!$term) {
                                $created = wp_insert_term($term_name, $taxonomy);
                                if (is_wp_error($created)) {
                                    error_log("Failed to create term '$term_name' in taxonomy '$taxonomy'");
                                    continue;
                                }
                                $term_ids[] = (int) $created['term_id'];
                            } else {
                                $term_ids[] = (int) $term->term_id;
                            }
                        }

                        wp_set_post_terms($post_id, $term_ids, $taxonomy, false);
                    }
                }
                
                // Process custom fields with multi-value support
                foreach ($meta_fields as $key => $value) {
                    // Skip empty values
                    if ($value === '' || $value === null) {
                        continue;
                    }

                    // Always replace existing meta for this key to ensure update works even if meta row doesn't exist.
                    $wpdb->query($wpdb->prepare(
                        "DELETE FROM {$wpdb->postmeta} 
                         WHERE post_id = %d 
                         AND meta_key = %s",
                        $post_id,
                        $key
                    ));
                    
                    // Handle multi-value custom fields (pipe-separated)
                    if (strpos($value, '|') !== false) {
                        // Add each value separately
                        $values = array_map('trim', explode('|', $value));
                        foreach ($values as $single_value) {
                            if ($single_value !== '') {
                                $wpdb->insert(
                                    $wpdb->postmeta,
                                    [
                                        'post_id' => $post_id,
                                        'meta_key' => $key,
                                        'meta_value' => $single_value
                                    ],
                                    ['%d', '%s', '%s']
                                );
                            }
                        }
                    } else {
                        // Single value (including serialized strings)
                        $wpdb->insert(
                            $wpdb->postmeta,
                            [
                                'post_id' => $post_id,
                                'meta_key' => $key,
                                'meta_value' => is_string($value) ? $value : maybe_serialize($value),
                            ],
                            ['%d', '%s', '%s']
                        );
                    }
                }
            } else {
                $errors++;
            }
        } catch (Exception $e) {
			$errors++;
		}
		
	}
	
	$next_row = $start_row + $processed; // Use actual processed count instead of batch_size
	$continue = $next_row < $total_rows;
    
    $log("Batch completed: processed=$processed, errors=$errors, next_row=$next_row, total=$total_rows, continue=" . ($continue ? 'yes' : 'no'));
    
    wp_send_json([
        'processed' => $next_row,
        'total' => $total_rows,
        'batch_processed' => $processed,
        'batch_errors' => $errors,
        'progress' => round(($next_row / $total_rows) * 100, 2),
        'continue' => $continue,
        'message' => "Processed $processed rows, $errors errors"
    ]);
}
