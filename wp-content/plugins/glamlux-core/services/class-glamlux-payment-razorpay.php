<?php
/**
 * Razorpay Gateway — implements PaymentGatewayInterface
 *
 * @package GlamLux\Services\Payment
 */
class GlamLux_Payment_Razorpay implements GlamLux_Payment_Gateway_Interface
{

    private string $key_id;
    private string $key_secret;
    private string $webhook_secret;
    private string $api_base = 'https://api.razorpay.com/v1';

    public function __construct()
    {
        $this->key_id = get_option('glamlux_razorpay_key_id', '');
        $this->key_secret = get_option('glamlux_razorpay_key_secret', '');
        $this->webhook_secret = get_option('glamlux_razorpay_webhook_secret', '');
    }

    // ── Interface Implementation ────────────────────────────────────────────

    public function create_payment(array $data): array |WP_Error
    {
        $this->validate_keys();

        $paise = (int)(($data['amount'] ?? 0) * 100);

        $payload = array(
            'amount' => $paise,
            'currency' => $data['currency'] ?? 'INR',
            'receipt' => $data['receipt'] ?? ('gl_' . ($data['appointment_id'] ?? uniqid())),
            'notes' => array(
                'appointment_id' => $data['appointment_id'] ?? 0,
                'source' => 'glamlux2lux',
            ),
        );

        return $this->api_post('/orders', $payload);
    }

    public function verify_webhook(string $raw_body, string $signature): bool
    {
        if (empty($this->webhook_secret) || empty($signature)) {
            return false;
        }
        $expected = hash_hmac('sha256', $raw_body, $this->webhook_secret);
        return hash_equals($expected, $signature);
    }

    public function capture_payment(string $payment_id, float $amount): array |WP_Error
    {
        // Razorpay auto-captures by default; explicit capture for authorize-only flows.
        return $this->api_post("/payments/{$payment_id}/capture", array('amount' => (int)$amount));
    }

    public function refund_payment(string $payment_id, float $amount = 0): array |WP_Error
    {
        $body = $amount > 0 ? array('amount' => (int)$amount) : array();
        return $this->api_post("/payments/{$payment_id}/refund", $body);
    }

    public function get_gateway_id(): string
    {
        return 'razorpay';
    }

    // ── Razorpay-specific: Payment response signature verification ──────────

    /**
     * Verify razorpay_signature from the checkout response.
     * Called on success redirect before marking appointment paid.
     */
    public function verify_payment_response(string $order_id, string $payment_id, string $signature): bool
    {
        $expected = hash_hmac('sha256', $order_id . '|' . $payment_id, $this->key_secret);
        return hash_equals($expected, $signature);
    }

    // ── Private Helpers ─────────────────────────────────────────────────────

    private function validate_keys(): void
    {
        if (empty($this->key_id) || empty($this->key_secret)) {
            throw new \RuntimeException('Razorpay API keys are not configured in GlamLux Settings.');
        }
    }

    /** @return array|WP_Error */
    private function api_post(string $path, array $body): array |WP_Error
    {
        $response = wp_remote_post(
            $this->api_base . $path,
            array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->key_id . ':' . $this->key_secret),
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
            'timeout' => 15,
        )
        );

        if (is_wp_error($response)) {
            glamlux_log_error('[Razorpay] API call failed: ' . $response->get_error_message());
            return $response;
        }

        $parsed = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($parsed['error'])) {
            return new WP_Error(
                'glamlux_razorpay_' . ($parsed['error']['code'] ?? 'error'),
                $parsed['error']['description'] ?? 'Razorpay API error.'
                );
        }

        return $parsed;
    }
}
