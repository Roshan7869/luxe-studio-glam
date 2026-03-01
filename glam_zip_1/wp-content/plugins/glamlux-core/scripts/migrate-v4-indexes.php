<?php
/**
 * GlamLux V4 Migration Script
 * Adds missing indexes to improve database performance and query capability.
 */

defined('ABSPATH') || exit;

function glamlux_run_v4_migration()
{
    global $wpdb;

    // Add keys to gl_service_logs
    $wpdb->query("ALTER TABLE {$wpdb->prefix}gl_service_logs ADD KEY IF NOT EXISTS `appointment_id` (`appointment_id`)");

    // Add keys to gl_product_sales
    $wpdb->query("ALTER TABLE {$wpdb->prefix}gl_product_sales ADD KEY IF NOT EXISTS `wc_order_id` (`wc_order_id`)");
    $wpdb->query("ALTER TABLE {$wpdb->prefix}gl_product_sales ADD KEY IF NOT EXISTS `client_id` (`client_id`)");

    // Add keys to gl_franchises
    $wpdb->query("ALTER TABLE {$wpdb->prefix}gl_franchises ADD KEY IF NOT EXISTS `admin_id` (`admin_id`)");

    update_option('glamlux_db_version', '4.0.0');
}
