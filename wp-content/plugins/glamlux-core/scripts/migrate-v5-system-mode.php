<?php
/**
 * GlamLux Database Migration V5: System Mode & Governance
 *
 * Run this script to generate the tracking tables required for Enterprise 
 * infrastructure safety.
 */

// Load WordPress context if called directly
if (!defined('ABSPATH')) {
    $wp_load = dirname(__FILE__, 4) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    }
    else {
        die("Cannot load WordPress core.");
    }
}

global $wpdb;

echo "🚀 Starting GlamLux V5 Database Migration (Governance)...\n\n";

$charset_collate = $wpdb->get_charset_collate();
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// ── 1. Create System Mode Audit Log Table ─────────────────────────────────
$table_audit = $wpdb->prefix . 'gl_mode_audit';
$sql_audit = "CREATE TABLE $table_audit (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	user_id bigint(20) unsigned NOT NULL,
	previous_mode varchar(50) NOT NULL,
	new_mode varchar(50) NOT NULL,
	ip_address varchar(45) NOT NULL,
	created_at datetime NOT NULL,
	PRIMARY KEY (id),
	KEY user_id (user_id)
) $charset_collate;";

dbDelta($sql_audit);
echo "✅ Created/Verified table: {$table_audit}\n";

// ── 2. Create Webhook Idempotency Tracking Table ──────────────────────────
$table_webhooks = $wpdb->prefix . 'gl_webhook_events';
$sql_webhooks = "CREATE TABLE $table_webhooks (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	gateway varchar(50) NOT NULL,
	transaction_id varchar(100) NOT NULL,
	event_type varchar(50) NOT NULL,
	payload longtext NOT NULL,
	status varchar(20) NOT NULL DEFAULT 'processing',
	processed_at datetime DEFAULT NULL,
	created_at datetime NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY gateway_transaction (gateway, transaction_id),
	KEY status (status)
) $charset_collate;";

dbDelta($sql_webhooks);
echo "✅ Created/Verified table: {$table_webhooks}\n";

echo "\n🎉 GlamLux V5 Migration Complete! Run 'wp cache flush' to reset schema cache optimally.\n";
