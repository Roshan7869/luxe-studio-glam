<?php
/**
 * Razorpay Payment Service
 *
 * Handles the full payment lifecycle:
 * - Order creation
 * - Payment signature verification
 * - Webhook event processing
 * - Appointment payment status updates
 * - Event dispatch post-payment
 */
class GlamLux_Service_Payment
{

    /** @var string Razorpay Key ID */
    private string $key_id;

    /** @var string Razorpay Key Secret */
    private string $key_secret;

    /** @var string Razorpay Webhook Secret */
    private string $webhook_secret;

    /** @var GlamLux_Event_Dispatcher */
    private GlamLux_Event_Dispatcher $dispatcher;

    public function __construct(GlamLux_Event_Dispatcher $dispatcher)
    {
        $this->key_id = get_option('glamlux_razorpay_key_id', '');
        $this->key_secret = get_option('glamlux_razorpay_key_secret', '');
        $this->webhook_secret = get_option('glamlux_razorpay_webhook_secret', '');
        $this->dispatcher = $dispatcher;

        // Register REST webhook route
        add_action('rest_api_init', array($this, 'register_webhook_route'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Webhook Registration
    // ─────────────────────────────────────────────────────────────────────────

    public function register_webhook_route()
    {
        register_rest_route(
            'glamlux/v1',
            '/payments/webhook',
            array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true', // Razorpay sends no auth header
        )
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create Razorpay Order
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a Razorpay order and return it for the frontend to open the checkout.
     *
     * @param int    $appointment_id  Your internal appointment ID.
     * @param float  $amount_rupees   Amount in ₹.
     * @param string $receipt        Short receipt string.
     * @return array|WP_Error  Razorpay order array or WP_Error.
     */
    public function create_order(int $appointment_id, float $amount_rupees, string $receipt = ''): array |WP_Error
    {
        if (empty($this->key_id) || empty($this->key_secret)) {
            return new WP_Error('glamlux_payment_config', 'Razorpay API keys are not configured.');
        }

        $payload = array(
            'amount' => (int)($amount_rupees * 100), // paise
            'currency' => 'INR',
            'receipt' => $receipt ?: 'gl_appt_' . $appointment_id,
            'notes' => array(
                'appointment_id' => $appointment_id,
                'source' => 'glamlux2lux',
            ),
        );

        $response = wp_remote_post(
            'https://api.razorpay.com/v1/orders',
            array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->key_id . ':' . $this->key_secret),
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($payload),
            'timeout' => 15,
        )
        );

        if (is_wp_error($response)) {
            glamlux_log_error('[Payment] Razorpay order create failed: ' . $response->get_error_message());
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['id'])) {
            return new WP_Error('glamlux_payment_api', $body['error']['description'] ?? 'Razorpay order creation failed.');
        }

        return $body;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Webhook Handler
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Receive and verify Razorpay webhook events.
     * Verifies HMAC-SHA256 signature before processing any event.
     */
    public function handle_webhook(WP_REST_Request $request): WP_REST_Response
    {
        $raw_body = $request->get_body();
        $signature = $request->get_header('x-razorpay-signature');

        // ── 1. Signature verification (CRITICAL — never skip) ────────────────
        if (!$this->verify_webhook_signature($raw_body, $signature)) {
            glamlux_log_error('[Payment] Webhook signature verification failed.');
            return rest_ensure_response(array('status' => 'signature_mismatch'));
        }

        $event = json_decode($raw_body, true);

        // ── 2. Route by event type ───────────────────────────────────────────
        switch ($event['event'] ?? '') {
            case 'payment.captured':
                $this->on_payment_captured($event['payload']['payment']['entity']);
                break;

            case 'payment.failed':
                $this->on_payment_failed($event['payload']['payment']['entity']);
                break;

            case 'order.paid':
                // Full order paid — redundant with payment.captured but log it
                glamlux_log_error('[Payment] order.paid received for ' . ($event['payload']['order']['entity']['id'] ?? 'unknown'));
                break;

            default:
                glamlux_log_error('[Payment] Unhandled webhook event: ' . ($event['event'] ?? 'none'));
        }

        return rest_ensure_response(array('status' => 'ok'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Signature Verification
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verify Razorpay webhook signature using HMAC-SHA256.
     *
     * @param string $raw_body   Raw POST body.
     * @param string $signature  Signature from 'x-razorpay-signature' header.
     * @return bool
     */
    public function verify_webhook_signature(string $raw_body, ?string $signature): bool
    {
        if (empty($this->webhook_secret) || empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $raw_body, $this->webhook_secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Verify Razorpay payment response signature (frontend redirect).
     * Used when customer returns to success page.
     *
     * @param string $payment_id  razorpay_payment_id from response.
     * @param string $order_id    razorpay_order_id from response.
     * @param string $signature   razorpay_signature from response.
     * @return bool
     */
    public function verify_payment_signature(string $payment_id, string $order_id, string $signature): bool
    {
        $expected = hash_hmac('sha256', $order_id . '|' . $payment_id, $this->key_secret);
        return hash_equals($expected, $signature);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Event Handlers
    // ─────────────────────────────────────────────────────────────────────────

    private function on_payment_captured(array $payment): void
    {

        $razorpay_order_id = $payment['order_id'] ?? '';
        $notes = $payment['notes'] ?? array();
        $appointment_id = (int)($notes['appointment_id'] ?? 0);

        if (!$appointment_id) {
            return;
        }

        // Mark appointment as paid
        $repo = new GlamLux_Repo_Appointment();
        $repo->update_payment_status($appointment_id, 'paid', 'confirmed');

        // Dispatch domain event → triggers CommissionService, InventoryService, NotificationService
        $this->dispatcher->dispatch('payment_captured', array(
            'appointment_id' => $appointment_id,
            'razorpay_order_id' => $razorpay_order_id,
            'amount_paise' => $payment['amount'],
            'payment_id' => $payment['id'],
        ));

        glamlux_log_error("[Payment] Appointment {$appointment_id} marked PAID via Razorpay {$payment['id']}");
    }

    private function on_payment_failed(array $payment): void
    {

        $notes = $payment['notes'] ?? array();
        $appointment_id = (int)($notes['appointment_id'] ?? 0);

        if (!$appointment_id) {
            return;
        }

        $repo = new GlamLux_Repo_Appointment();
        $repo->update_payment_status($appointment_id, 'failed', 'cancelled');

        $this->dispatcher->dispatch('payment_failed', array('appointment_id' => $appointment_id));
    }
}
