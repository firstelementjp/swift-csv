<?php

/**
 * High-Speed SQL Importer for Swift CSV
 *
 * Uses direct SQL queries for maximum performance.
 *
 * @since  0.9.4
 */

class Swift_CSV_SQL_Importer {

	/**
	 * Import posts using direct SQL queries
	 *
	 * @param array  $csv_data Parsed CSV data
	 * @param array  $mapping Field mapping
	 * @param string $post_type Target post type
	 * @param bool   $update_existing Whether to update existing posts
	 * @return array Import results
	 */
	public function import_posts_sql( $csv_data, $mapping, $post_type, $update_existing ) 
    {
		global $wpdb;

		$imported = 0;
		$updated  = 0;
		$errors   = array();

		// Disable transactions and WordPress cron to avoid deadlocks
		$wpdb->query( 'SET SESSION autocommit = 1' );
		$wpdb->query( 'SET SESSION innodb_lock_wait_timeout = 1' );

		// Disable WordPress cron during import
		if ( ! defined( 'DISABLE_WP_CRON' ) ) {
			define( 'DISABLE_WP_CRON', true );
		}

		// Clear any existing cron locks
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_doing_cron%'" );

		try {
			foreach ( $csv_data as $row_index => $row ) {
				$result = $this->process_row_sql( $row, $mapping, $post_type, $update_existing, $row_index );

				if ( $result['created'] ) {
					++$imported;
				} elseif ( $result['updated'] ) {
					++$updated;
				} elseif ( $result['error'] ) {
					$errors[] = '行 ' . ( $row_index + 2 ) . ': ' . $result['error'];
				}
			}

			// Restore autocommit
			$wpdb->query( 'SET SESSION autocommit = 0' );

		} catch ( Exception $e ) {
			// Restore autocommit on error
			$wpdb->query( 'SET SESSION autocommit = 0' );
			$errors[] = 'Import failed: ' . $e->getMessage();
		}

		return array(
			'imported' => $imported,
			'updated'  => $updated,
			'errors'   => $errors,
		);
	}

	/**
	 * Process single row using SQL
	 *
	 * @param array  $row CSV row data
	 * @param array  $mapping Field mapping
	 * @param string $post_type Target post type
	 * @param bool   $update_existing Whether to update existing posts
	 * @param int    $row_index Row index
	 * @return array Processing result
	 */
	private function process_row_sql( $row, $mapping, $post_type, $update_existing, $row_index ) {
		global $wpdb;

		$post_data     = array();
		$meta_data     = array();
		$taxonomy_data = array();
		$post_id       = null;
		$is_update     = false;

		// Extract data from row
		foreach ( $row as $index => $value ) {
			if ( ! isset( $mapping[ $index ] ) ) {
				continue;
			}

			$map = $mapping[ $index ];

			// Be very careful with trimming - only remove actual whitespace
			// Don't modify serialized data
			if ( ! $this->is_serialized_field( $value ) ) {
				$value = trim( $value );
			}

			// Debug mapping structure
			error_log( "SQL Import: Mapping structure for index $index: " . print_r( $map, true ) );

			switch ( $map['type'] ) {
				case 'post_field':
					if ( $map['field'] === 'ID' && $update_existing && is_numeric( $value ) ) {
						$post_id   = intval( $value );
						$is_update = true;
					} elseif ( $map['field'] === 'post_date' && ! empty( $value ) ) {
						$post_data[ $map['field'] ] = date( 'Y-m-d H:i:s', strtotime( $value ) );
					} else {
						$post_data[ $map['field'] ] = $value;
					}
					break;

				case 'meta_field':
					if ( ! empty( $value ) ) {
						// Save all meta fields as-is - ACF will handle processing
						$meta_data[ $map['meta_key'] ] = $value;
						error_log( "SQL Import: Meta field '{$map['meta_key']}' saved as-is: {$value}" );
					}
					break;

				case 'taxonomy':
					if ( ! empty( $value ) ) {
						// Handle pipe-separated multiple terms
						$terms = explode( '|', $value );
						foreach ( $terms as $term ) {
							$term = trim( $term );
							if ( ! empty( $term ) ) {
								$taxonomy_data[ $map['taxonomy'] ][] = $term;
								error_log( "SQL Import: Adding term '{$term}' to taxonomy '{$map['taxonomy']}'" );
							}
						}
					}
					break;
			}
		}

		// Set default post data
		if ( ! $is_update ) {
			$post_data['post_type']         = $post_type;
			$post_data['post_status']       = 'publish';
			$post_data['post_author']       = get_current_user_id();
			$post_data['post_date']         = current_time( 'mysql' );
			$post_data['post_date_gmt']     = current_time( 'mysql', 1 );
			$post_data['post_modified']     = current_time( 'mysql' );
			$post_data['post_modified_gmt'] = current_time( 'mysql', 1 );
		}

		// Insert or update post
		if ( $is_update ) {
			$result = $this->update_post_sql( $post_id, $post_data );
		} else {
			$result  = $this->insert_post_sql( $post_data );
			$post_id = $result['post_id'];
		}

		if ( isset( $result['error'] ) && $result['error'] ) {
			return array( 'error' => $result['error'] );
		}

		// Insert meta data
		error_log( "SQL Import: Starting meta data insertion for post {$post_id}" );
		error_log( 'SQL Import: Meta data count: ' . count( $meta_data ) );

		foreach ( $meta_data as $key => $value ) {
			error_log( "SQL Import: About to insert meta - Key: '{$key}', Value: " . print_r( $value, true ) );
			error_log( 'SQL Import: Value type: ' . gettype( $value ) );

			$result = $this->update_post_meta_sql( $post_id, $key, $value );

			if ( $result ) {
				error_log( "SQL Import: Successfully inserted meta '{$key}'" );
			} else {
				error_log( "SQL Import: Failed to insert meta '{$key}'" );
			}
		}

		// Handle taxonomies
		foreach ( $taxonomy_data as $taxonomy => $terms ) {
			error_log( "SQL Import: Processing taxonomy '{$taxonomy}' with terms: " . implode( ', ', $terms ) );
			$this->update_post_taxonomies_sql( $post_id, $taxonomy, $terms );
		}

		return array(
			'created' => ! $is_update,
			'updated' => $is_update,
			'post_id' => $post_id,
		);
	}

	/**
	 * Insert post using direct SQL
	 *
	 * @param array $post_data Post data
	 * @return array Result with post_id
	 */
	private function insert_post_sql( $post_data ) {
		global $wpdb;

		$table  = $wpdb->posts;
		$data   = array();
		$format = array();

		// Map fields
		$field_map = array(
			'post_author'       => '%d',
			'post_date'         => '%s',
			'post_date_gmt'     => '%s',
			'post_content'      => '%s',
			'post_title'        => '%s',
			'post_excerpt'      => '%s',
			'post_status'       => '%s',
			'post_name'         => '%s',
			'post_modified'     => '%s',
			'post_modified_gmt' => '%s',
			'post_parent'       => '%d',
			'menu_order'        => '%d',
			'post_type'         => '%s',
			'comment_status'    => '%s',
			'ping_status'       => '%s',
		);

		foreach ( $field_map as $field => $format_type ) {
			if ( isset( $post_data[ $field ] ) ) {
				$data[ $field ] = $post_data[ $field ];
				$format[]       = $format_type;
			}
		}

		$result = $wpdb->insert( $table, $data, $format );

		if ( $result === false ) {
			return array( 'error' => 'Failed to insert post: ' . $wpdb->last_error );
		}

		return array( 'post_id' => $wpdb->insert_id );
	}

	/**
	 * Update post using direct SQL
	 *
	 * @param int   $post_id Post ID
	 * @param array $post_data Post data
	 * @return array Result
	 */
	private function update_post_sql( $post_id, $post_data ) {
		global $wpdb;

		$table  = $wpdb->posts;
		$data   = array();
		$format = array();

		// Map fields
		$field_map = array(
			'post_content'      => '%s',
			'post_title'        => '%s',
			'post_excerpt'      => '%s',
			'post_status'       => '%s',
			'post_name'         => '%s',
			'post_modified'     => '%s',
			'post_modified_gmt' => '%s',
			'menu_order'        => '%d',
		);

		foreach ( $field_map as $field => $format_type ) {
			if ( isset( $post_data[ $field ] ) ) {
				$data[ $field ] = $post_data[ $field ];
				$format[]       = $format_type;
			}
		}

		$result = $wpdb->update(
			$table,
			$data,
			array( 'ID' => $post_id ),
			$format,
			array( '%d' )
		);

		if ( $result === false ) {
			return array( 'error' => 'Failed to update post: ' . $wpdb->last_error );
		}

		return array( 'success' => true );
	}

	/**
	 * Update post meta using direct SQL
	 *
	 * @param int    $post_id Post ID
	 * @param string $meta_key Meta key
	 * @param mixed  $meta_value Meta value
	 * @return bool Success
	 */
	private function update_post_meta_sql( $post_id, $meta_key, $meta_value ) {
		global $wpdb;

		$table = $wpdb->postmeta;

		error_log( "SQL Import: update_post_meta_sql called with post_id={$post_id}, meta_key={$meta_key}" );

		// Check if meta already exists
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_id FROM $table WHERE post_id = %d AND meta_key = %s LIMIT 1",
				$post_id,
				$meta_key
			)
		);

		error_log( 'SQL Import: Meta exists check result: ' . ( $exists ? "Yes (ID: $exists)" : 'No' ) );

		if ( $exists ) {
			// Update existing using safe prepare method
			error_log( 'SQL Import: Updating existing meta with prepare' );
			$sql    = $wpdb->prepare(
				"UPDATE $table SET meta_value = %s WHERE post_id = %d AND meta_key = %s",
				$meta_value,
				$post_id,
				$meta_key
			);
			$result = $wpdb->query( $sql );
			error_log( 'SQL Import: Update result: ' . ( $result !== false ? 'Success' : 'Failed: ' . $wpdb->last_error ) );
			return $result !== false;
		} else {
			// Insert new using safe prepare method
			error_log( 'SQL Import: Inserting new meta with prepare' );
			$sql    = $wpdb->prepare(
				"INSERT INTO $table (post_id, meta_key, meta_value) VALUES (%d, %s, %s)",
				$post_id,
				$meta_key,
				$meta_value
			);
			$result = $wpdb->query( $sql );
			error_log( 'SQL Import: Insert result: ' . ( $result !== false ? 'Success' : 'Failed: ' . $wpdb->last_error ) );
			return $result !== false;
		}
	}

	/**
	 * Update post taxonomies using direct SQL
	 *
	 * @param int    $post_id Post ID
	 * @param string $taxonomy Taxonomy name
	 * @param array  $terms Terms to assign
	 * @return bool Success
	 */
	private function update_post_taxonomies_sql( $post_id, $taxonomy, $terms ) {
		global $wpdb;

		error_log( "SQL Import: Starting taxonomy update for post {$post_id}, taxonomy '{$taxonomy}'" );

		// Remove existing terms
		$deleted = $wpdb->delete(
			$wpdb->term_relationships,
			array( 'object_id' => $post_id ),
			array( '%d' )
		);
		error_log( "SQL Import: Deleted {$deleted} existing term relationships" );

		// Add new terms
		foreach ( $terms as $term_name ) {
			$term = get_term_by( 'name', $term_name, $taxonomy );

			if ( ! $term ) {
				// Create term if it doesn't exist
				error_log( "SQL Import: Creating new term '{$term_name}' in taxonomy '{$taxonomy}'" );
				$term_info = wp_insert_term( $term_name, $taxonomy );
				if ( is_wp_error( $term_info ) ) {
					error_log( "SQL Import: Failed to create term '{$term_name}': " . $term_info->get_error_message() );
					continue;
				}
				$term_id = $term_info['term_id'];
				error_log( "SQL Import: Created term '{$term_name}' with ID {$term_id}" );
			} else {
				$term_id = $term->term_id;
				error_log( "SQL Import: Found existing term '{$term_name}' with ID {$term_id}" );
			}

			// Add relationship
			$insert_result = $wpdb->insert(
				$wpdb->term_relationships,
				array(
					'object_id'        => $post_id,
					'term_taxonomy_id' => $term_id,
					'term_order'       => 0,
				),
				array( '%d', '%d', '%d' )
			);

			if ( $insert_result === false ) {
				error_log( 'SQL Import: Failed to insert term relationship: ' . $wpdb->last_error );
			} else {
				error_log( "SQL Import: Successfully linked post {$post_id} to term {$term_id}" );
			}
		}

		// Update term count
		wp_update_term_count( $term_id, $taxonomy );

		return true;
	}

	/**
	 * Check if value is serialized ACF field
	 *
	 * @param string $value Value to check
	 * @return bool Is serialized
	 */
	private function is_serialized_field( $value ) {
		// Check for serialized arrays: a:{count}:{data}
		if ( is_string( $value ) && preg_match( '/^a:\d+:\{.*\}$/', $value ) ) {
			return true;
		}
		// Check for serialized strings: s:{length}:"{data}";
		if ( is_string( $value ) && preg_match( '/^s:\d+:".*";$/', $value ) ) {
			return true;
		}
		// Check for serialized integers: i:{value};
		if ( is_string( $value ) && preg_match( '/^i:\d+;$/', $value ) ) {
			return true;
		}
		return false;
	}
}
