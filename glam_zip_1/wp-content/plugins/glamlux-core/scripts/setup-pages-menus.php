<?php
/**
 * Setup Dynamic Navigation Menus & Pages
 * Run via WP-CLI: wp eval-file path/to/this/script.php
 */

echo "--- DYNAMIC PAGES & MENUS SEEDER ---\n";

// 1. Array of pages to ensure exist
$pages_to_create = array(
    'Our Philosophy' => 'philosophy',
    'Beauty Services' => 'services',
    'Franchise Overview' => 'franchise',
    'Apply for Franchise' => 'franchise-apply',
    'Global Locations' => 'locations',
    'Testimonials' => 'testimonials',
    'About Us' => 'about',
    'Careers' => 'careers',
    'Press' => 'press',
    'Contact' => 'contact',
    'Products' => 'products',
    'Gift Cards' => 'gift-cards',
    'Memberships' => 'memberships',
    'Dashboard' => 'dashboard',
    'Partner Portal' => 'partner',
);

$page_ids = array();

foreach ($pages_to_create as $title => $slug) {
    $existing = get_page_by_path($slug);
    if (!$existing) {
        $page_id = wp_insert_post(array(
            'post_title' => $title,
            'post_name' => $slug,
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_content' => "<!-- wp:heading --><h2>{$title}</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Welcome to the {$title} page. This content is fully editable from the WordPress admin panel.</p><!-- /wp:paragraph -->",
        ));
        $page_ids[$title] = $page_id;
        echo "Created Page: {$title} (ID: {$page_id})\n";
    }
    else {
        $page_ids[$title] = $existing->ID;
        echo "Page exists: {$title} (ID: {$existing->ID})\n";
    }
}

// 2. Map Menus to their items
$menus_to_create = array(
    'primary' => array(
        'name' => 'Primary Navigation',
        'items' => array('Our Philosophy', 'Beauty Services', 'Franchise Overview', 'Global Locations', 'Testimonials')
    ),
    'footer_company' => array(
        'name' => 'Footer: Company',
        'items' => array('About Us', 'Careers', 'Press', 'Contact')
    ),
    'footer_services' => array(
        'name' => 'Footer: Services',
        'items' => array('Beauty Services', 'Products', 'Gift Cards', 'Memberships')
    ),
    'footer_franchise' => array(
        'name' => 'Footer: Franchise',
        'items' => array('Franchise Overview', 'Apply for Franchise', 'Dashboard', 'Partner Portal')
    ),
);

$locations = get_theme_mod('nav_menu_locations', array());

foreach ($menus_to_create as $location_key => $menu_data) {
    // Check if menu already exists
    $menu_obj = wp_get_nav_menu_object($menu_data['name']);
    if (!$menu_obj) {
        $menu_id = wp_create_nav_menu($menu_data['name']);
        echo "Created Menu: {$menu_data['name']} (ID: {$menu_id})\n";
    }
    else {
        $menu_id = $menu_obj->term_id;
        echo "Menu exists: {$menu_data['name']} (ID: {$menu_id})\n";
    }

    // Clear existing items to avoid duplicates on re-run
    $items = wp_get_nav_menu_items($menu_id);
    if ($items) {
        foreach ($items as $item) {
            wp_delete_post($item->ID, true);
        }
    }

    // Assign items to menu
    foreach ($menu_data['items'] as $item_title) {
        if (isset($page_ids[$item_title])) {
            wp_update_nav_menu_item($menu_id, 0, array(
                'menu-item-title' => $item_title,
                'menu-item-object-id' => $page_ids[$item_title],
                'menu-item-object' => 'page',
                'menu-item-status' => 'publish',
                'menu-item-type' => 'post_type',
            ));
        }
    }

    // Assign location
    if (!is_array($locations))
        $locations = array();
    $locations[$location_key] = $menu_id;
    echo "Assigned Menu '{$menu_data['name']}' to location '{$location_key}'\n";
}

set_theme_mod('nav_menu_locations', $locations);

echo "\n✅ Successfully generated all pages and assigned native WordPress Menus!\n";
