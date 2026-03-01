<?php
/**
 * Payment Gateway Interface — Strategy Pattern
 *
 * All payment gateways (Razorpay, Stripe, PayU, etc.) MUST implement
 * this interface. No payment logic is allowed directly in controllers or modules.
 *
 * @package GlamLux\Services\Payment
 */
interface GlamLux_Payment_Gateway_Interface
{

    /**
     * Create a payment order/intent on the gateway.
     *
     * @param array $data {
     *   float  $amount         Amount in base currency unit (INR rupees / USD dollars)
     *   string $currency       ISO 4217 code e.g. 'INR', 'USD'
     *   int    $appointment_id Internal appointment reference
     *   string $receipt        Short unique receipt string
     * }
     * @return array|WP_Error  Gateway order object on success, WP_Error on failure.
     */
    public function create_payment(array $data): array |WP_Error;

    /**
     * Verify a webhook signature from the gateway.
     *
     * @param string $raw_body   Raw POST body from the gateway.
     * @param string $signature  Signature value from the gateway header.
     * @return bool  True if signature is valid.
     */
    public function verify_webhook(string $raw_body, string $signature): bool;

    /**
     * Capture / confirm a payment by gateway payment ID.
     * Some gateways auto-capture; others require an explicit call.
     *
     * @param string $payment_id  Gateway-issued payment identifier.
     * @param float  $amount      Amount to capture (paise / cents).
     * @return array|WP_Error
     */
    public function capture_payment(string $payment_id, float $amount): array |WP_Error;

    /**
     * Issue a full or partial refund.
     *
     * @param string $payment_id  Gateway payment ID.
     * @param float  $amount      Amount to refund (paise / cents). 0 = full refund.
     * @return array|WP_Error
     */
    public function refund_payment(string $payment_id, float $amount = 0): array |WP_Error;

    /**
     * Return the unique identifier for this gateway.
     * Used for storing gateway name in appointment records.
     *
     * @return string  e.g. 'razorpay', 'stripe'
     */
    public function get_gateway_id(): string;
}
