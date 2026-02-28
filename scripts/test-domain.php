<?php
// WP-CLI eval-file automatically bootstraps WordPress.

echo "--- DOMAIN-DRIVEN ARCHITECTURE TEST ---\n";

$repo = new GlamLux_Repo_Appointment();
$service = new GlamLux_Service_Booking($repo);

global $wpdb;

// 1. Create a dummy client
$wpdb->insert($wpdb->prefix . 'gl_clients', ['wp_user_id' => 1]);
$client_id = $wpdb->insert_id;

// 2. Create a dummy staff
$wpdb->insert($wpdb->prefix . 'gl_staff', ['wp_user_id' => 1, 'salon_id' => 1, 'commission_rate' => 50]);
$staff_id = $wpdb->insert_id;

// 3. Create the appointment via the Booking Service (Domain)
$appt_id = $service->secure_book_appointment($staff_id, $client_id, 1, 1, '2026-03-01 10:00:00', 'Domain Test');
echo 'Created Appt ID: ' . (is_wp_error($appt_id) ? $appt_id->get_error_message() : $appt_id) . "\n";

// 4. Mark as completed (should fire the event dispatcher → commission service)
$completed = $service->mark_completed($appt_id);
echo 'Completed: ' . ($completed ? 'Yes' : 'No') . "\n";

// 5. Verify the payroll ledger was updated asynchronously by the Event!
$payroll = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}gl_payroll WHERE appointment_id = {$appt_id}", ARRAY_A);

if ($payroll) {
    echo "✅ PAYROLL GENERATED AUTONOMOUSLY VIA EVENTS!\n";
    print_r($payroll);
}
else {
    echo "❌ PAYROLL FAILED TO GENERATE.\n";
}
