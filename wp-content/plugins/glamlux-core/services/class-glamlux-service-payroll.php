<?php
class GlamLux_Service_Payroll {
	private $repo;
	public function __construct(GlamLux_Repo_Payroll $repo = null) {
		$this->repo = $repo ?: new GlamLux_Repo_Payroll();
	}
	public function run_monthly_batch($ps, $pe, $sid = 0) {
		$key = "payroll_{$ps}_{$pe}_{$sid}_blog_" . get_current_blog_id();
		if (get_transient($key)) return ["status" => "already_ran"];
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
		set_transient($key, true, MONTH_IN_SECONDS);
		return ["status" => "ok", "payslips" => $c];
	}
	public function mark_paid($pid) { return $this->repo->update_payroll_status($pid, 'paid'); }
	public function get_pending_liability($sid = 0) { return $this->repo->get_pending_liability($sid); }
	public function get_payroll_list($f = []) { return $this->repo->get_payroll_list($f); }
}