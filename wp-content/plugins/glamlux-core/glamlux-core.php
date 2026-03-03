<?php
/**
 * Plugin Name: GlamLux Core
 * Plugin URI:  https://glamlux2lux.com
 * Description: Enterprise Franchise Operating System — GlamLux2Lux. Domain-Driven Modular Monolith Architecture.
 * Version:     3.0.0
 * Author:      Antigravity
 * Text Domain: glamlux-core
 */

if (!defined('WPINC')) {
	die;
}

define('GLAMLUX_VERSION', '3.0.0');
define('GLAMLUX_DB_VERSION', '3.0.0');
define('GLAMLUX_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GLAMLUX_PLUGIN_URL', plugin_dir_url(__FILE__));

// ─────────────────────────────────────────────────────────────────────────────
// Global Helpers
// ─────────────────────────────────────────────────────────────────────────────

function glamlux_log_error($message, $context = array())
{
	if (class_exists('GlamLux_Logger')) {
		GlamLux_Logger::error($message, $context);
	}
	else {
		if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			error_log('[GlamLux] ' . $message . (!empty($context) ? ' | Context: ' . wp_json_encode($context) : ''));
		}
	}
	do_action('glamlux_error_logged', $message, $context);
}

function glamlux_ajax_response($success, $message, $data = array(), $code = 200)
{
	$payload = array_merge(array('message' => $message), $data);
	if ($success) {
		wp_send_json_success($payload, $code);
	}
	else {
		wp_send_json_error($payload, $code);
	}
}

add_action('init', 'glamlux_emergency_homepage_recovery');
function glamlux_emergency_homepage_recovery()
{
	if (get_option('show_on_front') !== 'page' || !get_post(get_option('page_on_front'))) {
		$home_id = wp_insert_post([
			'post_title' => 'Home',
			'post_name' => 'home',
			'post_status' => 'publish',
			'post_type' => 'page',
		]);
		update_option('show_on_front', 'page');
		update_option('page_on_front', $home_id);
	}
}

function glamlux_inject_js_config()
{
	$config = array(
		'apiRoot' => esc_url_raw(rest_url('glamlux/v1/')),
		'nonce' => wp_create_nonce('wp_rest'),
		'ajaxUrl' => admin_url('admin-ajax.php'),
		'ajaxNonce' => wp_create_nonce('glamlux_ajax_nonce'),
		'isLoggedIn' => is_user_logged_in(),
		'loginUrl' => wp_login_url(home_url('/')),
		'razorpayKey' => get_option('glamlux_razorpay_key_id', ''),
	);
	echo '<script>window.GlamLux = ' . wp_json_encode($config) . ";</script>\n";
}
add_action('wp_head', 'glamlux_inject_js_config');

// ─────────────────────────────────────────────────────────────────────────────
// Activation / Deactivation
// ─────────────────────────────────────────────────────────────────────────────

function activate_glamlux_core()
{
	require_once GLAMLUX_PLUGIN_DIR . 'Core/class-activator.php';
	GlamLux_Activator::activate();
}
function deactivate_glamlux_core()
{
	require_once GLAMLUX_PLUGIN_DIR . 'Core/class-deactivator.php';
	GlamLux_Deactivator::deactivate();
}
register_activation_hook(__FILE__, 'activate_glamlux_core');
register_deactivation_hook(__FILE__, 'deactivate_glamlux_core');

// ─────────────────────────────────────────────────────────────────────────────
// Auto DB Migration on Plugin Update
// ─────────────────────────────────────────────────────────────────────────────

function glamlux_maybe_upgrade()
{
	require_once GLAMLUX_PLUGIN_DIR . 'Core/class-activator.php';
	GlamLux_Activator::run_db_migrations();
}
add_action('plugins_loaded', 'glamlux_maybe_upgrade', 1);

// Role capabilities are now updated ONLY on plugin activation/upgrade to save performance.
// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap — Enterprise Module Loader (v3.0.0)
// ─────────────────────────────────────────────────────────────────────────────

function run_glamlux_core()
{

	// ── STEP 1: Infrastructure & Governance ──────────────────────────────────
	require_once GLAMLUX_PLUGIN_DIR . 'Core/class-glamlux-sentry.php';
	GlamLux_Sentry::init();

	require_once GLAMLUX_PLUGIN_DIR . 'Core/class-glamlux-logger.php';
	require_once GLAMLUX_PLUGIN_DIR . 'Core/class-glamlux-schema-health.php';
	GlamLux_SchemaHealth::init();
	GlamLux_SchemaHealth::validate_schema();

	require_once GLAMLUX_PLUGIN_DIR . 'repositories/class-glamlux-repo-system-mode.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/system-mode/class-glamlux-system-mode.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/system-mode/class-glamlux-demo-middleware.php';
	GlamLux_System_Mode::init();
	GlamLux_Demo_Middleware::init();

	require_once GLAMLUX_PLUGIN_DIR . 'includes/class-glamlux-content-manager.php'; // CPTs, Permissions, Customizer
	require_once GLAMLUX_PLUGIN_DIR . 'includes/class-glamlux-wc-hooks.php';
	require_once GLAMLUX_PLUGIN_DIR . 'includes/class-glamlux-cron.php';
	require_once GLAMLUX_PLUGIN_DIR . 'includes/class-glamlux-ajax.php';
	require_once GLAMLUX_PLUGIN_DIR . 'includes/class-glamlux-site-provisioner.php';
	if (defined('WP_CLI') && WP_CLI) {
		require_once GLAMLUX_PLUGIN_DIR . 'includes/class-glamlux-cli-health.php';
	}
	require_once GLAMLUX_PLUGIN_DIR . 'Rest/class-base-controller.php';
	require_once GLAMLUX_PLUGIN_DIR . 'Rest/class-salon-controller.php';
	require_once GLAMLUX_PLUGIN_DIR . 'Rest/class-service-controller.php';
	require_once GLAMLUX_PLUGIN_DIR . 'Rest/class-booking-controller.php';
	require_once GLAMLUX_PLUGIN_DIR . 'Rest/class-staff-controller.php';
	require_once GLAMLUX_PLUGIN_DIR . 'Rest/class-lead-controller.php';
	require_once GLAMLUX_PLUGIN_DIR . 'Rest/class-reports-controller.php';
	require_once GLAMLUX_PLUGIN_DIR . 'Rest/class-operations-controller.php';
	require_once GLAMLUX_PLUGIN_DIR . 'Rest/class-gdpr-controller.php';
	require_once GLAMLUX_PLUGIN_DIR . 'Rest/class-health-controller.php';
	require_once GLAMLUX_PLUGIN_DIR . 'Rest/class-rest-manager.php';

	// ── STEP 2: Event Bus (load FIRST — all services depend on it) ───────────
	require_once GLAMLUX_PLUGIN_DIR . 'Core/class-event-dispatcher.php';
	$event_dispatcher = new GlamLux_Event_Dispatcher();
	$event_dispatcher->register_core_listeners(); // Boot multi-listener config map

	// ── STEP 3: Repositories (LLD) ───────────────────────────────────────────
	require_once GLAMLUX_PLUGIN_DIR . 'repositories/class-glamlux-repo-franchise.php';
	require_once GLAMLUX_PLUGIN_DIR . 'repositories/class-glamlux-repo-staff.php';
	require_once GLAMLUX_PLUGIN_DIR . 'repositories/class-glamlux-repo-inventory.php';

	// Group 1 Repositories
	require_once GLAMLUX_PLUGIN_DIR . 'repositories/class-glamlux-repo-payroll.php';
	require_once GLAMLUX_PLUGIN_DIR . 'repositories/class-glamlux-repo-attendance.php';
	require_once GLAMLUX_PLUGIN_DIR . 'repositories/class-glamlux-repo-revenue.php';

	// Group 2 Repositories
	require_once GLAMLUX_PLUGIN_DIR . 'repositories/class-glamlux-repo-appointment.php';
	require_once GLAMLUX_PLUGIN_DIR . 'repositories/class-glamlux-repo-lead.php';
	require_once GLAMLUX_PLUGIN_DIR . 'repositories/class-glamlux-repo-territory.php';
	require_once GLAMLUX_PLUGIN_DIR . 'repositories/class-glamlux-repo-membership.php';
	require_once GLAMLUX_PLUGIN_DIR . 'repositories/class-glamlux-repo-gdpr.php';
	require_once GLAMLUX_PLUGIN_DIR . 'repositories/class-glamlux-repo-operations.php';
	require_once GLAMLUX_PLUGIN_DIR . 'repositories/class-glamlux-repo-webhook.php';

	// ── STEP 4: Payment Domain (Interface → Concrete Gateways → Handler) ─────
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-payment-interface.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-payment-razorpay.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-payment-stripe.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-webhook-handler.php';

	// ── STEP 5: Business Domain Services ─────────────────────────────────────
	// Group 1 Services
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-service-commission.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-service-revenue.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-service-payroll.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-service-attendance.php';

	// Group 2 Services
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-service-booking.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-service-staff.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-service-lead.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-service-territory.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-service-membership.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-service-inventory.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-service-gdpr.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-service-operations.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-event-listeners.php';
	require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-mailer.php';

	// ── STEP 6: Boot Event Listeners ─────────────────────────────────────────
	GlamLux_Service_Commission::init(); // Legacy static listener — backward compat

	// ── STEP 7: Admin Modules ─────────────────────────────────────────────────
	require_once GLAMLUX_PLUGIN_DIR . 'admin/class-glamlux-admin.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/system-mode/class-glamlux-system-mode-admin.php';
	GlamLux_System_Mode_Admin::init();

	require_once GLAMLUX_PLUGIN_DIR . 'admin/class-glamlux-settings.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-appointments.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-reporting.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-franchises.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-franchise-leads.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-services-admin.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-payroll.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-salons.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-staff.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-memberships.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-inventory-admin.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-attendance-admin.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-shifts-admin.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-platform-settings.php';
	require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-territory-admin.php';

	// ── STEP 8: Instantiate Services (global DI references) ──────────────────

	global $glamlux_reporting, $glamlux_payment_webhook,
	$glamlux_leads, $glamlux_territory, $glamlux_revenue,
	$glamlux_payroll, $glamlux_attendance, $glamlux_operations_service;

	// Payment: build gateway instances → inject into webhook handler
	$razorpay_gw = new GlamLux_Payment_Razorpay();
	$stripe_gw = new GlamLux_Payment_Stripe();

	$glamlux_payment_webhook = new GlamLux_Webhook_Handler($event_dispatcher);
	$glamlux_payment_webhook->register_gateway($razorpay_gw);
	$glamlux_payment_webhook->register_gateway($stripe_gw);

	// Business services
	$glamlux_reporting = new GlamLux_Reporting();
	$glamlux_leads = new GlamLux_Service_Lead($event_dispatcher);
	$glamlux_territory = new GlamLux_Service_Territory();
	$glamlux_revenue = new GlamLux_Service_Revenue();
	$glamlux_payroll = new GlamLux_Service_Payroll();
	$glamlux_attendance = new GlamLux_Service_Attendance();
	$glamlux_operations_service = new GlamLux_Service_Operations();

	$glamlux_mailer = new GlamLux_Service_Mailer();

	// Admin + Infrastructure
	new GlamLux_Content_Manager(); // CPTs, Permissions, Customizer, REST content routes
	new GlamLux_Admin();
	new GlamLux_Settings();
	new GlamLux_WC_Hooks();
	new GlamLux_Cron();
	new GlamLux_AJAX();
	new GlamLux_Site_Provisioner();
	new GlamLux_REST_Manager();
	new GlamLux_Appointments();
	new GlamLux_Franchises();
	new GlamLux_Services_Admin();
	new GlamLux_Staff(); // Sprint 2: register admin_post handlers at boot
	new GlamLux_Attendance_Admin(); // Sprint 2: register check-in/check-out handlers
	new GlamLux_Shifts_Admin(); // Sprint 2: register shift create/delete handlers
	new GlamLux_Inventory_Admin(); // Sprint 4: register inventory CRUD handlers
	new GlamLux_Memberships(); // Sprint 3: register membership CRUD handlers
	new GlamLux_Platform_Settings(); // Sprint 5: platform settings page
	new GlamLux_Territory_Admin(); // Sprint 6: territory management
}
add_action('plugins_loaded', 'run_glamlux_core', 20);

// ─────────────────────────────────────────────────────────────────────────────
// TEMP: Execute Enterprise Visual Dataset Seeder via Browser (admin-only, one-time)
// ─────────────────────────────────────────────────────────────────────────────
add_action('init', function () {
	if (!isset($_GET['seed_now']) || $_GET['seed_now'] !== '1') {
		return;
	}
	if (!is_user_logged_in() || !current_user_can('manage_options')) {
		wp_die('Unauthorized. You must be logged in as an administrator to seed data.', 'GlamLux Seed', ['response' => 403]);
	}
	// Idempotency: only seed once. Delete option to re-seed.
	if (get_option('glamlux_enterprise_seed_v1')) {
		wp_die('✅ Enterprise dataset was already seeded on ' . get_option('glamlux_enterprise_seed_v1') . '. To re-seed, delete the <code>glamlux_enterprise_seed_v1</code> option.');
	}
	require_once GLAMLUX_PLUGIN_DIR . 'scripts/_dev-only/seed-enterprise-visual-dataset.php';
	update_option('glamlux_enterprise_seed_v1', gmdate('Y-m-d H:i:s'));
	wp_die('✅ Enterprise visual dataset seeded successfully! All 69 records are now live in the database.');
});
