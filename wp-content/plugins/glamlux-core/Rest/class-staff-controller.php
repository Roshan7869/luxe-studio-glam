<?php
/**
 * GlamLux Staff REST Controller
 *
 * LAYER: Presentation (REST API) Layer
 * RULE:  THIS FILE CONTAINS ZERO BUSINESS LOGIC AND ZERO SQL.
 *        It receives requests, extracts parameters, and delegates 100%
 *        of the work to GlamLux_Service_Staff.
 *
 * Endpoints:
 *   GET    /glamlux/v1/staff              → List all staff (filtered)
 *   GET    /glamlux/v1/staff/{id}         → Get a single staff member
 *   POST   /glamlux/v1/staff              → Create a new staff member
 *   PUT    /glamlux/v1/staff/{id}         → Update staff details
 *   DELETE /glamlux/v1/staff/{id}         → Deactivate staff member
 */
class GlamLux_Staff_Controller extends GlamLux_Base_Controller
{

    public function register_routes(): void
    {
        // Collection
        register_rest_route('glamlux/v1', '/staff', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'list_staff'],
                'permission_callback' => [$this, 'require_staff_or_admin'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_staff'],
                'permission_callback' => [$this, 'require_admin'],
            ],
        ]);

        // Single item
        register_rest_route('glamlux/v1', '/staff/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_staff'],
                'permission_callback' => [$this, 'require_staff_or_admin'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_staff'],
                'permission_callback' => [$this, 'require_admin'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deactivate_staff'],
                'permission_callback' => [$this, 'require_admin'],
            ],
        ]);

        // Performance endpoint
        register_rest_route('glamlux/v1', '/staff/(?P<id>\d+)/performance', [
            'methods' => 'GET',
            'callback' => [$this, 'get_performance'],
            'permission_callback' => [$this, 'require_staff_or_admin'],
        ]);
    }

    public function list_staff(WP_REST_Request $request): WP_REST_Response
    {
        $service = new GlamLux_Service_Staff();
        $filters = [
            'salon_id' => $request->get_param('salon_id'),
            'status' => $request->get_param('status'),
        ];
        return rest_ensure_response($service->get_all(array_filter($filters)));
    }

    public function get_staff(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int)$request->get_param('id');
        $service = new GlamLux_Service_Staff();
        $staff = $service->get_by_id($id);

        if (!$staff) {
            return new WP_Error('not_found', 'Staff member not found.', ['status' => 404]);
        }
        return rest_ensure_response($staff);
    }

    public function create_staff(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $service = new GlamLux_Service_Staff();
        $result = $service->create($request->get_params());

        if (is_wp_error($result))
            return $result;
        return rest_ensure_response(['success' => true, 'id' => $result]);
    }

    public function update_staff(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int)$request->get_param('id');
        $service = new GlamLux_Service_Staff();
        $result = $service->update($id, $request->get_params());

        if (is_wp_error($result))
            return $result;
        return rest_ensure_response(['success' => $result]);
    }

    public function deactivate_staff(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int)$request->get_param('id');
        $service = new GlamLux_Service_Staff();
        $result = $service->deactivate($id);

        if (is_wp_error($result))
            return $result;
        return rest_ensure_response(['success' => true, 'message' => 'Staff member deactivated.']);
    }

    public function get_performance(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int)$request->get_param('id');
        $from = $request->get_param('from') ?: date('Y-m-01');
        $to = $request->get_param('to') ?: date('Y-m-t');
        $service = new GlamLux_Service_Staff();
        $data = $service->get_performance($id, $from, $to);
        return rest_ensure_response($data);
    }
}
