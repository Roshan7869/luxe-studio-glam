# Phase 1: Architecture Enhancement - Complete Implementation Plan

**Status:** 🚀 INITIATED  
**Duration:** 3-4 weeks  
**Estimated Effort:** 40 development hours  
**Target Completion:** Week 4  
**Architecture Score:** 92/100 → 95/100 (+3 points)  

---

## 📋 Phase 1 Objectives

### Primary Goals
1. **Event-Driven Architecture** - Async task processing
2. **Push Notifications** - Firebase + Web Push API support
3. **Caching Layer** - Redis integration for performance
4. **Message Queue** - RabbitMQ/AWS SQS support
5. **Advanced Monitoring** - Enhanced logging and observability

### Key Deliverables
- Event dispatcher system with job queue
- Firebase Cloud Messaging integration
- Web Push API implementation
- Redis caching layer
- Message queue worker system
- Enhanced monitoring & alerting

---

## 🎯 Implementation Roadmap

### Week 1: Event-Driven Architecture & Job System

#### 1.1 Event Dispatcher System (6 hours)
**File:** `wp-content/plugins/glamlux-core/core/class-event-dispatcher.php`

```php
<?php
/**
 * Event Dispatcher - Async event handling
 */
class GlamLux_Event_Dispatcher {
    const PRIORITY_CRITICAL = 1;
    const PRIORITY_HIGH = 5;
    const PRIORITY_NORMAL = 10;
    const PRIORITY_LOW = 20;

    private static $events = [];
    private static $queue = [];

    public static function dispatch($event_name, $data = [], $priority = self::PRIORITY_NORMAL) {
        // Store event in queue for async processing
        $event = [
            'name' => $event_name,
            'data' => $data,
            'priority' => $priority,
            'timestamp' => current_time('mysql'),
            'id' => wp_generate_uuid4()
        ];

        // For critical events, process immediately
        if ($priority <= self::PRIORITY_HIGH) {
            self::process_event($event);
        } else {
            // Queue for background processing
            self::queue_event($event);
        }

        do_action("glamlux_event_dispatched_{$event_name}", $data);
    }

    public static function subscribe($event_name, $callback, $priority = 10) {
        add_action("glamlux_event_{$event_name}", $callback, $priority, 2);
    }

    private static function process_event($event) {
        do_action("glamlux_event_{$event['name']}", $event['data'], $event['id']);
    }

    private static function queue_event($event) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'gl_event_queue',
            [
                'event_name' => $event['name'],
                'event_data' => wp_json_encode($event['data']),
                'priority' => $event['priority'],
                'created_at' => current_time('mysql'),
                'status' => 'pending'
            ]
        );
        
        // Trigger WP-Cron for queue processing
        wp_schedule_single_event(time() + 5, 'glamlux_process_event_queue');
    }

    public static function process_queue() {
        global $wpdb;
        
        $events = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}gl_event_queue 
             WHERE status = 'pending' 
             ORDER BY priority ASC, created_at ASC 
             LIMIT 100"
        );

        foreach ($events as $event) {
            try {
                $data = json_decode($event->event_data);
                self::process_event([
                    'name' => $event->event_name,
                    'data' => $data,
                    'id' => $event->id
                ]);
                
                $wpdb->update(
                    $wpdb->prefix . 'gl_event_queue',
                    ['status' => 'processed', 'processed_at' => current_time('mysql')],
                    ['id' => $event->id]
                );
            } catch (Exception $e) {
                glamlux_log_error("Event processing failed: {$event->event_name}", [
                    'error' => $e->getMessage(),
                    'event_id' => $event->id
                ]);
            }
        }
    }
}
```

**Database Migration:**
```sql
CREATE TABLE wp_gl_event_queue (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    event_name VARCHAR(255) NOT NULL,
    event_data LONGTEXT NOT NULL,
    priority TINYINT DEFAULT 10,
    status VARCHAR(50) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME,
    KEY status_priority (status, priority),
    KEY created_at (created_at)
);
```

**Checklist:**
- [ ] Create event dispatcher class
- [ ] Create event queue database table
- [ ] Register WP-Cron task for queue processing
- [ ] Add event subscription system
- [ ] Create event logging

#### 1.2 Job Queue System (5 hours)
**File:** `wp-content/plugins/glamlux-core/core/class-job-queue.php`

- [ ] Job scheduler
- [ ] Async job execution
- [ ] Job retry logic
- [ ] Dead letter queue
- [ ] Job monitoring dashboard

#### 1.3 Integration Tests (3 hours)
- [ ] Event dispatch test
- [ ] Queue processing test
- [ ] Event handler test
- [ ] Performance benchmarks

**Week 1 Summary:** Event system foundation ready
- ✅ Event dispatcher operational
- ✅ Job queue functional
- ✅ Integration tests passing

---

### Week 2: Push Notification System

#### 2.1 Firebase Cloud Messaging (FCM) (8 hours)
**File:** `wp-content/plugins/glamlux-core/notifications/class-fcm-handler.php`

```php
<?php
/**
 * Firebase Cloud Messaging Handler
 */
class GlamLux_FCM_Handler {
    private $server_key;
    private $sender_id;

    public function __construct() {
        $this->server_key = get_option('glamlux_fcm_server_key');
        $this->sender_id = get_option('glamlux_fcm_sender_id');
    }

    public function send_to_user($user_id, $title, $body, $data = []) {
        global $wpdb;
        
        // Get user's device tokens
        $tokens = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT device_token FROM {$wpdb->prefix}gl_device_tokens 
                 WHERE user_id = %d AND is_active = 1",
                $user_id
            )
        );

        foreach ($tokens as $token) {
            $this->send($token, $title, $body, $data);
        }
    }

    public function send($device_token, $title, $body, $data = []) {
        $message = [
            'to' => $device_token,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default'
            ],
            'data' => array_merge($data, [
                'sent_at' => current_time('mysql')
            ]),
            'priority' => 'high'
        ];

        return wp_safe_remote_post('https://fcm.googleapis.com/fcm/send', [
            'headers' => [
                'Authorization' => "key={$this->server_key}",
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($message)
        ]);
    }

    public function register_device($user_id, $device_token, $device_type = 'web') {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}gl_device_tokens 
             (user_id, device_token, device_type, is_active, created_at) 
             VALUES (%d, %s, %s, 1, NOW())
             ON DUPLICATE KEY UPDATE is_active = 1, last_used = NOW()",
            $user_id,
            $device_token,
            $device_type
        ));
    }

    public function unregister_device($device_token) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'gl_device_tokens',
            ['is_active' => 0],
            ['device_token' => $device_token]
        );
    }
}
```

**Checklist:**
- [ ] Create FCM handler class
- [ ] Setup Firebase project
- [ ] Create device token management system
- [ ] Implement device registration endpoint
- [ ] Implement send notification endpoint

#### 2.2 Web Push API (6 hours)
**File:** `wp-content/plugins/glamlux-core/notifications/class-web-push-handler.php`

- [ ] Web Push subscription management
- [ ] VAPID key generation
- [ ] Push payload encryption
- [ ] Notification display on client

#### 2.3 Push Notification Events (4 hours)
- [ ] Appointment reminders
- [ ] Membership expiry alerts
- [ ] Payment confirmations
- [ ] System alerts

**Week 2 Summary:** Notifications system operational
- ✅ FCM integration complete
- ✅ Web Push API implemented
- ✅ Notification events configured

---

### Week 3: Caching Layer & Performance

#### 3.1 Redis Integration (6 hours)
**File:** `wp-content/plugins/glamlux-core/cache/class-redis-cache.php`

```php
<?php
/**
 * Redis Cache Driver
 */
class GlamLux_Redis_Cache {
    private $redis;
    private $prefix = 'glamlux:';
    private $ttl = 3600; // Default 1 hour

    public function __construct() {
        if (class_exists('Redis')) {
            $this->redis = new Redis();
            $this->redis->connect(
                getenv('REDIS_HOST') ?: 'localhost',
                getenv('REDIS_PORT') ?: 6379
            );
            
            if (getenv('REDIS_PASSWORD')) {
                $this->redis->auth(getenv('REDIS_PASSWORD'));
            }
        }
    }

    public function get($key) {
        if (!$this->redis) return null;
        
        $value = $this->redis->get($this->prefix . $key);
        return $value ? unserialize($value) : null;
    }

    public function set($key, $value, $ttl = null) {
        if (!$this->redis) return false;
        
        $ttl = $ttl ?? $this->ttl;
        return $this->redis->setex(
            $this->prefix . $key,
            $ttl,
            serialize($value)
        );
    }

    public function delete($key) {
        if (!$this->redis) return false;
        return $this->redis->del($this->prefix . $key);
    }

    public function flush() {
        if (!$this->redis) return false;
        return $this->redis->flushDB();
    }

    public function cache_query($query_key, $callback, $ttl = 3600) {
        $cached = $this->get($query_key);
        if ($cached !== null) {
            return $cached;
        }

        $result = $callback();
        $this->set($query_key, $result, $ttl);
        return $result;
    }
}
```

**Checklist:**
- [ ] Create Redis cache driver
- [ ] Setup Redis connection pooling
- [ ] Implement cache invalidation strategy
- [ ] Create cache warming jobs

#### 3.2 Query Caching (5 hours)
- [ ] Salon listings cache (TTL: 1 hour)
- [ ] Service pricing cache (TTL: 2 hours)
- [ ] Staff availability cache (TTL: 30 min)
- [ ] Appointment slots cache (TTL: 5 min)

#### 3.3 API Response Caching (4 hours)
- [ ] REST API response caching
- [ ] Cache headers (ETag, Last-Modified)
- [ ] Conditional GET support
- [ ] Cache busting on updates

**Week 3 Summary:** Caching operational
- ✅ Redis driver functional
- ✅ Query caching active
- ✅ Performance improved 2-3x

---

### Week 4: Message Queue & Monitoring

#### 4.1 Message Queue Integration (6 hours)
**Options:**
- AWS SQS (if Railway)
- RabbitMQ (if self-hosted)
- Database queue (fallback)

```php
public interface GlamLux_Queue_Driver {
    public function push($job_class, $data, $delay = 0);
    public function pop();
    public function process_batch($limit = 10);
}
```

**Checklist:**
- [ ] Create queue driver interface
- [ ] Implement AWS SQS driver
- [ ] Implement database queue driver
- [ ] Create worker CLI command

#### 4.2 Background Jobs (5 hours)
- [ ] Email sending queue
- [ ] PDF generation queue
- [ ] Report generation queue
- [ ] Data export queue
- [ ] Image processing queue

#### 4.3 Advanced Monitoring (5 hours)
- [ ] Queue depth monitoring
- [ ] Job execution metrics
- [ ] Failed job alerting
- [ ] Performance dashboards

**Week 4 Summary:** Production-grade architecture
- ✅ Message queue operational
- ✅ Background jobs working
- ✅ Monitoring in place

---

## 📊 Database Schema Changes

### New Tables

```sql
-- Event Queue
CREATE TABLE wp_gl_event_queue (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    event_name VARCHAR(255) NOT NULL,
    event_data LONGTEXT NOT NULL,
    priority TINYINT DEFAULT 10,
    status VARCHAR(50) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME,
    KEY status_priority (status, priority)
);

-- Device Tokens (Push Notifications)
CREATE TABLE wp_gl_device_tokens (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    device_token VARCHAR(1000) NOT NULL,
    device_type VARCHAR(50) DEFAULT 'web',
    is_active TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used DATETIME,
    UNIQUE KEY user_device (user_id, device_token),
    KEY user_id (user_id)
);

-- Push Notification History
CREATE TABLE wp_gl_notifications (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    data LONGTEXT,
    read_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY user_id (user_id),
    KEY created_at (created_at)
);

-- Message Queue
CREATE TABLE wp_gl_message_queue (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    job_class VARCHAR(255) NOT NULL,
    job_data LONGTEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME,
    completed_at DATETIME,
    error_message TEXT,
    KEY status_attempts (status, attempts)
);

-- Cache Keys (for monitoring)
CREATE TABLE wp_gl_cache_stats (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    cache_key VARCHAR(255) NOT NULL,
    hits INT DEFAULT 0,
    misses INT DEFAULT 0,
    last_accessed DATETIME,
    ttl INT,
    UNIQUE KEY cache_key (cache_key)
);
```

---

## 🧪 Testing & Validation

### Unit Tests
- [ ] Event dispatcher tests
- [ ] Job queue tests
- [ ] FCM handler tests
- [ ] Redis cache tests
- [ ] Message queue tests

### Integration Tests
- [ ] End-to-end event flow
- [ ] Push notification delivery
- [ ] Cache hit/miss scenarios
- [ ] Background job processing

### Performance Tests
- [ ] Cache performance (Redis vs DB)
- [ ] Queue throughput
- [ ] Event processing latency
- [ ] Memory usage

---

## 🚀 Deployment

### Pre-Deployment Checklist
- [ ] All code reviewed and tested
- [ ] Database migrations prepared
- [ ] Redis connection tested
- [ ] Firebase setup complete
- [ ] Queue service configured
- [ ] Monitoring alerts configured

### Deployment Steps
1. Create database tables (migration)
2. Deploy code to staging
3. Run integration tests
4. Configure environment variables
5. Enable Redis caching
6. Start queue workers
7. Deploy to production
8. Monitor metrics

### Rollback Plan
- Keep previous version running in parallel
- Disable new features via feature flags
- Revert to previous Redis backup
- Re-route queues to database driver

---

## 📈 Expected Improvements

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| API Response Time | 200ms | 50ms | 4x faster |
| Database Load | High | Low | 60% reduction |
| User Engagement | Baseline | +25% | Push notifications |
| Feature Flexibility | Limited | Full | Event-driven arch |

---

## 📚 Documentation

### Files to Create
- [ ] PHASE_1_IMPLEMENTATION_GUIDE.md
- [ ] EVENT_SYSTEM_DOCUMENTATION.md
- [ ] PUSH_NOTIFICATIONS_GUIDE.md
- [ ] REDIS_CACHING_GUIDE.md
- [ ] MESSAGE_QUEUE_SETUP.md

---

## ✅ Success Criteria

- [x] Phase 1 plan created
- [ ] Event dispatcher implemented & tested
- [ ] Push notifications working
- [ ] Redis caching active
- [ ] Message queue operational
- [ ] Performance improved 2-3x
- [ ] Architecture score: 95/100
- [ ] All tests passing
- [ ] Production deployment verified

---

## 🎯 Next Phase (Phase 2)

After Phase 1 completion:
- UI/UX Optimization
- Mobile-first design
- Accessibility improvements
- Performance tuning

---

**Estimated Timeline:** 3-4 weeks  
**Target Completion:** Week 4 of current sprint  
**Ready for Phase 2:** When all Phase 1 items complete  

Let's build an enterprise-grade architecture! 🚀
