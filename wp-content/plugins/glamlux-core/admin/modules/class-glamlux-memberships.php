<?php
/**
 * GlamLux Memberships Admin Module - Sprint 3 (Full CRUD)
 *
 * Manages membership tiers (create, edit, delete) and active members (grant, revoke).
 * Delegates business logic to GlamLux_Service_Membership.
 */
class GlamLux_Memberships
{
    public function __construct()
    {
        add_action('admin_post_glamlux_create_tier', [$this, 'handle_create_tier']);
        add_action('admin_post_glamlux_update_tier', [$this, 'handle_update_tier']);
        add_action('admin_post_glamlux_delete_tier', [$this, 'handle_delete_tier']);
        add_action('admin_post_glamlux_grant_membership', [$this, 'handle_grant']);
        add_action('admin_post_glamlux_revoke_membership', [$this, 'handle_revoke']);
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'glamlux-core'));
        }

        $service = new GlamLux_Service_Membership();
        $tiers = $service->get_all_tiers();
        $active_count = $service->get_active_member_count();
        $page_slug = sanitize_text_field($_GET['page'] ?? 'glamlux-memberships');

        global $wpdb;
        $members = $wpdb->get_results(
            "SELECT c.id AS client_id, c.membership_id, c.membership_expiry,
                    u.display_name, u.user_email, m.name AS tier_name
             FROM {$wpdb->prefix}gl_clients c
             INNER JOIN {$wpdb->users} u ON c.wp_user_id = u.ID
             INNER JOIN {$wpdb->prefix}gl_memberships m ON c.membership_id = m.id
             WHERE c.membership_id IS NOT NULL AND c.membership_expiry > NOW()
             ORDER BY c.membership_expiry ASC LIMIT 100",
            ARRAY_A
        ) ?: [];

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Membership Management', 'glamlux-core') . '</h1>';
        echo '<hr class="wp-header-end">';

        if (isset($_GET['gl_notice'])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['gl_notice'])) . '</p></div>';
        if (isset($_GET['gl_error'])) echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($_GET['gl_error'])) . '</p></div>';

        // KPI Cards
        echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:16px 0;max-width:500px;">';
        $this->kpi_card(__('Tiers', 'glamlux-core'), count($tiers), '#4A90D9');
        $this->kpi_card(__('Active Members', 'glamlux-core'), $active_count, '#2E7D32');
        $this->kpi_card(__('Expiring (30d)', 'glamlux-core'), $this->count_expiring_soon(), '#E65100');
        echo '</div>';

        // Membership Tiers Table
        echo '<h2>' . esc_html__('Membership Tiers', 'glamlux-core') . '</h2>';
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        echo '<th>' . esc_html__('Tier Name', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Price', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Duration', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Discount', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Actions', 'glamlux-core') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($tiers)) {
            echo '<tr><td colspan="5"><em>' . esc_html__('No membership tiers defined.', 'glamlux-core') . '</em></td></tr>';
        } else {
            foreach ($tiers as $tier) {
                echo '<tr>';
                printf('<td><strong>%s</strong></td>', esc_html($tier['name']));
                printf('<td>Rs %s</td>', number_format((float)$tier['price'], 0));
                printf('<td>%d months</td>', (int)$tier['duration_months']);
                printf('<td>%s%%</td>', number_format((float)$tier['discount_percent'], 1));
                echo '<td>';
                $del_url = wp_nonce_url(admin_url('admin-post.php?action=glamlux_delete_tier&tier_id=' . $tier['id'] . '&return_page=' . $page_slug), 'glamlux_delete_tier_' . $tier['id']);
                printf('<a href="%s" class="button button-small" style="color:#c62828;" onclick="return confirm(\'Delete this tier?\')">%s</a>', esc_url($del_url), esc_html__('Delete', 'glamlux-core'));
                echo '</td></tr>';
            }
        }
        echo '</tbody></table>';

        // Create Tier Form
        echo '<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:8px;margin-top:20px;max-width:700px;">';
        echo '<h3 style="margin-top:0;">' . esc_html__('Create New Tier', 'glamlux-core') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('glamlux_create_tier', '_glamlux_nonce');
        echo '<input type="hidden" name="action" value="glamlux_create_tier">';
        echo '<input type="hidden" name="return_page" value="' . esc_attr($page_slug) . '">';
        echo '<table class="form-table">';
        echo '<tr><th>Tier Name</th><td><input type="text" name="name" class="regular-text" required></td></tr>';
        echo '<tr><th>Price (Rs)</th><td><input type="number" name="price" step="1" min="0" value="999" required></td></tr>';
        echo '<tr><th>Duration (months)</th><td><input type="number" name="duration_months" min="1" value="12" required></td></tr>';
        echo '<tr><th>Discount %</th><td><input type="number" name="discount_percent" step="0.5" min="0" max="100" value="10"></td></tr>';
        echo '<tr><th>WC Product ID</th><td><input type="number" name="wc_product_id" min="0" value="0"></td></tr>';
        echo '<tr><th>Description</th><td><textarea name="description" class="large-text" rows="2"></textarea></td></tr>';
        echo '</table>';
        submit_button(__('Create Tier', 'glamlux-core'), 'primary');
        echo '</form></div>';

        // Active Members Table
        echo '<h2 style="margin-top:30px;">' . esc_html__('Active Members', 'glamlux-core') . '</h2>';
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        echo '<th>Member</th><th>Email</th><th>Tier</th><th>Expires</th><th>Actions</th>';
        echo '</tr></thead><tbody>';

        if (empty($members)) {
            echo '<tr><td colspan="5"><em>No active members.</em></td></tr>';
        } else {
            foreach ($members as $m) {
                $days_left = max(0, (int)((strtotime($m['membership_expiry']) - time()) / 86400));
                echo '<tr>';
                printf('<td><strong>%s</strong></td>', esc_html($m['display_name']));
                printf('<td>%s</td>', esc_html($m['user_email']));
                printf('<td>%s</td>', esc_html($m['tier_name']));
                printf('<td>%s (%dd)</td>', esc_html(date('M j, Y', strtotime($m['membership_expiry']))), $days_left);
                $revoke_url = wp_nonce_url(admin_url('admin-post.php?action=glamlux_revoke_membership&client_id=' . $m['client_id'] . '&return_page=' . $page_slug), 'glamlux_revoke_' . $m['client_id']);
                printf('<td><a href="%s" class="button button-small" style="color:#c62828;" onclick="return confirm(\'Revoke?\')">Revoke</a></td>', esc_url($revoke_url));
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        // Grant Form
        echo '<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:8px;margin-top:20px;max-width:600px;">';
        echo '<h3 style="margin-top:0;">Grant Membership</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">';
        wp_nonce_field('glamlux_grant_membership', '_glamlux_nonce');
        echo '<input type="hidden" name="action" value="glamlux_grant_membership">';
        echo '<input type="hidden" name="return_page" value="' . esc_attr($page_slug) . '">';
        echo '<div><label><strong>Client ID</strong><br><input type="number" name="client_id" min="1" required></label></div>';
        echo '<div><label><strong>Tier</strong><br><select name="membership_id" required><option value="">-- Select --</option>';
        foreach ($tiers as $t) printf('<option value="%d">%s (Rs %s)</option>', (int)$t['id'], esc_html($t['name']), number_format((float)$t['price'], 0));
        echo '</select></label></div>';
        echo '<div>' . get_submit_button(__('Grant', 'glamlux-core'), 'primary', 'submit', false) . '</div>';
        echo '</form></div>';
        echo '</div>';
    }

    public function handle_create_tier()
    {
        check_admin_referer('glamlux_create_tier', '_glamlux_nonce');
        if (!current_user_can('manage_options')) wp_die('Access denied.');
        $service = new GlamLux_Service_Membership();
        $result = $service->create_tier($_POST);
        $page = sanitize_text_field($_POST['return_page'] ?? 'glamlux-memberships');
        $redirect = admin_url('admin.php?page=' . $page);
        wp_redirect(add_query_arg($result ? 'gl_notice' : 'gl_error', urlencode($result ? __('Tier created.', 'glamlux-core') : __('Failed.', 'glamlux-core')), $redirect));
        exit;
    }

    public function handle_update_tier()
    {
        check_admin_referer('glamlux_update_tier', '_glamlux_nonce');
        if (!current_user_can('manage_options')) wp_die('Access denied.');
        $service = new GlamLux_Service_Membership();
        $service->update_tier(absint($_POST['tier_id']), $_POST);
        $page = sanitize_text_field($_POST['return_page'] ?? 'glamlux-memberships');
        wp_redirect(add_query_arg('gl_notice', urlencode(__('Tier updated.', 'glamlux-core')), admin_url('admin.php?page=' . $page)));
        exit;
    }

    public function handle_delete_tier()
    {
        $tid = absint($_GET['tier_id'] ?? 0);
        check_admin_referer('glamlux_delete_tier_' . $tid);
        if (!current_user_can('manage_options')) wp_die('Access denied.');
        $service = new GlamLux_Service_Membership();
        $result = $service->delete_tier($tid);
        $page = sanitize_text_field($_GET['return_page'] ?? 'glamlux-memberships');
        $redirect = admin_url('admin.php?page=' . $page);
        wp_redirect(add_query_arg($result === false ? 'gl_error' : 'gl_notice', urlencode($result === false ? __('Cannot delete: has members.', 'glamlux-core') : __('Tier deleted.', 'glamlux-core')), $redirect));
        exit;
    }

    public function handle_grant()
    {
        check_admin_referer('glamlux_grant_membership', '_glamlux_nonce');
        if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) wp_die('Access denied.');
        $service = new GlamLux_Service_Membership();
        $result = $service->grant(absint($_POST['client_id']), absint($_POST['membership_id']), 'manual');
        $page = sanitize_text_field($_POST['return_page'] ?? 'glamlux-memberships');
        $redirect = admin_url('admin.php?page=' . $page);
        wp_redirect(add_query_arg($result ? 'gl_notice' : 'gl_error', urlencode($result ? __('Granted.', 'glamlux-core') : __('Failed.', 'glamlux-core')), $redirect));
        exit;
    }

    public function handle_revoke()
    {
        $cid = absint($_GET['client_id'] ?? 0);
        check_admin_referer('glamlux_revoke_' . $cid);
        if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) wp_die('Access denied.');
        $service = new GlamLux_Service_Membership();
        $service->revoke($cid);
        $page = sanitize_text_field($_GET['return_page'] ?? 'glamlux-memberships');
        wp_redirect(add_query_arg('gl_notice', urlencode(__('Revoked.', 'glamlux-core')), admin_url('admin.php?page=' . $page)));
        exit;
    }

    public function grant_membership($user_id, $membership_id)
    {
        $service = new GlamLux_Service_Membership();
        return $service->grant($user_id, $membership_id, 'manual');
    }

    private function count_expiring_soon(): int
    {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gl_clients WHERE membership_id IS NOT NULL AND membership_expiry BETWEEN NOW() AND %s",
            date('Y-m-d', strtotime('+30 days'))
        ));
    }

    private function kpi_card($label, $value, $color)
    {
        printf('<div style="background:#fff;border-left:4px solid %s;padding:14px 18px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-radius:4px;">
            <div style="font-size:22px;font-weight:700;color:%s;">%s</div>
            <div style="color:#555;font-size:12px;margin-top:4px;">%s</div>
        </div>', esc_attr($color), esc_attr($color), esc_html($value), esc_html($label));
    }
}