<?php
/**
 * Firebase Cloud Messaging (FCM) API Wrapper
 * 
 * Implements the standard payload struct required by FCM API v1.
 * 
 * LAYER: Infrastructure / External Services
 */
class GlamLux_FCM_API
{
    private $server_key;

    public function __construct()
    {
        $this->server_key = defined('GLAMLUX_FCM_SERVER_KEY') ? GLAMLUX_FCM_SERVER_KEY : '';
    }

    /**
     * Send a push notification to a specific device.
     */
    public function send_push(string $device_token, string $title, string $body, array $data = []): bool
    {
        if (empty($this->server_key) || empty($device_token)) {
            glamlux_log_error('FCM Push blocked: Missing server key or device token.');
            return false;
        }

        $payload = [
            'message' => [
                'token' => $device_token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => (object)$data,
                'android' => [
                    'notification' => [
                        'sound' => 'default',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ]
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ]
                    ]
                ],
            ]
        ];

        $response = wp_remote_post('https://fcm.googleapis.com/v1/projects/glamlux/messages:send', [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->server_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            glamlux_log_error('FCM API Error: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code >= 300) {
            glamlux_log_error("FCM API Failed ({$code})", ['response' => $body]);
            return false;
        }

        return true;
    }
}
