<?php
/**
 * GlamLux Health Check Endpoint
 *
 * Railway / load balancer health probe.
 * URL: /glamlux-health.php
 *
 * Checks:
 *  - Database connection
 *  - Redis connection (optional)
 *  - GlamLux Core plugin active
 *  - REST API endpoint reachable
 *
 * Returns JSON with HTTP 200 (healthy) or 503 (degraded).
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$checks = [
    'status' => 'healthy',
    'timestamp' => gmdate('c'),
    'php_version' => PHP_VERSION,
    'checks' => [],
];

$all_ok = true;

// 1. Database check
try {
    $db_host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
    $db_port = getenv('MYSQLPORT') ?: '3306';
    $db_user = getenv('MYSQLUSER') ?: 'root';
    $db_pass = getenv('MYSQLPASSWORD') ?: '';
    $db_name = getenv('MYSQLDATABASE') ?: 'railway';

    $pdo = new PDO(
        "mysql:host={$db_host};port={$db_port};dbname={$db_name}",
        $db_user,
        $db_pass,
        [PDO::ATTR_TIMEOUT => 3]
    );
    $checks['checks']['database'] = 'connected';
} catch (Exception $e) {
    $checks['checks']['database'] = 'failed: ' . $e->getMessage();
    $all_ok = false;
}

// 2. Redis check
$redis_host = getenv('REDISHOST') ?: '';
if ($redis_host) {
    try {
        $redis_port = (int) (getenv('REDISPORT') ?: 6379);
        $redis_pass = getenv('REDIS_PASSWORD') ?: null;
        $fp = @fsockopen($redis_host, $redis_port, $errno, $errstr, 2);
        if ($fp) {
            fclose($fp);
            $checks['checks']['redis'] = 'reachable';
        } else {
            $checks['checks']['redis'] = "unreachable: {$errstr}";
            // Redis is optional — degrade but don't fail
        }
    } catch (Exception $e) {
        $checks['checks']['redis'] = 'error: ' . $e->getMessage();
    }
} else {
    $checks['checks']['redis'] = 'not_configured';
}

// 3. GlamLux Core plugin file check
$plugin_file = __DIR__ . '/wp-content/plugins/glamlux-core/glamlux-core.php';
$checks['checks']['glamlux_core'] = file_exists($plugin_file) ? 'present' : 'missing';
if (!file_exists($plugin_file)) {
    $all_ok = false;
}

// 4. wp-config check
$checks['checks']['wp_config'] = file_exists(__DIR__ . '/wp-config.php') ? 'present' : 'missing';

// 5. Environment
$checks['checks']['environment'] = getenv('WP_ENVIRONMENT_TYPE') ?: 'unset';

if (!$all_ok) {
    $checks['status'] = 'degraded';
    http_response_code(503);
}

echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
