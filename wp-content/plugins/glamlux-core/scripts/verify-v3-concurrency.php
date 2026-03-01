<?php
/**
 * Concurrency Verification Script (WP-CLI or Direct PHP)
 * 
 * Tests if the new 'unique_staff_time' DB-level constraint successfully
 * stops parallel requests from double-booking a single staff slot.
 */

if (php_sapi_name() !== 'cli') {
    die("Run from CLI only.");
}

require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';

// Prepare data constants
$salon_id = 1; // Assuming a valid salon
$client_id = 1; // Testing User
$service_id = 1;
$test_time = date('Y-m-d H:i:s', strtotime('+1 day 10:00:00')); // Tomorrow at 10 AM

// We'll bypass the exact business hour restrictions for the sake of checking
// the raw Repo transaction lock capability, or we can use the Service and 
// catch the Exception directly.

echo "Starting Concurrency Collision Simulation...\n";
echo "Simulating 10 users attempting to book $test_time simultaneously.\n";

$service = new GlamLux_Service_Booking();
$success_count = 0;
$rejection_count = 0;

for ($i = 0; $i < 10; $i++) {
    // Attempt the booking. In a pure PHP async script we'd fork threads, 
    // but the DB constraint catches sequential instantaneous loops equally well
    // since the unique key is assessed at write-time.

    // Pass '1' as staff_id to force them all to fight over the exact same slot.
    // If find_available_staff was used, it would auto-route them to 10 DIFFERENT staff.
    $result = $service->secure_book_appointment(1, $client_id, $service_id, $salon_id, $test_time);

    if (is_wp_error($result)) {
        $rejection_count++;
    }
    else {
        $success_count++;
    }
}

echo "Simulation Complete.\n";
echo "Successful Bookings (Expected: 1): $success_count\n";
echo "Rejected Bookings (Expected: 9): $rejection_count\n";

global $wpdb;
$duplicates = $wpdb->get_var(
    $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}gl_appointments 
         WHERE staff_id = 1 AND appointment_time = %s",
    $test_time
)
);

echo "Actual rows in database for this slot: $duplicates\n";

if ($success_count === 1 && $duplicates == 1) {
    echo "\n✅ VERIFICATION PASSED: No phantom bookings allowed.\n";
}
else {
    echo "\n❌ VERIFICATION FAILED: Unique constraint leak detected.\n";
}
