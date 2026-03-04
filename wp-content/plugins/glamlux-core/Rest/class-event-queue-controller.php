<?php
/**
 * Event Queue Controller - REST API for monitoring and managing event queue
 *
 * @package GlamLux
 * @subpackage REST
 * @since 7.1
 */

class GlamLux_Event_Queue_Controller extends GlamLux_Base_Controller
{
    protected $namespace = 'glamlux/v1';
    protected $resource_name = 'event-queue';

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/stats',
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_queue_stats'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => []
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/events',
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_events'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => [
                    'status' => [
                        'type' => 'string',
                        'default' => 'pending',
                        'enum' => ['pending', 'processed', 'failed']
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'default' => 50,
                        'minimum' => 1,
                        'maximum' => 500
                    ],
                    'offset' => [
                        'type' => 'integer',
                        'default' => 0,
                        'minimum' => 0
                    ]
                ]
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/process',
            [
                'methods' => 'POST',
                'callback' => [$this, 'manually_process_queue'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => []
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/event/(?P<event_id>[a-f0-9\-]+)',
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_event_details'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => [
                    'event_id' => [
                        'type' => 'string',
                        'required' => true
                    ]
                ]
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/event/(?P<event_id>[a-f0-9\-]+)/retry',
            [
                'methods' => 'POST',
                'callback' => [$this, 'retry_event'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => [
                    'event_id' => [
                        'type' => 'string',
                        'required' => true
                    ]
                ]
            ]
        );
    }

    /**
     * Get queue statistics
     */
    public function get_queue_stats($request)
    {
        try {
            require_once GLAMLUX_PLUGIN_DIR . 'core/class-event-dispatcher.php';
            $stats = GlamLux_Event_Dispatcher::get_queue_stats();

            return new WP_REST_Response(
                [
                    'success' => true,
                    'data' => $stats,
                    'timestamp' => current_time('mysql')
                ],
                200
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Get queued events with filtering and pagination
     */
    public function get_events($request)
    {
        global $wpdb;

        try {
            $status = $request->get_param('status') ?? 'pending';
            $limit = $request->get_param('limit') ?? 50;
            $offset = $request->get_param('offset') ?? 0;

            // Validate status
            if (!in_array($status, ['pending', 'processed', 'failed'])) {
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'error' => 'Invalid status parameter'
                    ],
                    400
                );
            }

            $total = (int)$wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}gl_event_queue WHERE status = %s",
                    $status
                )
            );

            $events = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, event_id, event_name, priority, status, error_message, created_at, processed_at
                     FROM {$wpdb->prefix}gl_event_queue
                     WHERE status = %s
                     ORDER BY priority ASC, created_at DESC
                     LIMIT %d OFFSET %d",
                    $status,
                    $limit,
                    $offset
                )
            );

            return new WP_REST_Response(
                [
                    'success' => true,
                    'data' => $events,
                    'pagination' => [
                        'total' => $total,
                        'count' => count($events),
                        'offset' => $offset,
                        'limit' => $limit,
                        'pages' => ceil($total / $limit)
                    ]
                ],
                200
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Manually trigger queue processing
     */
    public function manually_process_queue($request)
    {
        try {
            require_once GLAMLUX_PLUGIN_DIR . 'core/class-event-dispatcher.php';

            $start_stats = GlamLux_Event_Dispatcher::get_queue_stats();
            GlamLux_Event_Dispatcher::process_queue();
            $end_stats = GlamLux_Event_Dispatcher::get_queue_stats();

            $processed = $start_stats['pending'] - $end_stats['pending'];

            return new WP_REST_Response(
                [
                    'success' => true,
                    'message' => "Processed {$processed} events",
                    'before' => $start_stats,
                    'after' => $end_stats
                ],
                200
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Get event details
     */
    public function get_event_details($request)
    {
        global $wpdb;

        try {
            $event_id = sanitize_text_field($request->get_param('event_id'));

            $event = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}gl_event_queue WHERE event_id = %s",
                    $event_id
                )
            );

            if (!$event) {
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'error' => 'Event not found'
                    ],
                    404
                );
            }

            // Decode event data
            $event->event_data = json_decode($event->event_data, true);

            return new WP_REST_Response(
                [
                    'success' => true,
                    'data' => $event
                ],
                200
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Retry failed event
     */
    public function retry_event($request)
    {
        global $wpdb;

        try {
            $event_id = sanitize_text_field($request->get_param('event_id'));

            $event = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}gl_event_queue WHERE event_id = %s AND status = %s",
                    $event_id,
                    'failed'
                )
            );

            if (!$event) {
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'error' => 'Failed event not found'
                    ],
                    404
                );
            }

            // Reset to pending
            $wpdb->update(
                $wpdb->prefix . 'gl_event_queue',
                [
                    'status' => 'pending',
                    'error_message' => null,
                    'processed_at' => null
                ],
                ['event_id' => $event_id]
            );

            // Trigger immediate processing
            wp_schedule_single_event(time(), 'glamlux_process_event_queue');

            return new WP_REST_Response(
                [
                    'success' => true,
                    'message' => 'Event queued for retry'
                ],
                200
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Check admin permission
     */
    public function check_admin_permission()
    {
        return current_user_can('manage_options');
    }
}

// Instantiate and register routes
add_action('rest_api_init', function () {
    $controller = new GlamLux_Event_Queue_Controller();
    $controller->register_routes();
});
