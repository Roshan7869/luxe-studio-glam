<?php
/**
 * GlamLux Auth Controller
 * 
 * Provides JWT endpoints: login, refresh, logout with rate limiting and security.
 */
class GlamLux_Auth_Controller extends GlamLux_Base_Controller
{
    const LOGIN_RATE_LIMIT = 5; // 5 attempts per 15 minutes
    const LOGIN_RATE_WINDOW = 900; // 15 minutes in seconds

    public function register_routes()
    {
        // Login endpoint - generates access + refresh tokens
        register_rest_route('glamlux/v1', '/auth/login', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true',
            'args' => [
                'username' => ['type' => 'string', 'required' => true],
                'password' => ['type' => 'string', 'required' => true]
            ]
        ]);

        // Refresh endpoint - exchanges refresh token for new access token
        register_rest_route('glamlux/v1', '/auth/refresh', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'refresh'],
            'permission_callback' => '__return_true',
            'args' => [
                'refresh_token' => ['type' => 'string', 'required' => true]
            ]
        ]);

        // Logout endpoint - revokes the token
        register_rest_route('glamlux/v1', '/auth/logout', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'logout'],
            'permission_callback' => [$this, 'check_token']
        ]);

        // Legacy /auth/token endpoint for backwards compatibility
        register_rest_route('glamlux/v1', '/auth/token', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true',
            'args' => [
                'username' => ['type' => 'string', 'required' => true],
                'password' => ['type' => 'string', 'required' => true]
            ]
        ]);
    }

    /**
     * Check rate limit for login attempts
     */
    private function check_rate_limit($identifier): bool
    {
        $cache_key = 'gl_auth_attempts_' . md5($identifier);
        $attempts = get_transient($cache_key);

        if ($attempts && $attempts >= self::LOGIN_RATE_LIMIT) {
            return false; // Rate limit exceeded
        }

        // Increment attempts
        $new_attempts = ($attempts ?? 0) + 1;
        set_transient($cache_key, $new_attempts, self::LOGIN_RATE_WINDOW);

        return true;
    }

    /**
     * Login endpoint - generates JWT access + refresh tokens
     */
    public function login($request)
    {
        $username = sanitize_text_field($request->get_param('username'));
        $password = $request->get_param('password');

        // Rate limit check (use IP + username)
        $identifier = $_SERVER['REMOTE_ADDR'] . ':' . $username;
        if (!$this->check_rate_limit($identifier)) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Too many login attempts. Please try again later.',
                ['status' => 429]
            );
        }

        // Authenticate user
        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            return new WP_Error(
                'invalid_credentials',
                'Invalid username or password.',
                ['status' => 401]
            );
        }

        // Generate access token (24 hours)
        $access_token = GlamLux_JWT_Auth::encode([
            'user' => [
                'id' => $user->ID,
                'email' => $user->user_email,
                'roles' => $user->roles
            ]
        ], 24);

        // Generate refresh token (30 days)
        $refresh_token = GlamLux_JWT_Auth::generate_refresh_token($user->ID);

        // Log successful authentication
        glamlux_log_error('User login successful', [
            'user_id' => $user->ID,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'timestamp' => current_time('mysql')
        ]);

        return rest_ensure_response([
            'success' => true,
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'expires_in' => 86400, // 24 hours in seconds
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'roles' => $user->roles
            ]
        ]);
    }

    /**
     * Refresh endpoint - exchanges refresh token for new access token
     */
    public function refresh($request)
    {
        $refresh_token = sanitize_text_field($request->get_param('refresh_token'));

        $new_access_token = GlamLux_JWT_Auth::refresh_access_token($refresh_token);

        if (is_wp_error($new_access_token)) {
            return new WP_Error(
                'invalid_refresh_token',
                'Invalid or expired refresh token.',
                ['status' => 401]
            );
        }

        return rest_ensure_response([
            'success' => true,
            'access_token' => $new_access_token,
            'expires_in' => 86400,
            'token_type' => 'Bearer'
        ]);
    }

    /**
     * Logout endpoint - revokes the current token
     */
    public function logout($request)
    {
        $header = $request->get_header('authorization');
        if (empty($header)) {
            return new WP_Error('missing_token', 'Missing Authorization header', ['status' => 400]);
        }

        $token = trim(substr($header, 6)); // Remove "Bearer "
        
        if (GlamLux_JWT_Auth::revoke_token($token, 'user_logout')) {
            glamlux_log_error('User logout', ['timestamp' => current_time('mysql')]);
            return rest_ensure_response(['success' => true, 'message' => 'Logged out successfully']);
        }

        return new WP_Error('logout_failed', 'Failed to revoke token', ['status' => 500]);
    }

    /**
     * Permission callback to validate JWT token
     */
    public function check_token($request)
    {
        $payload = GlamLux_JWT_Auth::validate_request($request);
        return !is_wp_error($payload);
    }

    /**
     * Legacy method for backwards compatibility
     */
    public function generate_token($request)
    {
        return $this->login($request);
    }
}
