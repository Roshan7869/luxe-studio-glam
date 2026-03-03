<?php
/**
 * JWT Authentication Manager — Sprint C
 *
 * Provides standalone HS256 JWT encoding/decoding specifically built
 * for headless mobile app authentication. Avoids external composer dependencies
 * so that the plugin remains portable across all environments.
 *
 * Requires `GLAMLUX_JWT_SECRET` constant in wp-config.php.
 * Falls back to wp_salt('auth') if missing, but warns in logs.
 *
 * LAYER: Infrastructure / Security
 */
class GlamLux_JWT_Auth
{
    /**
     * Hook into WordPress initialization
     */
    public static function init()
    {
        add_filter('determine_current_user', [__CLASS__, 'determine_current_user'], 10, 1);
    }

    /**
     * Intercept WP user context with JWT if present
     */
    public static function determine_current_user($user_id)
    {
        if ($user_id) {
            return $user_id; // Already determined via cookies/session
        }

        $header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        if (empty($header) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        }

        if (empty($header) || strpos(trim($header), 'Bearer ') !== 0) {
            return $user_id;
        }

        $token = trim(substr(trim($header), 7));
        $payload = self::decode($token);

        if (is_wp_error($payload) || empty($payload->data->user->id)) {
            return $user_id;
        }

        return $payload->data->user->id;
    }

    /**
     * Retrieve the HS256 secret.
     */
    private static function get_secret(): string
    {
        if (defined('GLAMLUX_JWT_SECRET')) {
            return GLAMLUX_JWT_SECRET;
        }

        // Log warning once per lifecycle if secret is missing (production risk)
        $warning_key = 'gl_jwt_secret_warning_blog_' . get_current_blog_id();
        if (!get_transient($warning_key)) {
            glamlux_log_error('SECURITY WARNING: GLAMLUX_JWT_SECRET not defined in wp-config.php! Falling back to wp_salt(). Token stability may vary if salt changes.');
            set_transient($warning_key, true, DAY_IN_SECONDS);
        }

        return wp_salt('auth');
    }

    /**
     * Base64Url encode string (URL safe, no padding).
     */
    private static function base64url_encode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Encode a payload into a JWT string.
     * Automatically adds standard fields (iat, iss).
     */
    public static function encode(array $payload): string
    {
        $payload['iat'] = time();
        $payload['iss'] = site_url();

        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $body = json_encode($payload);

        $enc_header = self::base64url_encode($header);
        $enc_body = self::base64url_encode($body);

        $signature = hash_hmac('sha256', $enc_header . "." . $enc_body, self::get_secret(), true);
        $enc_sign = self::base64url_encode($signature);

        return $enc_header . "." . $enc_body . "." . $enc_sign;
    }

    /**
     * Decode and verify a JWT string.
     *
     * @return object|WP_Error Payload object on success, WP_Error on failure.
     */
    public static function decode(string $jwt)
    {
        $tokenParts = explode('.', $jwt);
        if (count($tokenParts) !== 3) {
            return new \WP_Error('invalid_token', 'Malformed JWT token format.', ['status' => 401]);
        }

        list($enc_header, $enc_body, $enc_sign) = $tokenParts;

        $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $enc_header)));
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $enc_body)));

        if (!$header || !isset($header->alg) || $header->alg !== 'HS256') {
            return new \WP_Error('unsupported_algorithm', 'Only HS256 algorithm is supported.', ['status' => 401]);
        }

        // Verify Expiration
        if (isset($payload->exp) && $payload->exp < time()) {
            return new \WP_Error('expired_token', 'JWT Token has expired.', ['status' => 401]);
        }

        // Verify Signature
        $expected_signature = hash_hmac('sha256', $enc_header . "." . $enc_body, self::get_secret(), true);
        $expected_sign = self::base64url_encode($expected_signature);

        if (!hash_equals($expected_sign, $enc_sign)) {
            return new \WP_Error('invalid_signature', 'JWT signature verification failed.', ['status' => 401]);
        }

        return $payload;
    }

    /**
     * Validate the Authorization Bearer header from a WP_REST_Request.
     * Usage: Call inside an endpoint's permission_callback.
     * 
     * @return object|WP_Error Payload on success, WP_Error on failure.
     */
    public static function validate_request($request)
    {
        $header = $request->get_header('authorization');
        if (empty($header)) {
            return new \WP_Error('missing_auth', 'Missing Authorization Bearer Header.', ['status' => 401]);
        }

        if (trim(substr($header, 0, 6)) !== 'Bearer') {
            return new \WP_Error('invalid_auth_format', 'Authorization header must be Bearer type.', ['status' => 401]);
        }

        $token = trim(substr($header, 6));
        return self::decode($token);
    }
}
