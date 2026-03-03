<?php
/**
 * Inventory Admin UI - Sprint 4 (Server-Rendered CRUD)
 */
class GlamLux_Inventory_Admin
{
    public function __construct()
    {
        add_action('admin_post_glamlux_add_inventory', [$this, 'handle_add']);
        add_action('admin_post_glamlux_restock_inventory', [$this, 'handle_restock']);
        add_action('admin_post_glamlux_delete_inventory', [$this, 'handle_delete']);
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'glamlux-core'));
        }

        global $wpdb;
        $salon_id = absint($_GET['salon_id'] ?? 0);
        $page_slug = sanitize_text_field($_GET['page'] ?? 'glamlux-inventory');

        $salons = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}gl_salons WHERE is_active = 1 ORDER BY name");
        if (!$salon_id && !empty($salons)) $salon_id = (int)$salons[0]->id;

        $service = new GlamLux_Service_Inventory();
        $items = $service->get_items($salon_id);

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Inventory Management', 'glamlux-core') . '</h1>';
        echo '<hr class="wp-header-end">';

        if (isset($_GET['gl_notice'])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['gl_notice'])) . '</p></div>';
        if (isset($_GET['gl_error'])) echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($_GET['gl_error'])) . '</p></div>';

        // Salon Selector
        echo '<form method="get" style="margin:16px 0;">';
        echo '<input type="hidden" name="page" value="' . esc_attr($page_slug) . '">';
        echo '<label><strong>' . esc_html__('Salon:', 'glamlux-core') . '</strong> ';
        echo '<select name="salon_id" onchange="this.form.submit()">';
        foreach ($salons as $s) {
            $sel = ((int)$s->id === $salon_id) ? ' selected' : '';
            printf('<option value="%d"%s>%s</option>', $s->id, $sel, esc_html($s->name));
        }
        echo '</select></label></form>';

        // KPI Cards
        $total = count($items);
        $low = count(array_filter($items, fn($i) => (int)$i['quantity'] <= (int)$i['reorder_threshold']));
        $value = array_sum(array_map(fn($i) => (float)$i['unit_cost'] * (int)$i['quantity'], $items));

        echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:16px 0;max-width:500px;">';
        $this->kpi_card(__('Total Items', 'glamlux-core'), $total, '#4A90D9');
        $this->kpi_card(__('Low Stock', 'glamlux-core'), $low, $low > 0 ? '#C62828' : '#2E7D32');
        $this->kpi_card(__('Stock Value', 'glamlux-core'), 'Rs ' . number_format($value, 0), '#6A1B9A');
        echo '</div>';

        // Inventory Table
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        echo '<th>Product</th><th>SKU</th><th>Stock</th><th>Reorder At</th><th>Unit Price</th><th>Status</th><th>Actions</th>';
        echo '</tr></thead><tbody>';

        if (empty($items)) {
            echo '<tr><td colspan="7"><em>No inventory items found.</em></td></tr>';
        } else {
            foreach ($items as $item) {
                $is_low = (int)$item['quantity'] <= (int)$item['reorder_threshold'];
                echo '<tr>';
                printf('<td><strong>%s</strong></td>', esc_html($item['product_name']));
                printf('<td>%s</td>', esc_html($item['sku'] ?? '-'));
                printf('<td style="%s">%d</td>', $is_low ? 'color:#C62828;font-weight:700;' : '', (int)$item['quantity']);
                printf('<td>%d</td>', (int)$item['reorder_threshold']);
                printf('<td>Rs %s</td>', number_format((float)($item['price_per_unit'] ?? 0), 2));
                printf('<td>%s</td>', $is_low ? '<span style="background:#ffebee;color:#C62828;padding:2px 8px;border-radius:12px;font-size:11px;">Low</span>' : '<span style="color:#2E7D32;">OK</span>');

                echo '<td style="display:flex;gap:4px;align-items:center;">';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:4px;">';
                wp_nonce_field('glamlux_restock_' . $item['id'], '_glamlux_nonce');
                echo '<input type="hidden" name="action" value="glamlux_restock_inventory">';
                echo '<input type="hidden" name="item_id" value="' . esc_attr($item['id']) . '">';
                echo '<input type="hidden" name="salon_id" value="' . esc_attr($salon_id) . '">';
                echo '<input type="hidden" name="return_page" value="' . esc_attr($page_slug) . '">';
                echo '<input type="number" name="restock_qty" min="1" value="10" style="width:60px;" required>';
                echo '<button type="submit" class="button button-small">+Restock</button>';
                echo '</form>';
                $del_url = wp_nonce_url(admin_url('admin-post.php?action=glamlux_delete_inventory&item_id=' . $item['id'] . '&salon_id=' . $salon_id . '&return_page=' . $page_slug), 'glamlux_delete_inv_' . $item['id']);
                echo '<a href="' . esc_url($del_url) . '" class="button button-small" style="color:#c62828;" onclick="return confirm(\'Delete?\')">Delete</a>';
                echo '</td></tr>';
            }
        }
        echo '</tbody></table>';

        // Add Item Form
        echo '<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:8px;margin-top:20px;max-width:600px;">';
        echo '<h2 style="margin-top:0;">Add Inventory Item</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('glamlux_add_inventory', '_glamlux_nonce');
        echo '<input type="hidden" name="action" value="glamlux_add_inventory">';
        echo '<input type="hidden" name="salon_id" value="' . esc_attr($salon_id) . '">';
        echo '<input type="hidden" name="return_page" value="' . esc_attr($page_slug) . '">';
        echo '<table class="form-table">';
        echo '<tr><th>Product Name</th><td><input type="text" name="product_name" class="regular-text" required></td></tr>';
        echo '<tr><th>SKU</th><td><input type="text" name="sku" class="regular-text"></td></tr>';
        echo '<tr><th>Category</th><td><select name="category"><option value="general">General</option><option value="hair">Hair</option><option value="skin">Skin</option><option value="nails">Nails</option><option value="tools">Tools</option><option value="consumables">Consumables</option></select></td></tr>';
        echo '<tr><th>Initial Quantity</th><td><input type="number" name="quantity" min="0" value="0" required></td></tr>';
        echo '<tr><th>Reorder Threshold</th><td><input type="number" name="reorder_threshold" min="0" value="5"></td></tr>';
        echo '<tr><th>Unit Cost (Rs)</th><td><input type="number" name="unit_cost" step="0.01" min="0" value="0"></td></tr>';
        echo '<tr><th>Selling Price (Rs)</th><td><input type="number" name="price_per_unit" step="0.01" min="0" value="0"></td></tr>';
        echo '</table>';
        submit_button(__('Add Item', 'glamlux-core'), 'primary');
        echo '</form></div></div>';
    }

    public function handle_add()
    {
        check_admin_referer('glamlux_add_inventory', '_glamlux_nonce');
        if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) wp_die('Access denied.');
        $service = new GlamLux_Service_Inventory();
        $result = $service->add_item($_POST);
        $page = sanitize_text_field($_POST['return_page'] ?? 'glamlux-inventory');
        $redirect = admin_url('admin.php?page=' . $page . '&salon_id=' . absint($_POST['salon_id'] ?? 0));
        wp_redirect(add_query_arg(is_wp_error($result) ? 'gl_error' : 'gl_notice', urlencode(is_wp_error($result) ? $result->get_error_message() : __('Item added.', 'glamlux-core')), $redirect));
        exit;
    }

    public function handle_restock()
    {
        $item_id = absint($_POST['item_id'] ?? 0);
        check_admin_referer('glamlux_restock_' . $item_id, '_glamlux_nonce');
        if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) wp_die('Access denied.');
        $qty = absint($_POST['restock_qty'] ?? 0);
        $service = new GlamLux_Service_Inventory();
        $service->restock($item_id, $qty);
        $page = sanitize_text_field($_POST['return_page'] ?? 'glamlux-inventory');
        $redirect = admin_url('admin.php?page=' . $page . '&salon_id=' . absint($_POST['salon_id'] ?? 0));
        wp_redirect(add_query_arg('gl_notice', urlencode(sprintf(__('Restocked %d units.', 'glamlux-core'), $qty)), $redirect));
        exit;
    }

    public function handle_delete()
    {
        $item_id = absint($_GET['item_id'] ?? 0);
        check_admin_referer('glamlux_delete_inv_' . $item_id);
        if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) wp_die('Access denied.');
        $service = new GlamLux_Service_Inventory();
        $service->delete_item($item_id);
        $page = sanitize_text_field($_GET['return_page'] ?? 'glamlux-inventory');
        $redirect = admin_url('admin.php?page=' . $page . '&salon_id=' . absint($_GET['salon_id'] ?? 0));
        wp_redirect(add_query_arg('gl_notice', urlencode(__('Item deleted.', 'glamlux-core')), $redirect));
        exit;
    }

    private function kpi_card($label, $value, $color)
    {
        printf('<div style="background:#fff;border-left:4px solid %s;padding:14px 18px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-radius:4px;">
            <div style="font-size:22px;font-weight:700;color:%s;">%s</div>
            <div style="color:#555;font-size:12px;margin-top:4px;">%s</div>
        </div>', esc_attr($color), esc_attr($color), esc_html($value), esc_html($label));
    }
}