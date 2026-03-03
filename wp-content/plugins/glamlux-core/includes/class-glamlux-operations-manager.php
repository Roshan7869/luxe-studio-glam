<?php
/**
 * GlamLux Operations Manager - Phase 2
 * Comprehensive operations, logging, and monitoring system
 */

class GlamLux_Operations_Manager {
    
    /**
     * Initialize operations system
     */
    public static function init() {
        // Create logging tables on activation
        add_action('plugins_loaded', [__CLASS__, 'create_tables'], 1);
        
        // Track performance
        add_action('init', [__CLASS__, 'start_performance_tracking']);
        add_action('wp_footer', [__CLASS__, 'end_performance_tracking']);
        
        // Log important actions
        add_action('rest_api_init', [__CLASS__, 'log_api_access']);
        add_action('wp_login', [__CLASS__, 'log_user_login']);
        add_action('wp_logout', [__CLASS__, 'log_user_logout']);
    }
    
    /**
     * Create operations tables
     */
    public static function create_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Audit log table
        $audit_log = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}glamlux_audit_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            event_type varchar(50) NOT NULL,
            user_id bigint(20),
            object_type varchar(50),
            object_id bigint(20),
            old_value longtext,
            new_value longtext,
            ip_address varchar(45),
            user_agent longtext,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        // Performance metrics table
        $perf_metrics = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}glamlux_performance (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            endpoint varchar(255),
            method varchar(10),
            response_time_ms float,
            memory_used_mb float,
            database_queries int,
            database_time_ms float,
            cache_hits int,
            cache_misses int,
            status_code int,
            user_id bigint(20),
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY endpoint (endpoint),
            KEY response_time (response_time_ms)
        ) $charset_collate;";
        
        // Error log table
        $error_log = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}glamlux_errors (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            level varchar(20),
            message longtext,
            code varchar(50),
            file varchar(255),
            line int,
            context longtext,
            trace longtext,
            user_id bigint(20),
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY level (level),
            KEY timestamp (timestamp),
            KEY code (code)
        ) $charset_collate;";
        
        // Alerts table
        $alerts = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}glamlux_alerts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            alert_type varchar(50),
            severity varchar(20),
            title varchar(255),
            message longtext,
            status varchar(20),
            resolved_at datetime,
            PRIMARY KEY (id),
            KEY alert_type (alert_type),
            KEY severity (severity),
            KEY timestamp (timestamp),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($audit_log);
        dbDelta($perf_metrics);
        dbDelta($error_log);
        dbDelta($alerts);
    }
    
    /**
     * Start performance tracking
     */
    public static function start_performance_tracking() {
        global $glamlux_perf;
        $glamlux_perf = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
        ];
    }
    
    /**
     * End performance tracking and log metrics
     */
    public static function end_performance_tracking() {
        global $wpdb, $glamlux_perf;
        
        if (!isset($glamlux_perf['start_time'])) {
            return;
        }
        
        $response_time = (microtime(true) - $glamlux_perf['start_time']) * 1000;
        $memory_used = (memory_get_usage(true) - $glamlux_perf['start_memory']) / 1024 / 1024;
        
        // Only log slow requests (> 500ms)
        if ($response_time > 500) {
            $wpdb->insert($wpdb->prefix . 'glamlux_performance', [
                'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                'response_time_ms' => $response_time,
                'memory_used_mb' => $memory_used,
                'database_queries' => $wpdb->num_queries,
                'status_code' => http_response_code(),
                'user_id' => get_current_user_id(),
            ]);
        }
    }
    
    /**
     * Log API access
     */
    public static function log_api_access() {
        if (!defined('REST_REQUEST')) {
            return;
        }
        
        global $wp;
        
        add_action('rest_dispatch', function($result) {
            global $wpdb;
            
            $wpdb->insert($wpdb->prefix . 'glamlux_audit_log', [
                'event_type' => 'api_access',
                'user_id' => get_current_user_id(),
                'object_type' => 'api_endpoint',
                'object_id' => 0,
                'new_value' => wp_json_encode([
                    'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
                    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                    'ip_address' => self::get_client_ip(),
                ]),
                'ip_address' => self::get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            
            return $result;
        }, 10, 1);
    }
    
    /**
     * Log user login
     */
    public static function log_user_login($user_login, $user = null) {
        global $wpdb;
        
        $wpdb->insert($wpdb->prefix . 'glamlux_audit_log', [
            'event_type' => 'user_login',
            'user_id' => $user ? $user->ID : 0,
            'object_type' => 'user',
            'object_id' => $user ? $user->ID : 0,
            'new_value' => wp_json_encode([
                'username' => $user_login,
                'role' => $user ? implode(',', $user->roles) : '',
            ]),
            'ip_address' => self::get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    }
    
    /**
     * Log user logout
     */
    public static function log_user_logout() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        if ($user_id > 0) {
            $wpdb->insert($wpdb->prefix . 'glamlux_audit_log', [
                'event_type' => 'user_logout',
                'user_id' => $user_id,
                'object_type' => 'user',
                'object_id' => $user_id,
                'ip_address' => self::get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
        }
    }
    
    /**
     * Log error
     */
    public static function log_error($message, $level = 'error', $code = null, $context = []) {
        global $wpdb;
        
        $trace = debug_backtrace();
        $file = $trace[1]['file'] ?? '';
        $line = $trace[1]['line'] ?? 0;
        
        $wpdb->insert($wpdb->prefix . 'glamlux_errors', [
            'level' => $level,
            'message' => $message,
            'code' => $code,
            'file' => $file,
            'line' => $line,
            'context' => wp_json_encode($context),
            'trace' => wp_json_encode(array_slice($trace, 0, 5)),
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_client_ip(),
        ]);
        
        // Send alert if critical
        if ($level === 'critical') {
            self::send_alert('ERROR', $level, $message);
        }
    }
    
    /**
     * Create alert
     */
    public static function send_alert($type, $severity, $message, $title = null) {
        global $wpdb;
        
        $wpdb->insert($wpdb->prefix . 'glamlux_alerts', [
            'alert_type' => $type,
            'severity' => $severity,
            'title' => $title ?? substr($message, 0, 100),
            'message' => $message,
            'status' => 'open',
        ]);
        
        // Send Slack notification if configured
        self::notify_slack($type, $severity, $title ?? $message, $message);
        
        // Send email notification
        self::notify_email($type, $severity, $title ?? $message, $message);
    }
    
    /**
     * Send Slack notification
     */
    private static function notify_slack($type, $severity, $title, $message) {
        $webhook = get_option('glamlux_slack_webhook');
        if (!$webhook) {
            return;
        }
        
        $color_map = [
            'critical' => '#FF0000',
            'error' => '#FF6B6B',
            'warning' => '#FFA500',
            'info' => '#0099FF',
        ];
        
        wp_remote_post($webhook, [
            'body' => wp_json_encode([
                'attachments' => [[
                    'color' => $color_map[$severity] ?? '#999999',
                    'title' => "$type - $severity",
                    'text' => $title,
                    'fields' => [
                        [
                            'title' => 'Details',
                            'value' => $message,
                            'short' => false,
                        ]
                    ],
                    'ts' => time(),
                ]]
            ]),
            'headers' => ['Content-Type' => 'application/json'],
        ]);
    }
    
    /**
     * Send email notification
     */
    private static function notify_email($type, $severity, $title, $message) {
        $email = get_option('glamlux_ops_email');
        if (!$email || $severity === 'info') {
            return; // Don't email info level alerts
        }
        
        wp_mail(
            $email,
            "[GlamLux] $type - $severity: $title",
            $message,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }
    
    /**
     * Get client IP address
     */
    public static function get_client_ip() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        return sanitize_text_field(trim($ip));
    }
    
    /**
     * Get performance summary
     */
    public static function get_performance_summary($hours = 24) {
        global $wpdb;
        
        $time_back = date('Y-m-d H:i:s', strtotime("-$hours hours"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                AVG(response_time_ms) as avg_response_time,
                MAX(response_time_ms) as max_response_time,
                MIN(response_time_ms) as min_response_time,
                AVG(memory_used_mb) as avg_memory,
                COUNT(*) as total_requests,
                COUNT(DISTINCT user_id) as unique_users
            FROM {$wpdb->prefix}glamlux_performance
            WHERE timestamp >= %s",
            $time_back
        ));
        
        return $results[0] ?? [];
    }
    
    /**
     * Get error summary
     */
    public static function get_error_summary($hours = 24) {
        global $wpdb;
        
        $time_back = date('Y-m-d H:i:s', strtotime("-$hours hours"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT level, COUNT(*) as count
            FROM {$wpdb->prefix}glamlux_errors
            WHERE timestamp >= %s
            GROUP BY level",
            $time_back
        ));
        
        return $results;
    }
}

// Initialize operations manager
add_action('plugins_loaded', [GlamLux_Operations_Manager::class, 'init']);
