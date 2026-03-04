<?php
/**
 * Alerting System
 * 
 * Monitors system health and sends alerts via multiple channels
 * 
 * @package GlamLux_Core
 * @subpackage Includes
 */

class GlamLux_Alerts {
    
    // Alert severity levels
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_CRITICAL = 'critical';
    
    /**
     * Initialize alerting system
     */
    public static function init() {
        // Register health check cron
        add_action( 'glamlux_check_health_thresholds', [ __CLASS__, 'check_thresholds' ] );
    }
    
    /**
     * Check system health thresholds
     */
    public static function check_thresholds() {
        // Check performance
        self::check_performance();
        
        // Check memory
        self::check_memory();
        
        // Check errors
        self::check_error_rate();
        
        // Check database
        self::check_database_health();
        
        // Check event queue
        self::check_event_queue();
        
        // Check job queue
        self::check_job_queue();
    }
    
    /**
     * Check page performance
     */
    private static function check_performance() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'glamlux_performance';
        $last_hour = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
        
        $avg_time = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(page_load_time) FROM $table WHERE timestamp > %s",
                $last_hour
            )
        );
        
        if ( $avg_time > 2.0 ) {
            self::send_alert(
                self::SEVERITY_WARNING,
                'High Average Page Load Time',
                sprintf(
                    'Average page load time is %.2fs (threshold: 2.0s)',
                    $avg_time
                )
            );
        }
    }
    
    /**
     * Check memory usage
     */
    private static function check_memory() {
        $memory_usage = memory_get_usage( true );
        $memory_limit = wp_convert_hr_to_bytes( WP_MEMORY_LIMIT );
        $percent = ( $memory_usage / $memory_limit ) * 100;
        
        if ( $percent > 90 ) {
            self::send_alert(
                self::SEVERITY_CRITICAL,
                'Critical Memory Usage',
                sprintf(
                    'Memory usage at %.2f%% of limit (%.2fMB / %.2fMB)',
                    $percent,
                    $memory_usage / 1024 / 1024,
                    $memory_limit / 1024 / 1024
                )
            );
        } elseif ( $percent > 80 ) {
            self::send_alert(
                self::SEVERITY_WARNING,
                'High Memory Usage',
                sprintf(
                    'Memory usage at %.2f%% of limit',
                    $percent
                )
            );
        }
    }
    
    /**
     * Check error rate
     */
    private static function check_error_rate() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'glamlux_logs';
        $last_5_min = gmdate( 'Y-m-d H:i:s', time() - ( 5 * MINUTE_IN_SECONDS ) );
        
        $error_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table 
                WHERE level IN ('ERROR', 'CRITICAL') 
                AND timestamp > %s",
                $last_5_min
            )
        );
        
        if ( $error_count > 10 ) {
            self::send_alert(
                self::SEVERITY_CRITICAL,
                'High Error Rate',
                sprintf(
                    '%d errors in last 5 minutes',
                    $error_count
                )
            );
        } elseif ( $error_count > 5 ) {
            self::send_alert(
                self::SEVERITY_WARNING,
                'Elevated Error Rate',
                sprintf(
                    '%d errors in last 5 minutes',
                    $error_count
                )
            );
        }
    }
    
    /**
     * Check database health
     */
    private static function check_database_health() {
        global $wpdb;
        
        // Check connection
        if ( ! $wpdb->check_connection() ) {
            self::send_alert(
                self::SEVERITY_CRITICAL,
                'Database Connection Failed',
                'Unable to connect to database'
            );
            return;
        }
        
        // Check slow queries
        $table = $wpdb->prefix . 'glamlux_performance';
        $last_hour = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
        
        $slow_queries = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table 
                WHERE database_time > 0.5 
                AND timestamp > %s",
                $last_hour
            )
        );
        
        if ( $slow_queries > 100 ) {
            self::send_alert(
                self::SEVERITY_WARNING,
                'Many Slow Database Queries',
                sprintf(
                    '%d queries slower than 500ms in last hour',
                    $slow_queries
                )
            );
        }
    }
    
    /**
     * Check event queue
     */
    private static function check_event_queue() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gl_event_queue';
        
        $pending = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='pending'" );
        $failed = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='failed'" );
        
        if ( $failed > 100 ) {
            self::send_alert(
                self::SEVERITY_CRITICAL,
                'High Event Queue Failures',
                sprintf( '%d failed events in queue', $failed )
            );
        } elseif ( $pending > 1000 ) {
            self::send_alert(
                self::SEVERITY_WARNING,
                'Event Queue Backlog',
                sprintf( '%d pending events', $pending )
            );
        }
    }
    
    /**
     * Check job queue
     */
    private static function check_job_queue() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gl_job_queue';
        
        $pending = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='pending'" );
        $failed = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='failed'" );
        
        if ( $failed > 50 ) {
            self::send_alert(
                self::SEVERITY_CRITICAL,
                'High Job Queue Failures',
                sprintf( '%d failed jobs', $failed )
            );
        } elseif ( $pending > 500 ) {
            self::send_alert(
                self::SEVERITY_WARNING,
                'Job Queue Backlog',
                sprintf( '%d pending jobs', $pending )
            );
        }
    }
    
    /**
     * Send alert to all channels
     */
    private static function send_alert( $severity, $title, $message ) {
        // Log alert
        GlamLux_Logger::log(
            $severity === self::SEVERITY_CRITICAL ? 'critical' : 'warning',
            $title,
            [ 'message' => $message ]
        );
        
        // Send to Slack
        self::send_slack_alert( $severity, $title, $message );
        
        // Send email for critical
        if ( self::SEVERITY_CRITICAL === $severity ) {
            self::send_email_alert( $title, $message );
        }
        
        // Store in database
        self::store_alert( $severity, $title, $message );
    }
    
    /**
     * Send Slack notification
     */
    private static function send_slack_alert( $severity, $title, $message ) {
        $webhook_url = get_option( 'glamlux_slack_webhook' );
        if ( ! $webhook_url ) {
            return;
        }
        
        $color_map = [
            self::SEVERITY_INFO     => '#0099FF',
            self::SEVERITY_WARNING  => '#FFA500',
            self::SEVERITY_CRITICAL => '#FF0000',
        ];
        
        $payload = [
            'attachments' => [
                [
                    'color'  => $color_map[ $severity ] ?? '#999999',
                    'title'  => $title,
                    'text'   => $message,
                    'ts'     => time(),
                    'fields' => [
                        [
                            'title' => 'Severity',
                            'value' => ucfirst( $severity ),
                            'short' => true,
                        ],
                        [
                            'title' => 'Time',
                            'value' => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
                            'short' => true,
                        ],
                    ],
                ],
            ],
        ];
        
        wp_remote_post(
            $webhook_url,
            [
                'body'    => wp_json_encode( $payload ),
                'headers' => [ 'Content-Type' => 'application/json' ],
                'timeout' => 5,
            ]
        );
    }
    
    /**
     * Send email alert
     */
    private static function send_email_alert( $title, $message ) {
        $admin_email = get_option( 'admin_email' );
        $subject = '[GlamLux Alert] ' . $title;
        $body = $message . "\n\nSite: " . get_bloginfo( 'url' );
        
        wp_mail( $admin_email, $subject, $body );
    }
    
    /**
     * Store alert in database
     */
    private static function store_alert( $severity, $title, $message ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'glamlux_alerts';
        
        // Create table if not exists
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            self::create_alerts_table();
        }
        
        $wpdb->insert(
            $table,
            [
                'severity' => $severity,
                'title'    => $title,
                'message'  => $message,
                'created_at' => current_time( 'mysql', true ),
            ],
            [ '%s', '%s', '%s', '%s' ]
        );
    }
    
    /**
     * Create alerts table
     */
    private static function create_alerts_table() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'glamlux_alerts';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            severity VARCHAR(20) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_resolved BOOLEAN DEFAULT FALSE,
            created_at DATETIME NOT NULL,
            resolved_at DATETIME,
            PRIMARY KEY (id),
            KEY severity (severity),
            KEY created_at (created_at),
            KEY is_resolved (is_resolved)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
    
    /**
     * Get recent alerts
     */
    public static function get_recent_alerts( $limit = 20 ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'glamlux_alerts';
        
        return $wpdb->get_results(
            "SELECT * FROM $table 
            WHERE is_resolved = FALSE 
            ORDER BY created_at DESC 
            LIMIT $limit"
        );
    }
}
