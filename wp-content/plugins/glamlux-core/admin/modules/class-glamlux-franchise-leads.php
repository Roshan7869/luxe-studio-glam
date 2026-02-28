<?php
/**
 * GlamLux Admin Module — Franchise Leads / CRM Inbox
 *
 * Renders an admin page listing all wp_gl_leads with
 * interest_type = 'franchise', with inline status-update AJAX.
 */
class GlamLux_Franchise_Leads
{
    /** Status badge colours */
    private static $status_colors = [
        'new' => ['bg' => '#E3F2FD', 'text' => '#1565C0', 'label' => 'New'],
        'contacted' => ['bg' => '#FFF8E1', 'text' => '#F57F17', 'label' => 'Contacted'],
        'qualified' => ['bg' => '#E8F5E9', 'text' => '#2E7D32', 'label' => 'Qualified'],
        'proposal_sent' => ['bg' => '#F3E5F5', 'text' => '#6A1B9A', 'label' => 'Proposal Sent'],
        'converted' => ['bg' => '#E8F5E9', 'text' => '#1B5E20', 'label' => '✅ Converted'],
        'lost' => ['bg' => '#FFEBEE', 'text' => '#B71C1C', 'label' => 'Lost'],
    ];

    public function render_admin_page(): void
    {
        global $wpdb;

        $this->handle_actions($wpdb);
        $this->enqueue_scripts();

        $leads = $wpdb->get_results(
            "SELECT l.*, u.display_name AS assigned_name
               FROM {$wpdb->prefix}gl_leads l
               LEFT JOIN {$wpdb->users} u ON l.assigned_to = u.ID
              WHERE l.interest_type = 'franchise'
              ORDER BY l.created_at DESC",
            ARRAY_A
        ) ?: [];

        $funnel = [];
        foreach (array_keys(self::$status_colors) as $s) {
            $funnel[$s] = 0;
        }
        foreach ($leads as $l) {
            if (isset($funnel[$l['status']])) {
                $funnel[$l['status']]++;
            }
        }

        echo '<div class="wrap">';
        echo '<h1 style="display:flex;align-items:center;gap:12px;">🤝 Franchise Applications <span style="font-size:13px;font-weight:400;color:#666;">(' . count($leads) . ' total)</span></h1>';

        // Funnel strip
        echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0 28px;">';
        foreach (self::$status_colors as $key => $meta) {
            printf(
                '<div style="background:%s;border-radius:8px;padding:10px 18px;min-width:100px;">
                    <div style="font-size:24px;font-weight:700;color:%s;">%s</div>
                    <div style="font-size:11px;color:%s;margin-top:2px;">%s</div>
                </div>',
                esc_attr($meta['bg']),
                esc_attr($meta['text']),
                intval($funnel[$key]),
                esc_attr($meta['text']),
                esc_html($meta['label'])
            );
        }
        echo '</div>';

        if (empty($leads)) {
            echo '<div class="notice notice-info"><p>No franchise applications yet. They will appear here when someone submits the Apply form.</p></div>';
            echo '</div>';
            return;
        }

        // Leads table
        echo '<table class="wp-list-table widefat fixed striped" id="gl-leads-table" style="border-radius:8px;overflow:hidden;">';
        echo '<thead><tr>
            <th style="width:50px;">#</th>
            <th>Applicant</th>
            <th>Contact</th>
            <th>State</th>
            <th>Message</th>
            <th>Status</th>
            <th>Applied</th>
            <th style="width:130px;">Actions</th>
        </tr></thead><tbody>';

        foreach ($leads as $i => $lead):
            $s = $lead['status'] ?? 'new';
            $smeta = self::$status_colors[$s] ?? self::$status_colors['new'];
            printf(
                '<tr id="gl-lead-row-%1$d">
                    <td><strong>%2$s</strong></td>
                    <td>
                        <div style="font-weight:600;color:#121212;">%3$s</div>
                        <div style="font-size:12px;color:#888;margin-top:2px;">%4$s</div>
                    </td>
                    <td>
                        <a href="mailto:%5$s" style="color:#1a73e8;text-decoration:none;">%5$s</a><br>
                        <a href="tel:%6$s" style="color:#555;font-size:12px;text-decoration:none;">%6$s</a>
                    </td>
                    <td>%7$s</td>
                    <td style="max-width:200px;font-size:12px;color:#555;">%8$s</td>
                    <td>
                        <span id="gl-badge-%1$d" style="background:%9$s;color:%10$s;padding:4px 10px;border-radius:9999px;font-size:11px;font-weight:600;white-space:nowrap;">%11$s</span>
                    </td>
                    <td style="font-size:12px;color:#888;">%12$s</td>
                    <td>
                        <select id="gl-status-sel-%1$d" data-lead-id="%1$d" class="gl-status-select"
                                style="font-size:12px;padding:4px 8px;border-radius:6px;border:1px solid #ddd;width:100%%;" onchange="glamluxUpdateLeadStatus(%1$d)">
                            %13$s
                        </select>
                        <div id="gl-status-msg-%1$d" style="font-size:11px;margin-top:4px;display:none;"></div>
                    </td>
                </tr>',
                intval($lead['id']),
                intval($i + 1),
                esc_html($lead['name'] ?? ''),
                esc_html($lead['assigned_name'] ? '👤 Assigned: ' . $lead['assigned_name'] : 'Unassigned'),
                esc_html($lead['email'] ?? ''),
                esc_html($lead['phone'] ?? ''),
                esc_html($lead['state'] ?? '—'),
                esc_html(wp_trim_words($lead['message'] ?? '', 14, '…')),
                esc_attr($smeta['bg']),
                esc_attr($smeta['text']),
                esc_html($smeta['label']),
                esc_html($lead['created_at'] ? date('d M Y, h:i A', strtotime($lead['created_at'])) : '—'),
                $this->render_status_options($s)
            );
        endforeach;

        echo '</tbody></table>';
        echo '<p style="color:#888;font-size:12px;margin-top:16px;">💡 Change status in the dropdown to move a lead through your CRM funnel. Updates save immediately.</p>';
        echo '</div>';

        // Inline JS for AJAX status update
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('glamlux_lead_status');
        $colors = wp_json_encode(self::$status_colors);
        echo "<script>
var glamluxLeadColors = {$colors};
function glamluxUpdateLeadStatus(leadId) {
    var sel = document.getElementById('gl-status-sel-' + leadId);
    var msg = document.getElementById('gl-status-msg-' + leadId);
    var badge = document.getElementById('gl-badge-' + leadId);
    var status = sel.value;
    if (!status) return;
    msg.style.display = 'block';
    msg.style.color = '#888';
    msg.textContent = 'Saving…';
    fetch('" . esc_js($ajax_url) . "', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=glamlux_update_lead_status&lead_id=' + leadId + '&status=' + encodeURIComponent(status) + '&_wpnonce=" . esc_js($nonce) . "'
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if (data && data.success) {
            msg.style.color = '#2E7D32';
            msg.textContent = '✔ Saved';
            if (glamluxLeadColors[status] && badge) {
                badge.style.background = glamluxLeadColors[status].bg;
                badge.style.color = glamluxLeadColors[status].text;
                badge.textContent = glamluxLeadColors[status].label;
            }
            setTimeout(function(){ msg.style.display='none'; }, 2000);
        } else {
            msg.style.color = '#C62828';
            msg.textContent = '✗ ' + ((data && data.data) ? data.data : 'Error');
        }
    })
    .catch(function(){
        msg.style.color = '#C62828';
        msg.textContent = '✗ Network error';
    });
}
</script>";
    }

    private function render_status_options(string $current): string
    {
        $html = '';
        foreach (self::$status_colors as $key => $meta) {
            $sel = ($key === $current) ? ' selected' : '';
            $html .= sprintf('<option value="%s"%s>%s</option>', esc_attr($key), $sel, esc_html($meta['label']));
        }
        return $html;
    }

    private function handle_actions(wpdb $wpdb): void
    {
    // Nothing from GET/POST in this view — all status changes are AJAX
    }

    private function enqueue_scripts(): void
    {
    // Uses WP core table styles — nothing extra needed
    }
}
