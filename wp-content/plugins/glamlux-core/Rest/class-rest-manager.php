<?php
class GlamLux_REST_Manager
{
	public function __construct()
	{
		add_filter('rest_pre_dispatch', [$this, 'check_rate_limit'], 10, 3);
		add_filter('rest_post_dispatch', [$this, 'format_response_envelope'], 10, 3);
		add_filter('rest_post_dispatch', [$this, 'add_caching_headers'], 10, 3);
		add_action('rest_api_init', [$this, 'init_controllers']);
	}
	public function init_controllers()
	{
		// Core Booking Domain
		(new GlamLux_Salon_Controller())->register_routes();
		(new GlamLux_Service_Controller())->register_routes();
		(new GlamLux_Booking_Controller())->register_routes();

		// Staff Domain
		(new GlamLux_Staff_Controller())->register_routes();

		// CRM Domain
		(new GlamLux_Lead_Controller())->register_routes();

		// Reports Domain
		if (class_exists('GlamLux_Reports_Controller')) {
			(new GlamLux_Reports_Controller())->register_routes();
		}

		// Operations
		if (class_exists('GlamLux_Operations_Controller')) {
			(new GlamLux_Operations_Controller())->register_routes();
		}

		// Compliance & Observability
		(new GlamLux_GDPR_Controller())->register_routes();
		(new GlamLux_Health_Controller())->register_routes();

		// Visual Dataset & Gallery Data
		if (class_exists('GlamLux_Data_Controller')) {
			(new GlamLux_Data_Controller())->register_routes();
		}

		// Inventory Domain
		if (class_exists('GlamLux_Inventory_Controller')) {
			(new GlamLux_Inventory_Controller())->register_routes();
		}

		// Membership Domain
		if (class_exists('GlamLux_Membership_Controller')) {
			(new GlamLux_Membership_Controller())->register_routes();
		}

		// Attendance Domain
		if (class_exists('GlamLux_Attendance_Controller')) {
			(new GlamLux_Attendance_Controller())->register_routes();
		}

		// Payroll Domain
		if (class_exists('GlamLux_Payroll_Controller')) {
			(new GlamLux_Payroll_Controller())->register_routes();
		}
	}
	public function check_rate_limit($result, $server, $request)
	{
		$route = $request->get_route();
		if (strpos($route, '/glamlux/v1') !== 0)
			return $result;
		if (current_user_can('manage_options'))
			return $result;

		$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

		// Whitelist server-side self-requests (PHP template -> REST API on loopback)
		if (in_array($ip, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true))
			return $result;

		$transient_key = 'glamlux_rl_' . md5($ip);
		$window = 60; // seconds
		$max_requests = 60;

		$data = get_transient($transient_key);
		if (false === $data) {
			set_transient($transient_key, ['count' => 1, 'start' => time()], $window);
			return $result;
		}
		if ($data['count'] >= $max_requests)
			return new WP_Error('rate_limit', 'Too many requests. Please try again in a minute.', ['status' => 429]);

		$data['count']++;
		set_transient($transient_key, $data, max(1, $window - (time() - $data['start'])));
		return $result;
	}

	public function add_caching_headers($response, $server, $request)
	{
		$route = $request->get_route();
		if (strpos($route, '/glamlux/v1') === 0 && $request->get_method() === 'GET') {
			if ($response instanceof WP_REST_Response) {
				$response->header('Cache-Control', 'public, max-age=900');
			}
		}
		return $response;
	}

	public function format_response_envelope($response, $server, $request)
	{
		$route = $request->get_route();
		if (strpos($route, '/glamlux/v1') === 0) {
			// Phase 3.1: Standardize Envelope
			if ($response instanceof WP_REST_Response) {
				$data = $response->get_data();
				$status = $response->get_status();
				$is_success = $status >= 200 && $status < 300;

				// Ensure we only format once
				if (!is_array($data) || (!isset($data['success']) && !isset($data['data']) && !isset($data['errors']))) {
					$formatted = [
						'success' => $is_success,
						'data' => $is_success ? $data : (object)[],
						'errors' => $is_success ? [] : (is_array($data) ? $data : [$data]),
						'meta' => (object)[]
					];
					$response->set_data($formatted);
				}
			}
		}
		return $response;
	}
}