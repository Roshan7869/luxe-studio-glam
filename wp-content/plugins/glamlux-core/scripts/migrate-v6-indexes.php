<?php
/**
 * GlamLux V6 Migration Script
 * Adds composite indexes for high-frequency queries with SHOW INDEX guards.
 */

defined('ABSPATH') || exit;

function glamlux_v6_add_index_with_guard(string $table_name, string $index_name, string $columns): void
{
    global $wpdb;

    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
    if ($table_exists !== $table_name) {
        return;
    }

    $existing = $wpdb->get_var(
        $wpdb->prepare("SHOW INDEX FROM {$table_name} WHERE Key_name = %s", $index_name)
    );

    if (!$existing) {
        $wpdb->query("ALTER TABLE {$table_name} ADD INDEX {$index_name} ({$columns})");
    }
}

function glamlux_run_v6_migration(): void
{
    global $wpdb;

    glamlux_v6_add_index_with_guard(
        $wpdb->prefix . 'gl_appointments',
        'idx_salon_time_status',
        'salon_id, appointment_time, status'
    );

    glamlux_v6_add_index_with_guard(
        $wpdb->prefix . 'gl_clients',
        'idx_membership_expiry',
        'membership_id, membership_expiry'
    );

    glamlux_v6_add_index_with_guard(
        $wpdb->prefix . 'gl_payroll',
        'idx_staff_period_status',
        'staff_id, period_start, period_end, status'
    );

    glamlux_v6_add_index_with_guard(
        $wpdb->prefix . 'gl_leads',
        'idx_status_assigned_created',
        'status, assigned_to, created_at'
    );

    update_option('glamlux_db_version', '6.0.0');
}
