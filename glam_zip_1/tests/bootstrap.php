<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Define WP constants if needed by tests
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!defined('GLAMLUX_PLUGIN_DIR')) {
    define('GLAMLUX_PLUGIN_DIR', __DIR__ . '/../wp-content/plugins/glamlux-core/');
}

// Minimal function mocks for WordPress so classes can be autoloaded safely
if (!function_exists('add_action')) {
    function add_action()
    {
    }
}
if (!function_exists('add_filter')) {
    function add_filter()
    {
    }
}
if (!function_exists('register_activation_hook')) {
    function register_activation_hook()
    {
    }
}
if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook()
    {
    }
}
if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default')
    {
        return $text;
    }
}
