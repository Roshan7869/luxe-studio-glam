<?php
class GlamLux_GDPR_Controller extends GlamLux_Base_Controller
{
	public function register_routes()
	{
		register_rest_route('glamlux/v1', '/user/export', [
			'methods' => 'GET',
			'callback' => [$this, 'gdpr_export_data'],
			'permission_callback' => [$this, 'require_logged_in'],
		]);
		register_rest_route('glamlux/v1', '/user/delete', [
			'methods' => 'DELETE',
			'callback' => [$this, 'gdpr_delete_account'],
			'permission_callback' => [$this, 'require_logged_in'],
		]);
	}
	public function gdpr_export_data($request)
	{
		$user_id = get_current_user_id();
		$service = new GlamLux_Service_GDPR();
		$data = $service->export_user_data($user_id);
		if (is_wp_error($data))
			return $data;
		return rest_ensure_response($data);
	}
	public function gdpr_delete_account($request)
	{
		$user_id = get_current_user_id();
		$service = new GlamLux_Service_GDPR();
		$result = $service->delete_user_account($user_id);
		if (is_wp_error($result))
			return $result;
		return rest_ensure_response(['success' => true, 'message' => 'Account deleted.']);
	}
}