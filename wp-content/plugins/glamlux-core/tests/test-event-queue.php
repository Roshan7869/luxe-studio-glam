<?php
/**
 * Event Queue System Tests
 *
 * @package GlamLux
 * @subpackage Tests
 * @since 7.1
 */

class GlamLux_Event_Queue_Test extends WP_UnitTestCase
{
    private $dispatcher;

    public function setUp(): void
    {
        parent::setUp();
        require_once GLAMLUX_PLUGIN_DIR . 'core/class-event-dispatcher.php';
        $this->dispatcher = GlamLux_Event_Dispatcher::getInstance();
    }

    /**
     * Test: Dispatch CRITICAL priority event immediately
     */
    public function test_critical_event_processes_immediately()
    {
        $executed = [];
        
        $this->dispatcher->on('test_critical_event', function ($payload) use (&$executed) {
            $executed[] = $payload;
        });

        $this->dispatcher->dispatch(
            'test_critical_event',
            ['data' => 'test'],
            GlamLux_Event_Dispatcher::PRIORITY_CRITICAL
        );

        $this->assertCount(1, $executed);
        $this->assertEquals('test', $executed[0]['data']);
    }

    /**
     * Test: Dispatch HIGH priority event immediately
     */
    public function test_high_priority_event_processes_immediately()
    {
        $executed = [];
        
        $this->dispatcher->on('test_high_event', function ($payload) use (&$executed) {
            $executed[] = $payload;
        });

        $this->dispatcher->dispatch(
            'test_high_event',
            ['data' => 'high_priority'],
            GlamLux_Event_Dispatcher::PRIORITY_HIGH
        );

        $this->assertCount(1, $executed);
    }

    /**
     * Test: Dispatch NORMAL priority event to queue
     */
    public function test_normal_priority_event_queued()
    {
        global $wpdb;

        $event_id = $this->dispatcher->dispatch(
            'test_normal_event',
            ['data' => 'queued'],
            GlamLux_Event_Dispatcher::PRIORITY_NORMAL
        );

        $queued = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gl_event_queue WHERE event_id = %s",
                $event_id
            )
        );

        $this->assertNotNull($queued);
        $this->assertEquals('pending', $queued->status);
        $this->assertEquals('test_normal_event', $queued->event_name);
    }

    /**
     * Test: Queue statistics reflect correct counts
     */
    public function test_queue_stats_accuracy()
    {
        global $wpdb;

        // Clear existing queue
        $wpdb->query("DELETE FROM {$wpdb->prefix}gl_event_queue");

        // Queue 3 events
        $this->dispatcher->dispatch('event_1', [], GlamLux_Event_Dispatcher::PRIORITY_NORMAL);
        $this->dispatcher->dispatch('event_2', [], GlamLux_Event_Dispatcher::PRIORITY_LOW);
        $this->dispatcher->dispatch('event_3', [], GlamLux_Event_Dispatcher::PRIORITY_NORMAL);

        $stats = GlamLux_Event_Dispatcher::get_queue_stats();

        $this->assertEquals(3, $stats['pending']);
        $this->assertEquals(0, $stats['processed']);
        $this->assertEquals(0, $stats['failed']);
    }

    /**
     * Test: Process queue moves pending events to processed
     */
    public function test_process_queue_updates_status()
    {
        global $wpdb;

        // Clear and queue event
        $wpdb->query("DELETE FROM {$wpdb->prefix}gl_event_queue");
        
        $event_id = $this->dispatcher->dispatch(
            'test_process_event',
            ['data' => 'process_me'],
            GlamLux_Event_Dispatcher::PRIORITY_NORMAL
        );

        // Register handler
        $executed = [];
        $this->dispatcher->on('test_process_event', function ($payload) use (&$executed) {
            $executed[] = $payload;
        });

        // Process queue
        GlamLux_Event_Dispatcher::process_queue();

        // Check event is processed
        $event = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gl_event_queue WHERE event_id = %s",
                $event_id
            )
        );

        $this->assertEquals('processed', $event->status);
        $this->assertNotNull($event->processed_at);
    }

    /**
     * Test: Failed events are marked with error message
     */
    public function test_failed_events_store_error()
    {
        global $wpdb;

        // Clear queue
        $wpdb->query("DELETE FROM {$wpdb->prefix}gl_event_queue");

        $event_id = $this->dispatcher->dispatch(
            'test_failing_event',
            [],
            GlamLux_Event_Dispatcher::PRIORITY_NORMAL
        );

        // Register handler that throws
        $this->dispatcher->on('test_failing_event', function () {
            throw new Exception('Test error message');
        });

        // Process queue
        GlamLux_Event_Dispatcher::process_queue();

        // Check event failed
        $event = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gl_event_queue WHERE event_id = %s",
                $event_id
            )
        );

        $this->assertEquals('failed', $event->status);
        $this->assertStringContainsString('Test error message', $event->error_message);
    }

    /**
     * Test: Event priority affects queue order
     */
    public function test_event_priority_ordering()
    {
        global $wpdb;

        // Clear queue
        $wpdb->query("DELETE FROM {$wpdb->prefix}gl_event_queue");

        // Queue events with different priorities
        $this->dispatcher->dispatch('event_low', [], GlamLux_Event_Dispatcher::PRIORITY_LOW);
        $this->dispatcher->dispatch('event_high', [], GlamLux_Event_Dispatcher::PRIORITY_HIGH);
        $this->dispatcher->dispatch('event_normal', [], GlamLux_Event_Dispatcher::PRIORITY_NORMAL);

        // HIGH should execute immediately, not be queued
        $pending = $wpdb->get_results(
            "SELECT event_name FROM {$wpdb->prefix}gl_event_queue WHERE status = 'pending' ORDER BY priority ASC"
        );

        $this->assertCount(2, $pending);
        $this->assertEquals('event_normal', $pending[0]->event_name);
        $this->assertEquals('event_low', $pending[1]->event_name);
    }

    /**
     * Test: Queue cleanup removes old events
     */
    public function test_cleanup_old_events()
    {
        global $wpdb;

        // Clear queue
        $wpdb->query("DELETE FROM {$wpdb->prefix}gl_event_queue");

        // Create event dated 40 days ago
        $old_date = date('Y-m-d H:i:s', strtotime('-40 days'));
        $wpdb->insert(
            $wpdb->prefix . 'gl_event_queue',
            [
                'event_id' => wp_generate_uuid4(),
                'event_name' => 'old_event',
                'event_data' => '{}',
                'priority' => 10,
                'status' => 'processed',
                'created_at' => $old_date,
                'processed_at' => $old_date
            ]
        );

        // Create recent event
        $wpdb->insert(
            $wpdb->prefix . 'gl_event_queue',
            [
                'event_id' => wp_generate_uuid4(),
                'event_name' => 'recent_event',
                'event_data' => '{}',
                'priority' => 10,
                'status' => 'processed',
                'created_at' => current_time('mysql'),
                'processed_at' => current_time('mysql')
            ]
        );

        GlamLux_Event_Dispatcher::cleanup_old_events();

        $remaining = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gl_event_queue"
        );

        // Old event should be deleted
        $this->assertEquals(1, $remaining);
    }

    /**
     * Test: Event payload is JSON encoded/decoded correctly
     */
    public function test_event_payload_serialization()
    {
        global $wpdb;

        $payload = [
            'user_id' => 123,
            'action' => 'booking_created',
            'details' => [
                'service_id' => 456,
                'date' => '2026-03-04',
                'total' => 150.50
            ]
        ];

        $event_id = $this->dispatcher->dispatch(
            'test_serialization',
            $payload,
            GlamLux_Event_Dispatcher::PRIORITY_NORMAL
        );

        $event = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gl_event_queue WHERE event_id = %s",
                $event_id
            )
        );

        $decoded = json_decode($event->event_data, true);

        $this->assertEquals($payload, $decoded);
        $this->assertEquals(123, $decoded['user_id']);
        $this->assertEquals(150.50, $decoded['details']['total']);
    }

    /**
     * Test: Multiple handlers for same event
     */
    public function test_multiple_event_handlers()
    {
        $handler1_called = false;
        $handler2_called = false;

        $this->dispatcher->on('test_multi_handler', function () use (&$handler1_called) {
            $handler1_called = true;
        });

        $this->dispatcher->on('test_multi_handler', function () use (&$handler2_called) {
            $handler2_called = true;
        });

        $this->dispatcher->dispatch(
            'test_multi_handler',
            [],
            GlamLux_Event_Dispatcher::PRIORITY_CRITICAL
        );

        $this->assertTrue($handler1_called);
        $this->assertTrue($handler2_called);
    }

    /**
     * Test: Event ID is unique UUID format
     */
    public function test_event_id_format()
    {
        $event_id = $this->dispatcher->dispatch(
            'test_uuid_event',
            [],
            GlamLux_Event_Dispatcher::PRIORITY_CRITICAL
        );

        // UUID v4 format: 8-4-4-4-12 hexadecimal digits
        $uuid_pattern = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i';
        
        $this->assertMatchesRegularExpression($uuid_pattern, $event_id);
    }

    /**
     * Test: Core listeners are registered and executed
     */
    public function test_core_event_listeners_registered()
    {
        $handler_called = [];

        // Mock handler
        $mock_class = new class {
            public static function handle_payment($payload, $event_id)
            {
                global $handler_called;
                $handler_called[] = 'payment_handler';
            }
        };

        // Verify core map is populated
        $reflection = new ReflectionMethod($this->dispatcher, 'register_core_listeners');
        $reflection->setAccessible(true);
        $reflection->invoke($this->dispatcher);

        // Core listeners should include payment_completed
        $reflection = new ReflectionProperty($this->dispatcher, 'core_map');
        $reflection->setAccessible(true);
        $core_map = $reflection->getValue($this->dispatcher);

        $this->assertArrayHasKey('payment_completed', $core_map);
        $this->assertIsArray($core_map['payment_completed']);
    }
}
