<?php

/**
 * Enterprise operations orchestration service.
 *
 * Provides an aggregated, cache-aware operations snapshot for
 * admin dashboards, REST APIs, and automated monitoring.
 */
class GlamLux_Service_Operations
{

	private $repo;

	public function __construct(GlamLux_Repo_Operations $repo = null)
	{
		$this->repo = $repo ?: new GlamLux_Repo_Operations();
	}

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

		$required_tables = array(
			'gl_franchises',
			'gl_salons',
			'gl_staff',
			'gl_memberships',
			'gl_membership_purchases',
			'gl_clients',
			'gl_appointments',
			'gl_attendance',
			'gl_shifts',
			'gl_payroll',
			'gl_product_sales',
			'gl_service_logs',
			'gl_financial_reports',
		);

		$missing_tables = $this->repo->get_missing_tables($required_tables);

		$appointments_today = $this->repo->get_appointments_today();
		$pending_appointments = $this->repo->get_pending_appointments();
		$active_memberships = $this->repo->get_active_memberships();
		$active_staff = $this->repo->get_active_staff();
		$open_leads = $this->repo->get_open_leads();
		$service_errors = $this->repo->get_service_errors_24h();

		$schema_health = class_exists('GlamLux_SchemaHealth') ?GlamLux_SchemaHealth::get_health_report() : array();
		$ops_health = empty($missing_tables) && $service_errors < 10 ? 'healthy' : 'warning';
		if (!empty($schema_health) && isset($schema_health['status']) && 'healthy' !== $schema_health['status']) {
			$ops_health = 'warning';
		}

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
			'schema_health' => $schema_health,
		);

		set_transient($cache_key, $summary, MINUTE_IN_SECONDS * 3);

		return $summary;
	}
}
