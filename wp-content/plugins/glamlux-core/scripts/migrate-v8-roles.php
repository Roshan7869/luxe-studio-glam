<?php
/**
 * Migration v8: Register Franchise Hierarchy Roles
 *
 * Adds three new roles introduced in Phase 3.2:
 *  - glamlux_chairperson
 *  - glamlux_franchise_manager
 *  - glamlux_franchise_employee
 *
 * Safe to run multiple times (idempotent).
 *
 * Usage (WP-CLI): wp eval-file scripts/migrate-v8-roles.php
 */

if (!defined('ABSPATH')) {
    // Bootstrapped outside WordPress — try to load it
    $wp_load = dirname(__DIR__, 4) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die("WordPress not found. Run this via WP-CLI: wp eval-file scripts/migrate-v8-roles.php\n");
    }
}

require_once __DIR__ . '/../Core/class-activator.php';

echo "Running Migration v8: Franchise Hierarchy Roles\n";

// Register the new roles by delegating to the activator.
// add_custom_roles is private; call the public update path instead.
$roles_to_ensure = [
    'glamlux_chairperson' => [
        'display_name' => 'Chairperson',
        'caps' => [
            'read' => true,
            'manage_glamlux_franchise' => true,
            'view_franchise_reports' => true,
            'manage_glamlux_franchise_managers' => true,
            'manage_glamlux_franchise_employees' => true,
        ],
    ],
    'glamlux_franchise_manager' => [
        'display_name' => 'Franchise Manager',
        'caps' => [
            'read' => true,
            'manage_glamlux_franchise_employees' => true,
            'manage_glamlux_appointments' => true,
            'view_franchise_reports' => true,
            'manage_glamlux_inventory' => true,
            'glamlux_check_attendance' => true,
        ],
    ],
    'glamlux_franchise_employee' => [
        'display_name' => 'Franchise Employee',
        'caps' => [
            'read' => true,
            'manage_glamlux_appointments' => true,
            'glamlux_check_attendance' => true,
        ],
    ],
];

foreach ($roles_to_ensure as $slug => $definition) {
    if (get_role($slug)) {
        echo "  Role '{$slug}' already exists — skipping creation.\n";
    } else {
        add_role($slug, __($definition['display_name'], 'glamlux-core'), $definition['caps']);
        echo "  Role '{$slug}' created.\n";
    }
}

// Sync all capabilities (runs the full update_role_capabilities logic).
GlamLux_Activator::update_role_capabilities();
echo "  Capabilities synced for all roles.\n";

echo "Migration v8 complete.\n";
