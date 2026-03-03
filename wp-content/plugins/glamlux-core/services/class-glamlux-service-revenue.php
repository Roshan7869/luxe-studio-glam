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
		$cache_key = 'gl_period_summary_' . md5($date_from . $date_to) . '_blog_' . get_current_blog_id();
		$cached = get_transient($cache_key);
		if ($cached !== false) {
			return $cached;
		}

		$result = $this->repo->get_period_summary($date_from, $date_to);

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
		$cache_key = 'gl_monthly_trend_' . $months . '_blog_' . get_current_blog_id();
		$cached = get_transient($cache_key);
		if ($cached !== false) {
			return $cached;
		}

		$result = $this->repo->get_monthly_trend($months);

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
		$cache_key = 'gl_rev_by_salon_' . md5($date_from . $date_to) . '_blog_' . get_current_blog_id();
		$cached = get_transient($cache_key);
		if ($cached !== false) {
			return $cached;
		}

		$result = $this->repo->get_revenue_by_salon($date_from, $date_to);

		set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
		return $result;
	}
}
