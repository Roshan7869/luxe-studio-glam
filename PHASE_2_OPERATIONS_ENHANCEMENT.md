# 🏢 PHASE 2: OPERATIONAL MANAGEMENT ENHANCEMENT

**Project**: Luxe Studio Glam v3.2  
**Phase**: 2 of 6  
**Duration**: 8-12 hours  
**Focus**: Operations, Monitoring, Deployment Automation  

---

## 🎯 Phase 2 Objectives

### Primary Goal
Build **enterprise-grade operational management** system with:
- Health monitoring & alerting
- Centralized logging
- Performance tracking
- Automated deployments
- Incident response
- Database optimization

---

## 📋 Implementation Roadmap

### Task 1: Health Check Endpoint (1 hour)

Create comprehensive health endpoint:
```php
// File: wp-content/plugins/glamlux-core/Rest/class-health-controller.php

GET /wp-json/glamlux/v1/health
↓
Returns status of:
  - Database connection
  - Redis cache connection
  - Plugin activation status
  - Database schema integrity
  - Cron job status
  - PHP version
  - Memory usage
  - Error logs
```

**Expected Response**:
```json
{
  "status": "healthy",
  "timestamp": "2026-03-03T14:05:00Z",
  "checks": {
    "database": { "status": "ok", "response_time": "12ms" },
    "redis": { "status": "ok", "response_time": "2ms" },
    "plugin": { "status": "active", "version": "3.2.0" },
    "schema": { "status": "valid", "tables": 10 },
    "cron": { "status": "running", "next_run": "2026-03-03T14:10:00Z" },
    "memory": { "usage": "256MB", "limit": "512MB", "status": "ok" }
  }
}
```

---

### Task 2: Centralized Logging System (2 hours)

Implement structured logging:

**File**: `wp-content/plugins/glamlux-core/Core/class-glamlux-logger.php` (ENHANCE)

```php
class GlamLux_Logger {
    
    public static function log($level, $message, $context = []) {
        $entry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'request_id' => self::get_request_id(),
            'ip_address' => self::get_client_ip(),
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        ];
        
        // Write to file
        error_log(wp_json_encode($entry));
        
        // Send to Sentry if configured
        if (class_exists('Sentry\SentrySdk')) {
            Sentry\SentrySdk::captureEvent([
                'level' => $level,
                'message' => $message,
                'extra' => $context,
            ]);
        }
        
        // Store in database for audit trail
        self::store_audit_log($entry);
    }
}
```

**Log Levels**:
```
debug   - Detailed debugging info
info    - General informational messages
warning - Warning messages (recoverable issues)
error   - Error messages (action failed)
critical - Critical failures (app at risk)
```

---

### Task 3: Performance Monitoring (2 hours)

Create performance tracking system:

**File**: `wp-content/plugins/glamlux-core/includes/class-glamlux-performance.php` (NEW)

```php
class GlamLux_Performance {
    
    public static function track_request() {
        $start_time = microtime(true);
        
        // Hooks to capture timing
        add_action('init', function() {
            global $glamlux_perf;
            $glamlux_perf['init'] = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        });
        
        add_action('wp_footer', function() {
            $duration = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            
            // Log if slow
            if ($duration > 1.0) {
                GlamLux_Logger::warning('Slow page load', [
                    'duration' => $duration,
                    'page' => $_SERVER['REQUEST_URI'],
                    'memory' => memory_get_peak_usage(true) / 1024 / 1024,
                ]);
            }
        });
    }
    
    public static function get_metrics() {
        return [
            'page_load_time' => self::get_page_load_time(),
            'memory_usage' => memory_get_usage(true) / 1024 / 1024,
            'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024,
            'database_queries' => self::count_queries(),
            'database_time' => self::get_query_time(),
            'cache_hits' => self::get_cache_hits(),
        ];
    }
}
```

---

### Task 4: Error Tracking & Alerts (2 hours)

**File**: `wp-content/plugins/glamlux-core/Core/class-glamlux-sentry.php` (ENHANCE)

```php
class GlamLux_Sentry {
    
    public static function init() {
        if (!defined('SENTRY_DSN')) {
            return;
        }
        
        Sentry\init([
            'dsn' => SENTRY_DSN,
            'environment' => WP_ENVIRONMENT_TYPE ?? 'production',
            'traces_sample_rate' => 0.1,
            'profiles_sample_rate' => 0.1,
        ]);
        
        // Capture unhandled exceptions
        self::setup_error_handlers();
        
        // Setup performance monitoring
        self::setup_performance_tracking();
    }
    
    private static function setup_error_handlers() {
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            Sentry\captureException(new ErrorException($errstr, 0, $errno, $errfile, $errline));
            return false;
        });
        
        set_exception_handler(function($exception) {
            Sentry\captureException($exception);
        });
    }
}
```

---

### Task 5: Database Optimization (2 hours)

**File**: `wp-content/plugins/glamlux-core/scripts/optimize-database.php` (NEW)

```php
/**
 * Database Optimization Script
 * Usage: wp eval-file wp-content/plugins/glamlux-core/scripts/optimize-database.php
 */

global $wpdb;

echo "🔧 Optimizing database...\n";

// 1. Optimize all tables
$tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}gl_%'");
foreach ($tables as $table) {
    echo "  Optimizing $table... ";
    $wpdb->query("OPTIMIZE TABLE $table");
    echo "✅\n";
}

// 2. Analyze tables for query optimizer
foreach ($tables as $table) {
    echo "  Analyzing $table... ";
    $wpdb->query("ANALYZE TABLE $table");
    echo "✅\n";
}

// 3. Check for missing indexes
echo "\n📊 Index Analysis:\n";
self::check_indexes($wpdb);

// 4. Generate statistics
echo "\n📈 Database Statistics:\n";
$stats = self::get_database_stats($wpdb);
echo "  Total size: " . size_format($stats['size']) . "\n";
echo "  Table count: " . count($tables) . "\n";
echo "  Total rows: " . number_format($stats['rows']) . "\n";

echo "\n✅ Database optimization complete!\n";
```

---

### Task 6: Caching Strategy (1.5 hours)

**File**: `wp-content/plugins/glamlux-core/includes/class-glamlux-cache.php` (ENHANCE)

```php
class GlamLux_Cache {
    
    // Cache durations
    const CACHE_SHORT = 5 * MINUTE_IN_SECONDS;      // 5 min
    const CACHE_MEDIUM = 15 * MINUTE_IN_SECONDS;    // 15 min
    const CACHE_LONG = HOUR_IN_SECONDS;             // 1 hour
    const CACHE_EXTENDED = DAY_IN_SECONDS;          // 1 day
    
    public static function get_salons() {
        $cache_key = 'glamlux_salons_all_' . get_current_blog_id();
        $cached = wp_cache_get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        global $wpdb;
        $salons = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}gl_salons WHERE is_active=1 ORDER BY name ASC",
            ARRAY_A
        );
        
        wp_cache_set($cache_key, $salons, '', self::CACHE_LONG);
        return $salons;
    }
    
    public static function invalidate_cache($type) {
        $patterns = [
            'salons' => 'glamlux_salons_*',
            'services' => 'glamlux_services_*',
            'appointments' => 'glamlux_appointments_*',
            'staff' => 'glamlux_staff_*',
        ];
        
        if (!isset($patterns[$type])) {
            return false;
        }
        
        // Clear matching cache entries
        wp_cache_flush_group($patterns[$type]);
        
        GlamLux_Logger::info("Cache invalidated: $type");
        return true;
    }
}
```

---

### Task 7: Deployment Automation (2.5 hours)

**File**: `.github/workflows/deploy.yml` (NEW)

```yaml
name: Deploy to Production

on:
  push:
    branches: [main]
  workflow_dispatch:

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Run tests
        run: |
          composer install
          composer test
          composer analyze
      
      - name: Build Docker image
        run: |
          docker build -t glamlux:${{ github.sha }} .
          docker tag glamlux:${{ github.sha }} glamlux:latest
      
      - name: Deploy to Railway
        env:
          RAILWAY_TOKEN: ${{ secrets.RAILWAY_TOKEN }}
        run: |
          railway deploy --detach
      
      - name: Run smoke tests
        run: |
          ./scripts/smoke-tests.sh
      
      - name: Notify Slack
        if: always()
        uses: slackapi/slack-github-action@v1
        with:
          webhook-url: ${{ secrets.SLACK_WEBHOOK }}
```

**File**: `scripts/smoke-tests.sh` (NEW)

```bash
#!/bin/bash
# Smoke tests after deployment

set -e

echo "🧪 Running smoke tests..."

# Test 1: Health endpoint
echo "  Testing health endpoint..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://glamlux.com/wp-json/glamlux/v1/health)
if [ "$STATUS" -ne 200 ]; then
    echo "    ❌ Health check failed (HTTP $STATUS)"
    exit 1
fi
echo "    ✅ Health check OK"

# Test 2: Homepage
echo "  Testing homepage..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://glamlux.com)
if [ "$STATUS" -ne 200 ]; then
    echo "    ❌ Homepage failed (HTTP $STATUS)"
    exit 1
fi
echo "    ✅ Homepage OK"

# Test 3: API endpoint
echo "  Testing API..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://glamlux.com/wp-json/glamlux/v1/salons)
if [ "$STATUS" -ne 200 ] && [ "$STATUS" -ne 401 ]; then
    echo "    ❌ API failed (HTTP $STATUS)"
    exit 1
fi
echo "    ✅ API OK"

# Test 4: Database
echo "  Testing database..."
WP_CLI_SSH_HOST=glamlux.railway.app wp db check
if [ $? -ne 0 ]; then
    echo "    ❌ Database check failed"
    exit 1
fi
echo "    ✅ Database OK"

echo ""
echo "✅ All smoke tests passed!"
```

---

### Task 8: Operational Dashboard (2 hours)

**File**: `wp-content/plugins/glamlux-core/admin/pages/class-operations-dashboard.php` (NEW)

```php
class GlamLux_Operations_Dashboard {
    
    public static function render() {
        echo '<div class="operations-dashboard">';
        echo '<h1>Operations Dashboard</h1>';
        
        // System Health
        self::render_health_status();
        
        // Performance Metrics
        self::render_performance_metrics();
        
        // Recent Errors
        self::render_recent_errors();
        
        // Audit Log
        self::render_audit_log();
        
        // Database Stats
        self::render_database_stats();
        
        echo '</div>';
    }
    
    private static function render_health_status() {
        $health = self::get_health_status();
        
        echo '<div class="health-status">';
        echo '<h2>System Health</h2>';
        echo '<div class="status-grid">';
        
        foreach ($health['checks'] as $name => $check) {
            $icon = $check['status'] === 'ok' ? '✅' : '❌';
            echo "<div class='status-card'>";
            echo "<h3>$icon $name</h3>";
            echo "<p>Response: {$check['response_time']}ms</p>";
            echo "</div>";
        }
        
        echo '</div></div>';
    }
    
    private static function render_performance_metrics() {
        $metrics = GlamLux_Performance::get_metrics();
        
        echo '<div class="performance-metrics">';
        echo '<h2>Performance</h2>';
        echo '<table>';
        foreach ($metrics as $key => $value) {
            echo "<tr><td>$key</td><td>$value</td></tr>";
        }
        echo '</table>';
        echo '</div>';
    }
    
    private static function render_recent_errors() {
        global $wpdb;
        
        $errors = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}glamlux_logs WHERE level='error' ORDER BY timestamp DESC LIMIT 10"
        );
        
        echo '<div class="recent-errors">';
        echo '<h2>Recent Errors</h2>';
        echo '<table>';
        foreach ($errors as $error) {
            echo "<tr><td>{$error->timestamp}</td><td>{$error->message}</td></tr>";
        }
        echo '</table>';
        echo '</div>';
    }
    
    private static function render_audit_log() {
        // Similar to errors, but all events
    }
    
    private static function render_database_stats() {
        // Database size, table count, row counts
    }
}
```

---

### Task 9: Incident Response Procedures (1 hour)

**File**: `docs/incident-response-procedures.md` (NEW)

```markdown
# Incident Response Procedures

## P0 - Critical (System Down)

### Detection
- Health check endpoint returns 500
- Multiple errors in Sentry
- Slack alert triggered

### Response (5 min SLA)
1. **Immediately**: Notify on-call team
2. **1 min**: Check system health dashboard
3. **2 min**: Check database status
4. **3 min**: Restart services if needed
5. **4 min**: Verify services are responding
6. **5 min**: Notify users

### Escalation
- If not resolved in 5 min, escalate to DevOps
- If database issue, escalate to DBA
- If code issue, escalate to eng lead

## P1 - High (Degraded Performance)

### Response (15 min SLA)
1. Check performance dashboard
2. Identify bottleneck (DB/API/Cache)
3. Apply optimization
4. Monitor improvement
5. Post-incident review

## P2 - Medium (Minor Issues)

### Response (1 hour SLA)
1. Document issue
2. Schedule fix
3. Implement fix
4. Test fix
5. Deploy fix

## P3 - Low (Feature Requests)

### Response (Next sprint)
1. Add to backlog
2. Prioritize
3. Schedule
4. Implement
5. Deploy
```

---

### Task 10: Alerting System (1.5 hours)

**File**: `wp-content/plugins/glamlux-core/includes/class-glamlux-alerts.php` (NEW)

```php
class GlamLux_Alerts {
    
    public static function check_thresholds() {
        // Check performance
        if (self::get_page_load_time() > 2.0) {
            self::send_alert('PERFORMANCE', 'Page load time exceeded 2 seconds');
        }
        
        // Check memory
        $memory_percent = (memory_get_usage(true) / WP_MEMORY_LIMIT) * 100;
        if ($memory_percent > 80) {
            self::send_alert('MEMORY', "Memory usage at ${memory_percent}%");
        }
        
        // Check errors
        $recent_errors = self::count_recent_errors(300); // Last 5 min
        if ($recent_errors > 10) {
            self::send_alert('ERRORS', "$recent_errors errors in last 5 minutes");
        }
        
        // Check database
        if (self::is_database_slow()) {
            self::send_alert('DATABASE', 'Database queries slow');
        }
        
        // Check cron
        if (self::is_cron_stuck()) {
            self::send_alert('CRON', 'Scheduled tasks not running');
        }
    }
    
    private static function send_alert($type, $message) {
        // Log to Sentry
        Sentry\captureMessage($message, 'warning');
        
        // Send Slack notification
        self::send_slack_alert($type, $message);
        
        // Send email
        self::send_email_alert($type, $message);
        
        // Store in database
        self::store_alert($type, $message);
    }
    
    private static function send_slack_alert($type, $message) {
        $webhook_url = get_option('glamlux_slack_webhook');
        if (!$webhook_url) return;
        
        $color = [
            'CRITICAL' => '#FF0000',
            'ERROR' => '#FF6B6B',
            'WARNING' => '#FFA500',
            'INFO' => '#0099FF',
        ];
        
        wp_remote_post($webhook_url, [
            'body' => wp_json_encode([
                'attachments' => [[
                    'color' => $color[$type] ?? '#999999',
                    'title' => $type,
                    'text' => $message,
                    'ts' => time(),
                ]]
            ])
        ]);
    }
}
```

---

## 🎯 Phase 2 Testing Checklist

```
✅ Health check endpoint returns correct data
✅ Logging captures all important events
✅ Performance monitoring tracks metrics
✅ Error tracking sends to Sentry
✅ Database optimization improves queries
✅ Cache strategy reduces queries
✅ Deployment automation works
✅ Smoke tests pass
✅ Alerts trigger correctly
✅ Dashboard displays real-time data
```

---

## 📊 Phase 2 Success Metrics

| Metric | Before | After | Target |
|---|---|---|---|
| Error Detection | Manual | Automatic | Real-time |
| MTTR (Mean Time To Repair) | 30+ min | 5-10 min | < 5 min |
| Logging Coverage | Partial | Complete | 100% |
| Performance Visibility | None | Dashboard | Real-time |
| Deployment Time | Manual | Automated | < 5 min |
| Incident Response | Ad-hoc | Procedures | Documented |

---

## ⏱️ Phase 2 Timeline

```
Days 1-2 (4 hours): Health & Logging
Days 3-4 (4 hours): Performance & Monitoring
Days 5-6 (2 hours): Database & Cache
Days 7-8 (2 hours): Deployment & Alerts
```

**Total: 12 hours**

---

## 🚀 Phase 2 Deliverables

1. ✅ Health check endpoint
2. ✅ Centralized logging system
3. ✅ Performance monitoring
4. ✅ Error tracking (Sentry)
5. ✅ Database optimization scripts
6. ✅ Caching strategy
7. ✅ Automated deployment
8. ✅ Operations dashboard
9. ✅ Incident response procedures
10. ✅ Alerting system

---

**Status**: 🔄 READY FOR PHASE 2  
**Next**: Implement all components above  
**Estimated Completion**: 2-3 days
