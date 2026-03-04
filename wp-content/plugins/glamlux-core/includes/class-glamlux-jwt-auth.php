<?php

/**
 * GlamLux JWT Authentication Handler
 *
 * Manages JWT token creation, validation, expiration, and refresh token lifecycle.
 * Supports token revocation and session tracking.
 *
 * @package GlamLux
 * @subpackage Core
 * @since 7.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

class GlamLux_JWT_Auth
{
    const DEFAULT_EXPIRATION_HOURS = 24;
    const REFRESH_TOKEN_EXPIRATION_DAYS = 30;

    /**
     * Create a JWT token with expiration
     */
    public static function encode($data, $expiration_hours = self::DEFAULT_EXPIRATION_HOURS)
    {
        $payload = [
            'data' => $data,
            'iat' => time(),
            'exp' => time() + ($expiration_hours * HOUR_IN_SECONDS)
        ];

        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $body = json_encode($payload);

        $enc_header = self::base64url_encode($header);
        $enc_body = self::base64url_encode($body);

        $signature = hash_hmac('sha256', $enc_header . "." . $enc_body, self::get_secret(), true);
        $enc_sign = self::base64url_encode($signature);

        return $enc_header . "." . $enc_body . "." . $enc_sign;
    }

    /**
     * Decode and validate JWT token
     */
    public static function decode($token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return new WP_Error('invalid_token_format', 'Invalid token format');
        }

        [$header_enc, $body_enc, $sig_enc] = $parts;

        // Verify signature
        $expected_sig = hash_hmac('sha256', $header_enc . "." . $body_enc, self::get_secret(), true);
        $expected_sig_enc = self::base64url_encode($expected_sig);

        if (!hash_equals($sig_enc, $expected_sig_enc)) {
            return new WP_Error('invalid_signature', 'Invalid token signature');
        }

        // Decode payload
        $payload_json = self::base64url_decode($body_enc);
        if (!$payload_json) {
            return new WP_Error('invalid_payload', 'Cannot decode payload');
        }

        $payload = json_decode($payload_json);

        // Check expiration
        if (!isset($payload->exp) || $payload->exp < time()) {
            return new WP_Error('token_expired', 'Token has expired');
        }

        return $payload;
    }

    /**
     * Check if token is revoked (blacklisted)
     */
    public static function is_token_revoked($token): bool
    {
        global $wpdb;

        $token_hash = hash('sha256', $token);
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->prefix}gl_token_blacklist WHERE token_hash = %s LIMIT 1",
            $token_hash
        ));

        return (bool)$result;
    }

    /**
     * Revoke a token (logout)
     */
    public static function revoke_token($token, $reason = 'logout'): bool
    {
        global $wpdb;

        $payload = self::decode($token);
        if (is_wp_error($payload)) {
            return false;
        }

        $token_hash = hash('sha256', $token);

        $result = $wpdb->insert(
            $wpdb->prefix . 'gl_token_blacklist',
            [
                'user_id' => (int)$payload->data->user->id,
                'token_hash' => $token_hash,
                'revoked_at' => current_time('mysql'),
                'reason' => sanitize_text_field($reason)
            ],
            ['%d', '%s', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Generate refresh token (long-lived, used to get new access tokens)
     */
    public static function generate_refresh_token($user_id): string
    {
        global $wpdb;

        $refresh_token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $refresh_token);

        $wpdb->insert(
            $wpdb->prefix . 'gl_refresh_tokens',
            [
                'user_id' => absint($user_id),
                'token_hash' => $token_hash,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+' . self::REFRESH_TOKEN_EXPIRATION_DAYS . ' days')),
                'created_at' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );

        return $refresh_token;
    }

    /**
     * Validate and exchange refresh token for new access token
     */
    public static function refresh_access_token($refresh_token)
    {
        global $wpdb;

        $token_hash = hash('sha256', $refresh_token);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gl_refresh_tokens 
             WHERE token_hash = %s 
             AND expires_at > NOW()",
            $token_hash
        ));

        if (!$row) {
            return new WP_Error('invalid_refresh_token', 'Invalid or expired refresh token');
        }

        // Generate new access token
        $new_token = self::encode(['user_id' => $row->user_id], self::DEFAULT_EXPIRATION_HOURS);

        // Update last_used_at
        $wpdb->update(
            $wpdb->prefix . 'gl_refresh_tokens',
            ['last_used_at' => current_time('mysql')],
            ['id' => $row->id]
        );

        return $new_token;
    }

    /**
     * Cleanup expired refresh tokens and old blacklist entries (runs daily via WP-Cron)
     */
    public static function cleanup_expired_tokens()
    {
        global $wpdb;

        // Delete expired refresh tokens (older than 30 days + 7 day grace period)
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}gl_refresh_tokens 
             WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        // Delete old blacklist entries (older than 90 days)
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}gl_token_blacklist 
             WHERE revoked_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );

        glamlux_log_error('Token cleanup completed', ['timestamp' => current_time('mysql')]);
    }

    /**
     * Retrieve the HS256 secret
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
     * Base64Url encode string (URL safe, no padding)
     */
    private static function base64url_encode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Base64Url decode string
     */
    private static function base64url_decode(string $data): string
    {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data), true);
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
            return new WP_Error('missing_auth', 'Missing Authorization Bearer Header.', ['status' => 401]);
        }

        if (trim(substr($header, 0, 6)) !== 'Bearer') {
            return new WP_Error('invalid_auth_format', 'Authorization header must be Bearer type.', ['status' => 401]);
        }

        $token = trim(substr($header, 6));
        $payload = self::decode($token);
        
        if (is_wp_error($payload)) {
            return $payload;
        }
        
        // Check if token is revoked
        if (self::is_token_revoked($token)) {
            return new WP_Error('revoked_token', 'This token has been revoked.', ['status' => 401]);
        }
        
        return $payload;
    }
}
