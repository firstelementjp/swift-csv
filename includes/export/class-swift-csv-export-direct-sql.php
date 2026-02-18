<?php
/**
 * Direct SQL Export Class for Swift CSV
 *
 * High-performance CSV export using direct SQL queries.
 * Extends base export class with SQL-based data retrieval.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Direct SQL Export Class
 *
 * Handles high-performance CSV export using direct SQL queries.
 * This class bypasses WordPress functions for maximum performance.
 *
 * @since 0.9.8
 * @package Swift_CSV
 */
class Swift_CSV_Export_Direct_SQL extends Swift_CSV_Export_Base {

	/**
	 * Get posts data using direct SQL
	 *
	 * @since 0.9.8
	 * @return array Posts data.
	 */
	protected function get_posts_data() {
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
}
