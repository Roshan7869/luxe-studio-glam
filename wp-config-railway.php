<?php
// === Luxe Studio Glam - Railway Production wp-config.php ===

// DB – uses Railway MYSQL* vars if available, falls back to confirmed working private hostname
define('DB_HOST', (getenv('MYSQLHOST') ?: 'mysql.railway.internal') . ':' . (getenv('MYSQLPORT') ?: '3306'));
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASSWORD', getenv('MYSQLPASSWORD') ?: 'NsDeFKtbxzhdOczkobJdDUAKVvhGrdlk');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'railway');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

define('AUTH_KEY', getenv('AUTH_KEY') ?: 'gl-auth-key-1');
define('SECURE_AUTH_KEY', getenv('SECURE_AUTH_KEY') ?: 'gl-secure-auth-key-2');
define('LOGGED_IN_KEY', getenv('LOGGED_IN_KEY') ?: 'gl-logged-in-key-3');
define('NONCE_KEY', getenv('NONCE_KEY') ?: 'gl-nonce-key-4');
define('AUTH_SALT', getenv('AUTH_SALT') ?: 'gl-auth-salt-5');
define('SECURE_AUTH_SALT', getenv('SECURE_AUTH_SALT') ?: 'gl-secure-auth-salt-6');
define('LOGGED_IN_SALT', getenv('LOGGED_IN_SALT') ?: 'gl-logged-in-salt-7');
define('NONCE_SALT', getenv('NONCE_SALT') ?: 'gl-nonce-salt-8');

$table_prefix = 'wp_';
define('WP_DEBUG', false);

// Force HTTPS behind Railway proxy
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// Dynamic site URL — works for any Railway subdomain or custom domain
if (!empty($_SERVER['HTTP_HOST'])) {
    $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    define('WP_HOME', $proto . $_SERVER['HTTP_HOST']);
    define('WP_SITEURL', $proto . $_SERVER['HTTP_HOST']);
}

// Redis Object Cache — only enable if the drop-in actually exists (prevents crash when Redis is not yet available)
$redis_host = getenv('REDISHOST') ?: '';
$redis_dropin = __DIR__ . '/wp-content/object-cache.php';
if ($redis_host && file_exists($redis_dropin)) {
    define('WP_CACHE', true);
    define('WP_REDIS_HOST', $redis_host);
    define('WP_REDIS_PORT', (int)(getenv('REDISPORT') ?: 6379));
    $redis_pass = getenv('REDIS_PASSWORD') ?: null;
    if ($redis_pass)
        define('WP_REDIS_PASSWORD', $redis_pass);
}
else {
    define('WP_CACHE', false);
}

// Disable WP-Cron — Railway scheduled job handles this
define('DISABLE_WP_CRON', true);

if (!defined('ABSPATH'))
    define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
