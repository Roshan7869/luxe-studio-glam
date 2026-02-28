<?php
echo "--- DOMAIN-DRIVEN ARCHITECTURE TEST ---\n";

$repo = new GlamLux_Repo_Appointment();
$service = new GlamLux_Service_Booking($repo);

global $wpdb;

$wpdb->insert($wpdb->prefix . 'gl_clients', ['wp_user_id' => 1]);
$client_id = $wpdb->insert_id;

$wpdb->insert($wpdb->prefix . 'gl_staff', ['wp_user_id' => 1, 'salon_id' => 1, 'commission_rate' => 50]);
$staff_id = $wpdb->insert_id;

$appt_id = $service->secure_book_appointment($staff_id, $client_id, 1, 1, '2026-03-01 10:00:00', 'Domain Test');
echo 'Created Appt ID: ' . (is_wp_error($appt_id) ? $appt_id->get_error_message() : $appt_id) . "\n";

$completed = $service->mark_completed($appt_id);
echo 'Completed: ' . ($completed ? 'Yes' : 'No') . "\n";

$payroll = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}gl_payroll WHERE appointment_id = {$appt_id}", ARRAY_A);

if ($payroll) {
    echo "✅ PAYROLL GENERATED AUTONOMOUSLY VIA EVENTS!\n";
    print_r($payroll);
}
else {
    echo "❌ PAYROLL FAILED TO GENERATE.\n";
}
