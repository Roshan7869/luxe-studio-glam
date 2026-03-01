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
		$cached = get_transient('glamlux_cached_services');
		if ($cached !== false)
			return rest_ensure_response($cached);

		$repo = new GlamLux_Repo_Franchise();
		$services = $repo->get_active_services();

		if (empty($services)) {
			$services = [
				['id' => 's1', 'name' => 'Luminous Facial', 'price' => 180, 'category' => 'Skin'],
				['id' => 's2', 'name' => 'Bridal Artistry', 'price' => 250, 'category' => 'Makeup']
			];
		}
		set_transient('glamlux_cached_services', $services, 300);
		return rest_ensure_response($services);
	}
}