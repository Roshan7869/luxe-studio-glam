<?php
class GlamLux_Salon_Controller extends GlamLux_Base_Controller
{
	public function register_routes()
	{
		register_rest_route('glamlux/v1', '/salons', [
			'methods' => 'GET',
			'callback' => [$this, 'get_salons'],
			'permission_callback' => '__return_true',
		]);
	}
	public function get_salons($request)
	{
		$cache_key = 'glamlux_cached_salons_blog_' . get_current_blog_id();
		$cached = get_transient($cache_key);
		if ($cached !== false)
			return rest_ensure_response($cached);

		$repo = new GlamLux_Repo_Franchise();
		$salons = $repo->get_active_salons();

		if (!empty($salons)) {
			set_transient($cache_key, $salons, 300);
		}
		return rest_ensure_response($salons);
	}
}