<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once GLAMLUX_PLUGIN_DIR . 'Rest/class-base-controller.php';

class GlamLux_Health_Controller extends GlamLux_Base_Controller
{
    public function __construct()
    {
        $this->namespace = 'glamlux/v1';
        $this->rest_base = 'health';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_health_status'],
                'permission_callback' => '__return_true', // Publicly accessible for health checks
            ],
        ]);
    }

    public function get_health_status(WP_REST_Request $request)
    {
        global $wpdb;

        // 1. Check Database connection
        $db_connected = false;
        if ($wpdb->check_connection()) {
            $db_connected = true;
        }

        // 2. Check Redis connection
        $redis_connected = false;
        if (function_exists('wp_cache_get_redis') && wp_cache_get_redis()) {
            try {
                $redis = wp_cache_get_redis();
                if ($redis->ping()) {
                    $redis_connected = true;
                }
            } catch (Exception $e) {
                // Redis ping failed
                $redis_connected = false;
            }
        } elseif (class_exists('Redis')) {
            // Fallback direct check if object cache drop-in isn't fully loaded yet
            try {
                $redis_host = getenv('REDISHOST') ?: (defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '');
                $redis_port = getenv('REDISPORT') ?: (defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379);
                if ($redis_host) {
                    $redis = new Redis();
                    if ($redis->connect($redis_host, $redis_port)) {
                        $redis_pass = getenv('REDIS_PASSWORD') ?: (defined('WP_REDIS_PASSWORD') ? WP_REDIS_PASSWORD : '');
                        if ($redis_pass) {
                            $redis->auth($redis_pass);
                        }
                        if ($redis->ping()) {
                            $redis_connected = true;
                        }
                    }
                }
            } catch (Exception $e) {
                $redis_connected = false;
            }
        }

        // 3. Overall status
        $status = ($db_connected) ? 'ok' : 'degraded';

        return rest_ensure_response([
            'status' => $status,
            'database' => $db_connected ? 'connected' : 'disconnected',
            'redis' => $redis_connected ? 'connected' : 'disconnected',
            'plugin_version' => defined('GLAMLUX_VERSION') ? GLAMLUX_VERSION : 'unknown',
            'timestamp' => current_time('mysql'),
        ]);
    }
}
