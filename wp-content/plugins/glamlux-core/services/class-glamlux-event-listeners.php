<?php
/**
 * Event Listeners for side effects (Phase 7).
 * Rules: Idempotent, fail silently, log errors, no new domain events dispatched.
 */
class GlamLux_Event_Listeners
{
    public static function on_appointment_created($payload)
    {
        try {
            glamlux_log_error('Event: appointment_created processing', $payload);
        // Emulate sending SMS/Email notification.
        }
        catch (Throwable $e) {
            glamlux_log_error('Error in on_appointment_created', ['err' => $e->getMessage()]);
        }
    }

    public static function on_membership_granted($payload)
    {
        try {
            glamlux_log_error('Event: membership_granted processing', $payload);
        }
        catch (Throwable $e) {
            glamlux_log_error('Error in on_membership_granted', ['err' => $e->getMessage()]);
        }
    }

    public static function on_payment_captured($payload)
    {
        try {
            glamlux_log_error('Event: payment_captured processing', $payload);
            if (!empty($payload['appointment_id'])) {
                // Confirm payment status using our Phase 6 state machine
                $service = new GlamLux_Service_Booking();
                $service->confirm_payment($payload['appointment_id']);
            }
        }
        catch (Throwable $e) {
            glamlux_log_error('Error in on_payment_captured', ['err' => $e->getMessage()]);
        }
    }

    public static function on_low_inventory($payload)
    {
        try {
            glamlux_log_error('Event: low_inventory_alert processing', $payload);
        }
        catch (Throwable $e) {
            glamlux_log_error('Error in on_low_inventory', ['err' => $e->getMessage()]);
        }
    }
}
