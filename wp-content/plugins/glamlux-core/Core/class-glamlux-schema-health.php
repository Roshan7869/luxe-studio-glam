<?php

/**
 * Lightweight schema guard for dashboard-critical routes/services.
 */
class GlamLux_SchemaHealth
{
	const TRANSIENT_KEY = 'glamlux_schema_health_v1';
	const CACHE_TTL = 300;

	/**
	 * Register schema validation lifecycle.
	 */
	public static function init()
	{
		add_action('plugins_loaded', array(__CLASS__, 'validate_schema'), 40);
	}

	/**
	 * Return cached/latest schema health report.
	 *
	 * @param bool $force_refresh
	 * @return array
	 */
	public static function get_health_report($force_refresh = false)
	{
		return self::validate_schema($force_refresh);
	}

	/**
	 * Validate required tables, columns and indexes used by operations dashboards.
	 *
	 * @param bool $force_refresh
	 * @return array
	 */
	public static function validate_schema($force_refresh = false)
	{
		delete_transient(self::TRANSIENT_KEY);
		delete_transient('gl_operations_summary');
		if (!$force_refresh) {
			$cached = get_transient(self::TRANSIENT_KEY);
			if (false !== $cached && is_array($cached)) {
				return $cached;
			}
		}

		global $wpdb;

		$schema_map = self::get_schema_map();
		$missing_tables = array();
		$missing_columns = array();
		$missing_indexes = array();

		foreach ($schema_map as $table => $requirements) {
			$full_table_name = $wpdb->prefix . $table;
			$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full_table_name));

			if ($table_exists !== $full_table_name) {
				$missing_tables[] = $table;
				continue;
			}

			if (!empty($requirements['columns'])) {
				foreach ($requirements['columns'] as $column) {
					$column_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$full_table_name} LIKE %s", $column));
					if (empty($column_exists)) {
						$missing_columns[] = array(
							'table' => $table,
							'column' => $column,
						);
					}
				}
			}

			if (!empty($requirements['indexes'])) {
				$index_rows = $wpdb->get_results("SHOW INDEX FROM {$full_table_name}", ARRAY_A);
				$present_index_names = array();
				if (is_array($index_rows)) {
					foreach ($index_rows as $index_row) {
						if (!empty($index_row['Key_name'])) {
							$present_index_names[] = $index_row['Key_name'];
						}
					}
				}

				foreach ($requirements['indexes'] as $index_name) {
					if (!in_array($index_name, $present_index_names, true)) {
						$missing_indexes[] = array(
							'table' => $table,
							'index' => $index_name,
						);
					}
				}
			}
		}

		$status = (empty($missing_tables) && empty($missing_columns) && empty($missing_indexes)) ? 'healthy' : 'degraded';
		$report = array(
			'checked_at' => gmdate('c'),
			'status' => $status,
			'mismatches' => array(
				'missing_tables' => $missing_tables,
				'missing_columns' => $missing_columns,
				'missing_indexes' => $missing_indexes,
			),
			'remediation' => self::get_remediation_steps(),
		);

		if ('degraded' === $status && class_exists('GlamLux_Logger')) {
			GlamLux_Logger::error(
				'Schema health validation detected mismatches for dashboard-critical tables.',
				array(
				'component' => 'schema_health',
				'report' => $report,
			)
			);
		}

		set_transient(self::TRANSIENT_KEY, $report, self::CACHE_TTL);

		return $report;
	}

	/**
	 * Critical schema contract for dashboard and operations endpoints.
	 *
	 * @return array
	 */
	private static function get_schema_map()
	{
		return array(
			'gl_appointments' => array(
				'columns' => array('id', 'salon_id', 'staff_id', 'appointment_time', 'status'),
				'indexes' => array('PRIMARY', 'salon_time'),
			),
			'gl_memberships' => array(
				'columns' => array('id', 'is_active'),
				'indexes' => array('PRIMARY'),
			),
			'gl_staff' => array(
				'columns' => array('id', 'salon_id', 'is_active'),
				'indexes' => array('PRIMARY', 'salon_id'),
			),
			'gl_leads' => array(
				'columns' => array('id', 'status'),
				'indexes' => array('PRIMARY', 'status'),
			),
			'gl_service_logs' => array(
				'columns' => array('id', 'logged_at', 'notes'),
				'indexes' => array('PRIMARY'),
			),
		);
	}

	/**
	 * Suggested remediation for operators.
	 *
	 * @return array
	 */
	private static function get_remediation_steps()
	{
		return array(
			'Re-run GlamLux database setup by deactivating and reactivating the plugin.',
			'If WP-CLI is available, run: wp eval "GlamLux_Activator::activate();"',
			'Review latest migration scripts under glamlux-core/scripts and apply missing migrations.',
		);
	}
}
