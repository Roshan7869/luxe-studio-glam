<?php
/**
 * GlamLux Reports REST Controller
 *
 * LAYER: Presentation (REST API) Layer
 * RULE:  Zero SQL, zero business logic. Delegates fully to GlamLux_Service_Revenue and GlamLux_Service_Payroll.
 *
 * Endpoints:
 *   GET /glamlux/v1/reports/revenue         → Revenue summary with filters
 *   GET /glamlux/v1/reports/revenue/salons  → Per-salon revenue breakdown
 *   GET /glamlux/v1/reports/revenue/trend   → Monthly revenue trend chart
 *   GET /glamlux/v1/reports/kpis            → Dashboard KPI metrics card
 */
class GlamLux_Reports_Controller extends GlamLux_Base_Controller
{

    public function register_routes(): void
    {
        register_rest_route('glamlux/v1', '/reports/revenue', [
            'methods' => 'GET',
            'callback' => [$this, 'get_revenue_summary'],
            'permission_callback' => [$this, 'require_staff_or_admin'],
        ]);

        register_rest_route('glamlux/v1', '/reports/revenue/salons', [
            'methods' => 'GET',
            'callback' => [$this, 'get_revenue_by_salon'],
            'permission_callback' => [$this, 'require_admin'],
        ]);

        register_rest_route('glamlux/v1', '/reports/revenue/trend', [
            'methods' => 'GET',
            'callback' => [$this, 'get_monthly_trend'],
            'permission_callback' => [$this, 'require_admin'],
        ]);

        register_rest_route('glamlux/v1', '/reports/kpis', [
            'methods' => 'GET',
            'callback' => [$this, 'get_kpis'],
            'permission_callback' => [$this, 'require_staff_or_admin'],
        ]);
    }

    public function get_revenue_summary(WP_REST_Request $request): WP_REST_Response
    {
        $from = $request->get_param('from') ?: date('Y-m-01');
        $to = $request->get_param('to') ?: date('Y-m-t');
        $service = new GlamLux_Service_Revenue();
        return rest_ensure_response($service->get_revenue_summary($from, $to));
    }

    public function get_revenue_by_salon(WP_REST_Request $request): WP_REST_Response
    {
        $from = $request->get_param('from') ?: date('Y-m-01');
        $to = $request->get_param('to') ?: date('Y-m-t');
        $service = new GlamLux_Service_Revenue();
        return rest_ensure_response($service->get_revenue_by_salon($from, $to));
    }

    public function get_monthly_trend(WP_REST_Request $request): WP_REST_Response
    {
        $months = (int)($request->get_param('months') ?: 12);
        $service = new GlamLux_Service_Revenue();
        return rest_ensure_response($service->get_monthly_trend($months));
    }

    public function get_kpis(WP_REST_Request $request): WP_REST_Response
    {
        $service = new GlamLux_Service_Revenue();
        return rest_ensure_response($service->get_metrics());
    }
}
