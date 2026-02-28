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
        $context_str = !empty($context) ? ' | Context: ' . wp_json_encode($context) : '';

        $formatted = "[{$timestamp}] [{$level}] {$message}{$context_str}" . PHP_EOL;

        error_log($formatted, 3, $file);
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
