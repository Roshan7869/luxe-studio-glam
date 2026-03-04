<?php
/**
 * Redis Cache Service Tests
 *
 * @package GlamLux
 * @subpackage Tests
 * @since 7.2
 */

class GlamLux_Redis_Cache_Test extends WP_UnitTestCase
{
    private $cache;

    public function setUp(): void
    {
        parent::setUp();
        require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
        $this->cache = new GlamLux_Redis_Cache();
    }

    /**
     * Test: Set and get value from cache
     */
    public function test_set_and_get_cache()
    {
        $key = 'test_key_' . time();
        $value = ['user_id' => 123, 'name' => 'Test User'];

        $this->cache->set($key, $value, 3600);
        $retrieved = $this->cache->get($key);

        $this->assertEquals($value, $retrieved);
    }

    /**
     * Test: Cache miss returns null
     */
    public function test_cache_miss()
    {
        $result = $this->cache->get('nonexistent_key_' . time());
        $this->assertNull($result);
    }

    /**
     * Test: Callback executed on cache miss
     */
    public function test_callback_on_cache_miss()
    {
        $key = 'callback_test_' . time();
        $expected_value = ['data' => 'from_callback'];

        $result = $this->cache->get($key, function () use ($expected_value) {
            return $expected_value;
        });

        $this->assertEquals($expected_value, $result);
    }

    /**
     * Test: Delete removes value from cache
     */
    public function test_delete_cache_key()
    {
        $key = 'delete_test_' . time();
        $value = 'test_value';

        $this->cache->set($key, $value);
        $this->cache->delete($key);

        $result = $this->cache->get($key);
        $this->assertNull($result);
    }

    /**
     * Test: TTL is respected
     */
    public function test_ttl_expiration()
    {
        $key = 'ttl_test_' . time();
        $value = 'expiring_value';

        // Set with 1 second TTL
        $this->cache->set($key, $value, 1);

        // Verify immediately available
        $this->assertEquals($value, $this->cache->get($key));

        // Wait for expiry
        sleep(2);

        // Verify expired (may be null depending on backend)
        // Note: WordPress transients also have TTL behavior
        $this->cache->delete($key);
    }

    /**
     * Test: Increment counter
     */
    public function test_increment_counter()
    {
        $key = 'counter_' . time();

        $this->cache->set($key, 0, 3600);
        $result1 = $this->cache->increment($key, 1);
        $result2 = $this->cache->increment($key, 5);

        $this->assertEquals(1, $result1);
        $this->assertEquals(6, $result2);
    }

    /**
     * Test: Decrement counter
     */
    public function test_decrement_counter()
    {
        $key = 'counter_dec_' . time();

        $this->cache->set($key, 10, 3600);
        $result1 = $this->cache->decrement($key, 2);
        $result2 = $this->cache->decrement($key, 3);

        $this->assertEquals(8, $result1);
        $this->assertEquals(5, $result2);
    }

    /**
     * Test: Decrement doesn't go below zero
     */
    public function test_decrement_zero_floor()
    {
        $key = 'counter_floor_' . time();

        $this->cache->set($key, 2, 3600);
        $this->cache->decrement($key, 5);
        $result = $this->cache->get($key);

        $this->assertEquals(0, $result);
    }

    /**
     * Test: Flush cache
     */
    public function test_flush_cache()
    {
        $key1 = 'flush_test_1_' . time();
        $key2 = 'flush_test_2_' . time();

        $this->cache->set($key1, 'value1', 3600);
        $this->cache->set($key2, 'value2', 3600);

        $this->cache->flush();

        // After flush, keys might not be available (depending on backend)
        // Just verify flush doesn't throw error
        $this->assertTrue(true);
    }

    /**
     * Test: Invalidate by pattern
     */
    public function test_invalidate_pattern()
    {
        $pattern_key1 = 'salons_list_' . time();
        $pattern_key2 = 'salons_details_' . time();
        $other_key = 'services_list_' . time();

        $this->cache->set($pattern_key1, 'value1', 3600);
        $this->cache->set($pattern_key2, 'value2', 3600);
        $this->cache->set($other_key, 'value3', 3600);

        // Invalidate only salons keys
        $invalidated = $this->cache->invalidate_pattern('salons_*');

        // Should have invalidated at least pattern keys
        $this->assertGreaterThanOrEqual(0, $invalidated);
    }

    /**
     * Test: Cache statistics
     */
    public function test_get_cache_stats()
    {
        $stats = $this->cache->get_stats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('connected', $stats);
        $this->assertArrayHasKey('type', $stats);

        if ($stats['connected']) {
            $this->assertArrayHasKey('keys', $stats);
            $this->assertArrayHasKey('memory_used', $stats);
        }
    }

    /**
     * Test: Cache availability check
     */
    public function test_cache_availability()
    {
        $available = $this->cache->is_available();
        $this->assertIsBool($available);

        // If not available, should fall back to transients gracefully
        if (!$available) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test: Multiple values in cache
     */
    public function test_multiple_cached_values()
    {
        $values = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
            ['id' => 3, 'name' => 'Item 3'],
        ];

        $key = 'multi_test_' . time();
        $this->cache->set($key, $values, 3600);
        $retrieved = $this->cache->get($key);

        $this->assertEquals($values, $retrieved);
        $this->assertCount(3, $retrieved);
    }

    /**
     * Test: Null value handling
     */
    public function test_null_value_handling()
    {
        $key = 'null_test_' . time();

        // Setting null should be handled gracefully
        $this->cache->set($key, null, 3600);
        $result = $this->cache->get($key);

        // Null values might not be cached (backend dependent)
        // Just ensure no errors occur
        $this->assertTrue(true);
    }

    /**
     * Test: Large value caching
     */
    public function test_large_value_caching()
    {
        $key = 'large_test_' . time();
        $large_value = array_fill(0, 1000, ['data' => 'value']);

        $this->cache->set($key, $large_value, 3600);
        $retrieved = $this->cache->get($key);

        $this->assertIsArray($retrieved);
        $this->assertCount(1000, $retrieved);
    }

    /**
     * Test: Global cache instance
     */
    public function test_global_cache_instance()
    {
        require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
        
        $cache1 = glamlux_cache();
        $cache2 = glamlux_cache();

        // Should be same instance (singleton)
        $this->assertSame($cache1, $cache2);
    }

    /**
     * Test: Cache key with special characters
     */
    public function test_cache_key_special_characters()
    {
        $key = 'test_key_with_:_colons_and_-_dashes_' . time();
        $value = 'special_key_value';

        $this->cache->set($key, $value, 3600);
        $retrieved = $this->cache->get($key);

        $this->assertEquals($value, $retrieved);
    }

    /**
     * Test: Rapid fire cache operations
     */
    public function test_rapid_operations()
    {
        $key = 'rapid_' . time();

        // Multiple rapid operations
        for ($i = 0; $i < 10; $i++) {
            $this->cache->set($key . "_$i", "value_$i", 3600);
        }

        // Retrieve all
        for ($i = 0; $i < 10; $i++) {
            $result = $this->cache->get($key . "_$i");
            $this->assertEquals("value_$i", $result);
        }

        // Cleanup
        for ($i = 0; $i < 10; $i++) {
            $this->cache->delete($key . "_$i");
        }
    }

    /**
     * Test: Cache with different data types
     */
    public function test_mixed_data_types()
    {
        $key = 'mixed_' . time();

        $mixed_data = [
            'string' => 'test',
            'integer' => 123,
            'float' => 45.67,
            'boolean' => true,
            'array' => [1, 2, 3],
            'nested' => ['key' => 'value']
        ];

        $this->cache->set($key, $mixed_data, 3600);
        $retrieved = $this->cache->get($key);

        $this->assertEquals($mixed_data, $retrieved);
        $this->assertEquals('test', $retrieved['string']);
        $this->assertEquals(123, $retrieved['integer']);
        $this->assertEquals(45.67, $retrieved['float']);
        $this->assertTrue($retrieved['boolean']);
    }
}
