<?php
/**
 * Admin logic for GlamLux Core.
 * Handles menus, scripts, and basic hook mappings.
 */
class GlamLux_Admin
{

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct()
	{
		add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
		add_action('admin_notices', array($this, 'show_health_notices'));

		// Map standard users to our custom roles
		add_action('user_register', array($this, 'map_default_role'));
	}

	/**
	 * Register the administration menu and submenus.
	 */
	public function add_plugin_admin_menu()
	{

		// ─── Super Admin Top Menu ───────────────────────────────────────────────
		add_menu_page(
			'GlamLux Platform',
			'GlamLux2Lux',
			'manage_glamlux_platform',
			'glamlux-dashboard',
			array($this, 'display_dashboard'),
			'dashicons-admin-site-alt3',
			3
		);
		add_submenu_page('glamlux-dashboard', 'Manage Franchises', 'Franchises', 'manage_glamlux_platform', 'glamlux-franchises', array($this, 'display_franchises'));
		add_submenu_page('glamlux-dashboard', 'Global Reporting', 'Reporting', 'manage_glamlux_platform', 'glamlux-reporting', array($this, 'display_reporting'));
		add_submenu_page('glamlux-dashboard', 'Services & Pricing', 'Services', 'manage_glamlux_platform', 'glamlux-services', array($this, 'display_services'));
		add_submenu_page('glamlux-dashboard', 'Salons', 'All Salons', 'manage_glamlux_platform', 'glamlux-salons', array($this, 'display_salons'));
		add_submenu_page('glamlux-dashboard', 'Staff', 'All Staff', 'manage_glamlux_platform', 'glamlux-staff', array($this, 'display_staff'));
		add_submenu_page('glamlux-dashboard', 'Memberships', 'Memberships', 'manage_glamlux_platform', 'glamlux-memberships', array($this, 'display_memberships'));
		add_submenu_page('glamlux-dashboard', 'Payroll (Global)', 'Payroll', 'manage_glamlux_platform', 'glamlux-payroll', array($this, 'display_payroll'));

		// ─── Franchise Admin Top Menu ───────────────────────────────────────────
		add_menu_page(
			'My Salon',
			'My Salon',
			'manage_glamlux_franchise',
			'glamlux-salon',
			array($this, 'display_salon_dashboard'),
			'dashicons-store',
			4
		);
		add_submenu_page('glamlux-salon', 'Manage Staff', 'Staff', 'manage_glamlux_franchise', 'glamlux-my-staff', array($this, 'display_staff'));
		add_submenu_page('glamlux-salon', 'Manage Salons', 'Salons', 'manage_glamlux_franchise', 'glamlux-my-salons', array($this, 'display_salons'));
		add_submenu_page('glamlux-salon', 'Payroll', 'Payroll', 'manage_glamlux_franchise', 'glamlux-my-payroll', array($this, 'display_payroll'));
		add_submenu_page('glamlux-salon', 'Memberships', 'Memberships', 'manage_glamlux_franchise', 'glamlux-my-memberships', array($this, 'display_memberships'));
		add_submenu_page('glamlux-salon', 'Appointments', 'Appointments', 'manage_glamlux_franchise', 'glamlux-my-appointments', array($this, 'display_appointments'));

		// ─── Staff Menu ─────────────────────────────────────────────────────────
		add_menu_page(
			'Appointments',
			'Appointments',
			'manage_glamlux_appointments',
			'glamlux-appointments',
			array($this, 'display_appointments'),
			'dashicons-calendar-alt',
			5
		);
	}

	// ==============================================
	// View View Placeholders
	// ==============================================
	public function display_dashboard()
	{
		global $glamlux_reporting, $glamlux_operations_service;
		$kpi = is_object($glamlux_reporting) ? $glamlux_reporting->get_kpi_summary() : array();
		$operations = is_object($glamlux_operations_service) ? $glamlux_operations_service->get_operations_summary() : array();
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('GlamLux2Lux — Super Admin Dashboard', 'glamlux-core') . '</h1>';
		echo '<hr class="wp-header-end">';
		if (!empty($kpi)) {
			echo '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:20px 0;">';
			foreach (array(
			['Total Revenue', '$' . number_format($kpi['total_revenue'], 2), '#C6A75E'],
			['Total Appointments', number_format($kpi['total_appointments']), '#4A90D9'],
			['Active Franchises', number_format($kpi['total_franchises']), '#7ED321'],
			['Active Salons', number_format($kpi['total_salons']), '#9B59B6'],
			) as [$label, $value, $color]) {
				printf('<div style="background:#fff;border-left:4px solid %s;padding:16px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-radius:4px;"><div style="font-size:24px;font-weight:700;color:%s;">%s</div><div style="color:#555;font-size:13px;margin-top:4px;">%s</div></div>',
					esc_attr($color), esc_attr($color), esc_html($value), esc_html($label));
			}
			echo '</div>';
		}

		if (!empty($operations) && !empty($operations['operations'])) {
			$health = isset($operations['health']) ? $operations['health'] : 'unknown';
			$health_color = 'healthy' === $health ? '#2E7D32' : '#F57C00';
			echo '<h2 style="margin-top:28px;">' . esc_html__('Enterprise Operations Health', 'glamlux-core') . '</h2>';
			printf('<p><strong>%s:</strong> <span style="color:%s;">%s</span></p>', esc_html__('Platform Status', 'glamlux-core'), esc_attr($health_color), esc_html(ucfirst($health)));
			echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:16px 0 24px;">';
			foreach (array(
			['Appointments Today', number_format((int)$operations['operations']['appointments_today']), '#1565C0'],
			['Pending Queue', number_format((int)$operations['operations']['pending_appointments']), '#6A1B9A'],
			['Active Staff', number_format((int)$operations['operations']['active_staff']), '#00897B'],
			['Active Memberships', number_format((int)$operations['operations']['active_memberships']), '#5D4037'],
			['Open Leads', number_format((int)$operations['operations']['open_leads']), '#546E7A'],
			['Service Errors (24h)', number_format((int)$operations['operations']['service_errors_24h']), '#C62828'],
			) as [$label, $value, $color]) {
				printf('<div style="background:#fff;border-left:4px solid %s;padding:14px 18px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-radius:4px;"><div style="font-size:22px;font-weight:700;color:%s;">%s</div><div style="color:#555;font-size:12px;margin-top:4px;">%s</div></div>', esc_attr($color), esc_attr($color), esc_html($value), esc_html($label));
			}
			echo '</div>';

			if (!empty($operations['database']['missing_tables'])) {
				echo '<div class="notice notice-warning inline"><p>' . esc_html__('Missing core tables detected:', 'glamlux-core') . ' <code>' . esc_html(implode(', ', $operations['database']['missing_tables'])) . '</code></p></div>';
			}
		}
		echo '<p><a href="' . esc_url(admin_url('admin.php?page=glamlux-reporting')) . '" class="button button-primary">' . esc_html__('View Full Reports →', 'glamlux-core') . '</a></p>';
		echo '<p><code>GET /wp-json/glamlux/v1/operations/summary</code></p>';
		echo '</div>';
	}

	public function display_franchises()
	{
		global $glamlux_franchises_instance;
		$franchises = new GlamLux_Franchises();
		$franchises->render_admin_page();
	}

	public function display_reporting()
	{
		global $glamlux_reporting;
		if (is_object($glamlux_reporting)) {
			$glamlux_reporting->render_admin_page();
		}
		else {
			$r = new GlamLux_Reporting();
			$r->render_admin_page();
		}
	}

	public function display_services()
	{
		$svc = new GlamLux_Services_Admin();
		$svc->render_admin_page();
	}

	public function display_salon_dashboard()
	{
		global $wpdb;
		$user_id = get_current_user_id();
		// Try to find the franchise this admin belongs to
		$franchise = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gl_franchises WHERE admin_id=%d LIMIT 1", $user_id
		));
		echo '<div class="wrap"><h1>' . esc_html__('My Salon Dashboard', 'glamlux-core') . '</h1>';
		if ($franchise) {
			$salon_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}gl_salons WHERE franchise_id=%d", $franchise->id));
			$staff_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gl_staff WHERE salon_id IN (SELECT id FROM {$wpdb->prefix}gl_salons WHERE franchise_id={$franchise->id})");
			echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:20px 0;max-width:700px;">';
			foreach ([
			['Franchise', $franchise->name, '#C6A75E'],
			['Salons', $salon_count . ' active', '#4A90D9'],
			['Staff', $staff_count . ' members', '#7ED321'],
			] as [$lbl, $val, $col]) {
				printf('<div style="background:#fff;border-left:4px solid %s;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.08)"><div style="font-size:20px;font-weight:700;color:%s">%s</div><div style="color:#555;font-size:12px">%s</div></div>',
					esc_attr($col), esc_attr($col), esc_html($val), esc_html($lbl));
			}
			echo '</div>';
			echo '<p>';
			echo '<a href="' . esc_url(admin_url('admin.php?page=glamlux-my-staff')) . '" class="button">Manage Staff</a> ';
			echo '<a href="' . esc_url(admin_url('admin.php?page=glamlux-my-salons')) . '" class="button">Manage Salons</a> ';
			echo '<a href="' . esc_url(admin_url('admin.php?page=glamlux-my-memberships')) . '" class="button">Memberships</a> ';
			echo '<a href="' . esc_url(admin_url('post-new.php')) . '" class="button button-primary">✏️ Write a Post</a> ';
			echo '<a href="' . esc_url(admin_url('post-new.php?post_type=page')) . '" class="button button-primary">📄 Create a Page</a>';
			echo '</p>';
		}
		else {
			echo '<div class="notice notice-warning"><p>No franchise associated with your account. Please contact the platform admin to assign you to a franchise.</p></div>';
		}
		echo '</div>';
	}

	public function display_staff()
	{
		$mod = new GlamLux_Staff();
		$mod->render_admin_page();
	}

	public function display_salons()
	{
		$mod = new GlamLux_Salons();
		$mod->render_admin_page();
	}

	public function display_memberships()
	{
		$mod = new GlamLux_Memberships();
		$mod->render_admin_page();
	}

	public function display_payroll()
	{
		$payroll = new GlamLux_Payroll();
		$payroll->render_admin_page();
	}

	public function display_appointments()
	{
		$appts = new GlamLux_Appointments();
		$appts->render_admin_dashboard();
	}

	/**
	 * Admin notices for platform health.
	 */
	public function show_health_notices()
	{
		// Only show on GlamLux pages
		$screen = get_current_screen();
		if (!$screen || strpos($screen->id, 'glamlux') === false)
			return;

		// Show success notices from redirects
		if (isset($_GET['gl_notice'])) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['gl_notice'])) . '</p></div>';
		}

		if (class_exists('GlamLux_SchemaHealth') && current_user_can('manage_options')) {
			$schema_health = GlamLux_SchemaHealth::get_health_report();
			if (!empty($schema_health) && isset($schema_health['status']) && 'healthy' !== $schema_health['status']) {
				$missing_tables = !empty($schema_health['mismatches']['missing_tables']) ? implode(', ', $schema_health['mismatches']['missing_tables']) : 'None';
				$missing_columns = !empty($schema_health['mismatches']['missing_columns']) ? implode(', ', array_map(function ($entry) {
					return $entry['table'] . '.' . $entry['column'];
				}, $schema_health['mismatches']['missing_columns'])) : 'None';
				$missing_indexes = !empty($schema_health['mismatches']['missing_indexes']) ? implode(', ', array_map(function ($entry) {
					return $entry['table'] . '.' . $entry['index'];
				}, $schema_health['mismatches']['missing_indexes'])) : 'None';

				echo '<div class="notice notice-error"><p><strong>GlamLux Schema Health:</strong> Dashboard-critical schema mismatches were detected.</p>';
				echo '<p><strong>Missing tables:</strong> <code>' . esc_html($missing_tables) . '</code><br/>';
				echo '<strong>Missing columns:</strong> <code>' . esc_html($missing_columns) . '</code><br/>';
				echo '<strong>Missing indexes:</strong> <code>' . esc_html($missing_indexes) . '</code></p>';
				echo '<p><strong>Remediation:</strong></p><ol>';
				foreach ((array)$schema_health['remediation'] as $step) {
					echo '<li>' . esc_html($step) . '</li>';
				}
				echo '</ol></div>';
			}
		}

		// Razorpay config check
		if (!get_option('glamlux_razorpay_key_id') && current_user_can('manage_options')) {
			echo '<div class="notice notice-warning"><p><strong>GlamLux:</strong> Razorpay Key ID is not configured. <a href="' . esc_url(admin_url('admin.php?page=glamlux-settings')) . '">Configure Payment Settings →</a></p></div>';
		}
	}

	/**
	 * Automatically map standard WordPress registrations to glamlux_client.
	 */
	public function map_default_role($user_id)
	{
		$user = get_userdata($user_id);

		// If they explicitly registered just now via WooCommerce or default form...
		if ($user && in_array('subscriber', (array)$user->roles)) {
			$user->remove_role('subscriber');
			$user->add_role('glamlux_client');
		}
		// Also remap customer role assigned by WooCommerce if needed
		if ($user && in_array('customer', (array)$user->roles)) {
			$user->remove_role('customer');
			$user->add_role('glamlux_client');
		}
	}
}
