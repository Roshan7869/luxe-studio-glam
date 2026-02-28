<?php
$repo_dir = __DIR__ . '/../Repositories/';
$service_dir = __DIR__ . '/../Services/';

if (!is_dir($repo_dir))
    mkdir($repo_dir, 0755, true);
if (!is_dir($service_dir))
    mkdir($service_dir, 0755, true);

// 1. REVENUE
file_put_contents($repo_dir . 'class-glamlux-repo-revenue.php', <<<PHP
<?php
class GlamLux_Repo_Revenue {
	public function get_all_metrics() {
		global \$wpdb;
		return \$wpdb->get_results("SELECT metric_key,metric_value FROM {\$wpdb->prefix}gl_metrics_cache", ARRAY_A);
	}
	public function get_aggregate_revenue() {
		global \$wpdb;
		return \$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {\$wpdb->prefix}gl_appointments WHERE status='completed'");
	}
	public function get_aggregate_clients() {
		global \$wpdb;
		return \$wpdb->get_var("SELECT COUNT(*) FROM {\$wpdb->prefix}gl_clients");
	}
	public function get_aggregate_bookings() {
		global \$wpdb;
		return \$wpdb->get_var("SELECT COUNT(*) FROM {\$wpdb->prefix}gl_appointments");
	}
	public function upsert_metric(\$key, \$val) {
		global \$wpdb;
		\$wpdb->query(\$wpdb->prepare("INSERT INTO {\$wpdb->prefix}gl_metrics_cache (metric_key,metric_value,updated_at) VALUES(%s,%f,NOW()) ON DUPLICATE KEY UPDATE metric_value=VALUES(metric_value),updated_at=NOW()", \$key, (float)\$val));
	}
	public function increment_revenue_metric(\$amount) {
		global \$wpdb;
		\$wpdb->query(\$wpdb->prepare("INSERT INTO {\$wpdb->prefix}gl_metrics_cache (metric_key,metric_value,updated_at) VALUES(%s,%f,NOW()) ON DUPLICATE KEY UPDATE metric_value=metric_value+VALUES(metric_value),updated_at=NOW()", 'total_revenue', (float)\$amount));
	}
	public function get_appointment_amount(\$id) {
		global \$wpdb;
		return \$wpdb->get_var(\$wpdb->prepare("SELECT amount FROM {\$wpdb->prefix}gl_appointments WHERE id=%d", \$id));
	}
}
PHP
);

file_put_contents($service_dir . 'class-glamlux-service-revenue.php', <<<PHP
<?php
class GlamLux_Service_Revenue {
	private \$repo;
	public function __construct(GlamLux_Repo_Revenue \$repo = null) {
		\$this->repo = \$repo ?: new GlamLux_Repo_Revenue();
	}
	public function handle_appointment_completed(\$p) {
		\$aid = (int)(\$p["appointment_id"] ?? 0);
		if (!\$aid) return;
		\$amount = \$this->repo->get_appointment_amount(\$aid);
		if (!\$amount) return;
		\$this->repo->increment_revenue_metric(\$amount);
	}
	public function handle_payment_completed(\$p) {
		\$this->handle_appointment_completed(\$p);
	}
	public function get_metrics() {
		\$rows = \$this->repo->get_all_metrics();
		return array_column(\$rows, "metric_value", "metric_key");
	}
	public function refresh_metrics_cache() {
		\$rev = \$this->repo->get_aggregate_revenue();
		\$clients = \$this->repo->get_aggregate_clients();
		\$bookings = \$this->repo->get_aggregate_bookings();
		\$this->repo->upsert_metric("total_revenue", \$rev);
		\$this->repo->upsert_metric("active_clients", \$clients);
		\$this->repo->upsert_metric("total_bookings", \$bookings);
	}
}
PHP
);

// 2. PAYROLL & COMMISSION
file_put_contents($repo_dir . 'class-glamlux-repo-payroll.php', <<<PHP
<?php
class GlamLux_Repo_Payroll {
	public function get_present_staff(\$salon_id = 0) {
		global \$wpdb;
		\$w = \$salon_id ? \$wpdb->prepare("AND salon_id=%d", \$salon_id) : "";
		return \$wpdb->get_results("SELECT DISTINCT staff_id,salon_id FROM {\$wpdb->prefix}gl_attendance WHERE status='present' {\$w}", ARRAY_A);
	}
	public function get_staff_commission(\$staff_id, \$start, \$end) {
		global \$wpdb;
		return (float)\$wpdb->get_var(\$wpdb->prepare("SELECT COALESCE(SUM(commission_earned),0) FROM {\$wpdb->prefix}gl_payroll WHERE staff_id=%d AND period_start>=%s AND period_end<=%s AND appointment_id IS NOT NULL", \$staff_id, \$start, \$end));
	}
	public function get_staff_base_salary(\$staff_id) {
		global \$wpdb;
		return (float)\$wpdb->get_var(\$wpdb->prepare("SELECT COALESCE(base_salary,0) FROM {\$wpdb->prefix}gl_staff WHERE id=%d", \$staff_id));
	}
	public function insert_payroll_record(\$data) {
		global \$wpdb;
		\$wpdb->insert(\$wpdb->prefix."gl_payroll", \$data, ["%d","%d","%s","%s","%f","%f","%f","%s"]);
		return \$wpdb->insert_id;
	}
	public function update_payroll_status(\$id, \$status) {
		global \$wpdb;
		return false !== \$wpdb->update(\$wpdb->prefix."gl_payroll", ["status"=>\$status, "paid_at"=>current_time('mysql')], ["id"=>\$id]);
	}
	public function get_pending_liability(\$salon_id = 0) {
		global \$wpdb;
		\$w = \$salon_id ? \$wpdb->prepare("AND salon_id=%d", \$salon_id) : "";
		return (float)\$wpdb->get_var("SELECT COALESCE(SUM(total_pay),0) FROM {\$wpdb->prefix}gl_payroll WHERE status='pending' {\$w}");
	}
	public function get_payroll_list(\$filters = []) {
		global \$wpdb;
		\$w = "WHERE 1=1";
		if (!empty(\$filters['salon_id'])) \$w .= \$wpdb->prepare(" AND p.salon_id=%d", \$filters['salon_id']);
		if (!empty(\$filters['status'])) \$w .= \$wpdb->prepare(" AND p.status=%s", \$filters['status']);
		return \$wpdb->get_results("SELECT p.*,s.name AS staff_name FROM {\$wpdb->prefix}gl_payroll p LEFT JOIN {\$wpdb->prefix}gl_staff s ON p.staff_id=s.id {\$w} ORDER BY p.id DESC LIMIT 200", ARRAY_A) ?: [];
	}
	public function get_appointment_for_commission(\$appointment_id) {
		global \$wpdb;
		return \$wpdb->get_row(\$wpdb->prepare("SELECT a.*,st.commission_rate FROM {\$wpdb->prefix}gl_appointments a LEFT JOIN {\$wpdb->prefix}gl_staff st ON a.staff_id=st.id WHERE a.id=%d LIMIT 1", \$appointment_id));
	}
	public function has_commission_record(\$appointment_id) {
		global \$wpdb;
		return \$wpdb->get_var(\$wpdb->prepare("SELECT id FROM {\$wpdb->prefix}gl_payroll WHERE appointment_id=%d LIMIT 1", \$appointment_id));
	}
}
PHP
);

file_put_contents($service_dir . 'class-glamlux-service-payroll.php', <<<PHP
<?php
class GlamLux_Service_Payroll {
	private \$repo;
	public function __construct(GlamLux_Repo_Payroll \$repo = null) {
		\$this->repo = \$repo ?: new GlamLux_Repo_Payroll();
	}
	public function run_monthly_batch(\$ps, \$pe, \$sid = 0) {
		\$key = "payroll_{\$ps}_{\$pe}_{\$sid}";
		if (get_transient(\$key)) return ["status" => "already_ran"];
		\$staff = \$this->repo->get_present_staff(\$sid);
		\$c = 0;
		foreach (\$staff as \$s) {
			\$comm = \$this->repo->get_staff_commission(\$s["staff_id"], \$ps, \$pe);
			\$base = \$this->repo->get_staff_base_salary(\$s["staff_id"]);
			\$this->repo->insert_payroll_record([
				"staff_id" => \$s["staff_id"], "salon_id" => \$s["salon_id"],
				"period_start" => \$ps, "period_end" => \$pe,
				"total_services" => 0, "commission_earned" => \$comm,
				"total_pay" => \$base + \$comm, "status" => "pending"
			]);
			\$c++;
		}
		set_transient(\$key, true, MONTH_IN_SECONDS);
		return ["status" => "ok", "payslips" => \$c];
	}
	public function mark_paid(\$pid) { return \$this->repo->update_payroll_status(\$pid, 'paid'); }
	public function get_pending_liability(\$sid = 0) { return \$this->repo->get_pending_liability(\$sid); }
	public function get_payroll_list(\$f = []) { return \$this->repo->get_payroll_list(\$f); }
}
PHP
);

file_put_contents($service_dir . 'class-glamlux-service-commission.php', <<<PHP
<?php
class GlamLux_Service_Commission {
	public static function init() {
		add_action("glamlux_event_appointment_completed", [static::class, "handle_appointment_completed"]);
		add_action("glamlux_event_payment_completed", [static::class, "handle_appointment_completed"]);
	}
	public static function handle_appointment_completed(\$payload) {
		\$aid = (int)(\$payload["appointment_id"] ?? 0);
		if (!\$aid) return;
		\$repo = new GlamLux_Repo_Payroll();
		\$appointment = \$repo->get_appointment_for_commission(\$aid);
		if (!\$appointment || !\$appointment->staff_id) return;
		if (\$repo->has_commission_record(\$aid)) return;
		\$rate = (float)(\$appointment->commission_rate ?? 0);
		\$amount = (float)(\$appointment->amount ?? 0);
		\$commission = round(\$amount * (\$rate / 100), 2);
		\$repo->insert_payroll_record([
			"staff_id" => \$appointment->staff_id, "salon_id" => \$appointment->salon_id,
			"appointment_id" => \$aid, "period_start" => date("Y-m-01"), "period_end" => date("Y-m-t"),
			"total_services" => \$amount, "commission_earned" => \$commission, "status" => "pending"
		]);
	}
}
PHP
);

// 3. ATTENDANCE
file_put_contents($repo_dir . 'class-glamlux-repo-attendance.php', <<<PHP
<?php
class GlamLux_Repo_Attendance {
	public function get_attendance(\$staff_id, \$date) {
		global \$wpdb;
		return \$wpdb->get_row(\$wpdb->prepare("SELECT * FROM {\$wpdb->prefix}gl_attendance WHERE staff_id=%d AND shift_date=%s", \$staff_id, \$date));
	}
	public function get_shift(\$staff_id, \$date) {
		global \$wpdb;
		return \$wpdb->get_row(\$wpdb->prepare("SELECT * FROM {\$wpdb->prefix}gl_shifts WHERE staff_id=%d AND shift_date=%s", \$staff_id, \$date));
	}
	public function insert_attendance(\$data) {
		global \$wpdb;
		return \$wpdb->insert(\$wpdb->prefix."gl_attendance", \$data, ["%d","%d","%s","%s","%d","%d","%s"]);
	}
	public function update_attendance(\$id, \$data) {
		global \$wpdb;
		return \$wpdb->update(\$wpdb->prefix."gl_attendance", \$data, ["id"=>\$id]);
	}
	public function get_monthly_attendance(\$staff_id, \$month) {
		global \$wpdb;
		return \$wpdb->get_results(\$wpdb->prepare("SELECT * FROM {\$wpdb->prefix}gl_attendance WHERE staff_id=%d AND DATE_FORMAT(shift_date,'%%Y-%%m')=%s", \$staff_id, \$month), ARRAY_A);
	}
}
PHP
);

file_put_contents($service_dir . 'class-glamlux-service-attendance.php', <<<PHP
<?php
class GlamLux_Service_Attendance {
	private \$repo;
	public function __construct(GlamLux_Repo_Attendance \$repo = null) {
		\$this->repo = \$repo ?: new GlamLux_Repo_Attendance();
	}
	public function check_in(\$staff_id, \$salon_id) {
		\$today = date("Y-m-d");
		if (\$this->repo->get_attendance(\$staff_id, \$today)) return false;
		\$shift = \$this->repo->get_shift(\$staff_id, \$today);
		\$late = 0; \$lmin = 0;
		if (\$shift) {
			\$sc = strtotime(\$today . " " . \$shift->start_time);
			if (time() > \$sc + 900) { \$late = 1; \$lmin = (int)((time() - \$sc) / 60); }
		}
		\$this->repo->insert_attendance([
			"staff_id" => (int)\$staff_id, "salon_id" => (int)\$salon_id, "shift_date" => \$today,
			"check_in" => current_time("mysql"), "is_late" => \$late, "late_minutes" => \$lmin, "status" => "present",
			"check_out" => null, "hours_worked" => 0.00
		]);
		return true;
	}
	public function check_out(\$staff_id) {
		\$today = date("Y-m-d");
		\$rec = \$this->repo->get_attendance(\$staff_id, \$today);
		if (!\$rec || \$rec->check_out) return false;
		\$this->repo->update_attendance(\$rec->id, [
			"check_out" => current_time("mysql"),
			"hours_worked" => round((time() - strtotime(\$rec->check_in)) / 3600, 2)
		]);
		return true;
	}
	public function get_monthly_summary(\$staff_id, \$month) {
		\$rows = \$this->repo->get_monthly_attendance(\$staff_id, \$month);
		return [
			"days" => count(\$rows),
			"late_days" => count(array_filter(\$rows, fn(\$r) => !empty(\$r["is_late"]))),
			"hours" => round(array_sum(array_column(\$rows, "hours_worked")), 2)
		];
	}
}
PHP
);

echo "Group 1 generated.\\n";
