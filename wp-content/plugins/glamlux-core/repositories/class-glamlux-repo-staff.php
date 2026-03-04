<?php
/**
 * GlamLux Staff Repository
 *
 * LAYER: Repository (Data Access Layer)
 * RULE:  The ONLY place where SQL is allowed for staff operations.
 */
class GlamLux_Repo_Staff
{
    // ─────────────────────────────────────────────────────────────────
    // READ Operations
    // ─────────────────────────────────────────────────────────────────

    public function get_all(array $filters = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        // PHASE 3: Support franchise_id filtering for tenant isolation
        if (!empty($filters['franchise_id'])) {
            $where[] = 'sl.franchise_id = %d';
            $params[] = (int)$filters['franchise_id'];
        }
        if (!empty($filters['salon_id'])) {
            $where[] = 'st.salon_id = %d';
            $params[] = (int)$filters['salon_id'];
        }
        if (isset($filters['status']) && $filters['status'] === 'active') {
            $where[] = 'st.is_active = 1';
        }
        elseif (isset($filters['status']) && $filters['status'] === 'inactive') {
            $where[] = 'st.is_active = 0';
        }

        $where_sql = implode(' AND ', $where);

        $sql = "SELECT st.id, st.salon_id, st.wp_user_id, st.job_role, st.specializations,
                       st.commission_rate, st.profile_image_url, st.is_active, st.created_at,
                       u.display_name AS name, u.user_email AS email,
                       sl.name AS salon_name
                  FROM {$wpdb->prefix}gl_staff st
                  LEFT JOIN {$wpdb->users} u  ON st.wp_user_id = u.ID
                  LEFT JOIN {$wpdb->prefix}gl_salons sl ON st.salon_id = sl.id
                 WHERE {$where_sql}
                 ORDER BY sl.name ASC, u.display_name ASC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
        }
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function get_by_id(int $id): ?array
    {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
            "SELECT st.id, st.salon_id, st.wp_user_id, st.job_role, st.specializations,
                        st.commission_rate, st.profile_image_url, st.is_active, st.created_at,
                        u.display_name AS name, u.user_email AS email,
                        sl.name AS salon_name
                   FROM {$wpdb->prefix}gl_staff st
                   LEFT JOIN {$wpdb->users} u  ON st.wp_user_id = u.ID
                   LEFT JOIN {$wpdb->prefix}gl_salons sl ON st.salon_id = sl.id
                  WHERE st.id = %d LIMIT 1",
            $id
        ),
            ARRAY_A
        ) ?: null;
    }

    /**
     * Phase 3 (Load Balancer): Fetch staff sorted by how many active appointments they have today, ascending.
     */
    public function get_staff_ordered_by_current_load(int $salon_id, string $date): array
    {
        global $wpdb;
        $sql = "SELECT s.id, COUNT(a.id) AS total_today
                FROM {$wpdb->prefix}gl_staff s
                LEFT JOIN {$wpdb->prefix}gl_appointments a 
                  ON s.id = a.staff_id 
                  AND DATE(a.appointment_time) = %s 
                  AND a.status NOT IN ('cancelled', 'refunded')
                WHERE s.salon_id = %d AND s.is_active = 1
                GROUP BY s.id
                ORDER BY total_today ASC";

        return $wpdb->get_results($wpdb->prepare($sql, $date, $salon_id), ARRAY_A) ?: [];
    }

    public function get_by_user_id(int $wp_user_id): ?array
    {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
            "SELECT st.*, u.display_name AS name, u.user_email AS email
                   FROM {$wpdb->prefix}gl_staff st
                   LEFT JOIN {$wpdb->users} u ON st.wp_user_id = u.ID
                  WHERE st.wp_user_id = %d LIMIT 1",
            $wp_user_id
        ),
            ARRAY_A
        ) ?: null;
    }

    /**
     * Performance: count bookings and revenue attributed to a staff member
     * in a given date range, joined via appointments.
     */
    public function get_performance_stats(int $staff_id, string $from, string $to): array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
            "SELECT COUNT(a.id)              AS total_appointments,
                        COUNT(CASE WHEN a.status = 'completed' THEN 1 END) AS completed_appointments,
                        COALESCE(SUM(CASE WHEN a.status = 'completed' THEN a.amount END), 0) AS total_revenue,
                        COALESCE(AVG(CASE WHEN a.status = 'completed' THEN a.amount END), 0) AS avg_ticket_value
                   FROM {$wpdb->prefix}gl_appointments a
                  WHERE a.staff_id = %d
                    AND DATE(a.appointment_time) BETWEEN %s AND %s",
            $staff_id, $from, $to
        ),
            ARRAY_A
        );
        return $row ?: [
            'total_appointments' => 0,
            'completed_appointments' => 0,
            'total_revenue' => 0,
            'avg_ticket_value' => 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // WRITE Operations
    // ─────────────────────────────────────────────────────────────────

    public function insert(array $data): int
    {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'gl_staff',
            array_merge($data, ['created_at' => current_time('mysql')])
        );
        return (int)$wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;
        return false !== $wpdb->update(
            $wpdb->prefix . 'gl_staff',
            $data,
        ['id' => $id]
        );
    }

    public function deactivate(int $id): bool
    {
        global $wpdb;
        return false !== $wpdb->update(
            $wpdb->prefix . 'gl_staff',
        ['is_active' => 0],
        ['id' => $id]
        );
    }
}
