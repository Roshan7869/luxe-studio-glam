<?php
/**
 * Fired during plugin uninstall.
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Cleanup procedures.
 * For production plugins, we typically do NOT drop tables here.
 * We'll leave the tables intact unless the user has opted for full removal.
 */
// global $wpdb;
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gl_franchises" );
// ...
