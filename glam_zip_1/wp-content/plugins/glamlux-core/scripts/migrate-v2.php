<?php
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
global $wpdb;
$charset = $wpdb->get_charset_collate();

$tables = array(
    "CREATE TABLE {$wpdb->prefix}gl_leads (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(150) NOT NULL,
        email varchar(150) NOT NULL,
        phone varchar(30) NOT NULL,
        state varchar(100) DEFAULT NULL,
        interest_type varchar(80) DEFAULT 'franchise' NOT NULL,
        message text,
        status varchar(50) DEFAULT 'new' NOT NULL,
        assigned_to bigint(20) DEFAULT NULL,
        source varchar(80) DEFAULT 'website' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY status (status),
        KEY assigned_to (assigned_to)
    ) {$charset};",

    "CREATE TABLE {$wpdb->prefix}gl_followups (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        lead_id bigint(20) NOT NULL,
        type varchar(80) NOT NULL,
        notes text,
        due_at datetime DEFAULT NULL,
        status varchar(50) DEFAULT 'pending' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY lead_id (lead_id),
        KEY due_at (due_at)
    ) {$charset};",

    "CREATE TABLE {$wpdb->prefix}gl_territories (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        franchise_id bigint(20) NOT NULL,
        state varchar(100) NOT NULL,
        district varchar(150) DEFAULT NULL,
        effective_from date NOT NULL,
        status varchar(50) DEFAULT 'active' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY franchise_id (franchise_id)
    ) {$charset};",

    "CREATE TABLE {$wpdb->prefix}gl_metrics_cache (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        metric_key varchar(100) NOT NULL,
        metric_value text NOT NULL,
        cached_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY metric_key (metric_key)
    ) {$charset};",
);

foreach ($tables as $sql) {
    dbDelta($sql);
}

echo "Migration complete. Tables now present:\n";
$all = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}gl_%'");
foreach ($all as $t) {
    echo " - {$t}\n";
}
