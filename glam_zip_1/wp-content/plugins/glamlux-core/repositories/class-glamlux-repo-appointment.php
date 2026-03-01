<?php
class GlamLux_Repo_Appointment
{
	public function get_appointment_by_id($id)
	{
		global $wpdb;
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gl_appointments WHERE id=%d LIMIT 1", $id), ARRAY_A);
	}
	public function check_availability($staff_id, $time)
	{
		global $wpdb;
		$exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}gl_appointments WHERE staff_id=%d AND appointment_time=%s AND status NOT IN ('cancelled','refunded')", $staff_id, $time));
		return !$exists;
	}
	public function create_appointment($data)
	{
		global $wpdb;
		$wpdb->insert($wpdb->prefix . "gl_appointments", $data);
		return $wpdb->insert_id;
	}
	public function update_status($id, $status)
	{
		global $wpdb;
		return false !== $wpdb->update($wpdb->prefix . "gl_appointments", ["status" => $status, "updated_at" => current_time('mysql')], ["id" => $id]);
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
}