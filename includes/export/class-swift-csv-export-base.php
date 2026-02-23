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

	/**
	 * Get post field headers
	 *
	 * @since 0.9.8
	 * @return array Post field headers.
	 */
	protected function get_post_headers() {
		$export_scope = $this->config['export_scope'] ?? 'basic';
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

		return $headers;
	}

	/**
	 * Get complete CSV headers including all optional columns
	 *
	 * Combines post headers, taxonomy headers, and custom field headers
	 * based on export configuration. Provides unified header generation
	 * for both standard and Direct SQL export methods.
	 *
	 * @since 0.9.11
	 * @param array  $config Export configuration.
	 * @param array  $query_spec Query specification for filtering.
	 * @param string $context Export context ('standard' or 'direct_sql').
	 * @return string[] Complete CSV headers.
	 */
	protected function get_complete_headers( array $config, array $query_spec = [], string $context = 'standard' ) {
		// Start with post headers.
		$headers = self::get_post_headers();

		// Add taxonomy headers if enabled.
		if ( ! empty( $config['include_taxonomies'] ) ) {
			$tax_headers = $this->get_taxonomy_headers( $config );
			if ( ! empty( $tax_headers ) ) {
				$headers = array_merge( $headers, $tax_headers );
			}
		}

		// Add custom field headers if enabled.
		if ( ! empty( $config['include_custom_fields'] ) ) {
			$cf_headers = $this->get_custom_field_headers( $config, $query_spec );
			if ( ! empty( $cf_headers ) ) {
				$headers = array_merge( $headers, $cf_headers );
			}
		}

		/**
		 * Filter complete CSV headers
		 *
		 * Allows developers to modify the complete CSV headers before export.
		 * This filter is applied after all header types are combined.
		 *
		 * @since 0.9.0
		 * @param array  $headers Complete CSV headers.
		 * @param array  $config Export configuration.
		 * @param string $context Export context.
		 * @return array Modified headers.
		 */
		return apply_filters( 'swift_csv_export_headers', $headers, $config, $context );
	}

	/**
	 * Get taxonomy headers for export
	 *
	 * Generates taxonomy column headers with 'tax_' prefix.
	 * Supports filtering of taxonomy objects.
	 *
	 * @since 0.9.11
	 * @param array $config Export configuration.
	 * @return string[] Taxonomy headers.
	 */
	protected function get_taxonomy_headers( array $config ) {
		$post_type = $config['post_type'] ?? 'post';

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );

		/**
		 * Filter taxonomy objects for header generation
		 *
		 * Allows developers to filter which taxonomies are included in headers.
		 * This enables selective taxonomy inclusion and custom taxonomy processing.
		 *
		 * @since 0.9.0
		 * @param array $taxonomies Taxonomy objects.
		 * @param array $args Export arguments including context.
		 * @return array Modified taxonomy objects.
		 */
		$taxonomy_filter_args = [
			'post_type'            => $post_type,
			'export_scope'         => $config['export_scope'] ?? 'basic',
			'include_private_meta' => ! empty( $config['include_private_meta'] ),
			'context'              => 'taxonomy_objects_filter',
		];
		$taxonomies           = apply_filters( 'swift_csv_filter_taxonomy_objects', $taxonomies, $taxonomy_filter_args );

		$tax_headers = [];
		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->public ) {
				$tax_headers[] = 'tax_' . $taxonomy->name;
			}
		}

		return $tax_headers;
	}

	/**
	 * Get custom field (meta) headers
	 *
	 * Uses a sample post to discover meta keys and generate custom field headers.
	 * Supports meta key classification and header generation via hooks.
	 * Uses the "cf_" prefix for custom field columns.
	 *
	 * @since 0.9.11
	 * @param array $config Export configuration.
	 * @param array $query_spec Query specification for filtering.
	 * @return string[] Custom field headers.
	 */
	protected function get_custom_field_headers( array $config, array $query_spec = [] ) {
		$post_type            = $config['post_type'] ?? 'post';
		$post_status          = $config['post_status'] ?? 'publish';
		$export_scope         = $config['export_scope'] ?? 'basic';
		$export_scope         = is_array( $export_scope ) ? ( $export_scope['scope'] ?? 'basic' ) : $export_scope;
		$include_private_meta = ! empty( $config['include_private_meta'] );

		$sample_args       = [
			'post_type' => $post_type,
			'context'   => 'meta_discovery',
		];
		$sample_query_args = [
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => 1,
			'orderby'        => 'post_date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		];

		// Merge query spec to ensure sample matches export criteria.
		if ( ! empty( $query_spec ) ) {
			// Convert tax_query and meta_query to WP_Query format.
			if ( isset( $query_spec['tax_query'] ) ) {
				$sample_query_args['tax_query'] = $query_spec['tax_query'];
			}
			if ( isset( $query_spec['meta_query'] ) ) {
				$sample_query_args['meta_query'] = $query_spec['meta_query'];
			}
		}

		$sample_query_args                   = apply_filters( 'swift_csv_sample_query_args', $sample_query_args, $sample_args );
		$sample_query_args['posts_per_page'] = 1;
		$sample_post_ids                     = get_posts( $sample_query_args );

		$all_meta_keys      = [];
		$found_private_meta = false;
		foreach ( (array) $sample_post_ids as $sample_post_id ) {
			$sample_post_id = (int) $sample_post_id;
			if ( 0 === $sample_post_id ) {
				continue;
			}
			$post_meta = get_post_meta( $sample_post_id );
			$meta_keys = array_keys( (array) $post_meta );
			foreach ( $meta_keys as $meta_key ) {
				if ( ! is_string( $meta_key ) || '' === $meta_key ) {
					continue;
				}
				if ( ! in_array( $meta_key, $all_meta_keys, true ) ) {
					$all_meta_keys[] = $meta_key;
				}
				if ( str_starts_with( $meta_key, '_' ) ) {
					$found_private_meta = true;
				}
			}

			if ( $found_private_meta && ! $include_private_meta ) {
				break; // Found what we need.
			}
		}

		// Hook for meta key classification.
		/**
		 * Filter and classify discovered meta keys
		 *
		 * Allows developers to classify meta keys into different categories
		 * (regular, private) for specialized processing. This hook enables
		 * custom field type classification and processing.
		 *
		 * @since 0.9.0
		 * @param array $all_meta_keys All discovered meta keys
		 * @param array $args Export arguments including context
		 * @return array Classified meta keys array with 'regular', 'private' keys
		 */
		$meta_classify_args   = [
			'post_type'            => $post_type,
			'export_scope'         => $export_scope,
			'include_private_meta' => $include_private_meta,
			'context'              => 'meta_key_classification',
		];
		$classified_meta_keys = apply_filters( 'swift_csv_classify_meta_keys', $all_meta_keys, $meta_classify_args );

		// Ensure classified structure exists.
		if ( ! is_array( $classified_meta_keys ) || ! isset( $classified_meta_keys['regular'] ) ) {
			// Fallback: create basic classification.
			$classified_meta_keys = [
				'regular' => [],
				'private' => [],
			];

			foreach ( $all_meta_keys as $meta_key ) {
				if ( str_starts_with( $meta_key, '_' ) ) {
					$classified_meta_keys['private'][] = $meta_key;
				} else {
					$classified_meta_keys['regular'][] = $meta_key;
				}
			}
		}

		// Hook for custom field headers generation.
		/**
		 * Generate custom field headers for export
		 *
		 * Allows extensions to generate custom field headers from classified meta keys.
		 * This hook enables custom header generation with different prefixes based on field type.
		 *
		 * @since 0.9.0
		 * @param array $custom_field_headers Array of custom field headers (empty array to start)
		 * @param array $classified_meta_keys Classified meta keys with 'regular', 'private' keys
		 * @param array $args Export arguments including context
		 * @return array Complete custom field headers array
		 */
		$custom_field_args = [
			'post_type'            => $post_type,
			'export_scope'         => $export_scope,
			'include_private_meta' => $include_private_meta,
			'context'              => 'custom_field_headers_generation',
		];

		$custom_field_headers = ! empty( $config['include_custom_fields'] ) ? apply_filters( 'swift_csv_generate_custom_field_headers', [], $classified_meta_keys, $custom_field_args ) : [];
		$custom_field_headers = is_array( $custom_field_headers ) ? $custom_field_headers : [];

		// Always merge fallback cf_ headers from classified meta keys.
		// This ensures private meta headers are not lost even if filters return partial results.
		$fallback_custom_field_headers = [];
		foreach ( (array) ( $classified_meta_keys['regular'] ?? [] ) as $meta_key ) {
			if ( ! is_string( $meta_key ) || '' === $meta_key ) {
				continue;
			}
			$fallback_custom_field_headers[] = 'cf_' . $meta_key;
		}
		if ( $include_private_meta ) {
			foreach ( (array) ( $classified_meta_keys['private'] ?? [] ) as $meta_key ) {
				if ( ! is_string( $meta_key ) || '' === $meta_key ) {
					continue;
				}
				$fallback_custom_field_headers[] = 'cf_' . $meta_key;
			}
		}

		if ( ! empty( $config['include_custom_fields'] ) ) {
			$custom_field_headers = array_merge( $custom_field_headers, $fallback_custom_field_headers );
		}

		return $custom_field_headers;
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

		return apply_filters( 'swift_csv_export_row', $row, $post->ID, $this->config, $scope );
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
		$headers = self::get_post_headers();
		$csv     = implode( ',', $headers ) . "\n";

		// Add data rows.
		foreach ( $posts_data as $post_data ) {
			$row  = $this->get_csv_row( $post_data );
			$csv .= implode( ',', $row ) . "\n";
		}

		return $csv;
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
			'post_type'             => sanitize_text_field( $config['post_type'] ),
			'post_status'           => $this->sanitize_post_status( $config['post_status'] ),
			'export_scope'          => $this->sanitize_export_scope( $config['export_scope'] ),
			'include_private_meta'  => isset( $config['include_private_meta'] ) ? (bool) $config['include_private_meta'] : false,
			'include_taxonomies'    => isset( $config['include_taxonomies'] ) ? (bool) $config['include_taxonomies'] : true,
			'include_custom_fields' => isset( $config['include_custom_fields'] ) ? (bool) $config['include_custom_fields'] : true,
			'export_limit'          => isset( $config['export_limit'] ) ? absint( $config['export_limit'] ) : 0,
			'taxonomy_format'       => isset( $config['taxonomy_format'] ) ? sanitize_text_field( $config['taxonomy_format'] ) : 'name',
			'enable_logs'           => isset( $config['enable_logs'] ) ? (bool) $config['enable_logs'] : false,
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
	 * Abstract method for getting posts data
	 *
	 * @since 0.9.8
	 * @return array Posts data.
	 */
	abstract protected function get_posts_data();
}
