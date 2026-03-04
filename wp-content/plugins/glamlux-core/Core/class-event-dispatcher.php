<?php
/**
 * GlamLux Event Dispatcher - Enhanced with Async Queue Processing
 *
 * Provides a robust event-driven architecture for both immediate and async task processing.
 * Events can be processed immediately (critical) or queued for background processing.
 *
 * @package GlamLux
 * @subpackage Core
 * @since 7.1
 */

class GlamLux_Event_Dispatcher
{
    const PRIORITY_CRITICAL = 1;
    const PRIORITY_HIGH = 5;
    const PRIORITY_NORMAL = 10;
    const PRIORITY_LOW = 20;

    private $listeners = [];
    private $core_map = [];
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register_core_listeners()
    {
        $this->core_map = [
            'appointment_completed' => [
                ['GlamLux_Service_Commission', 'handle_appointment_completed'],
                ['GlamLux_Service_Revenue', 'handle_appointment_completed']
            ],
            'payment_completed' => [
                ['GlamLux_Service_Commission', 'handle_appointment_completed'],
                ['GlamLux_Service_Revenue', 'handle_payment_completed'],
                ['GlamLux_Event_Listeners', 'on_payment_captured']
            ],
            'appointment_created' => [
                ['GlamLux_Event_Listeners', 'on_appointment_created']
            ],
            'membership_granted' => [
                ['GlamLux_Event_Listeners', 'on_membership_granted']
            ],
            'payment_captured' => [
                ['GlamLux_Event_Listeners', 'on_payment_captured']
            ],
            'low_inventory_alert' => [
                ['GlamLux_Event_Listeners', 'on_low_inventory']
            ],
        ];
    }

    /**
     * Dispatch an event (immediately or queued based on priority)
     */
    public function dispatch($event, $payload = [], $priority = self::PRIORITY_NORMAL)
    {
        $event_id = wp_generate_uuid4();

        // For critical/high priority, execute immediately
        if ($priority <= self::PRIORITY_HIGH) {
            $this->execute_event($event, $payload, $event_id);
        } else {
            // Queue for background processing
            $this->queue_event($event, $payload, $priority, $event_id);
        }

        return $event_id;
    }

    /**
     * Execute event handlers immediately
     */
    private function execute_event($event, $payload, $event_id)
    {
        $all = array_merge($this->listeners[$event] ?? [], $this->core_map[$event] ?? []);
        
        foreach ($all as $cb) {
            try {
                if (is_array($cb) && is_string($cb[0]) && class_exists($cb[0])) {
                    call_user_func([$cb[0], $cb[1]], $payload, $event_id);
                } elseif (is_callable($cb)) {
                    call_user_func($cb, $payload, $event_id);
                }
            } catch (Throwable $e) {
                glamlux_log_error("[EventDispatcher] {$event} handler error", [
                    'event_id' => $event_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        do_action('glamlux_event_' . $event, $payload, $event_id);
    }

    /**
     * Queue event for background processing
     */
    private function queue_event($event, $payload, $priority, $event_id)
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'gl_event_queue',
            [
                'event_id' => $event_id,
                'event_name' => $event,
                'event_data' => wp_json_encode($payload),
                'priority' => $priority,
                'user_id' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'status' => 'pending'
            ]
        );

        // Trigger WP-Cron for processing if not already scheduled
        if (!wp_next_scheduled('glamlux_process_event_queue')) {
            wp_schedule_single_event(time() + 5, 'glamlux_process_event_queue');
        }
    }

    /**
     * Process queued events
     */
    public static function process_queue()
    {
        global $wpdb;

        $events = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}gl_event_queue
             WHERE status = 'pending'
             ORDER BY priority ASC, created_at ASC
             LIMIT 100"
        );

        if (empty($events)) {
            return;
        }

        $dispatcher = self::getInstance();
        $processed = 0;

        foreach ($events as $row) {
            try {
                $payload = json_decode($row->event_data, true);
                $dispatcher->execute_event($row->event_name, $payload, $row->event_id);

                $wpdb->update(
                    $wpdb->prefix . 'gl_event_queue',
                    ['status' => 'processed', 'processed_at' => current_time('mysql')],
                    ['id' => $row->id]
                );

                $processed++;
            } catch (Throwable $e) {
                glamlux_log_error("Queue event failed: {$row->event_name}", [
                    'error' => $e->getMessage()
                ]);

                $wpdb->update(
                    $wpdb->prefix . 'gl_event_queue',
                    ['status' => 'failed', 'error_message' => substr($e->getMessage(), 0, 255)],
                    ['id' => $row->id]
                );
            }
        }

        do_action('glamlux_queue_processed', ['processed' => $processed]);
    }

    /**
     * Get queue statistics
     */
    public static function get_queue_stats()
    {
        global $wpdb;

        return [
            'pending' => (int)$wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}gl_event_queue WHERE status = 'pending'"
            ),
            'processed' => (int)$wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}gl_event_queue WHERE status = 'processed'"
            ),
            'failed' => (int)$wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}gl_event_queue WHERE status = 'failed'"
            )
        ];
    }

    /**
     * Cleanup old processed/failed events (>30 days)
     */
    public static function cleanup_old_events()
    {
        global $wpdb;

        // Delete processed events older than 30 days
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}gl_event_queue
             WHERE status IN ('processed', 'failed')
             AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Keep failed events for 90 days for investigation
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}gl_event_queue
             WHERE status = 'failed'
             AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
    }

    /**
     * Register event listener
     */
    public function on($event, $listener)
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = $listener;
    }

    /**
     * Cleanup old events
     */
    public static function cleanup_old_events($days_old = 30)
    {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}gl_event_queue
                 WHERE status IN ('processed', 'failed')
                 AND processed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days_old
            )
        );
    }
}
