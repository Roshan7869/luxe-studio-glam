<?php
/**
 * Push Notifications Controller - REST API for device management and notifications
 *
 * @package GlamLux
 * @subpackage REST
 * @since 7.1
 */

class GlamLux_Push_Notifications_Controller extends GlamLux_Base_Controller
{
    protected $namespace = 'glamlux/v1';
    protected $resource_name = 'notifications';

    public function register_routes()
    {
        // Register device token
        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/device/register',
            [
                'methods' => 'POST',
                'callback' => [$this, 'register_device'],
                'permission_callback' => [$this, 'check_rest_auth'],
                'args' => [
                    'token' => [
                        'type' => 'string',
                        'required' => true,
                        'minLength' => 100
                    ],
                    'device_name' => [
                        'type' => 'string',
                        'default' => 'Mobile Device'
                    ]
                ]
            ]
        );

        // Get user devices
        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/device/list',
            [
                'methods' => 'GET',
                'callback' => [$this, 'list_user_devices'],
                'permission_callback' => [$this, 'check_rest_auth']
            ]
        );

        // Unregister device
        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/device/unregister',
            [
                'methods' => 'POST',
                'callback' => [$this, 'unregister_device'],
                'permission_callback' => [$this, 'check_rest_auth'],
                'args' => [
                    'token' => [
                        'type' => 'string',
                        'required' => true
                    ]
                ]
            ]
        );

        // Send test notification (admin only)
        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/send-test',
            [
                'methods' => 'POST',
                'callback' => [$this, 'send_test_notification'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => [
                    'user_id' => [
                        'type' => 'integer',
                        'required' => true
                    ],
                    'title' => [
                        'type' => 'string',
                        'default' => 'Test Notification'
                    ],
                    'body' => [
                        'type' => 'string',
                        'default' => 'This is a test notification'
                    ]
                ]
            ]
        );

        // Send bulk notification (admin only)
        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/send-bulk',
            [
                'methods' => 'POST',
                'callback' => [$this, 'send_bulk_notification'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => [
                    'user_ids' => [
                        'type' => 'array',
                        'required' => true,
                        'items' => ['type' => 'integer']
                    ],
                    'title' => [
                        'type' => 'string',
                        'required' => true
                    ],
                    'body' => [
                        'type' => 'string',
                        'required' => true
                    ]
                ]
            ]
        );

        // Send to role (admin only)
        register_rest_route(
            $this->namespace,
            '/' . $this->resource_name . '/send-role',
            [
                'methods' => 'POST',
                'callback' => [$this, 'send_role_notification'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => [
                    'role' => [
                        'type' => 'string',
                        'required' => true,
                        'enum' => ['administrator', 'stylist', 'client', 'staff']
                    ],
                    'title' => [
                        'type' => 'string',
                        'required' => true
                    ],
                    'body' => [
                        'type' => 'string',
                        'required' => true
                    ]
                ]
            ]
        );
    }

    /**
     * Register device token
     */
    public function register_device($request)
    {
        try {
            $user_id = get_current_user_id();
            $token = sanitize_text_field($request->get_param('token'));
            $device_name = sanitize_text_field($request->get_param('device_name'));

            require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-firebase-messaging.php';
            $fcm = new GlamLux_Firebase_Messaging();

            $device_id = $fcm->register_device_token($user_id, $token, $device_name);

            if (!$device_id) {
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'error' => 'Failed to register device token'
                    ],
                    400
                );
            }

            return new WP_REST_Response(
                [
                    'success' => true,
                    'device_id' => $device_id,
                    'message' => 'Device registered successfully'
                ],
                201
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Get user's registered devices
     */
    public function list_user_devices($request)
    {
        try {
            $user_id = get_current_user_id();

            require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-firebase-messaging.php';
            $fcm = new GlamLux_Firebase_Messaging();

            $devices = $fcm->get_user_devices($user_id);

            // Mask tokens for security
            $devices = array_map(function ($device) {
                $device->token = substr($device->token, 0, 20) . '...' . substr($device->token, -20);
                return $device;
            }, $devices ?? []);

            return new WP_REST_Response(
                [
                    'success' => true,
                    'devices' => $devices ?? []
                ],
                200
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Unregister device
     */
    public function unregister_device($request)
    {
        try {
            $token = sanitize_text_field($request->get_param('token'));

            require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-firebase-messaging.php';
            $fcm = new GlamLux_Firebase_Messaging();

            $fcm->unregister_device_token($token);

            return new WP_REST_Response(
                [
                    'success' => true,
                    'message' => 'Device unregistered successfully'
                ],
                200
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Send test notification to current user
     */
    public function send_test_notification($request)
    {
        try {
            $user_id = intval($request->get_param('user_id'));
            $title = sanitize_text_field($request->get_param('title'));
            $body = sanitize_text_field($request->get_param('body'));

            // Verify user exists
            $user = get_user_by('id', $user_id);
            if (!$user) {
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'error' => 'User not found'
                    ],
                    404
                );
            }

            require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-firebase-messaging.php';
            $fcm = new GlamLux_Firebase_Messaging();

            $result = $fcm->send_to_user($user_id, $title, $body);

            return new WP_REST_Response(
                [
                    'success' => true,
                    'result' => $result
                ],
                200
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Send bulk notification to multiple users
     */
    public function send_bulk_notification($request)
    {
        try {
            $user_ids = $request->get_param('user_ids');
            $title = sanitize_text_field($request->get_param('title'));
            $body = sanitize_text_field($request->get_param('body'));

            if (!is_array($user_ids) || empty($user_ids)) {
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'error' => 'Invalid user_ids parameter'
                    ],
                    400
                );
            }

            require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-firebase-messaging.php';
            $fcm = new GlamLux_Firebase_Messaging();

            $total_sent = 0;
            $total_failed = 0;

            foreach ($user_ids as $user_id) {
                $result = $fcm->send_to_user(
                    intval($user_id),
                    $title,
                    $body
                );

                $total_sent += $result['sent'] ?? 0;
                $total_failed += $result['failed'] ?? 0;
            }

            return new WP_REST_Response(
                [
                    'success' => true,
                    'sent' => $total_sent,
                    'failed' => $total_failed,
                    'total_users' => count($user_ids)
                ],
                200
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Send notification to all users with a role
     */
    public function send_role_notification($request)
    {
        try {
            $role = sanitize_text_field($request->get_param('role'));
            $title = sanitize_text_field($request->get_param('title'));
            $body = sanitize_text_field($request->get_param('body'));

            require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-firebase-messaging.php';
            $fcm = new GlamLux_Firebase_Messaging();

            $result = $fcm->send_to_role($role, $title, $body);

            return new WP_REST_Response(
                [
                    'success' => true,
                    'result' => $result
                ],
                200
            );
        } catch (Exception $e) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage()
                ],
                500
            );
        }
    }

    /**
     * Check admin permission
     */
    public function check_admin_permission()
    {
        return current_user_can('manage_options');
    }
}

// Instantiate and register routes
add_action('rest_api_init', function () {
    $controller = new GlamLux_Push_Notifications_Controller();
    $controller->register_routes();
});
