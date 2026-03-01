<?php
/**
 * Stripe Gateway — implements PaymentGatewayInterface
 * Future-ready stub: swap Stripe secret key and this is fully wired.
 *
 * @package GlamLux\Services\Payment
 */
class GlamLux_Payment_Stripe implements GlamLux_Payment_Gateway_Interface
{

    private string $secret_key;
    private string $webhook_secret;
    private string $api_base = 'https://api.stripe.com/v1';

    public function __construct()
    {
        $this->secret_key = get_option('glamlux_stripe_secret_key', '');
        $this->webhook_secret = get_option('glamlux_stripe_webhook_secret', '');
    }

    public function create_payment(array $data): array |WP_Error
    {
        $cents = (int)(($data['amount'] ?? 0) * 100);

        return $this->api_post('/payment_intents', array(
            'amount' => $cents,
            'currency' => strtolower($data['currency'] ?? 'inr'),
            'metadata' => array('appointment_id' => $data['appointment_id'] ?? 0),
            'automatic_payment_methods' => array('enabled' => 'true'),
        ));
    }

    public function verify_webhook(string $raw_body, string $signature): bool
    {
        // Stripe uses a timestamp-based HMAC — simplified version:
        if (empty($this->webhook_secret) || empty($signature)) {
            return false;
        }
        // Parse t= and v1= from signature header
        $parts = array();
        foreach (explode(',', $signature) as $part) {
            [$k, $v] = explode('=', $part, 2);
            $parts[trim($k)] = trim($v);
        }
        if (empty($parts['t']) || empty($parts['v1'])) {
            return false;
        }
        $signed_payload = $parts['t'] . '.' . $raw_body;
        $expected = hash_hmac('sha256', $signed_payload, $this->webhook_secret);
        return hash_equals($expected, $parts['v1']);
    }

    public function capture_payment(string $payment_id, float $amount): array |WP_Error
    {
        return $this->api_post("/payment_intents/{$payment_id}/confirm", array());
    }

    public function refund_payment(string $payment_id, float $amount = 0): array |WP_Error
    {
        $body = array('payment_intent' => $payment_id);
        if ($amount > 0) {
            $body['amount'] = (int)$amount;
        }
        return $this->api_post('/refunds', $body);
    }

    public function get_gateway_id(): string
    {
        return 'stripe';
    }

    private function api_post(string $path, array $body): array |WP_Error
    {
        $response = wp_remote_post(
            $this->api_base . $path,
            array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => http_build_query($body),
            'timeout' => 15,
        )
        );

        if (is_wp_error($response)) {
            glamlux_log_error('[Stripe] API call failed: ' . $response->get_error_message());
            return $response;
        }

        $parsed = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($parsed['error'])) {
            return new WP_Error('glamlux_stripe_error', $parsed['error']['message'] ?? 'Stripe error.');
        }

        return $parsed;
    }
}
