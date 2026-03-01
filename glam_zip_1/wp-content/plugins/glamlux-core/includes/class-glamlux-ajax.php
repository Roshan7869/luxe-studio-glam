<?php
/**
 * GlamLux AJAX Endpoints
 *
 * LAYER: Presentation (AJAX)
 * RULE:  Validate → sanitize → delegate to Service. Never touch DB directly.
 */
class GlamLux_AJAX
{

	public function __construct()
	{
		add_action('wp_ajax_glamlux_check_availability', array($this, 'check_availability'));
		add_action('wp_ajax_nopriv_glamlux_check_availability', array($this, 'check_availability'));

		add_action('wp_ajax_glamlux_book_appointment', array($this, 'book_appointment'));
		add_action('wp_ajax_nopriv_glamlux_book_appointment', array($this, 'book_appointment'));
	}

	/**
	 * Check staff availability for a given time slot.
	 */
	public function check_availability()
	{
		check_ajax_referer('glamlux_booking_nonce', 'nonce');

		$staff_id = absint($_POST['staff_id'] ?? 0);
		$appointment_time = sanitize_text_field($_POST['appointment_time'] ?? '');

		if (!$staff_id || !$appointment_time || !strtotime($appointment_time)) {
			wp_send_json_error(array('message' => 'Invalid parameters.'), 400);
		}

		$repo = new GlamLux_Repo_Appointment();
		$available = $repo->check_availability($staff_id, $appointment_time);

		wp_send_json_success(array('available' => (bool)$available));
	}

	/**
	 * FIX 3 (P0): Create a real booking — previously returned success with zero logic.
	 *
	 * Flow: AJAX → sanitize → GlamLux_Service_Booking::book_appointment_via_api()
	 *       → GlamLux_Repo_Appointment (transaction) → event dispatch → return ID
	 */
	public function book_appointment()
	{
		check_ajax_referer('glamlux_booking_nonce', 'nonce');

		// ── Input Validation ──────────────────────────────────────────────────
		$salon_id = absint($_POST['salon_id'] ?? 0);
		$service_id = absint($_POST['service_id'] ?? 0);
		$appointment_time = sanitize_text_field($_POST['appointment_time'] ?? '');
		$notes = sanitize_textarea_field($_POST['notes'] ?? '');

		if (!$salon_id || !$service_id || !$appointment_time) {
			wp_send_json_error(array('message' => 'Missing required fields: salon_id, service_id, appointment_time.'), 400);
		}

		if (!strtotime($appointment_time) || strtotime($appointment_time) < time()) {
			wp_send_json_error(array('message' => 'Invalid or past appointment time.'), 400);
		}

		// ── Delegate to Service (never touch repo from AJAX) ──────────────────
		$service = new GlamLux_Service_Booking();
		$user_id = get_current_user_id(); // 0 for guests — service handles client creation
		$booking_id = $service->book_appointment_via_api($user_id, $salon_id, $service_id, $appointment_time, $notes);

		if (is_wp_error($booking_id)) {
			wp_send_json_error(array(
				'message' => $booking_id->get_error_message(),
				'code' => $booking_id->get_error_code(),
			), 400);
		}

		wp_send_json_success(array(
			'message' => 'Appointment Confirmed',
			'appointment_id' => $booking_id,
		));
	}
}
