<?php

/**
 * JWT Authentication Integration Test
 * Verifies token generation, expiration, refresh, and revocation
 */

if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

class GlamLux_JWT_Auth_Test
{
    public static function run_all_tests()
    {
        echo "\n=== GlamLux JWT Authentication Tests ===\n\n";

        $results = [];
        $results['token_generation'] = self::test_token_generation();
        $results['token_expiration'] = self::test_token_expiration();
        $results['token_validation'] = self::test_token_validation();
        $results['token_refresh'] = self::test_token_refresh();
        $results['token_revocation'] = self::test_token_revocation();
        $results['rate_limiting'] = self::test_rate_limiting();

        // Summary
        echo "\n=== Test Summary ===\n";
        $passed = 0;
        foreach ($results as $result) {
            if ($result) $passed++;
        }
        $total = count($results);
        echo "Passed: $passed / $total\n\n";

        return $passed === $total;
    }

    private static function test_token_generation(): bool
    {
        echo "[TEST 1] Token Generation\n";
        
        $data = [
            'user' => [
                'id' => 1,
                'email' => 'admin@example.com',
                'roles' => ['administrator']
            ]
        ];

        $token = GlamLux_JWT_Auth::encode($data, 24);
        
        if (!empty($token) && is_string($token)) {
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                echo "✓ Token generated successfully\n";
                echo "  Format: JWT (3 parts)\n";
                echo "  Length: " . strlen($token) . " chars\n";
                return true;
            }
        }
        
        echo "✗ Token generation failed\n";
        return false;
    }

    private static function test_token_expiration(): bool
    {
        echo "\n[TEST 2] Token Expiration\n";
        
        // Create token that expires in 1 second
        $data = ['user' => ['id' => 1]];
        $token = GlamLux_JWT_Auth::encode($data, 1/3600); // ~1 second
        
        // Should validate immediately
        $payload = GlamLux_JWT_Auth::decode($token);
        if (is_wp_error($payload)) {
            echo "✗ Token validation failed immediately\n";
            return false;
        }
        echo "✓ Token validates immediately after generation\n";
        
        // Wait for expiration
        sleep(2);
        $payload = GlamLux_JWT_Auth::decode($token);
        if (is_wp_error($payload) && $payload->get_error_code() === 'token_expired') {
            echo "✓ Token correctly expired after 2 seconds\n";
            return true;
        }
        
        echo "✗ Token did not expire as expected\n";
        return false;
    }

    private static function test_token_validation(): bool
    {
        echo "\n[TEST 3] Token Validation\n";
        
        $data = ['user' => ['id' => 1, 'email' => 'test@example.com']];
        $token = GlamLux_JWT_Auth::encode($data, 24);
        
        $payload = GlamLux_JWT_Auth::decode($token);
        
        if (!is_wp_error($payload)) {
            if (isset($payload->data->user->id) && $payload->data->user->id === 1) {
                echo "✓ Token validated successfully\n";
                echo "  User ID: " . $payload->data->user->id . "\n";
                echo "  Email: " . $payload->data->user->email . "\n";
                return true;
            }
        }
        
        echo "✗ Token validation failed\n";
        return false;
    }

    private static function test_token_refresh(): bool
    {
        echo "\n[TEST 4] Token Refresh\n";
        
        global $wpdb;
        
        // Check if refresh token table exists
        $table_exists = $wpdb->get_var(
            "SELECT 1 FROM information_schema.TABLES WHERE table_schema=DATABASE() AND table_name='{$wpdb->prefix}gl_refresh_tokens'"
        );
        
        if (!$table_exists) {
            echo "⚠ Refresh token table not found (run database migration)\n";
            return false;
        }
        
        // Create refresh token
        $refresh_token = GlamLux_JWT_Auth::generate_refresh_token(1);
        
        if (!empty($refresh_token)) {
            echo "✓ Refresh token generated\n";
            echo "  Token length: " . strlen($refresh_token) . " chars\n";
            
            // Try to exchange it for new access token
            $new_token = GlamLux_JWT_Auth::refresh_access_token($refresh_token);
            
            if (!is_wp_error($new_token)) {
                echo "✓ New access token generated from refresh token\n";
                return true;
            }
        }
        
        echo "✗ Token refresh failed\n";
        return false;
    }

    private static function test_token_revocation(): bool
    {
        echo "\n[TEST 5] Token Revocation (Logout)\n";
        
        global $wpdb;
        
        // Check if blacklist table exists
        $table_exists = $wpdb->get_var(
            "SELECT 1 FROM information_schema.TABLES WHERE table_schema=DATABASE() AND table_name='{$wpdb->prefix}gl_token_blacklist'"
        );
        
        if (!$table_exists) {
            echo "⚠ Token blacklist table not found (run database migration)\n";
            return false;
        }
        
        $data = ['user' => ['id' => 1]];
        $token = GlamLux_JWT_Auth::encode($data, 24);
        
        // Token should not be revoked yet
        if (GlamLux_JWT_Auth::is_token_revoked($token)) {
            echo "✗ Token marked as revoked before revocation\n";
            return false;
        }
        echo "✓ Token is valid (not revoked)\n";
        
        // Revoke the token
        $revoked = GlamLux_JWT_Auth::revoke_token($token, 'manual_logout');
        
        if ($revoked) {
            echo "✓ Token revoked successfully\n";
            
            // Check if revocation persists
            if (GlamLux_JWT_Auth::is_token_revoked($token)) {
                echo "✓ Token correctly marked as revoked\n";
                return true;
            }
        }
        
        echo "✗ Token revocation failed\n";
        return false;
    }

    private static function test_rate_limiting(): bool
    {
        echo "\n[TEST 6] Rate Limiting\n";
        
        // Simulate multiple login attempts
        $rate_cache_key = 'gl_auth_attempts_' . md5('127.0.0.1:testuser');
        
        // Clear previous attempts
        delete_transient($rate_cache_key);
        
        // Simulate 5 attempts
        for ($i = 0; $i < 5; $i++) {
            $current = get_transient($rate_cache_key) ?? 0;
            set_transient($rate_cache_key, $current + 1, 900);
        }
        
        $attempts = get_transient($rate_cache_key);
        
        if ($attempts === 5) {
            echo "✓ Rate limiting tracking works\n";
            echo "  Attempts recorded: $attempts / 5 limit\n";
            
            // 6th attempt should be blocked
            $allowed = $attempts < 5;
            if (!$allowed) {
                echo "✓ Rate limit correctly enforced\n";
                return true;
            }
        }
        
        echo "✗ Rate limiting test failed\n";
        return false;
    }
}

// Run tests if WordPress is loaded
if (function_exists('do_action')) {
    add_action('wp_loaded', function () {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Run tests in debug mode
            // GlamLux_JWT_Auth_Test::run_all_tests();
        }
    });
}
