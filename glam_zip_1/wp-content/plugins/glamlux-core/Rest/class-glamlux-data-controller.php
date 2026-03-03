<?php
/**
 * GlamLux Data REST Controller
 *
 * Exposes the visual enterprise dataset endpoints:
 *   GET /glamlux/v1/memberships         → Membership plans with banner images
 *   GET /glamlux/v1/leads               → CRM leads with avatar images (admin-only)
 *   GET /glamlux/v1/service-logs        → Before/After service log images
 *   GET /glamlux/v1/product-sales       → Product sales with product images
 *   GET /glamlux/v1/inventory           → Inventory levels (admin-only)
 *   GET /glamlux/v1/financial-reports   → Franchise financial reports (admin-only)
 *   GET /glamlux/v1/franchises          → All franchises
 *   GET /glamlux/v1/staff/profiles      → Staff with profile images (public gallery)
 */
class GlamLux_Data_Controller extends GlamLux_Base_Controller
{
    public function register_routes(): void
    {
        // ── Public read-only gallery endpoints ──────────────────────────────

        register_rest_route('glamlux/v1', '/memberships', [
            'methods' => 'GET',
            'callback' => [$this, 'get_memberships'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('glamlux/v1', '/service-logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_service_logs'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('glamlux/v1', '/franchises', [
            'methods' => 'GET',
            'callback' => [$this, 'get_franchises'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('glamlux/v1', '/staff/profiles', [
            'methods' => 'GET',
            'callback' => [$this, 'get_staff_profiles'],
            'permission_callback' => '__return_true',
        ]);

        // ── Admin-only endpoints ─────────────────────────────────────────────

        register_rest_route('glamlux/v1', '/leads', [
            'methods' => 'GET',
            'callback' => [$this, 'get_leads'],
            'permission_callback' => [$this, 'require_admin'],
        ]);

        register_rest_route('glamlux/v1', '/product-sales', [
            'methods' => 'GET',
            'callback' => [$this, 'get_product_sales'],
            'permission_callback' => [$this, 'require_admin'],
        ]);

        register_rest_route('glamlux/v1', '/inventory', [
            'methods' => 'GET',
            'callback' => [$this, 'get_inventory'],
            'permission_callback' => [$this, 'require_admin'],
        ]);

        register_rest_route('glamlux/v1', '/financial-reports', [
            'methods' => 'GET',
            'callback' => [$this, 'get_financial_reports'],
            'permission_callback' => [$this, 'require_admin'],
        ]);
    }

    // ── Handlers ─────────────────────────────────────────────────────────────

    public function get_memberships(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $cache_key = 'gl_api_memberships_blog_' . get_current_blog_id();
        $cached = get_transient($cache_key);
        if (false !== $cached)
            return rest_ensure_response($cached);

        $rows = $wpdb->get_results(
            "SELECT id, name, tier_level, price, duration_months, benefits, banner_image_url, is_active
               FROM {$wpdb->prefix}gl_memberships
              WHERE is_active = 1
              ORDER BY tier_level ASC, price ASC",
            ARRAY_A
        ) ?: [];

        set_transient($cache_key, $rows, 15 * MINUTE_IN_SECONDS);
        return rest_ensure_response($rows);
    }

    public function get_service_logs(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $limit = min(abs((int)($request->get_param('limit') ?: 20)), 100);
        $salon = (int)($request->get_param('salon_id') ?: 0);
        $cache_key = 'gl_api_service_logs_' . $salon . '_' . $limit . '_blog_' . get_current_blog_id();
        $cached = get_transient($cache_key);
        if (false !== $cached)
            return rest_ensure_response($cached);

        $where = $salon ? $wpdb->prepare(
            " AND a.salon_id = %d", $salon
        ) : '';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT sl.id, sl.appointment_id, sl.before_image_url, sl.after_image_url,
                    sl.notes, sl.logged_at,
                    a.service_name, a.amount, a.status AS appointment_status,
                    s.name AS salon_name
               FROM {$wpdb->prefix}gl_service_logs sl
               LEFT JOIN {$wpdb->prefix}gl_appointments a ON sl.appointment_id = a.id
               LEFT JOIN {$wpdb->prefix}gl_salons s ON a.salon_id = s.id
              WHERE 1=1 {$where}
              ORDER BY sl.logged_at DESC
              LIMIT %d",
            $limit
        ), ARRAY_A) ?: [];

        set_transient($cache_key, $rows, 15 * MINUTE_IN_SECONDS);
        return rest_ensure_response($rows);
    }

    public function get_franchises(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $cache_key = 'gl_api_franchises_blog_' . get_current_blog_id();
        $cached = get_transient($cache_key);
        if (false !== $cached)
            return rest_ensure_response($cached);

        $rows = $wpdb->get_results(
            "SELECT id, name, location, territory_state, created_at
               FROM {$wpdb->prefix}gl_franchises
              ORDER BY name ASC",
            ARRAY_A
        ) ?: [];

        set_transient($cache_key, $rows, 15 * MINUTE_IN_SECONDS);
        return rest_ensure_response($rows);
    }

    public function get_staff_profiles(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $salon = (int)($request->get_param('salon_id') ?: 0);
        $cache_key = 'gl_api_staff_profiles_' . $salon . '_blog_' . get_current_blog_id();
        $cached = get_transient($cache_key);
        if (false !== $cached)
            return rest_ensure_response($cached);

        $where = $salon ? $wpdb->prepare(" AND st.salon_id = %d", $salon) : '';

        $rows = $wpdb->get_results(
            "SELECT st.id, st.salon_id, st.job_role AS role, st.specializations,
                    st.profile_image_url, st.commission_rate, st.is_active,
                    u.display_name AS name,
                    sl.name AS salon_name
               FROM {$wpdb->prefix}gl_staff st
               LEFT JOIN {$wpdb->users} u ON st.wp_user_id = u.ID
               LEFT JOIN {$wpdb->prefix}gl_salons sl ON st.salon_id = sl.id
              WHERE st.is_active = 1 {$where}
              ORDER BY sl.name ASC, u.display_name ASC",
            ARRAY_A
        ) ?: [];

        set_transient($cache_key, $rows, 15 * MINUTE_IN_SECONDS);
        return rest_ensure_response($rows);
    }

    public function get_leads(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $status = sanitize_text_field($request->get_param('status') ?: '');
        $limit = min(abs((int)($request->get_param('limit') ?: 50)), 200);
        $where = $status ? $wpdb->prepare(" AND status = %s", $status) : '';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, email, phone, source, avatar_url, status, interest_type, created_at
               FROM {$wpdb->prefix}gl_leads
              WHERE 1=1 {$where}
              ORDER BY created_at DESC
              LIMIT %d",
            $limit
        ), ARRAY_A) ?: [];

        return rest_ensure_response($rows);
    }

    public function get_product_sales(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $salon = (int)($request->get_param('salon_id') ?: 0);
        $limit = min(abs((int)($request->get_param('limit') ?: 50)), 200);
        $where = $salon ? $wpdb->prepare(" AND ps.salon_id = %d", $salon) : '';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ps.id, ps.salon_id, ps.product_name, ps.product_image_url,
                    ps.total_amount, ps.sale_date,
                    sl.name AS salon_name
               FROM {$wpdb->prefix}gl_product_sales ps
               LEFT JOIN {$wpdb->prefix}gl_salons sl ON ps.salon_id = sl.id
              WHERE 1=1 {$where}
              ORDER BY ps.sale_date DESC
              LIMIT %d",
            $limit
        ), ARRAY_A) ?: [];

        return rest_ensure_response($rows);
    }

    public function get_inventory(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $salon = (int)($request->get_param('salon_id') ?: 0);
        $where = $salon ? $wpdb->prepare(" AND inv.salon_id = %d", $salon) : '';

        $rows = $wpdb->get_results(
            "SELECT inv.id, inv.salon_id, inv.product_name, inv.category,
                    inv.quantity, inv.reorder_threshold, inv.unit_cost, inv.last_restocked,
                    sl.name AS salon_name,
                    IF(inv.quantity <= inv.reorder_threshold, 1, 0) AS low_stock_alert
               FROM {$wpdb->prefix}gl_inventory inv
               LEFT JOIN {$wpdb->prefix}gl_salons sl ON inv.salon_id = sl.id
              WHERE 1=1 {$where}
              ORDER BY sl.name ASC, inv.product_name ASC",
            ARRAY_A
        ) ?: [];

        return rest_ensure_response($rows);
    }

    public function get_financial_reports(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $franchise = (int)($request->get_param('franchise_id') ?: 0);
        $month = sanitize_text_field($request->get_param('month') ?: '');
        $limit = min(abs((int)($request->get_param('limit') ?: 12)), 100);
        $wheres = [];
        if ($franchise)
            $wheres[] = $wpdb->prepare("fr.franchise_id = %d", $franchise);
        if ($month)
            $wheres[] = $wpdb->prepare("fr.report_month = %s", $month);
        $where_sql = $wheres ? ' AND ' . implode(' AND ', $wheres) : '';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT fr.id, fr.franchise_id, fr.report_month, fr.total_revenue,
                    fr.total_expenses, fr.net_profit, fr.report_chart_image_url, fr.generated_at,
                    f.name AS franchise_name
               FROM {$wpdb->prefix}gl_financial_reports fr
               LEFT JOIN {$wpdb->prefix}gl_franchises f ON fr.franchise_id = f.id
              WHERE 1=1 {$where_sql}
              ORDER BY fr.report_month DESC
              LIMIT %d",
            $limit
        ), ARRAY_A) ?: [];

        return rest_ensure_response($rows);
    }
}
