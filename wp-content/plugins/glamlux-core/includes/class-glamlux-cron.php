<?php
/**
 * GlamLux WP-Cron Jobs
 *
 * Background processing: membership expiry, daily DB backup, inventory reorder alerts.
 *
 * Phase 14 Additions:
 * - Daily DB backup export (to local storage + optional remote sync)
 * - Low-inventory reorder alert notifications
 */
class GlamLux_Cron
{

	public function __construct()
	{
		add_filter('cron_schedules', array($this, 'add_cron_intervals'));

		// ── Membership expiry check ─────────────────────────────────────────
		add_action('glamlux_daily_membership_check', array($this, 'process_memberships'));
		if (!wp_next_scheduled('glamlux_daily_membership_check')) {
			wp_schedule_event(time(), 'daily', 'glamlux_daily_membership_check');
		}

		// ── Daily DB backup ─────────────────────────────────────────────────
		add_action('glamlux_daily_backup', array($this, 'run_db_backup'));
		if (!wp_next_scheduled('glamlux_daily_backup')) {
			// Schedule at 2:00 AM server time to avoid peak hours
			$midnight = strtotime('tomorrow 02:00:00');
			wp_schedule_event($midnight, 'daily', 'glamlux_daily_backup');
		}

		// ── Inventory reorder alert ─────────────────────────────────────────
		add_action('glamlux_inventory_check', array($this, 'check_inventory_levels'));
		if (!wp_next_scheduled('glamlux_inventory_check')) {
			wp_schedule_event(time(), 'daily', 'glamlux_inventory_check');
		}

		// ── Monthly Payroll (FIX 4: dynamic 1st-of-month scheduling, no drift) ─
		add_action('glamlux_monthly_payroll', array($this, 'run_payroll'));
		if (!wp_next_scheduled('glamlux_monthly_payroll')) {
			// Always schedule for 01:00 AM on the 1st day of next calendar month
			$next_first = mktime(1, 0, 0, (int)date('n') + 1, 1, (int)date('Y'));
			wp_schedule_single_event($next_first, 'glamlux_monthly_payroll');
		}
	}

	/**
	 * Register custom WP-Cron intervals (weekly, bi-weekly).
	 */
	public function add_cron_intervals($schedules)
	{
		$schedules['weekly'] = array(
			'interval' => 7 * DAY_IN_SECONDS,
			'display' => __('Once Weekly', 'glamlux-core'),
		);
		$schedules['bi_weekly'] = array(
			'interval' => 14 * DAY_IN_SECONDS,
			'display' => __('Bi-Weekly', 'glamlux-core'),
		);
		$schedules['monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS, // Kept for interval API compatibility; payroll uses single events instead
			'display' => __('Once Monthly', 'glamlux-core'),
		);
		$schedules['every_minute'] = array(
			'interval' => 60,
			'display' => __('Every Minute', 'glamlux-core'),
		);
		return $schedules;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Membership Processing
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Find expired memberships and downgrade client roles.
	 * Fires monthly via single WP-Cron event.
	 * FIX 5 (P1): Raw SQL replaced with Service delegation — LLD compliant.
	 */
	public function process_memberships()
	{
		if (!apply_filters('glamlux_allow_cron_execution', true))
			return;

		if (!class_exists('GlamLux_Service_Membership')) {
			glamlux_log_error('Cron [Membership]: GlamLux_Service_Membership not found. Cannot process.');
			return;
		}

		$service = new GlamLux_Service_Membership();
		$count = $service->process_expired();

		glamlux_log_error(
			sprintf('Cron [Membership]: Processed %d expired memberships.', $count),
			array('count' => $count)
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Daily DB Backup
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Export a SQL dump of all GlamLux custom tables to the uploads folder.
	 * On Hostinger VPS / production, a cron job or Cloudflare R2 hook should
	 * pick up the file and sync it off-server.
	 *
	 * File format: glamlux-backup-YYYY-MM-DD-HHMMSS.sql
	 */
	public function run_db_backup()
	{
		if (!apply_filters('glamlux_allow_cron_execution', true))
			return;
		global $wpdb;

		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/glamlux-backups';

		// Create backup directory if it doesn't exist
		if (!file_exists($backup_dir)) {
			wp_mkdir_p($backup_dir);
			// Protect from direct web access
			file_put_contents($backup_dir . '/.htaccess', 'Deny from all');
		}

		$filename = 'glamlux-backup-' . gmdate('Y-m-d-His') . '.sql';
		$filepath = $backup_dir . '/' . $filename;
		$tables = array(
			'gl_franchises', 'gl_salons', 'gl_staff', 'gl_memberships',
			'gl_clients', 'gl_appointments', 'gl_payroll', 'gl_product_sales',
			'gl_service_logs', 'gl_financial_reports', 'gl_service_pricing', 'gl_inventory',
		);

		$sql_dump = "-- GlamLux2Lux DB Backup\n-- Generated: " . gmdate('Y-m-d H:i:s') . " UTC\n-- Tables: " . implode(', ', $tables) . "\n\n";
		$sql_dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

		foreach ($tables as $table_name) {
			$full_table = $wpdb->prefix . $table_name;

			// Capture CREATE TABLE
			$create = $wpdb->get_row("SHOW CREATE TABLE `{$full_table}`", ARRAY_N);
			if (!$create) {
				continue;
			}
			$sql_dump .= "-- ──────────────────────────────────────\n";
			$sql_dump .= "DROP TABLE IF EXISTS `{$full_table}`;\n";
			$sql_dump .= $create[1] . ";\n\n";

			// Capture all rows
			$rows = $wpdb->get_results("SELECT * FROM `{$full_table}`", ARRAY_A);
			if (!empty($rows)) {
				$sql_dump .= "INSERT INTO `{$full_table}` VALUES\n";
				$row_strings = array();
				foreach ($rows as $row) {
					$values = array_map(function ($val) use ($wpdb) {
						return is_null($val) ? 'NULL' : "'" . esc_sql($val) . "'";
					}, array_values($row));
					$row_strings[] = '(' . implode(', ', $values) . ')';
				}
				$sql_dump .= implode(",\n", $row_strings) . ";\n\n";
			}
		}

		$sql_dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

		// Write to disk
		$written = file_put_contents($filepath, $sql_dump);

		if (false === $written) {
			glamlux_log_error('Cron [Backup]: Failed to write backup file.', array('path' => $filepath));
			return;
		}

		// Prune backups older than 30 days
		$this->prune_old_backups($backup_dir, 30);

		// Fire hook for off-site sync (Cloudflare R2, S3, etc.)
		do_action('glamlux_backup_completed', $filepath, $filename);

		glamlux_log_error(
			sprintf('Cron [Backup]: Backup completed — %s (%s KB)', $filename, round($written / 1024, 1))
		);
	}

	/**
	 * Delete backup files older than $days days.
	 *
	 * @param string $dir   Backup directory path.
	 * @param int    $days  Retention window in days.
	 */
	private function prune_old_backups($dir, $days = 30)
	{
		$cutoff = time() - ($days * DAY_IN_SECONDS);
		foreach (glob($dir . '/*.sql') as $file) {
			if (filemtime($file) < $cutoff) {
				wp_delete_file($file);
			}
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Inventory Reorder Alert
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Check inventory levels across all salons and alert when stock
	 * falls below the reorder_threshold for any product.
	 */
	public function check_inventory_levels()
	{
		if (!apply_filters('glamlux_allow_cron_execution', true))
			return;
		global $wpdb;

		$low_stock = $wpdb->get_results(
			"SELECT i.*, s.name AS salon_name
			 FROM {$wpdb->prefix}gl_inventory i
			 INNER JOIN {$wpdb->prefix}gl_salons s ON i.salon_id = s.id
			 WHERE i.quantity <= i.reorder_threshold"
		);

		if (empty($low_stock)) {
			return;
		}

		foreach ($low_stock as $item) {
			// Fire action — can hook in email, SMS, or Slack notification
			do_action('glamlux_low_inventory_alert', $item);
		}

		glamlux_log_error(
			sprintf('Cron [Inventory]: %d low-stock item(s) detected.', count($low_stock))
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Monthly Payroll Processing
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Run the monthly payroll batch processor.
	 * FIX 4 (P1): After running, re-schedules itself for the NEXT 1st-of-month
	 * to avoid drift from fixed 30-day interval.
	 */
	public function run_payroll()
	{
		if (!apply_filters('glamlux_allow_cron_execution', true))
			return;

		if (class_exists('GlamLux_Service_Payroll')) {
			$service = new GlamLux_Service_Payroll();
			$service->run_monthly_batch();
		}

		// Reschedule for the 1st of next month at 01:00 AM
		$next_first = mktime(1, 0, 0, (int)date('n') + 1, 1, (int)date('Y'));
		wp_schedule_single_event($next_first, 'glamlux_monthly_payroll');

		glamlux_log_error(
			'Cron [Payroll]: Monthly payroll executed. Next run scheduled for: ' . date('Y-m-d H:i', $next_first)
		);
	}
}
