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
}
