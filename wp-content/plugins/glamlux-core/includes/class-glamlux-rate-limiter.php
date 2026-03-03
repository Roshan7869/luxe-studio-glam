<?php
/**
 * API Rate Limiter — Sprint A
 *
 * Transient-based token bucket rate limiter for REST API endpoints.
 * Hooks into rest_pre_dispatch to intercept all glamlux/v1/* requests.
 *
 * LAYER: Infrastructure / Security
 */
class GlamLux_Rate_Limiter
{
    const PUBLIC_LIMIT = 60; // requests per minute for unauthenticated
    const AUTH_LIMIT = 120; // requests per minute for authenticated
    const WINDOW = 60; // seconds

    public function __construct()
    {
        add_filter('rest_pre_dispatch', [$this, 'check_rate_limit'], 10, 3);
    }

    /**
     * Check rate limit before dispatching any glamlux REST request.
     */
    public function check_rate_limit($result, $server, $request)
    {
        // Only rate-limit our own namespace
        $route = $request->get_route();
        if (strpos($route, '/glamlux/v1/') === false) {
            return $result;
        }

        // Skip rate limiting for admin users
        if (current_user_can('manage_options')) {
            return $result;
        }

        $ip = $this->get_client_ip();
        $key = 'gl_rl_' . md5($ip);
        $limit = is_user_logged_in() ?self::AUTH_LIMIT : self::PUBLIC_LIMIT;

        $data = get_transient($key);
        if ($data === false) {
            // First request in window
            set_transient($key, ['count' => 1, 'start' => time()], self::WINDOW);
            return $result;
        }

        $data['count']++;
        $elapsed = time() - $data['start'];

        if ($data['count'] > $limit && $elapsed < self::WINDOW) {
            $retry_after = self::WINDOW - $elapsed;

            glamlux_log_error('Rate limit exceeded', [
                'ip' => $ip,
                'route' => $route,
                'count' => $data['count'],
                'limit' => $limit,
            ]);

            return new WP_Error(
                'rate_limit_exceeded',
                sprintf('Rate limit exceeded. Try again in %d seconds.', $retry_after),
            [
                'status' => 429,
                'headers' => ['Retry-After' => $retry_after],
            ]
                );
        }

        // Update count
        $remaining_ttl = max(1, self::WINDOW - $elapsed);
        set_transient($key, $data, $remaining_ttl);

        return $result;
    }

    /**
     * Get client IP address, respecting proxy headers.
     */
    private function get_client_ip(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // X-Forwarded-For can contain multiple IPs — use first
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
