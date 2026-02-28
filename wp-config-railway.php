<?php
/**
 * Railway-optimized WordPress configuration.
 *
 * Uses getenv() with safe fallbacks:
 *   Railway MySQL plugin injects: MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE, MYSQLPORT
 *   WordPress Docker image expects: WORDPRESS_DB_HOST, WORDPRESS_DB_USER, WORDPRESS_DB_PASSWORD, WORDPRESS_DB_NAME
 *
 * Both chains are supported so this works whether variables come
 * from a linked Railway MySQL service OR from manually set env vars.
 */

// --------------------------------------------------------------------------
// Database configuration (Railway-safe)
// --------------------------------------------------------------------------
$mysql_host = getenv('MYSQLHOST') ?: getenv('WORDPRESS_DB_HOST') ?: 'localhost';
$mysql_port = getenv('MYSQLPORT') ?: '3306';
define('DB_HOST', $mysql_host . ':' . $mysql_port);
define('DB_USER', getenv('MYSQLUSER') ?: getenv('WORDPRESS_DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('MYSQLPASSWORD') ?: getenv('WORDPRESS_DB_PASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('WORDPRESS_DB_NAME') ?: 'wordpress');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// --------------------------------------------------------------------------
// Authentication keys & salts
// These fallbacks let the site boot even if salts aren't set yet.
// Replace with your own keys from: https://api.wordpress.org/secret-key/1.1/salt/
// --------------------------------------------------------------------------
define('AUTH_KEY', getenv('AUTH_KEY') ?: 'change-me-auth-key');
define('SECURE_AUTH_KEY', getenv('SECURE_AUTH_KEY') ?: 'change-me-secure-auth-key');
define('LOGGED_IN_KEY', getenv('LOGGED_IN_KEY') ?: 'change-me-logged-in-key');
define('NONCE_KEY', getenv('NONCE_KEY') ?: 'change-me-nonce-key');
define('AUTH_SALT', getenv('AUTH_SALT') ?: 'change-me-auth-salt');
define('SECURE_AUTH_SALT', getenv('SECURE_AUTH_SALT') ?: 'change-me-secure-auth-salt');
define('LOGGED_IN_SALT', getenv('LOGGED_IN_SALT') ?: 'change-me-logged-in-salt');
define('NONCE_SALT', getenv('NONCE_SALT') ?: 'change-me-nonce-salt');

// --------------------------------------------------------------------------
// Table prefix
// --------------------------------------------------------------------------
$table_prefix = 'wp_';

// --------------------------------------------------------------------------
// Debug mode
// --------------------------------------------------------------------------
define('WP_DEBUG', filter_var(getenv('WP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN));
define('WP_DEBUG_LOG', filter_var(getenv('WP_DEBUG_LOG') ?: false, FILTER_VALIDATE_BOOLEAN));

// --------------------------------------------------------------------------
// Force HTTPS when behind Railway's proxy
// --------------------------------------------------------------------------
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// --------------------------------------------------------------------------
// Dynamic site URL (works for any Railway-generated subdomain or custom domain)
// --------------------------------------------------------------------------
if (!empty($_SERVER['HTTP_HOST'])) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    define('WP_HOME', $protocol . $_SERVER['HTTP_HOST']);
    define('WP_SITEURL', $protocol . $_SERVER['HTTP_HOST']);
}

// --------------------------------------------------------------------------
// Redis Object Cache (only if Railway Redis is linked)
// --------------------------------------------------------------------------
$redis_host = getenv('REDISHOST') ?: getenv('WP_REDIS_HOST') ?: null;
if ($redis_host) {
    define('WP_CACHE', true);
    define('WP_REDIS_HOST', $redis_host);
    define('WP_REDIS_PORT', (int)(getenv('REDISPORT') ?: getenv('WP_REDIS_PORT') ?: 6379));
    $redis_password = getenv('REDISPASSWORD') ?: getenv('WP_REDIS_PASSWORD') ?: null;
    if ($redis_password) {
        define('WP_REDIS_PASSWORD', $redis_password);
    }
}

// --------------------------------------------------------------------------
// Disable WP-Cron so Railway scheduled jobs handle it
// --------------------------------------------------------------------------
if (filter_var(getenv('DISABLE_WP_CRON') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
    define('DISABLE_WP_CRON', true);
}

/* That's all, stop editing! Happy publishing. */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';
