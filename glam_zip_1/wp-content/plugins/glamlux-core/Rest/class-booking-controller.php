<?php
class GlamLux_Booking_Controller extends GlamLux_Base_Controller
{
	public function register_routes()
	{
		register_rest_route('glamlux/v1', '/my-appointments', [
			'methods' => 'GET',
			'callback' => [$this, 'get_my_appointments'],
			'permission_callback' => [$this, 'require_logged_in'],
		]);
		register_rest_route('glamlux/v1', '/book', [
			'methods' => 'POST',
			'callback' => [$this, 'create_booking'],
			'permission_callback' => [$this, 'require_client_role'],
		]);
		register_rest_route('glamlux/v1', '/appointments/(?P<id>\d+)/cancel', [
			'methods' => 'POST',
			'callback' => [$this, 'cancel_booking'],
			'permission_callback' => [$this, 'require_staff_or_admin'],
		]);
	}
	public function get_my_appointments($request)
	{
		$user_id = get_current_user_id();
		$service = new GlamLux_Service_Booking();
		$appointments = $service->get_client_appointments_by_user($user_id);
		return rest_ensure_response($appointments);
	}
	public function create_booking($request)
	{
		$user_id = get_current_user_id();
		$salon_id = $request->get_param('salon_id');
		$service_id = $request->get_param('service_id');
		$appointment_time = $request->get_param('appointment_time');
		$notes = $request->get_param('notes') ?? '';

		$service = new GlamLux_Service_Booking();
		$result = $service->book_appointment_via_api($user_id, $salon_id, $service_id, $appointment_time, $notes);

		if (is_wp_error($result))
			return $result;
		return rest_ensure_response(['success' => true, 'appointment_id' => $result, 'message' => 'Appointment confirmed!']);
	}
	public function cancel_booking($request)
	{
		$appointment_id = absint($request->get_param('id'));
		$service = new GlamLux_Service_Booking();
		$result = $service->cancel_booking($appointment_id);
		if (is_wp_error($result))
			return $result;
		return rest_ensure_response(['success' => true, 'message' => 'Cancelled.']);
	}
}