<?php
/**
 * GlamLux Salons Admin Module
 *
 * Provides the WordPress admin UI for managing salons within a franchise.
 * Franchise owners and super-admins can view, create, edit, and toggle active status.
 */
class GlamLux_Salons
{

	public function render_admin_page()
	{
		if (!current_user_can('manage_glamlux_platform') && !current_user_can('manage_glamlux_franchise')) {
			wp_die(esc_html__('Insufficient permissions.', 'glamlux-core'));
		}

		$action = sanitize_key($_GET['gl_action'] ?? 'list');
		$id = (int)($_GET['gl_id'] ?? 0);

		if ('POST' === $_SERVER['REQUEST_METHOD']) {
			$this->handle_post();
		}

		if ('toggle' === $action && $id && isset($_GET['_wpnonce'])) {
			$this->handle_toggle($id);
		}

		switch ($action) {
			case 'create':
			case 'edit':
				$this->render_form($id);
				break;
			default:
				$this->render_list();
		}
	}

	private function render_list()
	{
		global $wpdb;
		$salons = $wpdb->get_results(
			"SELECT s.*, f.name AS franchise_name FROM {$wpdb->prefix}gl_salons s
			 LEFT JOIN {$wpdb->prefix}gl_franchises f ON s.franchise_id = f.id
			 ORDER BY s.id DESC", ARRAY_A
		) ?: [];

		echo '<div class="wrap"><h1 class="wp-heading-inline">Salons</h1>';
		echo '<a href="' . esc_url(add_query_arg('gl_action', 'create')) . '" class="page-title-action">+ Add Salon</a>';
		echo '<hr class="wp-header-end">';

		if (isset($_GET['gl_notice'])) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['gl_notice'])) . '</p></div>';
		}

		if (empty($salons)) {
			echo '<div class="notice notice-info"><p>No salons found. Create your first salon above.</p></div></div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped" style="margin-top:16px">';
		echo '<thead><tr><th>ID</th><th>Salon Name</th><th>Franchise</th><th>Address</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
		foreach ($salons as $s) {
			$toggle_url = add_query_arg(['gl_action' => 'toggle', 'gl_id' => $s['id'], '_wpnonce' => wp_create_nonce('gl_toggle_salon_' . $s['id'])]);
			$status_text = $s['is_active'] ? '<span style="color:#0a0">● Active</span>' : '<span style="color:#a00">● Inactive</span>';
			printf(
				'<tr><td>%d</td><td><strong>%s</strong></td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s">Edit</a> | <a href="%s">%s</a></td></tr>',
				$s['id'], esc_html($s['name']), esc_html($s['franchise_name'] ?? '—'),
				esc_html($s['address']), $status_text,
				esc_url(add_query_arg(['gl_action' => 'edit', 'gl_id' => $s['id']])),
				esc_url($toggle_url),
				$s['is_active'] ? 'Deactivate' : 'Activate'
			);
		}
		echo '</tbody></table></div>';
	}

	private function render_form($id = 0)
	{
		global $wpdb;
		$salon = $id ? ($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gl_salons WHERE id=%d", $id), ARRAY_A) ?: []) : [];
		$franchises = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}gl_franchises ORDER BY name", ARRAY_A) ?: [];
		$v = fn($k, $d = '') => esc_attr($salon[$k] ?? $d);

		echo '<div class="wrap"><h1>' . esc_html($id ? 'Edit Salon' : 'Add Salon') . '</h1>';
		echo '<form method="post" style="max-width:520px;margin-top:20px">';
		wp_nonce_field('gl_save_salon', 'gl_salon_nonce');
		echo '<input type="hidden" name="gl_salon_id" value="' . esc_attr($id) . '">';

		echo '<p><label><strong>Salon Name *</strong><br><input type="text" name="name" value="' . $v('name') . '" class="regular-text" required></label></p>';
		echo '<p><label><strong>Address *</strong><br><input type="text" name="address" value="' . $v('address') . '" class="regular-text" required></label></p>';

		echo '<p><label><strong>Franchise *</strong><br><select name="franchise_id" class="regular-text" required>';
		echo '<option value="">— Select Franchise —</option>';
		foreach ($franchises as $f) {
			printf('<option value="%d" %s>%s</option>', $f['id'], selected($salon['franchise_id'] ?? 0, $f['id'], false), esc_html($f['name']));
		}
		echo '</select></label></p>';

		echo '<p><label><input type="checkbox" name="is_active" value="1" ' . checked($salon['is_active'] ?? 1, 1, false) . '> <strong>Active</strong></label></p>';
		echo '<p><input type="submit" name="gl_save_salon" value="' . esc_attr($id ? 'Update Salon' : 'Create Salon') . '" class="button button-primary"></p>';
		echo '</form></div>';
	}

	private function handle_post()
	{
		if (!isset($_POST['gl_salon_nonce']) || !wp_verify_nonce($_POST['gl_salon_nonce'], 'gl_save_salon'))
			return;
		if (!current_user_can('manage_glamlux_platform') && !current_user_can('manage_glamlux_franchise'))
			wp_die('Insufficient permissions.');

		global $wpdb;
		$data = [
			'name' => sanitize_text_field($_POST['name'] ?? ''),
			'address' => sanitize_text_field($_POST['address'] ?? ''),
			'franchise_id' => (int)($_POST['franchise_id'] ?? 0),
			'is_active' => isset($_POST['is_active']) ? 1 : 0,
		];
		$id = (int)($_POST['gl_salon_id'] ?? 0);
		if ($id) {
			$wpdb->update("{$wpdb->prefix}gl_salons", $data, ['id' => $id]);
			$msg = 'Salon updated.';
		}
		else {
			$wpdb->insert("{$wpdb->prefix}gl_salons", $data);
			$msg = 'Salon created.';
		}
		wp_redirect(add_query_arg('gl_notice', urlencode($msg), remove_query_arg(['gl_action', 'gl_id'])));
		exit;
	}

	private function handle_toggle($id)
	{
		if (!wp_verify_nonce($_GET['_wpnonce'], 'gl_toggle_salon_' . $id))
			wp_die('Nonce failed.');
		global $wpdb;
		$current = (int)$wpdb->get_var($wpdb->prepare("SELECT is_active FROM {$wpdb->prefix}gl_salons WHERE id=%d", $id));
		$wpdb->update("{$wpdb->prefix}gl_salons", ['is_active' => $current ? 0 : 1], ['id' => $id]);
		wp_redirect(add_query_arg('gl_notice', urlencode($current ? 'Salon deactivated.' : 'Salon activated.'), remove_query_arg(['gl_action', 'gl_id', '_wpnonce'])));
		exit;
	}
}
