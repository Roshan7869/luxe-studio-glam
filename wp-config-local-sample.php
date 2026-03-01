<?php
/**
 * Luxe Studio Glam - Local WP Configuration Template
 * 
 * Instructions:
 * 1. Copy this file to wp-config.php in your Local WP root.
 * 2. Update the DB_* constants with your Local WP site details.
 */

define('DB_NAME', 'local');
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

/** Authentication Unique Keys and Salts. */
define('AUTH_KEY', 'put your unique phrase here');
define('SECURE_AUTH_KEY', 'put your unique phrase here');
define('LOGGED_IN_KEY', 'put your unique phrase here');
define('NONCE_KEY', 'put your unique phrase here');
define('AUTH_SALT', 'put your unique phrase here');
define('SECURE_AUTH_SALT', 'put your unique phrase here');
define('LOGGED_IN_SALT', 'put your unique phrase here');
define('NONCE_SALT', 'put your unique phrase here');

$table_prefix = 'wp_';

/**
 * For Local WP:
 * Set WP_DEBUG to true to see errors during development.
 */
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

/**
 * Cleanup:
 * Disable Railway-specific settings that aren't needed locally.
 */
define('DISABLE_WP_CRON', false);


/* That's all, stop editing! Happy publishing. */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';
