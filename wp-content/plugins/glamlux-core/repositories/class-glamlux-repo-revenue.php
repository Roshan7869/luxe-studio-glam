<?php
class GlamLux_Repo_Revenue {
	public function get_all_metrics() {
		global $wpdb;
		return $wpdb->get_results("SELECT metric_key,metric_value FROM {$wpdb->prefix}gl_metrics_cache", ARRAY_A);
	}
	public function get_aggregate_revenue() {
		global $wpdb;
		return $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}gl_appointments WHERE status='completed'");
	}
	public function get_aggregate_clients() {
		global $wpdb;
		return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gl_clients");
	}
	public function get_aggregate_bookings() {
		global $wpdb;
		return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gl_appointments");
	}
	public function upsert_metric($key, $val) {
		global $wpdb;
		$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}gl_metrics_cache (metric_key,metric_value,updated_at) VALUES(%s,%f,NOW()) ON DUPLICATE KEY UPDATE metric_value=VALUES(metric_value),updated_at=NOW()", $key, (float)$val));
	}
	public function increment_revenue_metric($amount) {
		global $wpdb;
		$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}gl_metrics_cache (metric_key,metric_value,updated_at) VALUES(%s,%f,NOW()) ON DUPLICATE KEY UPDATE metric_value=metric_value+VALUES(metric_value),updated_at=NOW()", 'total_revenue', (float)$amount));
	}
	public function get_appointment_amount($id) {
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare("SELECT amount FROM {$wpdb->prefix}gl_appointments WHERE id=%d", $id));
	}
}