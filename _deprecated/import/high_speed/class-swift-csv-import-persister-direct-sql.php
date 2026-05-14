<?php
/**
 * Post persistence (Direct SQL) for FE CSV Import & Export import.
 *
 * @since 0.9.10
 * @package FE_CSV_Import_Export
 * @deprecated 0.9.8 Migrated to FE CSV Import & Export Pro. Use fe_csv_import_export_import_direct_sql hook instead.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persist wp_posts rows for CSV import (Direct SQL).
 *
 * @since 0.9.10
 * @package FE_CSV_Import_Export
 * @deprecated 0.9.8 Migrated to FE CSV Import & Export Pro. Use fe_csv_import_export_import_direct_sql hook instead.
 */
class Swift_CSV_Import_Persister_Direct_SQL extends Swift_CSV_Import_Persister {
}
