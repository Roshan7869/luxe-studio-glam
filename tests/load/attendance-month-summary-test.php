<?php

require_once __DIR__ . '/../../wp-content/plugins/glamlux-core/repositories/class-glamlux-repo-attendance.php';
require_once __DIR__ . '/../../wp-content/plugins/glamlux-core/services/class-glamlux-service-attendance.php';

class FakeAttendanceRepo extends GlamLux_Repo_Attendance
{
    public $calls = [];

    public function get_monthly_attendance($staff_id, $year, $month)
    {
        $this->calls[] = [
            'staff_id' => $staff_id,
            'year' => $year,
            'month' => $month,
        ];

        return [
            ['is_late' => 1, 'hours_worked' => 8.25],
            ['is_late' => 0, 'hours_worked' => 7.75],
            ['is_late' => 1, 'hours_worked' => 6.00],
        ];
    }
}

function assert_true($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$repo = new FakeAttendanceRepo();
$service = new GlamLux_Service_Attendance($repo);
$summary = $service->get_monthly_summary(12, '2026-03');

assert_true(count($repo->calls) === 1, 'Expected repository to be called exactly once.');
assert_true($repo->calls[0]['staff_id'] === 12, 'Expected staff_id to be forwarded unchanged.');
assert_true($repo->calls[0]['year'] === 2026, 'Expected year to be parsed from YYYY-MM.');
assert_true($repo->calls[0]['month'] === 3, 'Expected month to be parsed as integer from YYYY-MM.');
assert_true($summary['days'] === 3, 'Expected day count to equal attendance rows.');
assert_true($summary['late_days'] === 2, 'Expected late day count to aggregate is_late rows.');
assert_true(abs($summary['hours'] - 22.0) < 0.00001, 'Expected hours total to be rounded aggregate.');

$invalid_inputs = ['2026-3', '26-03', '2026/03', '2026-13', '', null];
foreach ($invalid_inputs as $input) {
    try {
        $service->get_monthly_summary(12, $input);
        throw new RuntimeException('Expected InvalidArgumentException for invalid month input.');
    } catch (InvalidArgumentException $e) {
        // expected
    }
}

echo "attendance-month-summary-test: PASS\n";
