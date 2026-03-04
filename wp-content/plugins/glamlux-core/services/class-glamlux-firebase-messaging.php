<?php
/**
 * Firebase Cloud Messaging Integration for Push Notifications
 *
 * Handles device token management and FCM notification sending.
 *
 * @package GlamLux
 * @subpackage Services
 * @since 7.1
 */

class GlamLux_Firebase_Messaging
{
    private $project_id;
    private $service_account_key;
    private $access_token = null;
    private $token_expiry = null;

    public function __construct()
    {
        $this->project_id = defined('GLAMLUX_FIREBASE_PROJECT_ID')
            ? GLAMLUX_FIREBASE_PROJECT_ID
            : false;

        if (!$this->project_id) {
            glamlux_log_error('Firebase Cloud Messaging not configured. Set GLAMLUX_FIREBASE_PROJECT_ID.');
        }
    }

    /**
     * Get Firebase Access Token (OAuth2)
     */
    private function get_access_token()
    {
        // Return cached token if not expired
        if ($this->access_token && $this->token_expiry > time()) {
            return $this->access_token;
        }

        $service_account_path = defined('GLAMLUX_FIREBASE_SERVICE_ACCOUNT_PATH')
            ? GLAMLUX_FIREBASE_SERVICE_ACCOUNT_PATH
            : null;

        if (!$service_account_path || !file_exists($service_account_path)) {
            glamlux_log_error('Firebase service account not found: ' . ($service_account_path ?? 'undefined'));
            return false;
        }

        try {
            $key_data = json_decode(file_get_contents($service_account_path), true);

            if (!$key_data || !isset($key_data['private_key'], $key_data['client_email'])) {
                throw new Exception('Invalid service account format');
            }

            // Create JWT for service account
            $jwt = $this->create_jwt(
                $key_data['private_key'],
                $key_data['client_email'],
                'https://oauth2.googleapis.com/token'
            );

            // Exchange JWT for access token
            $response = wp_remote_post('https://oauth2.googleapis.com/token', [
                'body' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt
                ]
            ]);

            if (is_wp_error($response)) {
                throw new Exception('Token exchange failed: ' . $response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (!isset($body['access_token'])) {
                throw new Exception('No access token in response');
            }

            $this->access_token = $body['access_token'];
            $this->token_expiry = time() + ($body['expires_in'] - 300); // 5 min buffer

            return $this->access_token;
        } catch (Exception $e) {
            glamlux_log_error('Firebase token generation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create JWT for Google service account
     */
    private function create_jwt($private_key, $client_email, $audience)
    {
        $now = time();
        $payload = [
            'iss' => $client_email,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $audience,
            'exp' => $now + 3600,
            'iat' => $now
        ];

        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $body = base64_encode(json_encode($payload));
        $signature_input = "{$header}.{$body}";

        // Sign with private key
        openssl_sign($signature_input, $signature, $private_key, 'RSA-SHA256');
        $signature_encoded = base64_encode($signature);

        return "{$signature_input}.{$signature_encoded}";
    }

    /**
     * Register device token for user
     */
    public function register_device_token($user_id, $token, $device_name = 'Unknown')
    {
        global $wpdb;

        if (!$user_id || !$token) {
            return false;
        }

        try {
            // Verify token format (FCM tokens are typically 152+ chars)
            if (strlen($token) < 100) {
                throw new Exception('Invalid FCM token format');
            }

            // Check if token already exists
            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}gl_device_tokens
                     WHERE user_id = %d AND token = %s",
                    $user_id,
                    $token
                )
            );

            if ($existing) {
                // Update last activity
                $wpdb->update(
                    $wpdb->prefix . 'gl_device_tokens',
                    ['last_used_at' => current_time('mysql')],
                    ['id' => $existing->id]
                );
                return $existing->id;
            }

            // Insert new token
            $result = $wpdb->insert(
                $wpdb->prefix . 'gl_device_tokens',
                [
                    'user_id' => $user_id,
                    'token' => $token,
                    'device_name' => sanitize_text_field($device_name),
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'last_used_at' => current_time('mysql')
                ]
            );

            if (!$result) {
                throw new Exception('Failed to insert device token');
            }

            return $wpdb->insert_id;
        } catch (Exception $e) {
            glamlux_log_error('Device token registration failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Unregister/deactivate device token
     */
    public function unregister_device_token($token)
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'gl_device_tokens',
            ['is_active' => 0, 'deactivated_at' => current_time('mysql')],
            ['token' => $token]
        );

        return true;
    }

    /**
     * Send notification to single device
     */
    public function send_notification($token, $title, $body, $data = [], $click_action = null)
    {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return false;
        }

        try {
            $message = [
                'token' => $token,
                'notification' => [
                    'title' => sanitize_text_field($title),
                    'body' => sanitize_text_field($body)
                ]
            ];

            if ($click_action) {
                $message['notification']['click_action'] = $click_action;
            }

            if (!empty($data)) {
                $message['data'] = array_map('sanitize_text_field', $data);
            }

            $response = wp_remote_post(
                "https://fcm.googleapis.com/v1/projects/{$this->project_id}/messages:send",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$access_token}",
                        'Content-Type' => 'application/json'
                    ],
                    'body' => wp_json_encode(['message' => $message])
                ]
            );

            if (is_wp_error($response)) {
                throw new Exception('FCM request failed: ' . $response->get_error_message());
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            if ($status_code !== 200) {
                if ($status_code === 404 && strpos($response_body['error']['message'] ?? '', 'Unregistered') !== false) {
                    // Token no longer valid
                    $this->unregister_device_token($token);
                }

                throw new Exception('FCM error: ' . ($response_body['error']['message'] ?? 'Unknown'));
            }

            glamlux_log('FCM notification sent', [
                'token' => substr($token, 0, 20) . '...',
                'title' => $title
            ]);

            return true;
        } catch (Exception $e) {
            glamlux_log_error('FCM send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to multiple devices (batch)
     */
    public function send_notification_batch($tokens, $title, $body, $data = [])
    {
        if (empty($tokens)) {
            return ['sent' => 0, 'failed' => 0];
        }

        $sent = 0;
        $failed = 0;

        foreach ($tokens as $token) {
            if ($this->send_notification($token, $title, $body, $data)) {
                $sent++;
            } else {
                $failed++;
            }

            // Respect rate limits (avoid overwhelming FCM)
            usleep(50000); // 50ms between requests
        }

        return ['sent' => $sent, 'failed' => $failed, 'total' => count($tokens)];
    }

    /**
     * Send notification to user group
     */
    public function send_to_user($user_id, $title, $body, $data = [])
    {
        global $wpdb;

        try {
            $tokens = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT token FROM {$wpdb->prefix}gl_device_tokens
                     WHERE user_id = %d AND is_active = 1",
                    $user_id
                )
            );

            if (empty($tokens)) {
                return ['sent' => 0, 'failed' => 0, 'reason' => 'No active device tokens'];
            }

            return $this->send_notification_batch($tokens, $title, $body, $data);
        } catch (Exception $e) {
            glamlux_log_error('Failed to send notification to user: ' . $e->getMessage());
            return ['sent' => 0, 'failed' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send notification to user role (e.g., all stylists)
     */
    public function send_to_role($role, $title, $body, $data = [])
    {
        global $wpdb;

        try {
            $users = get_users(['role' => $role]);

            if (empty($users)) {
                return ['sent' => 0, 'failed' => 0, 'reason' => 'No users with this role'];
            }

            $sent = 0;
            $failed = 0;

            foreach ($users as $user) {
                $result = $this->send_to_user($user->ID, $title, $body, $data);
                $sent += $result['sent'] ?? 0;
                $failed += $result['failed'] ?? 0;
            }

            return ['sent' => $sent, 'failed' => $failed];
        } catch (Exception $e) {
            glamlux_log_error('Failed to send notification to role: ' . $e->getMessage());
            return ['sent' => 0, 'failed' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get user's active device tokens
     */
    public function get_user_devices($user_id)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, token, device_name, last_used_at FROM {$wpdb->prefix}gl_device_tokens
                 WHERE user_id = %d AND is_active = 1
                 ORDER BY last_used_at DESC",
                $user_id
            )
        );
    }

    /**
     * Clean up inactive/old tokens
     */
    public function cleanup_inactive_tokens($days = 90)
    {
        global $wpdb;

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}gl_device_tokens
                 WHERE is_active = 0
                 AND deactivated_at < %s",
                $cutoff_date
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}gl_device_tokens
                 WHERE last_used_at < %s AND is_active = 1",
                $cutoff_date
            )
        );
    }
}
