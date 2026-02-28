<?php
/**
 * Custom WordPress configuration for Railway deployment.
 *
 * This file dynamically reads environment variables injected by Railway
 * and configures WordPress accordingly.
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', $_ENV['MYSQL_DATABASE'] ?? $_ENV['WORDPRESS_DB_NAME'] ?? 'wordpress');

/** Database username */
define('DB_USER', $_ENV['MYSQL_USER'] ?? $_ENV['WORDPRESS_DB_USER'] ?? 'root');

/** Database password */
define('DB_PASSWORD', $_ENV['MYSQL_PASSWORD'] ?? $_ENV['WORDPRESS_DB_PASSWORD'] ?? '');

/** Database hostname */
define('DB_HOST', $_ENV['MYSQLHOST'] ? $_ENV['MYSQLHOST'] . ':' . ($_ENV['MYSQLPORT'] ?? '3306') : ($_ENV['WORDPRESS_DB_HOST'] ?? 'localhost'));

/** Database charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The database collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', $_ENV['AUTH_KEY'] ?? 'put your unique phrase here');
define('SECURE_AUTH_KEY', $_ENV['SECURE_AUTH_KEY'] ?? 'put your unique phrase here');
define('LOGGED_IN_KEY', $_ENV['LOGGED_IN_KEY'] ?? 'put your unique phrase here');
define('NONCE_KEY', $_ENV['NONCE_KEY'] ?? 'put your unique phrase here');
define('AUTH_SALT', $_ENV['AUTH_SALT'] ?? 'put your unique phrase here');
define('SECURE_AUTH_SALT', $_ENV['SECURE_AUTH_SALT'] ?? 'put your unique phrase here');
define('LOGGED_IN_SALT', $_ENV['LOGGED_IN_SALT'] ?? 'put your unique phrase here');
define('NONCE_SALT', $_ENV['NONCE_SALT'] ?? 'put your unique phrase here');

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define('WP_DEBUG', filter_var($_ENV['WP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));

/* Add any custom values between this line and the "stop editing" line. */

// Force HTTPS in Railway (since Railway sits behind a proxy)
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// Ensure the site URL is correct dynamically (Railway gives random URLs)
if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    define('WP_HOME', $protocol . $_SERVER['HTTP_HOST']);
    define('WP_SITEURL', $protocol . $_SERVER['HTTP_HOST']);
}

// Redis Object Cache Settings
if (isset($_ENV['REDISHOST']) || isset($_ENV['WP_REDIS_HOST'])) {
    define('WP_REDIS_HOST', $_ENV['REDISHOST'] ?? $_ENV['WP_REDIS_HOST']);
    define('WP_REDIS_PORT', $_ENV['REDISPORT'] ?? $_ENV['WP_REDIS_PORT'] ?? 6379);
    if (isset($_ENV['REDISPASSWORD']) || isset($_ENV['WP_REDIS_PASSWORD'])) {
        define('WP_REDIS_PASSWORD', $_ENV['REDISPASSWORD'] ?? $_ENV['WP_REDIS_PASSWORD']);
    }
}

// Disable WP Cron if specified (so Railway scheduled jobs can do it)
if (isset($_ENV['DISABLE_WP_CRON']) && filter_var($_ENV['DISABLE_WP_CRON'], FILTER_VALIDATE_BOOLEAN)) {
    define('DISABLE_WP_CRON', true);
}

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
