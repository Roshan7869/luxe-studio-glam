<?php
/**
 * GlamLux Shift Scheduling Admin Module — Phase 4
 *
 * Weekly shift schedule viewer and editor per salon.
 * Delegates to GlamLux_Service_Attendance::create_shift/update_shift/delete_shift.
 */
class GlamLux_Shifts_Admin
{

    public function __construct()
    {
        add_action('admin_post_glamlux_create_shift', array($this, 'handle_create'));
        add_action('admin_post_glamlux_delete_shift', array($this, 'handle_delete'));
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'glamlux-core'));
        }

        global $wpdb;
        $salon_id = absint($_GET['salon_id'] ?? 0);
        $week_of = sanitize_text_field($_GET['week_of'] ?? date('Y-m-d'));
        $page_slug = sanitize_text_field($_GET['page'] ?? 'glamlux-shifts');

        // Calculate week boundaries (Mon-Sun)
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($week_of)));
        $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($week_of)));

        $salons = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}gl_salons WHERE is_active = 1 ORDER BY name");
        if (!$salon_id && !empty($salons))
            $salon_id = (int)$salons[0]->id;

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Shift Schedule', 'glamlux-core') . '</h1>';
        echo '<hr class="wp-header-end">';

        // Notices
        if (isset($_GET['gl_notice']))
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['gl_notice'])) . '</p></div>';
        if (isset($_GET['gl_error']))
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($_GET['gl_error'])) . '</p></div>';

        // ── Filters ─────────────────────────────────────────────────────
        $prev_week = date('Y-m-d', strtotime('-7 days', strtotime($week_start)));
        $next_week = date('Y-m-d', strtotime('+7 days', strtotime($week_start)));

        echo '<form method="get" style="display:flex;align-items:center;gap:12px;margin:16px 0;flex-wrap:wrap;">';
        echo '<input type="hidden" name="page" value="' . esc_attr($page_slug) . '">';

        echo '<label><strong>' . esc_html__('Salon:', 'glamlux-core') . '</strong> ';
        echo '<select name="salon_id" onchange="this.form.submit()">';
        foreach ($salons as $s) {
            $sel = ((int)$s->id === $salon_id) ? ' selected' : '';
            printf('<option value="%d"%s>%s</option>', $s->id, $sel, esc_html($s->name));
        }
        echo '</select></label>';

        echo '<label><strong>' . esc_html__('Week of:', 'glamlux-core') . '</strong> ';
        echo '<input type="date" name="week_of" value="' . esc_attr($week_of) . '" onchange="this.form.submit()">';
        echo '</label>';

        echo '<div>';
        printf('<a href="%s" class="button" style="margin-right:4px;">← %s</a>', esc_url(add_query_arg(['week_of' => $prev_week, 'salon_id' => $salon_id, 'page' => $page_slug])), esc_html__('Prev', 'glamlux-core'));
        printf('<a href="%s" class="button">%s →</a>', esc_url(add_query_arg(['week_of' => $next_week, 'salon_id' => $salon_id, 'page' => $page_slug])), esc_html__('Next', 'glamlux-core'));
        echo '</div>';
        echo '</form>';

        echo '<h3>' . sprintf(esc_html__('Week: %s – %s', 'glamlux-core'), esc_html(date('M j', strtotime($week_start))), esc_html(date('M j, Y', strtotime($week_end)))) . '</h3>';

        // ── Fetch shifts ────────────────────────────────────────────────
        $service = new GlamLux_Service_Attendance();
        $shifts = $service->get_shifts_for_salon($salon_id, $week_start, $week_end);

        // Group shifts by date
        $by_date = [];
        foreach ($shifts as $shift) {
            $by_date[$shift['shift_date']][] = $shift;
        }

        // ── Weekly Grid ─────────────────────────────────────────────────
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th style="width:120px;">' . esc_html__('Day', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Staff', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Start', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('End', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Type', 'glamlux-core') . '</th>';
        echo '<th>' . esc_html__('Actions', 'glamlux-core') . '</th>';
        echo '</tr></thead><tbody>';

        for ($i = 0; $i < 7; $i++) {
            $day = date('Y-m-d', strtotime("+{$i} days", strtotime($week_start)));
            $day_label = date('D, M j', strtotime($day));
            $day_shifts = $by_date[$day] ?? [];
            $is_today = ($day === date('Y-m-d'));

            if (empty($day_shifts)) {
                echo '<tr' . ($is_today ? ' style="background:#e8f5e9;"' : '') . '>';
                printf('<td><strong%s>%s</strong></td>', $is_today ? ' style="color:#2E7D32;"' : '', esc_html($day_label));
                echo '<td colspan="4"><em>' . esc_html__('No shifts scheduled', 'glamlux-core') . '</em></td>';
                echo '<td>—</td>';
                echo '</tr>';
            }
            else {
                $first = true;
                foreach ($day_shifts as $shift) {
                    echo '<tr' . ($is_today ? ' style="background:#e8f5e9;"' : '') . '>';
                    if ($first) {
                        printf('<td rowspan="%d"><strong%s>%s</strong></td>', count($day_shifts), $is_today ? ' style="color:#2E7D32;"' : '', esc_html($day_label));
                        $first = false;
                    }
                    printf('<td>%s <span style="color:#888;">(%s)</span></td>', esc_html($shift['staff_name'] ?? ''), esc_html($shift['job_role'] ?? ''));
                    printf('<td>%s</td>', esc_html(date('g:i A', strtotime($shift['shift_start']))));
                    printf('<td>%s</td>', esc_html(date('g:i A', strtotime($shift['shift_end']))));
                    printf('<td><span style="background:#e3f2fd;padding:2px 8px;border-radius:3px;font-size:12px;">%s</span></td>', esc_html(ucfirst($shift['type'])));
                    $del_url = wp_nonce_url(
                        admin_url('admin-post.php?action=glamlux_delete_shift&shift_id=' . $shift['id'] . '&page=' . $page_slug . '&salon_id=' . $salon_id . '&week_of=' . $week_of),
                        'glamlux_delete_shift_' . $shift['id']
                    );
                    echo '<td><a href="' . esc_url($del_url) . '" class="button button-small" style="color:#c62828;" onclick="return confirm(\'' . esc_js(__('Delete this shift?', 'glamlux-core')) . '\')">' . esc_html__('Delete', 'glamlux-core') . '</a></td>';
                    echo '</tr>';
                }
            }
        }
        echo '</tbody></table>';

        // ── Create Shift Form ───────────────────────────────────────────
        $staff_list = $wpdb->get_results($wpdb->prepare(
            "SELECT st.id, u.display_name AS name FROM {$wpdb->prefix}gl_staff st
			 LEFT JOIN {$wpdb->users} u ON st.wp_user_id = u.ID
			 WHERE st.salon_id = %d AND st.is_active = 1 ORDER BY u.display_name",
            $salon_id
        ));

        echo '<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:8px;margin-top:20px;max-width:600px;">';
        echo '<h2>' . esc_html__('Add Shift', 'glamlux-core') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('glamlux_shift_form', '_glamlux_nonce');
        echo '<input type="hidden" name="action" value="glamlux_create_shift">';
        echo '<input type="hidden" name="salon_id" value="' . esc_attr($salon_id) . '">';
        echo '<input type="hidden" name="return_page" value="' . esc_attr($page_slug) . '">';
        echo '<input type="hidden" name="week_of" value="' . esc_attr($week_of) . '">';

        echo '<table class="form-table">';
        echo '<tr><th><label for="staff_id">' . esc_html__('Staff', 'glamlux-core') . '</label></th><td>';
        echo '<select name="staff_id" required>';
        echo '<option value="">' . esc_html__('— Select —', 'glamlux-core') . '</option>';
        foreach ($staff_list as $s) {
            printf('<option value="%d">%s</option>', $s->id, esc_html($s->name));
        }
        echo '</select></td></tr>';

        echo '<tr><th><label for="shift_date">' . esc_html__('Date', 'glamlux-core') . '</label></th><td>';
        echo '<input type="date" name="shift_date" required value="' . esc_attr(date('Y-m-d')) . '"></td></tr>';

        echo '<tr><th><label for="shift_start">' . esc_html__('Start Time', 'glamlux-core') . '</label></th><td>';
        echo '<input type="time" name="shift_start" required value="09:00"></td></tr>';

        echo '<tr><th><label for="shift_end">' . esc_html__('End Time', 'glamlux-core') . '</label></th><td>';
        echo '<input type="time" name="shift_end" required value="18:00"></td></tr>';

        echo '<tr><th><label for="type">' . esc_html__('Type', 'glamlux-core') . '</label></th><td>';
        echo '<select name="type">';
        echo '<option value="scheduled">' . esc_html__('Scheduled', 'glamlux-core') . '</option>';
        echo '<option value="overtime">' . esc_html__('Overtime', 'glamlux-core') . '</option>';
        echo '<option value="split">' . esc_html__('Split Shift', 'glamlux-core') . '</option>';
        echo '</select></td></tr>';
        echo '</table>';

        submit_button(__('Create Shift', 'glamlux-core'), 'primary');
        echo '</form>';
        echo '</div>';

        echo '</div>';
    }

    public function handle_create()
    {
        if (!check_admin_referer('glamlux_shift_form', '_glamlux_nonce')) {
            wp_die(esc_html__('Security check failed.', 'glamlux-core'));
        }
        if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'glamlux-core'));
        }

        $service = new GlamLux_Service_Attendance();
        $result = $service->create_shift([
            'staff_id' => absint($_POST['staff_id']),
            'salon_id' => absint($_POST['salon_id']),
            'shift_date' => sanitize_text_field($_POST['shift_date']),
            'shift_start' => sanitize_text_field($_POST['shift_start']),
            'shift_end' => sanitize_text_field($_POST['shift_end']),
            'type' => sanitize_text_field($_POST['type'] ?? 'scheduled'),
        ]);

        $page = sanitize_text_field($_POST['return_page'] ?? 'glamlux-shifts');
        $redirect = admin_url('admin.php?page=' . $page . '&salon_id=' . absint($_POST['salon_id']) . '&week_of=' . sanitize_text_field($_POST['week_of'] ?? date('Y-m-d')));

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('gl_error', urlencode($result->get_error_message()), $redirect));
        }
        else {
            wp_redirect(add_query_arg('gl_notice', urlencode(__('Shift created.', 'glamlux-core')), $redirect));
        }
        exit;
    }

    public function handle_delete()
    {
        $shift_id = absint($_GET['shift_id'] ?? 0);
        if (!check_admin_referer('glamlux_delete_shift_' . $shift_id)) {
            wp_die(esc_html__('Security check failed.', 'glamlux-core'));
        }
        if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'glamlux-core'));
        }

        $service = new GlamLux_Service_Attendance();
        $service->delete_shift($shift_id);

        $page = sanitize_text_field($_GET['page'] ?? 'glamlux-shifts');
        $redirect = admin_url('admin.php?page=' . $page . '&salon_id=' . absint($_GET['salon_id'] ?? 0) . '&week_of=' . sanitize_text_field($_GET['week_of'] ?? date('Y-m-d')));
        wp_redirect(add_query_arg('gl_notice', urlencode(__('Shift deleted.', 'glamlux-core')), $redirect));
        exit;
    }
}
