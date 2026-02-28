<?php

/**
 * Enterprise operations orchestration service.
 *
 * Provides an aggregated, cache-aware operations snapshot for
 * admin dashboards, REST APIs, and automated monitoring.
 */
class GlamLux_Service_Operations
{

	/**
	 * Build a unified operations summary for enterprise monitoring.
	 *
	 * @return array
	 */
	public function get_operations_summary()
	{
		$cache_key = 'glamlux_ops_summary_v1';
		$cached = get_transient($cache_key);
		if (false !== $cached && is_array($cached)) {
			return $cached;
		}

		global $wpdb;

		$required_tables = array(
			'gl_franchises',
			'gl_salons',
			'gl_staff',
			'gl_memberships',
			'gl_clients',
			'gl_appointments',
			'gl_payroll',
			'gl_product_sales',
			'gl_service_logs',
			'gl_financial_reports',
		);

		$missing_tables = array();
		foreach ($required_tables as $table) {
			$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . $table));
			if ($exists !== $wpdb->prefix . $table) {
				$missing_tables[] = $table;
			}
		}

		$appointments_today = (int)$wpdb->get_var(
			"SELECT COUNT(id) FROM {$wpdb->prefix}gl_appointments WHERE DATE(appointment_time) = CURDATE()"
		);

		$pending_appointments = (int)$wpdb->get_var(
			"SELECT COUNT(id) FROM {$wpdb->prefix}gl_appointments WHERE status IN ('pending', 'scheduled')"
		);

		$active_memberships = (int)$wpdb->get_var(
			"SELECT COUNT(id) FROM {$wpdb->prefix}gl_clients WHERE membership_id IS NOT NULL AND membership_expiry IS NOT NULL AND membership_expiry > NOW()"
		);

		$active_staff = (int)$wpdb->get_var(
			"SELECT COUNT(id) FROM {$wpdb->prefix}gl_staff WHERE is_active=1"
		);

		$open_leads = 0;
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'gl_leads')) === $wpdb->prefix . 'gl_leads') {
			$open_leads = (int)$wpdb->get_var(
				"SELECT COUNT(id) FROM {$wpdb->prefix}gl_leads WHERE status IN ('new','open','follow_up')"
			);
		}

		$service_errors = 0;
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'gl_service_logs')) === $wpdb->prefix . 'gl_service_logs') {
			$has_logged_at = (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}gl_service_logs LIKE %s", 'logged_at'));
			$has_notes = (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}gl_service_logs LIKE %s", 'notes'));
			if ($has_logged_at && $has_notes) {
				$service_errors = (int)$wpdb->get_var(
					"SELECT COUNT(id) FROM {$wpdb->prefix}gl_service_logs WHERE logged_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND (LOWER(notes) LIKE '%error%' OR LOWER(notes) LIKE '%failed%')"
				);
			}
		}

		$ops_health = empty($missing_tables) && $service_errors < 10 ? 'healthy' : 'warning';

		$summary = array(
			'generated_at' => gmdate('c'),
			'health' => $ops_health,
			'database' => array(
				'required_tables' => $required_tables,
				'missing_tables' => $missing_tables,
				'status' => empty($missing_tables) ? 'ready' : 'degraded',
			),
			'operations' => array(
				'appointments_today' => $appointments_today,
				'pending_appointments' => $pending_appointments,
				'active_memberships' => $active_memberships,
				'active_staff' => $active_staff,
				'open_leads' => $open_leads,
				'service_errors_24h' => $service_errors,
			),
		);

		set_transient($cache_key, $summary, MINUTE_IN_SECONDS * 3);

		return $summary;
	}
}
