<?php

/**
 * Repository for logging and persisting webhook events for auditing and idempotency constraint checking.
 */
class GlamLux_Repo_Webhook
{
    /**
     * Log a webhook transaction to the database safely.
     *
     * @return bool False if it is a duplicate event. True if successfully logged or safely skipped (legacy environment fallback).
     */
    public function log_webhook_event(string $gateway, string $transaction_id, string $event_type, string $payload): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'gl_webhook_events';

        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($table_exists === $table) {
            $exists = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM {$table} WHERE gateway = %s AND transaction_id = %s", $gateway, $transaction_id)
            );
            if ($exists) {
                return false;
            }
            $wpdb->insert($table, array(
                'gateway' => $gateway,
                'transaction_id' => $transaction_id,
                'event_type' => $event_type,
                'payload' => $payload,
                'created_at' => current_time('mysql'),
            ));
        }
        return true;
    }
}
