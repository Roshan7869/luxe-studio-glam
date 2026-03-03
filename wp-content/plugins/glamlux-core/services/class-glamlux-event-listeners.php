<?php
/**
 * Event Listeners — Sprint 5
 *
 * Real side effects: email + SMS notifications for domain events.
 * Rules: Idempotent, fail silently, log errors, no new domain events dispatched.
 */
class GlamLux_Event_Listeners
{
    /**
     * Appointment created → send confirmation email + SMS to client.
     */
    public static function on_appointment_created($payload)
    {
        try {
            glamlux_log_error('Event: appointment_created processing', $payload);

            $appointment_id = $payload['appointment_id'] ?? 0;
            if (!$appointment_id)
                return;

            global $wpdb;
            $apt = $wpdb->get_row($wpdb->prepare(
                "SELECT a.*, c.wp_user_id, s.name AS salon_name
                 FROM {$wpdb->prefix}gl_appointments a
                 LEFT JOIN {$wpdb->prefix}gl_clients c ON a.client_id = c.id
                 LEFT JOIN {$wpdb->prefix}gl_salons s ON a.salon_id = s.id
                 WHERE a.id = %d", $appointment_id
            ));

            if (!$apt || !$apt->wp_user_id)
                return;

            $user = get_userdata($apt->wp_user_id);
            if (!$user || !$user->user_email)
                return;

            $date_formatted = date('l, M j Y \a\t g:i A', strtotime($apt->appointment_date));

            // Email notification
            $subject = 'Your GlamLux Appointment is Confirmed';
            $body = sprintf(
                '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
                    <h2 style="color:#121212;">Appointment Confirmed ✨</h2>
                    <p>Dear %s,</p>
                    <p>Your appointment at <strong>%s</strong> on <strong>%s</strong> has been confirmed.</p>
                    <p>Appointment ID: #%d</p>
                    <hr style="border:0;border-top:1px solid #eaeaea;margin:20px 0;">
                    <p style="font-size:0.8rem;color:#6a6a6a;">GlamLux2Lux Enterprise</p>
                </div>',
                esc_html($user->display_name),
                esc_html($apt->salon_name ?? 'GlamLux'),
                esc_html($date_formatted),
                $appointment_id
            );
            wp_mail($user->user_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);

            // SMS notification
            $phone = get_user_meta($apt->wp_user_id, 'billing_phone', true);
            if ($phone) {
                require_once GLAMLUX_PLUGIN_DIR . 'includes/class-glamlux-exotel-api.php';
                $sms = new GlamLux_Exotel_API();
                $sms->send_sms($phone, sprintf(
                    'GlamLux: Your appointment #%d at %s on %s is confirmed!',
                    $appointment_id, $apt->salon_name ?? 'GlamLux', date('M j, g:i A', strtotime($apt->appointment_date))
                ));
            }
        }
        catch (\Throwable $e) {
            glamlux_log_error('Error in on_appointment_created', ['err' => $e->getMessage()]);
        }
    }

    /**
     * Membership granted → send welcome email + SMS.
     */
    public static function on_membership_granted($payload)
    {
        try {
            glamlux_log_error('Event: membership_granted processing', $payload);

            $client_id = $payload['client_id'] ?? ($payload[0] ?? 0);
            if (!$client_id)
                return;

            global $wpdb;
            $client = $wpdb->get_row($wpdb->prepare(
                "SELECT c.*, u.display_name, u.user_email, mt.name AS tier_name
                 FROM {$wpdb->prefix}gl_clients c
                 INNER JOIN {$wpdb->users} u ON c.wp_user_id = u.ID
                 LEFT JOIN {$wpdb->prefix}gl_membership_tiers mt ON c.membership_id = mt.id
                 WHERE c.id = %d", $client_id
            ));

            if (!$client || !$client->user_email)
                return;

            $subject = 'Welcome to GlamLux Membership! 💎';
            $body = sprintf(
                '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
                    <h2 style="color:#121212;">Membership Activated</h2>
                    <p>Dear %s,</p>
                    <p>Your <strong>%s</strong> membership is now active until <strong>%s</strong>.</p>
                    <p>Enjoy exclusive discounts on all services!</p>
                    <hr style="border:0;border-top:1px solid #eaeaea;margin:20px 0;">
                    <p style="font-size:0.8rem;color:#6a6a6a;">GlamLux2Lux Enterprise</p>
                </div>',
                esc_html($client->display_name),
                esc_html($client->tier_name ?? 'Premium'),
                esc_html(date('M j, Y', strtotime($client->membership_expiry)))
            );
            wp_mail($client->user_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);

            // SMS
            $phone = get_user_meta($client->wp_user_id, 'billing_phone', true);
            if ($phone) {
                require_once GLAMLUX_PLUGIN_DIR . 'includes/class-glamlux-exotel-api.php';
                $sms = new GlamLux_Exotel_API();
                $sms->send_sms($phone, sprintf(
                    'Welcome to GlamLux %s membership! Valid until %s. Enjoy exclusive benefits!',
                    $client->tier_name ?? 'Premium', date('M j, Y', strtotime($client->membership_expiry))
                ));
            }
        }
        catch (\Throwable $e) {
            glamlux_log_error('Error in on_membership_granted', ['err' => $e->getMessage()]);
        }
    }

    /**
     * Payment captured → confirm booking + send receipt email.
     */
    public static function on_payment_captured($payload)
    {
        try {
            glamlux_log_error('Event: payment_captured processing', $payload);

            if (!empty($payload['appointment_id'])) {
                $service = new GlamLux_Service_Booking();
                $service->confirm_payment($payload['appointment_id']);

                // Send payment receipt
                global $wpdb;
                $apt = $wpdb->get_row($wpdb->prepare(
                    "SELECT a.*, c.wp_user_id, s.name AS salon_name
                     FROM {$wpdb->prefix}gl_appointments a
                     LEFT JOIN {$wpdb->prefix}gl_clients c ON a.client_id = c.id
                     LEFT JOIN {$wpdb->prefix}gl_salons s ON a.salon_id = s.id
                     WHERE a.id = %d", $payload['appointment_id']
                ));

                if ($apt && $apt->wp_user_id) {
                    $user = get_userdata($apt->wp_user_id);
                    if ($user && $user->user_email) {
                        $amount = isset($payload['amount']) ? number_format($payload['amount'] / 100, 2) : 'N/A';
                        $body = sprintf(
                            '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
                                <h2 style="color:#121212;">Payment Receipt 🧾</h2>
                                <p>Dear %s,</p>
                                <p>Payment of <strong>₹%s</strong> for appointment #%d at %s has been received.</p>
                                <hr style="border:0;border-top:1px solid #eaeaea;margin:20px 0;">
                                <p style="font-size:0.8rem;color:#6a6a6a;">GlamLux2Lux Enterprise</p>
                            </div>',
                            esc_html($user->display_name), $amount, $payload['appointment_id'],
                            esc_html($apt->salon_name ?? 'GlamLux')
                        );
                        wp_mail($user->user_email, 'GlamLux Payment Received', $body, ['Content-Type: text/html; charset=UTF-8']);
                    }
                }
            }
        }
        catch (\Throwable $e) {
            glamlux_log_error('Error in on_payment_captured', ['err' => $e->getMessage()]);
        }
    }

    /**
     * Low inventory → alert salon admin via email.
     */
    public static function on_low_inventory($payload)
    {
        try {
            glamlux_log_error('Event: low_inventory_alert processing', $payload);

            $item_name = $payload['product_name'] ?? 'Unknown';
            $salon_name = $payload['salon_name'] ?? 'Unknown Salon';
            $qty = $payload['quantity'] ?? 0;
            $threshold = $payload['reorder_threshold'] ?? 0;

            $admin_email = get_option('admin_email');
            $body = sprintf(
                '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #ccc;padding:20px;">
                    <h3 style="margin-top:0;color:#C62828;">⚠️ Low Inventory Alert</h3>
                    <p><strong>Product:</strong> %s</p>
                    <p><strong>Salon:</strong> %s</p>
                    <p><strong>Current Stock:</strong> %d (Reorder at: %d)</p>
                    <p><a href="%s">Manage Inventory →</a></p>
                </div>',
                esc_html($item_name), esc_html($salon_name), (int)$qty, (int)$threshold,
                admin_url('admin.php?page=glamlux-inventory')
            );
            wp_mail($admin_email, "[GlamLux] Low Stock: {$item_name} at {$salon_name}", $body, ['Content-Type: text/html; charset=UTF-8']);
        }
        catch (\Throwable $e) {
            glamlux_log_error('Error in on_low_inventory', ['err' => $e->getMessage()]);
        }
    }
}
