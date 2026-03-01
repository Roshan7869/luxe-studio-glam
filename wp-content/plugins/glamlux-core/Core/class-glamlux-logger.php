<?php
class GlamLux_Logger
{
    public static function get_log_dir()
    {
        $dir = GLAMLUX_PLUGIN_DIR . 'logs/';
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
            file_put_contents($dir . '.htaccess', 'Deny from all');
        }
        return $dir;
    }

    public static function log($message, $context = [], $level = 'error', $domain = 'core')
    {
        if (stripos($message, '[Payment]') !== false || stripos($message, 'webhook') !== false) {
            $domain = 'payment';
        }
        elseif (stripos($message, '[Payroll]') !== false) {
            $domain = 'payroll';
        }
        elseif (stripos($message, '[Cron]') !== false) {
            $domain = 'cron';
        }

        $dir = self::get_log_dir();
        $file = $dir . 'glamlux-' . $domain . '.log';

        $timestamp = current_time('mysql');

        // Phase 4: Structured JSON Logging
        $log_event = array(
            'timestamp' => $timestamp,
            'level' => $level,
            'domain' => $domain,
            'message' => $message,
            'context' => $context
        );
        $formatted = wp_json_encode($log_event) . PHP_EOL;
        error_log($formatted, 3, $file);

        // Phase 4: Sentry SDK Forwarding
        if (in_array($level, array('error', 'warning'), true) && function_exists('\Sentry\captureMessage')) {
            $sentry_level = $level === 'error' ?\Sentry\Severity::error() : \Sentry\Severity::warning();
            \Sentry\captureMessage($message, $sentry_level);
        }
    }

    public static function info($message, $context = [], $domain = 'core')
    {
        self::log($message, $context, 'info', $domain);
    }

    public static function warning($message, $context = [], $domain = 'core')
    {
        self::log($message, $context, 'warning', $domain);
    }

    public static function error($message, $context = [], $domain = 'core')
    {
        self::log($message, $context, 'error', $domain);
    }
}
