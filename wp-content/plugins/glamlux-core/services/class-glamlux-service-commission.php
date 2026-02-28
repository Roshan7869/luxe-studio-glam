<?php
class GlamLux_Service_Commission {
	public static function init() {
		add_action("glamlux_event_appointment_completed", [static::class, "handle_appointment_completed"]);
		add_action("glamlux_event_payment_completed", [static::class, "handle_appointment_completed"]);
	}
	public static function handle_appointment_completed($payload) {
		$aid = (int)($payload["appointment_id"] ?? 0);
		if (!$aid) return;
		$repo = new GlamLux_Repo_Payroll();
		$appointment = $repo->get_appointment_for_commission($aid);
		if (!$appointment || !$appointment->staff_id) return;
		if ($repo->has_commission_record($aid)) return;
		$rate = (float)($appointment->commission_rate ?? 0);
		$amount = (float)($appointment->amount ?? 0);
		$commission = round($amount * ($rate / 100), 2);
		$repo->insert_payroll_record([
			"staff_id" => $appointment->staff_id, "salon_id" => $appointment->salon_id,
			"appointment_id" => $aid, "period_start" => date("Y-m-01"), "period_end" => date("Y-m-t"),
			"total_services" => $amount, "commission_earned" => $commission, "status" => "pending"
		]);
	}
}