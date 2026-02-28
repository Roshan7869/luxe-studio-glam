<?php
class GlamLux_Repo_Attendance
{

	public function get_attendance($staff_id, $date)
	{
		global $wpdb;
		return $wpdb->get_row($wpdb->prepare(
			"SELECT id, staff_id, shift_date, check_in, check_out, status
			 FROM {$wpdb->prefix}gl_attendance
			 WHERE staff_id = %d AND shift_date = %s",
			$staff_id, $date
		));
	}

	public function get_shift($staff_id, $date)
	{
		global $wpdb;
		return $wpdb->get_row($wpdb->prepare(
			"SELECT id, staff_id, shift_date, start_time, end_time, status
			 FROM {$wpdb->prefix}gl_shifts
			 WHERE staff_id = %d AND shift_date = %s",
			$staff_id, $date
		));
	}

	public function insert_attendance($data)
	{
		global $wpdb;
		return $wpdb->insert(
			$wpdb->prefix . 'gl_attendance',
			$data,
		['%d', '%d', '%s', '%s', '%d', '%d', '%s']
		);
	}

	public function update_attendance($id, $data)
	{
		global $wpdb;
		return $wpdb->update(
			$wpdb->prefix . 'gl_attendance',
			$data,
		['id' => absint($id)],
			null,
		['%d']
		);
	}

	/**
	 * AUDIT FIX: Replaced DATE_FORMAT(shift_date,'%%Y-%%m')=%s with
	 * YEAR/MONTH predicates so the index on shift_date is used.
	 * Accepts $year (int) and $month (int) separately.
	 *
	 * @param int $staff_id
	 * @param int $year   e.g. 2026
	 * @param int $month  e.g. 3
	 * @return array
	 */
	public function get_monthly_attendance($staff_id, $year, $month)
	{
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare(
			"SELECT id, staff_id, shift_date, check_in, check_out, status, is_late, hours_worked
			 FROM {$wpdb->prefix}gl_attendance
			 WHERE staff_id = %d
			   AND YEAR(shift_date)  = %d
			   AND MONTH(shift_date) = %d
			 ORDER BY shift_date ASC",
			absint($staff_id),
			absint($year),
			absint($month)
		), ARRAY_A);
	}
}
