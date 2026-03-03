<?php
class GlamLux_Service_Attendance
{
	private $repo;
	public function __construct(GlamLux_Repo_Attendance $repo = null)
	{
		$this->repo = $repo ?: new GlamLux_Repo_Attendance();
	}
	public function check_in($staff_id, $salon_id)
	{
		$today = date("Y-m-d");
		if ($this->repo->get_attendance($staff_id, $today))
			return false;
		$shift = $this->repo->get_shift($staff_id, $today);
		$late = 0;
		$lmin = 0;
		if ($shift) {
			$sc = strtotime($today . " " . $shift->start_time);
			if (time() > $sc + 900) {
				$late = 1;
				$lmin = (int)((time() - $sc) / 60);
			}
		}
		$this->repo->insert_attendance([
			"staff_id" => (int)$staff_id, "salon_id" => (int)$salon_id, "shift_date" => $today,
			"check_in" => current_time("mysql"), "is_late" => $late, "late_minutes" => $lmin, "status" => "present",
			"check_out" => null, "hours_worked" => 0.00
		]);
		return true;
	}
	public function check_out($staff_id)
	{
		$today = date("Y-m-d");
		$rec = $this->repo->get_attendance($staff_id, $today);
		if (!$rec || $rec->check_out)
			return false;
		$this->repo->update_attendance($rec->id, [
			"check_out" => current_time("mysql"),
			"hours_worked" => round((time() - strtotime($rec->check_in)) / 3600, 2)
		]);
		return true;
	}
	public function get_monthly_summary($staff_id, $month)
	{
		[$year, $month_num] = $this->parse_month_parts($month);
		$rows = $this->repo->get_monthly_attendance($staff_id, $year, $month_num);
		return [
			"days" => count($rows),
			"late_days" => count(array_filter($rows, fn($r) => !empty($r["is_late"]))),
			"hours" => round(array_sum(array_column($rows, "hours_worked")), 2)
		];
	}

	private function parse_month_parts($month)
	{
		$month = is_string($month) ? trim($month) : '';
		if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month, $matches)) {
			throw new InvalidArgumentException('Invalid month format. Expected YYYY-MM.');
		}

		return [(int)$matches[1], (int)$matches[2]];
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Phase 4: Shift CRUD
	// ─────────────────────────────────────────────────────────────────────────

	public function create_shift($data)
	{
		global $wpdb;
		$required = ['staff_id', 'salon_id', 'shift_date', 'shift_start', 'shift_end'];
		foreach ($required as $k) {
			if (empty($data[$k])) {
				return new \WP_Error('missing_field', "Missing required field: {$k}");
			}
		}

		// Check for existing shift on this date for this staff
		$existing = $this->repo->get_shift((int)$data['staff_id'], sanitize_text_field($data['shift_date']));
		if ($existing) {
			return new \WP_Error('duplicate_shift', 'A shift already exists for this staff member on this date.');
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'gl_shifts',
		[
			'staff_id' => absint($data['staff_id']),
			'salon_id' => absint($data['salon_id']),
			'shift_date' => sanitize_text_field($data['shift_date']),
			'shift_start' => sanitize_text_field($data['shift_start']),
			'shift_end' => sanitize_text_field($data['shift_end']),
			'type' => sanitize_text_field($data['type'] ?? 'scheduled'),
		],
		['%d', '%d', '%s', '%s', '%s', '%s']
		);
		return $result ? $wpdb->insert_id : new \WP_Error('db_error', 'Failed to create shift.');
	}

	public function update_shift($shift_id, $data)
	{
		global $wpdb;
		$update = [];
		$formats = [];
		if (isset($data['shift_start'])) {
			$update['shift_start'] = sanitize_text_field($data['shift_start']);
			$formats[] = '%s';
		}
		if (isset($data['shift_end'])) {
			$update['shift_end'] = sanitize_text_field($data['shift_end']);
			$formats[] = '%s';
		}
		if (isset($data['type'])) {
			$update['type'] = sanitize_text_field($data['type']);
			$formats[] = '%s';
		}
		if (empty($update))
			return false;

		return $wpdb->update(
			$wpdb->prefix . 'gl_shifts',
			$update,
		['id' => absint($shift_id)],
			$formats,
		['%d']
		);
	}

	public function delete_shift($shift_id)
	{
		global $wpdb;
		return $wpdb->delete(
			$wpdb->prefix . 'gl_shifts',
		['id' => absint($shift_id)],
		['%d']
		);
	}

	public function get_shifts_for_salon($salon_id, $week_start, $week_end)
	{
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare(
			"SELECT s.id, s.staff_id, s.shift_date, s.shift_start, s.shift_end, s.type,
			        u.display_name AS staff_name, st.job_role
			 FROM {$wpdb->prefix}gl_shifts s
			 INNER JOIN {$wpdb->prefix}gl_staff st ON s.staff_id = st.id
			 LEFT JOIN {$wpdb->users} u ON st.wp_user_id = u.ID
			 WHERE s.salon_id = %d AND s.shift_date BETWEEN %s AND %s
			 ORDER BY s.shift_date ASC, s.shift_start ASC",
			absint($salon_id), $week_start, $week_end
		), ARRAY_A) ?: [];
	}
}
