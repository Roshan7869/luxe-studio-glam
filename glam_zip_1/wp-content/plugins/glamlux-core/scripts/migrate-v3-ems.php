<?php
/**
 * Run EMS + analytics cache DB migration
 * wp eval-file /var/www/html/wp-content/plugins/glamlux-core/scripts/migrate-v3-ems.php
 */
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
global $wpdb;
$charset = $wpdb->get_charset_collate();

$tables = array(
    // Attendance (EMS)
    "CREATE TABLE {$wpdb->prefix}gl_attendance (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        staff_id bigint(20) NOT NULL,
        salon_id bigint(20) NOT NULL,
        shift_date date NOT NULL,
        check_in datetime DEFAULT NULL,
        check_out datetime DEFAULT NULL,
        hours_worked decimal(5,2) DEFAULT 0.00,
        is_late tinyint(1) DEFAULT 0,
        late_minutes int(11) DEFAULT 0,
        status varchar(50) DEFAULT 'present' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY staff_id (staff_id),
        KEY shift_date (shift_date),
        UNIQUE KEY staff_shift (staff_id, shift_date)
    ) {$charset};",

    // Shifts (EMS)
    "CREATE TABLE {$wpdb->prefix}gl_shifts (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        staff_id bigint(20) NOT NULL,
        salon_id bigint(20) NOT NULL,
        shift_date date NOT NULL,
        start_time time NOT NULL,
        end_time time NOT NULL,
        status varchar(50) DEFAULT 'scheduled' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY staff_id (staff_id),
        KEY shift_date (shift_date),
        UNIQUE KEY staff_shift (staff_id, shift_date)
    ) {$charset};",
);

foreach ($tables as $sql) {
    $result = dbDelta($sql);
}

$wp_version_option = get_option('glamlux_db_version');
update_option('glamlux_db_version', '2.1.0');

echo "EMS Migration v2.1.0 complete!\n";
$all = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}gl_%'");
echo "All GL tables (" . count($all) . "):\n";
foreach ($all as $t) {
    echo " - {$t}\n";
}
