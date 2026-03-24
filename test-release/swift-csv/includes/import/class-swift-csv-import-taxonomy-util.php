<?php
/**
 * Taxonomy utilities for Swift CSV import.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import taxonomy utilities.
 *
 * @since 0.9.0
 * @package Swift_CSV
 */
class Swift_CSV_Import_Taxonomy_Util {
	/**
	 * Analyze term values to determine format type.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $term_values Array of term values.
	 * @return array{all_numeric:bool,all_string:bool,mixed:bool} Format analysis result.
	 */
	public function analyze_term_values_format( array $term_values ): array {
		$all_numeric = true;
		$all_string  = true;
		$mixed       = false;

		foreach ( $term_values as $term_val ) {
			if ( '' === $term_val ) {
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

		return [
			'all_numeric' => $all_numeric,
			'all_string'  => $all_string,
			'mixed'       => $mixed,
		];
	}

	/**
	 * Validate taxonomy format consistency.
	 *
	 * @since 0.9.0
	 * @param string      $taxonomy_format UI selected format.
	 * @param array       $validation Taxonomy validation data.
	 * @param string|null $file_path Optional file path for cleanup.
	 * @return array{valid:bool,error:string|null}
	 */
	public function validate_taxonomy_format_consistency(
		string $taxonomy_format,
		array $validation,
		?string $file_path = null
	): array {
		if ( 'name' === $taxonomy_format && ! empty( $validation['all_numeric'] ) ) {
			$error = sprintf(
				/* translators: 1: taxonomy name, 2: UI format, 3: sample values */
				__( 'Format mismatch detected for taxonomy "%1$s". UI is set to "Names" but CSV contains only numeric values: %2$s. Please check your data format.', 'swift-csv' ),
				$validation['taxonomy_name'] ?? 'unknown',
				implode( ', ', $validation['sample_values'] ?? [] )
			);

			if ( $file_path && file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}

			return [
				'valid' => false,
				'error' => $error,
			];
		}

		if ( 'id' === $taxonomy_format && ! empty( $validation['all_string'] ) ) {
			$error = sprintf(
				/* translators: 1: taxonomy name, 2: UI format, 3: sample values */
				__( 'Format mismatch detected for taxonomy "%1$s". UI is set to "Term IDs" but CSV contains only text values: %2$s. Please check your data format.', 'swift-csv' ),
				$validation['taxonomy_name'] ?? 'unknown',
				implode( ', ', $validation['sample_values'] ?? [] )
			);

			if ( $file_path && file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}

			return [
				'valid' => false,
				'error' => $error,
			];
		}

		return [
			'valid' => true,
			'error' => null,
		];
	}

	/**
	 * Validate ID column exists in CSV headers.
	 *
	 * @since 0.9.0
	 * @param array<int, string> $headers CSV headers.
	 * @param string|null        $file_path Optional file path for cleanup.
	 * @return array{valid:bool,id_col:int|null,error:string|null}
	 */
	public function validate_id_column( array $headers, ?string $file_path = null ): array {
		$id_col = array_search( 'ID', $headers, true );
		if ( false !== $id_col ) {
			return [
				'valid'  => true,
				'id_col' => $id_col,
				'error'  => null,
			];
		}

		if ( $file_path && file_exists( $file_path ) ) {
			wp_delete_file( $file_path );
		}

		return [
			'valid'  => false,
			'id_col' => null,
			'error'  => 'Invalid CSV format: ID column is required',
		];
	}

	/**
	 * Resolve term by ID with validation.
	 *
	 * @since 0.9.0
	 * @param int    $term_id Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array{valid:bool,term_id:int|null,error:string|null}
	 */
	public function resolve_term_by_id( int $term_id, string $taxonomy ): array {
		$term = get_term( $term_id, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			return [
				'valid'   => true,
				'term_id' => $term_id,
				'error'   => null,
			];
		}

		return [
			'valid'   => false,
			'term_id' => null,
			'error'   => "Invalid term ID: {$term_id} in taxonomy '{$taxonomy}'",
		];
	}

	/**
	 * Resolve term by name, creating if not found.
	 *
	 * @since 0.9.0
	 * @param string $term_name Term name.
	 * @param string $taxonomy Taxonomy name.
	 * @return array{valid:bool,term_id:int|null,error:string|null}
	 */
	public function resolve_term_by_name( string $term_name, string $taxonomy ): array {
		if ( $this->should_resolve_hierarchical_path( $term_name, $taxonomy ) ) {
			return $this->resolve_term_by_path( $term_name, $taxonomy );
		}

		$term = get_term_by( 'name', $term_name, $taxonomy );
		if ( $term ) {
			return [
				'valid'   => true,
				'term_id' => (int) $term->term_id,
				'error'   => null,
			];
		}

		$created = wp_insert_term( $term_name, $taxonomy );
		if ( is_wp_error( $created ) ) {
			return [
				'valid'   => false,
				'term_id' => null,
				'error'   => "Failed to create term '{$term_name}' in taxonomy '{$taxonomy}'",
			];
		}

		return [
			'valid'   => true,
			'term_id' => (int) $created['term_id'],
			'error'   => null,
		];
	}

	/**
	 * Determine whether a term value should be treated as a hierarchical path
	 *
	 * @since 0.9.8
	 * @param string $term_name Term value from CSV.
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	private function should_resolve_hierarchical_path( string $term_name, string $taxonomy ): bool {
		if ( false === strpos( $term_name, '>' ) ) {
			return false;
		}

		$taxonomy_object = get_taxonomy( $taxonomy );

		return $taxonomy_object instanceof \WP_Taxonomy && ! empty( $taxonomy_object->hierarchical );
	}

	/**
	 * Resolve a hierarchical term path such as `Parent > Child`
	 *
	 * Creates missing parent and child terms as needed, preserving hierarchy.
	 *
	 * @since 0.9.8
	 * @param string $term_path Hierarchical term path.
	 * @param string $taxonomy Taxonomy name.
	 * @return array{valid:bool,term_id:int|null,error:string|null}
	 */
	private function resolve_term_by_path( string $term_path, string $taxonomy ): array {
		$segments = array_values( array_filter( array_map( 'trim', explode( '>', $term_path ) ), 'strlen' ) );

		if ( empty( $segments ) ) {
			return [
				'valid'   => false,
				'term_id' => null,
				'error'   => "Invalid hierarchical term path '{$term_path}' in taxonomy '{$taxonomy}'",
			];
		}

		$parent_id     = 0;
		$resolved_term = null;

		foreach ( $segments as $segment ) {
			$resolved_term = $this->resolve_term_in_parent( $segment, $taxonomy, $parent_id );

			if ( ! $resolved_term['valid'] ) {
				return $resolved_term;
			}

			$parent_id = (int) $resolved_term['term_id'];
		}

		return $resolved_term;
	}

	/**
	 * Resolve or create a term under a specific parent term
	 *
	 * @since 0.9.8
	 * @param string $term_name Term name.
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $parent_id Parent term ID.
	 * @return array{valid:bool,term_id:int|null,error:string|null}
	 */
	private function resolve_term_in_parent( string $term_name, string $taxonomy, int $parent_id ): array {
		$existing_terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'name'       => $term_name,
				'parent'     => $parent_id,
			]
		);

		if ( ! is_wp_error( $existing_terms ) && ! empty( $existing_terms ) ) {
			$term = reset( $existing_terms );

			if ( $term instanceof \WP_Term ) {
				return [
					'valid'   => true,
					'term_id' => (int) $term->term_id,
					'error'   => null,
				];
			}
		}

		$created = wp_insert_term(
			$term_name,
			$taxonomy,
			[
				'parent' => $parent_id,
			]
		);

		if ( is_wp_error( $created ) ) {
			return [
				'valid'   => false,
				'term_id' => null,
				'error'   => "Failed to create hierarchical term '{$term_name}' in taxonomy '{$taxonomy}'",
			];
		}

		return [
			'valid'   => true,
			'term_id' => (int) $created['term_id'],
			'error'   => null,
		];
	}

	/**
	 * Resolve term IDs from a term value based on format.
	 *
	 * @since 0.9.0
	 * @param string $taxonomy Taxonomy name.
	 * @param string $term_value Term value.
	 * @param string $taxonomy_format Taxonomy format.
	 * @param array  $taxonomy_format_validation Taxonomy format validation data.
	 * @return array<int, int>
	 */
	public function resolve_term_ids_from_value(
		string $taxonomy,
		string $term_value,
		string $taxonomy_format,
		array $taxonomy_format_validation
	): array {
		if ( 'name' === $taxonomy_format && $this->should_resolve_hierarchical_path( $term_value, $taxonomy ) ) {
			return $this->resolve_term_ids_from_path( $term_value, $taxonomy );
		}

		if ( 'id' === $taxonomy_format ) {
			$term_id = intval( $term_value );

			if ( 0 === $term_id ) {
				if (
				isset( $taxonomy_format_validation[ $taxonomy ] ) &&
				! empty( $taxonomy_format_validation[ $taxonomy ]['mixed'] )
				) {
					$name_result = $this->resolve_term_by_name( $term_value, $taxonomy );
					if ( $name_result['valid'] ) {
						return [ (int) $name_result['term_id'] ];
					}
				}

				return [];
			}

			$id_result = $this->resolve_term_by_id( $term_id, $taxonomy );
			if ( $id_result['valid'] ) {
				return [ (int) $id_result['term_id'] ];
			}

			return [];
		}

		$name_result = $this->resolve_term_by_name( $term_value, $taxonomy );
		if ( $name_result['valid'] ) {
			return [ (int) $name_result['term_id'] ];
		}

		return [];
	}

	/**
	 * Resolve all term IDs included in a hierarchical term path
	 *
	 * Returns parent and child IDs in path order so the post can be assigned to
	 * every hierarchical level represented in the CSV value.
	 *
	 * @since 0.9.8
	 * @param string $term_path Hierarchical term path.
	 * @param string $taxonomy Taxonomy name.
	 * @return array<int, int>
	 */
	private function resolve_term_ids_from_path( string $term_path, string $taxonomy ): array {
		$segments = array_values( array_filter( array_map( 'trim', explode( '>', $term_path ) ), 'strlen' ) );

		if ( empty( $segments ) ) {
			return [];
		}

		$resolved_ids = [];
		$parent_id    = 0;

		foreach ( $segments as $segment ) {
			$resolved_term = $this->resolve_term_in_parent( $segment, $taxonomy, $parent_id );

			if ( ! $resolved_term['valid'] || empty( $resolved_term['term_id'] ) ) {
				return [];
			}

			$parent_id      = (int) $resolved_term['term_id'];
			$resolved_ids[] = $parent_id;
		}

		return $resolved_ids;
	}
}
