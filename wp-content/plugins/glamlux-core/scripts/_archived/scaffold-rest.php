<?php

$dir = __DIR__ . '/../Rest/';
if (!is_dir($dir))
    mkdir($dir, 0755, true);

// 1. Base Controller with shared logic (Rate Limiting, permissions)
file_put_contents($dir . 'class-base-controller.php', <<<PHP
<?php
class GlamLux_Base_Controller {
	public function require_logged_in() {
		if (!is_user_logged_in()) return new WP_Error('glamlux_unauthorized', 'You must be logged in.', ['status'=>401]);
		return true;
	}
	public function require_client_role() {
		if (!is_user_logged_in()) return new WP_Error('glamlux_unauthorized', 'Authentication required.', ['status'=>401]);
		\$allowed = ['glamlux_client', 'glamlux_staff', 'glamlux_franchise_admin', 'glamlux_super_admin', 'administrator'];
		\$user = wp_get_current_user();
		foreach (\$allowed as \$role) { if (in_array(\$role, (array)\$user->roles, true)) return true; }
		return new WP_Error('glamlux_forbidden', 'Permission denied.', ['status'=>403]);
	}
	public function require_staff_or_admin() {
		if (!is_user_logged_in()) return new WP_Error('glamlux_unauthorized', 'Authentication required.', ['status'=>401]);
		if (current_user_can('glamlux_manage_appointments') || current_user_can('manage_options')) return true;
		return new WP_Error('glamlux_forbidden', 'Insufficient permissions.', ['status'=>403]);
	}
	public function require_franchise_admin() {
		return current_user_can('manage_options') || current_user_can('glamlux_state_manager') || current_user_can('glamlux_franchise_admin');
	}
	public function require_salon_manager() {
		return current_user_can('manage_options') || current_user_can('glamlux_state_manager') || current_user_can('glamlux_franchise_admin') || current_user_can('glamlux_salon_manager');
	}
}
PHP);

// 2. Salon Controller
file_put_contents($dir . 'class-salon-controller.php', <<<PHP
<?php
class GlamLux_Salon_Controller extends GlamLux_Base_Controller {
	public function register_routes() {
		register_rest_route('glamlux/v1', '/salons', [
			'methods' => 'GET',
			'callback' => [\$this, 'get_salons'],
			'permission_callback' => '__return_true',
		]);
	}
	public function get_salons(\$request) {
		\$cached = get_transient('glamlux_cached_salons');
		if (\$cached !== false) return rest_ensure_response(\$cached);
		global \$wpdb;
		\$salons = \$wpdb->get_results("SELECT id, name, address, phone FROM {\$wpdb->prefix}gl_salons WHERE is_active = 1 ORDER BY name ASC");
		if (empty(\$salons)) {
			\$salons = [
				['id'=>1,'name'=>'GlamLux Downtown','address'=>'123 Luxury Ave','phone'=>'+91 9000000001'],
				['id'=>2,'name'=>'GlamLux Beverly Hills','address'=>'90210 High St','phone'=>'+91 9000000002']
			];
		}
		set_transient('glamlux_cached_salons', \$salons, 300);
		return rest_ensure_response(\$salons);
	}
}
PHP);

// 3. Service Controller
file_put_contents($dir . 'class-service-controller.php', <<<PHP
<?php
class GlamLux_Service_Controller extends GlamLux_Base_Controller {
	public function register_routes() {
		register_rest_route('glamlux/v1', '/services', [
			'methods' => 'GET',
			'callback' => [\$this, 'get_services'],
			'permission_callback' => '__return_true',
		]);
	}
	public function get_services(\$request) {
		\$cached = get_transient('glamlux_cached_services');
		if (\$cached !== false) return rest_ensure_response(\$cached);
		global \$wpdb;
		\$services = \$wpdb->get_results("SELECT id, service_name AS name, price FROM {\$wpdb->prefix}gl_service_logs WHERE is_active = 1 ORDER BY service_name ASC");
		if (empty(\$services)) {
			\$services = [
				['id'=>'s1','name'=>'Luminous Facial','price'=>180,'category'=>'Skin'],
				['id'=>'s2','name'=>'Bridal Artistry','price'=>250,'category'=>'Makeup']
			];
		}
		set_transient('glamlux_cached_services', \$services, 300);
		return rest_ensure_response(\$services);
	}
}
PHP);

// 4. Booking Controller
file_put_contents($dir . 'class-booking-controller.php', <<<PHP
<?php
class GlamLux_Booking_Controller extends GlamLux_Base_Controller {
	public function register_routes() {
		register_rest_route('glamlux/v1', '/my-appointments', [
			'methods' => 'GET',
			'callback' => [\$this, 'get_my_appointments'],
			'permission_callback' => [\$this, 'require_logged_in'],
		]);
		register_rest_route('glamlux/v1', '/book', [
			'methods' => 'POST',
			'callback' => [\$this, 'create_booking'],
			'permission_callback' => [\$this, 'require_client_role'],
		]);
		register_rest_route('glamlux/v1', '/appointments/(?P<id>\d+)/cancel', [
			'methods' => 'POST',
			'callback' => [\$this, 'cancel_booking'],
			'permission_callback' => [\$this, 'require_staff_or_admin'],
		]);
	}
	public function get_my_appointments(\$request) {
		\$user_id = get_current_user_id();
		global \$wpdb;
		\$client = \$wpdb->get_row(\$wpdb->prepare("SELECT id FROM {\$wpdb->prefix}gl_clients WHERE wp_user_id = %d LIMIT 1", \$user_id));
		if (!\$client) return rest_ensure_response([]);
		\$appointments = \$wpdb->get_results(\$wpdb->prepare(
			"SELECT a.*, s.name AS service_name, sl.name AS salon_name FROM {\$wpdb->prefix}gl_appointments a LEFT JOIN {\$wpdb->prefix}gl_service_logs s ON a.service_id = s.id LEFT JOIN {\$wpdb->prefix}gl_salons sl ON a.salon_id = sl.id WHERE a.client_id = %d ORDER BY a.appointment_time DESC LIMIT 50",
			\$client->id
		));
		return rest_ensure_response(\$appointments);
	}
	public function create_booking(\$request) {
		// Business logic extraction handled in subsequent phase
		\$user_id = get_current_user_id();
		\$salon_id = \$request->get_param('salon_id');
		\$service_id = \$request->get_param('service_id');
		\$appointment_time = \$request->get_param('appointment_time');
		\$notes = \$request->get_param('notes') ?? '';
		if (!strtotime(\$appointment_time)) return new WP_Error('glamlux_invalid_date', 'Invalid date.', ['status'=>400]);
		global \$wpdb;
		\$client_id = \$wpdb->get_var(\$wpdb->prepare("SELECT id FROM {\$wpdb->prefix}gl_clients WHERE wp_user_id = %d LIMIT 1", \$user_id));
		if (!\$client_id) { \$wpdb->insert("{\$wpdb->prefix}gl_clients", ['wp_user_id'=>\$user_id,'created_at'=>current_time('mysql')]); \$client_id = \$wpdb->insert_id; }
		\$staff_id = \$wpdb->get_var(\$wpdb->prepare("SELECT id FROM {\$wpdb->prefix}gl_staff WHERE salon_id = %d LIMIT 1", \$salon_id));
		if (!\$staff_id) \$staff_id = 1;
		\$repo = new GlamLux_Repo_Appointment();
		\$service = new GlamLux_Service_Booking(\$repo);
		\$result = \$service->secure_book_appointment(\$staff_id, \$client_id, \$service_id, \$salon_id, \$appointment_time, \$notes);
		if (is_wp_error(\$result)) return \$result;
		return rest_ensure_response(['success'=>true, 'appointment_id'=>\$result, 'message'=>'Appointment confirmed!']);
	}
	public function cancel_booking(\$request) {
		global \$wpdb; \$appointment_id = absint(\$request->get_param('id'));
		\$updated = \$wpdb->update("{\$wpdb->prefix}gl_appointments", ['status'=>'cancelled','updated_at'=>current_time('mysql')], ['id'=>\$appointment_id]);
		if (false === \$updated) return new WP_Error('fail', 'Could not cancel.', ['status'=>500]);
		do_action('glamlux_appointment_cancelled', \$appointment_id);
		return rest_ensure_response(['success'=>true, 'message'=>'Cancelled.']);
	}
}
PHP);

// 5. GDPR Controller
file_put_contents($dir . 'class-gdpr-controller.php', <<<PHP
<?php
class GlamLux_GDPR_Controller extends GlamLux_Base_Controller {
	public function register_routes() {
		register_rest_route('glamlux/v1', '/user/export', [
			'methods' => 'GET',
			'callback' => [\$this, 'gdpr_export_data'],
			'permission_callback' => [\$this, 'require_logged_in'],
		]);
		register_rest_route('glamlux/v1', '/user/delete', [
			'methods' => 'DELETE',
			'callback' => [\$this, 'gdpr_delete_account'],
			'permission_callback' => [\$this, 'require_logged_in'],
		]);
	}
	public function gdpr_export_data(\$request) {
		global \$wpdb; \$user_id = get_current_user_id(); \$user = get_userdata(\$user_id);
		if (!\$user) return new WP_Error('not_found', 'User not found.', ['status'=>404]);
		\$profile = ['id'=>\$user->ID,'username'=>\$user->user_login,'email'=>\$user->user_email];
		\$client = \$wpdb->get_row(\$wpdb->prepare("SELECT id, phone FROM {\$wpdb->prefix}gl_clients WHERE wp_user_id = %d LIMIT 1", \$user_id), ARRAY_A);
		\$appointments = \$wpdb->get_results(\$wpdb->prepare("SELECT a.id, a.status FROM {\$wpdb->prefix}gl_appointments a INNER JOIN {\$wpdb->prefix}gl_clients c ON a.client_id = c.id WHERE c.wp_user_id = %d", \$user_id), ARRAY_A);
		return rest_ensure_response(['generated_at'=>gmdate('Y-m-d\TH:i:s\Z'),'profile'=>\$profile,'client'=>\$client,'appointments'=>\$appointments]);
	}
	public function gdpr_delete_account(\$request) {
		global \$wpdb; \$user_id = get_current_user_id();
		if (current_user_can('manage_options')) return new WP_Error('forbidden', 'Super admin cannot delete via API.', ['status'=>403]);
		\$client = \$wpdb->get_row(\$wpdb->prepare("SELECT id FROM {\$wpdb->prefix}gl_clients WHERE wp_user_id = %d LIMIT 1", \$user_id));
		if (\$client) { \$wpdb->update("{\$wpdb->prefix}gl_appointments", ['client_id'=>null], ['client_id'=>\$client->id]); \$wpdb->delete("{\$wpdb->prefix}gl_clients", ['id'=>\$client->id]); }
		require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user(\$user_id, 1);
		do_action('glamlux_account_deleted', \$user_id);
		return rest_ensure_response(['success'=>true, 'message'=>'Account deleted.']);
	}
}
PHP);

// 6. Rest Manager (Orchestrator, rate limiting buffer)
file_put_contents($dir . 'class-rest-manager.php', <<<PHP
<?php
class GlamLux_REST_Manager {
	public function __construct() {
		add_filter('rest_pre_dispatch', [\$this, 'check_rate_limit'], 10, 3);
		add_action('rest_api_init', [\$this, 'init_controllers']);
	}
	public function init_controllers() {
		(new GlamLux_Salon_Controller())->register_routes();
		(new GlamLux_Service_Controller())->register_routes();
		(new GlamLux_Booking_Controller())->register_routes();
		(new GlamLux_GDPR_Controller())->register_routes();
	}
	public function check_rate_limit(\$result, \$server, \$request) {
		\$route = \$request->get_route();
		if (strpos(\$route, '/glamlux/v1') !== 0) return \$result;
		if (current_user_can('manage_options')) return \$result;
		\$ip = \$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
		\$transient_key = 'glamlux_rl_' . md5(\$ip);
		\$data = get_transient(\$transient_key);
		if (false === \$data) { set_transient(\$transient_key, ['count'=>1, 'start'=>time()], 10); return \$result; }
		if (\$data['count'] >= 5) return new WP_Error('rate_limit', 'Too many requests.', ['status'=>429]);
		\$data['count']++;
		set_transient(\$transient_key, \$data, max(1, 10 - (time() - \$data['start'])));
		return \$result;
	}
}
PHP);

echo "REST Architecture Generated in REST/ directory!\\n";
