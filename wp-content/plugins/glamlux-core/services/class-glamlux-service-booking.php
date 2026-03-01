<?php
class GlamLux_Service_Booking
{
	private $repo;
	private $staffRepo;

	public function __construct(GlamLux_Repo_Appointment $repo = null, GlamLux_Repo_Staff $staffRepo = null)
	{
		$this->repo = $repo ?: new GlamLux_Repo_Appointment();
		$this->staffRepo = $staffRepo ?: new GlamLux_Repo_Staff();
	}

	private function validate_business_hours($salon_id, $appointment_time, $duration_minutes)
	{
		$timestamp = strtotime($appointment_time);
		if ($timestamp <= current_time('timestamp')) {
			throw new Exception("Cannot book appointment in the past.");
		}

		$minute = date('i', $timestamp);
		if ($minute % 30 !== 0) {
			throw new Exception("Invalid slot interval. Bookings must be in 30-minute intervals.");
		}

		$day = date('w', $timestamp);
		$time = date('H:i:s', $timestamp);
		$end_time = date('H:i:s', $timestamp + ($duration_minutes * 60));

		$hours = $this->repo->get_salon_hours($salon_id, $day);

		if (!$hours) {
			throw new Exception("Salon closed on this day.");
		}

		if ($time < $hours->open_time || $end_time > $hours->close_time) {
			throw new Exception("Outside operating hours or exceeds closing time.");
		}
	}

	private function find_available_staff($salon_id, $start_time, $end_time)
	{
		// Phase 3 & 4: Load balanced staff retrieval.
		$date = date('Y-m-d', strtotime($start_time));
		$staff_members = $this->staffRepo->get_staff_ordered_by_current_load($salon_id, $date);

		foreach ($staff_members as $staff) {
			if (!$this->repo->has_time_overlap($staff['id'], $start_time, $end_time)) {
				return $staff['id'];
			}
		}

		return null;
	}

	/**
	 * PHASE 1 & 2 (Concurrency and Duration):
	 * DB-level UNIQUE KEY enforces slot integrity. Pre-row locking removed.
	 * Structural duration ensures no time-block intersections.
	 */
	public function secure_book_appointment($staff_id, $client_id, $service_id, $salon_id, $appointment_time, $notes = '', $duration_minutes = 30)
	{
		$id = null;
		$end_time = date('Y-m-d H:i:s', strtotime($appointment_time) + ($duration_minutes * 60));

		try {
			// PRE-TRANSACTION VALIDATION: Business Logic
			$this->validate_business_hours($salon_id, $appointment_time, $duration_minutes);

			$this->repo->transaction_start();

			// Attempt assigning specific staff if requested, otherwise auto-assign
			$assigned_staff_id = $staff_id;
			if (!$assigned_staff_id) {
				$assigned_staff_id = $this->find_available_staff($salon_id, $appointment_time, $end_time);
			}
			else {
				// Forced staff requested, just verify them
				if ($this->repo->has_time_overlap($assigned_staff_id, $appointment_time, $end_time)) {
					$assigned_staff_id = null;
				}
			}

			if (!$assigned_staff_id) {
				throw new Exception('This time slot is no longer available or no staff can accommodate this time.');
			}

			// Phase 6: Set status to pending_payment for Payment Lifecycle
			$id = $this->repo->create_appointment([
				'staff_id' => $assigned_staff_id,
				'client_id' => $client_id,
				'service_id' => $service_id,
				'salon_id' => $salon_id,
				'appointment_time' => $appointment_time,
				'duration_minutes' => $duration_minutes,
				'status' => 'pending_payment',
				'notes' => sanitize_text_field($notes),
			]);

			if (!$id) {
				throw new Exception('Failed to create booking.');
			}

			$this->repo->transaction_commit();

		}
		catch (Exception $e) {
			$this->repo->transaction_rollback();

			// If it's the expected WPDB constraint collision (MySQL 1062)
			global $wpdb;
			if ($wpdb->last_error && strpos($wpdb->last_error, 'Duplicate entry') !== false) {
				return new WP_Error('booking_failed', 'Slot already taken (concurrency blocked).', ['status' => 409]);
			}

			return new WP_Error('booking_failed', $e->getMessage(), ['status' => 400]);
		}

		// ── POST-COMMIT: side effects fire OUTSIDE transaction ────────────────
		// Commission calculation, inventory deduction, SMS, etc. happen here.
		// A failure here does NOT roll back the booking — it gets retried via cron.
		if ($id && class_exists('GlamLux_Event_Dispatcher')) {
			GlamLux_Event_Dispatcher::dispatch('appointment_created', [
				'appointment_id' => $id,
				'client_id' => $client_id,
				'salon_id' => $salon_id,
			]);
		}

		return $id;
	}
	public function mark_completed($id)
	{
		$apt = $this->repo->get_appointment_by_id($id);
		if (!$apt || $apt['status'] === 'completed')
			return false;
		$this->repo->update_status($id, 'completed');
		if (class_exists('GlamLux_Event_Dispatcher'))
			GlamLux_Event_Dispatcher::dispatch('appointment_completed', ['appointment' => $apt]);
		return true;
	}

	// Phase 6 State Machine: Confirm Payment
	public function confirm_payment($appointment_id)
	{
		$apt = $this->repo->get_appointment_by_id($appointment_id);
		if (!$apt || $apt['status'] !== 'pending_payment') {
			return false;
		}

		$this->repo->update_status($appointment_id, 'confirmed');
		return true;
	}

	// Phase 6 State Machine: Cancel Payment
	public function cancel_payment($appointment_id)
	{
		$apt = $this->repo->get_appointment_by_id($appointment_id);
		if (!$apt || $apt['status'] !== 'pending_payment') {
			return false;
		}

		$this->repo->update_status($appointment_id, 'cancelled');
		return true;
	}

	public function get_client_appointments_by_user($user_id)
	{
		$client_id = $this->repo->get_client_id_by_user_id($user_id);
		if (!$client_id)
			return [];
		return $this->repo->get_client_appointments($client_id);
	}

	public function book_appointment_via_api($user_id, $salon_id, $service_id, $appointment_time, $notes)
	{
		if (!strtotime($appointment_time))
			return new WP_Error('glamlux_invalid_date', 'Invalid date.', ['status' => 400]);

		$client_id = $this->repo->get_client_id_by_user_id($user_id);
		if (!$client_id) {
			$client_id = $this->repo->create_client($user_id);
		}

		$staff_id = $this->repo->get_random_staff_for_salon($salon_id) ?: 1;

		return $this->secure_book_appointment($staff_id, $client_id, $service_id, $salon_id, $appointment_time, $notes);
	}

	public function cancel_booking($appointment_id)
	{
		$updated = $this->repo->update_status($appointment_id, 'cancelled');
		if (!$updated)
			return new WP_Error('fail', 'Could not cancel.', ['status' => 500]);
		do_action('glamlux_appointment_cancelled', $appointment_id);
		return true;
	}
}