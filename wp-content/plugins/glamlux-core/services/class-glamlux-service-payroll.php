<?php
class GlamLux_Service_Payroll {
	private $repo;
	public function __construct(GlamLux_Repo_Payroll $repo = null) {
		$this->repo = $repo ?: new GlamLux_Repo_Payroll();
	}
	public function run_monthly_batch($ps, $pe, $sid = 0) {
		global $wpdb;
		
		// PHASE 2: Hard DB-level gate — verify if batch already executed
		// Don't rely solely on transient cache which can be flushed externally
		$existing_count = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}gl_payroll 
			 WHERE period_start = %s AND period_end = %s AND (salon_id = %d OR %d = 0)",
			$ps, $pe, $sid, $sid
		));
		
		if ($existing_count > 0) {
			return ["status" => "already_ran", "message" => "Payroll batch for this period already exists"];
		}
		
		// Transient as secondary lock for concurrent request protection
		$key = "payroll_{$ps}_{$pe}_{$sid}";
		if (get_transient($key)) return ["status" => "already_ran", "message" => "Batch currently processing"];
		set_transient($key, true, 300); // 5 min lock
		
		try {
			$staff = $this->repo->get_present_staff($sid);
			$c = 0;
			foreach ($staff as $s) {
				$comm = $this->repo->get_staff_commission($s["staff_id"], $ps, $pe);
				$base = $this->repo->get_staff_base_salary($s["staff_id"]);
				$this->repo->insert_payroll_record([
					"staff_id" => $s["staff_id"], "salon_id" => $s["salon_id"],
					"period_start" => $ps, "period_end" => $pe,
					"total_services" => 0, "commission_earned" => $comm,
					"total_pay" => $base + $comm, "status" => "pending"
				]);
				$c++;
			}
			return ["status" => "ok", "payslips" => $c];
		} finally {
			delete_transient($key);
		}
	}
	public function mark_paid($pid) { return $this->repo->update_payroll_status($pid, 'paid'); }
	public function get_pending_liability($sid = 0) { return $this->repo->get_pending_liability($sid); }
	public function get_payroll_list($f = []) { return $this->repo->get_payroll_list($f); }
}