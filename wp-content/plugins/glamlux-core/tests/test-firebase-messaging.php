<?php
/**
 * Firebase Cloud Messaging Tests
 *
 * @package GlamLux
 * @subpackage Tests
 * @since 7.1
 */

class GlamLux_Firebase_Messaging_Test extends WP_UnitTestCase
{
    private $fcm;
    private $test_user_id;
    private $test_token = 'eGx4LzFfOEQxVVoxckp2T3d4VVp1UG1DbFN3VnF1QUZzNlZmMndVbGRGaDAwODg6RkE5QTU3MzgwMjhhZWE1ZDQ1MWI4MzYwODFmODQ3OWQ=';

    public function setUp(): void
    {
        parent::setUp();
        require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-firebase-messaging.php';
        $this->fcm = new GlamLux_Firebase_Messaging();
        $this->test_user_id = self::factory()->user->create(['role' => 'client']);
    }

    /**
     * Test: Register device token for user
     */
    public function test_register_device_token()
    {
        global $wpdb;

        $device_id = $this->fcm->register_device_token(
            $this->test_user_id,
            $this->test_token,
            'iPhone 14 Pro'
        );

        $this->assertIsInt($device_id);
        $this->assertGreaterThan(0, $device_id);

        $device = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gl_device_tokens WHERE id = %d",
                $device_id
            )
        );

        $this->assertNotNull($device);
        $this->assertEquals($this->test_user_id, $device->user_id);
        $this->assertEquals($this->test_token, $device->token);
        $this->assertEquals('iPhone 14 Pro', $device->device_name);
        $this->assertEquals(1, $device->is_active);
    }

    /**
     * Test: Reject invalid token format
     */
    public function test_reject_short_token()
    {
        $device_id = $this->fcm->register_device_token(
            $this->test_user_id,
            'short_token',
            'Device'
        );

        $this->assertFalse($device_id);
    }

    /**
     * Test: Update last_used_at on duplicate token registration
     */
    public function test_duplicate_token_updates_activity()
    {
        global $wpdb;

        // Register token first time
        $device_id = $this->fcm->register_device_token(
            $this->test_user_id,
            $this->test_token,
            'Device 1'
        );

        $first_activity = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT last_used_at FROM {$wpdb->prefix}gl_device_tokens WHERE id = %d",
                $device_id
            )
        );

        sleep(1);

        // Register same token again
        $same_device_id = $this->fcm->register_device_token(
            $this->test_user_id,
            $this->test_token,
            'Device 1'
        );

        $updated_activity = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT last_used_at FROM {$wpdb->prefix}gl_device_tokens WHERE id = %d",
                $same_device_id
            )
        );

        $this->assertEquals($device_id, $same_device_id);
        $this->assertGreaterThan($first_activity, $updated_activity);
    }

    /**
     * Test: Unregister device token
     */
    public function test_unregister_device_token()
    {
        global $wpdb;

        $this->fcm->register_device_token($this->test_user_id, $this->test_token);
        $this->fcm->unregister_device_token($this->test_token);

        $device = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT is_active, deactivated_at FROM {$wpdb->prefix}gl_device_tokens WHERE token = %s",
                $this->test_token
            )
        );

        $this->assertEquals(0, $device->is_active);
        $this->assertNotNull($device->deactivated_at);
    }

    /**
     * Test: Get user devices only returns active devices
     */
    public function test_get_user_devices_only_active()
    {
        $token1 = $this->test_token;
        $token2 = 'eGx4LzFfOEQxVVoxckp2T3d4VVp1UG1DbFN3VnF1QUZzNlZmMndVbGRGaDAwODg6RkE5QTU3MzgwMjhhZWE1ZDQ1MWI4MzYwODFmODQ3OWQyMjI=';
        $token3 = 'eGx4LzFfOEQxVVoxckp2T3d4VVp1UG1DbFN3VnF1QUZzNlZmMndVbGRGaDAwODg6RkE5QTU3MzgwMjhhZWE1ZDQ1MWI4MzYwODFmODQ3OWQzMzM=';

        $this->fcm->register_device_token($this->test_user_id, $token1, 'Device 1');
        $this->fcm->register_device_token($this->test_user_id, $token2, 'Device 2');
        $this->fcm->register_device_token($this->test_user_id, $token3, 'Device 3');

        // Deactivate one
        $this->fcm->unregister_device_token($token2);

        $devices = $this->fcm->get_user_devices($this->test_user_id);

        $this->assertCount(2, $devices);

        $tokens = array_column($devices, 'token');
        $this->assertContains($token1, $tokens);
        $this->assertContains($token3, $tokens);
        $this->assertNotContains($token2, $tokens);
    }

    /**
     * Test: Cleanup removes inactive devices older than threshold
     */
    public function test_cleanup_inactive_tokens()
    {
        global $wpdb;

        // Register and deactivate a token
        $this->fcm->register_device_token($this->test_user_id, $this->test_token);
        $this->fcm->unregister_device_token($this->test_token);

        // Manually set deactivated_at to 100 days ago
        $old_date = date('Y-m-d H:i:s', strtotime('-100 days'));
        $wpdb->update(
            $wpdb->prefix . 'gl_device_tokens',
            ['deactivated_at' => $old_date],
            ['token' => $this->test_token]
        );

        // Create another token that was last used 100 days ago
        $this->fcm->register_device_token($this->test_user_id, $this->test_token . 'old');
        $wpdb->update(
            $wpdb->prefix . 'gl_device_tokens',
            ['last_used_at' => $old_date],
            ['token' => $this->test_token . 'old']
        );

        // Run cleanup (90 day threshold)
        $this->fcm->cleanup_inactive_tokens(90);

        $remaining = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}gl_device_tokens WHERE user_id = %d",
                $this->test_user_id
            )
        );

        // Both old tokens should be deleted
        $this->assertEquals(0, $remaining);
    }

    /**
     * Test: Send notification to user (requires Firebase config)
     */
    public function test_send_notification_requires_config()
    {
        $this->fcm->register_device_token($this->test_user_id, $this->test_token);

        // Without Firebase config, should fail gracefully
        $result = $this->fcm->send_to_user(
            $this->test_user_id,
            'Test Title',
            'Test Body'
        );

        // Should return result structure even if Firebase not configured
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('failed', $result);
    }

    /**
     * Test: Send notification to role
     */
    public function test_send_notification_to_role_format()
    {
        // Create multiple users with stylist role
        $stylist1 = self::factory()->user->create(['role' => 'stylist']);
        $stylist2 = self::factory()->user->create(['role' => 'stylist']);
        $client = self::factory()->user->create(['role' => 'client']);

        $this->fcm->register_device_token($stylist1, $this->test_token . '1');
        $this->fcm->register_device_token($stylist2, $this->test_token . '2');
        $this->fcm->register_device_token($client, $this->test_token . '3');

        // Send to stylist role (should attempt 2 users)
        $result = $this->fcm->send_to_role(
            'stylist',
            'Test Notification',
            'Test Body'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('failed', $result);
    }

    /**
     * Test: Send batch notification format validation
     */
    public function test_send_batch_notification_format()
    {
        $token1 = $this->test_token . '1';
        $token2 = $this->test_token . '2';

        $result = $this->fcm->send_notification_batch(
            [$token1, $token2],
            'Batch Test',
            'Test Body',
            ['action' => 'test', 'id' => '123']
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(2, $result['total']);
    }

    /**
     * Test: Empty token list handling
     */
    public function test_send_batch_with_empty_list()
    {
        $result = $this->fcm->send_notification_batch([], 'Title', 'Body');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(0, $result['failed']);
    }

    /**
     * Test: Multiple devices for same user
     */
    public function test_multiple_devices_per_user()
    {
        global $wpdb;

        $tokens = [];
        for ($i = 1; $i <= 5; $i++) {
            $token = $this->test_token . $i;
            $tokens[] = $token;
            $this->fcm->register_device_token($this->test_user_id, $token, "Device $i");
        }

        $devices = $this->fcm->get_user_devices($this->test_user_id);

        $this->assertCount(5, $devices);

        $registered_tokens = array_column($devices, 'token');
        foreach ($tokens as $token) {
            $this->assertContains($token, $registered_tokens);
        }
    }

    /**
     * Test: Device name sanitization
     */
    public function test_device_name_sanitization()
    {
        global $wpdb;

        $malicious_name = '<script>alert("xss")</script>Device';
        $device_id = $this->fcm->register_device_token(
            $this->test_user_id,
            $this->test_token,
            $malicious_name
        );

        $device = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT device_name FROM {$wpdb->prefix}gl_device_tokens WHERE id = %d",
                $device_id
            )
        );

        // Should be sanitized (script tags removed)
        $this->assertNotContains('<script>', $device->device_name);
        $this->assertStringContainsString('Device', $device->device_name);
    }

    /**
     * Test: Tokens ordered by last_used_at descending
     */
    public function test_devices_ordered_by_activity()
    {
        global $wpdb;

        $token1 = $this->test_token . '1';
        $token2 = $this->test_token . '2';
        $token3 = $this->test_token . '3';

        $id1 = $this->fcm->register_device_token($this->test_user_id, $token1);
        sleep(1);
        $id2 = $this->fcm->register_device_token($this->test_user_id, $token2);
        sleep(1);
        $id3 = $this->fcm->register_device_token($this->test_user_id, $token3);

        $devices = $this->fcm->get_user_devices($this->test_user_id);

        // Most recently used should be first
        $this->assertEquals($token3, $devices[0]->token);
        $this->assertEquals($token2, $devices[1]->token);
        $this->assertEquals($token1, $devices[2]->token);
    }
}
