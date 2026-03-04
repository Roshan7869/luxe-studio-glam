<?php
/**
 * Redis Cache Service for GlamLux
 *
 * Provides high-performance caching layer with automatic TTL management,
 * cache invalidation, and fallback to WordPress transients.
 *
 * @package GlamLux
 * @subpackage Services
 * @since 7.2
 */

class GlamLux_Redis_Cache
{
    private $redis = null;
    private $connected = false;
    private $prefix = 'glamlux_';
    private $default_ttl = 3600; // 1 hour

    public function __construct()
    {
        $this->connect();
    }

    /**
     * Connect to Redis instance
     */
    private function connect()
    {
        try {
            // Try Redis extension first
            if (extension_loaded('redis')) {
                $this->redis = new Redis();

                $host = defined('GLAMLUX_REDIS_HOST') ? GLAMLUX_REDIS_HOST : '127.0.0.1';
                $port = defined('GLAMLUX_REDIS_PORT') ? GLAMLUX_REDIS_PORT : 6379;
                $password = defined('GLAMLUX_REDIS_PASSWORD') ? GLAMLUX_REDIS_PASSWORD : null;
                $database = defined('GLAMLUX_REDIS_DB') ? GLAMLUX_REDIS_DB : 0;

                // Attempt connection
                if (@$this->redis->connect($host, $port, 5)) {
                    if ($password) {
                        $this->redis->auth($password);
                    }
                    $this->redis->select($database);
                    $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
                    $this->connected = true;

                    glamlux_log('Redis cache connected', [
                        'host' => $host,
                        'port' => $port,
                        'db' => $database
                    ]);
                } else {
                    throw new Exception('Cannot connect to Redis server');
                }
            }
        } catch (Exception $e) {
            glamlux_log_error('Redis connection failed, using WordPress transients: ' . $e->getMessage());
            $this->connected = false;
        }
    }

    /**
     * Get value from cache
     */
    public function get($key, $callback = null)
    {
        $cache_key = $this->prefix . $key;

        try {
            // Try Redis first
            if ($this->connected) {
                $value = $this->redis->get($cache_key);
                if ($value !== false) {
                    glamlux_log('Cache HIT (Redis): ' . $key);
                    return $value;
                }
            }

            // Fallback to WordPress transients
            $value = get_transient($cache_key);
            if ($value !== false) {
                glamlux_log('Cache HIT (Transient): ' . $key);
                return $value;
            }

            // Cache miss - execute callback if provided
            if ($callback && is_callable($callback)) {
                $value = call_user_func($callback);
                if ($value !== null) {
                    $this->set($key, $value);
                }
                return $value;
            }

            glamlux_log('Cache MISS: ' . $key);
            return null;
        } catch (Exception $e) {
            glamlux_log_error('Cache get error: ' . $e->getMessage());
            return $callback ? call_user_func($callback) : null;
        }
    }

    /**
     * Set value in cache
     */
    public function set($key, $value, $ttl = null)
    {
        $cache_key = $this->prefix . $key;
        $ttl = $ttl ?? $this->default_ttl;

        try {
            if ($this->connected) {
                $this->redis->setex($cache_key, $ttl, $value);
                glamlux_log('Cache SET (Redis): ' . $key . " (TTL: {$ttl}s)");
            } else {
                set_transient($cache_key, $value, $ttl);
                glamlux_log('Cache SET (Transient): ' . $key . " (TTL: {$ttl}s)");
            }

            return true;
        } catch (Exception $e) {
            glamlux_log_error('Cache set error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete value from cache
     */
    public function delete($key)
    {
        $cache_key = $this->prefix . $key;

        try {
            if ($this->connected) {
                $this->redis->del($cache_key);
                glamlux_log('Cache DELETE (Redis): ' . $key);
            } else {
                delete_transient($cache_key);
                glamlux_log('Cache DELETE (Transient): ' . $key);
            }

            return true;
        } catch (Exception $e) {
            glamlux_log_error('Cache delete error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all glamlux cache keys
     */
    public function flush()
    {
        try {
            if ($this->connected) {
                // Get all keys with our prefix
                $keys = $this->redis->keys($this->prefix . '*');
                if (!empty($keys)) {
                    $this->redis->del(...$keys);
                    glamlux_log('Cache FLUSH (Redis): ' . count($keys) . ' keys deleted');
                }
            } else {
                // With transients, we'd need to track keys manually
                glamlux_log('Cache FLUSH (Transient): Manual cleanup recommended');
            }

            return true;
        } catch (Exception $e) {
            glamlux_log_error('Cache flush error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get cache statistics
     */
    public function get_stats()
    {
        try {
            if ($this->connected) {
                $info = $this->redis->info('Stats');
                $memory = $this->redis->info('Memory');
                $keys = count($this->redis->keys($this->prefix . '*'));

                return [
                    'connected' => true,
                    'type' => 'Redis',
                    'keys' => $keys,
                    'memory_used' => $memory['used_memory_human'] ?? 'Unknown',
                    'total_hits' => $info['keyspace_hits'] ?? 0,
                    'total_misses' => $info['keyspace_misses'] ?? 0
                ];
            } else {
                return [
                    'connected' => false,
                    'type' => 'WordPress Transients',
                    'message' => 'Using WordPress transient API as fallback'
                ];
            }
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cache query results with automatic key generation
     */
    public function cache_query($query, $ttl = 3600, $key_prefix = 'query')
    {
        $cache_key = $key_prefix . '_' . md5($query);

        return $this->get($cache_key, function () use ($query) {
            global $wpdb;
            return $wpdb->get_results($query);
        }, $ttl);
    }

    /**
     * Invalidate cache by pattern
     */
    public function invalidate_pattern($pattern)
    {
        try {
            if ($this->connected) {
                $full_pattern = $this->prefix . $pattern;
                $keys = $this->redis->keys($full_pattern);
                
                if (!empty($keys)) {
                    $this->redis->del(...$keys);
                    glamlux_log('Cache INVALIDATE (pattern): ' . $pattern . ' - ' . count($keys) . ' keys');
                    return count($keys);
                }
            }

            return 0;
        } catch (Exception $e) {
            glamlux_log_error('Cache invalidate pattern error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if cache is available
     */
    public function is_available()
    {
        return $this->connected;
    }

    /**
     * Increment a counter
     */
    public function increment($key, $increment = 1)
    {
        $cache_key = $this->prefix . $key;

        try {
            if ($this->connected) {
                return $this->redis->incrBy($cache_key, $increment);
            }

            // Fallback: get, increment, set
            $current = intval(get_transient($cache_key)) ?? 0;
            $new_value = $current + $increment;
            set_transient($cache_key, $new_value, 3600);
            return $new_value;
        } catch (Exception $e) {
            glamlux_log_error('Cache increment error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Decrement a counter
     */
    public function decrement($key, $decrement = 1)
    {
        $cache_key = $this->prefix . $key;

        try {
            if ($this->connected) {
                return $this->redis->decrBy($cache_key, $decrement);
            }

            // Fallback: get, decrement, set
            $current = intval(get_transient($cache_key)) ?? 0;
            $new_value = max(0, $current - $decrement);
            set_transient($cache_key, $new_value, 3600);
            return $new_value;
        } catch (Exception $e) {
            glamlux_log_error('Cache decrement error: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Global Redis cache instance
 */
function glamlux_cache()
{
    static $cache = null;
    if ($cache === null) {
        $cache = new GlamLux_Redis_Cache();
    }
    return $cache;
}
