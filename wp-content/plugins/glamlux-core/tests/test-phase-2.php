<?php
/**
 * Phase 2 Tests - Operational Management
 * 
 * Test health check, logging, performance, and alerting systems
 * 
 * Run: wp scaffold post-type test --theme=twentytwentyone
 * Run: wp phpunit --group=phase2
 * 
 * @package GlamLux_Core
 * @subpackage Tests
 */

class Phase_2_Tests extends WP_UnitTestCase {
    
    /**
     * Test health check endpoint
     */
    public function test_health_endpoint() {
        $controller = new GlamLux_Health_Controller();
        $request = new WP_REST_Request( 'GET', '/glamlux/v1/health' );
        
        $response = $controller->get_health( $request );
        
        $this->assertInstanceOf( 'WP_REST_Response', $response );
        $this->assertEquals( 200, $response->get_status() );
        
        $data = $response->get_data();
        $this->assertArrayHasKey( 'status', $data );
        $this->assertArrayHasKey( 'timestamp', $data );
        $this->assertArrayHasKey( 'checks', $data );
    }
    
    /**
     * Test health check database
     */
    public function test_health_database_check() {
        global $wpdb;
        
        $controller = new GlamLux_Health_Controller();
        $request = new WP_REST_Request( 'GET', '/glamlux/v1/health' );
        
        $response = $controller->get_health( $request );
        $data = $response->get_data();
        
        $this->assertArrayHasKey( 'database', $data['checks'] );
        $this->assertEquals( 'ok', $data['checks']['database']['status'] );
    }
    
    /**
     * Test logger functionality
     */
    public function test_logger() {
        GlamLux_Logger::info( 'Test info message', [ 'test' => 'data' ] );
        
        global $wpdb;
        $table = $wpdb->prefix . 'glamlux_logs';
        
        $result = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $this->assertGreater( 0, $result );
    }
    
    /**
     * Test logger levels
     */
    public function test_logger_levels() {
        GlamLux_Logger::debug( 'Debug message' );
        GlamLux_Logger::info( 'Info message' );
        GlamLux_Logger::warning( 'Warning message' );
        GlamLux_Logger::error( 'Error message' );
        
        global $wpdb;
        $table = $wpdb->prefix . 'glamlux_logs';
        
        $error_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE level='ERROR'" );
        $this->assertGreater( 0, $error_count );
    }
    
    /**
     * Test performance tracking
     */
    public function test_performance_tracking() {
        // Simulate page load
        GlamLux_Performance::init();
        
        $metrics = GlamLux_Performance::get_metrics();
        
        $this->assertArrayHasKey( 'page_load_time', $metrics );
        $this->assertArrayHasKey( 'memory_usage', $metrics );
        $this->assertArrayHasKey( 'database_queries', $metrics );
    }
    
    /**
     * Test performance metrics storage
     */
    public function test_performance_storage() {
        GlamLux_Performance::init();
        
        // Get metrics and verify table creation
        global $wpdb;
        $table = $wpdb->prefix . 'glamlux_performance';
        
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table
            )
        );
        
        $this->assertEquals( $table, $exists );
    }
    
    /**
     * Test average metrics calculation
     */
    public function test_average_metrics() {
        GlamLux_Performance::init();
        
        $avg = GlamLux_Performance::get_average_metrics( 24 );
        
        $this->assertArrayHasKey( 'avg_page_load', $avg );
        $this->assertArrayHasKey( 'total_requests', $avg );
    }
    
    /**
     * Test alerts system
     */
    public function test_alerts_system() {
        GlamLux_Alerts::init();
        
        // Manually trigger threshold check
        GlamLux_Alerts::check_thresholds();
        
        global $wpdb;
        $table = $wpdb->prefix . 'glamlux_alerts';
        
        // Table should exist
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table
            )
        );
        
        $this->assertEquals( $table, $exists );
    }
    
    /**
     * Test alert storage
     */
    public function test_alert_storage() {
        global $wpdb;
        $table = $wpdb->prefix . 'glamlux_alerts';
        
        // Create table if not exists
        GlamLux_Alerts::check_thresholds();
        
        // Count alerts
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        
        $this->assertIsNumeric( $count );
    }
    
    /**
     * Test log retrieval
     */
    public function test_get_recent_logs() {
        GlamLux_Logger::info( 'Test message 1' );
        GlamLux_Logger::info( 'Test message 2' );
        
        $logs = GlamLux_Logger::get_recent_logs( 10 );
        
        $this->assertIsArray( $logs );
    }
    
    /**
     * Test health check redis (if available)
     */
    public function test_health_redis_check() {
        $controller = new GlamLux_Health_Controller();
        $request = new WP_REST_Request( 'GET', '/glamlux/v1/health' );
        
        $response = $controller->get_health( $request );
        $data = $response->get_data();
        
        $this->assertArrayHasKey( 'redis', $data['checks'] );
    }
    
    /**
     * Test health check memory
     */
    public function test_health_memory_check() {
        $controller = new GlamLux_Health_Controller();
        $request = new WP_REST_Request( 'GET', '/glamlux/v1/health' );
        
        $response = $controller->get_health( $request );
        $data = $response->get_data();
        
        $this->assertArrayHasKey( 'memory', $data['checks'] );
        $this->assertArrayHasKey( 'status', $data['checks']['memory'] );
    }
    
    /**
     * Test event queue status in health check
     */
    public function test_health_event_queue() {
        $controller = new GlamLux_Health_Controller();
        $request = new WP_REST_Request( 'GET', '/glamlux/v1/health' );
        
        $response = $controller->get_health( $request );
        $data = $response->get_data();
        
        $this->assertArrayHasKey( 'event_queue', $data['checks'] );
    }
    
    /**
     * Test job queue status in health check
     */
    public function test_health_job_queue() {
        $controller = new GlamLux_Health_Controller();
        $request = new WP_REST_Request( 'GET', '/glamlux/v1/health' );
        
        $response = $controller->get_health( $request );
        $data = $response->get_data();
        
        $this->assertArrayHasKey( 'job_queue', $data['checks'] );
    }
    
    /**
     * Test cache stats in health check
     */
    public function test_health_cache_stats() {
        $controller = new GlamLux_Health_Controller();
        $request = new WP_REST_Request( 'GET', '/glamlux/v1/health' );
        
        $response = $controller->get_health( $request );
        $data = $response->get_data();
        
        $this->assertArrayHasKey( 'cache', $data['checks'] );
    }
    
    /**
     * Test performance log cleanup
     */
    public function test_performance_cleanup() {
        GlamLux_Performance::cleanup_old_metrics( 0 );
        
        global $wpdb;
        $table = $wpdb->prefix . 'glamlux_performance';
        
        // Table should still exist
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table
            )
        );
        
        $this->assertEquals( $table, $exists );
    }
    
    /**
     * Test logger cleanup
     */
    public function test_logger_cleanup() {
        GlamLux_Logger::cleanup_old_logs( 0 );
        
        global $wpdb;
        $table = $wpdb->prefix . 'glamlux_logs';
        
        // Table should still exist
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table
            )
        );
        
        $this->assertEquals( $table, $exists );
    }
    
    /**
     * Test alert retrieval
     */
    public function test_get_recent_alerts() {
        GlamLux_Alerts::init();
        
        $alerts = GlamLux_Alerts::get_recent_alerts( 10 );
        
        $this->assertIsArray( $alerts );
    }
}
