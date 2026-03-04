<?php
/**
 * Database Migration: Token Management Tables
 * 
 * PHASE 0: JWT Token Lifecycle Management
 * 
 * Creates tables for:
 * - Token blacklist (revoked tokens)
 * - Refresh tokens (token rotation)
 * - Token sessions (user activity tracking)
 * 
 * Usage: wp eval-file wp-content/plugins/glamlux-core/scripts/migrate-v7-token-management.php
 */

global $wpdb;

echo "[GlamLux] Starting token management migration...\n";

$charset_collate = $wpdb->get_charset_collate();

// ─────────────────────────────────────────────────────────────────────────────
// Table 1: Token Blacklist (revoked tokens)
// ─────────────────────────────────────────────────────────────────────────────

$table_blacklist = $wpdb->prefix . 'gl_token_blacklist';

if (!$wpdb->get_var("SHOW TABLES LIKE '$table_blacklist'")) {
    $sql = "CREATE TABLE $table_blacklist (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        token_hash varchar(255) NOT NULL,
        revoked_at datetime NOT NULL,
        reason varchar(255) DEFAULT 'manual' NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY user_token (user_id, token_hash),
        KEY revoked_at (revoked_at),
        INDEX user_id (user_id)
    ) $charset_collate;";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    
    echo "✅ Created table: $table_blacklist\n";
} else {
    echo "⚠️  Table already exists: $table_blacklist\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// Table 2: Refresh Tokens (token rotation)
// ─────────────────────────────────────────────────────────────────────────────

$table_refresh = $wpdb->prefix . 'gl_refresh_tokens';

if (!$wpdb->get_var("SHOW TABLES LIKE '$table_refresh'")) {
    $sql = "CREATE TABLE $table_refresh (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        token_hash varchar(255) NOT NULL,
        expires_at datetime NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        last_used_at datetime DEFAULT NULL,
        ip_address varchar(45),
        user_agent varchar(255),
        PRIMARY KEY (id),
        UNIQUE KEY user_token (user_id, token_hash),
        KEY expires_at (expires_at),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    
    echo "✅ Created table: $table_refresh\n";
} else {
    echo "⚠️  Table already exists: $table_refresh\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// Table 3: Token Sessions (user activity tracking)
// ─────────────────────────────────────────────────────────────────────────────

$table_sessions = $wpdb->prefix . 'gl_token_sessions';

if (!$wpdb->get_var("SHOW TABLES LIKE '$table_sessions'")) {
    $sql = "CREATE TABLE $table_sessions (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        session_id varchar(255) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        last_activity datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        ip_address varchar(45),
        device_info varchar(255),
        revoked_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY session_id (session_id),
        KEY user_id (user_id),
        KEY revoked_at (revoked_at)
    ) $charset_collate;";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    
    echo "✅ Created table: $table_sessions\n";
} else {
    echo "⚠️  Table already exists: $table_sessions\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// Register migration as completed
// ─────────────────────────────────────────────────────────────────────────────

$current_migration_version = (int)get_option('glamlux_token_migration_version', 0);

if ($current_migration_version < 7) {
    update_option('glamlux_token_migration_version', 7);
    echo "✅ Token management migration (v7) registered\n";
}

echo "[GlamLux] Token management migration complete!\n";
echo "[GlamLux] Tables ready for JWT token lifecycle management\n";
?>
