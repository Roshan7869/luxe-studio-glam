<?php
/**
 * GlamLux Lead REST Controller
 *
 * LAYER: Presentation (REST API) Layer
 * RULE:  THIS FILE CONTAINS ZERO BUSINESS LOGIC AND ZERO SQL.
 *        It receives requests, extracts parameters, and delegates 100%
 *        of the work to GlamLux_Service_Lead.
 *
 * Endpoints:
 *   GET    /glamlux/v1/leads                       → List all leads
 *   POST   /glamlux/v1/leads                       → Capture a new lead
 *   GET    /glamlux/v1/leads/{id}                  → Get single lead
 *   PUT    /glamlux/v1/leads/{id}/status           → Update lead status
 *   PUT    /glamlux/v1/leads/{id}/assign           → Assign lead to user
 *   POST   /glamlux/v1/leads/{id}/followups        → Schedule follow-up
 *   GET    /glamlux/v1/leads/funnel                → CRM funnel summary
 */
class GlamLux_Lead_Controller extends GlamLux_Base_Controller
{

    public function register_routes(): void
    {
        // Funnel
        register_rest_route('glamlux/v1', '/leads/funnel', [
            'methods' => 'GET',
            'callback' => [$this, 'get_funnel'],
            'permission_callback' => [$this, 'require_admin'],
        ]);

        // Collection
        register_rest_route('glamlux/v1', '/leads', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'list_leads'],
                'permission_callback' => [$this, 'require_admin'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'capture_lead'],
                'permission_callback' => '__return_true', // Public — franchise enquiries
            ],
        ]);

        // Single Lead
        register_rest_route('glamlux/v1', '/leads/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_lead'],
            'permission_callback' => [$this, 'require_admin'],
        ]);

        // Status Update
        register_rest_route('glamlux/v1', '/leads/(?P<id>\d+)/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_status'],
            'permission_callback' => [$this, 'require_admin'],
        ]);

        // Assign Lead
        register_rest_route('glamlux/v1', '/leads/(?P<id>\d+)/assign', [
            'methods' => 'PUT',
            'callback' => [$this, 'assign_lead'],
            'permission_callback' => [$this, 'require_admin'],
        ]);

        // Follow-ups
        register_rest_route('glamlux/v1', '/leads/(?P<id>\d+)/followups', [
            'methods' => 'POST',
            'callback' => [$this, 'add_followup'],
            'permission_callback' => [$this, 'require_admin'],
        ]);
    }

    public function get_funnel(WP_REST_Request $request): WP_REST_Response
    {
        $service = new GlamLux_Service_Lead();
        return rest_ensure_response($service->get_funnel_summary());
    }

    public function list_leads(WP_REST_Request $request): WP_REST_Response
    {
        $service = new GlamLux_Service_Lead();
        $filters = [
            'status' => $request->get_param('status'),
            'assigned_to' => $request->get_param('assigned_to'),
            'state' => $request->get_param('state'),
        ];
        return rest_ensure_response($service->get_all(array_filter($filters)));
    }

    public function capture_lead(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $service = new GlamLux_Service_Lead();
        $result = $service->capture_lead($request->get_params());

        if (is_wp_error($result))
            return $result;
        return rest_ensure_response(['success' => true, 'lead_id' => $result, 'message' => 'Thank you! We will contact you shortly.']);
    }

    public function get_lead(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int)$request->get_param('id');
        $service = new GlamLux_Service_Lead();
        $lead = $service->get_by_id($id);

        if (!$lead) {
            return new WP_Error('not_found', 'Lead not found.', ['status' => 404]);
        }
        return rest_ensure_response($lead);
    }

    public function update_status(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int)$request->get_param('id');
        $status = $request->get_param('status');
        $notes = $request->get_param('notes') ?? '';
        $service = new GlamLux_Service_Lead();
        $result = $service->update_status($id, $status, $notes);

        if (is_wp_error($result))
            return $result;
        return rest_ensure_response(['success' => $result]);
    }

    public function assign_lead(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int)$request->get_param('id');
        $user_id = (int)$request->get_param('user_id');
        $service = new GlamLux_Service_Lead();
        $result = $service->assign($id, $user_id);

        if (is_wp_error($result))
            return $result;
        return rest_ensure_response(['success' => true]);
    }

    public function add_followup(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int)$request->get_param('id');
        $type = $request->get_param('type') ?? 'manual';
        $due = $request->get_param('due') ?? '+1 day';
        $service = new GlamLux_Service_Lead();
        $result = $service->schedule_followup($id, $type, $due);

        return rest_ensure_response(['success' => $result]);
    }
}
