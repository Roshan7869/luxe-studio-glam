<?php
/**
 * Staff Admin UI Module
 * RULE: Pure service calls only. No SQL.
 */
class GlamLux_Staff
{
	public function render_admin_page()
	{
		if (!current_user_can('manage_glamlux_franchise') && !current_user_can('manage_options')) {
			wp_die('Unauthorized');
		}

		$service = new GlamLux_Service_Staff();
		$staff_members = method_exists($service, 'get_all') ? $service->get_all([]) : [];

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">Manage Staff</h1>';
		echo '<a href="#" class="page-title-action">Invite Staff Member</a>';
		echo '<hr class="wp-header-end">';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>Staff Name</th><th>Role</th><th>Commission Rate (%)</th><th>Specializations</th><th>Status</th></tr></thead><tbody>';

		if (empty($staff_members)) {
			echo '<tr><td colspan="5">No staff members found.</td></tr>';
		}
		else {
			foreach ($staff_members as $s) {
				printf('<tr><td><strong>%s</strong></td><td>%s</td><td>%s%%</td><td>%s</td><td>%s</td></tr>',
					esc_html($s['name'] ?? 'Unknown'),
					esc_html($s['job_role'] ?? ''),
					esc_html($s['commission_rate'] ?? '0'),
					esc_html($s['specializations'] ?? ''),
					!empty($s['is_active']) ? '<span class="notice-success">Active</span>' : '<span class="notice-warning">Inactive</span>'
				);
			}
		}

		echo '</tbody></table>';
		echo '</div>';
	}
}
