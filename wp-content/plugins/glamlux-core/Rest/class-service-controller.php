<?php
class GlamLux_Service_Controller extends GlamLux_Base_Controller
{
	public function register_routes()
	{
		register_rest_route('glamlux/v1', '/services', [
			'methods' => 'GET',
			'callback' => [$this, 'get_services'],
			'permission_callback' => '__return_true',
		]);
	}
	public function get_services($request)
	{
		$cache_key = 'glamlux_cached_services_blog_' . get_current_blog_id();
		$cached = get_transient($cache_key);
		if ($cached !== false)
			return rest_ensure_response($cached);

		$repo = new GlamLux_Repo_Franchise();
		$services = $repo->get_active_services();

		if (!empty($services)) {
			set_transient($cache_key, $services, 300);
		}
		return rest_ensure_response($services);
	}
}