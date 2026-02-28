<?php
class GlamLux_REST_Manager
{
	public function __construct()
	{
		add_filter('rest_pre_dispatch', [$this, 'check_rate_limit'], 10, 3);
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

		// Compliance
		(new GlamLux_GDPR_Controller())->register_routes();
	}
	public function check_rate_limit($result, $server, $request)
	{
		$route = $request->get_route();
		if (strpos($route, '/glamlux/v1') !== 0)
			return $result;
		if (current_user_can('manage_options'))
			return $result;
		$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
		$transient_key = 'glamlux_rl_' . md5($ip);
		$data = get_transient($transient_key);
		if (false === $data) {
			set_transient($transient_key, ['count' => 1, 'start' => time()], 10);
			return $result;
		}
		if ($data['count'] >= 5)
			return new WP_Error('rate_limit', 'Too many requests.', ['status' => 429]);
		$data['count']++;
		set_transient($transient_key, $data, max(1, 10 - (time() - $data['start'])));
		return $result;
	}
}