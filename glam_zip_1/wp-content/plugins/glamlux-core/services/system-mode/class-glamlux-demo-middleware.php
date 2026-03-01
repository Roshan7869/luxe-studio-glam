<?php
/**
 * GlamLux Demo Mode Middleware
 *
 * LAYER: Middleware / Infrastructure
 * PURPOSE: Makes the system lightning fast by intercepting and short-circuiting
 *          heavy database queries, external API calls, and crons.
 */
class GlamLux_Demo_Middleware
{

    public static function init(): void
    {
        if (!GlamLux_System_Mode::is_demo()) {
            return;
        }

        $instance = new self();

        // 2.1 Cron Gating
        add_filter('glamlux_allow_cron_execution', '__return_false');

        // 2.2 Email Interception
        add_filter('pre_wp_mail', [$instance, 'intercept_emails'], 10, 2);

        // 2.3 Force Metrics Cache
        add_filter('pre_transient_gl_revenue_summary', [$instance, 'mock_revenue_summary']);
        add_filter('pre_transient_gl_active_salons', [$instance, 'mock_active_salons']);

        // 2.4 REST GET Cache Headers
        add_filter('rest_post_dispatch', [$instance, 'add_rest_cache_headers'], 10, 3);

        // 2.5 Disable External API Calls (Stripe, Razorpay, SMS)
        add_filter('glamlux_pre_payment_gateway_charge', [$instance, 'mock_payment_charge'], 10, 3);
    }

    /**
     * 2.2 Replaces genuine email sending with a log entry.
     */
    public function intercept_emails($null, $args)
    {
        if (class_exists('GlamLux_Logger')) {
            GlamLux_Logger::info('demo_mode', "Simulation bypass: wp_mail intercepted for {$args['to']}");
        }
        // Returning true short-circuits wp_mail()
        return true;
    }

    /**
     * 2.3 Force return metrics for instant dashboard load without SQL aggregations
     */
    public function mock_revenue_summary($pretransient)
    {
        return [
            'revenue' => 125430.50,
            'bookings' => 1420,
            'new_clients' => 350,
            'avg_booking_value' => 88.33,
        ];
    }

    public function mock_active_salons($pretransient)
    {
        // Mock array of locations to bypass franchise table scans during demo
        return [
            ['id' => 1, 'name' => 'Luxe Studio - Downtown', 'city' => 'New York', 'is_active' => 1],
            ['id' => 2, 'name' => 'Luxe Studio - Westside', 'city' => 'Los Angeles', 'is_active' => 1],
            ['id' => 3, 'name' => 'Luxe Studio - Gold Coast', 'city' => 'Chicago', 'is_active' => 1],
            ['id' => 4, 'name' => 'Luxe Studio - Marina', 'city' => 'Miami', 'is_active' => 1],
            ['id' => 5, 'name' => 'Luxe Studio - Midtown', 'city' => 'Atlanta', 'is_active' => 1],
        ];
    }

    /**
     * 2.4 Force browser and edge caching for REST API GET requests during demo mode.
     */
    public function add_rest_cache_headers($result, $server, $request)
    {
        if ($request->get_method() === 'GET') {
            $route = $request->get_route();
            if (strpos($route, '/glamlux/v1/') === 0) {
                $server->send_header('Cache-Control', 'public, max-age=300');
            }
        }
        return $result;
    }

    /**
     * 2.5 Force success on payment orchestration without hitting APIs
     */
    public function mock_payment_charge($null, $amount, $gateway)
    {
        if (class_exists('GlamLux_Logger')) {
            GlamLux_Logger::info('demo_mode', "Simulation bypass: {$gateway} charge mocked for amount {$amount}");
        }
        return 'demo_txn_' . uniqid();
    }
}
