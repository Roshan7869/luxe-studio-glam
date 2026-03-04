<?php
/**
 * Performance Monitoring System
 * 
 * Tracks page load times, database queries, and performance metrics
 * 
 * @package GlamLux_Core
 * @subpackage Includes
 */

class GlamLux_Performance {
    
    private static $start_time = null;
    private static $queries = [];
    private static $hooks = [];
    
    /**
     * Initialize performance tracking
     */
    public static function init() {
        if ( null === self::$start_time ) {
            self::$start_time = microtime( true );
        }
        
        // Track template redirect
        add_action( 'template_redirect', [ __CLASS__, 'track_page_load' ], 999 );
        
        // Track database queries
        add_action( 'wp_footer', [ __CLASS__, 'log_performance' ], 999 );
        
        // Register daily cleanup
        add_action( 'glamlux_cleanup_performance_logs', [ __CLASS__, 'cleanup_old_metrics' ] );
    }
    
    /**
     * Track page load
     */
    public static function track_page_load() {
        if ( ! isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ) {
            return;
        }
        
        $duration = microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'];
        
        // Log slow pages
        if ( $duration > 1.0 ) {
            GlamLux_Logger::warning( 'Slow page load detected', [
                'duration'       => round( $duration, 3 ) . 's',
                'page'           => $_SERVER['REQUEST_URI'],
                'method'         => $_SERVER['REQUEST_METHOD'],
                'memory_usage'   => round( memory_get_usage( true ) / 1024 / 1024, 2 ) . 'MB',
                'memory_peak'    => round( memory_get_peak_usage( true ) / 1024 / 1024, 2 ) . 'MB',
            ] );
        }
    }
    
    /**
     * Log performance metrics
     */
    public static function log_performance() {
        $metrics = self::get_metrics();
        
        // Store in database
        self::store_metrics( $metrics );
        
        // Send to monitoring service if slow
        if ( $metrics['page_load_time'] > 2.0 ) {
            self::alert_slow_page( $metrics );
        }
    }
    
    /**
     * Get current metrics
     */
    public static function get_metrics() {
        global $wpdb;
        
        $page_load_time = microtime( true ) - ( $_SERVER['REQUEST_TIME_FLOAT'] ?? time() );
        
        return [
            'page_load_time'    => round( $page_load_time, 3 ),
            'memory_usage'      => round( memory_get_usage( true ) / 1024 / 1024, 2 ),
            'memory_peak'       => round( memory_get_peak_usage( true ) / 1024 / 1024, 2 ),
            'database_queries'  => $wpdb->num_queries,
            'database_time'     => isset( $wpdb->query_time ) ? round( $wpdb->query_time, 3 ) : 0,
            'cache_hits'        => self::get_cache_hits(),
            'wp_cache_enabled'  => defined( 'WP_CACHE' ) && WP_CACHE ? 'yes' : 'no',
            'timestamp'         => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'endpoint'          => $_SERVER['REQUEST_URI'] ?? '',
            'method'            => $_SERVER['REQUEST_METHOD'] ?? '',
        ];
    }
    
    /**
     * Get cache hit count
     */
    private static function get_cache_hits() {
        if ( function_exists( 'wp_cache_get_stats' ) ) {
            $stats = wp_cache_get_stats();
            return isset( $stats['hits'] ) ? $stats['hits'] : 0;
        }
        return 0;
    }
    
    /**
     * Store metrics in database
     */
    private static function store_metrics( $metrics ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'glamlux_performance';
        
        // Create table if not exists
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            self::create_performance_table();
        }
        
        $wpdb->insert(
            $table,
            [
                'timestamp'           => current_time( 'mysql', true ),
                'page_load_time'      => $metrics['page_load_time'],
                'memory_usage'        => $metrics['memory_usage'],
                'memory_peak'         => $metrics['memory_peak'],
                'database_queries'    => $metrics['database_queries'],
                'database_time'       => $metrics['database_time'],
                'cache_hits'          => $metrics['cache_hits'],
                'wp_cache_enabled'    => $metrics['wp_cache_enabled'],
                'endpoint'            => $metrics['endpoint'],
                'method'              => $metrics['method'],
            ],
            [
                '%s', '%f', '%f', '%f', '%d', '%f', '%d', '%s', '%s', '%s',
            ]
        );
    }
    
    /**
     * Alert for slow pages
     */
    private static function alert_slow_page( $metrics ) {
        GlamLux_Logger::warning( 'Slow page performance', [
            'page_load_time'   => $metrics['page_load_time'] . 's',
            'memory_usage'     => $metrics['memory_usage'] . 'MB',
            'database_queries' => $metrics['database_queries'],
            'endpoint'         => $metrics['endpoint'],
        ] );
    }
    
    /**
     * Create performance table
     */
    private static function create_performance_table() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'glamlux_performance';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            timestamp DATETIME NOT NULL,
            page_load_time FLOAT NOT NULL,
            memory_usage FLOAT NOT NULL,
            memory_peak FLOAT NOT NULL,
            database_queries INT NOT NULL,
            database_time FLOAT NOT NULL,
            cache_hits INT NOT NULL,
            wp_cache_enabled VARCHAR(3),
            endpoint VARCHAR(500),
            method VARCHAR(10),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY endpoint (endpoint),
            KEY page_load_time (page_load_time)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
    
    /**
     * Get average metrics
     */
    public static function get_average_metrics( $hours = 24 ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'glamlux_performance';
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $hours * HOUR_IN_SECONDS ) );
        
        $results = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    AVG(page_load_time) as avg_page_load,
                    AVG(memory_usage) as avg_memory,
                    AVG(database_queries) as avg_queries,
                    MAX(page_load_time) as max_page_load,
                    MIN(page_load_time) as min_page_load,
                    COUNT(*) as total_requests
                FROM $table
                WHERE timestamp >= %s",
                $cutoff
            )
        );
        
        return [
            'avg_page_load'    => round( $results->avg_page_load, 3 ),
            'avg_memory'       => round( $results->avg_memory, 2 ),
            'avg_queries'      => round( $results->avg_queries, 0 ),
            'max_page_load'    => round( $results->max_page_load, 3 ),
            'min_page_load'    => round( $results->min_page_load, 3 ),
            'total_requests'   => intval( $results->total_requests ),
            'period_hours'     => $hours,
        ];
    }
    
    /**
     * Get performance by endpoint
     */
    public static function get_metrics_by_endpoint( $limit = 20 ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'glamlux_performance';
        
        return $wpdb->get_results(
            "SELECT 
                endpoint,
                method,
                AVG(page_load_time) as avg_time,
                MAX(page_load_time) as max_time,
                COUNT(*) as requests
            FROM $table
            GROUP BY endpoint, method
            ORDER BY avg_time DESC
            LIMIT $limit"
        );
    }
    
    /**
     * Cleanup old performance metrics
     */
    public static function cleanup_old_metrics( $days = 7 ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'glamlux_performance';
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE timestamp < %s",
                $cutoff
            )
        );
    }
}
