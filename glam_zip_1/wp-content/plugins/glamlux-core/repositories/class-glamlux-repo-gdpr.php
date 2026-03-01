<?php
class GlamLux_Repo_GDPR
{
    public function get_client_by_user_id($user_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT id, phone FROM {$wpdb->prefix}gl_clients WHERE wp_user_id = %d LIMIT 1", $user_id), ARRAY_A);
    }

    public function get_client_appointments($user_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.status FROM {$wpdb->prefix}gl_appointments a INNER JOIN {$wpdb->prefix}gl_clients c ON a.client_id = c.id WHERE c.wp_user_id = %d",
            $user_id
        ), ARRAY_A);
    }

    public function delete_client_data($client_id)
    {
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}gl_appointments", ['client_id' => null], ['client_id' => $client_id]);
        $wpdb->delete("{$wpdb->prefix}gl_clients", ['id' => $client_id]);
    }
}
