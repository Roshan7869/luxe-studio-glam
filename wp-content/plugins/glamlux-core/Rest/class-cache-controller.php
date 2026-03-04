<?php
/**
 * Cache Management Controller - REST API for cache monitoring and control
 *
 * @package GlamLux
 * @subpackage REST
 * @since 7.2
 */

class GlamLux_Cache_Controller extends GlamLux_Base_Controller
{
    protected $namespace = 'glamlux/v1';
    protected $resource_name = 'cache';

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/stats',
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_cache_stats'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => []
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/flush',
            [
                'methods' => 'POST',
                'callback' => [$this, 'flush_cache'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => [
                    'type' => [
                        'type' => 'string',
                        'default' => 'all',
                        'enum' => ['all', 'salons', 'services', 'staff', 'events']
                    ]
                ]
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/warmup',
            [
                'methods' => 'POST',
                'callback' => [$this, 'warmup_cache'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => []
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/config',
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_cache_config'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => []
            ]
        );
    }

    /**
     * Get cache statistics
     */
    public function get_cache_stats($request)
    {
        try {
            require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
            $cache = glamlux_cache();
            $stats = $cache->get_stats();

            return new WP_REST_Response(
                [
                    'success' => true,
                    'data' => $stats,
                    'timestamp' => current_time('mysql')
                ],
                200
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Flush cache by type
     */
    public function flush_cache($request)
    {
        try {
            $type = $request->get_param('type') ?? 'all';
            require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
            $cache = glamlux_cache();

            $flushed = 0;

            switch ($type) {
                case 'salons':
                    $flushed = $cache->invalidate_pattern('salons_*');
                    break;
                case 'services':
                    $flushed = $cache->invalidate_pattern('services_*');
                    break;
                case 'staff':
                    $flushed = $cache->invalidate_pattern('staff_*');
                    break;
                case 'events':
                    $flushed = $cache->invalidate_pattern('events_*');
                    break;
                case 'all':
                default:
                    $flushed_salons = $cache->invalidate_pattern('salons_*');
                    $flushed_services = $cache->invalidate_pattern('services_*');
                    $flushed_staff = $cache->invalidate_pattern('staff_*');
                    $flushed_events = $cache->invalidate_pattern('events_*');
                    $flushed = $flushed_salons + $flushed_services + $flushed_staff + $flushed_events;
                    break;
            }

            return new WP_REST_Response(
                [
                    'success' => true,
                    'type' => $type,
                    'keys_flushed' => $flushed,
                    'message' => "Cache flushed: {$flushed} keys"
                ],
                200
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Warmup cache with frequently accessed data
     */
    public function warmup_cache($request)
    {
        try {
            require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
            $cache = glamlux_cache();

            global $wpdb;
            $warmed_up = [];

            // Warm up salons list
            $salons = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts WHERE post_type = 'salon' AND post_status = 'publish' LIMIT 50");
            if (!empty($salons)) {
                $cache->set('salons_list', $salons, 3600); // 1 hour TTL
                $warmed_up['salons'] = count($salons);
            }

            // Warm up services list
            $services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts WHERE post_type = 'service' AND post_status = 'publish' LIMIT 100");
            if (!empty($services)) {
                $cache->set('services_list', $services, 7200); // 2 hour TTL
                $warmed_up['services'] = count($services);
            }

            // Warm up staff list
            $staff = get_users(['role' => 'staff', 'number' => 50]);
            if (!empty($staff)) {
                $cache->set('staff_list', $staff, 3600);
                $warmed_up['staff'] = count($staff);
            }

            return new WP_REST_Response(
                [
                    'success' => true,
                    'warmed_up' => $warmed_up,
                    'total_items' => array_sum($warmed_up)
                ],
                200
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Get cache configuration
     */
    public function get_cache_config($request)
    {
        try {
            require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
            $cache = glamlux_cache();

            $config = [
                'redis_enabled' => defined('GLAMLUX_REDIS_HOST'),
                'redis_host' => defined('GLAMLUX_REDIS_HOST') ? GLAMLUX_REDIS_HOST : 'Not configured',
                'redis_port' => defined('GLAMLUX_REDIS_PORT') ? GLAMLUX_REDIS_PORT : 6379,
                'redis_db' => defined('GLAMLUX_REDIS_DB') ? GLAMLUX_REDIS_DB : 0,
                'cache_available' => $cache->is_available(),
                'fallback_method' => $cache->is_available() ? 'Redis' : 'WordPress Transients',
                'ttl_settings' => [
                    'salons' => 3600,     // 1 hour
                    'services' => 7200,   // 2 hours
                    'staff' => 3600,      // 1 hour
                    'events' => 1800      // 30 minutes
                ]
            ];

            return new WP_REST_Response(
                [
                    'success' => true,
                    'config' => $config
                ],
                200
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Check admin permission
     */
    public function check_admin_permission()
    {
        return current_user_can('manage_options');
    }
}

// Instantiate and register routes
add_action('rest_api_init', function () {
    $controller = new GlamLux_Cache_Controller();
    $controller->register_routes();
});
