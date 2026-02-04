<?php
/**
 * Add to wp-config.php to disable WordPress cron
 */

// Add this line to wp-config.php before /* That's all, stop editing! */
define( 'DISABLE_WP_CRON', true );

// Also add these for better performance during imports
define( 'WP_POST_REVISIONS', 0 );
define( 'AUTOSAVE_INTERVAL', 300 );
