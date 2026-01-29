<?php

/**
 * Exporter class for handling CSV exports
 *
 * This file contains the export functionality for the Swift CSV plugin,
 * including data retrieval, CSV formatting, and file download.
 *
 * @package Swift_CSV
 * @since   1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

class Swift_CSV_Exporter
{
    /**
     * Constructor
     *
     * Sets up WordPress hooks for export functionality.
     *
     * @since  1.0.0
     * @return void
     */
    public function __construct()
    {
        add_action('admin_post_swift_csv_export', [$this, 'handle_export']);
    }

    /**
     * Handle CSV export request
     *
     * Validates user permissions, nonce, and parameters before processing export.
     *
     * @since  1.0.0
     * @return void
     */
    public function handle_export()
    {
        // Security checks
        if (
            !current_user_can('manage_options')
            || !isset($_POST['csv_export_nonce'])
            || !wp_verify_nonce($_POST['csv_export_nonce'], 'swift_csv_export')
        ) {
            wp_die('セキュリティチェックに失敗しました。');
        }

        // Validate required parameters
        if (!isset($_POST['post_type']) || !isset($_POST['posts_per_page'])) {
            wp_die('リクエストが不正です。');
        }

        // Sanitize and validate inputs
        $post_type = sanitize_text_field($_POST['post_type']);
        $posts_per_page = intval($_POST['posts_per_page']);

        // Validate post type exists
        if (!post_type_exists($post_type)) {
            wp_die('無効な投稿タイプです。');
        }

        // Limit posts per page to prevent memory issues (max 1000)
        $posts_per_page = max(1, min($posts_per_page, 1000));

        $this->export_csv($post_type, $posts_per_page);
    }

    /**
     * Export posts to CSV
     *
     * Retrieves posts, generates CSV headers and content, and sends file to browser.
     *
     * @since  1.0.0
     * @param  string $post_type      The post type to export.
     * @param  int    $posts_per_page Number of posts to export.
     * @return void
     */
    private function export_csv($post_type, $posts_per_page)
    {
        $this->_debugLog('Export CSV started for post type: ' . $post_type);

        // Query arguments for post retrieval
        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'orderby' => 'ID',
            'order' => 'ASC'
        ];

        $this->_debugLog('Query args: ' . print_r($args, true));

        $query = new WP_Query($args);
        $posts = $query->posts;

        $this->_debugLog('Found posts count: ' . count($posts));

        // Check if posts exist
        if (empty($posts)) {
            wp_die('エクスポートする投稿が見つかりません。');
        }

        // Prepare CSV headers with standard post fields
        $headers = ['ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name', 'post_date'];

        // Add taxonomy columns
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        foreach ($taxonomies as $taxonomy) {
            if ($taxonomy->public) {
                $headers[] = 'tax_' . $taxonomy->name;
            }
        }

        // Add custom field columns (sample first 20 posts to detect fields)
        $custom_fields = [];
        $sample_posts = array_slice($posts, 0, 20);
        foreach ($sample_posts as $post) {
            $fields = get_post_meta($post->ID);
            foreach ($fields as $key => $value) {
                // Skip private fields (starting with _) and duplicates
                if (!str_starts_with($key, '_') && !in_array($key, $custom_fields)) {
                    $custom_fields[] = $key;
                }
            }
        }

        // Debug: Log detected custom fields
        $custom_fields_dump = print_r($custom_fields, true);
        $this->_debugLog('Export: Detected custom fields: ' . $custom_fields_dump);

        // Add custom field headers with 'cf_' prefix
        foreach ($custom_fields as $field) {
            $headers[] = 'cf_' . $field;
        }

        // Generate CSV content
        $csv_content = $this->generate_csv_content($posts, $headers, $taxonomies, $custom_fields);

        // Generate filename with timestamp
        $filename = 'export_' . $post_type . '_' . date('Y-m-d_H-i-s') . '.csv';

        // Clear any existing output buffer
        if (ob_get_level()) {
            ob_clean();
        }

        // Add BOM for Excel compatibility with Japanese characters
        $bom = "\xEF\xBB\xBF";
        $full_content = $bom . $csv_content;

        // Set HTTP headers for CSV download
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($full_content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo $full_content;

        exit;
    }

    /**
     * Debug logger
     *
     * Logs debug messages only when WP_DEBUG is enabled.
     *
     * @since  1.0.0
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
     * Generate CSV content from posts
     *
     * Creates CSV data including headers, post data, taxonomies, and custom fields.
     *
     * @since  1.0.0
     * @param  array $posts         Array of WP_Post objects.
     * @param  array $headers       CSV header columns.
     * @param  array $taxonomies    Taxonomy objects for the post type.
     * @param  array $custom_fields Custom field keys detected in posts.
     * @return string Generated CSV content.
     */
    private function generate_csv_content($posts, $headers, $taxonomies, $custom_fields)
    {
        $csv = [];

        // Add header row
        $csv[] = $headers;

        // Add data rows for each post
        foreach ($posts as $post) {
            $row = [];

            // Basic post data
            $row[] = $post->ID;
            $row[] = $post->post_title;
            $row[] = $post->post_content;
            $row[] = $post->post_excerpt;
            $row[] = $post->post_status;
            $row[] = $post->post_name;
            $row[] = $post->post_date;

            // Taxonomy data
            foreach ($taxonomies as $taxonomy) {
                if ($taxonomy->public) {
                    $terms = wp_get_post_terms($post->ID, $taxonomy->name);
                    $term_names = array_map(
                        function ($term) {
                            return $term->name;
                        },
                        $terms
                    );
                    // Comma-separated term names for multiple terms
                    $row[] = implode(',', $term_names);
                }
            }

            // Custom field data
            foreach ($custom_fields as $field) {
                $value = get_post_meta($post->ID, $field, true);
                $row[] = $value;
            }

            $csv[] = $row;
        }

        // Convert array to CSV string using PHP's built-in function
        $output = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }
}
