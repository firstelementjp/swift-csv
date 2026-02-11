<?php
/**
 * Ajax Import Handler for Swift CSV
 *
 * Handles asynchronous CSV import with chunked processing for large files.
 * Supports custom post types, taxonomies, and meta fields.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */

add_action( 'wp_ajax_swift_csv_ajax_import', 'swift_csv_ajax_import_handler' );
add_action( 'wp_ajax_nopriv_swift_csv_ajax_import', 'swift_csv_ajax_import_handler' );
add_action( 'wp_ajax_swift_csv_ajax_upload', 'swift_csv_ajax_upload_handler' );
add_action( 'wp_ajax_nopriv_swift_csv_ajax_upload', 'swift_csv_ajax_upload_handler' );

/**
 * Ajax Import handler.
 *
 * @since 0.9.0
 */
class Swift_CSV_Ajax_Import {
	/**
	 * Handle CSV file upload via AJAX.
	 *
	 * @since 0.9.0
	 * @return void Sends JSON response
	 */
	public function upload_handler() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'swift_csv_ajax_nonce' ) ) {
			wp_send_json(
				[
					'success' => false,
					'error'   => 'Security check failed',
				]
			);
			return;
		}

		if ( ! isset( $_FILES['csv_file'] ) ) {
			wp_send_json(
				[
					'success' => false,
					'error'   => 'No file uploaded',
				]
			);
			return;
		}

		$file = $_FILES['csv_file'];

		// Validate file
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json(
				[
					'success' => false,
					'error'   => 'Upload error: ' . $file['error'],
				]
			);
			return;
		}

		// Get actual PHP upload limits
		$upload_max = ini_get( 'upload_max_filesize' );
		$post_max   = ini_get( 'post_max_size' );

		$upload_max_bytes = $this->parse_ini_size( $upload_max );
		$post_max_bytes   = $this->parse_ini_size( $post_max );
		$max_file_size    = min( $upload_max_bytes, $post_max_bytes );

		if ( $file['size'] > $max_file_size ) {
			wp_send_json(
				[
					'success' => false,
					'error'   => 'File too large',
				]
			);
			return;
		}

		// Create temp file
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/swift-csv-temp';
		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		$temp_file = $temp_dir . '/ajax-import-' . time() . '.csv';

		if ( move_uploaded_file( $file['tmp_name'], $temp_file ) ) {
			wp_send_json( [ 'file_path' => $temp_file ] );
		} else {
			wp_send_json(
				[
					'success' => false,
					'error'   => 'Failed to save file',
				]
			);
		}
	}

	/**
	 * Handle CSV import processing via AJAX.
	 *
	 * @since 0.9.0
	 * @return void Sends JSON response with import results
	 */
	public function import_handler() {
		global $wpdb;

		if ( ! $this->verify_nonce_or_send_error_and_cleanup() ) {
			return;
		}

		$this->setup_db_session( $wpdb );

		$start_row       = intval( $_POST['start_row'] ?? 0 );
		$batch_size      = 10;
		$post_type       = sanitize_text_field( wp_unslash( $_POST['post_type'] ?? 'post' ) );
		$update_existing = sanitize_text_field( wp_unslash( $_POST['update_existing'] ?? '0' ) );
		$taxonomy_format = sanitize_text_field( wp_unslash( $_POST['taxonomy_format'] ?? 'name' ) );
		$dry_run         = sanitize_text_field( wp_unslash( $_POST['dry_run'] ?? '0' ) ) === '1';

		// Get file path for cleanup
		$file_path = sanitize_text_field( wp_unslash( $_POST['file_path'] ?? '' ) );

		// Advanced taxonomy format detection and validation
		$taxonomy_format_validation = [];
		$first_row_processed        = false;

		// Initialize counters
		$created     = 0;
		$updated     = 0;
		$errors      = 0;
		$dry_run_log = [];

		$csv_content = $this->read_uploaded_csv_content_or_send_error_and_cleanup( $file_path );
		if ( null === $csv_content ) {
			return;
		}

		$lines = $this->parse_csv_lines_preserving_quoted_newlines( $csv_content );

		$delimiter = $this->detect_csv_delimiter( $lines );

		$debug_logging = ( defined( 'SWIFT_CSV_DEBUG' ) && SWIFT_CSV_DEBUG ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );

		$headers = $this->read_and_normalize_headers( $lines, $delimiter );

		$taxonomy_format_validation = $this->detect_taxonomy_format_validation_or_send_error_and_cleanup(
			$lines,
			$delimiter,
			$headers,
			$taxonomy_format,
			$file_path
		);
		if ( null === $taxonomy_format_validation ) {
			return;
		}

		$allowed_post_fields = $this->get_allowed_post_fields();

		$id_col = $this->ensure_id_column_or_send_error_and_cleanup( $headers, $file_path );
		if ( null === $id_col ) {
			return;
		}

		$total_rows = $this->count_total_rows( $lines );

		$processed = 0;
		$errors    = 0;

		$cumulative_counts = $this->get_cumulative_counts();
		$previous_created  = $cumulative_counts['created'];
		$previous_updated  = $cumulative_counts['updated'];
		$previous_errors   = $cumulative_counts['errors'];

		$created = 0;
		$updated = 0;

		for ( $i = $start_row; $i < min( $start_row + $batch_size, $total_rows ); $i++ ) {
			// Skip empty lines only
			if ( $this->is_empty_csv_line( $lines[ $i ] ) ) {
				++$processed; // Count empty lines as processed to avoid infinite loop
				continue;
			}

			$data = $this->parse_csv_row( $lines[ $i ], $delimiter );

			// First check if this looks like an ID row (first column is numeric ID)
			$first_col = $data[0] ?? '';
			if ( is_numeric( $first_col ) && strlen( $first_col ) <= 6 ) {
				// This is normal - most rows have ID in first column
				// Don't skip - process the actual data
			} else {
				// Continue processing anyway
			}

			$post_id_from_csv = $first_col;

			// Collect post fields from CSV (header-driven)
			$post_fields_from_csv = $this->collect_post_fields_from_csv_row( $headers, $data, $allowed_post_fields );

			// Check for existing post by CSV ID (only if update_existing is checked)
			$existing  = $this->find_existing_post_for_update( $wpdb, $update_existing, $post_type, $post_id_from_csv );
			$post_id   = $existing['post_id'];
			$is_update = $existing['is_update'];

			// Validation
			if ( $this->should_skip_row_due_to_missing_title( $update_existing, $post_fields_from_csv ) ) {
				continue;
			}

			try {
				// Direct SQL insert or update (update only fields provided by CSV)
				$post_data = [];

				if ( $is_update ) {
					// Update only provided post fields
					foreach ( $post_fields_from_csv as $key => $value ) {
						$post_data[ $key ] = $value;
					}
					// Keep modification timestamps consistent
					$post_data['post_modified']     = current_time( 'mysql' );
					$post_data['post_modified_gmt'] = current_time( 'mysql', true );
				} else {
					// Insert with defaults + provided values

					// Handle post_author - convert display name to user ID
					$post_author_id = 1; // Default to admin
					if ( ! empty( $post_fields_from_csv['post_author'] ) ) {
						$author_display_name = trim( $post_fields_from_csv['post_author'] );
						if ( is_numeric( $author_display_name ) ) {
							// If it's already a numeric ID, use it directly
							$post_author_id = (int) $author_display_name;
						} else {
							// Try to find user by display name
							$author_user = get_user_by( 'display_name', $author_display_name );
							if ( $author_user ) {
								$post_author_id = $author_user->ID;
							}
							// If not found, fallback to current user or admin
							elseif ( get_current_user_id() ) {
								$post_author_id = get_current_user_id();
							}
						}
					} elseif ( get_current_user_id() ) {
						$post_author_id = get_current_user_id();
					}

					$post_data = [
						'post_author'       => $post_author_id,
						'post_date'         => $post_fields_from_csv['post_date'] ?? current_time( 'mysql' ),
						'post_date_gmt'     => $post_fields_from_csv['post_date_gmt'] ?? current_time( 'mysql', true ),
						'post_content'      => $post_fields_from_csv['post_content'] ?? '',
						'post_title'        => $post_fields_from_csv['post_title'] ?? '',
						'post_excerpt'      => $post_fields_from_csv['post_excerpt'] ?? '',
						'post_status'       => $post_fields_from_csv['post_status'] ?? 'publish',
						'post_name'         => $post_fields_from_csv['post_name'] ?? sanitize_title( (string) ( $post_fields_from_csv['post_title'] ?? '' ) ),
						'post_type'         => $post_type,
						'comment_status'    => $post_fields_from_csv['comment_status'] ?? 'closed',
						'ping_status'       => $post_fields_from_csv['ping_status'] ?? 'closed',
						'post_modified'     => current_time( 'mysql' ),
						'post_modified_gmt' => current_time( 'mysql', true ),
						'post_parent'       => (int) ( $post_fields_from_csv['post_parent'] ?? 0 ),
						'menu_order'        => (int) ( $post_fields_from_csv['menu_order'] ?? 0 ),
						'post_mime_type'    => '',
						'comment_count'     => 0,
					];
				}

				if ( $is_update ) {
					if ( empty( $post_data ) ) {
						$result = 0;
					} else {
						$post_data_formats = [];
						foreach ( array_keys( $post_data ) as $key ) {
							$post_data_formats[] = in_array( $key, [ 'post_author', 'post_parent', 'menu_order' ], true ) ? '%d' : '%s';
						}

						if ( $dry_run ) {
							error_log( "[Dry Run] Would update post ID: {$post_id} with title: " . ( $post_data['post_title'] ?? 'Untitled' ) );
							$dry_run_log[] = sprintf(
								/* translators: 1: post ID, 2: post title */
								__( 'Update post: ID=%1$s, title=%2$s', 'swift-csv' ),
								$post_id,
								$post_data['post_title'] ?? 'Untitled'
							);
							$result = 1; // Simulate success for dry run
						} else {
							$result = $wpdb->update(
								$wpdb->posts,
								$post_data,
								[ 'ID' => $post_id ],
								$post_data_formats,
								[ '%d' ]
							);
						}
					}
				} else {
					$post_data_formats = [];
					foreach ( array_keys( $post_data ) as $key ) {
						$post_data_formats[] = in_array( $key, [ 'post_author', 'post_parent', 'menu_order' ], true ) ? '%d' : '%s';
					}

					if ( $dry_run ) {
						error_log( '[Dry Run] Would create new post with title: ' . ( $post_data['post_title'] ?? 'Untitled' ) );
						$dry_run_log[] = sprintf(
							/* translators: 1: post title */
							__( 'New post: title=%1$s', 'swift-csv' ),
							$post_data['post_title'] ?? 'Untitled'
						);
						$result  = 1; // Simulate success for dry run
						$post_id = 0; // Placeholder for dry run
					} else {
						$result = $wpdb->insert( $wpdb->posts, $post_data, $post_data_formats );
						if ( $result !== false ) {
							$post_id = $wpdb->insert_id;
						}
					}
				}

				if ( $result !== false ) {
					++$processed;

					// Count created vs updated
					if ( $is_update ) {
						++$updated;
					} else {
						++$created;
					}

					// Update GUID for new posts
					if ( ! $is_update ) {
						$wpdb->update(
							$wpdb->posts,
							[ 'guid' => get_permalink( $post_id ) ],
							[ 'ID' => $post_id ],
							[ '%s' ],
							[ '%d' ]
						);
					}

					// Process custom fields and taxonomies like original Swift CSV
					$meta_fields          = [];
					$taxonomies           = [];
					$taxonomy_term_ids    = []; // Store term_ids for reuse
					$normalize_field_name = function ( $name ) {
						$name = trim( (string) $name );
						$name = preg_replace( '/^\xEF\xBB\xBF/', '', $name );
						$name = preg_replace( '/[\x00-\x1F\x7F]/', '', $name );
						return trim( $name );
					};

					for ( $j = 0; $j < count( $headers ); $j++ ) {
						$header_name            = $headers[ $j ] ?? '';
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
							$field_key  = trim( (string) $meta_value );
							if ( str_starts_with( $field_key, '"' ) && str_ends_with( $field_key, '"' ) ) {
								$field_key = substr( $field_key, 1, -1 );
							} elseif ( str_starts_with( $field_key, "'" ) && str_ends_with( $field_key, "'" ) ) {
								$field_key = substr( $field_key, 1, -1 );
							}
							$field_key = preg_replace( '/[\x00-\x1F\x7F]/', '', $field_key );
							$field_key = trim( $field_key );

							if ( $field_name !== '' && strpos( $field_key, 'field_' ) === 0 ) {
								$meta_fields[ '_' . $field_name ] = $field_key;
							}
						}
					}

					for ( $j = 0; $j < count( $headers ); $j++ ) {
						$header_name            = $headers[ $j ] ?? '';
						$header_name_normalized = $normalize_field_name( $header_name );
						if ( $header_name_normalized === '' || $header_name_normalized === 'ID' ) {
							continue;
						}
						if ( in_array( $header_name_normalized, $allowed_post_fields, true ) ) {
							continue;
						}

						if ( empty( trim( $data[ $j ] ?? '' ) ) ) {
							continue; // Skip empty fields
						}

						$meta_value = $data[ $j ];

						// Do not store post fields as meta
						if ( in_array( $header_name_normalized, $allowed_post_fields, true ) ) {
							continue;
						}

						// Check if this is a taxonomy field (tax_ prefix ONLY, not cf_ fields)
						if ( strpos( $header_name_normalized, 'tax_' ) === 0 ) {
							// Handle taxonomy (pipe-separated) - this is for article-taxonomy relationship
							$terms = array_map( 'trim', explode( '|', $meta_value ) );
							// Store by actual taxonomy name (without tax_ prefix)
							$taxonomy_name                = substr( $header_name_normalized, 4 ); // Remove 'tax_'
							$taxonomies[ $taxonomy_name ] = $terms;

							// Extract taxonomy name from header (remove tax_ prefix)
							if ( taxonomy_exists( $taxonomy_name ) ) {
								// Get term_ids for reuse
								$term_ids = [];
								foreach ( $terms as $term_name ) {
									if ( ! empty( $term_name ) ) {
										// Find existing term by name in the specific taxonomy
										$term = get_term_by( 'name', $term_name, $taxonomy_name );
										if ( $term ) {
											$term_ids[] = $term->term_id;
										} else {
										}
									}
								}
								if ( ! empty( $term_ids ) ) {
									$taxonomy_term_ids[ $taxonomy_name ] = $term_ids;
								}
							} else {
							}
						} else {
							// Handle regular custom fields (cf_<field> => <field>) ONLY
							// Skip all other fields - only process cf_ prefixed fields
							if ( strpos( $header_name_normalized, 'cf_' ) !== 0 ) {
								continue; // Skip non-cf_ fields
							}

							$clean_field_name                 = substr( $header_name_normalized, 3 ); // Remove cf_
							$meta_fields[ $clean_field_name ] = (string) $meta_value;
						}
					}

					// Process taxonomies
					foreach ( $taxonomies as $taxonomy => $terms ) {
						if ( ! empty( $terms ) ) {
							// Debug log for taxonomy processing
							error_log( "[Swift CSV] Processing taxonomy: {$taxonomy}, format: {$taxonomy_format}" );

							// Use WordPress API to set term relationships (handles tt_id and counts)
							$term_ids = [];
							foreach ( $terms as $term_value ) {
								$term_value = trim( (string) $term_value );
								if ( $term_value === '' ) {
									continue;
								}

								error_log( "[Swift CSV] Processing term value: '{$term_value}' with format: {$taxonomy_format}" );

								// Handle different taxonomy formats with mixed data support
								if ( $taxonomy_format === 'id' ) {
									// Handle term IDs
									$term_id = intval( $term_value );
									error_log( "[Swift CSV] Looking up term by ID: {$term_id}" );

									// Check if the term value is actually a valid ID
									if ( $term_id === 0 ) {
										// For mixed format, treat numeric values as names
										if ( isset( $taxonomy_format_validation[ $taxonomy ] ) && $taxonomy_format_validation[ $taxonomy ]['mixed'] ) {
											error_log( "[Swift CSV] Mixed format detected: treating '{$term_value}' as term name" );
											$term = get_term_by( 'name', $term_value, $taxonomy );
											if ( ! $term ) {
												error_log( "[Swift CSV] Term not found, creating new term: '{$term_value}'" );
												$created = wp_insert_term( $term_value, $taxonomy );
												if ( is_wp_error( $created ) ) {
													error_log( "Failed to create term '$term_value' in taxonomy '$taxonomy'" );
													continue;
												}
												$term_ids[] = (int) $created['term_id'];
												error_log( "[Swift CSV] Created new term: '{$term_value}' with ID: {$created['term_id']}" );
											} else {
												$term_ids[] = (int) $term->term_id;
												error_log( "[Swift CSV] Found existing term by name: '{$term_value}' with ID: {$term->term_id}" );
											}
										} else {
											error_log( "[Swift CSV] ERROR: Term value '{$term_value}' is not a valid ID. Expected numeric ID when 'Term IDs' format is selected." );
											// Skip this term as it's not a valid ID
											continue;
										}
									} else {
										// Valid ID, process normally
										$term = get_term( $term_id, $taxonomy );
										if ( $term && ! is_wp_error( $term ) ) {
											$term_ids[] = $term_id;
											error_log( "[Swift CSV] Found term by ID: {$term_id} -> {$term->name}" );
										} else {
											error_log( "[Swift CSV] Invalid term ID: {$term_id} in taxonomy '{$taxonomy}'" );
										}
									}
								} else {
									// Handle term names (default)
									error_log( "[Swift CSV] Looking up term by name: '{$term_value}'" );
									$term = get_term_by( 'name', $term_value, $taxonomy );
									if ( ! $term ) {
										error_log( "[Swift CSV] Term not found, creating new term: '{$term_value}'" );
										$created = wp_insert_term( $term_value, $taxonomy );
										if ( is_wp_error( $created ) ) {
											error_log( "Failed to create term '$term_value' in taxonomy '$taxonomy'" );
											continue;
										}
										$term_ids[] = (int) $created['term_id'];
										error_log( "[Swift CSV] Created new term: '{$term_value}' with ID: {$created['term_id']}" );
									} else {
										$term_ids[] = (int) $term->term_id;
										error_log( "[Swift CSV] Found existing term by name: '{$term_value}' with ID: {$term->term_id}" );
									}
								}
							}
						}

						if ( ! empty( $term_ids ) ) {
							if ( $dry_run ) {
								error_log( "[Dry Run] Would set terms for post {$post_id}: " . implode( ', ', $term_ids ) );
								// Log each term for Dry Run
								foreach ( $term_ids as $term_id ) {
									$term = get_term( $term_id, $taxonomy );
									if ( $term && ! is_wp_error( $term ) ) {
										$dry_run_log[] = sprintf(
											/* translators: 1: term name, 2: term ID, 3: taxonomy name */
											__( 'Existing term: %1$s (ID: %2$s, taxonomy: %3$s)', 'swift-csv' ),
											$term->name,
											$term_id,
											$taxonomy
										);
									}
								}
							} else {
								wp_set_post_terms( $post_id, $term_ids, $taxonomy, false );
								error_log( "[Swift CSV] Set terms for post {$post_id}: " . implode( ', ', $term_ids ) );
							}
						}
					}
					// Process custom fields with multi-value support
					foreach ( $meta_fields as $key => $value ) {
						// Skip empty values
						if ( $value === '' || $value === null ) {
							continue;
						}

						if ( $dry_run ) {
							error_log( "[Dry Run] Would process custom field: {$key} = {$value}" );

							// Handle multi-value custom fields (pipe-separated)
							if ( strpos( $value, '|' ) !== false ) {
								// Add each value separately
								$values = array_map( 'trim', explode( '|', $value ) );
								foreach ( $values as $single_value ) {
									if ( $single_value !== '' ) {
										$dry_run_log[] = sprintf(
											/* translators: 1: field name, 2: field value */
											__( 'Custom field (multi-value): %1$s = %2$s', 'swift-csv' ),
											$key,
											$single_value
										);
									}
								}
							} else {
								// Single value (including serialized strings)
								$dry_run_log[] = sprintf(
									/* translators: 1: field name, 2: field value */
									__( 'Custom field: %1$s = %2$s', 'swift-csv' ),
									$key,
									$value
								);
							}
						} else {
							// Always replace existing meta for this key to ensure update works even if meta row doesn't exist.
							$wpdb->query(
								$wpdb->prepare(
									"DELETE FROM {$wpdb->postmeta} 
	                         WHERE post_id = %d 
	                         AND meta_key = %s",
									$post_id,
									$key
								)
							);

							// Handle multi-value custom fields (pipe-separated)
							if ( strpos( $value, '|' ) !== false ) {
								// Add each value separately
								$values = array_map( 'trim', explode( '|', $value ) );
								foreach ( $values as $single_value ) {
									if ( $single_value !== '' ) {
										$wpdb->insert(
											$wpdb->postmeta,
											[
												'post_id'  => $post_id,
												'meta_key' => $key,
												'meta_value' => $single_value,
											],
											[ '%d', '%s', '%s' ]
										);
									}
								}
							} else {
								// Single value (including serialized strings)
								$wpdb->insert(
									$wpdb->postmeta,
									[
										'post_id'    => $post_id,
										'meta_key'   => $key,
										'meta_value' => is_string( $value ) ? $value : maybe_serialize( $value ),
									],
									[ '%d', '%s', '%s' ]
								);
							}
						}
					}

					// Custom field processing hook for extensions
					/**
					 * Action for processing custom fields during import
					 *
					 * Allows extensions to process custom fields with their own logic.
					 * This hook is called after basic field processing is complete.
					 *
					 * @since 0.9.0
					 * @param int $post_id The ID of the created/updated post
					 * @param array $meta_fields Array of meta fields to process
					 */
					do_action( 'swift_csv_process_custom_fields', $post_id, $meta_fields );
				} else {
					++$errors;
				}
			} catch ( Exception $e ) {
				++$errors;
			}
		}

		$next_row = $start_row + $processed; // Use actual processed count
		$continue = $next_row < $total_rows;

		// Cleanup temporary file when import is complete
		if ( ! $continue && $file_path && file_exists( $file_path ) ) {
			unlink( $file_path );
		}

		wp_send_json(
			[
				'success'            => true,
				'processed'          => $next_row,
				'total'              => $total_rows,
				'batch_processed'    => $processed,
				'batch_errors'       => $errors,
				'created'            => $created,
				'updated'            => $updated,
				'errors'             => $errors,
				'cumulative_created' => $previous_created + $created,
				'cumulative_updated' => $previous_updated + $updated,
				'cumulative_errors'  => $previous_errors + $errors,
				'progress'           => round( ( $next_row / $total_rows ) * 100, 2 ),
				'continue'           => $continue,
				'dry_run'            => $dry_run,
				'dry_run_log'        => $dry_run_log,
			]
		);
	}

	/**
	 * Verify nonce for AJAX request.
	 *
	 * @since 0.9.0
	 * @return bool True if nonce is valid.
	 */
	private function verify_nonce_or_send_error_and_cleanup() {
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'swift_csv_ajax_nonce' ) ) {
			return true;
		}

		$file_path = sanitize_text_field( wp_unslash( $_POST['file_path'] ?? '' ) );
		if ( $file_path && file_exists( $file_path ) ) {
			unlink( $file_path );
		}

		wp_send_json( [ 'error' => 'Security check failed' ] );
		return false;
	}

	/**
	 * Read uploaded CSV content from request.
	 *
	 * @since 0.9.0
	 * @param string $file_path Temporary file path for cleanup.
	 * @return string|null CSV content or null on error (sends JSON response).
	 */
	private function read_uploaded_csv_content_or_send_error_and_cleanup( $file_path ) {
		// Handle file upload directly
		if ( ! isset( $_FILES['csv_file'] ) ) {
			// Cleanup file on error
			if ( $file_path && file_exists( $file_path ) ) {
				unlink( $file_path );
			}
			wp_send_json(
				[
					'success' => false,
					'error'   => 'No file uploaded',
				]
			);
			return null;
		}

		$file = $_FILES['csv_file'];

		// Validate file
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			// Cleanup file on error
			if ( $file_path && file_exists( $file_path ) ) {
				unlink( $file_path );
			}
			wp_send_json(
				[
					'success' => false,
					'error'   => 'Upload error: ' . $file['error'],
				]
			);
			return null;
		}

		// Read CSV directly from uploaded file
		$csv_content = file_get_contents( $file['tmp_name'] );
		$csv_content = str_replace( [ "\r\n", "\r" ], "\n", $csv_content ); // Normalize line endings

		return $csv_content;
	}

	/**
	 * Parse CSV content line by line to handle quoted fields with newlines.
	 *
	 * @since 0.9.0
	 * @param string $csv_content CSV content.
	 * @return array<int, string>
	 */
	private function parse_csv_lines_preserving_quoted_newlines( $csv_content ) {
		$lines        = [];
		$current_line = '';
		$in_quotes    = false;

		foreach ( explode( "\n", $csv_content ) as $line ) {
			// Count quotes to determine if we're inside a quoted field
			$quote_count = substr_count( $line, '"' );

			if ( $in_quotes ) {
				$current_line .= "\n" . $line;
			} else {
				$current_line = $line;
			}

			// Toggle quote state (odd number of quotes means we're inside quotes)
			if ( $quote_count % 2 === 1 ) {
				$in_quotes = ! $in_quotes;
			}

			// Only add line if we're not inside quotes
			if ( ! $in_quotes ) {
				$lines[]      = $current_line;
				$current_line = '';
			}
		}

		return $lines;
	}

	/**
	 * Detect CSV delimiter from first line.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines.
	 * @return string
	 */
	private function detect_csv_delimiter( $lines ) {
		$first_line = $lines[0] ?? '';
		$delimiters = [ ',', ';', "\t" ];
		$delimiter  = ',';

		foreach ( $delimiters as $delim ) {
			if ( substr_count( $first_line, $delim ) > substr_count( $first_line, $delimiter ) ) {
				$delimiter = $delim;
			}
		}

		return $delimiter;
	}

	/**
	 * Read CSV header row and normalize it.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines (will consume the first line).
	 * @param string             $delimiter CSV delimiter.
	 * @return array<int, string>
	 */
	private function read_and_normalize_headers( &$lines, $delimiter ) {
		$headers = str_getcsv( array_shift( $lines ), $delimiter );
		// Normalize headers - remove BOM and control characters
		$headers = array_map(
			function ( $header ) {
				// Remove BOM (UTF-8 BOM is \xEF\xBB\xBF)
				$header = preg_replace( '/^\xEF\xBB\xBF/', '', $header );
				// Remove other control characters
				return preg_replace( '/[\x00-\x1F\x7F]/u', '', trim( $header ?? '' ) );
			},
			$headers
		);

		return $headers;
	}

	/**
	 * Detect taxonomy format from the first data row and validate UI format consistency.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines.
	 * @param string             $delimiter CSV delimiter.
	 * @param array<int, string> $headers CSV headers.
	 * @param string             $taxonomy_format Taxonomy format selected in UI.
	 * @param string             $file_path Temporary file path for cleanup.
	 * @return array<string, array>|null Taxonomy validation data or null on error (sends JSON response).
	 */
	private function detect_taxonomy_format_validation_or_send_error_and_cleanup( $lines, $delimiter, $headers, $taxonomy_format, $file_path ) {
		$taxonomy_format_validation = [];
		$first_row_processed        = false;

		// Process first row for format detection
		$data = [];
		foreach ( $lines as $line ) {
			$row = str_getcsv( $line, $delimiter );
			if ( count( $row ) !== count( $headers ) ) {
				continue; // Skip malformed rows
			}
			$data[] = $row;

			// Process taxonomies for format detection on first data row only
			if ( ! $first_row_processed ) {
				foreach ( $headers as $j => $header_name ) {
					$header_name_normalized = strtolower( trim( $header_name ) );

					if ( strpos( $header_name_normalized, 'tax_' ) === 0 ) {
						$taxonomy_name = substr( $header_name_normalized, 4 ); // Remove tax_

						// Get taxonomy object to validate
						$taxonomy_obj = get_taxonomy( $taxonomy_name );
						if ( ! $taxonomy_obj ) {
							continue; // Skip invalid taxonomy
						}

						$meta_value = $row[ $j ] ?? '';
						if ( $meta_value !== '' ) {
							$term_values = array_map( 'trim', explode( ',', $meta_value ) );
							$all_numeric = true;
							$all_string  = true;
							$mixed       = false;

							foreach ( $term_values as $term_val ) {
								if ( $term_val === '' ) {
									continue;
								}

								if ( is_numeric( $term_val ) ) {
									$all_string = false;
								} else {
									$all_numeric = false;
								}

								if ( ! $all_numeric && ! $all_string ) {
									$mixed = true;
									break;
								}
							}

							$taxonomy_format_validation[ $taxonomy_name ] = [
								'all_numeric'   => $all_numeric,
								'all_string'    => $all_string,
								'mixed'         => $mixed,
								'sample_values' => $term_values,
							];
						}
					}
				}
				$first_row_processed = true;
			}
		}

		// Validate format consistency
		foreach ( $taxonomy_format_validation as $taxonomy_name => $validation ) {
			// Check for format mismatches
			if ( $taxonomy_format === 'name' && $validation['all_numeric'] ) {
				// Cleanup file on error
				if ( $file_path && file_exists( $file_path ) ) {
					unlink( $file_path );
				}
				wp_send_json(
					[
						'success' => false,
						'error'   => sprintf(
							/* translators: 1: taxonomy name, 2: UI format, 3: sample values */
							__( 'Format mismatch detected for taxonomy "%1$s". UI is set to "Names" but CSV contains only numeric values: %2$s. Please check your data format.', 'swift-csv' ),
							$taxonomy_name,
							implode( ', ', $validation['sample_values'] )
						),
					]
				);
				return null;
			}

			if ( $taxonomy_format === 'id' && $validation['all_string'] ) {
				// Cleanup file on error
				if ( $file_path && file_exists( $file_path ) ) {
					unlink( $file_path );
				}
				wp_send_json(
					[
						'success' => false,
						'error'   => sprintf(
							/* translators: 1: taxonomy name, 2: UI format, 3: sample values */
							__( 'Format mismatch detected for taxonomy "%1$s". UI is set to "Term IDs" but CSV contains only text values: %2$s. Please check your data format.', 'swift-csv' ),
							$taxonomy_name,
							implode( ', ', $validation['sample_values'] )
						),
					]
				);
				return null;
			}
		}

		return $taxonomy_format_validation;
	}

	/**
	 * Get allowed WP post fields that can be imported from CSV.
	 *
	 * @since 0.9.0
	 * @return array<int, string>
	 */
	private function get_allowed_post_fields() {
		return [
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
	}

	/**
	 * Ensure CSV has the required ID column.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $headers CSV headers.
	 * @param string             $file_path Temporary file path for cleanup.
	 * @return int|null ID column index or null on error (sends JSON response).
	 */
	private function ensure_id_column_or_send_error_and_cleanup( $headers, $file_path ) {
		$id_col = array_search( 'ID', $headers, true );
		if ( $id_col !== false ) {
			return $id_col;
		}

		// Cleanup file on error
		if ( $file_path && file_exists( $file_path ) ) {
			unlink( $file_path );
		}
		wp_send_json(
			[
				'success' => false,
				'error'   => 'Invalid CSV format: ID column is required',
			]
		);

		return null;
	}

	/**
	 * Count actual data rows (exclude empty lines).
	 *
	 * @since 0.9.0
	 * @param array<int, string> $lines CSV lines.
	 * @return int
	 */
	private function count_total_rows( $lines ) {
		$total_rows = 0;
		foreach ( $lines as $line ) {
			if ( ! empty( trim( $line ) ) ) {
				++$total_rows;
			}
		}
		return $total_rows;
	}

	/**
	 * Get cumulative counts from previous chunks.
	 *
	 * @since 0.9.0
	 * @return array{created:int,updated:int,errors:int}
	 */
	private function get_cumulative_counts() {
		$previous_created = isset( $_POST['cumulative_created'] ) ? intval( $_POST['cumulative_created'] ) : 0;
		$previous_updated = isset( $_POST['cumulative_updated'] ) ? intval( $_POST['cumulative_updated'] ) : 0;
		$previous_errors  = isset( $_POST['cumulative_errors'] ) ? intval( $_POST['cumulative_errors'] ) : 0;

		return [
			'created' => $previous_created,
			'updated' => $previous_updated,
			'errors'  => $previous_errors,
		];
	}

	/**
	 * Parse a CSV row.
	 *
	 * @since 0.9.0
	 * @param string $line CSV line.
	 * @param string $delimiter CSV delimiter.
	 * @return array
	 */
	private function parse_csv_row( $line, $delimiter ) {
		return str_getcsv( $line, $delimiter );
	}

	/**
	 * Check if a CSV line is empty.
	 *
	 * @since 0.9.0
	 * @param string $line CSV line.
	 * @return bool
	 */
	private function is_empty_csv_line( $line ) {
		return empty( trim( $line ) );
	}

	/**
	 * Collect allowed post fields from a CSV row (header-driven).
	 *
	 * @since 0.9.0
	 * @param array<int, string> $headers CSV headers.
	 * @param array              $data CSV row data.
	 * @param array<int, string> $allowed_post_fields Allowed WP post fields.
	 * @return array<string, string>
	 */
	private function collect_post_fields_from_csv_row( $headers, $data, $allowed_post_fields ) {
		$post_fields_from_csv = [];
		for ( $j = 0; $j < count( $headers ); $j++ ) {
			$header = trim( (string) $headers[ $j ] );
			if ( $header === '' || $header === 'ID' ) {
				continue;
			}
			if ( ! in_array( $header, $allowed_post_fields, true ) ) {
				continue;
			}
			if ( ! array_key_exists( $j, $data ) ) {
				continue;
			}
			$value = (string) $data[ $j ];
			if ( $value === '' ) {
				continue;
			}
			if ( str_starts_with( $value, '"' ) && str_ends_with( $value, '"' ) ) {
				$value = substr( $value, 1, -1 );
				$value = str_replace( '""', '"', $value );
			}
			$post_fields_from_csv[ $header ] = $value;
		}
		return $post_fields_from_csv;
	}

	/**
	 * Find existing post ID for update.
	 *
	 * @since 0.9.0
	 * @param wpdb   $wpdb WordPress DB instance.
	 * @param string $update_existing Update flag.
	 * @param string $post_type Post type.
	 * @param string $post_id_from_csv Post ID from CSV.
	 * @return array{post_id:int|null,is_update:bool}
	 */
	private function find_existing_post_for_update( $wpdb, $update_existing, $post_type, $post_id_from_csv ) {
		$post_id   = null;
		$is_update = false;

		if ( $update_existing === '1' && ! empty( $post_id_from_csv ) ) {
			$existing_post_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = %s 
                 AND ID = %d 
                 LIMIT 1",
					$post_type,
					$post_id_from_csv
				)
			);

			if ( $existing_post_id ) {
				$post_id   = (int) $existing_post_id;
				$is_update = true;
			}
		}

		return [
			'post_id'   => $post_id,
			'is_update' => $is_update,
		];
	}

	/**
	 * Determine whether to skip row due to missing title.
	 *
	 * @since 0.9.0
	 * @param string                $update_existing Update flag.
	 * @param array<string, string> $post_fields_from_csv Post fields.
	 * @return bool
	 */
	private function should_skip_row_due_to_missing_title( $update_existing, $post_fields_from_csv ) {
		if ( $update_existing !== '1' && empty( $post_fields_from_csv['post_title'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Setup DB session for import process.
	 *
	 * @since 0.9.0
	 * @param wpdb $wpdb WordPress DB instance.
	 * @return void
	 */
	private function setup_db_session( $wpdb ) {
		// Disable locks
		$wpdb->query( 'SET SESSION autocommit = 1' );
		$wpdb->query( 'SET SESSION innodb_lock_wait_timeout = 1' );
	}

	/**
	 * Parse PHP ini size string to bytes.
	 *
	 * @since 0.9.0
	 * @param string $size The size string (e.g., '10M', '1G')
	 * @return int Size in bytes
	 */
	private function parse_ini_size( $size ) {
		$unit  = strtoupper( substr( $size, -1 ) );
		$value = (int) substr( $size, 0, -1 );

		switch ( $unit ) {
			case 'G':
				return $value * 1024 * 1024 * 1024;
			case 'M':
				return $value * 1024 * 1024;
			case 'K':
				return $value * 1024;
			default:
				return (int) $size;
		}
	}
}

/**
 * Get Ajax import singleton instance.
 *
 * @since 0.9.0
 * @return Swift_CSV_Ajax_Import
 */
function swift_csv_ajax_import_instance() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new Swift_CSV_Ajax_Import();
	}
	return $instance;
}

/**
 * Handle CSV file upload via AJAX
 *
 * @since 0.9.0
 * @return void Sends JSON response
 */
function swift_csv_ajax_upload_handler() {
	swift_csv_ajax_import_instance()->upload_handler();
}

/**
 * Handle CSV import processing via AJAX
 *
 * @since 0.9.0
 * @return void Sends JSON response with import results
 */
function swift_csv_ajax_import_handler() {
	swift_csv_ajax_import_instance()->import_handler();
	return;

	global $wpdb;

	// Verify nonce
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'swift_csv_ajax_nonce' ) ) {
		// Cleanup file on error
		$file_path = sanitize_text_field( wp_unslash( $_POST['file_path'] ?? '' ) );
		if ( $file_path && file_exists( $file_path ) ) {
			unlink( $file_path );
		}
		wp_send_json( [ 'error' => 'Security check failed' ] );
		return;
	}

	// Disable locks
	$wpdb->query( 'SET SESSION autocommit = 1' );
	$wpdb->query( 'SET SESSION innodb_lock_wait_timeout = 1' );

	$start_row       = intval( $_POST['start_row'] ?? 0 );
	$batch_size      = 10;
	$post_type       = sanitize_text_field( wp_unslash( $_POST['post_type'] ?? 'post' ) );
	$update_existing = sanitize_text_field( wp_unslash( $_POST['update_existing'] ?? '0' ) );
	$taxonomy_format = sanitize_text_field( wp_unslash( $_POST['taxonomy_format'] ?? 'name' ) );
	$dry_run         = sanitize_text_field( wp_unslash( $_POST['dry_run'] ?? '0' ) ) === '1';

	// Get file path for cleanup
	$file_path = sanitize_text_field( wp_unslash( $_POST['file_path'] ?? '' ) );

	// Advanced taxonomy format detection and validation
	$taxonomy_format_validation = [];
	$first_row_processed        = false;

	// Initialize counters
	$created     = 0;
	$updated     = 0;
	$errors      = 0;
	$dry_run_log = [];

	// Handle file upload directly
	if ( ! isset( $_FILES['csv_file'] ) ) {
		// Cleanup file on error
		if ( $file_path && file_exists( $file_path ) ) {
			unlink( $file_path );
		}
		wp_send_json(
			[
				'success' => false,
				'error'   => 'No file uploaded',
			]
		);
		return;
	}

	$file = $_FILES['csv_file'];

	// Validate file
	if ( $file['error'] !== UPLOAD_ERR_OK ) {
		// Cleanup file on error
		if ( $file_path && file_exists( $file_path ) ) {
			unlink( $file_path );
		}
		wp_send_json(
			[
				'success' => false,
				'error'   => 'Upload error: ' . $file['error'],
			]
		);
		return;
	}

	// Read CSV directly from uploaded file
	$csv_content = file_get_contents( $file['tmp_name'] );
	$csv_content = str_replace( [ "\r\n", "\r" ], "\n", $csv_content ); // Normalize line endings

	// Parse CSV line by line to handle quoted fields with newlines
	$lines        = [];
	$current_line = '';
	$in_quotes    = false;

	foreach ( explode( "\n", $csv_content ) as $line ) {
		// Count quotes to determine if we're inside a quoted field
		$quote_count = substr_count( $line, '"' );

		if ( $in_quotes ) {
			$current_line .= "\n" . $line;
		} else {
			$current_line = $line;
		}

		// Toggle quote state (odd number of quotes means we're inside quotes)
		if ( $quote_count % 2 === 1 ) {
			$in_quotes = ! $in_quotes;
		}

		// Only add line if we're not inside quotes
		if ( ! $in_quotes ) {
			$lines[]      = $current_line;
			$current_line = '';
		}
	}

	// Auto-detect delimiter
	$first_line = $lines[0] ?? '';
	$delimiters = [ ',', ';', "\t" ];
	$delimiter  = ',';

	foreach ( $delimiters as $delim ) {
		if ( substr_count( $first_line, $delim ) > substr_count( $first_line, $delimiter ) ) {
			$delimiter = $delim;
		}
	}

	$debug_logging = ( defined( 'SWIFT_CSV_DEBUG' ) && SWIFT_CSV_DEBUG ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );

	$headers = str_getcsv( array_shift( $lines ), $delimiter );
	// Normalize headers - remove BOM and control characters
	$headers = array_map(
		function ( $header ) {
			// Remove BOM (UTF-8 BOM is \xEF\xBB\xBF)
			$header = preg_replace( '/^\xEF\xBB\xBF/', '', $header );
			// Remove other control characters
			return preg_replace( '/[\x00-\x1F\x7F]/u', '', trim( $header ?? '' ) );
		},
		$headers
	);

	// Advanced taxonomy format detection and validation
	$taxonomy_format_validation = [];
	$first_row_processed        = false;

	// Process first row for format detection
	$data = [];
	foreach ( $lines as $line ) {
		$row = str_getcsv( $line, $delimiter );
		if ( count( $row ) !== count( $headers ) ) {
			continue; // Skip malformed rows
		}
		$data[] = $row;

		// Process taxonomies for format detection on first data row only
		if ( ! $first_row_processed ) {
			foreach ( $headers as $j => $header_name ) {
				$header_name_normalized = strtolower( trim( $header_name ) );

				if ( strpos( $header_name_normalized, 'tax_' ) === 0 ) {
					$taxonomy_name = substr( $header_name_normalized, 4 ); // Remove tax_

					// Get taxonomy object to validate
					$taxonomy_obj = get_taxonomy( $taxonomy_name );
					if ( ! $taxonomy_obj ) {
						continue; // Skip invalid taxonomy
					}

					$meta_value = $row[ $j ] ?? '';
					if ( $meta_value !== '' ) {
						$term_values = array_map( 'trim', explode( ',', $meta_value ) );
						$all_numeric = true;
						$all_string  = true;
						$mixed       = false;

						foreach ( $term_values as $term_val ) {
							if ( $term_val === '' ) {
								continue;
							}

							if ( is_numeric( $term_val ) ) {
								$all_string = false;
							} else {
								$all_numeric = false;
							}

							if ( ! $all_numeric && ! $all_string ) {
								$mixed = true;
								break;
							}
						}

						$taxonomy_format_validation[ $taxonomy_name ] = [
							'all_numeric'   => $all_numeric,
							'all_string'    => $all_string,
							'mixed'         => $mixed,
							'sample_values' => $term_values,
						];
					}
				}
			}
			$first_row_processed = true;
		}
	}

	// Validate format consistency
	foreach ( $taxonomy_format_validation as $taxonomy_name => $validation ) {
		// Check for format mismatches
		if ( $taxonomy_format === 'name' && $validation['all_numeric'] ) {
			// Cleanup file on error
			if ( $file_path && file_exists( $file_path ) ) {
				unlink( $file_path );
			}
			wp_send_json(
				[
					'success' => false,
					'error'   => sprintf(
						/* translators: 1: taxonomy name, 2: UI format, 3: sample values */
						__( 'Format mismatch detected for taxonomy "%1$s". UI is set to "Names" but CSV contains only numeric values: %2$s. Please check your data format.', 'swift-csv' ),
						$taxonomy_name,
						implode( ', ', $validation['sample_values'] )
					),
				]
			);
			return;
		}

		if ( $taxonomy_format === 'id' && $validation['all_string'] ) {
			// Cleanup file on error
			if ( $file_path && file_exists( $file_path ) ) {
				unlink( $file_path );
			}
			wp_send_json(
				[
					'success' => false,
					'error'   => sprintf(
						/* translators: 1: taxonomy name, 2: UI format, 3: sample values */
						__( 'Format mismatch detected for taxonomy "%1$s". UI is set to "Term IDs" but CSV contains only text values: %2$s. Please check your data format.', 'swift-csv' ),
						$taxonomy_name,
						implode( ', ', $validation['sample_values'] )
					),
				]
			);
			return;
		}
	}

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

	$id_col = array_search( 'ID', $headers, true );
	if ( $id_col === false ) {
		// Cleanup file on error
		if ( $file_path && file_exists( $file_path ) ) {
			unlink( $file_path );
		}
		wp_send_json(
			[
				'success' => false,
				'error'   => 'Invalid CSV format: ID column is required',
			]
		);
		return;
	}

	// Count actual data rows (exclude empty lines)
	$total_rows = 0;
	foreach ( $lines as $line ) {
		if ( ! empty( trim( $line ) ) ) {
			++$total_rows;
		}
	}

	$processed = 0;
	$errors    = 0;

	// Get cumulative counts from previous chunks
	$previous_created = isset( $_POST['cumulative_created'] ) ? intval( $_POST['cumulative_created'] ) : 0;
	$previous_updated = isset( $_POST['cumulative_updated'] ) ? intval( $_POST['cumulative_updated'] ) : 0;
	$previous_errors  = isset( $_POST['cumulative_errors'] ) ? intval( $_POST['cumulative_errors'] ) : 0;

	$created = 0;
	$updated = 0;

	for ( $i = $start_row; $i < min( $start_row + $batch_size, $total_rows ); $i++ ) {
		// Skip empty lines only
		if ( empty( trim( $lines[ $i ] ) ) ) {
			++$processed; // Count empty lines as processed to avoid infinite loop
			continue;
		}

		$data = str_getcsv( $lines[ $i ], $delimiter );

		// First check if this looks like an ID row (first column is numeric ID)
		$first_col = $data[0] ?? '';
		if ( is_numeric( $first_col ) && strlen( $first_col ) <= 6 ) {
			// This is normal - most rows have ID in first column
			// Don't skip - process the actual data
		} else {
			// Continue processing anyway
		}

		$post_id_from_csv = $first_col;

		// Collect post fields from CSV (header-driven)
		$post_fields_from_csv = [];
		for ( $j = 0; $j < count( $headers ); $j++ ) {
			$header = trim( (string) $headers[ $j ] );
			if ( $header === '' || $header === 'ID' ) {
				continue;
			}
			if ( ! in_array( $header, $allowed_post_fields, true ) ) {
				continue;
			}
			if ( ! array_key_exists( $j, $data ) ) {
				continue;
			}
			$value = (string) $data[ $j ];
			if ( $value === '' ) {
				continue;
			}
			if ( str_starts_with( $value, '"' ) && str_ends_with( $value, '"' ) ) {
				$value = substr( $value, 1, -1 );
				$value = str_replace( '""', '"', $value );
			}
			$post_fields_from_csv[ $header ] = $value;
		}

		// Check for existing post by CSV ID (only if update_existing is checked)
		$existing_post_id = null;
		$is_update        = false;

		if ( $update_existing === '1' && ! empty( $post_id_from_csv ) ) {
			// Use original Swift CSV logic for finding existing posts
			$existing_post_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = %s 
                 AND ID = %d 
                 LIMIT 1",
					$post_type,
					$post_id_from_csv
				)
			);

			if ( $existing_post_id ) {
				$post_id   = $existing_post_id;
				$is_update = true;
			}
		}

		// Validation
		if ( $update_existing !== '1' ) {
			if ( empty( $post_fields_from_csv['post_title'] ) ) {
				continue;
			}
		}

		try {
			// Direct SQL insert or update (update only fields provided by CSV)
			$post_data = [];

			if ( $is_update ) {
				// Update only provided post fields
				foreach ( $post_fields_from_csv as $key => $value ) {
					$post_data[ $key ] = $value;
				}
				// Keep modification timestamps consistent
				$post_data['post_modified']     = current_time( 'mysql' );
				$post_data['post_modified_gmt'] = current_time( 'mysql', true );
			} else {
				// Insert with defaults + provided values

				// Handle post_author - convert display name to user ID
				$post_author_id = 1; // Default to admin
				if ( ! empty( $post_fields_from_csv['post_author'] ) ) {
					$author_display_name = trim( $post_fields_from_csv['post_author'] );
					if ( is_numeric( $author_display_name ) ) {
						// If it's already a numeric ID, use it directly
						$post_author_id = (int) $author_display_name;
					} else {
						// Try to find user by display name
						$author_user = get_user_by( 'display_name', $author_display_name );
						if ( $author_user ) {
							$post_author_id = $author_user->ID;
						}
						// If not found, fallback to current user or admin
						elseif ( get_current_user_id() ) {
							$post_author_id = get_current_user_id();
						}
					}
				} elseif ( get_current_user_id() ) {
					$post_author_id = get_current_user_id();
				}

				$post_data = [
					'post_author'       => $post_author_id,
					'post_date'         => $post_fields_from_csv['post_date'] ?? current_time( 'mysql' ),
					'post_date_gmt'     => $post_fields_from_csv['post_date_gmt'] ?? current_time( 'mysql', true ),
					'post_content'      => $post_fields_from_csv['post_content'] ?? '',
					'post_title'        => $post_fields_from_csv['post_title'] ?? '',
					'post_excerpt'      => $post_fields_from_csv['post_excerpt'] ?? '',
					'post_status'       => $post_fields_from_csv['post_status'] ?? 'publish',
					'post_name'         => $post_fields_from_csv['post_name'] ?? sanitize_title( (string) ( $post_fields_from_csv['post_title'] ?? '' ) ),
					'post_type'         => $post_type,
					'comment_status'    => $post_fields_from_csv['comment_status'] ?? 'closed',
					'ping_status'       => $post_fields_from_csv['ping_status'] ?? 'closed',
					'post_modified'     => current_time( 'mysql' ),
					'post_modified_gmt' => current_time( 'mysql', true ),
					'post_parent'       => (int) ( $post_fields_from_csv['post_parent'] ?? 0 ),
					'menu_order'        => (int) ( $post_fields_from_csv['menu_order'] ?? 0 ),
					'post_mime_type'    => '',
					'comment_count'     => 0,
				];
			}

			if ( $is_update ) {
				if ( empty( $post_data ) ) {
					$result = 0;
				} else {
					$post_data_formats = [];
					foreach ( array_keys( $post_data ) as $key ) {
						$post_data_formats[] = in_array( $key, [ 'post_author', 'post_parent', 'menu_order' ], true ) ? '%d' : '%s';
					}

					if ( $dry_run ) {
						error_log( "[Dry Run] Would update post ID: {$post_id} with title: " . ( $post_data['post_title'] ?? 'Untitled' ) );
						$dry_run_log[] = sprintf(
							/* translators: 1: post ID, 2: post title */
							__( 'Update post: ID=%1$s, title=%2$s', 'swift-csv' ),
							$post_id,
							$post_data['post_title'] ?? 'Untitled'
						);
						$result = 1; // Simulate success for dry run
					} else {
						$result = $wpdb->update(
							$wpdb->posts,
							$post_data,
							[ 'ID' => $post_id ],
							$post_data_formats,
							[ '%d' ]
						);
					}
				}
			} else {
				$post_data_formats = [];
				foreach ( array_keys( $post_data ) as $key ) {
					$post_data_formats[] = in_array( $key, [ 'post_author', 'post_parent', 'menu_order' ], true ) ? '%d' : '%s';
				}

				if ( $dry_run ) {
					error_log( '[Dry Run] Would create new post with title: ' . ( $post_data['post_title'] ?? 'Untitled' ) );
					$dry_run_log[] = sprintf(
						/* translators: 1: post title */
						__( 'New post: title=%1$s', 'swift-csv' ),
						$post_data['post_title'] ?? 'Untitled'
					);
					$result  = 1; // Simulate success for dry run
					$post_id = 0; // Placeholder for dry run
				} else {
					$result = $wpdb->insert( $wpdb->posts, $post_data, $post_data_formats );
					if ( $result !== false ) {
						$post_id = $wpdb->insert_id;
					}
				}
			}

			if ( $result !== false ) {
				++$processed;

				// Count created vs updated
				if ( $is_update ) {
					++$updated;
				} else {
					++$created;
				}

				// Update GUID for new posts
				if ( ! $is_update ) {
					$wpdb->update(
						$wpdb->posts,
						[ 'guid' => get_permalink( $post_id ) ],
						[ 'ID' => $post_id ],
						[ '%s' ],
						[ '%d' ]
					);
				}

				// Process custom fields and taxonomies like original Swift CSV
				$meta_fields          = [];
				$taxonomies           = [];
				$taxonomy_term_ids    = []; // Store term_ids for reuse
				$normalize_field_name = function ( $name ) {
					$name = trim( (string) $name );
					$name = preg_replace( '/^\xEF\xBB\xBF/', '', $name );
					$name = preg_replace( '/[\x00-\x1F\x7F]/', '', $name );
					return trim( $name );
				};

				for ( $j = 0; $j < count( $headers ); $j++ ) {
					$header_name            = $headers[ $j ] ?? '';
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
						$field_key  = trim( (string) $meta_value );
						if ( str_starts_with( $field_key, '"' ) && str_ends_with( $field_key, '"' ) ) {
							$field_key = substr( $field_key, 1, -1 );
						} elseif ( str_starts_with( $field_key, "'" ) && str_ends_with( $field_key, "'" ) ) {
							$field_key = substr( $field_key, 1, -1 );
						}
						$field_key = preg_replace( '/[\x00-\x1F\x7F]/', '', $field_key );
						$field_key = trim( $field_key );

						if ( $field_name !== '' && strpos( $field_key, 'field_' ) === 0 ) {
							$meta_fields[ '_' . $field_name ] = $field_key;
						}
					}
				}

				for ( $j = 0; $j < count( $headers ); $j++ ) {
					$header_name            = $headers[ $j ] ?? '';
					$header_name_normalized = $normalize_field_name( $header_name );
					if ( $header_name_normalized === '' || $header_name_normalized === 'ID' ) {
						continue;
					}
					if ( in_array( $header_name_normalized, $allowed_post_fields, true ) ) {
						continue;
					}

					if ( empty( trim( $data[ $j ] ?? '' ) ) ) {
						continue; // Skip empty fields
					}

					$meta_value = $data[ $j ];

					// Do not store post fields as meta
					if ( in_array( $header_name_normalized, $allowed_post_fields, true ) ) {
						continue;
					}

					// Check if this is a taxonomy field (tax_ prefix ONLY, not cf_ fields)
					if ( strpos( $header_name_normalized, 'tax_' ) === 0 ) {
						// Handle taxonomy (pipe-separated) - this is for article-taxonomy relationship
						$terms = array_map( 'trim', explode( '|', $meta_value ) );
						// Store by actual taxonomy name (without tax_ prefix)
						$taxonomy_name                = substr( $header_name_normalized, 4 ); // Remove 'tax_'
						$taxonomies[ $taxonomy_name ] = $terms;

						// Extract taxonomy name from header (remove tax_ prefix)
						if ( taxonomy_exists( $taxonomy_name ) ) {
							// Get term_ids for reuse
							$term_ids = [];
							foreach ( $terms as $term_name ) {
								if ( ! empty( $term_name ) ) {
									// Find existing term by name in the specific taxonomy
									$term = get_term_by( 'name', $term_name, $taxonomy_name );
									if ( $term ) {
										$term_ids[] = $term->term_id;
									} else {
									}
								}
							}
							if ( ! empty( $term_ids ) ) {
								$taxonomy_term_ids[ $taxonomy_name ] = $term_ids;
							}
						} else {
						}
					} else {
						// Handle regular custom fields (cf_<field> => <field>) ONLY
						// Skip all other fields - only process cf_ prefixed fields
						if ( strpos( $header_name_normalized, 'cf_' ) !== 0 ) {
							continue; // Skip non-cf_ fields
						}

						$clean_field_name                 = substr( $header_name_normalized, 3 ); // Remove cf_
						$meta_fields[ $clean_field_name ] = (string) $meta_value;
					}
				}

				// Process taxonomies
				foreach ( $taxonomies as $taxonomy => $terms ) {
					if ( ! empty( $terms ) ) {
						// Debug log for taxonomy processing
						error_log( "[Swift CSV] Processing taxonomy: {$taxonomy}, format: {$taxonomy_format}" );

						// Use WordPress API to set term relationships (handles tt_id and counts)
						$term_ids = [];
						foreach ( $terms as $term_value ) {
							$term_value = trim( (string) $term_value );
							if ( $term_value === '' ) {
								continue;
							}

							error_log( "[Swift CSV] Processing term value: '{$term_value}' with format: {$taxonomy_format}" );

							// Handle different taxonomy formats with mixed data support
							if ( $taxonomy_format === 'id' ) {
								// Handle term IDs
								$term_id = intval( $term_value );
								error_log( "[Swift CSV] Looking up term by ID: {$term_id}" );

								// Check if the term value is actually a valid ID
								if ( $term_id === 0 ) {
									// For mixed format, treat numeric values as names
									if ( isset( $taxonomy_format_validation[ $taxonomy ] ) && $taxonomy_format_validation[ $taxonomy ]['mixed'] ) {
										error_log( "[Swift CSV] Mixed format detected: treating '{$term_value}' as term name" );
										$term = get_term_by( 'name', $term_value, $taxonomy );
										if ( ! $term ) {
											error_log( "[Swift CSV] Term not found, creating new term: '{$term_value}'" );
											$created = wp_insert_term( $term_value, $taxonomy );
											if ( is_wp_error( $created ) ) {
												error_log( "Failed to create term '$term_value' in taxonomy '$taxonomy'" );
												continue;
											}
											$term_ids[] = (int) $created['term_id'];
											error_log( "[Swift CSV] Created new term: '{$term_value}' with ID: {$created['term_id']}" );
										} else {
											$term_ids[] = (int) $term->term_id;
											error_log( "[Swift CSV] Found existing term by name: '{$term_value}' with ID: {$term->term_id}" );
										}
									} else {
										error_log( "[Swift CSV] ERROR: Term value '{$term_value}' is not a valid ID. Expected numeric ID when 'Term IDs' format is selected." );
										// Skip this term as it's not a valid ID
										continue;
									}
								} else {
									// Valid ID, process normally
									$term = get_term( $term_id, $taxonomy );
									if ( $term && ! is_wp_error( $term ) ) {
										$term_ids[] = $term_id;
										error_log( "[Swift CSV] Found term by ID: {$term_id} -> {$term->name}" );
									} else {
										error_log( "[Swift CSV] Invalid term ID: {$term_id} in taxonomy '{$taxonomy}'" );
									}
								}
							} else {
								// Handle term names (default)
								error_log( "[Swift CSV] Looking up term by name: '{$term_value}'" );
								$term = get_term_by( 'name', $term_value, $taxonomy );
								if ( ! $term ) {
									error_log( "[Swift CSV] Term not found, creating new term: '{$term_value}'" );
									$created = wp_insert_term( $term_value, $taxonomy );
									if ( is_wp_error( $created ) ) {
										error_log( "Failed to create term '$term_value' in taxonomy '$taxonomy'" );
										continue;
									}
									$term_ids[] = (int) $created['term_id'];
									error_log( "[Swift CSV] Created new term: '{$term_value}' with ID: {$created['term_id']}" );
								} else {
									$term_ids[] = (int) $term->term_id;
									error_log( "[Swift CSV] Found existing term by name: '{$term_value}' with ID: {$term->term_id}" );
								}
							}
						}

						if ( ! empty( $term_ids ) ) {
							if ( $dry_run ) {
								error_log( "[Dry Run] Would set terms for post {$post_id}: " . implode( ', ', $term_ids ) );
								// Log each term for Dry Run
								foreach ( $term_ids as $term_id ) {
									$term = get_term( $term_id, $taxonomy );
									if ( $term && ! is_wp_error( $term ) ) {
										$dry_run_log[] = sprintf(
											/* translators: 1: term name, 2: term ID, 3: taxonomy name */
											__( 'Existing term: %1$s (ID: %2$s, taxonomy: %3$s)', 'swift-csv' ),
											$term->name,
											$term_id,
											$taxonomy
										);
									}
								}
							} else {
								wp_set_post_terms( $post_id, $term_ids, $taxonomy, false );
								error_log( "[Swift CSV] Set terms for post {$post_id}: " . implode( ', ', $term_ids ) );
							}
						}
					}
				}

				// Process custom fields with multi-value support
				foreach ( $meta_fields as $key => $value ) {
					// Skip empty values
					if ( $value === '' || $value === null ) {
						continue;
					}

					if ( $dry_run ) {
						error_log( "[Dry Run] Would process custom field: {$key} = {$value}" );

						// Handle multi-value custom fields (pipe-separated)
						if ( strpos( $value, '|' ) !== false ) {
							// Add each value separately
							$values = array_map( 'trim', explode( '|', $value ) );
							foreach ( $values as $single_value ) {
								if ( $single_value !== '' ) {
									$dry_run_log[] = sprintf(
										/* translators: 1: field name, 2: field value */
										__( 'Custom field (multi-value): %1$s = %2$s', 'swift-csv' ),
										$key,
										$single_value
									);
								}
							}
						} else {
							// Single value (including serialized strings)
							$dry_run_log[] = sprintf(
								/* translators: 1: field name, 2: field value */
								__( 'Custom field: %1$s = %2$s', 'swift-csv' ),
								$key,
								$value
							);
						}
					} else {
						// Always replace existing meta for this key to ensure update works even if meta row doesn't exist.
						$wpdb->query(
							$wpdb->prepare(
								"DELETE FROM {$wpdb->postmeta} 
	                         WHERE post_id = %d 
	                         AND meta_key = %s",
								$post_id,
								$key
							)
						);

						// Handle multi-value custom fields (pipe-separated)
						if ( strpos( $value, '|' ) !== false ) {
							// Add each value separately
							$values = array_map( 'trim', explode( '|', $value ) );
							foreach ( $values as $single_value ) {
								if ( $single_value !== '' ) {
									$wpdb->insert(
										$wpdb->postmeta,
										[
											'post_id'    => $post_id,
											'meta_key'   => $key,
											'meta_value' => $single_value,
										],
										[ '%d', '%s', '%s' ]
									);
								}
							}
						} else {
							// Single value (including serialized strings)
							$wpdb->insert(
								$wpdb->postmeta,
								[
									'post_id'    => $post_id,
									'meta_key'   => $key,
									'meta_value' => is_string( $value ) ? $value : maybe_serialize( $value ),
								],
								[ '%d', '%s', '%s' ]
							);
						}
					}
				}

				// Custom field processing hook for extensions
				/**
				 * Action for processing custom fields during import
				 *
				 * Allows extensions to process custom fields with their own logic.
				 * This hook is called after basic field processing is complete.
				 *
				 * @since 0.9.0
				 * @param int $post_id The ID of the created/updated post
				 * @param array $meta_fields Array of meta fields to process
				 */
				do_action( 'swift_csv_process_custom_fields', $post_id, $meta_fields );
			} else {
				++$errors;
			}
		} catch ( Exception $e ) {
			++$errors;
		}
	}

	$next_row = $start_row + $processed; // Use actual processed count
	$continue = $next_row < $total_rows;

	// Cleanup temporary file when import is complete
	if ( ! $continue && $file_path && file_exists( $file_path ) ) {
		unlink( $file_path );
	}

	wp_send_json(
		[
			'success'            => true,
			'processed'          => $next_row,
			'total'              => $total_rows,
			'batch_processed'    => $processed,
			'batch_errors'       => $errors,
			'created'            => $created,
			'updated'            => $updated,
			'errors'             => $errors,
			'cumulative_created' => $previous_created + $created,
			'cumulative_updated' => $previous_updated + $updated,
			'cumulative_errors'  => $previous_errors + $errors,
			'progress'           => round( ( $next_row / $total_rows ) * 100, 2 ),
			'continue'           => $continue,
			'dry_run'            => $dry_run,
			'dry_run_log'        => $dry_run_log,
		]
	);
}
