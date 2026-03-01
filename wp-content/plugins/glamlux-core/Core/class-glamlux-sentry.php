<?php

class GlamLux_Sentry
{
    public static function init()
    {
        // 1. Check if Sentry SDK is installed via Composer
        if (!class_exists('\Sentry\ClientBuilder')) {
            return;
        }

        // 2. Load DSN from environment (Railway injects this)
        $dsn = getenv('SENTRY_DSN');
        if (!$dsn) {
            return;
        }

        // 3. Initialize Sentry
        \Sentry\init([
            'dsn' => $dsn,
            'environment' => wp_get_environment_type(),
            // Configure error types to capture
            'error_types' => E_ALL & ~E_DEPRECATED & ~E_NOTICE,
            'sample_rate' => 1.0,
        ]);
    }
}
