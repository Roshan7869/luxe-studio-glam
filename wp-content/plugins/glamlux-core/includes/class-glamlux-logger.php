<?php
/**
 * Centralized Logging System
 * 
 * Structured logging with multiple outputs (file, database, Sentry)
 * 
 * @package GlamLux_Core
 * @subpackage Core
 */

class GlamLux_Logger {
    
    // Log levels
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    
    // Log levels priority
    private static $log_levels = [
        'debug'    => 0,
        'info'     => 1,
        'warning'  => 2,
        'error'    => 3,
        'critical' => 4,
    ];
    
    /**
     * Log message
     */
    public static function log( $level, $message, $context = [] ) {
        // Normalize level
        $level = strtolower( $level );
        
        // Build log entry
        $entry = [
            'timestamp'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'level'        => strtoupper( $level ),
            'message'      => $message,
            'context'      => $context,
            'user_id'      => get_current_user_id(),
            'request_id'   => self::get_request_id(),
            'ip_address'   => self::get_client_ip(),
            'endpoint'     => $_SERVER['REQUEST_URI'] ?? '',
            'method'       => $_SERVER['REQUEST_METHOD'] ?? '',
            'memory'       => round( memory_get_usage( true ) / 1024 / 1024, 2 ) . 'MB',
        ];
        
        // Log to file
        self::log_to_file( $entry );
        
        // Log to database
        self::log_to_database( $entry );
        
        // Send to Sentry if critical/error
        if ( in_array( $level, [ 'error', 'critical' ], true ) ) {
            self::log_to_sentry( $entry );
        }
    }
    
    /**
     * Log debug message
     */
    public static function debug( $message, $context = [] ) {
        self::log( self::LEVEL_DEBUG, $message, $context );
    }
    
    /**
     * Log info message
     */
    public static function info( $message, $context = [] ) {
        self::log( self::LEVEL_INFO, $message, $context );
    }
    
    /**
     * Log warning
     */
    public static function warning( $message, $context = [] ) {
        self::log( self::LEVEL_WARNING, $message, $context );
    }
    
    /**
     * Log error
     */
    public static function error( $message, $context = [] ) {
        self::log( self::LEVEL_ERROR, $message, $context );
    }
    
    /**
     * Log critical error
     */
    public static function critical( $message, $context = [] ) {
        self::log( self::LEVEL_CRITICAL, $message, $context );
    }
    
    /**
     * Log to file
     */
    private static function log_to_file( $entry ) {
        $log_dir = WP_CONTENT_DIR . '/logs/glamlux';
        
        if ( ! is_dir( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
        }
        
        $log_file = $log_dir . '/' . gmdate( 'Y-m-d' ) . '.log';
        $log_line = wp_json_encode( $entry ) . "\n";
        
        error_log( $log_line, 3, $log_file );
    }
    
    /**
     * Log to database
     */
    private static function log_to_database( $entry ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'glamlux_logs';
        
        // Create table if not exists
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            self::create_logs_table();
        }
        
        $wpdb->insert(
            $table,
            [
                'timestamp'    => current_time( 'mysql', true ),
                'level'        => $entry['level'],
                'message'      => $entry['message'],
                'context'      => wp_json_encode( $entry['context'] ),
                'user_id'      => $entry['user_id'],
                'request_id'   => $entry['request_id'],
                'ip_address'   => $entry['ip_address'],
                'endpoint'     => $entry['endpoint'],
                'method'       => $entry['method'],
            ],
            [
                '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s',
            ]
        );
    }
    
    /**
     * Log to Sentry
     */
    private static function log_to_sentry( $entry ) {
        if ( ! function_exists( 'Sentry\captureException' ) && ! defined( 'SENTRY_DSN' ) ) {
            return;
        }
        
        \Sentry\captureMessage(
            $entry['message'],
            $entry['level'] === 'CRITICAL' ? 'fatal' : 'error'
        );
    }
    
    /**
     * Get request ID (for tracing)
     */
    private static function get_request_id() {
        if ( ! isset( $_SERVER['HTTP_X_REQUEST_ID'] ) ) {
            $_SERVER['HTTP_X_REQUEST_ID'] = uniqid( 'req_', true );
        }
        return sanitize_text_field( $_SERVER['HTTP_X_REQUEST_ID'] );
    }
    
    /**
     * Get client IP
     */
    private static function get_client_ip() {
        $ip = '';
        
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            $ip = sanitize_text_field( $_SERVER['HTTP_CF_CONNECTING_IP'] );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            $ip = trim( $ips[0] );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
        }
        
        return $ip;
    }
    
    /**
     * Create logs table
     */
    private static function create_logs_table() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'glamlux_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            timestamp DATETIME NOT NULL,
            level VARCHAR(20) NOT NULL,
            message LONGTEXT NOT NULL,
            context JSON,
            user_id BIGINT(20) UNSIGNED,
            request_id VARCHAR(255),
            ip_address VARCHAR(45),
            endpoint VARCHAR(500),
            method VARCHAR(10),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY timestamp (timestamp),
            KEY user_id (user_id),
            KEY request_id (request_id)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
    
    /**
     * Get recent logs
     */
    public static function get_recent_logs( $limit = 50, $level = null ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'glamlux_logs';
        
        $query = "SELECT * FROM $table";
        
        if ( $level ) {
            $query .= $wpdb->prepare( ' WHERE level = %s', $level );
        }
        
        $query .= ' ORDER BY timestamp DESC LIMIT %d';
        
        return $wpdb->get_results( $wpdb->prepare( $query, $limit ) );
    }
    
    /**
     * Cleanup old logs
     */
    public static function cleanup_old_logs( $days = 30 ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'glamlux_logs';
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE timestamp < %s",
                $cutoff
            )
        );
    }
}
