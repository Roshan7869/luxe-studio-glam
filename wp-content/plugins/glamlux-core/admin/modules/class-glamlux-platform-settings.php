<?php
/**
 * GlamLux Platform Settings Admin — Sprint 5
 *
 * Centralized settings page for SMS, payment gateways, and business config.
 * Uses WordPress Settings API for secure storage.
 */
class GlamLux_Platform_Settings
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu'], 90);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_menu()
    {
        add_submenu_page(
            'glamlux-super-admin',
            __('Platform Settings', 'glamlux-core'),
            __('⚙️ Platform Settings', 'glamlux-core'),
            'manage_options',
            'glamlux-platform-settings',
        [$this, 'render_page']
        );
    }

    public function register_settings()
    {
        // ── SMS / Exotel ─────────────────────────────────────────────────
        add_settings_section('glamlux_sms', __('SMS (Exotel)', 'glamlux-core'), function () {
            echo '<p>' . esc_html__('Configure Exotel API credentials for SMS notifications.', 'glamlux-core') . '</p>';
        }, 'glamlux-platform-settings');

        $sms_fields = [
            'glamlux_exotel_key' => __('API Key', 'glamlux-core'),
            'glamlux_exotel_token' => __('API Token', 'glamlux-core'),
            'glamlux_exotel_subdomain' => __('Subdomain', 'glamlux-core'),
            'glamlux_exotel_sid' => __('Account SID', 'glamlux-core'),
        ];
        foreach ($sms_fields as $key => $label) {
            register_setting('glamlux_platform_settings', $key, ['sanitize_callback' => 'sanitize_text_field']);
            add_settings_field($key, $label, [$this, 'text_field'], 'glamlux-platform-settings', 'glamlux_sms', ['key' => $key]);
        }

        // ── Razorpay ────────────────────────────────────────────────────
        add_settings_section('glamlux_razorpay', __('Razorpay', 'glamlux-core'), function () {
            echo '<p>' . esc_html__('Configure Razorpay payment gateway.', 'glamlux-core') . '</p>';
        }, 'glamlux-platform-settings');

        $rp_fields = [
            'glamlux_razorpay_key_id' => __('Key ID', 'glamlux-core'),
            'glamlux_razorpay_key_secret' => __('Key Secret', 'glamlux-core'),
            'glamlux_razorpay_webhook_secret' => __('Webhook Secret', 'glamlux-core'),
        ];
        foreach ($rp_fields as $key => $label) {
            register_setting('glamlux_platform_settings', $key, ['sanitize_callback' => 'sanitize_text_field']);
            add_settings_field($key, $label, [$this, 'password_field'], 'glamlux-platform-settings', 'glamlux_razorpay', ['key' => $key]);
        }

        // ── Stripe ──────────────────────────────────────────────────────
        add_settings_section('glamlux_stripe', __('Stripe', 'glamlux-core'), function () {
            echo '<p>' . esc_html__('Configure Stripe payment gateway.', 'glamlux-core') . '</p>';
        }, 'glamlux-platform-settings');

        $stripe_fields = [
            'glamlux_stripe_secret_key' => __('Secret Key', 'glamlux-core'),
            'glamlux_stripe_publishable_key' => __('Publishable Key', 'glamlux-core'),
            'glamlux_stripe_webhook_secret' => __('Webhook Secret', 'glamlux-core'),
        ];
        foreach ($stripe_fields as $key => $label) {
            register_setting('glamlux_platform_settings', $key, ['sanitize_callback' => 'sanitize_text_field']);
            add_settings_field($key, $label, [$this, 'password_field'], 'glamlux-platform-settings', 'glamlux_stripe', ['key' => $key]);
        }

        // ── Business Config ─────────────────────────────────────────────
        add_settings_section('glamlux_business', __('Business Settings', 'glamlux-core'), function () {
            echo '<p>' . esc_html__('General business configuration.', 'glamlux-core') . '</p>';
        }, 'glamlux-platform-settings');

        register_setting('glamlux_platform_settings', 'glamlux_default_commission_rate', ['sanitize_callback' => 'floatval']);
        add_settings_field('glamlux_default_commission_rate', __('Default Commission Rate (%)', 'glamlux-core'),
        [$this, 'number_field'], 'glamlux-platform-settings', 'glamlux_business',
        ['key' => 'glamlux_default_commission_rate', 'step' => '0.1', 'min' => '0', 'max' => '100']);

        register_setting('glamlux_platform_settings', 'glamlux_currency', ['sanitize_callback' => 'sanitize_text_field']);
        add_settings_field('glamlux_currency', __('Currency', 'glamlux-core'),
        [$this, 'text_field'], 'glamlux-platform-settings', 'glamlux_business', ['key' => 'glamlux_currency']);

        register_setting('glamlux_platform_settings', 'glamlux_late_threshold_minutes', ['sanitize_callback' => 'absint']);
        add_settings_field('glamlux_late_threshold_minutes', __('Late Threshold (minutes)', 'glamlux-core'),
        [$this, 'number_field'], 'glamlux-platform-settings', 'glamlux_business',
        ['key' => 'glamlux_late_threshold_minutes', 'step' => '1', 'min' => '0']);
    }

    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'glamlux-core'));
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Platform Settings', 'glamlux-core') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('glamlux_platform_settings');
        do_settings_sections('glamlux-platform-settings');
        submit_button();
        echo '</form></div>';
    }

    // ── Field Renderers ──────────────────────────────────────────────────

    public function text_field($args)
    {
        $val = get_option($args['key'], '');
        printf('<input type="text" name="%s" value="%s" class="regular-text">', esc_attr($args['key']), esc_attr($val));
    }

    public function password_field($args)
    {
        $val = get_option($args['key'], '');
        $masked = $val ? str_repeat('•', min(strlen($val), 20)) : '';
        printf(
            '<input type="password" name="%s" value="%s" class="regular-text" placeholder="%s">',
            esc_attr($args['key']), esc_attr($val), $masked ? esc_attr__('••• configured •••', 'glamlux-core') : ''
        );
    }

    public function number_field($args)
    {
        $val = get_option($args['key'], '');
        printf(
            '<input type="number" name="%s" value="%s" step="%s" min="%s" max="%s" class="small-text">',
            esc_attr($args['key']), esc_attr($val),
            esc_attr($args['step'] ?? '1'), esc_attr($args['min'] ?? '0'), esc_attr($args['max'] ?? '')
        );
    }
}
