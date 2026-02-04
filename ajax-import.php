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
    $batch_size = 1; // Temporary: process only 1 record for debugging
    
    // Debug: Force single batch processing only
    if ($start_row > 0) {
        wp_send_json([
            'processed' => $start_row,
            'total' => 0,
            'batch_processed' => 0,
            'batch_errors' => 0,
            'progress' => 100,
            'continue' => false,
            'message' => "Debug: Stopping after first batch"
        ]);
        return;
    }
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
    
    error_log("Detected delimiter: '$delimiter'");
    error_log("Total lines after proper parsing: " . count($lines));
    
    $headers = str_getcsv(array_shift($lines), $delimiter);
    error_log("Headers: " . print_r($headers, true));
    
    // Find important column indices based on actual CSV headers
    $title_col = array_search('タイトル', $headers) ?: 
                 array_search('title', $headers) ?: 
                 array_search('Title', $headers) ?: 
                 array_search('件名', $headers) ?: 1; // fallback to column 1
    
    $content_col = array_search('本文', $headers) ?: 
                   array_search('content', $headers) ?: 
                   array_search('Content', $headers) ?: 
                   array_search('内容', $headers) ?: 2; // fallback to column 2
    
    $status_col = array_search('ステータス', $headers) ?: 
                  array_search('status', $headers) ?: 
                  array_search('Status', $headers) ?: 
                  array_search('状態', $headers) ?: 4; // fallback to column 4
    
    error_log("Column mapping: title=$title_col, content=$content_col, status=$status_col");
    
    $total_rows = count($lines);
    error_log("Total data rows: $total_rows");
    
    $processed = 0;
    $errors = 0;
    
    // Process small batch
    error_log("Starting batch processing from row $start_row to " . min($start_row + $batch_size, $total_rows));
    
    for ($i = $start_row; $i < min($start_row + $batch_size, $total_rows); $i++) {
        if (empty(trim($lines[$i]))) {
            error_log("Skipping empty line $i");
            continue;
        }
        
        error_log("Processing line $i: " . $lines[$i]);
        
        $data = str_getcsv($lines[$i], $delimiter);
        
        // Debug: Log parsed data
        error_log("Parsed data for line $i: " . print_r($data, true));
        
        // First check if this looks like an ID row (first column is numeric ID)
        $first_col = $data[0] ?? '';
        if (is_numeric($first_col) && strlen($first_col) <= 6) {
            error_log("Found ID row: $first_col - processing normally");
            // This is normal - most rows have ID in first column
            // Don't skip - process the actual data
        } else {
            error_log("Unexpected first column: '$first_col' - might be header or malformed data");
            // Continue processing anyway
        }
        
        // Get data using correct column indices
        $title = $data[$title_col] ?? 'Untitled';
        $content = $data[$content_col] ?? '';
        $status = $data[$status_col] ?? 'publish';
        $post_id_from_csv = $first_col; // ID列を取得
        
        // Debug: Log title and content
        error_log("Title: '$title', Content: '$content', Status: '$status', CSV ID: '$post_id_from_csv'");
        
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
                error_log("Found existing post: $existing_post_id for CSV ID: $post_id_from_csv");
                $post_id = $existing_post_id;
                $is_update = true;
            } else {
                error_log("Creating new post for CSV ID: $post_id_from_csv");
            }
        } else {
            error_log("Update existing is disabled or no CSV ID - creating new post");
        }
        
        // Additional validation
        if (empty($title) || $title === 'Untitled') {
            error_log("Skipping row $i - no valid title found");
            continue;
        }
        
        try {
            // Direct SQL insert or update
            $post_data = [
                'post_author' => get_current_user_id() ?: 1,
                'post_date' => current_time('mysql'),
                'post_date_gmt' => current_time('mysql', true),
                'post_content' => $content,
                'post_title' => $title,
                'post_excerpt' => '',
                'post_status' => $status,
                'post_name' => sanitize_title($title),
                'post_type' => $post_type, // Use the specified post type
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', true),
                'post_parent' => 0,
                'menu_order' => 0,
                'post_mime_type' => '',
                'comment_count' => 0
            ];
            
            if ($is_update) {
                // Update existing post
                $result = $wpdb->update(
                    $wpdb->posts,
                    $post_data,
                    ['ID' => $post_id],
                    array_fill(0, count($post_data), '%s'),
                    ['%d']
                );
                error_log("Updated post: $post_id");
            } else {
                // Insert new post
                $result = $wpdb->insert($wpdb->posts, $post_data);
                if ($result !== false) {
                    $post_id = $wpdb->insert_id;
                    error_log("Created new post: $post_id");
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
                
                // Store CSV ID for future reference
                if (!empty($post_id_from_csv)) {
                    $wpdb->insert(
                        $wpdb->postmeta,
                        [
                            'post_id' => $post_id,
                            'meta_key' => 'csv_import_id',
                            'meta_value' => $post_id_from_csv
                        ],
                        ['%d', '%s', '%s']
                    );
                }
                
                // Process custom fields and taxonomies like original Swift CSV
                error_log("Processing custom fields for post ID $post_id");
                $meta_fields = [];
                $taxonomies = [];
                $taxonomy_term_ids = []; // Store term_ids for reuse in ACF fields
                
                for ($j = 0; $j < count($headers); $j++) {
                    if ($j == $title_col || $j == $content_col || $j == $status_col) {
                        continue; // Skip already processed columns
                    }
                    
                    if (empty(trim($data[$j]))) {
                        continue; // Skip empty fields
                    }
                    
                    $header_name = $headers[$j];
                    $meta_value = $data[$j];
                    
                    // Check if this is a taxonomy field (tax_ prefix ONLY, not cf_ fields)
                    if (strpos($header_name, 'tax_') === 0) {
                        // Handle taxonomy (pipe-separated) - this is for article-taxonomy relationship
                        $terms = array_map('trim', explode('|', $meta_value));
                        $taxonomies[$header_name] = $terms;
                        
                        // Extract taxonomy name from header (remove tax_ prefix)
                        $taxonomy_name = substr($header_name, 4); // Remove 'tax_'
                        if (taxonomy_exists($taxonomy_name)) {
                            // Get term_ids for reuse in ACF fields
                            $term_ids = [];
                            foreach ($terms as $term_name) {
                                if (!empty($term_name)) {
                                    // Find existing term by name in the specific taxonomy
                                    $term = get_term_by('name', $term_name, $taxonomy_name);
                                    if ($term) {
                                        $term_ids[] = $term->term_id;
                                        error_log("Found existing term '$term_name' (ID: {$term->term_id}) in taxonomy '$taxonomy_name'");
                                    } else {
                                        error_log("Term '$term_name' not found in taxonomy '$taxonomy_name' - skipping");
                                    }
                                }
                            }
                            if (!empty($term_ids)) {
                                $taxonomy_term_ids[$taxonomy_name] = $term_ids;
                                error_log("Stored term_ids for taxonomy '$taxonomy_name': [" . implode(', ', $term_ids) . "]");
                            }
                        } else {
                            error_log("Taxonomy '$taxonomy_name' does not exist - skipping field '$header_name'");
                        }
                        
                        error_log("Taxonomy field: '$header_name' with terms: " . implode(', ', $terms));
                    } else {
                        // Check if this is an ACF taxonomy field by checking ACF field metadata
                        $is_acf_taxonomy_field = false;
                        $taxonomy = '';
                        
                        // Check if this is an ACF field reference (starts with underscore)
                        if (strpos($header_name, '_') === 0) {
                            // This is an ACF field reference, get the actual field name
                            $actual_field_name = substr($header_name, 1); // Remove underscore
                            error_log("Found ACF field reference '$header_name' for actual field '$actual_field_name'");
                            
                            // Remove cf_ prefix if present
                            if (strpos($actual_field_name, 'cf_') === 0) {
                                $actual_field_name = substr($actual_field_name, 3); // Remove cf_
                                error_log("Removed cf_ prefix, actual field name: '$actual_field_name'");
                            }
                            
                            error_log("Processing ACF field reference: '$header_name' -> '$actual_field_name'");
                            
                            // Get ACF field key from postmeta using the actual field name
                            $acf_field_key = $wpdb->get_var($wpdb->prepare(
                                "SELECT meta_key FROM {$wpdb->postmeta} 
                                 WHERE meta_key LIKE 'field_%' 
                                 AND meta_value = %s 
                                 LIMIT 1",
                                $actual_field_name
                            ));
                            
                            error_log("ACF field search for '$actual_field_name': " . ($acf_field_key ? "found key '$acf_field_key'" : "not found"));
                            
                            if (!$acf_field_key) {
                                // Debug: show all field mappings for this field name
                                $all_mappings = $wpdb->get_results($wpdb->prepare(
                                    "SELECT meta_key, meta_value FROM {$wpdb->postmeta} 
                                     WHERE meta_value LIKE %s 
                                     AND meta_key LIKE 'field_%'",
                                    '%' . $actual_field_name . '%'
                                ));
                                foreach ($all_mappings as $mapping) {
                                    error_log("Field mapping: {$mapping->meta_key} -> {$mapping->meta_value}");
                                }
                            }
                            
                            if ($acf_field_key) {
                                error_log("Found ACF field key '$acf_field_key' for field '$actual_field_name'");
                                
                                // Get ACF field configuration from posts table
                                $field_config = $wpdb->get_var($wpdb->prepare(
                                    "SELECT post_content FROM {$wpdb->posts} 
                                     WHERE post_name = %s 
                                     AND post_type = 'acf-field'",
                                    $acf_field_key
                                ));
                                
                                if ($field_config) {
                                    $field_data = maybe_unserialize($field_config);
                                    if (is_array($field_data) && isset($field_data['type']) && $field_data['type'] === 'taxonomy') {
                                        $is_acf_taxonomy_field = true;
                                        $taxonomy = $field_data['taxonomy'] ?? '';
                                        error_log("ACF taxonomy field '$actual_field_name' uses taxonomy '$taxonomy'");
                                        
                                        // Now get the actual data from the corresponding field without underscore
                                        $actual_data = '';
                                        for ($k = 0; $k < count($headers); $k++) {
                                            if ($headers[$k] === $actual_field_name) {
                                                $actual_data = $data[$k] ?? '';
                                                error_log("Found actual data for '$actual_field_name': '$actual_data'");
                                                break;
                                            }
                                        }
                                        
                                        if (!empty($actual_data)) {
                                            // Process the actual data as ACF taxonomy field
                                            $term_names = array_map('trim', explode('|', $actual_data));
                                            $term_ids = [];
                                            
                                            // Check if we already have term_ids from tax_ processing
                                            if (isset($taxonomy_term_ids[$taxonomy])) {
                                                $term_ids = $taxonomy_term_ids[$taxonomy];
                                                error_log("Reusing stored term_ids for taxonomy '$taxonomy': [" . implode(', ', $term_ids) . "]");
                                            } else {
                                                // Get term_ids by name (only find existing terms, don't create)
                                                foreach ($term_names as $term_name) {
                                                    if (!empty($term_name)) {
                                                        // Find existing term by name in the specific taxonomy
                                                        $term = get_term_by('name', $term_name, $taxonomy);
                                                        if ($term) {
                                                            $term_ids[] = $term->term_id;
                                                            error_log("Found existing term '$term_name' (ID: {$term->term_id}) in taxonomy '$taxonomy'");
                                                        } else {
                                                            error_log("Term '$term_name' not found in taxonomy '$taxonomy' - skipping");
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            if (!empty($term_ids)) {
                                                // Store as serialized array of term_ids (ACF format)
                                                $serialized_term_ids = maybe_serialize($term_ids);
                                                $meta_fields[$header_name] = $serialized_term_ids;
                                                error_log("ACF taxonomy field '$header_name': term_ids [" . implode(', ', $term_ids) . "] -> serialized: '$serialized_term_ids'");
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        if (!$is_acf_taxonomy_field) {
                            // Handle regular custom field
                            // Remove cf_ prefix if present for field name
                            $clean_field_name = $header_name;
                            if (strpos($header_name, 'cf_') === 0) {
                                $clean_field_name = substr($header_name, 3); // Remove cf_
                                error_log("Removed cf_ prefix from field name: '$header_name' -> '$clean_field_name'");
                            }
                            $meta_fields[$clean_field_name] = $meta_value;
                            error_log("Custom field: '$clean_field_name' = '$meta_value'");
                        }
                    }
                }
                
                // Process taxonomies
                foreach ($taxonomies as $taxonomy => $terms) {
                    if (!empty($terms)) {
                        // Remove existing term relationships
                        $wpdb->query($wpdb->prepare(
                            "DELETE FROM {$wpdb->term_relationships} 
                             WHERE object_id = %d 
                             AND term_taxonomy_id IN (
                                 SELECT tt.term_taxonomy_id 
                                 FROM {$wpdb->term_taxonomy} tt 
                                 JOIN {$wpdb->terms} t ON tt.term_id = t.term_id 
                                 WHERE tt.taxonomy = %s
                             )",
                            $post_id,
                            $taxonomy
                        ));
                        
                        // Add new term relationships
                        foreach ($terms as $term_name) {
                            if (!empty($term_name)) {
                                // Ensure term exists
                                $term = get_term_by('name', $term_name, $taxonomy);
                                if (!$term) {
                                    $term = wp_insert_term($term_name, $taxonomy);
                                    if (is_wp_error($term)) {
                                        error_log("Failed to create term '$term_name' in taxonomy '$taxonomy'");
                                        continue;
                                    }
                                    $term_id = $term['term_id'];
                                } else {
                                    $term_id = $term->term_id;
                                }
                                
                                // Add relationship
                                $wpdb->insert(
                                    $wpdb->term_relationships,
                                    [
                                        'object_id' => $post_id,
                                        'term_taxonomy_id' => get_term_taxonomy_id($term_id, $taxonomy)
                                    ],
                                    ['%d', '%d']
                                );
                            }
                        }
                    }
                }
                
                // Process custom fields with multi-value support
                foreach ($meta_fields as $key => $value) {
                    // Skip empty values
                    if ($value === '' || $value === null) {
                        continue;
                    }
                    
                    // Handle multi-value custom fields (pipe-separated)
                    if (strpos($value, '|') !== false) {
                        // Remove existing meta values
                        $wpdb->query($wpdb->prepare(
                            "DELETE FROM {$wpdb->postmeta} 
                             WHERE post_id = %d 
                             AND meta_key = %s",
                            $post_id,
                            $key
                        ));
                        
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
                        // Check if serialized and preserve it
                        if (is_serialized($value)) {
                            $unserialized = maybe_unserialize($value);
                            if (is_array($unserialized)) {
                                $reserialized = maybe_serialize($unserialized);
                                $wpdb->query($wpdb->prepare(
                                    "UPDATE {$wpdb->postmeta} 
                                     SET meta_value = %s 
                                     WHERE post_id = %d 
                                     AND meta_key = %s",
                                    $reserialized,
                                    $post_id,
                                    $key
                                ));
                            } else {
                                // Fallback to raw SQL
                                $escaped_value = addslashes($value);
                                $wpdb->query(
                                    "UPDATE {$wpdb->postmeta} 
                                     SET meta_value = '{$escaped_value}' 
                                     WHERE post_id = {$post_id} 
                                     AND meta_key = '{$key}'"
                                );
                            }
                        } else {
                            // Single value
                            $wpdb->query($wpdb->prepare(
                                "UPDATE {$wpdb->postmeta} 
                                 SET meta_value = %s 
                                 WHERE post_id = %d 
                                 AND meta_key = %s",
                                $value,
                                $post_id,
                                $key
                            ));
                        }
                    }
                }
            } else {
                $errors++;
            }
        } catch (Exception $e) {
            $errors++;
        }
        
        // Small delay reduced for better performance
        usleep(10000); // 10ms instead of 50ms
        
        // Debug: Check post_meta immediately after processing
        if ($post_id) {
            error_log("=== POST-META DEBUG FOR POST ID $post_id ===");
            $meta_values = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->postmeta} 
                 WHERE post_id = %d 
                 ORDER BY meta_key",
                $post_id
            ));
            
            foreach ($meta_values as $meta) {
                $value = $meta->meta_value;
                if (strlen($value) > 100) {
                    $value = substr($value, 0, 100) . '...';
                }
                error_log("META: {$meta->meta_key} = {$value}");
            }
            error_log("=== END POST-META DEBUG ===");
        }
    }
    
    $next_row = $start_row + $processed; // Use actual processed count instead of batch_size
    $continue = $next_row < $total_rows;
    
    error_log("Batch completed: processed=$processed, errors=$errors, next_row=$next_row, total=$total_rows, continue=" . ($continue ? 'yes' : 'no'));
    
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
