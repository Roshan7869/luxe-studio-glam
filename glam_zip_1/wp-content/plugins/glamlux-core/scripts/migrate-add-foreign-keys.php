<?php
/**
 * GlamLux FK Migration Script
 *
 * Adds InnoDB Foreign Key constraints to all GlamLux custom tables.
 * Run this AFTER the initial plugin activation to enforce referential integrity.
 *
 * USAGE (WP-CLI on VPS):
 *   wp eval-file wp-content/plugins/glamlux-core/scripts/migrate-add-foreign-keys.php
 *
 * Phase 14 Deliverable: Data integrity via FK constraints with ON DELETE CASCADE.
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Allow running via WP-CLI eval-file
	define( 'WP_USE_THEMES', false );
	// Find WordPress root intelligently
	$wp_root = dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) );
	if ( file_exists( $wp_root . '/wp-load.php' ) ) {
		require_once $wp_root . '/wp-load.php';
	} else {
		die( "Could not locate wp-load.php. Run this script via WP-CLI: wp eval-file <path>\n" );
	}
}

global $wpdb;
$prefix = $wpdb->prefix;

/**
 * Add a foreign key constraint safely (only if it doesn't already exist).
 *
 * @param string $table        Table to add FK to.
 * @param string $fk_name     Constraint name.
 * @param string $column       Column on this table.
 * @param string $ref_table    Referenced table.
 * @param string $ref_column   Referenced column.
 * @param string $on_delete    ON DELETE action (CASCADE, SET NULL, RESTRICT).
 */
function glamlux_add_fk( $table, $fk_name, $column, $ref_table, $ref_column, $on_delete = 'CASCADE' ) {
	global $wpdb;

	// Check if FK already exists
	$existing = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT CONSTRAINT_NAME
			 FROM information_schema.KEY_COLUMN_USAGE
			 WHERE TABLE_SCHEMA = DATABASE()
			 AND TABLE_NAME = %s
			 AND CONSTRAINT_NAME = %s",
			$table,
			$fk_name
		)
	);

	if ( $existing ) {
		echo "[SKIP]  FK `{$fk_name}` already exists on `{$table}`.\n";
		return;
	}

	$result = $wpdb->query(
		"ALTER TABLE `{$table}`
		 ADD CONSTRAINT `{$fk_name}`
		 FOREIGN KEY (`{$column}`) REFERENCES `{$ref_table}` (`{$ref_column}`)
		 ON DELETE {$on_delete} ON UPDATE CASCADE"
	);

	if ( false === $result ) {
		echo "[ERROR] FK `{$fk_name}` on `{$table}`: " . $wpdb->last_error . "\n";
	} else {
		echo "[OK]    FK `{$fk_name}` added to `{$table}` ({$column} → {$ref_table}.{$ref_column}).\n";
	}
}

echo "GlamLux FK Migration — Starting...\n";
echo str_repeat( '─', 60 ) . "\n";

// Disable FK checks temporarily for migration safety
$wpdb->query( "SET FOREIGN_KEY_CHECKS = 0" );

// ─── wp_gl_salons.franchise_id → wp_gl_franchises.id ────────────────────────
glamlux_add_fk(
	"{$prefix}gl_salons", "fk_salons_franchise",
	"franchise_id", "{$prefix}gl_franchises", "id",
	"CASCADE"
);

// ─── wp_gl_staff.salon_id → wp_gl_salons.id ──────────────────────────────────
glamlux_add_fk(
	"{$prefix}gl_staff", "fk_staff_salon",
	"salon_id", "{$prefix}gl_salons", "id",
	"CASCADE"
);

// ─── wp_gl_clients.wp_user_id → wp_users.ID ──────────────────────────────────
glamlux_add_fk(
	"{$prefix}gl_clients", "fk_clients_wp_user",
	"wp_user_id", "{$prefix}users", "ID",
	"CASCADE"
);

// ─── wp_gl_appointments.salon_id → wp_gl_salons.id ───────────────────────────
glamlux_add_fk(
	"{$prefix}gl_appointments", "fk_appointments_salon",
	"salon_id", "{$prefix}gl_salons", "id",
	"CASCADE"
);

// ─── wp_gl_appointments.client_id → wp_gl_clients.id ────────────────────────
glamlux_add_fk(
	"{$prefix}gl_appointments", "fk_appointments_client",
	"client_id", "{$prefix}gl_clients", "id",
	"SET NULL"
);

// ─── wp_gl_appointments.staff_id → wp_gl_staff.id ───────────────────────────
glamlux_add_fk(
	"{$prefix}gl_appointments", "fk_appointments_staff",
	"staff_id", "{$prefix}gl_staff", "id",
	"SET NULL"
);

// ─── wp_gl_payroll.staff_id → wp_gl_staff.id ─────────────────────────────────
glamlux_add_fk(
	"{$prefix}gl_payroll", "fk_payroll_staff",
	"staff_id", "{$prefix}gl_staff", "id",
	"CASCADE"
);

// ─── wp_gl_payroll.salon_id → wp_gl_salons.id ────────────────────────────────
glamlux_add_fk(
	"{$prefix}gl_payroll", "fk_payroll_salon",
	"salon_id", "{$prefix}gl_salons", "id",
	"CASCADE"
);

// ─── wp_gl_inventory.salon_id → wp_gl_salons.id ──────────────────────────────
glamlux_add_fk(
	"{$prefix}gl_inventory", "fk_inventory_salon",
	"salon_id", "{$prefix}gl_salons", "id",
	"CASCADE"
);

// ─── wp_gl_service_pricing.service_id → wp_gl_service_pricing.id (self-ref) ──
// (franchise-level overrides reference the global service row)
glamlux_add_fk(
	"{$prefix}gl_service_pricing", "fk_service_pricing_parent",
	"service_id", "{$prefix}gl_service_pricing", "id",
	"CASCADE"
);

// ─── wp_gl_financial_reports.franchise_id → wp_gl_franchises.id ──────────────
glamlux_add_fk(
	"{$prefix}gl_financial_reports", "fk_reports_franchise",
	"franchise_id", "{$prefix}gl_franchises", "id",
	"SET NULL"
);

// Re-enable FK checks
$wpdb->query( "SET FOREIGN_KEY_CHECKS = 1" );

echo str_repeat( '─', 60 ) . "\n";
echo "GlamLux FK Migration — Complete.\n";
