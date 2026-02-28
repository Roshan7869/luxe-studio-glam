/**
 * GlamLux k6 — Query EXPLAIN Validation Script
 * Phase 6: Database Query Validation
 *
 * Run this script via WP-CLI to ensure all critical queries use indexes.
 *
 * USAGE:
 *   wp eval-file tests/load/glamlux-query-explain.php
 *
 * OUTPUT: Table analysis for each critical query showing index usage.
 */

if (!defined('ABSPATH')) {
    die('Run via WP-CLI: wp eval-file glamlux-query-explain.php');
}

global $wpdb;

$queries = [
    'Monthly Revenue (index-safe YEAR/MONTH)' => "EXPLAIN SELECT YEAR(appointment_time) AS year, MONTH(appointment_time) AS month, SUM(amount) FROM {$wpdb->prefix}gl_appointments WHERE status='completed' AND appointment_time >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY YEAR(appointment_time), MONTH(appointment_time)",

    'Booking Availability Check' => "EXPLAIN SELECT id FROM {$wpdb->prefix}gl_appointments WHERE staff_id=1 AND appointment_time='2026-03-01 10:00:00' AND status NOT IN ('cancelled') LIMIT 1",

    'Franchise Revenue (joining salons)' => "EXPLAIN SELECT COALESCE(SUM(a.amount),0) FROM {$wpdb->prefix}gl_appointments a INNER JOIN {$wpdb->prefix}gl_salons s ON a.salon_id=s.id WHERE s.franchise_id=1 AND a.status='completed'",

    'Expired Membership Scan' => "EXPLAIN SELECT id, wp_user_id FROM {$wpdb->prefix}gl_clients WHERE membership_id IS NOT NULL AND membership_expiry IS NOT NULL AND membership_expiry < NOW()",

    'Active Salons List' => "EXPLAIN SELECT id, name, territory_state FROM {$wpdb->prefix}gl_salons WHERE is_active=1 ORDER BY name ASC",

    'Attendance Monthly (YEAR/MONTH)' => "EXPLAIN SELECT * FROM {$wpdb->prefix}gl_attendance WHERE staff_id=1 AND YEAR(shift_date)=2026 AND MONTH(shift_date)=3",

    'KPI Total Revenue Count' => "EXPLAIN SELECT COUNT(id) FROM {$wpdb->prefix}gl_appointments WHERE status='completed'",

    'Webhook Idempotency Check' => "EXPLAIN SELECT id FROM {$wpdb->prefix}gl_webhook_events WHERE gateway='razorpay' AND transaction_id='pay_test123'",
];

$pass = 0;
$warn = 0;

WP_CLI::line('');
WP_CLI::line('══════════════════════════════════════════════════════════');
WP_CLI::line('  GlamLux2Lux Query EXPLAIN Analysis');
WP_CLI::line('══════════════════════════════════════════════════════════');

foreach ($queries as $label => $sql) {
    $rows = $wpdb->get_results($sql, ARRAY_A);

    WP_CLI::line('');
    WP_CLI::line("▶ {$label}");

    if (empty($rows)) {
        WP_CLI::warning("  No EXPLAIN output (table may not exist)");
        $warn++;
        continue;
    }

    foreach ($rows as $row) {
        $type  = $row['type']     ?? 'unknown';
        $key   = $row['key']      ?? 'NULL';
        $extra = $row['Extra']    ?? '';
        $rows_scan = $row['rows'] ?? '?';
        $table = $row['table']    ?? '';

        $is_bad = in_array($type, ['ALL', 'index'], true) && $key === 'NULL';

        if ($is_bad) {
            WP_CLI::warning("  ❌ FULL SCAN on `{$table}` — type={$type}, key=NULL, rows={$rows_scan}");
            WP_CLI::warning("     Extra: {$extra}");
            $warn++;
        } else {
            WP_CLI::success("  ✅ `{$table}` — type={$type}, key={$key}, rows={$rows_scan}");
            $pass++;
        }
    }
}

WP_CLI::line('');
WP_CLI::line('══════════════════════════════════════════════════════════');
WP_CLI::line("  Result: {$pass} queries indexed ✅  |  {$warn} warnings ⚠️");
WP_CLI::line('══════════════════════════════════════════════════════════');
WP_CLI::line('');
