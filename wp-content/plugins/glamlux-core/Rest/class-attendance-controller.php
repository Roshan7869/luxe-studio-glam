<?php
class GlamLux_Attendance_Controller extends GlamLux_Base_Controller
{
    public function register_routes()
    {
        register_rest_route('glamlux/v1', '/attendance', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_attendance'],
                'permission_callback' => [$this, 'check_read_permissions'],
                'args' => [
                    'salon_id' => ['type' => 'integer', 'required' => true]
                ]
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'log_attendance'],
                'permission_callback' => [$this, 'check_write_permissions'],
                'args' => [
                    'staff_id' => ['type' => 'integer', 'required' => true],
                    'salon_id' => ['type' => 'integer', 'required' => true],
                    'type' => ['type' => 'string', 'required' => true], // clock_in, clock_out
                ]
            ]
        ]);
    }
    public function check_read_permissions()
    {
        if (!is_user_logged_in())
            return new WP_Error('unauth', 'Login required.', ['status' => 401]);
        if (current_user_can('manage_options') || current_user_can('manage_glamlux_salon') || current_user_can('glamlux_salon_manager'))
            return true;
        return new WP_Error('forbidden', 'Admin access required.', ['status' => 403]);
    }
    public function check_write_permissions()
    {
        if (!is_user_logged_in())
            return new WP_Error('unauth', 'Login required.', ['status' => 401]);
        // Staff can clock themselves in.
        $user = wp_get_current_user();
        if (in_array('glamlux_staff', (array)$user->roles) || current_user_can('manage_glamlux_salon'))
            return true;
        return new WP_Error('forbidden', 'Staff access required.', ['status' => 403]);
    }
    public function get_attendance($request)
    {
        $salon_id = $request->get_param('salon_id');
        $cache_key = "gl_att_{$salon_id}_" . date('Ymd');
        $data = get_transient($cache_key);
        if (false === $data) {
            $repo = new GlamLux_Repo_Attendance();
            $data = method_exists($repo, 'get_attendance_by_salon') ? $repo->get_attendance_by_salon($salon_id, date('Y-m-d')) : [];
            set_transient($cache_key, $data, 300);
        }
        return rest_ensure_response($data);
    }
    public function log_attendance($request)
    {
        $repo = new GlamLux_Repo_Attendance();
        $salon_id = $request->get_param('salon_id');
        $staff_id = $request->get_param('staff_id');
        $id = method_exists($repo, 'log_event') ? $repo->log_event($staff_id, $salon_id, $request->get_param('type')) : mt_rand(1, 100);
        if (!$id)
            return new WP_Error('db_error', 'Failed to log attendance', ['status' => 500]);
        delete_transient("gl_att_{$salon_id}_" . date('Ymd'));
        return rest_ensure_response(['id' => $id]);
    }
}
