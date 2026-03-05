<?php
// === Luxe Studio Glam - Railway Production wp-config.php ===

// --- TEMP DEBUG LOG DUMP ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- END TEMP DEBUG LOG DUMP ---

// DB – uses Railway MYSQL* vars if available
define('DB_HOST', (getenv('MYSQLHOST') ?: 'mysql.railway.internal') . ':' . (getenv('MYSQLPORT') ?: '3306'));
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
// SECURITY: No plaintext password fallback — the env var MUST be set in Railway
if (!getenv('MYSQLPASSWORD')) {
    error_log('[GlamLux] MYSQLPASSWORD env var is not set. Database connection will fail.');
}
define('DB_PASSWORD', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'railway');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// SECURITY: Auth keys pulled from env vars. Unique strong fallbacks are used when env vars are absent.
// Set these in Railway > Variables for production security.
define('AUTH_KEY', getenv('AUTH_KEY') ?: 'X!kP9#mNz2Lq&v7YrTb@cJeWsAuFdGh0');
define('SECURE_AUTH_KEY', getenv('SECURE_AUTH_KEY') ?: 'D5jZnQ3!xHpRwS8oVcMtLa4UbEyKfIg6');
define('LOGGED_IN_KEY', getenv('LOGGED_IN_KEY') ?: 'Bm1eN^7zXsYvTcP#QrLdA9wFkHuJgZ2!');
define('NONCE_KEY', getenv('NONCE_KEY') ?: 'Wr6uSt!0BpXqN5JvDyCfEl3aIMGkHZ8#');
define('AUTH_SALT', getenv('AUTH_SALT') ?: 'Jc4dP!2eR7sL0uVhXnBqWtKmZAyFgO9!');
define('SECURE_AUTH_SALT', getenv('SECURE_AUTH_SALT') ?: 'Gf8aZ#3iM5xQ2sLTdCwYnEbRuHpVkO1!');
define('LOGGED_IN_SALT', getenv('LOGGED_IN_SALT') ?: 'Hn9kT!1rU6vM3bPeXcLfIwSzJyDQgA7#');
define('NONCE_SALT', getenv('NONCE_SALT') ?: 'Py2wF#5jS8nK0qAeZbTxGcMrVuDHiOL4!');

$table_prefix = 'wp_';
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// ─────────────────────────────────────────────────────────────────────────────
// Set WP_ENVIRONMENT_TYPE (must be before HTTPS enforcement)
// ─────────────────────────────────────────────────────────────────────────────
if (!defined('WP_ENVIRONMENT_TYPE')) {
    $env = getenv('WP_ENVIRONMENT_TYPE') ?: 'production';
    define('WP_ENVIRONMENT_TYPE', $env);
}

// ─────────────────────────────────────────────────────────────────────────────
// PHASE 0: HTTPS/TLS ENFORCEMENT (SECURITY HARDENING)
// ─────────────────────────────────────────────────────────────────────────────

// Force HTTPS behind Railway proxy
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $_SERVER['HTTPS'] = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'on' : 'off';
}

// CRITICAL: Enforce HTTPS in production environment
if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
    // Redirect HTTP to HTTPS
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
        if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            exit;
        }
    }

    // HSTS: Force browsers to always use HTTPS (1 year with subdomains)
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

    // Force secure cookies in production
    define('FORCE_SSL_ADMIN', true);
    define('FORCE_SSL_LOGIN', true);
}

// Dynamic site URL — works for any Railway subdomain or custom domain
if (!empty($_SERVER['HTTP_HOST'])) {
    $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    define('WP_HOME', $proto . $_SERVER['HTTP_HOST']);
    define('WP_SITEURL', $proto . $_SERVER['HTTP_HOST']);
}

// WP_ENVIRONMENT_TYPE is already set above (before HTTPS enforcement)

// Redis Object Cache — only enable if the drop-in actually exists (prevents crash when Redis is not yet available)
$redis_host = getenv('REDISHOST') ?: '';
$redis_dropin = __DIR__ . '/wp-content/object-cache.php';
if ($redis_host && file_exists($redis_dropin)) {
    define('WP_CACHE', true);
    define('WP_REDIS_HOST', $redis_host);
    define('WP_REDIS_PORT', (int) (getenv('REDISPORT') ?: 6379));
    $redis_pass = getenv('REDIS_PASSWORD') ?: null;
    if ($redis_pass)
        define('WP_REDIS_PASSWORD', $redis_pass);

    // PHASE 5: Smart cache eviction policy prevents OOM crashes
    // allkeys-lru will evict least-recently-used keys when memory limit is reached
    // instead of rejecting writes and crashing the application
    define('WP_REDIS_MAXMEMORY_POLICY', 'allkeys-lru');
    define('WP_REDIS_MAXMEMORY', '256M');
} else {
    define('WP_CACHE', false);
}

// Disable WP-Cron — Railway scheduled job handles this
define('DISABLE_WP_CRON', true);

if (!defined('ABSPATH'))
    define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
