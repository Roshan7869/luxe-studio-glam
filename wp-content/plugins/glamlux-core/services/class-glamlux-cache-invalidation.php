<?php
/**
 * Cache Invalidation Manager
 *
 * Automatically invalidates cache when data changes through hooks.
 * Integrated with WordPress post/user updates and custom events.
 *
 * @package GlamLux
 * @subpackage Services
 * @since 7.2
 */

class GlamLux_Cache_Invalidation
{
    public static function init()
    {
        // Post updates
        add_action('save_post_salon', [__CLASS__, 'invalidate_salons']);
        add_action('save_post_service', [__CLASS__, 'invalidate_services']);
        add_action('save_post_staff', [__CLASS__, 'invalidate_staff']);
        add_action('delete_post', [__CLASS__, 'invalidate_post_type']);

        // User updates
        add_action('profile_update', [__CLASS__, 'invalidate_staff']);
        add_action('delete_user', [__CLASS__, 'invalidate_staff']);
        add_action('user_register', [__CLASS__, 'invalidate_staff']);

        // Booking updates (invalidate salons availability)
        add_action('glamlux_booking_created', [__CLASS__, 'invalidate_availability']);
        add_action('glamlux_booking_cancelled', [__CLASS__, 'invalidate_availability']);
        add_action('glamlux_booking_completed', [__CLASS__, 'invalidate_availability']);

        // Event system integration
        add_action('glamlux_event_appointment_created', [__CLASS__, 'invalidate_salons']);
        add_action('glamlux_event_appointment_completed', [__CLASS__, 'invalidate_availability']);

        glamlux_log('Cache invalidation hooks registered');
    }

    /**
     * Invalidate salon cache
     */
    public static function invalidate_salons()
    {
        require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
        $cache = glamlux_cache();

        $count = $cache->invalidate_pattern('salons_*');
        glamlux_log('Cache invalidated: salons (' . $count . ' keys)');
    }

    /**
     * Invalidate service cache
     */
    public static function invalidate_services()
    {
        require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
        $cache = glamlux_cache();

        $count = $cache->invalidate_pattern('services_*');
        glamlux_log('Cache invalidated: services (' . $count . ' keys)');
    }

    /**
     * Invalidate staff cache
     */
    public static function invalidate_staff()
    {
        require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
        $cache = glamlux_cache();

        $count = $cache->invalidate_pattern('staff_*');
        glamlux_log('Cache invalidated: staff (' . $count . ' keys)');
    }

    /**
     * Invalidate availability cache
     */
    public static function invalidate_availability()
    {
        require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
        $cache = glamlux_cache();

        $count = $cache->invalidate_pattern('availability_*');
        glamlux_log('Cache invalidated: availability (' . $count . ' keys)');
    }

    /**
     * Invalidate cache by post type
     */
    public static function invalidate_post_type($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
        $cache = glamlux_cache();

        switch ($post->post_type) {
            case 'salon':
                $cache->invalidate_pattern('salons_*');
                break;
            case 'service':
                $cache->invalidate_pattern('services_*');
                break;
            case 'staff':
                $cache->invalidate_pattern('staff_*');
                break;
        }
    }

    /**
     * Invalidate specific pattern
     */
    public static function invalidate_pattern($pattern)
    {
        require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
        $cache = glamlux_cache();
        return $cache->invalidate_pattern($pattern);
    }

    /**
     * Clear all cache
     */
    public static function flush_all()
    {
        require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-redis-cache.php';
        $cache = glamlux_cache();
        return $cache->flush();
    }
}

// Initialize cache invalidation on plugin load
add_action('glamlux_init', function () {
    GlamLux_Cache_Invalidation::init();
});
