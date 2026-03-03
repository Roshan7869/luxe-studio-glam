<?php
/**
 * GlamLux Attendance Admin Module — Phase 3 Fix
 *
 * Daily attendance viewer and check-in/out actions.
 * Delegates to GlamLux_Service_Attendance.
 */
class GlamLux_Attendance_Admin
{

    public function __construct()
    {
        add_action('admin_post_glamlux_attendance_checkin', array($this, 'handle_checkin'));
        add_action('admin_post_glamlux_attendance_checkout', array($this, 'handle_checkout'));
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'glamlux-core'));
        }

        global $wpdb;
        $salon_id = absint($_GET['salon_id'] ?? 0);
        $date = sanitize_text_field($_GET['date'] ?? date('Y-m-d'));
        $page_slug = sanitize_text_field($_GET['page'] ?? 'glamlux-attendance');

        $salons = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}gl_salons WHERE is_active = 1 ORDER BY name");
        if (!$salon_id && !empty($salons)) {
            $salon_id = (int)$salons[0]->id;
        }

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Daily Attendance', 'glamlux-core') . '</h1>';
        echo '<hr class="wp-header-end">';

        if (isset($_GET['gl_notice']))
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['gl_notice'])) . '</p></div>';
        if (isset($_GET['gl_error']))
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($_GET['gl_error'])) . '</p></div>';

        // Filters
        echo '<form method="get" style="display:flex;align-items:center;gap:12px;margin:16px 0;flex-wrap:wrap;">';
        echo '<input type="hidden" name="page" value="' . esc_attr($page_slug) . '">';

        echo '<label><strong>' . esc_html__('Salon:', 'glamlux-core') . '</strong> ';
        echo '<select name="salon_id" onchange="this.form.submit()">';
        foreach ($salons as $s) {
            $sel = ((int)$s->id === $salon_id) ? ' selected' : '';
            printf('<option value="%d"%s>%s</option>', $s->id, $sel, esc_html($s->name));
        }
        echo '</select></label>';

        echo '<label><strong>' . esc_html__('Date:', 'glamlux-core') . '</strong> ';
        echo '<input type="date" name="date" value="' . esc_attr($date) . '" onchange="this.form.submit()">';
        echo '</label>';
        echo '</form>';

        // Fetch staff for this salon
        $staff_list = $wpdb->get_results($wpdb->prepare(
            "SELECT st.id, u.display_name AS name, st.job_role, st.is_active 
             FROM {$wpdb->prefix}gl_staff st
             LEFT JOIN {$wpdb->users} u ON st.wp_user_id = u.ID
             WHERE st.salon_id = %d AND st.is_active = 1 ORDER BY u.display_name",
            $salon_id
        ));

        // Fetch attendance for the date
        $attendance_data = $wpdb->get_results($wpdb->prepare(
            "SELECT a.* FROM {$wpdb->prefix}gl_attendance a
             INNER JOIN {$wpdb->prefix}gl_staff st ON a.staff_id = st.id
             WHERE st.salon_id = %d AND a.shift_date = %s",
            $salon_id, $date
        ), ARRAY_A);

        $att_map = [];
        if ($attendance_data) {
            foreach ($attendance_data as $row) {
                $att_map[$row['staff_id']] = $row;
            }
        }

        // Attendance Table
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Staff Member', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Role', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Check In', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Check Out', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Status', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Actions', 'glamlux-core') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($staff_list)) {
            echo '<tr><td colspan="6"><em>' . esc_html__('No active staff found for this salon.', 'glamlux-core') . '</em></td></tr>';
        }
        else {
            foreach ($staff_list as $staff) {
                $att = $att_map[$staff->id] ?? null;
                $has_checked_in = !empty($att);
                $has_checked_out = $has_checked_in && !empty($att['check_out']);

                $status = '<span style="color:#9e9e9e;">Absent/Not Clocked In</span>';
                if ($has_checked_in) {
                    if ($has_checked_out) {
                        $status = '<span style="color:#2E7D32;">Completed Shift</span>';
                    }
                    else {
                        $status = '<span style="color:#1565C0;"><strong>Working</strong></span>';
                    }
                    if (!empty($att['is_late'])) {
                        $status .= ' <span style="background:#ffebee;color:#c62828;padding:2px 6px;border-radius:12px;font-size:11px;">Late (' . (int)$att['late_minutes'] . 'm)</span>';
                    }
                }

                echo '<tr>';
                printf('<td><strong>%s</strong></td>', esc_html($staff->name));
                printf('<td>%s</td>', esc_html($staff->job_role));

                if ($has_checked_in) {
                    printf('<td>%s</td>', esc_html(date('g:i A', strtotime($att['check_in']))));
                    printf('<td>%s</td>', $has_checked_out ? esc_html(date('g:i A', strtotime($att['check_out']))) : '—');
                }
                else {
                    echo '<td>—</td><td>—</td>';
                }

                printf('<td>%s</td>', $status);

                // Actions
                echo '<td>';
                // Only allow checking in/out for TODAY
                if ($date === date('Y-m-d')) {
                    if (!$has_checked_in) {
                        $ci_url = wp_nonce_url(
                            admin_url('admin-post.php?action=glamlux_attendance_checkin&staff_id=' . $staff->id . '&salon_id=' . $salon_id . '&page=' . $page_slug . '&date=' . $date),
                            'glamlux_checkin_' . $staff->id
                        );
                        echo '<a href="' . esc_url($ci_url) . '" class="button button-small button-primary">' . esc_html__('Check In', 'glamlux-core') . '</a>';
                    }
                    elseif (!$has_checked_out) {
                        $co_url = wp_nonce_url(
                            admin_url('admin-post.php?action=glamlux_attendance_checkout&staff_id=' . $staff->id . '&salon_id=' . $salon_id . '&page=' . $page_slug . '&date=' . $date),
                            'glamlux_checkout_' . $staff->id
                        );
                        echo '<a href="' . esc_url($co_url) . '" class="button button-small">' . esc_html__('Check Out', 'glamlux-core') . '</a>';
                    }
                }
                echo '</td>';

                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        echo '</div>';
    }

    public function handle_checkin()
    {
        $staff_id = absint($_GET['staff_id'] ?? 0);
        $salon_id = absint($_GET['salon_id'] ?? 0);

        if (!check_admin_referer('glamlux_checkin_' . $staff_id)) {
            wp_die('Security check failed.');
        }
        if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        $service = new GlamLux_Service_Attendance();
        $result = $service->check_in($staff_id, $salon_id);

        $page = sanitize_text_field($_GET['page'] ?? 'glamlux-attendance');
        $date = sanitize_text_field($_GET['date'] ?? date('Y-m-d'));
        $redirect = admin_url('admin.php?page=' . $page . '&salon_id=' . $salon_id . '&date=' . $date);

        if ($result) {
            wp_redirect(add_query_arg('gl_notice', urlencode(__('Staff checked in successfully.', 'glamlux-core')), $redirect));
        }
        else {
            wp_redirect(add_query_arg('gl_error', urlencode(__('Check-in failed. Staff might already be checked in.', 'glamlux-core')), $redirect));
        }
        exit;
    }

    public function handle_checkout()
    {
        $staff_id = absint($_GET['staff_id'] ?? 0);
        $salon_id = absint($_GET['salon_id'] ?? 0);

        if (!check_admin_referer('glamlux_checkout_' . $staff_id)) {
            wp_die('Security check failed.');
        }
        if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        $service = new GlamLux_Service_Attendance();
        $result = $service->check_out($staff_id);

        $page = sanitize_text_field($_GET['page'] ?? 'glamlux-attendance');
        $date = sanitize_text_field($_GET['date'] ?? date('Y-m-d'));
        $redirect = admin_url('admin.php?page=' . $page . '&salon_id=' . $salon_id . '&date=' . $date);

        if ($result) {
            wp_redirect(add_query_arg('gl_notice', urlencode(__('Staff checked out successfully.', 'glamlux-core')), $redirect));
        }
        else {
            wp_redirect(add_query_arg('gl_error', urlencode(__('Check-out failed. Staff might already be checked out.', 'glamlux-core')), $redirect));
        }
        exit;
    }
}
