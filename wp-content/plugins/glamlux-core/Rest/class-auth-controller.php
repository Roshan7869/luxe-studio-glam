<?php
/**
 * GlamLux Auth Controller
 * 
 * Provides an endpoint to generate JWT tokens for mobile apps.
 */
class GlamLux_Auth_Controller extends GlamLux_Base_Controller
{
    public function register_routes()
    {
        register_rest_route('glamlux/v1', '/auth/token', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'generate_token'],
            'permission_callback' => '__return_true',
            'args' => [
                'username' => ['type' => 'string', 'required' => true],
                'password' => ['type' => 'string', 'required' => true]
            ]
        ]);
    }

    public function generate_token($request)
    {
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            return new WP_Error(
                'invalid_credentials',
                'Invalid username or password.',
            ['status' => 403]
                );
        }

        $issuedAt = time();
        $expire = $issuedAt + (DAY_IN_SECONDS * 7); // 7 day token

        $payload = [
            'iss' => site_url(),
            'iat' => $issuedAt,
            'exp' => $expire,
            'data' => [
                'user' => [
                    'id' => $user->ID,
                    'email' => $user->user_email,
                    'roles' => $user->roles
                ]
            ]
        ];

        $token = GlamLux_JWT_Auth::encode($payload);

        return rest_ensure_response([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'roles' => $user->roles
            ]
        ]);
    }
}
