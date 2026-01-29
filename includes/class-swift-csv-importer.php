<?php
/**
 * Importer class for handling CSV imports
 *
 * This file contains the import functionality for the Swift CSV plugin,
 * including file validation, data processing, and post creation.
 *
 * @since  0.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Swift_CSV_Importer {
    /**
     * Constructor
     *
     * Sets up WordPress hooks for import functionality.
     * 
     * @since  0.9.0
     * @return void
     */
    public function __construct()
    {
        add_action('admin_post_swift_csv_import', [$this, 'handle_import']);
        add_action('admin_post_swift_csv_import_batch', [$this, 'handle_batch_import']);
    }
    
    /**
     * Handle CSV import request
     * 
     * Validates user permissions, nonce, file upload, and parameters before processing import.
     * 
     * @since  0.9.0
     * @return void
     */
    public function handle_import()
    {
        // Security checks
        if (!current_user_can('manage_options')  
            || !isset($_POST['csv_import_nonce'])
            || !wp_verify_nonce($_POST['csv_import_nonce'], 'swift_csv_import')
        ) {
            wp_die( esc_html__( 'Security check failed.', 'swift-csv' ) );
        }

        // Validate required parameters
        if (!isset($_POST['import_post_type'])) {
            wp_die( esc_html__( 'Invalid request.', 'swift-csv' ) );
        }
        
        // Validate file upload
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die( esc_html__( 'File upload failed.', 'swift-csv' ) );
        }
        
        // Additional security: verify uploaded file
        if (!is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            wp_die( esc_html__( 'Invalid upload.', 'swift-csv' ) );
        }

        // Validate file type and extension using WordPress function
        $file_check = wp_check_filetype_and_ext(
            $_FILES['csv_file']['tmp_name'],
            $_FILES['csv_file']['name'],
            ['csv' => 'text/csv']
        );
        if (empty($file_check['ext']) || $file_check['ext'] !== 'csv') {
            wp_die( esc_html__( 'Only CSV files can be uploaded.', 'swift-csv' ) );
        }
        
        // File size limit (50MB) for batch processing
        if ($_FILES['csv_file']['size'] > 50 * 1024 * 1024) {
            wp_die( esc_html__( 'File size is too large. Please select a file under 50MB.', 'swift-csv' ) );
        }
        
        // Sanitize and validate post type
        $post_type = sanitize_text_field($_POST['import_post_type']);
        
        if (!post_type_exists($post_type)) {
            wp_die( esc_html__( 'Invalid post type.', 'swift-csv' ) );
        }
        
        // Check if existing posts should be updated
        $update_existing = isset($_POST['update_existing']);
        
        // Check file size to determine processing method
        $file_size = $_FILES['csv_file']['size'];
        $csv_data = $this->read_csv_file($_FILES['csv_file']['tmp_name']);
        $row_count = count($csv_data) - 1; // Exclude header
        
        // Use batch processing for large files
        if ($row_count > 1000 || $file_size > 10 * 1024 * 1024) {
            $this->handle_batch_import($_FILES['csv_file']['tmp_name'], $post_type, $update_existing);
        } else {
            $this->import_csv($_FILES['csv_file']['tmp_name'], $post_type, $update_existing);
        }
    }
    
    /**
     * Handle batch CSV import request
     * 
     * Processes large CSV files using batch processing to avoid timeouts.
     * 
     * @since  0.9.0
     * @param  string $file_path       Path to the uploaded CSV file.
     * @param  string $post_type       Target post type for import.
     * @param  bool   $update_existing Whether to update existing posts.
     * @return void
     */
    public function handle_batch_import($file_path, $post_type, $update_existing)
    {
        // Initialize batch processor
        $batch = new Swift_CSV_Batch();
        
        // Start batch processing
        $batch_id = $batch->start_batch($file_path, $post_type, $update_existing);
        
        // Redirect to batch progress page
        wp_redirect(admin_url('admin.php?page=swift-csv&tab=import&batch=' . $batch_id));
        exit;
    }

    /**
     * Debug logger
     *
     * Logs debug messages only when WP_DEBUG is enabled.
     *
     * @since  0.9.0
     * @param  string $message Message to log.
     * @return void
     */
    private function _debugLog($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message);
        }
    }
    
    /**
     * Import CSV file
     * 
     * Reads CSV data, creates mapping, and processes each row for import/update.
     * 
     * @since  0.9.0
     * @param  string $file_path       Path to the uploaded CSV file.
     * @param  string $post_type       Target post type for import.
     * @param  bool   $update_existing Whether to update existing posts.
     * @return void
     */
    private function import_csv($file_path, $post_type, $update_existing)
    {
        // Read and parse CSV file
        $csv_data = $this->read_csv_file($file_path);
        
        // Validate CSV data
        if (empty($csv_data) || count($csv_data) < 2) {
            wp_die( esc_html__( 'CSV file is empty or invalid.', 'swift-csv' ) );
        }
        
        // Extract headers and create field mapping
        $headers = array_shift($csv_data); // First row as headers
        $mapping = $this->create_mapping($headers);
        
        // Initialize counters
        $imported = 0;
        $updated = 0;
        $errors = [];
        
        // Process each data row
        foreach ($csv_data as $row_index => $row) {
            try {
                // Log first few rows for debugging
                if ($row_index < 3) {
                    $this->_debugLog('Processing row ' . ($row_index + 2) . ': ' . print_r($row, true));
                }
                
                $result = $this->process_row($row, $mapping, $post_type, $update_existing);
                
                if ($result['created']) {
                    $imported++;
                } elseif ($result['updated']) {
                    $updated++;
                }
                
            } catch (Exception $e) {
                $errors[] = '行 ' . ($row_index + 2) . ': ' . $e->getMessage();
                $this->_debugLog('Error processing row ' . ($row_index + 2) . ': ' . $e->getMessage());
            }
        }
        
        // Display import results
        $this->show_import_results($imported, $updated, $errors);
    }
    
    /**
     * Read CSV file with proper encoding
     */
    private function read_csv_file($file_path)
    {
        $content = file_get_contents($file_path);
        
        // Remove BOM if present
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        
        // Convert to UTF-8 if needed
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8, SJIS, EUC-JP, JIS');
        
        // Use proper CSV parsing to handle quoted fields and newlines
        $csv_data = [];
        $lines = explode("\n", $content);
        $current_row = [];
        $in_quotes = false;
        $current_field = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line) && !$in_quotes) {
                continue;
            }
            
            // Process character by character to handle quoted fields
            $chars = str_split($line);
            foreach ($chars as $char) {
                if ($char === '"') {
                    if ($in_quotes && substr($current_field, -1) === '\\') {
                        // Escaped quote
                        $current_field .= '"';
                    } else {
                        // Toggle quote state
                        $in_quotes = !$in_quotes;
                    }
                } elseif ($char === ',' && !$in_quotes) {
                    // Field separator
                    $current_row[] = $current_field;
                    $current_field = '';
                } else {
                    $current_field .= $char;
                }
            }
            
            // End of line
            if (!$in_quotes) {
                $current_row[] = $current_field;
                if (!empty(array_filter($current_row))) {
                    $csv_data[] = $current_row;
                }
                $current_row = [];
                $current_field = '';
            } else {
                // Continue field on next line
                $current_field .= "\n";
            }
        }
        
        // Handle last row if file doesn't end with newline
        if (!empty($current_field) || !empty($current_row)) {
            $current_row[] = $current_field;
            if (!empty(array_filter($current_row))) {
                $csv_data[] = $current_row;
            }
        }
        
        $this->_debugLog('Parsed CSV rows: ' . count($csv_data));
        
        return $csv_data;
    }
    
    /**
     * Create field mapping from CSV headers
     */
    private function create_mapping($headers)
    {
        $mapping = [];
        
        $this->_debugLog('CSV Headers: ' . print_r($headers, true));
        
        foreach ($headers as $index => $header) {
            $header = trim($header);
            
            // Basic post fields
            if (in_array($header, ['ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name', 'post_date'])) {
                $mapping[$index] = ['type' => 'post_field', 'field' => $header];
                $this->_debugLog("Mapped column $index to post_field: $header");
            }
            // Taxonomy fields (tax_ prefix)
            elseif (str_starts_with($header, 'tax_')) {
                $taxonomy = substr($header, 4);
                $mapping[$index] = ['type' => 'taxonomy', 'taxonomy' => $taxonomy];
                $this->_debugLog("Mapped column $index to taxonomy: $taxonomy");
            }
            // Custom fields (cf_ prefix)
            elseif (str_starts_with($header, 'cf_')) {
                $meta_key = substr($header, 3);
                $mapping[$index] = ['type' => 'meta_field', 'meta_key' => $meta_key];
                $this->_debugLog("Mapped column $index to meta_field: $meta_key");
            }
            // Unknown field
            else {
                $mapping[$index] = ['type' => 'unknown', 'field' => $header];
                $this->_debugLog("Mapped column $index to unknown: $header");
            }
        }
        
        $this->_debugLog('Final Mapping: ' . print_r($mapping, true));
        
        return $mapping;
    }
    
    /**
     * Process single CSV row
     */
    private function process_row($row, $mapping, $post_type, $update_existing)
    {
        $post_data = [
            'post_type' => $post_type,
            'post_status' => 'publish'
        ];
        
        $taxonomies = [];
        $meta_fields = [];
        $post_id = null;
        $is_update = false;
        
        foreach ($row as $index => $value) {
            if (!isset($mapping[$index])) { continue;
            }
            
            $map = $mapping[$index];
            $value = trim($value);
            
            // Clean up HTML tags and excessive content
            $value = $this->_clean_field_value($value, $map['type'], $map['field'] ?? '');
            
            switch ($map['type']) {
            case 'post_field':
                if ($map['field'] === 'ID' && $update_existing && is_numeric($value)) {
                    $post_id = intval($value);
                    $is_update = true;
                } elseif ($map['field'] === 'post_date') {
                    // Convert various date formats to MySQL format
                    if (!empty($value)) {
                        $timestamp = strtotime($value);
                        if ($timestamp !== false) {
                            $post_data[$map['field']] = date('Y-m-d H:i:s', $timestamp);
                        }
                    }
                } elseif ($map['field'] === 'post_status') {
                    // Validate post status
                    $valid_statuses = ['publish', 'draft', 'private', 'pending', 'trash'];
                    if (in_array($value, $valid_statuses)) {
                        $post_data[$map['field']] = $value;
                    }
                } elseif ($map['field'] === 'post_title') {
                    // Ensure title is not empty and not too long
                    if (!empty($value) && strlen($value) < 200) {
                        $post_data[$map['field']] = $value;
                    }
                } else {
                    $post_data[$map['field']] = $value;
                }
                break;
                    
            case 'taxonomy':
                if (!empty($value)) {
                    $terms = array_map('trim', explode(',', $value));
                    $taxonomies[$map['taxonomy']] = $terms;
                }
                break;
                    
            case 'meta_field':
                if (!empty($value)) {
                    $meta_fields[$map['meta_key']] = $value;
                }
                break;
            }
        }
        
        // Handle post creation/update
        if ($is_update && $post_id) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }
        
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $error_data = $result->get_error_data();
            $debug_info = print_r($post_data, true);
            $this->_debugLog("Import Error - Post Data: " . $debug_info);
            $this->_debugLog("Import Error - WP Error: " . $error_message);
            if ($error_data) {
                $this->_debugLog("Import Error - Error Data: " . print_r($error_data, true));
            }
            throw new Exception( esc_html__( 'Database error:', 'swift-csv' ) . ' ' . $error_message );
        }
        
        $post_id = $result;
        
        // Set taxonomies with hierarchical support
        foreach ($taxonomies as $taxonomy => $terms) {
            $processed_terms = array();
            
            foreach ($terms as $term) {
                // Handle hierarchical terms (e.g., "Parent > Child")
                if (strpos($term, '>') !== false) {
                    $hierarchy = array_map('trim', explode('>', $term));
                    $parent_term = array_shift($hierarchy);
                    
                    // Ensure parent exists
                    $parent_id = $this->ensure_term_exists($parent_term, $taxonomy, 0);
                    
                    // Process child terms
                    $current_parent = $parent_id;
                    foreach ($hierarchy as $child_term) {
                        $current_parent = $this->ensure_term_exists($child_term, $taxonomy, $current_parent);
                    }
                    $processed_terms[] = $current_parent;
                } else {
                    // Simple term
                    $term_id = $this->ensure_term_exists($term, $taxonomy, 0);
                    $processed_terms[] = $term_id;
                }
            }
            
            wp_set_post_terms($post_id, $processed_terms, $taxonomy);
        }
        
        // Set meta fields
        foreach ($meta_fields as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
        
        return ['updated' => $is_update, 'post_id' => $post_id];
    }
    
    /**
     * Ensure term exists and return its ID
     *
     * Creates term if it doesn't exist, handles hierarchical relationships.
     *
     * @since  0.9.0
     * @param  string $term_name     Term name to create/find.
     * @param  string $taxonomy      Taxonomy name.
     * @param  int    $parent_id     Parent term ID (0 for top-level).
     * @return int    Term ID.
     */
    private function ensure_term_exists($term_name, $taxonomy, $parent_id = 0)
    {
        // Check if term already exists with this parent
        $existing_term = get_term_by('name', $term_name, $taxonomy);
        if ($existing_term && $existing_term->parent == $parent_id) {
            return $existing_term->term_id;
        }
        
        // Create new term
        $result = wp_insert_term($term_name, $taxonomy, [
            'parent' => $parent_id,
        ]);
        
        if (is_wp_error($result)) {
            $this->_debugLog("Failed to create term: {$term_name} in {$taxonomy}");
            return 0;
        }
        
        return $result['term_id'];
    }
    
    /**
     * Clean up field values
     * 
     * @param  string $value The field value to clean
     * @param  string $type  The field type
     * @param  string $field The field name
     * @return string The cleaned value
     */
    private function _clean_field_value($value, $type, $field)
    {
        // Remove Excel/Google Sheets styling and formatting
        $value = $this->remove_excel_formatting($value);
        
        // Only remove HTML tags from non-content fields to preserve block editor
        if (!in_array($field, ['post_content', 'post_excerpt'])) {
            $value = strip_tags($value);
        }
        
        // Remove excessive whitespace
        $value = preg_replace('/\s+/', ' ', $value);
        
        // Handle JSON-like data
        if (str_contains($value, '{') && str_contains($value, '}')) {
            // Try to extract clean text from JSON-like strings
            preg_match('/"([^"]+)"/', $value, $matches);
            if (isset($matches[1])) {
                $value = $matches[1];
            }
        }
        
        // Trim and limit length
        $value = trim($value);
        
        // Field-specific cleaning
        if ($type === 'post_field') {
            switch ($field) {
            case 'post_status':
                $value = in_array($value, ['publish', 'draft', 'private', 'pending']) ? $value : 'publish';
                break;
            case 'post_title':
                $value = substr($value, 0, 200);
                break;
            case 'post_content':
            case 'post_excerpt':
                $value = substr($value, 0, 50000);
                break;
            }
        }
        
        return $value;
    }
    
    /**
     * Remove Excel/Google Sheets formatting from data
     * 
     * @param  string $value The value to clean
     * @return string The cleaned value
     */
    private function remove_excel_formatting($value)
    {
        // Remove CSS styles
        $value = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $value);
        $value = preg_replace('/style="[^"]*"/i', '', $value);
        
        // Remove Excel-specific formatting
        $patterns = [
            '/td\s*{\s*border:\s*[^}]*}/i',
            '/br\s*{\s*mso-data-placement:\s*[^}]*}/i',
            '/mso-data-placement:\s*[^;]*;?/i',
            '/font-family:\s*[^;]*;?/i',
            '/font-size:\s*[^;]*;?/i',
            '/color:\s*rgb\([^)]*\);?/i',
            '/letter-spacing:\s*[^;]*;?/i',
            '/white-space-collapse:\s*[^;]*;?/i',
            '/data-sheets-value="[^"]*"/i',
            '/data-sheets-userformat="[^"]*"/i',
            '/<span[^>]*data-sheets[^>]*>/i',
            '/<\/span>/i',
        ];
        
        foreach ($patterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }
        
        // Clean up JSON-like data from Google Sheets
        if (preg_match('/\{[^}]*"([^"]+)"[^}]*\}/', $value, $matches)) {
            if (isset($matches[1])) {
                $value = $matches[1];
            }
        }
        
        return $value;
    }
    
    /**
     * Show import results
     */
    private function show_import_results($imported, $updated, $errors)
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Import Results', 'swift-csv' ); ?></h1>
            
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php esc_html_e( 'Import completed!', 'swift-csv' ); ?></strong><br>
                    <?php esc_html_e( 'Created:', 'swift-csv' ); ?> <?php echo $imported; ?> <?php esc_html_e( 'posts', 'swift-csv' ); ?><br>
                    <?php esc_html_e( 'Updated:', 'swift-csv' ); ?> <?php echo $updated; ?> <?php esc_html_e( 'posts', 'swift-csv' ); ?>
                </p>
            </div>
            
            <?php if (!empty($errors)) : ?>
                <div class="notice notice-error">
                    <h3><?php esc_html_e( 'Errors:', 'swift-csv' ); ?></h3>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <p>
                <a href="?page=swift-csv&tab=import" class="button"><?php esc_html_e( 'Continue Import', 'swift-csv' ); ?></a>
                <a href="<?php echo admin_url('admin.php?page=swift-csv'); ?>" class="button"><?php esc_html_e( 'Back to Admin', 'swift-csv' ); ?></a>
            </p>
        </div>
        <?php
        exit;
    }
}
