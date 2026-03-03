<?php
/**
 * Staff Admin UI Module — Phase 2
 *
 * Full CRUD admin page for staff management.
 * Delegates all data operations to GlamLux_Service_Staff.
 * RULE: Pure service calls only. No SQL.
 */
class GlamLux_Staff
{

	public function __construct()
	{
		add_action('admin_post_glamlux_create_staff', array($this, 'handle_create'));
		add_action('admin_post_glamlux_update_staff', array($this, 'handle_update'));
		add_action('admin_post_glamlux_deactivate_staff', array($this, 'handle_deactivate'));
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Admin Page Render
	// ─────────────────────────────────────────────────────────────────────────

	public function render_admin_page()
	{
		if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) {
			wp_die('Unauthorized');
		}

		$service = new GlamLux_Service_Staff();
		$staff_members = $service->get_all([]);
		$editing_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
		$editing = $editing_id ? $service->get_by_id($editing_id) : null;

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__('Manage Staff', 'glamlux-core') . '</h1>';
		echo '<a href="#glamlux-staff-form" class="page-title-action" onclick="document.getElementById(\'glamlux-staff-form\').style.display=\'block\';this.style.display=\'none\';">' . esc_html__('+ Add Staff Member', 'glamlux-core') . '</a>';
		echo '<hr class="wp-header-end">';

		// Show notices
		if (isset($_GET['gl_notice'])) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['gl_notice'])) . '</p></div>';
		}
		if (isset($_GET['gl_error'])) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($_GET['gl_error'])) . '</p></div>';
		}

		// ── Create / Edit Form ─────────────────────────────────────────────
		$form_display = $editing ? 'block' : 'none';
		$form_action = $editing ? 'glamlux_update_staff' : 'glamlux_create_staff';
		$form_title = $editing ? __('Edit Staff Member', 'glamlux-core') : __('Add New Staff Member', 'glamlux-core');

		echo '<div id="glamlux-staff-form" style="display:' . esc_attr($form_display) . '; background:#fff; padding:24px; border:1px solid #ccd0d4; border-radius:8px; margin:20px 0; max-width:600px;">';
		echo '<h2>' . esc_html($form_title) . '</h2>';

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('glamlux_staff_form', '_glamlux_nonce');
		echo '<input type="hidden" name="action" value="' . esc_attr($form_action) . '">';
		if ($editing) {
			echo '<input type="hidden" name="staff_id" value="' . esc_attr($editing_id) . '">';
		}

		echo '<table class="form-table">';

		if (!$editing) {
			// WP User select (only non-staff users)
			echo '<tr><th><label for="wp_user_id">' . esc_html__('WordPress User', 'glamlux-core') . '</label></th><td>';
			echo '<select name="wp_user_id" id="wp_user_id" required>';
			echo '<option value="">' . esc_html__('— Select User —', 'glamlux-core') . '</option>';
			$users = get_users(['role__not_in' => ['administrator']]);
			foreach ($users as $u) {
				printf('<option value="%d">%s (%s)</option>', $u->ID, esc_html($u->display_name), esc_html($u->user_email));
			}
			echo '</select><p class="description">' . esc_html__('Select an existing WordPress user to assign as staff.', 'glamlux-core') . '</p></td></tr>';
		}

		// Salon select
		global $wpdb;
		$salons = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}gl_salons WHERE is_active = 1 ORDER BY name");
		echo '<tr><th><label for="salon_id">' . esc_html__('Salon', 'glamlux-core') . '</label></th><td>';
		echo '<select name="salon_id" id="salon_id" required>';
		echo '<option value="">' . esc_html__('— Select Salon —', 'glamlux-core') . '</option>';
		foreach ($salons as $s) {
			$selected = ($editing && (int)$editing['salon_id'] === (int)$s->id) ? ' selected' : '';
			printf('<option value="%d"%s>%s</option>', $s->id, $selected, esc_html($s->name));
		}
		echo '</select></td></tr>';

		// Job Role
		$role_val = $editing ? esc_attr($editing['job_role'] ?? '') : '';
		echo '<tr><th><label for="job_role">' . esc_html__('Job Role', 'glamlux-core') . '</label></th><td>';
		echo '<input type="text" name="job_role" id="job_role" class="regular-text" value="' . $role_val . '" placeholder="e.g. Senior Stylist" required>';
		echo '</td></tr>';

		// Specializations
		$spec_val = $editing ? esc_attr($editing['specializations'] ?? '') : '';
		echo '<tr><th><label for="specializations">' . esc_html__('Specializations', 'glamlux-core') . '</label></th><td>';
		echo '<input type="text" name="specializations" id="specializations" class="regular-text" value="' . $spec_val . '" placeholder="e.g. Bridal, Colour, Keratin">';
		echo '</td></tr>';

		// Commission Rate
		$comm_val = $editing ? esc_attr($editing['commission_rate'] ?? '0') : '15';
		echo '<tr><th><label for="commission_rate">' . esc_html__('Commission Rate (%)', 'glamlux-core') . '</label></th><td>';
		echo '<input type="number" name="commission_rate" id="commission_rate" min="0" max="100" step="0.5" value="' . $comm_val . '" style="width:100px;">';
		echo '</td></tr>';

		echo '</table>';

		echo '<p class="submit">';
		submit_button($editing ? __('Update Staff', 'glamlux-core') : __('Add Staff Member', 'glamlux-core'), 'primary', 'submit', false);
		if ($editing) {
			echo ' <a href="' . esc_url(remove_query_arg('edit')) . '" class="button">' . esc_html__('Cancel', 'glamlux-core') . '</a>';
		}
		echo '</p>';
		echo '</form>';
		echo '</div>';

		// ── Staff Table ────────────────────────────────────────────────────
		echo '<table class="wp-list-table widefat fixed striped" style="margin-top:20px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Staff Name', 'glamlux-core') . '</th>';
		echo '<th>' . esc_html__('Email', 'glamlux-core') . '</th>';
		echo '<th>' . esc_html__('Salon', 'glamlux-core') . '</th>';
		echo '<th>' . esc_html__('Role', 'glamlux-core') . '</th>';
		echo '<th>' . esc_html__('Commission (%)', 'glamlux-core') . '</th>';
		echo '<th>' . esc_html__('Specializations', 'glamlux-core') . '</th>';
		echo '<th>' . esc_html__('Status', 'glamlux-core') . '</th>';
		echo '<th>' . esc_html__('Actions', 'glamlux-core') . '</th>';
		echo '</tr></thead><tbody>';

		if (empty($staff_members)) {
			echo '<tr><td colspan="8">' . esc_html__('No staff members found. Click "+ Add Staff Member" to create one.', 'glamlux-core') . '</td></tr>';
		}
		else {
			foreach ($staff_members as $s) {
				$edit_url = add_query_arg('edit', $s['id']);
				$deactivate_url = wp_nonce_url(
					admin_url('admin-post.php?action=glamlux_deactivate_staff&staff_id=' . $s['id']),
					'glamlux_deactivate_staff_' . $s['id']
				);
				$status_label = !empty($s['is_active']) ? '<span style="color:#2e7d32;font-weight:600;">Active</span>' : '<span style="color:#c62828;font-weight:600;">Inactive</span>';

				echo '<tr>';
				printf('<td><strong>%s</strong></td>', esc_html($s['name'] ?? 'Unknown'));
				printf('<td>%s</td>', esc_html($s['email'] ?? '—'));
				printf('<td>%s</td>', esc_html($s['salon_name'] ?? '—'));
				printf('<td>%s</td>', esc_html($s['job_role'] ?? ''));
				printf('<td>%s%%</td>', esc_html($s['commission_rate'] ?? '0'));
				printf('<td>%s</td>', esc_html($s['specializations'] ?? '—'));
				printf('<td>%s</td>', $status_label);
				echo '<td>';
				echo '<a href="' . esc_url($edit_url) . '" class="button button-small">' . esc_html__('Edit', 'glamlux-core') . '</a> ';
				if (!empty($s['is_active'])) {
					echo '<a href="' . esc_url($deactivate_url) . '" class="button button-small" style="color:#c62828;" onclick="return confirm(\'' . esc_js(__('Deactivate this staff member?', 'glamlux-core')) . '\')">' . esc_html__('Deactivate', 'glamlux-core') . '</a>';
				}
				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Form Handlers
	// ─────────────────────────────────────────────────────────────────────────

	public function handle_create()
	{
		if (!check_admin_referer('glamlux_staff_form', '_glamlux_nonce')) {
			wp_die(esc_html__('Security check failed.', 'glamlux-core'));
		}
		if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) {
			wp_die(esc_html__('Access denied.', 'glamlux-core'));
		}

		$service = new GlamLux_Service_Staff();
		$result = $service->create([
			'wp_user_id' => absint($_POST['wp_user_id'] ?? 0),
			'salon_id' => absint($_POST['salon_id'] ?? 0),
			'job_role' => sanitize_text_field($_POST['job_role'] ?? ''),
			'specializations' => sanitize_text_field($_POST['specializations'] ?? ''),
			'commission_rate' => floatval($_POST['commission_rate'] ?? 0),
		]);

		if (is_wp_error($result)) {
			wp_redirect(add_query_arg('gl_error', urlencode($result->get_error_message()), $this->get_return_url()));
		}
		else {
			wp_redirect(add_query_arg('gl_notice', urlencode(__('Staff member created successfully.', 'glamlux-core')), $this->get_return_url()));
		}
		exit;
	}

	public function handle_update()
	{
		if (!check_admin_referer('glamlux_staff_form', '_glamlux_nonce')) {
			wp_die(esc_html__('Security check failed.', 'glamlux-core'));
		}
		if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) {
			wp_die(esc_html__('Access denied.', 'glamlux-core'));
		}

		$staff_id = absint($_POST['staff_id'] ?? 0);
		$service = new GlamLux_Service_Staff();
		$result = $service->update($staff_id, [
			'salon_id' => absint($_POST['salon_id'] ?? 0),
			'job_role' => sanitize_text_field($_POST['job_role'] ?? ''),
			'specializations' => sanitize_text_field($_POST['specializations'] ?? ''),
			'commission_rate' => floatval($_POST['commission_rate'] ?? 0),
		]);

		if (is_wp_error($result)) {
			wp_redirect(add_query_arg('gl_error', urlencode($result->get_error_message()), $this->get_return_url()));
		}
		else {
			wp_redirect(add_query_arg('gl_notice', urlencode(__('Staff member updated successfully.', 'glamlux-core')), $this->get_return_url()));
		}
		exit;
	}

	public function handle_deactivate()
	{
		$staff_id = absint($_GET['staff_id'] ?? 0);
		if (!check_admin_referer('glamlux_deactivate_staff_' . $staff_id)) {
			wp_die(esc_html__('Security check failed.', 'glamlux-core'));
		}
		if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) {
			wp_die(esc_html__('Access denied.', 'glamlux-core'));
		}

		$service = new GlamLux_Service_Staff();
		$result = $service->deactivate($staff_id);

		if (is_wp_error($result)) {
			wp_redirect(add_query_arg('gl_error', urlencode($result->get_error_message()), $this->get_return_url()));
		}
		else {
			wp_redirect(add_query_arg('gl_notice', urlencode(__('Staff member deactivated.', 'glamlux-core')), $this->get_return_url()));
		}
		exit;
	}

	private function get_return_url()
	{
		// Detect which admin page we are on (super-admin vs franchise-admin)
		$page = isset($_GET['page']) ? $_GET['page'] : (isset($_POST['_wp_http_referer']) ? '' : 'glamlux-staff');
		foreach (['glamlux-my-staff', 'glamlux-staff'] as $slug) {
			if (strpos(wp_get_referer() ?: '', $slug) !== false) {
				return admin_url('admin.php?page=' . $slug);
			}
		}
		return admin_url('admin.php?page=glamlux-staff');
	}
}
