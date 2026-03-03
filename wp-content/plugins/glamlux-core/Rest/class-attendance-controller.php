<?php
/**
 * GlamLux Attendance REST Controller
 *
 * Phase 1.2: Refactored to delegate to GlamLux_Service_Attendance.
 * Previous version used non-existent repo methods (get_attendance_by_salon, log_event).
 *
 * Endpoints:
 *   GET  /glamlux/v1/attendance          → Today's attendance for a salon
 *   POST /glamlux/v1/attendance/check-in → Staff clock-in
 *   POST /glamlux/v1/attendance/check-out→ Staff clock-out
 *   GET  /glamlux/v1/attendance/summary  → Monthly summary for a staff member
 */
class GlamLux_Attendance_Controller extends GlamLux_Base_Controller
{
    public function register_routes()
    {
        // Daily attendance for a salon
        register_rest_route('glamlux/v1', '/attendance', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_attendance'],
            'permission_callback' => [$this, 'check_read_permissions'],
            'args' => [
                'salon_id' => ['type' => 'integer', 'required' => true],
                'date' => ['type' => 'string', 'required' => false], // YYYY-MM-DD, defaults to today
            ]
        ]);

        // Check-in
        register_rest_route('glamlux/v1', '/attendance/check-in', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'check_in'],
            'permission_callback' => [$this, 'check_write_permissions'],
            'args' => [
                'staff_id' => ['type' => 'integer', 'required' => true],
                'salon_id' => ['type' => 'integer', 'required' => true],
            ]
        ]);

        // Check-out
        register_rest_route('glamlux/v1', '/attendance/check-out', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'check_out'],
            'permission_callback' => [$this, 'check_write_permissions'],
            'args' => [
                'staff_id' => ['type' => 'integer', 'required' => true],
            ]
        ]);

        // Monthly summary
        register_rest_route('glamlux/v1', '/attendance/summary', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_summary'],
            'permission_callback' => [$this, 'check_read_permissions'],
            'args' => [
                'staff_id' => ['type' => 'integer', 'required' => true],
                'month' => ['type' => 'string', 'required' => true], // YYYY-MM
            ]
        ]);
    }

    public function check_read_permissions()
    {
        if (!is_user_logged_in())
            return new WP_Error('unauth', 'Login required.', ['status' => 401]);
        if (current_user_can('manage_options') || current_user_can('manage_glamlux_salon') || current_user_can('manage_glamlux_franchise') || current_user_can('glamlux_check_attendance'))
            return true;
        return new WP_Error('forbidden', 'Access denied.', ['status' => 403]);
    }

    public function check_write_permissions()
    {
        if (!is_user_logged_in())
            return new WP_Error('unauth', 'Login required.', ['status' => 401]);
        $user = wp_get_current_user();
        if (in_array('glamlux_staff', (array)$user->roles) || current_user_can('manage_glamlux_salon') || current_user_can('manage_glamlux_franchise') || current_user_can('manage_options'))
            return true;
        return new WP_Error('forbidden', 'Staff access required.', ['status' => 403]);
    }

    public function get_attendance($request)
    {
        $salon_id = (int)$request->get_param('salon_id');
        $date = $request->get_param('date') ?: date('Y-m-d');

        $cache_key = "gl_att_{$salon_id}_{$date}";
        $data = get_transient($cache_key);
        if (false === $data) {
            $repo = new GlamLux_Repo_Attendance();
            $data = $repo->get_attendance_by_salon($salon_id, $date);
            set_transient($cache_key, $data, 300);
        }
        return rest_ensure_response($data);
    }

    public function check_in($request)
    {
        $staff_id = (int)$request->get_param('staff_id');
        $salon_id = (int)$request->get_param('salon_id');

        $service = new GlamLux_Service_Attendance();
        $result = $service->check_in($staff_id, $salon_id);

        if (!$result) {
            return new WP_Error('already_checked_in', 'Staff member has already checked in today.', ['status' => 409]);
        }

        delete_transient("gl_att_{$salon_id}_" . date('Ymd'));
        return rest_ensure_response(['success' => true, 'message' => 'Checked in successfully.']);
    }

    public function check_out($request)
    {
        $staff_id = (int)$request->get_param('staff_id');

        $service = new GlamLux_Service_Attendance();
        $result = $service->check_out($staff_id);

        if (!$result) {
            return new WP_Error('no_checkin', 'No active check-in found for today, or already checked out.', ['status' => 409]);
        }

        return rest_ensure_response(['success' => true, 'message' => 'Checked out successfully.']);
    }

    public function get_summary($request)
    {
        $staff_id = (int)$request->get_param('staff_id');
        $month = sanitize_text_field($request->get_param('month'));

        try {
            $service = new GlamLux_Service_Attendance();
            $summary = $service->get_monthly_summary($staff_id, $month);
            return rest_ensure_response($summary);
        }
        catch (\InvalidArgumentException $e) {
            return new WP_Error('invalid_month', $e->getMessage(), ['status' => 400]);
        }
    }
}
