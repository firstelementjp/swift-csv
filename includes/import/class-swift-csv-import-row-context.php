<?php
/**
 * Row context builder for Swift CSV import.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build per-row import contexts from CSV lines.
 *
 * This class intentionally mirrors the existing behavior of the import handler
 * and is designed to be called from Swift_CSV_Ajax_Import via delegation.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_Import_Row_Context {
	/**
	 * CSV utility instance.
	 *
	 * @since 0.9.0
	 * @var Swift_CSV_Import_Csv
	 */
	private $csv_util;

	/**
	 * Constructor.
	 *
	 * @since 0.9.0
	 * @param Swift_CSV_Import_Csv|null $csv_util CSV utility.
	 */
	public function __construct( ?Swift_CSV_Import_Csv $csv_util = null ) {
		$this->csv_util = $csv_util ?: new Swift_CSV_Import_Csv();
	}

	/**
	 * Build per-row import context using config values.
	 *
	 * @since 0.9.0
	 * @param wpdb                                                                                                                             $wpdb WordPress database handler.
	 * @param array{file_path:string,start_row:int,batch_size:int,post_type:string,update_existing:string,taxonomy_format:string,dry_run:bool} $config Import configuration.
	 * @param string                                                                                                                           $line Raw CSV line.
	 * @param string                                                                                                                           $delimiter CSV delimiter.
	 * @param array<int, string>                                                                                                               $headers CSV headers.
	 * @param array<int, string>                                                                                                               $allowed_post_fields Allowed post fields.
	 * @return array{data:array<int,string>,post_fields_from_csv:array<string,mixed>,post_id:int,is_update:bool}|null Null means skip this row.
	 */
	public function build_import_row_context_from_config( wpdb $wpdb, array $config, string $line, string $delimiter, array $headers, array $allowed_post_fields ): ?array {
		return $this->build_import_row_context(
			$wpdb,
			$line,
			$delimiter,
			$headers,
			$allowed_post_fields,
			(string) ( $config['update_existing'] ?? '0' ),
			(string) ( $config['post_type'] ?? 'post' )
		);
	}

	/**
	 * Build per-row import context (parse row, collect fields, resolve existing post, validate).
	 *
	 * @since 0.9.0
	 * @param wpdb               $wpdb WordPress database handler.
	 * @param string             $line Raw CSV line.
	 * @param string             $delimiter CSV delimiter.
	 * @param array<int, string> $headers CSV headers.
	 * @param array<int, string> $allowed_post_fields Allowed post fields.
	 * @param string             $update_existing Update flag from request.
	 * @param string             $post_type Post type.
	 * @return array{data:array<int,string>,post_fields_from_csv:array<string,mixed>,post_id:int|null,is_update:bool}|null Null means skip this row.
	 */
	public function build_import_row_context( wpdb $wpdb, string $line, string $delimiter, array $headers, array $allowed_post_fields, string $update_existing, string $post_type ): ?array {
		$parsed               = $this->parse_row_and_collect_post_fields( $line, $delimiter, $headers, $allowed_post_fields );
		$data                 = $parsed['data'];
		$post_id_from_csv     = $parsed['post_id_from_csv'];
		$post_fields_from_csv = $parsed['post_fields_from_csv'];

		$existing  = $this->resolve_post_id_and_update_flag( $wpdb, $update_existing, $post_type, $post_id_from_csv );
		$post_id   = $existing['post_id'];
		$is_update = $existing['is_update'];

		if ( $this->maybe_skip_import_row_context( $update_existing, $post_fields_from_csv ) ) {
			return null;
		}

		// Run row validation hook
		$row_validation = [
			'valid'    => true,
			'errors'   => [],
			'warnings' => [],
		];

		/**
		 * Validate individual CSV row data
		 *
		 * Allows developers to implement row-level validation logic for each
		 * CSV row during import processing. This hook enables field-level
		 * validation, data format checks, and custom business rules.
		 *
		 * @since 0.9.7
		 * @param array{valid:bool,errors:array<string>,warnings:array<string>} $row_validation Row validation result.
		 * @param array{data:array<int,string>,post_fields_from_csv:array<string,mixed>,post_id:int|null,is_update:bool} $row_context Complete row processing context.
		 * @param array{headers:array<int,string>,allowed_post_fields:array<int,string>,update_existing:string,post_type:string} $context Processing context and configuration.
		 * @return array{valid:bool,errors:array<string>,warnings:array<string>} Modified validation result.
		 */
		$row_validation = apply_filters(
			'swift_csv_import_row_validation',
			$row_validation,
			[
				'data'                 => $data,
				'post_fields_from_csv' => $post_fields_from_csv,
				'post_id'              => $post_id,
				'is_update'            => $is_update,
			],
			[
				'headers'             => $headers,
				'allowed_post_fields' => $allowed_post_fields,
				'update_existing'     => $update_existing,
				'post_type'           => $post_type,
			]
		);

		// Handle row validation errors
		if ( ! $row_validation['valid'] ) {
			// For now, we'll continue processing even with validation errors
			// In a future version, this could skip the row or stop processing
		}

		// Run data filtering hook
		/**
		 * Filter and preprocess CSV row data before final processing
		 *
		 * Allows developers to modify, clean, or preprocess CSV data before
		 * it gets processed by the import system. This hook enables data
		 * transformation, normalization, and custom preprocessing.
		 *
		 * @since 0.9.7
		 * @param array{data:array<int,string>,post_fields_from_csv:array<string,mixed>} $filtered_data Modified row data and post fields.
		 * @param array{data:array<int,string>,post_fields_from_csv:array<string,mixed>} $original_data Original row data and post fields.
		 * @param array{headers:array<int,string>,allowed_post_fields:array<int,string>,update_existing:string,post_type:string} $context Processing context and configuration.
		 * @return array{data:array<int,string>,post_fields_from_csv:array<string,mixed>} Modified row data and post fields.
		 */
		$filtered_data = apply_filters(
			'swift_csv_import_data_filter',
			[
				'data'                 => $data,
				'post_fields_from_csv' => $post_fields_from_csv,
			],
			[
				'data'                 => $data,
				'post_fields_from_csv' => $post_fields_from_csv,
			],
			[
				'headers'             => $headers,
				'allowed_post_fields' => $allowed_post_fields,
				'update_existing'     => $update_existing,
				'post_type'           => $post_type,
			]
		);

		// Use filtered data
		$data                 = $filtered_data['data'];
		$post_fields_from_csv = $filtered_data['post_fields_from_csv'];

		return $this->build_import_row_context_array( $data, $post_fields_from_csv, $post_id, $is_update );
	}

	/**
	 * Build the import row context array.
	 *
	 * @since 0.9.0
	 * @param array<int, string>   $data Parsed CSV row data.
	 * @param array<string, mixed> $post_fields_from_csv Post fields collected from CSV.
	 * @param int|null             $post_id Resolved target post ID.
	 * @param bool                 $is_update Whether this row is an update.
	 * @return array{data:array<int,string>,post_fields_from_csv:array<string,mixed>,post_id:int|null,is_update:bool}
	 */
	public function build_import_row_context_array( array $data, array $post_fields_from_csv, ?int $post_id, bool $is_update ): array {
		return [
			'data'                 => $data,
			'post_fields_from_csv' => $post_fields_from_csv,
			'post_id'              => $post_id,
			'is_update'            => $is_update,
		];
	}

	/**
	 * Parse a CSV line and collect post fields.
	 *
	 * @since 0.9.0
	 * @param string             $line Raw CSV line.
	 * @param string             $delimiter CSV delimiter.
	 * @param array<int, string> $headers CSV headers.
	 * @param array<int, string> $allowed_post_fields Allowed post fields.
	 * @return array{data:array<int,string>,post_id_from_csv:int,post_fields_from_csv:array<string,mixed>}
	 */
	public function parse_row_and_collect_post_fields( string $line, string $delimiter, array $headers, array $allowed_post_fields ): array {
		$data                 = $this->get_parsed_csv_row( $line, $delimiter );
		$post_id_from_csv     = $this->get_post_id_from_csv_row( $data );
		$post_fields_from_csv = $this->get_post_fields_from_csv_row( $headers, $data, $allowed_post_fields );

		return [
			'data'                 => $data,
			'post_id_from_csv'     => $post_id_from_csv,
			'post_fields_from_csv' => $post_fields_from_csv,
		];
	}

	/**
	 * Get post ID value from parsed CSV row.
	 *
	 * @since 0.9.0
	 * @param array $data Parsed CSV row.
	 * @return string Post ID value from CSV (may be empty).
	 */
	public function get_post_id_from_csv_row( array $data ): string {
		$first_col = $data[0] ?? '';

		// First check if this looks like an ID row (first column is numeric ID)
		if ( is_numeric( $first_col ) && strlen( (string) $first_col ) <= 6 ) {
			// This is normal - most rows have ID in first column
			// Don't skip - process the actual data
		} else {
			// Continue processing anyway
		}

		return (string) $first_col;
	}

	/**
	 * Parse one CSV row string into an array.
	 *
	 * @since 0.9.0
	 * @param string $line Raw CSV line.
	 * @param string $delimiter CSV delimiter.
	 * @return array Parsed row data.
	 */
	public function get_parsed_csv_row( string $line, string $delimiter ): array {
		return $this->csv_util->parse_csv_row( $line, $delimiter );
	}

	/**
	 * Resolve whether a CSV row should update an existing post.
	 *
	 * @since 0.9.0
	 * @param wpdb   $wpdb WordPress database handler.
	 * @param string $update_existing Update flag from request.
	 * @param string $post_type Post type.
	 * @param string $post_id_from_csv Post ID from CSV (first column).
	 * @return array{post_id:int,is_update:bool}
	 */
	public function resolve_existing_post_for_import( wpdb $wpdb, string $update_existing, string $post_type, string $post_id_from_csv ): array {
		return $this->find_existing_post_for_update( $wpdb, $update_existing, $post_type, $post_id_from_csv );
	}

	/**
	 * Resolve target post ID and whether the row should be treated as an update.
	 *
	 * @since 0.9.0
	 * @param wpdb   $wpdb WordPress database handler.
	 * @param string $update_existing Update flag from request.
	 * @param string $post_type Post type.
	 * @param int    $post_id_from_csv Post ID value from CSV.
	 * @return array{post_id:int,is_update:bool}
	 */
	public function resolve_post_id_and_update_flag( wpdb $wpdb, string $update_existing, string $post_type, int $post_id_from_csv ): array {
		$existing = $this->resolve_existing_post_for_import( $wpdb, $update_existing, $post_type, (string) $post_id_from_csv );

		return [
			'post_id'   => $existing['post_id'],
			'is_update' => $existing['is_update'],
		];
	}

	/**
	 * Determine whether the current CSV row should be skipped during import.
	 *
	 * @since 0.9.0
	 * @param string $update_existing Update flag from request.
	 * @param array  $post_fields_from_csv Post fields collected from CSV.
	 * @return bool True if the row should be skipped.
	 */
	public function should_skip_import_row( string $update_existing, array $post_fields_from_csv ): bool {
		return null !== $this->get_skip_reason_for_import_row( $update_existing, $post_fields_from_csv );
	}

	/**
	 * Get skip reason for import row.
	 *
	 * @since 0.9.0
	 * @param string $update_existing Update flag from request.
	 * @param array  $post_fields_from_csv Post fields collected from CSV.
	 * @return string|null Null means do not skip.
	 */
	public function get_skip_reason_for_import_row( string $update_existing, array $post_fields_from_csv ): ?string {
		if ( $this->should_skip_row_due_to_missing_title( $update_existing, $post_fields_from_csv ) ) {
			return 'missing_title';
		}

		return null;
	}

	/**
	 * Determine whether the current row context should be skipped.
	 *
	 * @since 0.9.0
	 * @param string $update_existing Update flag from request.
	 * @param array  $post_fields_from_csv Post fields collected from CSV.
	 * @return bool True if the row context should be skipped.
	 */
	public function maybe_skip_import_row_context( string $update_existing, array $post_fields_from_csv ): bool {
		return null !== $this->get_skip_reason_for_import_row( $update_existing, $post_fields_from_csv );
	}

	/**
	 * Decide whether to skip a row due to missing title.
	 *
	 * @since 0.9.0
	 * @param string              $update_existing Update flag.
	 * @param array<string,mixed> $post_fields_from_csv Post fields.
	 * @return bool
	 */
	public function should_skip_row_due_to_missing_title( string $update_existing, array $post_fields_from_csv ): bool {
		return $update_existing !== '1' && empty( $post_fields_from_csv['post_title'] );
	}

	/**
	 * Get post fields array from a parsed CSV row.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $headers CSV headers.
	 * @param array              $data Parsed CSV row data.
	 * @param array<int, string> $allowed_post_fields Allowed post fields.
	 * @return array<string, mixed> Collected post fields.
	 */
	public function get_post_fields_from_csv_row( array $headers, array $data, array $allowed_post_fields ): array {
		return $this->collect_post_fields_from_csv_row( $headers, $data, $allowed_post_fields );
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
	public function collect_post_fields_from_csv_row( $headers, $data, $allowed_post_fields ) {
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
	 * Find existing post for update.
	 *
	 * @since 0.9.0
	 * @param wpdb   $wpdb WordPress database handler.
	 * @param string $update_existing Update flag.
	 * @param string $post_type Post type.
	 * @param string $post_id_from_csv Post ID from CSV.
	 * @return array{post_id:int|null,is_update:bool}
	 */
	public function find_existing_post_for_update( $wpdb, $update_existing, $post_type, $post_id_from_csv ) {
		$post_id   = null;
		$is_update = false;

		if ( $update_existing === '1' && $post_id_from_csv !== '' ) {
			$post_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = %s",
					$post_id_from_csv,
					$post_type
				)
			);

			if ( $post_id ) {
				$is_update = true;
			}
		}

		return [
			'post_id'   => $post_id,
			'is_update' => $is_update,
		];
	}
}
