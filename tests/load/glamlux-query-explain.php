<?php
/**
 * GlamLux Query Plan Baseline Check (staging)
 *
 * Run via WP-CLI:
 *   wp eval-file tests/load/glamlux-query-explain.php
 */

if (!defined('ABSPATH')) {
    die('Run via WP-CLI: wp eval-file tests/load/glamlux-query-explain.php');
}

if (!class_exists('WP_CLI')) {
    echo "This script must run under WP-CLI.\n";
    return;
}

global $wpdb;

$checks = [
    [
        'label' => 'Operations: pending/scheduled appointments by salon+time+status',
        'sql' => "EXPLAIN SELECT id FROM {$wpdb->prefix}gl_appointments WHERE salon_id = 1 AND appointment_time >= NOW() AND status IN ('pending','scheduled') ORDER BY appointment_time ASC LIMIT 100",
        'expect_keys' => ['idx_salon_time_status', 'salon_time'],
    ],
    [
        'label' => 'Reporting: completed monthly appointment aggregation',
        'sql' => "EXPLAIN SELECT DATE_FORMAT(appointment_time, '%Y-%m') AS month, COALESCE(SUM(amount),0) AS total_revenue FROM {$wpdb->prefix}gl_appointments WHERE status = 'completed' AND appointment_time >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY month",
        'expect_keys' => ['idx_salon_time_status', 'salon_time'],
    ],
    [
        'label' => 'Booking: slot availability check',
        'sql' => "EXPLAIN SELECT id FROM {$wpdb->prefix}gl_appointments WHERE staff_id = 1 AND appointment_time = '2026-03-01 10:00:00' AND status NOT IN ('cancelled','refunded') LIMIT 1",
        'expect_keys' => ['idx_salon_time_status', 'salon_time'],
    ],
    [
        'label' => 'Membership: clients by membership + expiry',
        'sql' => "EXPLAIN SELECT id FROM {$wpdb->prefix}gl_clients WHERE membership_id IS NOT NULL AND membership_expiry < NOW() ORDER BY membership_expiry ASC LIMIT 200",
        'expect_keys' => ['idx_membership_expiry'],
    ],
    [
        'label' => 'Payroll: staff + period + status',
        'sql' => "EXPLAIN SELECT id, total_pay FROM {$wpdb->prefix}gl_payroll WHERE staff_id = 1 AND period_start >= '2026-01-01' AND period_end <= '2026-01-31' AND status = 'pending' ORDER BY id DESC LIMIT 200",
        'expect_keys' => ['idx_staff_period_status', 'staff_period'],
    ],
    [
        'label' => 'Leads: status + assigned_to + created_at window',
        'sql' => "EXPLAIN SELECT id FROM {$wpdb->prefix}gl_leads WHERE status = 'open' AND assigned_to = 7 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY created_at DESC LIMIT 200",
        'expect_keys' => ['idx_status_assigned_created', 'status', 'assigned_to'],
    ],
];

$pass = 0;
$warn = 0;

WP_CLI::line('');
WP_CLI::line('══════════════════════════════════════════════════════════');
WP_CLI::line('  GlamLux Query Plan Baseline (Staging)');
WP_CLI::line('══════════════════════════════════════════════════════════');

foreach ($checks as $check) {
    $rows = $wpdb->get_results($check['sql'], ARRAY_A);

    WP_CLI::line('');
    WP_CLI::line("▶ {$check['label']}");

    if (empty($rows)) {
        WP_CLI::warning('  No EXPLAIN output (table may not exist in this environment)');
        $warn++;
        continue;
    }

    foreach ($rows as $row) {
        $table = $row['table'] ?? '(unknown)';
        $type = $row['type'] ?? 'unknown';
        $key = $row['key'] ?? 'NULL';
        $rows_scan = $row['rows'] ?? '?';

        $uses_expected_key = in_array($key, $check['expect_keys'], true);
        $full_scan = in_array($type, ['ALL', 'index'], true) && $key === 'NULL';

        if ($full_scan || !$uses_expected_key) {
            WP_CLI::warning("  ⚠ `{$table}` type={$type}, key={$key}, rows={$rows_scan}");
            WP_CLI::warning('    Expected one of: ' . implode(', ', $check['expect_keys']));
            $warn++;
        } else {
            WP_CLI::success("  ✅ `{$table}` type={$type}, key={$key}, rows={$rows_scan}");
            $pass++;
        }
    }
}

WP_CLI::line('');
WP_CLI::line('══════════════════════════════════════════════════════════');
WP_CLI::line("  Result: {$pass} checks passed ✅  |  {$warn} warnings ⚠️");
WP_CLI::line('══════════════════════════════════════════════════════════');
WP_CLI::line('');
