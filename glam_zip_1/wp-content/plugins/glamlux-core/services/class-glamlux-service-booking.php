<?php
class GlamLux_Service_Booking
{
	private $repo;
	public function __construct(GlamLux_Repo_Appointment $repo = null)
	{
		$this->repo = $repo ?: new GlamLux_Repo_Appointment();
	}
	/**
	 * PHASE 5 (Concurrency): Shortened transaction scope.
	 * Transaction wraps ONLY: check availability → insert → commit.
	 * Event dispatch and side effects fire AFTER commit to minimize row-lock duration.
	 *
	 * Before: Transaction held during event dispatch (~50ms row lock)
	 * After:  Transaction held during slot check + insert only (~5ms row lock)
	 */
	public function secure_book_appointment($staff_id, $client_id, $service_id, $salon_id, $appointment_time, $notes = '')
	{
		$id = null;

		try {
			// ── TRANSACTION SCOPE: minimal — slot lock + insert only ──────────
			$this->repo->transaction_start();

			if (!$this->repo->check_availability($staff_id, $appointment_time)) {
				throw new Exception('This time slot is no longer available.');
			}

			$id = $this->repo->create_appointment([
				'staff_id' => $staff_id,
				'client_id' => $client_id,
				'service_id' => $service_id,
				'salon_id' => $salon_id,
				'appointment_time' => $appointment_time,
				'status' => 'pending',
				'notes' => sanitize_text_field($notes),
			]);

			if (!$id) {
				throw new Exception('Failed to create booking.');
			}

			$this->repo->transaction_commit();
		// ── END TRANSACTION — row unlocked here ───────────────────────────

		}
		catch (Exception $e) {
			$this->repo->transaction_rollback();
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