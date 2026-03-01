<?php
class GlamLux_Payroll_Controller extends GlamLux_Base_Controller
{
    public function register_routes()
    {
        register_rest_route('glamlux/v1', '/payroll', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_payroll'],
            'permission_callback' => [$this, 'check_read_permissions'],
            'args' => [
                'staff_id' => ['type' => 'integer', 'required' => true]
            ]
        ]);
    }
    public function check_read_permissions()
    {
        if (!is_user_logged_in())
            return new WP_Error('unauth', 'Login required.', ['status' => 401]);
        if (current_user_can('manage_options') || current_user_can('manage_glamlux_salon'))
            return true;
        // Allow staff to view their own
        $user = wp_get_current_user();
        if (in_array('glamlux_staff', (array)$user->roles))
            return true;
        return new WP_Error('forbidden', 'Admin access required.', ['status' => 403]);
    }
    public function get_payroll($request)
    {
        $staff_id = $request->get_param('staff_id');
        $cache_key = "gl_pay_{$staff_id}_" . date('Ym');
        $data = get_transient($cache_key);
        if (false === $data) {
            $repo = new GlamLux_Repo_Payroll();
            $data = method_exists($repo, 'get_staff_payroll') ? $repo->get_staff_payroll($staff_id) : [];
            set_transient($cache_key, $data, HOUR_IN_SECONDS);
        }
        return rest_ensure_response($data);
    }
}
