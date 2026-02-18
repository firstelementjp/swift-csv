<?php
/**
 * Simple Direct SQL Export Test
 *
 * Minimal implementation: JavaScript only handles button click,
 * PHP handles everything including SQL, CSV generation, and download.
 *
 * @package Swift_CSV
 * @since 0.9.8
 */

// Enable Direct SQL Export for testing
add_filter( 'swift_csv_enable_direct_sql_export', '__return_true' );

/**
 * Add simple test button to admin page
 */
add_action(
	'admin_footer',
	function () {
		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, 'swift-csv' ) !== false ) {
			?>
		<script>
		jQuery(function($) {
			console.log('Simple Direct SQL script loaded');
			
			// Check if form exists
			var $form = $('#swift-csv-ajax-export-form');
			console.log('Form found:', $form.length);
			
			if ($form.length) {
				// Add simple test button
				$form.append(
					'<button type="button" id="test_direct_sql_simple" class="button button-secondary" style="margin-top: 10px;">Test Direct SQL (Simple)</button>'
				);
				console.log('Button added');
			} else {
				console.error('Form not found');
				return;
			}
			
			// Simple click handler - just trigger AJAX
			$('#test_direct_sql_simple').on('click', function() {
				console.log('Simple Direct SQL Export clicked');
				
				// Show loading state
				$(this).prop('disabled', true).text('Processing...');
				
				// Simple AJAX call
				$.post(ajaxurl, {
					action: 'swift_csv_ajax_export_direct_sql_simple'
				}, function(response) {
					console.log('Response received:', response);
					
					if (response.success && response.data && response.data.csv) {
						// Create and download CSV
						var blob = new Blob([response.data.csv], { type: 'text/csv;charset=utf-8;' });
						var link = document.createElement('a');
						var url = URL.createObjectURL(blob);
						
						link.setAttribute('href', url);
						link.setAttribute('download', 'direct-sql-export-' + new Date().toISOString().slice(0, 10) + '.csv');
						link.style.visibility = 'hidden';
						
						document.body.appendChild(link);
						link.click();
						document.body.removeChild(link);
						
						console.log('CSV downloaded successfully - ' + response.data.count + ' records');
					} else {
						console.error('Export failed:', response);
						alert('Export failed: ' + (response.data ? response.data.message : 'Unknown error'));
					}
					
				}).always(function() {
					// Reset button
					$('#test_direct_sql_simple').prop('disabled', false).text('Test Direct SQL (Simple)');
				});
			});
		});
		</script>
			<?php
		}
	}
);

/**
 * Simple Direct SQL Export AJAX handler
 */
add_action(
	'wp_ajax_swift_csv_ajax_export_direct_sql_simple',
	function () {
		error_log( '[Direct SQL Simple] Started at: ' . date( 'Y-m-d H:i:s' ) );

		try {
			// Simple hardcoded values for testing
			$post_type    = 'post';
			$post_status  = 'publish';
			$export_limit = 100;

			error_log( '[Direct SQL Simple] Querying posts: type=' . $post_type . ', status=' . $post_status . ', limit=' . $export_limit );

			// Direct SQL query
			global $wpdb;
			$query = $wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_content, p.post_status, p.post_date, p.post_modified 
             FROM {$wpdb->posts} p 
             WHERE p.post_type = %s 
             AND p.post_status = %s 
             LIMIT %d",
				$post_type,
				$post_status,
				$export_limit
			);

			$posts = $wpdb->get_results( $query, ARRAY_A );

			error_log( '[Direct SQL Simple] Found ' . count( $posts ) . ' posts' );

			if ( empty( $posts ) ) {
				wp_send_json_error( 'No posts found' );
				return;
			}

			// Generate CSV
			$csv = "ID,Title,Content,Status,Post Date,Modified Date\n";

			foreach ( $posts as $post ) {
				$csv .= implode(
					',',
					[
						$post['ID'],
						'"' . str_replace( '"', '""', $post['post_title'] ?? '' ) . '"',
						'"' . str_replace( '"', '""', wp_strip_all_tags( $post['post_content'] ?? '' ) ) . '"',
						$post['post_status'] ?? '',
						$post['post_date'] ?? '',
						$post['post_modified'] ?? '',
					]
				) . "\n";
			}

			error_log( '[Direct SQL Simple] CSV generated: ' . strlen( $csv ) . ' bytes' );

			// Send success response
			wp_send_json_success(
				[
					'csv'     => $csv,
					'count'   => count( $posts ),
					'message' => 'Export completed successfully',
				]
			);

		} catch ( Exception $e ) {
			error_log( '[Direct SQL Simple] Error: ' . $e->getMessage() );
			wp_send_json_error( 'Export failed: ' . $e->getMessage() );
		}
	}
);
