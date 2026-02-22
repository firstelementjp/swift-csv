<?php
/**
 * Base Export Class for Swift CSV
 *
 * Provides common functionality for both standard and Direct SQL exports.
 * Handles configuration validation, user permissions, rate limiting, and CSV generation.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base Export Class
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
abstract class Swift_CSV_Export_Base {

	/**
	 * Export configuration
	 *
	 * @since 0.9.8
	 * @var array
	 */
	protected $config;

	/**
	 * Export session identifier
	 *
	 * @since 0.9.8
	 * @var string
	 */
	protected $export_session;

	/**
	 * Constructor
	 *
	 * @since 0.9.8
	 * @param array $config Export configuration.
	 */
	public function __construct( $config ) {
		// Security: Validate and sanitize config.
		$this->config = $this->validate_config( $config );

		// Performance: Set limits for large exports.
		$this->set_performance_limits();

		$this->export_session = 'export_' . gmdate( 'Y-m-d_H-i-s' ) . '_' . wp_generate_uuid4();
	}

	/**
	 * Validate export configuration
	 *
	 * @since 0.9.8
	 * @param array $config Export configuration.
	 * @return array Validated configuration.
	 * @throws InvalidArgumentException When configuration is invalid.
	 */
	protected function validate_config( $config ) {
		// Required fields validation.
		$required = [ 'post_type', 'post_status', 'export_scope' ];
		foreach ( $required as $field ) {
			if ( empty( $config[ $field ] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new InvalidArgumentException( "Missing required field: {$field}" );
			}
		}

		// Sanitize configuration.
		$validated_config = [
			'post_type'            => sanitize_text_field( $config['post_type'] ),
			'post_status'          => $this->sanitize_post_status( $config['post_status'] ),
			'export_scope'         => $this->sanitize_export_scope( $config['export_scope'] ),
			'include_private_meta' => isset( $config['include_private_meta'] ) ? (bool) $config['include_private_meta'] : false,
			'include_taxonomies'   => isset( $config['include_taxonomies'] ) ? (bool) $config['include_taxonomies'] : false,
			'export_limit'         => isset( $config['export_limit'] ) ? absint( $config['export_limit'] ) : 0,
			'taxonomy_format'      => isset( $config['taxonomy_format'] ) ? sanitize_text_field( $config['taxonomy_format'] ) : 'name',
			'enable_logs'          => isset( $config['enable_logs'] ) ? (bool) $config['enable_logs'] : false,
		];

		return $validated_config;
	}

	/**
	 * Set performance limits for large exports
	 *
	 * @since 0.9.8
	 */
	protected function set_performance_limits() {
		// Increase memory limit for large exports.
		wp_raise_memory_limit( 'admin' );

		// Set time limit.
		set_time_limit( 300 ); // 5 minutes.
	}

	/**
	 * Sanitize post status field (handles single, multiple, and array values)
	 *
	 * @since 0.9.8
	 * @param string|array $post_status Post status string (comma-separated for multiple) or array of statuses.
	 * @return string|array Sanitized post status(es).
	 */
	protected function sanitize_post_status( $post_status ) {
		// Handle array input (from custom processing).
		if ( is_array( $post_status ) ) {
			$sanitized = [];
			foreach ( $post_status as $status ) {
				$sanitized_status = sanitize_text_field( $status );
				if ( ! empty( $sanitized_status ) ) {
					$sanitized[] = $sanitized_status;
				}
			}
			return ! empty( $sanitized ) ? $sanitized : [ 'publish' ];
		}

		// Handle multiple statuses (comma-separated).
		if ( strpos( $post_status, ',' ) !== false ) {
			$statuses  = explode( ',', $post_status );
			$sanitized = [];

			foreach ( $statuses as $status ) {
				$sanitized_status = sanitize_text_field( trim( $status ) );
				if ( ! empty( $sanitized_status ) ) {
					$sanitized[] = $sanitized_status;
				}
			}

			return $sanitized;
		}

		// Handle "all" or "any" status - return all common post statuses.
		if ( 'all' === $post_status || 'any' === $post_status ) {
			return [
				'publish',
				'pending',
				'draft',
				'auto-draft',
				'future',
				'private',
				'inherit',
				'trash',
			];
		}

		// Single status.
		return sanitize_text_field( $post_status );
	}

	/**
	 * Sanitize export scope
	 *
	 * @since 0.9.8
	 * @param string $export_scope Export scope value.
	 * @return string Sanitized export scope.
	 */
	protected function sanitize_export_scope( $export_scope ) {
		return sanitize_text_field( $export_scope );
	}

	/**
	 * Get CSV headers based on export scope
	 *
	 * @since 0.9.8
	 * @return array CSV headers.
	 */
	protected function get_csv_headers() {
		$export_scope = $this->config['export_scope'];
		$scope        = $export_scope;

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

		/**
		 * Filter CSV headers
		 *
		 * @since 0.9.0
		 * @param array $headers CSV headers.
		 * @param array $config Export configuration.
		 */
		return apply_filters( 'swift_csv_export_headers', $headers, $this->config );
	}

	/**
	 * Get CSV row data for a post
	 *
	 * @since 0.9.8
	 * @param array $post Post data.
	 * @return array CSV row data.
	 */
	protected function get_csv_row( $post ) {
		$export_scope = $this->config['export_scope'];
		$scope        = $export_scope;

		// Basic row data.
		$basic_row = [
			$post['ID'],
			'"' . str_replace( '"', '""', $post['post_title'] ?? '' ) . '"',
			'"' . str_replace( '"', '""', $post['post_content'] ?? '' ) . '"',
			$post['post_status'] ?? '',
			$post['post_date'] ?? '',
			$post['post_modified'] ?? '',
			$post['post_name'] ?? '',
			'"' . str_replace( '"', '""', $post['post_excerpt'] ?? '' ) . '"',
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

		return apply_filters( 'swift_csv_export_row', $row, $post, $scope );
	}

	/**
	 * Get custom column value
	 *
	 * @since 0.9.8
	 * @param array  $post Post data.
	 * @param string $column_key Column key.
	 * @return string Custom column value.
	 */
	protected function get_custom_column_value( $post, $column_key ) {
		// Allow custom column processing via filter.
		$value = apply_filters( 'swift_csv_custom_column_value', '', $column_key, $post['ID'] );

		if ( '' !== $value ) {
			return $value;
		}

		// Default: try to get from post data.
		return $post[ $column_key ] ?? '';
	}

	/**
	 * Generate CSV from posts data
	 *
	 * @since 0.9.8
	 * @param array $posts_data Posts data.
	 * @return string CSV content.
	 */
	protected function generate_csv( $posts_data ) {
		// Get headers based on export scope.
		$headers = $this->get_csv_headers();
		$csv     = implode( ',', $headers ) . "\n";

		// Add data rows.
		foreach ( $posts_data as $post_data ) {
			$row  = $this->get_csv_row( $post_data );
			$csv .= implode( ',', $row ) . "\n";
		}

		return $csv;
	}

	/**
	 * Abstract method for getting posts data
	 *
	 * @since 0.9.8
	 * @return array Posts data.
	 */
	abstract protected function get_posts_data();

	/**
	 * Export posts to CSV
	 *
	 * @since 0.9.8
	 * @return array Export result with success status and data.
	 */
	public function export() {
		try {
			// Get posts data using child class method.
			$posts_data = $this->get_posts_data();

			if ( empty( $posts_data ) ) {
				return [
					'success' => false,
					'message' => 'No posts found',
					'data'    => [],
				];
			}

			// Generate CSV.
			$csv_content = $this->generate_csv( $posts_data );

			return [
				'success' => true,
				'message' => 'Export completed successfully',
				'data'    => [
					'csv_content'    => $csv_content,
					'record_count'   => count( $posts_data ),
					'export_session' => $this->export_session,
				],
			];

		} catch ( Exception $e ) {
			return [
				'success' => false,
				'message' => 'Export failed: ' . $e->getMessage(),
				'data'    => [],
			];
		}
	}
}
