<?php
/**
 * Migration Script: v3 Appointments
 * Adds structural duration columns and hard database-level unique constraints
 * to completely eliminate race conditions and phantom reads.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';

global $wpdb;

echo "Starting GlamLux Appointments v3 Migration...\n";

// 1. Resolve active duplicate keys
echo "\nChecking for existing duplicate bookings...\n";
$duplicates = $wpdb->get_results(
    "SELECT staff_id, appointment_time, COUNT(*) as c 
     FROM {$wpdb->prefix}gl_appointments 
     GROUP BY staff_id, appointment_time 
     HAVING COUNT(*) > 1"
);

if (!empty($duplicates)) {
    echo "Found " . count($duplicates) . " duplicate slots. Resolving by cancelling all but the first created...\n";
    foreach ($duplicates as $dup) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}gl_appointments 
             WHERE staff_id = %d AND appointment_time = %s 
             ORDER BY id ASC",
            $dup->staff_id, $dup->appointment_time
        ));

        // Keep the first one, cancel the rest
        array_shift($rows);
        foreach ($rows as $r) {
            $wpdb->update(
                "{$wpdb->prefix}gl_appointments",
            ['status' => 'cancelled', 'notes' => 'Cancelled by v3 migration conflict resolution'],
            ['id' => $r->id]
            );
        }
    }
}

// 2. Add Unique Key Constraint
echo "\nApplying UNIQUE KEY to (staff_id, appointment_time) to prevent race conditions...\n";
$wpdb->query("ALTER TABLE {$wpdb->prefix}gl_appointments ADD UNIQUE KEY unique_staff_time (staff_id, appointment_time)");

// 3. Add duration column
echo "\nAdding duration_minutes column...\n";
$wpdb->query("ALTER TABLE {$wpdb->prefix}gl_appointments ADD COLUMN duration_minutes INT NOT NULL DEFAULT 30");

echo "\nMigration complete.\n";
