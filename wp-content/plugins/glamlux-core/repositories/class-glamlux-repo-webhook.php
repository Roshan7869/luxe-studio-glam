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

        // Suppress WP error output so duplicate key insertion safely fails quietly.
        $wpdb->suppress_errors(true);
        $inserted = $wpdb->insert($table, array(
            'gateway' => $gateway,
            'transaction_id' => $transaction_id,
            'event_type' => $event_type,
            'payload' => $payload,
            'created_at' => current_time('mysql'),
        ));
        $wpdb->suppress_errors(false);

        // If it failed to insert, it means the UNIQUE KEY (gateway, transaction_id) fired.
        if (!$inserted) {
            return false;
        }

        return true;
    }
}
