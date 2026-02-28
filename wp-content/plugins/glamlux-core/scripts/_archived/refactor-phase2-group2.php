<?php
$repo_dir = __DIR__ . '/../Repositories/';
$service_dir = __DIR__ . '/../Services/';

if (!is_dir($repo_dir))
    mkdir($repo_dir, 0755, true);
if (!is_dir($service_dir))
    mkdir($service_dir, 0755, true);

// -----------------------------------------------------------------------------
// 1. LEADS
// -----------------------------------------------------------------------------
file_put_contents($repo_dir . 'class-glamlux-repo-lead.php', <<<PHP
<?php
class GlamLux_Repo_Lead {
	public function get_recent_duplicate(\$email, \$phone) {
		global \$wpdb;
		return \$wpdb->get_var(\$wpdb->prepare("SELECT id FROM {\$wpdb->prefix}gl_leads WHERE email=%s AND phone=%s AND created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR) LIMIT 1", \$email, \$phone));
	}
	public function insert_lead(\$data) {
		global \$wpdb;
		\$wpdb->insert(\$wpdb->prefix . "gl_leads", \$data, ["%s","%s","%s","%s","%s","%s","%s","%d","%s","%s"]);
		return \$wpdb->insert_id;
	}
	public function insert_followup(\$data) {
		global \$wpdb;
		\$wpdb->insert(\$wpdb->prefix . "gl_followups", \$data, ["%d","%s","%s","%s","%s","%s"]);
		return \$wpdb->insert_id;
	}
	public function update_lead_status(\$id, \$status) {
		global \$wpdb;
		return false !== \$wpdb->update(\$wpdb->prefix . "gl_leads", ["status"=>\$status, "updated_at"=>current_time("mysql")], ["id"=>\$id]);
	}
	public function get_funnel_summary() {
		global \$wpdb;
		return \$wpdb->get_results("SELECT status, COUNT(*) as count FROM {\$wpdb->prefix}gl_leads GROUP BY status", ARRAY_A);
	}
}
PHP);

file_put_contents($service_dir . 'class-glamlux-service-lead.php', <<<PHP
<?php
class GlamLux_Service_Lead {
	private \$dispatcher;
	private \$repo;
	private \$territory_service;
	public function __construct(GlamLux_Event_Dispatcher \$dispatcher = null, GlamLux_Repo_Lead \$repo = null) {
		\$this->dispatcher = \$dispatcher;
		\$this->repo = \$repo ?: new GlamLux_Repo_Lead();
	}
	public function capture_lead(array \$data): int|WP_Error {
		foreach (['name', 'email', 'phone'] as \$f) if (empty(\$data[\$f])) return new WP_Error('glamlux_lead_missing', "Missing field: {\$f}");
		if (\$this->repo->get_recent_duplicate(sanitize_email(\$data['email']), sanitize_text_field(\$data['phone']))) return new WP_Error('glamlux_lead_duplicate', 'Lead exists.');
		
		\$state = sanitize_text_field(\$data['state'] ?? '');
		if (!class_exists('GlamLux_Service_Territory')) require_once __DIR__ . '/class-glamlux-service-territory.php';
		\$assigned_to = (new GlamLux_Service_Territory())->auto_assign_by_territory(\$state);
		
		\$lead_id = \$this->repo->insert_lead([
			'name' => sanitize_text_field(\$data['name']), 'email' => sanitize_email(\$data['email']),
			'phone' => sanitize_text_field(\$data['phone']), 'state' => \$state,
			'interest_type' => sanitize_text_field(\$data['interest_type'] ?? 'franchise'),
			'message' => sanitize_textarea_field(\$data['message'] ?? ''), 'status' => 'new',
			'assigned_to' => \$assigned_to, 'source' => sanitize_text_field(\$data['source'] ?? 'website'),
			'created_at' => current_time('mysql')
		]);
		if (!\$lead_id) return new WP_Error('glamlux_lead_db', 'Failed to save lead.');
		
		\$this->schedule_followup(\$lead_id, 'initial_contact', '+24 hours');
		if (\$this->dispatcher) \$this->dispatcher->dispatch('lead_captured', ['lead_id'=>\$lead_id, 'state'=>\$state, 'assigned_to'=>\$assigned_to]);
		return \$lead_id;
	}
	public function schedule_followup(int \$id, string \$type, string \$due = '+1 day'): bool {
		return (bool)\$this->repo->insert_followup(['lead_id'=>\$id, 'type'=>sanitize_text_field(\$type), 'due_at'=>date('Y-m-d H:i:s', strtotime(\$due)), 'status'=>'pending', 'created_at'=>current_time('mysql')]);
	}
	public function update_status(int \$id, string \$status, string \$notes = ''): bool {
		if (!in_array(\$status, ['new','contacted','qualified','proposal_sent','converted','lost'])) return false;
		if (!\$this->repo->update_lead_status(\$id, \$status)) return false;
		if (\$notes) \$this->repo->insert_followup(['lead_id'=>\$id,'type'=>'status_change_'.\$status,'notes'=>sanitize_textarea_field(\$notes),'status'=>'completed','due_at'=>current_time('mysql'),'created_at'=>current_time('mysql')]);
		if (\$status === 'converted' && \$this->dispatcher) \$this->dispatcher->dispatch('lead_converted', ['lead_id'=>\$id]);
		return true;
	}
	public function get_funnel_summary(): array {
		\$rows = \$this->repo->get_funnel_summary();
		\$f = ['new'=>0,'contacted'=>0,'qualified'=>0,'proposal_sent'=>0,'converted'=>0,'lost'=>0];
		foreach (\$rows as \$r) \$f[\$r['status']] = (int)\$r['count'];
		return \$f;
	}
}
PHP);

// -----------------------------------------------------------------------------
// 2. TERRITORY
// -----------------------------------------------------------------------------
file_put_contents($repo_dir . 'class-glamlux-repo-territory.php', <<<PHP
<?php
class GlamLux_Repo_Territory {
	public function count_franchises_in_state(\$state, \$exclude_id = null) {
		global \$wpdb;
		\$q = "SELECT COUNT(*) FROM {\$wpdb->prefix}gl_franchises WHERE territory_state=%s";
		\$a = [\$state];
		if (\$exclude_id) { \$q .= " AND id!=%d"; \$a[] = \$exclude_id; }
		return (int)\$wpdb->get_var(\$wpdb->prepare(\$q, ...\$a));
	}
	public function get_franchise_by_state(\$state) {
		global \$wpdb;
		return \$wpdb->get_row(\$wpdb->prepare("SELECT * FROM {\$wpdb->prefix}gl_franchises WHERE territory_state=%s LIMIT 1", \$state)) ?: null;
	}
	public function get_territory_map() {
		global \$wpdb;
		return \$wpdb->get_results("SELECT f.territory_state AS state, f.name AS franchise_name, u.display_name AS admin_name, COUNT(s.id) AS salon_count FROM {\$wpdb->prefix}gl_franchises f LEFT JOIN {\$wpdb->users} u ON f.admin_id=u.ID LEFT JOIN {\$wpdb->prefix}gl_salons s ON s.franchise_id=f.id GROUP BY f.id ORDER BY f.territory_state ASC", ARRAY_A);
	}
	public function get_revenue_by_territory(\$from, \$to) {
		global \$wpdb;
		return \$wpdb->get_results(\$wpdb->prepare("SELECT f.territory_state AS state, SUM(a.amount) AS total_revenue, COUNT(a.id) AS appointment_count FROM {\$wpdb->prefix}gl_appointments a INNER JOIN {\$wpdb->prefix}gl_salons s ON a.salon_id=s.id INNER JOIN {\$wpdb->prefix}gl_franchises f ON s.franchise_id=f.id WHERE a.appointment_time BETWEEN %s AND %s AND a.payment_status='paid' GROUP BY f.territory_state ORDER BY total_revenue DESC", \$from . ' 00:00:00', \$to . ' 23:59:59'), ARRAY_A);
	}
	public function get_admin_id_by_territory(\$state) {
		global \$wpdb;
		return \$wpdb->get_var(\$wpdb->prepare("SELECT admin_id FROM {\$wpdb->prefix}gl_franchises WHERE territory_state=%s AND admin_id>0 LIMIT 1", \$state));
	}
}
PHP);

file_put_contents($service_dir . 'class-glamlux-service-territory.php', <<<PHP
<?php
class GlamLux_Service_Territory {
	private \$repo;
	public function __construct(GlamLux_Repo_Territory \$repo = null) { \$this->repo = \$repo ?: new GlamLux_Repo_Territory(); }
	public function has_territory_conflict(string \$state, ?int \$exclude = null): bool { return \$this->repo->count_franchises_in_state(\$state, \$exclude) > 0; }
	public function get_franchise_by_state(string \$state): ?object { return \$this->repo->get_franchise_by_state(\$state); }
	public function get_territory_map(): array { return \$this->repo->get_territory_map(); }
	public function get_revenue_by_territory(string \$f, string \$t): array { return \$this->repo->get_revenue_by_territory(\$f, \$t); }
	public function auto_assign_by_territory(string \$state): ?int { \$id = \$this->repo->get_admin_id_by_territory(\$state); return \$id ? (int)\$id : null; }
}
PHP);

// -----------------------------------------------------------------------------
// 3. MEMBERSHIP
// -----------------------------------------------------------------------------
file_put_contents($repo_dir . 'class-glamlux-repo-membership.php', <<<PHP
<?php
class GlamLux_Repo_Membership {
	public function get_tier(\$id) { global \$wpdb; return \$wpdb->get_row(\$wpdb->prepare("SELECT * FROM {\$wpdb->prefix}gl_memberships WHERE id=%d", \$id)) ?: null; }
	public function get_all_tiers() { global \$wpdb; return \$wpdb->get_results("SELECT * FROM {\$wpdb->prefix}gl_memberships ORDER BY price ASC", ARRAY_A) ?: []; }
	public function get_tier_by_wc_product(\$pid) { global \$wpdb; return \$wpdb->get_row(\$wpdb->prepare("SELECT * FROM {\$wpdb->prefix}gl_memberships WHERE wc_product_id=%d LIMIT 1", \$pid)); }
	public function get_client(\$id) { global \$wpdb; return \$wpdb->get_row(\$wpdb->prepare("SELECT * FROM {\$wpdb->prefix}gl_clients WHERE id=%d", \$id)); }
	public function get_client_by_user_id(\$uid) { global \$wpdb; return \$wpdb->get_row(\$wpdb->prepare("SELECT * FROM {\$wpdb->prefix}gl_clients WHERE wp_user_id=%d LIMIT 1", \$uid)); }
	public function update_client_membership(\$id, \$mid, \$expiry) { global \$wpdb; return false !== \$wpdb->update(\$wpdb->prefix."gl_clients",["membership_id"=>\$mid,"membership_expiry"=>\$expiry],["id"=>\$id]); }
	public function insert_purchase(\$data) { global \$wpdb; return \$wpdb->insert(\$wpdb->prefix."gl_membership_purchases", \$data, ["%d","%d","%s","%d","%s","%s","%s"]); }
	public function get_expired_clients() { global \$wpdb; return \$wpdb->get_results("SELECT c.id AS cid,u.user_email,u.display_name FROM {\$wpdb->prefix}gl_clients c LEFT JOIN {\$wpdb->users} u ON c.wp_user_id=u.ID WHERE c.membership_id IS NOT NULL AND c.membership_expiry IS NOT NULL AND c.membership_expiry<NOW()"); }
	public function get_expiring_clients(\$target_date) { global \$wpdb; return \$wpdb->get_results(\$wpdb->prepare("SELECT c.id,u.user_email,u.display_name,m.name AS tname FROM {\$wpdb->prefix}gl_clients c LEFT JOIN {\$wpdb->users} u ON c.wp_user_id=u.ID LEFT JOIN {\$wpdb->prefix}gl_memberships m ON c.membership_id=m.id WHERE DATE(c.membership_expiry)=%s", \$target_date)); }
	public function insert_tier(\$d) { global \$wpdb; \$r = \$wpdb->insert(\$wpdb->prefix."gl_memberships",\$d); return \$r ? \$wpdb->insert_id : false; }
	public function update_tier(\$id, \$d) { global \$wpdb; return false !== \$wpdb->update(\$wpdb->prefix."gl_memberships",\$d,["id"=>\$id]); }
	public function has_members(\$tier_id) { global \$wpdb; return (int)\$wpdb->get_var(\$wpdb->prepare("SELECT COUNT(*) FROM {\$wpdb->prefix}gl_clients WHERE membership_id=%d", \$tier_id)) > 0; }
	public function delete_tier(\$id) { global \$wpdb; return false !== \$wpdb->delete(\$wpdb->prefix."gl_memberships",["id"=>\$id]); }
	public function get_revenue(\$f,\$t) { global \$wpdb; return \$wpdb->get_results(\$wpdb->prepare("SELECT m.id AS membership_id,m.name AS tier_name,m.price,COUNT(p.id) AS sales_count,SUM(m.price) AS total_revenue FROM {\$wpdb->prefix}gl_membership_purchases p INNER JOIN {\$wpdb->prefix}gl_memberships m ON p.membership_id=m.id WHERE DATE(p.granted_at) BETWEEN %s AND %s GROUP BY m.id ORDER BY total_revenue DESC",\$f,\$t),ARRAY_A)?:[]; }
	public function get_active_count() { global \$wpdb; return (int)\$wpdb->get_var("SELECT COUNT(*) FROM {\$wpdb->prefix}gl_clients WHERE membership_id IS NOT NULL AND membership_expiry>NOW()"); }
}
PHP);

file_put_contents($service_dir . 'class-glamlux-service-membership.php', <<<PHP
<?php
class GlamLux_Service_Membership {
	private \$repo;
	public function __construct(GlamLux_Repo_Membership \$repo = null) { \$this->repo = \$repo ?: new GlamLux_Repo_Membership(); }
	public function grant(\$cid, \$mid, \$src="manual", \$oid=0) {
		\$tier = \$this->repo->get_tier(\$mid); if (!\$tier) return false;
		\$expiry = date("Y-m-d H:i:s", strtotime("+".((int)\$tier->duration_months)." months"));
		\$this->repo->update_client_membership(\$cid, \$mid, \$expiry);
		\$this->repo->insert_purchase(["client_id"=>\$cid,"membership_id"=>\$mid,"source"=>\$src,"wc_order_id"=>\$oid,"granted_at"=>current_time("mysql"),"expires_at"=>\$expiry,"status"=>"active"]);
		do_action("glamlux_membership_granted", \$cid, \$mid, \$expiry); return true;
	}
	public function revoke(\$cid) { return \$this->repo->update_client_membership(\$cid, null, null); }
	public function renew(\$cid, \$mid, \$src="manual", \$oid=0) {
		\$tier = \$this->repo->get_tier(\$mid); if (!\$tier) return false;
		\$cl = \$this->repo->get_client(\$cid);
		\$base = (\$cl && \$cl->membership_expiry && strtotime(\$cl->membership_expiry) > time()) ? strtotime(\$cl->membership_expiry) : time();
		\$expiry = date("Y-m-d H:i:s", strtotime("+".((int)\$tier->duration_months)." months", \$base));
		\$this->repo->update_client_membership(\$cid, \$mid, \$expiry);
		\$this->repo->insert_purchase(["client_id"=>\$cid,"membership_id"=>\$mid,"source"=>\$src,"wc_order_id"=>\$oid,"granted_at"=>current_time("mysql"),"expires_at"=>\$expiry,"status"=>"renewed"]);
		return true;
	}
	public function process_expired() {
		\$expired = \$this->repo->get_expired_clients();
		foreach (\$expired as \$c) {
			\$this->repo->update_client_membership(\$c->cid, null, null);
			if (\$c->user_email) wp_mail(\$c->user_email, "GlamLux Membership Expired", "Dear ".\$c->display_name.", your membership expired.");
		}
		return count(\$expired);
	}
	public function send_renewal_reminders(\$days=5) {
		\$target = date("Y-m-d", strtotime("+\$days days"));
		\$rows = \$this->repo->get_expiring_clients(\$target);
		\$cnt = 0;
		foreach (\$rows as \$r) {
			if (\$r->user_email) { wp_mail(\$r->user_email,"GlamLux ".\$r->tname." expires in \$days day(s)", "Renew!"); \$cnt++; }
		}
		return \$cnt;
	}
	public function get_tier(\$id) { return \$this->repo->get_tier(\$id); }
	public function get_all_tiers() { return \$this->repo->get_all_tiers(); }
	public function create_tier(\$d) {
		return \$this->repo->insert_tier(["name"=>sanitize_text_field(\$d["name"]),"description"=>sanitize_textarea_field(\$d["description"]??""),"price"=>(float)(\$d["price"]??0),"duration_months"=>(int)(\$d["duration_months"]??12),"discount_percent"=>(float)(\$d["discount_percent"]??0),"wc_product_id"=>(int)(\$d["wc_product_id"]??0),"is_active"=>1]);
	}
	public function update_tier(\$id,\$d) {
		return \$this->repo->update_tier(\$id, ["name"=>sanitize_text_field(\$d["name"]),"description"=>sanitize_textarea_field(\$d["description"]??""),"price"=>(float)(\$d["price"]??0),"duration_months"=>(int)(\$d["duration_months"]??12),"discount_percent"=>(float)(\$d["discount_percent"]??0),"wc_product_id"=>(int)(\$d["wc_product_id"]??0),"is_active"=>(int)(\$d["is_active"]??1)]);
	}
	public function delete_tier(\$id) { if (\$this->repo->has_members(\$id)) return false; return \$this->repo->delete_tier(\$id); }
	public function get_revenue_by_tier(\$f,\$t) { return \$this->repo->get_revenue(\$f,\$t); }
	public function get_active_member_count() { return \$this->repo->get_active_count(); }
	public function register_wc_hooks() { if (!class_exists("WooCommerce")) return; add_action("woocommerce_order_status_completed", [\$this,"on_wc_order_completed"]); }
	public function on_wc_order_completed(\$oid) {
		\$order=wc_get_order(\$oid); if(!\$order) return;
		foreach (\$order->get_items() as \$item) {
			\$tier = \$this->repo->get_tier_by_wc_product(\$item->get_product_id()); if (!\$tier) continue;
			\$cl = \$this->repo->get_client_by_user_id(\$order->get_customer_id()); if (\$cl) \$this->grant(\$cl->id, \$tier->id, "woocommerce", \$oid);
		}
	}
}
PHP);

// -----------------------------------------------------------------------------
// 4. BOOKING
// -----------------------------------------------------------------------------
file_put_contents($repo_dir . 'class-glamlux-repo-appointment.php', <<<PHP
<?php
class GlamLux_Repo_Appointment {
	public function get_appointment_by_id(\$id) { global \$wpdb; return \$wpdb->get_row(\$wpdb->prepare("SELECT * FROM {\$wpdb->prefix}gl_appointments WHERE id=%d LIMIT 1", \$id), ARRAY_A); }
	public function check_availability(\$staff_id, \$time) {
		global \$wpdb;
		\$exists = \$wpdb->get_var(\$wpdb->prepare("SELECT id FROM {\$wpdb->prefix}gl_appointments WHERE staff_id=%d AND appointment_time=%s AND status NOT IN ('cancelled','refunded')", \$staff_id, \$time));
		return !\$exists;
	}
	public function create_appointment(\$data) { global \$wpdb; \$wpdb->insert(\$wpdb->prefix."gl_appointments", \$data); return \$wpdb->insert_id; }
	public function update_status(\$id, \$status) { global \$wpdb; return false !== \$wpdb->update(\$wpdb->prefix."gl_appointments", ["status"=>\$status,"updated_at"=>current_time('mysql')], ["id"=>\$id]); }
	public function transaction_start() { global \$wpdb; \$wpdb->query("START TRANSACTION"); }
	public function transaction_commit() { global \$wpdb; \$wpdb->query("COMMIT"); }
	public function transaction_rollback() { global \$wpdb; \$wpdb->query("ROLLBACK"); }
}
PHP);

file_put_contents($service_dir . 'class-glamlux-service-booking.php', <<<PHP
<?php
class GlamLux_Service_Booking {
	private \$repo;
	public function __construct(GlamLux_Repo_Appointment \$repo = null) { \$this->repo = \$repo ?: new GlamLux_Repo_Appointment(); }
	public function secure_book_appointment(\$staff_id, \$client_id, \$service_id, \$salon_id, \$appointment_time, \$notes = '') {
		try {
			\$this->repo->transaction_start();
			if (!\$this->repo->check_availability(\$staff_id, \$appointment_time)) throw new Exception('This time slot is no longer available.');
			\$id = \$this->repo->create_appointment(['staff_id'=>\$staff_id,'client_id'=>\$client_id,'service_id'=>\$service_id,'salon_id'=>\$salon_id,'appointment_time'=>\$appointment_time,'status'=>'pending','notes'=>sanitize_text_field(\$notes)]);
			if (!\$id) throw new Exception('Failed to create booking.');
			\$this->repo->transaction_commit();
			if (class_exists('GlamLux_Event_Dispatcher')) GlamLux_Event_Dispatcher::dispatch('appointment_created', ['appointment_id'=>\$id,'client_id'=>\$client_id,'salon_id'=>\$salon_id]);
			return \$id;
		} catch (Exception \$e) {
			\$this->repo->transaction_rollback();
			return new WP_Error('booking_failed', \$e->getMessage(), ['status'=>400]);
		}
	}
	public function mark_completed(\$id) {
		\$apt = \$this->repo->get_appointment_by_id(\$id);
		if (!\$apt || \$apt['status'] === 'completed') return false;
		\$this->repo->update_status(\$id, 'completed');
		if (class_exists('GlamLux_Event_Dispatcher')) GlamLux_Event_Dispatcher::dispatch('appointment_completed', ['appointment'=>\$apt]);
		return true;
	}
}
PHP);

echo "Group 2 generated.\\n";
