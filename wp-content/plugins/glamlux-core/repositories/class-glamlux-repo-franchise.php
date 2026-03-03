<?php
/**
 * GlamLux Franchise Repository
 *
 * LAYER: Repository (Data Access Layer)
 * RULE:  The ONLY place where SQL is allowed for franchise/salon/service operations.
 *        Controllers and Services must NEVER call $wpdb directly.
 */
class GlamLux_Repo_Franchise
{

    // ─────────────────────────────────────────────────────────────────
    // SALON Operations
    // ─────────────────────────────────────────────────────────────────

    public function get_active_salons(): array
    {
        global $wpdb;
        $cache_key = 'gl_active_salons_blog_' . get_current_blog_id();
        $cached = get_transient($cache_key);
        if (false !== $cached)
            return $cached;

        $result = $wpdb->get_results(
            "SELECT id, name, address, city, state, phone, email, is_active, interior_image_url, franchise_id
			 FROM {$wpdb->prefix}gl_salons
			 WHERE is_active = 1
			 ORDER BY name ASC",
            ARRAY_A
        ) ?: [];

        set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
        return $result;
    }

    public function get_all_salons(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT s.*, f.name AS franchise_name
			 FROM {$wpdb->prefix}gl_salons s
			 LEFT JOIN {$wpdb->prefix}gl_franchises f ON s.franchise_id = f.id
			 ORDER BY s.name ASC",
            ARRAY_A
        ) ?: [];
    }

    public function get_salon_by_id(int $id): ?array
    {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gl_salons WHERE id = %d LIMIT 1",
            $id
        ),
            ARRAY_A
        ) ?: null;
    }

    public function insert_salon(array $data): int
    {
        global $wpdb;
        delete_transient('gl_active_salons_blog_' . get_current_blog_id());
        $wpdb->insert($wpdb->prefix . 'gl_salons', array_merge($data, [
            'created_at' => current_time('mysql'),
        ]));
        return (int)$wpdb->insert_id;
    }

    public function update_salon(int $id, array $data): bool
    {
        global $wpdb;
        delete_transient('gl_active_salons_blog_' . get_current_blog_id());
        return false !== $wpdb->update($wpdb->prefix . 'gl_salons', $data, ['id' => $id]);
    }

    // ─────────────────────────────────────────────────────────────────
    // SERVICE PRICING Operations
    // ─────────────────────────────────────────────────────────────────

    public function get_active_services(): array
    {
        global $wpdb;
        $cache_key = 'gl_active_services_blog_' . get_current_blog_id();
        $cached = get_transient($cache_key);
        if (false !== $cached)
            return $cached;

        $result = $wpdb->get_results(
            "SELECT service_id AS id, service_name AS name, base_price AS price, category, duration_minutes, is_active, image_url, description
			 FROM {$wpdb->prefix}gl_service_pricing
			 WHERE is_active = 1
			 ORDER BY category ASC, service_name ASC",
            ARRAY_A
        ) ?: [];

        set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
        return $result;
    }

    public function get_services_by_category(string $category): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
            "SELECT service_id AS id, service_name AS name, base_price AS price, duration_minutes
				 FROM {$wpdb->prefix}gl_service_pricing
				 WHERE category = %s AND is_active = 1",
            $category
        ),
            ARRAY_A
        ) ?: [];
    }

    // ─────────────────────────────────────────────────────────────────
    // FRANCHISE Operations
    // ─────────────────────────────────────────────────────────────────

    public function get_all_franchises(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT f.*, u.display_name AS admin_name, u.user_email AS admin_email
			 FROM {$wpdb->prefix}gl_franchises f
			 LEFT JOIN {$wpdb->users} u ON f.admin_id = u.ID
			 ORDER BY f.name ASC",
            ARRAY_A
        ) ?: [];
    }

    public function get_franchise_by_id(int $id): ?array
    {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
            "SELECT f.*, u.display_name AS admin_name, u.user_email AS admin_email
				 FROM {$wpdb->prefix}gl_franchises f
				 LEFT JOIN {$wpdb->users} u ON f.admin_id = u.ID
				 WHERE f.id = %d LIMIT 1",
            $id
        ),
            ARRAY_A
        ) ?: null;
    }

    public function get_franchise_by_admin(int $user_id): ?array
    {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gl_franchises WHERE admin_id = %d LIMIT 1",
            $user_id
        ),
            ARRAY_A
        ) ?: null;
    }

    public function insert_franchise(array $data): int
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'gl_franchises', array_merge($data, [
            'created_at' => current_time('mysql'),
        ]));
        return (int)$wpdb->insert_id;
    }

    public function update_franchise(int $id, array $data): bool
    {
        global $wpdb;
        return false !== $wpdb->update($wpdb->prefix . 'gl_franchises', $data, ['id' => $id]);
    }

    // ─────────────────────────────────────────────────────────────────
    // REPORTING Helpers
    // ─────────────────────────────────────────────────────────────────

    public function get_revenue_per_salon(string $from, string $to): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
            "SELECT a.salon_id, sl.name AS salon_name,
						COUNT(*) AS bookings,
						COALESCE(SUM(a.amount), 0) AS revenue
				 FROM {$wpdb->prefix}gl_appointments a
				 LEFT JOIN {$wpdb->prefix}gl_salons sl ON a.salon_id = sl.id
				 WHERE a.status = 'completed'
				   AND a.appointment_time BETWEEN %s AND %s
				 GROUP BY a.salon_id ORDER BY revenue DESC",
            $from, $to
        ),
            ARRAY_A
        ) ?: [];
    }
}
