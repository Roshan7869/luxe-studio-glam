<?php
class GlamLux_Service_Revenue
{
	private $repo;
	public function __construct(GlamLux_Repo_Revenue $repo = null)
	{
		$this->repo = $repo ?: new GlamLux_Repo_Revenue();
	}

	public function handle_appointment_completed($p)
	{
		$aid = (int)($p["appointment_id"] ?? 0);
		if (!$aid)
			return;
		$amount = $this->repo->get_appointment_amount($aid);
		if (!$amount)
			return;
		$this->repo->increment_revenue_metric($amount);
	}

	public function handle_payment_completed($p)
	{
		$this->handle_appointment_completed($p);
	}

	public function get_metrics()
	{
		$rows = $this->repo->get_all_metrics();
		return array_column($rows, "metric_value", "metric_key");
	}

	public function refresh_metrics_cache()
	{
		$rev = $this->repo->get_aggregate_revenue();
		$clients = $this->repo->get_aggregate_clients();
		$bookings = $this->repo->get_aggregate_bookings();
		$this->repo->upsert_metric("total_revenue", $rev);
		$this->repo->upsert_metric("active_clients", $clients);
		$this->repo->upsert_metric("total_bookings", $bookings);
	}

	/**
	 * FIX 2 (P0): Added missing method — used by Cache Warm button.
	 * Returns aggregated revenue totals for a given date range.
	 *
	 * @param string $date_from Y-m-d format
	 * @param string $date_to   Y-m-d format
	 * @return array { total_revenue, booking_count, avg_booking_value }
	 */
	public function get_period_summary(string $date_from, string $date_to): array
	{
		global $wpdb;
		// Cache key tied to date range — avoids repeated full-table reads
		$cache_key = 'gl_period_summary_' . md5($date_from . $date_to);
		$cached = get_transient($cache_key);
		if ($cached !== false) {
			return $cached;
		}

		$result = $wpdb->get_row(
			$wpdb->prepare(
			"SELECT
					COALESCE(SUM(amount), 0)      AS total_revenue,
					COUNT(id)                      AS booking_count,
					COALESCE(AVG(amount), 0)       AS avg_booking_value
				 FROM {$wpdb->prefix}gl_appointments
				 WHERE status = 'completed'
				 AND DATE(appointment_time) BETWEEN %s AND %s",
			$date_from,
			$date_to
		),
			ARRAY_A
		) ?: ['total_revenue' => 0, 'booking_count' => 0, 'avg_booking_value' => 0];

		set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
		return $result;
	}

	/**
	 * FIX 2 (P0): Added missing method — used by Cache Warm button.
	 * Returns monthly revenue trend for the last N months.
	 * Uses YEAR()/MONTH() instead of DATE_FORMAT() to allow index usage.
	 *
	 * @param int $months Number of months to look back
	 * @return array [ { year, month, total_revenue, booking_count } ]
	 */
	public function get_monthly_trend(int $months = 6): array
	{
		global $wpdb;
		$cache_key = 'gl_monthly_trend_' . $months;
		$cached = get_transient($cache_key);
		if ($cached !== false) {
			return $cached;
		}

		$result = $wpdb->get_results(
			$wpdb->prepare(
			"SELECT
					YEAR(appointment_time)  AS year,
					MONTH(appointment_time) AS month,
					COALESCE(SUM(amount), 0) AS total_revenue,
					COUNT(id)                AS booking_count
				 FROM {$wpdb->prefix}gl_appointments
				 WHERE status = 'completed'
				 AND appointment_time >= DATE_SUB(NOW(), INTERVAL %d MONTH)
				 GROUP BY YEAR(appointment_time), MONTH(appointment_time)
				 ORDER BY year ASC, month ASC",
			absint($months)
		),
			ARRAY_A
		) ?: [];

		set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
		return $result;
	}

	/**
	 * Alias for get_period_summary — used by GlamLux_Reports_Controller.
	 */
	public function get_revenue_summary(string $date_from, string $date_to): array
	{
		return $this->get_period_summary($date_from, $date_to);
	}

	/**
	 * Revenue breakdown per salon for a date range.
	 * Delegates to GlamLux_Repo_Revenue -> GlamLux_Repo_Franchise.
	 */
	public function get_revenue_by_salon(string $date_from, string $date_to): array
	{
		global $wpdb;
		$cache_key = 'gl_rev_by_salon_' . md5($date_from . $date_to);
		$cached = get_transient($cache_key);
		if ($cached !== false) {
			return $cached;
		}

		$result = $wpdb->get_results(
			$wpdb->prepare(
			"SELECT a.salon_id,
					sl.name AS salon_name,
					COUNT(a.id)              AS bookings,
					COALESCE(SUM(a.amount), 0) AS revenue
				 FROM {$wpdb->prefix}gl_appointments a
				 LEFT JOIN {$wpdb->prefix}gl_salons sl ON a.salon_id = sl.id
				 WHERE a.status = 'completed'
				   AND DATE(a.appointment_time) BETWEEN %s AND %s
				 GROUP BY a.salon_id, sl.name
				 ORDER BY revenue DESC",
			$date_from, $date_to
		),
			ARRAY_A
		) ?: [];

		set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
		return $result;
	}
}
