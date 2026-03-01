<?php
class GlamLux_Repo_Territory {
	public function count_franchises_in_state($state, $exclude_id = null) {
		global $wpdb;
		$q = "SELECT COUNT(*) FROM {$wpdb->prefix}gl_franchises WHERE territory_state=%s";
		$a = [$state];
		if ($exclude_id) { $q .= " AND id!=%d"; $a[] = $exclude_id; }
		return (int)$wpdb->get_var($wpdb->prepare($q, ...$a));
	}
	public function get_franchise_by_state($state) {
		global $wpdb;
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gl_franchises WHERE territory_state=%s LIMIT 1", $state)) ?: null;
	}
	public function get_territory_map() {
		global $wpdb;
		return $wpdb->get_results("SELECT f.territory_state AS state, f.name AS franchise_name, u.display_name AS admin_name, COUNT(s.id) AS salon_count FROM {$wpdb->prefix}gl_franchises f LEFT JOIN {$wpdb->users} u ON f.admin_id=u.ID LEFT JOIN {$wpdb->prefix}gl_salons s ON s.franchise_id=f.id GROUP BY f.id ORDER BY f.territory_state ASC", ARRAY_A);
	}
	public function get_revenue_by_territory($from, $to) {
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare("SELECT f.territory_state AS state, SUM(a.amount) AS total_revenue, COUNT(a.id) AS appointment_count FROM {$wpdb->prefix}gl_appointments a INNER JOIN {$wpdb->prefix}gl_salons s ON a.salon_id=s.id INNER JOIN {$wpdb->prefix}gl_franchises f ON s.franchise_id=f.id WHERE a.appointment_time BETWEEN %s AND %s AND a.payment_status='paid' GROUP BY f.territory_state ORDER BY total_revenue DESC", $from . ' 00:00:00', $to . ' 23:59:59'), ARRAY_A);
	}
	public function get_admin_id_by_territory($state) {
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare("SELECT admin_id FROM {$wpdb->prefix}gl_franchises WHERE territory_state=%s AND admin_id>0 LIMIT 1", $state));
	}
}