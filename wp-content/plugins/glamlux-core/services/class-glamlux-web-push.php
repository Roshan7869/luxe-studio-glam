<?php
/**
 * Web Push API Service for Browser Notifications
 *
 * Implements Web Push Protocol (RFC 8030) with VAPID authentication.
 * Enables server-sent push notifications to web browsers.
 *
 * @package GlamLux
 * @subpackage Services
 * @since 7.3
 */

class GlamLux_Web_Push
{
    private $vapid_public_key;
    private $vapid_private_key;
    private $subject;

    public function __construct()
    {
        $this->vapid_public_key = defined('GLAMLUX_VAPID_PUBLIC_KEY') 
            ? GLAMLUX_VAPID_PUBLIC_KEY 
            : null;

        $this->vapid_private_key = defined('GLAMLUX_VAPID_PRIVATE_KEY') 
            ? GLAMLUX_VAPID_PRIVATE_KEY 
            : null;

        $this->subject = 'mailto:' . get_option('admin_email');

        if (!$this->vapid_public_key || !$this->vapid_private_key) {
            glamlux_log_error('Web Push: VAPID keys not configured');
        }
    }

    /**
     * Generate VAPID key pair
     */
    public static function generate_vapid_keys()
    {
        // Note: In production, use a proper VAPID library
        // This is a simplified representation

        $private_key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $details = openssl_pkey_get_details($private_key);
        $public_key = $details['key'];

        openssl_pkey_export($private_key, $private_key_export);

        return [
            'public_key' => $public_key,
            'private_key' => $private_key_export
        ];
    }

    /**
     * Register browser subscription
     */
    public function subscribe($user_id, $subscription)
    {
        global $wpdb;

        try {
            if (!isset($subscription['endpoint']) || !isset($subscription['keys'])) {
                throw new Exception('Invalid subscription data');
            }

            $result = $wpdb->insert(
                $wpdb->prefix . 'gl_web_push_subscriptions',
                [
                    'user_id' => $user_id,
                    'endpoint' => sanitize_url($subscription['endpoint']),
                    'auth_key' => $subscription['keys']['auth'] ?? null,
                    'p256dh_key' => $subscription['keys']['p256dh'] ?? null,
                    'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s']
            );

            if (!$result) {
                throw new Exception('Database insert failed');
            }

            glamlux_log("Web Push subscription registered for user {$user_id}");
            return $wpdb->insert_id;
        } catch (Exception $e) {
            glamlux_log_error('Web Push subscription error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Unsubscribe browser
     */
    public function unsubscribe($subscription_id)
    {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'gl_web_push_subscriptions',
            ['id' => $subscription_id]
        );

        glamlux_log("Web Push subscription removed: {$subscription_id}");
        return true;
    }

    /**
     * Send push notification to user
     */
    public function send_to_user($user_id, $title, $body, $data = [])
    {
        global $wpdb;

        try {
            $subscriptions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}gl_web_push_subscriptions 
                     WHERE user_id = %d",
                    $user_id
                )
            );

            if (empty($subscriptions)) {
                return ['sent' => 0, 'failed' => 0];
            }

            $sent = 0;
            $failed = 0;

            $payload = [
                'title' => $title,
                'body' => $body,
                'icon' => get_site_icon_url(192),
                'badge' => get_site_icon_url(128),
                'data' => $data,
                'timestamp' => time()
            ];

            foreach ($subscriptions as $sub) {
                if ($this->send_notification($sub, $payload)) {
                    $sent++;
                } else {
                    $failed++;
                }
            }

            return ['sent' => $sent, 'failed' => $failed];
        } catch (Exception $e) {
            glamlux_log_error('Web Push send error: ' . $e->getMessage());
            return ['sent' => 0, 'failed' => 0];
        }
    }

    /**
     * Send notification to subscription
     */
    private function send_notification($subscription, $payload)
    {
        try {
            if (!$this->vapid_private_key) {
                throw new Exception('VAPID keys not configured');
            }

            $payload_json = wp_json_encode($payload);

            // Create VAPID JWT
            $vapid_jwt = $this->create_vapid_jwt();

            // Encrypt payload (would require proper encryption library)
            // For now, send unencrypted for simplicity
            // In production, use proper encryption

            $response = wp_remote_post($subscription->endpoint, [
                'headers' => [
                    'Authorization' => 'vapid t=' . $vapid_jwt . ', k=' . $this->vapid_public_key,
                    'Content-Type' => 'application/octet-stream',
                    'Content-Length' => strlen($payload_json)
                ],
                'body' => $payload_json,
                'timeout' => 10
            ]);

            if (is_wp_error($response)) {
                throw new Exception('HTTP request failed: ' . $response->get_error_message());
            }

            $status = wp_remote_retrieve_response_code($response);

            if ($status === 201) {
                glamlux_log('Web Push sent successfully');
                return true;
            } elseif ($status === 410) {
                // Subscription expired, remove it
                global $wpdb;
                $wpdb->delete(
                    $wpdb->prefix . 'gl_web_push_subscriptions',
                    ['id' => $subscription->id]
                );
                glamlux_log('Web Push subscription expired and removed');
                return false;
            } else {
                throw new Exception("HTTP {$status}: " . wp_remote_retrieve_body($response));
            }
        } catch (Exception $e) {
            glamlux_log_error('Web Push notification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create VAPID JWT
     */
    private function create_vapid_jwt()
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        $payload = json_encode([
            'aud' => $this->get_audience(),
            'exp' => time() + 43200, // 12 hours
            'sub' => $this->subject
        ]);

        $header_encoded = $this->base64_url_encode($header);
        $payload_encoded = $this->base64_url_encode($payload);
        $signature_input = $header_encoded . '.' . $payload_encoded;

        // Sign with private key
        openssl_sign($signature_input, $signature, $this->vapid_private_key, 'RSA-SHA256');
        $signature_encoded = $this->base64_url_encode($signature);

        return $signature_input . '.' . $signature_encoded;
    }

    /**
     * Get audience from subscription endpoint
     */
    private function get_audience()
    {
        // Parse the origin from the endpoint URL
        $parsed = parse_url($this->subject);
        return $parsed['scheme'] . '://' . $parsed['host'];
    }

    /**
     * Base64 URL encode
     */
    private function base64_url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Get user subscriptions
     */
    public function get_user_subscriptions($user_id)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, endpoint, user_agent, created_at FROM {$wpdb->prefix}gl_web_push_subscriptions 
                 WHERE user_id = %d
                 ORDER BY created_at DESC",
                $user_id
            )
        );
    }

    /**
     * Cleanup expired subscriptions
     */
    public static function cleanup_expired_subscriptions($days = 90)
    {
        global $wpdb;

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}gl_web_push_subscriptions
                 WHERE created_at < %s",
                $cutoff_date
            )
        );
    }
}
