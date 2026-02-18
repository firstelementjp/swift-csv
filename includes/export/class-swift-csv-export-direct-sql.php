<?php
/**
 * Direct SQL Export Class
 *
 * High-performance CSV export using direct SQL queries
 * instead of WordPress functions to avoid N+1 queries.
 *
 * @package Swift_CSV
 * @since 0.9.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Direct SQL Export Class
 *
 * Handles high-performance CSV export using direct SQL queries.
 * This class bypasses WordPress functions for maximum performance.
 *
 * @since 0.9.8
 */
class Swift_CSV_Export_Direct_SQL {

	/**
	 * Export configuration
	 *
	 * @var array
	 */
	private $config = [];

	/**
	 * Export session ID
	 *
	 * @var string
	 */
	private $export_session = '';

	/**
	 * Constructor
	 *
	 * @param array $config Export configuration.
	 * @throws Exception When security checks fail.
	 */
	public function __construct( $config = [] ) {
		// Security: Check user capabilities.
		if ( ! current_user_can( 'export' ) ) {
			throw new Exception( 'Insufficient permissions for export.' );
		}

		// Security: Rate limiting.
		$this->check_rate_limit();

		// Security: Validate and sanitize config.
		$this->config = $this->validate_config( $config );

		// Performance: Set limits for large exports.
		$this->set_performance_limits();

		$this->export_session = 'export_' . gmdate( 'Y-m-d_H-i-s' ) . '_' . wp_generate_uuid4();
	}

	/**
	 * Check rate limiting for export
	 *
	 * @throws Exception When rate limit exceeded.
	 */
	private function check_rate_limit() {
		$user_id     = get_current_user_id();
		$cache_key   = 'swift_csv_export_rate_limit_' . $user_id;
		$last_export = get_transient( $cache_key );

		if ( $last_export ) {
			throw new Exception( 'Please wait before exporting again.' );
		}

		// Set rate limit (1 minute between exports).
		set_transient( $cache_key, time(), MINUTE_IN_SECONDS );
	}

	/**
	 * Validate and sanitize export configuration
	 *
	 * @param array $config Raw configuration.
	 * @return array Validated configuration.
	 * @throws Exception When validation fails.
	 */
	private function validate_config( $config ) {
		$defaults = [
			'post_type'            => 'post',
			'post_status'          => 'publish',
			'export_scope'         => 'basic',
			'include_private_meta' => false, // Default limit for safety.
			'export_limit'         => 1000,
			'taxonomy_format'      => 'names', // Default taxonomy format.
			'enable_logs'          => false, // Default logs disabled.
		];

		$config = wp_parse_args( $config, $defaults );

		// Validate post_type.
		if ( ! post_type_exists( $config['post_type'] ) ) {
			throw new Exception( 'Invalid post type specified.' );
		}

		// Validate export_limit.
		$config['export_limit'] = absint( $config['export_limit'] );
		if ( $config['export_limit'] > 50000 ) {
			$config['export_limit'] = 50000; // Hard limit for large exports.
		}

		// Validate post_status.
		if ( is_array( $config['post_status'] ) ) {
			$config['post_status'] = array_filter( $config['post_status'], 'sanitize_text_field' );
		} else {
			$config['post_status'] = sanitize_text_field( $config['post_status'] );
		}

		// Validate export_scope.
		$allowed_scopes = [ 'basic', 'all', 'custom' ];
		if ( is_array( $config['export_scope'] ) ) {
			$scope = $config['export_scope']['scope'] ?? 'basic';
			if ( ! in_array( $scope, $allowed_scopes, true ) ) {
				$config['export_scope']['scope'] = 'basic';
			}
		} elseif ( ! in_array( $config['export_scope'], $allowed_scopes, true ) ) {
			$config['export_scope'] = 'basic';
		}

		return $config;
	}

	/**
	 * Set performance limits for large exports
	 */
	private function set_performance_limits() {
		// Increase memory limit for large exports.
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		// Set time limit.
		if ( function_exists( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
			set_time_limit( 300 ); // 5 minutes
		}
	}

	/**
	 * Export posts using direct SQL
	 *
	 * @return array Export result with CSV data and metadata.
	 */
	public function export() {
		try {
			// Get total posts count.
			$total_count = $this->get_total_posts_count();

			if ( 0 === $total_count ) {
				return [
					'success' => false,
					'message' => 'No posts found',
					'csv'     => '',
					'count'   => 0,
				];
			}

			// Get posts data.
			$posts_data = $this->get_posts_data();

			// Generate CSV.
			$csv = $this->generate_csv( $posts_data );

			return [
				'success' => true,
				'message' => 'Export completed successfully',
				'csv'     => $csv,
				'count'   => count( $posts_data ),
				'total'   => $total_count,
			];

		} catch ( Exception $e ) {
			return [
				'success' => false,
				'message' => 'Export failed: ' . $e->getMessage(),
				'csv'     => '',
				'count'   => 0,
			];
		}
	}

	/**
	 * Get total posts count using direct SQL
	 *
	 * @return int Total count.
	 */
	private function get_total_posts_count() {
		global $wpdb;

		// Build the complete query with status parameters.
		$query = $this->build_count_query();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Build count query with proper parameter binding
	 *
	 * @return string Prepared query.
	 */
	private function build_count_query() {
		global $wpdb;

		$statuses = $this->config['post_status'];

		if ( is_array( $statuses ) ) {
			// Multiple statuses - build IN clause.
			$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$query               = "SELECT COUNT(*) FROM {$wpdb->posts} 
					 WHERE post_type = %s 
					 AND post_status IN ({$status_placeholders})";

			// Build parameters array and use call_user_func_array.
			$params = array_merge( [ $this->config['post_type'] ], $statuses );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return call_user_func_array( [ $wpdb, 'prepare' ], array_merge( [ $query ], $params ) );
		} else {
			// Single status.
			$query = "SELECT COUNT(*) FROM {$wpdb->posts} 
					 WHERE post_type = %s 
					 AND post_status = %s";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return $wpdb->prepare( $query, $this->config['post_type'], $statuses );
		}
	}

	/**
	 * Build WHERE clause for post statuses
	 *
	 * @return string WHERE clause for statuses.
	 */
	private function build_status_where_clause() {
		$statuses = $this->config['post_status'];

		if ( is_array( $statuses ) ) {
			// Multiple statuses.
			$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			return "post_status IN ({$status_placeholders})";
		} else {
			// Single status.
			return 'post_status = %s';
		}
	}

	/**
	 * Get posts data using direct SQL
	 *
	 * @return array Posts data.
	 */
	private function get_posts_data() {
		global $wpdb;

		$limit_clause = '';
		if ( $this->config['export_limit'] > 0 ) {
			$limit_clause = $wpdb->prepare( 'LIMIT %d', $this->config['export_limit'] );
		}

		// Build query based on status type.
		$statuses = $this->config['post_status'];

		if ( is_array( $statuses ) ) {
			// Multiple statuses.
			$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$query               = "SELECT p.ID, p.post_title, p.post_content, p.post_status, 
							p.post_date, p.post_modified, p.post_name, p.post_excerpt,
							p.post_author, p.comment_count, p.menu_order, p.post_type,
							p.post_parent, p.comment_status, p.ping_status, p.post_password
						FROM {$wpdb->posts} p
						WHERE p.post_type = %s 
						AND p.post_status IN ({$status_placeholders})
						ORDER BY p.post_date DESC
						{$limit_clause}";

			// Use call_user_func_array to pass all status parameters.
			$params = array_merge( [ $this->config['post_type'] ], $statuses );
			$query  = call_user_func_array( [ $wpdb, 'prepare' ], array_merge( [ $query ], $params ) );
		} else {
			// Single status.
			$query = "SELECT p.ID, p.post_title, p.post_content, p.post_status, 
							p.post_date, p.post_modified, p.post_name, p.post_excerpt,
							p.post_author, p.comment_count, p.menu_order, p.post_type,
							p.post_parent, p.comment_status, p.ping_status, p.post_password
						FROM {$wpdb->posts} p
						WHERE p.post_type = %s 
						AND p.post_status = %s
						ORDER BY p.post_date DESC
						{$limit_clause}";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $wpdb->prepare( $query, $this->config['post_type'], $statuses );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Generate CSV from posts data
	 *
	 * @param array $posts_data Posts data.
	 * @return string CSV content.
	 */
	private function generate_csv( $posts_data ) {
		// Get headers based on export scope.
		$headers = $this->get_csv_headers();
		$csv     = implode( ',', $headers ) . "\n";

		// CSV rows.
		foreach ( $posts_data as $post ) {
			$row  = $this->get_csv_row( $post );
			$csv .= implode( ',', $row ) . "\n";
		}

		return $csv;
	}

	/**
	 * Get CSV headers based on export scope
	 *
	 * @return array CSV headers.
	 */
	private function get_csv_headers() {
		$export_scope   = $this->config['export_scope'];
		$scope          = is_array( $export_scope ) ? $export_scope['scope'] : $export_scope;
		$custom_columns = is_array( $export_scope ) ? $export_scope['custom_columns'] : [];

		// Basic headers - use actual DB column names.
		$basic_headers = [
			'ID',
			'post_title',
			'post_content',
			'post_status',
			'post_date',
			'post_modified',
			'post_name',
			'post_excerpt',
			'post_author',
			'comment_count',
			'menu_order',
		];

		// All headers (includes additional fields) - use actual DB column names.
		$all_headers = array_merge(
			$basic_headers,
			[
				'post_type',
				'post_parent',
				'comment_status',
				'ping_status',
				'post_password',
				'post_sticky',
			]
		);

		// Determine headers based on scope.
		switch ( $scope ) {
			case 'basic':
				$headers = $basic_headers;
				break;
			case 'all':
				$headers = $all_headers;
				break;
			default:
				$headers = $basic_headers;
				break;
		}

		// Add custom columns.
		if ( ! empty( $custom_columns ) ) {
			foreach ( $custom_columns as $column_key => $column_label ) {
				$headers[] = sanitize_text_field( $column_label );
			}
		}

		return apply_filters( 'swift_csv_export_headers', $headers, $scope );
	}

	/**
	 * Get CSV row data for a post
	 *
	 * @param array $post Post data.
	 * @return array CSV row data.
	 */
	private function get_csv_row( $post ) {
		$export_scope   = $this->config['export_scope'];
		$scope          = is_array( $export_scope ) ? $export_scope['scope'] : $export_scope;
		$custom_columns = is_array( $export_scope ) ? $export_scope['custom_columns'] : [];

		// Basic row data.
		$basic_row = [
			$post['ID'],
			'"' . str_replace( '"', '""', $post['post_title'] ?? '' ) . '"',
			'"' . str_replace( '"', '""', wp_strip_all_tags( $post['post_content'] ?? '' ) ) . '"',
			$post['post_status'] ?? '',
			$post['post_date'] ?? '',
			$post['post_modified'] ?? '',
			$post['post_name'] ?? '',
			'"' . str_replace( '"', '""', wp_strip_all_tags( $post['post_excerpt'] ?? '' ) ) . '"',
			$post['post_author'] ?? 0,
			$post['comment_count'] ?? 0,
			$post['menu_order'] ?? 0,
		];

		// All row data (includes additional fields).
		$all_row = array_merge(
			$basic_row,
			[
				$post['post_type'] ?? '',
				$post['post_parent'] ?? 0,
				$post['comment_status'] ?? '',
				$post['ping_status'] ?? '',
				$post['post_password'] ?? '',
				( $post['ID'] && is_sticky( $post['ID'] ) ) ? '1' : '0',
			]
		);

		// Determine row data based on scope.
		switch ( $scope ) {
			case 'basic':
				$row = $basic_row;
				break;
			case 'all':
				$row = $all_row;
				break;
			default:
				$row = $basic_row;
				break;
		}

			// Add custom column data.
		if ( ! empty( $custom_columns ) ) {
			foreach ( $custom_columns as $column_key => $column_label ) {
				$custom_value = $this->get_custom_column_value( $post, $column_key );
				$row[]        = '"' . str_replace( '"', '""', $custom_value ) . '"';
			}
		}

			return apply_filters( 'swift_csv_export_row', $row, $post, $scope );
	}

	/**
	 * Get custom column value for a post
	 *
	 * @param array  $post Post data.
	 * @param string $column_key Column key.
	 * @return string Column value.
	 */
	private function get_custom_column_value( $post, $column_key ) {
		// Allow custom column processing through filter.
		$value = apply_filters( 'swift_csv_export_custom_column_value', '', $column_key, $post['ID'] );

		// If no custom value, try to get from post data.
		if ( '' === $value && isset( $post[ $column_key ] ) ) {
			$value = $post[ $column_key ];
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Get export configuration
	 *
	 * @return array Configuration.
	 */
	public function get_config() {
		return $this->config;
	}

	/**
	 * Get export session ID
	 *
	 * @return string Session ID.
	 */
	public function get_export_session() {
		return $this->export_session;
	}
}
