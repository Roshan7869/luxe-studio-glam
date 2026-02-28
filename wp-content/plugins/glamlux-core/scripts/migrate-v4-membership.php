<?php
/**
 * Membership Module DB Migration — v2.2.0
 * Creates wp_gl_membership_purchases + ensures gl_memberships has all required columns.
 *
 * Run via: wp eval-file /var/www/html/wp-content/plugins/glamlux-core/scripts/migrate-v4-membership.php
 */
if (!defined('ABSPATH'))
    define('ABSPATH', '/var/www/html/');
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
global $wpdb;
$charset = $wpdb->get_charset_collate();

// ── membership_purchases ──────────────────────────────────────────────────────
$sql = "CREATE TABLE {$wpdb->prefix}gl_membership_purchases (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    client_id bigint(20) NOT NULL,
    membership_id bigint(20) NOT NULL,
    source varchar(50) NOT NULL DEFAULT 'manual',
    wc_order_id bigint(20) DEFAULT 0,
    granted_at datetime NOT NULL,
    expires_at datetime NOT NULL,
    status varchar(50) NOT NULL DEFAULT 'active',
    PRIMARY KEY  (id),
    KEY client_id (client_id),
    KEY membership_id (membership_id),
    KEY granted_at (granted_at)
) {$charset};";
dbDelta($sql);

// ── Ensure gl_memberships has all required columns ────────────────────────────
$cols = $wpdb->get_col("DESCRIBE {$wpdb->prefix}gl_memberships");

if (!in_array('description', $cols, true)) {
    $wpdb->query("ALTER TABLE {$wpdb->prefix}gl_memberships ADD COLUMN description text DEFAULT NULL AFTER name");
}
if (!in_array('discount_percent', $cols, true)) {
    $wpdb->query("ALTER TABLE {$wpdb->prefix}gl_memberships ADD COLUMN discount_percent decimal(5,2) DEFAULT 0.00 AFTER duration_months");
}
if (!in_array('wc_product_id', $cols, true)) {
    $wpdb->query("ALTER TABLE {$wpdb->prefix}gl_memberships ADD COLUMN wc_product_id bigint(20) DEFAULT 0 AFTER discount_percent");
}
if (!in_array('is_active', $cols, true)) {
    $wpdb->query("ALTER TABLE {$wpdb->prefix}gl_memberships ADD COLUMN is_active tinyint(1) DEFAULT 1 AFTER wc_product_id");
}
if (!in_array('duration_months', $cols, true)) {
    $wpdb->query("ALTER TABLE {$wpdb->prefix}gl_memberships ADD COLUMN duration_months int(11) DEFAULT 12 AFTER price");
}

// ── Seed 3 default tiers if none exist ───────────────────────────────────────
$existing = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gl_memberships");
if ((int)$existing === 0) {
    $wpdb->insert($wpdb->prefix . 'gl_memberships', [
        'name' => 'Silver', 'description' => 'Entry-level membership — 5% service discount.',
        'price' => 999.00, 'duration_months' => 12, 'discount_percent' => 5, 'wc_product_id' => 0, 'is_active' => 1,
    ]);
    $wpdb->insert($wpdb->prefix . 'gl_memberships', [
        'name' => 'Gold', 'description' => 'Premium membership — 10% service discount + priority booking.',
        'price' => 2499.00, 'duration_months' => 12, 'discount_percent' => 10, 'wc_product_id' => 0, 'is_active' => 1,
    ]);
    $wpdb->insert($wpdb->prefix . 'gl_memberships', [
        'name' => 'Platinum', 'description' => 'Elite membership — 20% discount + free monthly treatment.',
        'price' => 4999.00, 'duration_months' => 12, 'discount_percent' => 20, 'wc_product_id' => 0, 'is_active' => 1,
    ]);
    echo "Seeded 3 default membership tiers (Silver, Gold, Platinum).\n";
}

update_option('glamlux_db_version', '2.2.0');
echo "Membership Migration v2.2.0 complete!\n";

// List all tables
$tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}gl_%'");
echo 'All GL tables (' . count($tables) . "):\n";
foreach ($tables as $t)
    echo " - {$t}\n";
