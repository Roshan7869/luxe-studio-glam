<?php
/**
 * GlamLux Service Mailer — Sprint 5 (Expanded)
 *
 * Handles automated email dispatching on key domain events.
 * Covers: franchise leads, appointments, memberships, inventory alerts, payroll.
 */

class GlamLux_Service_Mailer
{
    private $dispatcher;

    public function __construct(GlamLux_Event_Dispatcher $dispatcher = null)
    {
        $this->dispatcher = $dispatcher;
        $this->register_listeners();
    }

    private function register_listeners()
    {
        // Lead capture (existing)
        add_action('glamlux_lead_captured', [$this, 'handle_franchise_lead'], 10, 2);

        // Appointment events
        add_action('glamlux_event_appointment_created', [$this, 'handle_appointment_booked'], 10, 1);

        // Membership events
        add_action('glamlux_membership_granted', [$this, 'handle_membership_granted'], 10, 3);

        // Payment events
        add_action('glamlux_event_payment_completed', [$this, 'handle_payment_completed'], 10, 1);

        // Inventory events
        add_action('glamlux_low_inventory_alert', [$this, 'handle_low_inventory'], 10, 1);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Franchise Lead (existing)
    // ─────────────────────────────────────────────────────────────────────────

    public function handle_franchise_lead(int $lead_id, array $lead_data)
    {
        $name = sanitize_text_field($lead_data['name'] ?? 'Applicant');
        $email = sanitize_email($lead_data['email'] ?? '');
        $state = sanitize_text_field($lead_data['state'] ?? 'Unknown');

        if (!$email)
            return;

        $this->send_applicant_confirmation($email, $name);
        $this->send_admin_alert($lead_id, $lead_data);
    }

    private function send_applicant_confirmation(string $email, string $name)
    {
        $subject = 'GlamLux2Lux Franchise Application Received';
        $body = $this->wrap_template(
            "Welcome to GlamLux2Lux, {$name}.",
            '<p>Thank you for submitting your preliminary franchise application. We have successfully received your details.</p>
             <p>A regional franchise director will review your submission and contact you within the next 24 hours.</p>'
        );
        wp_mail($email, $subject, $body, $this->html_headers());
    }

    private function send_admin_alert(int $lead_id, array $data)
    {
        $admin_email = get_option('admin_email');
        $subject = "[Urgent] New Franchise Lead: {$data['name']}";
        $body = $this->wrap_template(
            'New Franchise Lead Generated',
            sprintf(
            '<p><strong>Lead ID:</strong> %d</p>
                 <p><strong>Name:</strong> %s</p>
                 <p><strong>Email:</strong> %s</p>
                 <p><strong>State:</strong> %s</p>
                 <p><a href="%s">View in CRM</a></p>',
            $lead_id, esc_html($data['name']), esc_html($data['email']),
            esc_html($data['state'] ?? 'Unknown'), admin_url('admin.php?page=gl-franchise-leads')
        )
        );
        wp_mail($admin_email, $subject, $body, $this->html_headers());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Appointment Booked (Sprint 5)
    // ─────────────────────────────────────────────────────────────────────────

    public function handle_appointment_booked($payload)
    {
        $apt_id = $payload['appointment_id'] ?? 0;
        if (!$apt_id)
            return;

        global $wpdb;
        $apt = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, c.wp_user_id, s.name AS salon_name
             FROM {$wpdb->prefix}gl_appointments a
             LEFT JOIN {$wpdb->prefix}gl_clients c ON a.client_id = c.id
             LEFT JOIN {$wpdb->prefix}gl_salons s ON a.salon_id = s.id
             WHERE a.id = %d", $apt_id
        ));
        if (!$apt || !$apt->wp_user_id)
            return;

        $user = get_userdata($apt->wp_user_id);
        if (!$user || !$user->user_email)
            return;

        $date = date('l, M j, Y \a\t g:i A', strtotime($apt->appointment_date));
        $body = $this->wrap_template(
            'Appointment Confirmed ✨',
            sprintf(
            '<p>Dear %s,</p>
                 <p>Your appointment at <strong>%s</strong> on <strong>%s</strong> has been confirmed.</p>
                 <p>Appointment ID: #%d</p>',
            esc_html($user->display_name), esc_html($apt->salon_name ?? 'GlamLux'),
            esc_html($date), $apt_id
        )
        );
        wp_mail($user->user_email, 'GlamLux Appointment Confirmed', $body, $this->html_headers());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Membership Granted (Sprint 5)
    // ─────────────────────────────────────────────────────────────────────────

    public function handle_membership_granted($cid, $mid, $expiry)
    {
        global $wpdb;
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, u.display_name, u.user_email, mt.name AS tier_name
             FROM {$wpdb->prefix}gl_clients c
             INNER JOIN {$wpdb->users} u ON c.wp_user_id = u.ID
             LEFT JOIN {$wpdb->prefix}gl_membership_tiers mt ON mt.id = %d
             WHERE c.id = %d", $mid, $cid
        ));
        if (!$client || !$client->user_email)
            return;

        $body = $this->wrap_template(
            'Membership Activated 💎',
            sprintf(
            '<p>Dear %s,</p>
                 <p>Your <strong>%s</strong> membership is now active until <strong>%s</strong>.</p>
                 <p>Enjoy exclusive discounts on all services!</p>',
            esc_html($client->display_name), esc_html($client->tier_name ?? 'Premium'),
            esc_html(date('M j, Y', strtotime($expiry)))
        )
        );
        wp_mail($client->user_email, 'GlamLux Membership Activated', $body, $this->html_headers());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Payment Completed (Sprint 5)
    // ─────────────────────────────────────────────────────────────────────────

    public function handle_payment_completed($payload)
    {
        $apt_id = $payload['appointment_id'] ?? 0;
        $amount = $payload['amount'] ?? 0;
        if (!$apt_id)
            return;

        global $wpdb;
        $apt = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, c.wp_user_id, s.name AS salon_name
             FROM {$wpdb->prefix}gl_appointments a
             LEFT JOIN {$wpdb->prefix}gl_clients c ON a.client_id = c.id
             LEFT JOIN {$wpdb->prefix}gl_salons s ON a.salon_id = s.id
             WHERE a.id = %d", $apt_id
        ));
        if (!$apt || !$apt->wp_user_id)
            return;

        $user = get_userdata($apt->wp_user_id);
        if (!$user || !$user->user_email)
            return;

        $body = $this->wrap_template(
            'Payment Receipt 🧾',
            sprintf(
            '<p>Dear %s,</p>
                 <p>Payment of <strong>₹%s</strong> for appointment #%d at %s has been received.</p>
                 <p>Thank you for choosing GlamLux!</p>',
            esc_html($user->display_name), number_format($amount / 100, 2),
            $apt_id, esc_html($apt->salon_name ?? 'GlamLux')
        )
        );
        wp_mail($user->user_email, 'GlamLux Payment Received', $body, $this->html_headers());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Low Inventory (Sprint 5)
    // ─────────────────────────────────────────────────────────────────────────

    public function handle_low_inventory($item)
    {
        $admin_email = get_option('admin_email');
        $name = $item['product_name'] ?? 'Unknown';
        $salon = $item['salon_name'] ?? 'Unknown';

        $body = $this->wrap_template(
            '⚠️ Low Inventory Alert',
            sprintf(
            '<p><strong>Product:</strong> %s</p>
                 <p><strong>Salon:</strong> %s</p>
                 <p><strong>Stock:</strong> %d (Reorder at: %d)</p>
                 <p><a href="%s">Manage Inventory →</a></p>',
            esc_html($name), esc_html($salon),
            (int)($item['quantity'] ?? 0), (int)($item['reorder_threshold'] ?? 0),
            admin_url('admin.php?page=glamlux-inventory')
        )
        );
        wp_mail($admin_email, "[GlamLux] Low Stock: {$name}", $body, $this->html_headers());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function html_headers()
    {
        return ['Content-Type: text/html; charset=UTF-8', 'From: GlamLux2Lux <no-reply@glamlux2lux.local>'];
    }

    private function wrap_template(string $heading, string $content): string
    {
        return sprintf(
            '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#121212;">
                <h2 style="color:#121212;">%s</h2>
                %s
                <hr style="border:0;border-top:1px solid #EAEAEA;margin:24px 0;">
                <p style="font-size:0.8rem;color:#6A6A6A;">GlamLux2Lux Enterprise Team</p>
            </div>',
            $heading, $content
        );
    }
}
