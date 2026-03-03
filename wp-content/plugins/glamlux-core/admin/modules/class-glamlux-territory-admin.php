<?php
/**
 * GlamLux Territory Admin Module — Sprint 6
 *
 * Admin page for viewing and managing franchise territory assignments.
 * Delegates to GlamLux_Service_Territory for business logic.
 */
class GlamLux_Territory_Admin
{

    public function __construct()
    {
        add_action('admin_post_glamlux_assign_territory', array($this, 'handle_assign'));
        add_action('admin_post_glamlux_remove_territory', array($this, 'handle_remove'));
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'glamlux-core'));
        }

        $service = new GlamLux_Service_Territory();
        $page_slug = sanitize_text_field($_GET['page'] ?? 'glamlux-territories');

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Territory Management', 'glamlux-core') . '</h1>';
        echo '<hr class="wp-header-end">';

        if (isset($_GET['gl_notice']))
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['gl_notice'])) . '</p></div>';
        if (isset($_GET['gl_error']))
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($_GET['gl_error'])) . '</p></div>';

        // Get territory map and conflicts
        $territory_map = $service->get_territory_map();
        $conflicts = [];
        foreach ($territory_map as $state => $franchises) {
            if (count($franchises) > 1) {
                $conflicts[$state] = $franchises;
            }
        }

        // Get all franchises for assignment
        global $wpdb;
        $franchises_list = $wpdb->get_results("SELECT id, franchise_name FROM {$wpdb->prefix}gl_franchises WHERE is_active = 1 ORDER BY franchise_name");

        // Indian states for assignment dropdown
        $states = [
            'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa',
            'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala',
            'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland',
            'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
            'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Delhi', 'Chandigarh', 'Puducherry'
        ];

        // Conflict warnings
        if (!empty($conflicts)) {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__('⚠️ Territory Conflicts Detected:', 'glamlux-core') . '</strong></p><ul style="margin-left:20px;">';
            foreach ($conflicts as $state => $frs) {
                $names = array_map(fn($f) => esc_html($f->franchise_name ?? $f['franchise_name'] ?? 'Unknown'), $frs);
                printf('<li><strong>%s:</strong> %s</li>', esc_html($state), implode(', ', $names));
            }
            echo '</ul></div>';
        }

        // KPIs
        $total_states = count($territory_map);
        $assigned = count(array_filter($territory_map, fn($v) => !empty($v)));
        $conflict_count = count($conflicts);

        echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:16px 0;max-width:500px;">';
        $this->kpi_card(__('Total Territories', 'glamlux-core'), $total_states, '#4A90D9');
        $this->kpi_card(__('Assigned', 'glamlux-core'), $assigned, '#2E7D32');
        $this->kpi_card(__('Conflicts', 'glamlux-core'), $conflict_count, $conflict_count > 0 ? '#C62828' : '#2E7D32');
        echo '</div>';

        // Assign form
        echo '<div style="background:#fff;padding:16px;border:1px solid #ccd0d4;border-radius:6px;margin:16px 0;max-width:600px;">';
        echo '<h3 style="margin-top:0;">' . esc_html__('Assign Territory', 'glamlux-core') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">';
        wp_nonce_field('glamlux_assign_territory', '_glamlux_nonce');
        echo '<input type="hidden" name="action" value="glamlux_assign_territory">';
        echo '<input type="hidden" name="return_page" value="' . esc_attr($page_slug) . '">';

        echo '<div><label><strong>' . esc_html__('State', 'glamlux-core') . '</strong><br>';
        echo '<select name="state" required><option value="">' . esc_html__('— Select —', 'glamlux-core') . '</option>';
        foreach ($states as $s)
            printf('<option value="%s">%s</option>', esc_attr($s), esc_html($s));
        echo '</select></label></div>';

        echo '<div><label><strong>' . esc_html__('Franchise', 'glamlux-core') . '</strong><br>';
        echo '<select name="franchise_id" required><option value="">' . esc_html__('— Select —', 'glamlux-core') . '</option>';
        foreach ($franchises_list as $f)
            printf('<option value="%d">%s</option>', $f->id, esc_html($f->franchise_name));
        echo '</select></label></div>';

        echo '<div>' . get_submit_button(__('Assign', 'glamlux-core'), 'primary', 'submit', false) . '</div>';
        echo '</form></div>';

        // Territory table
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('State', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Assigned Franchise(s)', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Status', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Actions', 'glamlux-core') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($territory_map)) {
            echo '<tr><td colspan="4"><em>' . esc_html__('No territory assignments found.', 'glamlux-core') . '</em></td></tr>';
        }
        else {
            foreach ($territory_map as $state => $franchises) {
                $is_conflict = count($franchises) > 1;
                echo '<tr>';
                printf('<td><strong>%s</strong></td>', esc_html($state));

                if (empty($franchises)) {
                    echo '<td><em>' . esc_html__('Unassigned', 'glamlux-core') . '</em></td>';
                    echo '<td><span style="color:#9E9E9E;">—</span></td>';
                    echo '<td>—</td>';
                }
                else {
                    $names = [];
                    $actions = [];
                    foreach ($franchises as $f) {
                        $fid = $f->id ?? $f['franchise_id'] ?? 0;
                        $fname = $f->franchise_name ?? $f['franchise_name'] ?? 'Unknown';
                        $names[] = esc_html($fname);

                        $remove_url = wp_nonce_url(
                            admin_url('admin-post.php?action=glamlux_remove_territory&state=' . urlencode($state) . '&franchise_id=' . $fid . '&return_page=' . $page_slug),
                            'glamlux_remove_territory_' . $fid
                        );
                        $actions[] = sprintf(
                            '<a href="%s" class="button button-small" style="color:#c62828;" onclick="return confirm(\'%s\')">%s %s</a>',
                            esc_url($remove_url),
                            esc_js(sprintf(__('Remove %s from %s?', 'glamlux-core'), $fname, $state)),
                            esc_html__('Remove', 'glamlux-core'),
                            esc_html($fname)
                        );
                    }
                    printf('<td>%s</td>', implode(', ', $names));
                    printf('<td>%s</td>', $is_conflict
                        ? '<span style="color:#C62828;font-weight:600;">⚠️ Conflict</span>'
                        : '<span style="color:#2E7D32;">✅ OK</span>');
                    printf('<td>%s</td>', implode(' ', $actions));
                }
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Handlers
    // ─────────────────────────────────────────────────────────────────────────

    public function handle_assign()
    {
        check_admin_referer('glamlux_assign_territory', '_glamlux_nonce');
        if (!current_user_can('manage_options'))
            wp_die('Access denied.');

        $state = sanitize_text_field($_POST['state'] ?? '');
        $franchise_id = absint($_POST['franchise_id'] ?? 0);

        if (!$state || !$franchise_id) {
            $redirect = admin_url('admin.php?page=' . sanitize_text_field($_POST['return_page'] ?? 'glamlux-territories'));
            wp_redirect(add_query_arg('gl_error', urlencode(__('State and franchise are required.', 'glamlux-core')), $redirect));
            exit;
        }

        $service = new GlamLux_Service_Territory();

        // Check for conflicts
        if ($service->has_territory_conflict($state, $franchise_id)) {
            $redirect = admin_url('admin.php?page=' . sanitize_text_field($_POST['return_page'] ?? 'glamlux-territories'));
            wp_redirect(add_query_arg('gl_error', urlencode(__('This franchise already has this territory.', 'glamlux-core')), $redirect));
            exit;
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'gl_franchises',
        ['state' => $state],
        ['id' => $franchise_id],
        ['%s'], ['%d']
        );

        $page = sanitize_text_field($_POST['return_page'] ?? 'glamlux-territories');
        wp_redirect(add_query_arg('gl_notice', urlencode(__('Territory assigned.', 'glamlux-core')), admin_url('admin.php?page=' . $page)));
        exit;
    }

    public function handle_remove()
    {
        $franchise_id = absint($_GET['franchise_id'] ?? 0);
        check_admin_referer('glamlux_remove_territory_' . $franchise_id);
        if (!current_user_can('manage_options'))
            wp_die('Access denied.');

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'gl_franchises',
        ['state' => ''],
        ['id' => $franchise_id],
        ['%s'], ['%d']
        );

        $page = sanitize_text_field($_GET['return_page'] ?? 'glamlux-territories');
        wp_redirect(add_query_arg('gl_notice', urlencode(__('Territory removed.', 'glamlux-core')), admin_url('admin.php?page=' . $page)));
        exit;
    }

    private function kpi_card($label, $value, $color)
    {
        printf(
            '<div style="background:#fff;border-left:4px solid %s;padding:14px 18px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-radius:4px;">
				<div style="font-size:22px;font-weight:700;color:%s;">%s</div>
				<div style="color:#555;font-size:12px;margin-top:4px;">%s</div>
			</div>',
            esc_attr($color), esc_attr($color), esc_html($value), esc_html($label)
        );
    }
}
