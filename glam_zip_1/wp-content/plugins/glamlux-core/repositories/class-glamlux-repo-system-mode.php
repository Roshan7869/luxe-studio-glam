<?php

/**
 * System Mode Data Access Object
 */
class GlamLux_Repo_System_Mode
{
    /**
     * Log a system mode override action to the database safely.
     */
    public static function log_audit(int $user_id, string $old_mode, string $new_mode, string $ip_address): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'gl_mode_audit';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            $wpdb->insert($table, array(
                'user_id' => $user_id,
                'previous_mode' => $old_mode,
                'new_mode' => $new_mode,
                'ip_address' => $ip_address,
                'created_at' => current_time('mysql'),
            ));
        }
    }
}
