<?php

/**
 * Health Controller
 * Implements /glamlux/v1/health for infrastructure observability.
 */
class GlamLux_Health_Controller extends GlamLux_Base_Controller
{
    protected $namespace = 'glamlux/v1';
    protected $rest_base = 'health';

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
                array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_health'),
                'permission_callback' => '__return_true',
            )
        ));
    }

    public function get_health(WP_REST_Request $request)
    {
        global $wpdb;

        // Validate DB Connection using a lightweight query
        $db_status = 'disconnected';
        if ($wpdb->get_var('SELECT 1') === '1') {
            $db_status = 'connected';
        }

        return rest_ensure_response(array(
            'status' => 'ok',
            'timestamp' => current_time('mysql', true),
            'database' => $db_status,
            'version' => defined('GLAMLUX_VERSION') ? GLAMLUX_VERSION : 'unknown'
        ));
    }
}
