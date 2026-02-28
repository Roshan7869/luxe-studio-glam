<?php

/**
 * Enterprise operations data access layer.
 */
class GlamLux_Repo_Operations
{
    public function get_missing_tables(array $required_tables): array
    {
        global $wpdb;
        $missing = array();
        foreach ($required_tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . $table));
            if ($exists !== $wpdb->prefix . $table) {
                $missing[] = $table;
            }
        }
        return $missing;
    }

    public function get_appointments_today(): int
    {
        global $wpdb;
        return (int)$wpdb->get_var(
            "SELECT COUNT(id) FROM {$wpdb->prefix}gl_appointments WHERE DATE(appointment_time) = CURDATE()"
        );
    }

    public function get_pending_appointments(): int
    {
        global $wpdb;
        return (int)$wpdb->get_var(
            "SELECT COUNT(id) FROM {$wpdb->prefix}gl_appointments WHERE status IN ('pending', 'scheduled')"
        );
    }

    public function get_active_memberships(): int
    {
        global $wpdb;
        return (int)$wpdb->get_var(
            "SELECT COUNT(id) FROM {$wpdb->prefix}gl_clients WHERE membership_id IS NOT NULL AND membership_expiry IS NOT NULL AND membership_expiry > NOW()"
        );
    }

    public function get_active_staff(): int
    {
        global $wpdb;
        return (int)$wpdb->get_var(
            "SELECT COUNT(id) FROM {$wpdb->prefix}gl_staff WHERE is_active=1"
        );
    }

    public function get_open_leads(): int
    {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'gl_leads'));
        if ($exists === $wpdb->prefix . 'gl_leads') {
            return (int)$wpdb->get_var(
                "SELECT COUNT(id) FROM {$wpdb->prefix}gl_leads WHERE status IN ('new','open','follow_up')"
            );
        }
        return 0;
    }

    public function get_service_errors_24h(): int
    {
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'gl_service_logs')) === $wpdb->prefix . 'gl_service_logs') {
            $has_logged_at = (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}gl_service_logs LIKE %s", 'logged_at'));
            $has_notes = (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}gl_service_logs LIKE %s", 'notes'));
            if ($has_logged_at && $has_notes) {
                return (int)$wpdb->get_var(
                    "SELECT COUNT(id) FROM {$wpdb->prefix}gl_service_logs WHERE logged_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND (LOWER(notes) LIKE '%error%' OR LOWER(notes) LIKE '%failed%')"
                );
            }
        }
        return 0;
    }
}
