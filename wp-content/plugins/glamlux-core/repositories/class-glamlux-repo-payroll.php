<?php
class GlamLux_Repo_Payroll {
	public function get_present_staff($salon_id = 0) {
		global $wpdb;
		$w = $salon_id ? $wpdb->prepare("AND salon_id=%d", $salon_id) : "";
		return $wpdb->get_results("SELECT DISTINCT staff_id,salon_id FROM {$wpdb->prefix}gl_attendance WHERE status='present' {$w}", ARRAY_A);
	}
	public function get_staff_commission($staff_id, $start, $end) {
		global $wpdb;
		return (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(commission_earned),0) FROM {$wpdb->prefix}gl_payroll WHERE staff_id=%d AND period_start>=%s AND period_end<=%s AND appointment_id IS NOT NULL", $staff_id, $start, $end));
	}
	public function get_staff_base_salary($staff_id) {
		global $wpdb;
		return (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(base_salary,0) FROM {$wpdb->prefix}gl_staff WHERE id=%d", $staff_id));
	}
	public function insert_payroll_record($data) {
		global $wpdb;
		$wpdb->insert($wpdb->prefix."gl_payroll", $data, ["%d","%d","%s","%s","%f","%f","%f","%s"]);
		return $wpdb->insert_id;
	}
	public function update_payroll_status($id, $status) {
		global $wpdb;
		return false !== $wpdb->update($wpdb->prefix."gl_payroll", ["status"=>$status, "paid_at"=>current_time('mysql')], ["id"=>$id]);
	}
	public function get_pending_liability($salon_id = 0) {
		global $wpdb;
		$w = $salon_id ? $wpdb->prepare("AND salon_id=%d", $salon_id) : "";
		return (float)$wpdb->get_var("SELECT COALESCE(SUM(total_pay),0) FROM {$wpdb->prefix}gl_payroll WHERE status='pending' {$w}");
	}
	public function get_payroll_list($filters = []) {
		global $wpdb;
		$w = "WHERE 1=1";
		if (!empty($filters['salon_id'])) $w .= $wpdb->prepare(" AND p.salon_id=%d", $filters['salon_id']);
		if (!empty($filters['status'])) $w .= $wpdb->prepare(" AND p.status=%s", $filters['status']);
		return $wpdb->get_results("SELECT p.*,s.name AS staff_name FROM {$wpdb->prefix}gl_payroll p LEFT JOIN {$wpdb->prefix}gl_staff s ON p.staff_id=s.id {$w} ORDER BY p.id DESC LIMIT 200", ARRAY_A) ?: [];
	}
	public function get_appointment_for_commission($appointment_id) {
		global $wpdb;
		return $wpdb->get_row($wpdb->prepare("SELECT a.*,st.commission_rate FROM {$wpdb->prefix}gl_appointments a LEFT JOIN {$wpdb->prefix}gl_staff st ON a.staff_id=st.id WHERE a.id=%d LIMIT 1", $appointment_id));
	}
	public function has_commission_record($appointment_id) {
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}gl_payroll WHERE appointment_id=%d LIMIT 1", $appointment_id));
	}
}