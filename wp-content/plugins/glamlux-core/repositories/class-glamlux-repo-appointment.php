<?php
class GlamLux_Repo_Appointment
{
	public function get_appointment_by_id($id)
	{
		global $wpdb;
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gl_appointments WHERE id=%d LIMIT 1", $id), ARRAY_A);
	}
	public function has_time_overlap($staff_id, $start_time, $end_time)
	{
		global $wpdb;
		// PHASE 2: Structural overlap detection with row-level locking
		// Use SELECT ... FOR UPDATE to prevent concurrent bookings during calculation
		$exists = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}gl_appointments
			 WHERE staff_id = %d AND status NOT IN ('cancelled','refunded')
			   AND appointment_time < %s
			   AND DATE_ADD(appointment_time, INTERVAL duration_minutes MINUTE) > %s
			 FOR UPDATE",
			$staff_id, $end_time, $start_time
		));
		return (bool)$exists;
	}

	public function count_booked_staff($salon_id, $time)
	{
		global $wpdb;
		return (int)$wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}gl_appointments
			 WHERE salon_id = %d AND appointment_time = %s AND status IN ('confirmed','pending')",
			$salon_id, $time
		));
	}

	public function get_salon_hours($salon_id, $day_of_week)
	{
		global $wpdb;
		// Assuming wp_gl_salon_hours contains columns: salon_id, day_of_week (0-6), open_time, close_time
		return $wpdb->get_row($wpdb->prepare(
			"SELECT open_time, close_time FROM {$wpdb->prefix}gl_salon_hours
			 WHERE salon_id = %d AND day_of_week = %d LIMIT 1",
			$salon_id, $day_of_week
		));
	}
	/**
	 * Concurrency-safe appointment creation.
	 * Re-checks staff overlap inside a transaction to prevent double-booking
	 * when two requests arrive simultaneously for the same slot.
	 *
	 * @param array $data Appointment data including staff_id, appointment_time, duration_minutes.
	 * @return int|WP_Error Insert ID on success, WP_Error on conflict.
	 */
	public function create_appointment($data)
	{
		global $wpdb;

		$wpdb->query('START TRANSACTION');

		// Re-check overlap under lock if staff_id and times are provided
		if (!empty($data['staff_id']) && !empty($data['appointment_time']) && !empty($data['duration_minutes'])) {
			$start = $data['appointment_time'];
			$end = date('Y-m-d H:i:s', strtotime($start) + ($data['duration_minutes'] * 60));

			// Lock existing appointments for this staff member
			$conflict = $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}gl_appointments
                 WHERE staff_id = %d AND status NOT IN ('cancelled','refunded')
                   AND appointment_time < %s
                   AND DATE_ADD(appointment_time, INTERVAL duration_minutes MINUTE) > %s
                 FOR UPDATE",
				$data['staff_id'], $end, $start
			));

			if ($conflict) {
				$wpdb->query('ROLLBACK');
				return new \WP_Error('slot_taken', 'This time slot is no longer available. Please choose another.');
			}
		}

		$wpdb->insert($wpdb->prefix . "gl_appointments", $data);
		$id = $wpdb->insert_id;

		$wpdb->query('COMMIT');
		return $id;
	}
	public function update_status($id, $status)
	{
		global $wpdb;
		return false !== $wpdb->update($wpdb->prefix . "gl_appointments", ["status" => $status, "updated_at" => current_time('mysql')], ["id" => $id]);
	}
	public function update_payment_status($id, $payment_status, $status)
	{
		global $wpdb;
		return false !== $wpdb->update($wpdb->prefix . "gl_appointments", ["payment_status" => $payment_status, "status" => $status, "updated_at" => current_time('mysql')], ["id" => $id]);
	}
	public function transaction_start()
	{
		global $wpdb;
		$wpdb->query("START TRANSACTION");
	}
	public function transaction_commit()
	{
		global $wpdb;
		$wpdb->query("COMMIT");
	}
	public function transaction_rollback()
	{
		global $wpdb;
		$wpdb->query("ROLLBACK");
	}

	public function get_client_id_by_user_id($user_id)
	{
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}gl_clients WHERE wp_user_id = %d LIMIT 1", $user_id));
	}

	public function create_client($user_id)
	{
		global $wpdb;
		$wpdb->insert("{$wpdb->prefix}gl_clients", ['wp_user_id' => $user_id, 'created_at' => current_time('mysql')]);
		return $wpdb->insert_id;
	}

	public function get_random_staff_for_salon($salon_id)
	{
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}gl_staff WHERE salon_id = %d LIMIT 1", $salon_id));
	}

	public function get_client_appointments($client_id)
	{
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare(
			"SELECT a.*, s.name AS service_name, sl.name AS salon_name FROM {$wpdb->prefix}gl_appointments a LEFT JOIN {$wpdb->prefix}gl_service_logs s ON a.service_id = s.id LEFT JOIN {$wpdb->prefix}gl_salons sl ON a.salon_id = sl.id WHERE a.client_id = %d ORDER BY a.appointment_time DESC LIMIT 50",
			$client_id
		));
	}

	public function get_last_db_error()
	{
		global $wpdb;
		return $wpdb->last_error || '';
	}

	public function is_duplicate_entry_error(): bool
	{
		global $wpdb;
		return $wpdb->last_error && strpos($wpdb->last_error, 'Duplicate entry') !== false;
	}
}