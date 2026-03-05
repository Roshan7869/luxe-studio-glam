<?php
/**
 * Rate Limiting Middleware for API Protection
 *
 * Implements sliding window rate limiting with per-IP and per-user tracking.
 * Protects against abuse and ensures fair resource allocation.
 *
 * @package GlamLux
 * @subpackage Services
 * @since 7.3
 */

class GlamLux_Advanced_Rate_Limiter
{
    private $cache;

    // Default rate limits (requests per time window)
    private $limits = [
        'default' => ['requests' => 60, 'window' => 60],        // 60 req/min
        'auth' => ['requests' => 5, 'window' => 900],           // 5 req/15min
        'search' => ['requests' => 30, 'window' => 60],         // 30 req/min
        'api' => ['requests' => 100, 'window' => 60],           // 100 req/min
        'webhook' => ['requests' => 1000, 'window' => 60],      // 1000 req/min
    ];

    public function __construct()
    {
        require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
        $this->cache = glamlux_cache();
    }

    /**
     * Check rate limit for request
     */
    public function check_limit($identifier, $endpoint = 'default', $user_id = null)
    {
        $limit_config = $this->limits[$endpoint] ?? $this->limits['default'];

        $cache_key = $this->get_cache_key($identifier, $endpoint);
        $current_count = $this->get_current_count($cache_key);

        if ($current_count >= $limit_config['requests']) {
            glamlux_log("Rate limit exceeded: {$endpoint} - {$identifier}");
            return [
                'allowed' => false,
                'limit' => $limit_config['requests'],
                'current' => $current_count,
                'reset_at' => time() + $limit_config['window']
            ];
        }

        // Increment counter
        $this->cache->increment($cache_key, 1);

        // Set expiry if new
        if ($current_count === 0) {
            $this->cache->set(
                $cache_key,
                1,
                $limit_config['window']
            );
        }

        return [
            'allowed' => true,
            'limit' => $limit_config['requests'],
            'current' => $current_count + 1,
            'remaining' => $limit_config['requests'] - ($current_count + 1),
            'reset_at' => time() + $limit_config['window']
        ];
    }

    /**
     * Get current count from cache
     */
    private function get_current_count($cache_key)
    {
        $count = $this->cache->get($cache_key);
        return intval($count) ?? 0;
    }

    /**
     * Generate cache key
     */
    private function get_cache_key($identifier, $endpoint)
    {
        return "rate_limit_" . $endpoint . "_" . md5($identifier);
    }

    /**
     * Middleware for WordPress REST API
     */
    public static function rest_api_middleware($response, $server, $request)
    {
        $limiter = new self();
        $endpoint = self::get_endpoint_type($request->get_route());
        $identifier = self::get_client_identifier();
        $user_id = get_current_user_id();

        $result = $limiter->check_limit($identifier, $endpoint, $user_id);

        if (!$result['allowed']) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                [
                    'status' => 429,
                    'limit' => $result['limit'],
                    'remaining' => 0,
                    'reset_at' => $result['reset_at']
                ]
            );
        }

        // Add rate limit headers to response
        $response->header('X-RateLimit-Limit', $result['limit']);
        $response->header('X-RateLimit-Remaining', $result['remaining']);
        $response->header('X-RateLimit-Reset', $result['reset_at']);

        return $response;
    }

    /**
     * Get endpoint type for rate limiting
     */
    private static function get_endpoint_type($route)
    {
        if (strpos($route, '/auth/') !== false) {
            return 'auth';
        } elseif (strpos($route, '/search') !== false) {
            return 'search';
        } elseif (strpos($route, '/webhook') !== false) {
            return 'webhook';
        }

        return 'api';
    }

    /**
     * Get client IP identifier
     */
    private static function get_client_identifier()
    {
        $ip = self::get_client_ip();
        $user_id = get_current_user_id();

        if ($user_id) {
            return "user_" . $user_id;
        }

        return "ip_" . $ip;
    }

    /**
     * Get client IP address
     */
    private static function get_client_ip()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Handle multiple IPs (take first)
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Validate IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return '0.0.0.0';
    }

    /**
     * Set custom rate limit for endpoint
     */
    public function set_limit($endpoint, $requests, $window_seconds)
    {
        $this->limits[$endpoint] = [
            'requests' => $requests,
            'window' => $window_seconds
        ];
    }

    /**
     * Get rate limit statistics
     */
    public static function get_statistics()
    {
        global $wpdb;

        $stats = [];

        // Count requests by endpoint (if tracking table exists)
        // This would require additional implementation

        return [
            'timestamp' => current_time('mysql'),
            'active_endpoints' => count((new self())->limits)
        ];
    }

    /**
     * Reset user rate limit
     */
    public static function reset_user_limit($user_id, $endpoint = null)
    {
        require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
        $cache = glamlux_cache();

        $pattern = "rate_limit_";
        if ($endpoint) {
            $pattern .= $endpoint . "_*";
        } else {
            $pattern .= "*";
        }

        $count = $cache->invalidate_pattern($pattern);
        glamlux_log("Rate limit reset for user {$user_id}: {$count} keys");

        return $count;
    }
}

/**
 * Register rate limiting middleware on REST API
 */
add_filter('rest_pre_dispatch', function ($response, $server, $request) {
    // Skip internal requests
    if (defined('DOING_CRON') && DOING_CRON) {
        return $response;
    }

    // Only apply to specific endpoints
    $route = $request->get_route();
    if (strpos($route, '/glamlux/') !== false) {
        return GlamLux_Advanced_Rate_Limiter::rest_api_middleware($response, $server, $request);
    }

    return $response;
}, 10, 3);
