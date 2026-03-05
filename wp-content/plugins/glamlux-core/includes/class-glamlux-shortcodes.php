<?php
/**
 * GlamLux Shortcodes — Frontend Widget Shortcodes
 *
 * Registers WordPress shortcodes for embedding GlamLux UI components
 * in posts, pages, and widget areas.
 *
 * @package GlamLux
 * @subpackage Includes
 * @since 3.0.0
 */
class GlamLux_Shortcodes
{
    public function __construct()
    {
        add_shortcode('glamlux_booking', [$this, 'render_booking_form']);
        add_shortcode('glamlux_services', [$this, 'render_services_list']);
        add_shortcode('glamlux_salons', [$this, 'render_salons_list']);
    }

    /**
     * Render booking form shortcode
     */
    public function render_booking_form($atts)
    {
        $atts = shortcode_atts([
            'salon_id' => '',
            'service_id' => '',
        ], $atts, 'glamlux_booking');

        ob_start();
        echo '<div id="glamlux-booking-widget" data-salon="' . esc_attr($atts['salon_id']) . '" data-service="' . esc_attr($atts['service_id']) . '"></div>';
        return ob_get_clean();
    }

    /**
     * Render services list shortcode
     */
    public function render_services_list($atts)
    {
        $atts = shortcode_atts([
            'salon_id' => '',
            'limit' => 10,
        ], $atts, 'glamlux_services');

        ob_start();
        echo '<div id="glamlux-services-widget" data-salon="' . esc_attr($atts['salon_id']) . '" data-limit="' . esc_attr($atts['limit']) . '"></div>';
        return ob_get_clean();
    }

    /**
     * Render salons list shortcode
     */
    public function render_salons_list($atts)
    {
        $atts = shortcode_atts([
            'limit' => 10,
        ], $atts, 'glamlux_salons');

        ob_start();
        echo '<div id="glamlux-salons-widget" data-limit="' . esc_attr($atts['limit']) . '"></div>';
        return ob_get_clean();
    }
}
