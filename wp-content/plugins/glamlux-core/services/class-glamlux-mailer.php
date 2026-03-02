<?php
/**
 * GlamLux Service Mailer — Enterprise Real-Time Communications Sync
 * Handles automated email dispatching on key domain events.
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
        // Hook into the core WP action dispatched by GlamLux_Service_Lead
        add_action('glamlux_lead_captured', [$this, 'handle_franchise_lead'], 10, 2);
    }

    /**
     * Dispatches notifications when a new franchise lead is captured.
     * 
     * @param int $lead_id
     * @param array $lead_data
     */
    public function handle_franchise_lead(int $lead_id, array $lead_data)
    {
        $name = sanitize_text_field($lead_data['name'] ?? 'Applicant');
        $email = sanitize_email($lead_data['email'] ?? '');
        $state = sanitize_text_field($lead_data['state'] ?? 'Unknown');

        if (!$email) {
            return; // Can't send without email
        }

        // 1. Send Auto-Responder to Applicant
        $this->send_applicant_confirmation($email, $name);

        // 2. Send Alert to Administration/Regional Director
        $this->send_admin_alert($lead_id, $lead_data);
    }

    private function send_applicant_confirmation(string $email, string $name)
    {
        $subject = 'GlamLux2Lux Franchise Application Received';
        $headers = ['Content-Type: text/html; charset=UTF-8', 'From: GlamLux2Lux Enterprise <no-reply@glamlux2lux.local>'];

        $body = "
		<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #121212;'>
			<h2 style='color: #121212;'>Welcome to GlamLux2Lux, {$name}.</h2>
			<p>Thank you for submitting your preliminary franchise application. We have successfully received your details.</p>
			<p>A regional franchise director will review your submission and contact you within the next 24 hours to discuss the next steps.</p>
			<hr style='border: 0; border-top: 1px solid #EAEAEA; margin: 24px 0;' />
			<p style='font-size: 0.8rem; color: #6A6A6A;'>GlamLux2Lux Enterprise Team</p>
		</div>";

        wp_mail($email, $subject, $body, $headers);
    }

    private function send_admin_alert(int $lead_id, array $data)
    {
        $admin_email = get_option('admin_email');
        $subject = "[Urgent] New Franchise Lead Captured: {$data['name']}";
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $body = "
		<div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #CCC;'>
			<h3 style='margin-top:0;'>New Franchise Lead Generated</h3>
			<p><strong>Lead ID:</strong> {$lead_id}</p>
			<p><strong>Name:</strong> {$data['name']}</p>
			<p><strong>Email:</strong> {$data['email']}</p>
			<p><strong>State/Territory:</strong> {$data['state']}</p>
			<p><a href='" . admin_url('admin.php?page=gl-franchise-leads') . "'>View in CRM</a></p>
		</div>";

        wp_mail($admin_email, $subject, $body, $headers);
    }
}
