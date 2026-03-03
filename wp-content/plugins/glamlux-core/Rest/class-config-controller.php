<?php
/**
 * GlamLux Config Controller
 * 
 * Provides a discovery endpoint for headless frontends (e.g. Flutter mobile app)
 * to retrieve global theme variables, feature flags, and API routing.
 */
class GlamLux_Config_Controller extends GlamLux_Base_Controller
{
    public function register_routes()
    {
        register_rest_route('glamlux/v1', '/config', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_config'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_config($request)
    {
        return rest_ensure_response([
            'theme' => [
                'primary' => '#C6A75E',
                'primaryDark' => '#A8893E',
                'background' => '#0F0F0F',
                'textPrimary' => '#0F0F0F',
                'textLight' => '#F8F7F3'
            ],
            'features' => [
                'booking_active' => true,
                'memberships_active' => true,
                'franchise_leads' => true,
                'ecommerce_integration' => false,
            ],
            'version' => '1.2.0',
            'endpoints' => [
                'base' => rest_url('glamlux/v1'),
                'auth' => rest_url('glamlux/v1/auth/token')
            ],
            'site' => [
                'name' => get_bloginfo('name'),
                'description' => get_bloginfo('description')
            ]
        ]);
    }
}
