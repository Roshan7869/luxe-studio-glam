<?php
/**
 * Fired during plugin activation.
 */
class GlamLux_Activator
{

	/**
	 * Run all activation hooks.
	 */
	public static function activate()
	{
		self::create_custom_tables();
		self::add_custom_roles();
		self::update_role_capabilities(); // Ensure caps are always current
		self::run_db_migrations(); // INFRA FIX (P4): ensure governance tables exist

		// Flush rewrite rules for custom routing
		flush_rewrite_rules();
	}

	/**
	 * INFRA FIX (P4): Auto-run DB migrations on activation/upgrade.
	 * Creates wp_gl_mode_audit and wp_gl_webhook_events if they don't exist.
	 * Safe to call multiple times — uses dbDelta for idempotency.
	 */
	public static function run_db_migrations(): void
	{
		// PHASE 0.1: Versioned migrations only
		if (get_option('glamlux_db_version') === GLAMLUX_DB_VERSION) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// PHASE 0.2: Duplicate Detection Before UNIQUE Constraint
		if (self::table_exists($GLOBALS['wpdb']->prefix . 'gl_appointments')) {
			if (self::detect_appointment_duplicates()) {
				// Abort migration if duplicates exist to prevent site breakage
				return;
			}
		}

		// PHASE 1.1: Add gl_salon_hours Table
		if (!self::table_exists($GLOBALS['wpdb']->prefix . 'gl_salon_hours')) {
			$charset_collate = $GLOBALS['wpdb']->get_charset_collate();
			dbDelta("CREATE TABLE {$GLOBALS['wpdb']->prefix}gl_salon_hours (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				salon_id bigint(20) NOT NULL,
				day_of_week tinyint(1) NOT NULL,
				open_time time NOT NULL,
				close_time time NOT NULL,
				PRIMARY KEY  (id),
				KEY salon_id (salon_id)
			) $charset_collate;");
		}

		// PHASE 1.2: Add UNIQUE constraint (safe since duplicate check passed)
		if (self::table_exists($GLOBALS['wpdb']->prefix . 'gl_appointments')) {
			$index_exists = $GLOBALS['wpdb']->get_var("SHOW INDEX FROM {$GLOBALS['wpdb']->prefix}gl_appointments WHERE Key_name = 'staff_time'");
			if (!$index_exists) {
				$GLOBALS['wpdb']->query("ALTER TABLE {$GLOBALS['wpdb']->prefix}gl_appointments ADD UNIQUE INDEX staff_time (staff_id, appointment_time)");
			}
		}

		// PHASE 0 SECURITY: Add JWT token management tables
		self::create_token_management_tables();

		$version = (int)get_option('glamlux_migration_version', 0);
		$target_version = 6;

		if ($version >= $target_version) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		// Step 1: EMS tables (historical migrate-v3-ems.php)
		if ($version < 1) {
			dbDelta("CREATE TABLE {$wpdb->prefix}gl_attendance (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				staff_id bigint(20) NOT NULL,
				salon_id bigint(20) NOT NULL,
				shift_date date NOT NULL,
				check_in datetime DEFAULT NULL,
				check_out datetime DEFAULT NULL,
				hours_worked decimal(5,2) DEFAULT 0.00,
				is_late tinyint(1) DEFAULT 0,
				late_minutes int(11) DEFAULT 0,
				status varchar(50) DEFAULT 'present' NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY staff_id (staff_id),
				KEY shift_date (shift_date),
				UNIQUE KEY staff_shift (staff_id, shift_date)
			) $charset_collate;");

			dbDelta("CREATE TABLE {$wpdb->prefix}gl_shifts (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				staff_id bigint(20) NOT NULL,
				salon_id bigint(20) NOT NULL,
				shift_date date NOT NULL,
				start_time time NOT NULL,
				end_time time NOT NULL,
				status varchar(50) DEFAULT 'scheduled' NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY staff_id (staff_id),
				KEY shift_date (shift_date),
				UNIQUE KEY staff_shift (staff_id, shift_date)
			) $charset_collate;");

			$version = 3;
			update_option('glamlux_migration_version', $version);
		}

		// Step 4: Governance tables (historical migrate-v5-system-mode.php)
		if ($version < 4) {
			$table_audit = $wpdb->prefix . 'gl_mode_audit';
			dbDelta("CREATE TABLE $table_audit (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				previous_mode varchar(50) NOT NULL,
				new_mode varchar(50) NOT NULL,
				ip_address varchar(45) NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY (id),
				KEY user_id (user_id)
			) $charset_collate;");

			$table_webhooks = $wpdb->prefix . 'gl_webhook_events';
			dbDelta("CREATE TABLE $table_webhooks (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				gateway varchar(50) NOT NULL,
				transaction_id varchar(100) NOT NULL,
				event_type varchar(50) NOT NULL,
				payload longtext NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'processing',
				processed_at datetime DEFAULT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY gateway_transaction (gateway, transaction_id),
				KEY status (status)
			) $charset_collate;");

			$version = 4;
			update_option('glamlux_migration_version', $version);
		}

		// Step 5: Canonical migration checkpoint after consolidation in run_db_migrations.
		if ($version < 5) {
			$version = 5;
			update_option('glamlux_migration_version', $version);
		}

		// Step 6: Current target migration version.
		if ($version < $target_version) {
			update_option('glamlux_migration_version', $target_version);
		}

		// Conclude PHASE 0.1
		update_option('glamlux_db_version', GLAMLUX_DB_VERSION);
	}

	/**
	 * Create token management tables for JWT authentication
	 */
	private static function create_token_management_tables(): void
	{
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Token Blacklist: Revoked tokens
		if (!self::table_exists($wpdb->prefix . 'gl_token_blacklist')) {
			dbDelta("CREATE TABLE {$wpdb->prefix}gl_token_blacklist (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				token_hash varchar(64) NOT NULL,
				revoked_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				reason varchar(100) DEFAULT 'logout',
				PRIMARY KEY (id),
				UNIQUE KEY token_hash (token_hash),
				KEY user_id (user_id),
				KEY revoked_at (revoked_at)
			) $charset_collate;");
		}

		// Refresh Tokens: Long-lived tokens for token rotation
		if (!self::table_exists($wpdb->prefix . 'gl_refresh_tokens')) {
			dbDelta("CREATE TABLE {$wpdb->prefix}gl_refresh_tokens (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				token_hash varchar(64) NOT NULL,
				expires_at datetime NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				last_used_at datetime DEFAULT NULL,
				ip_address varchar(45) DEFAULT NULL,
				user_agent text DEFAULT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY token_hash (token_hash),
				KEY user_id (user_id),
				KEY expires_at (expires_at)
			) $charset_collate;");
		}

		// Token Sessions: Track active sessions for audit
		if (!self::table_exists($wpdb->prefix . 'gl_token_sessions')) {
			dbDelta("CREATE TABLE {$wpdb->prefix}gl_token_sessions (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				session_id varchar(64) NOT NULL,
				device_name varchar(255) DEFAULT NULL,
				ip_address varchar(45) DEFAULT NULL,
				user_agent text DEFAULT NULL,
				last_activity datetime DEFAULT CURRENT_TIMESTAMP,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY session_id (session_id),
				KEY user_id (user_id),
				KEY last_activity (last_activity)
			) $charset_collate;");
		}

		// Phase 1: Event Queue for async processing
		if (!self::table_exists($wpdb->prefix . 'gl_event_queue')) {
			dbDelta("CREATE TABLE {$wpdb->prefix}gl_event_queue (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_id varchar(36) NOT NULL,
				event_name varchar(255) NOT NULL,
				event_data longtext NOT NULL,
				priority tinyint DEFAULT 10,
				user_id bigint(20) unsigned,
				status varchar(50) DEFAULT 'pending',
				error_message text,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				processed_at datetime,
				PRIMARY KEY (id),
				KEY status_priority (status, priority),
				KEY created_at (created_at),
				KEY event_id (event_id)
			) $charset_collate;");
		}
	}

	/**
	 * Phase 0.2: Detect duplicates before UNIQUE constraint
	 */
	private static function detect_appointment_duplicates(): bool
	{
		global $wpdb;
		$duplicates = $wpdb->get_results("
			SELECT staff_id, appointment_time, COUNT(*) as count
			FROM {$wpdb->prefix}gl_appointments
			GROUP BY staff_id, appointment_time
			HAVING COUNT(*) > 1
		");

		if (!empty($duplicates)) {
			glamlux_log_error('Migration Aborted: Found duplicate appointments for staff slots. Resolve manually before upgrading DB.', ['duplicates' => $duplicates]);
			return true;
		}
		return false;
	}

	private static function table_exists(string $table_name): bool
	{
		global $wpdb;
		$like = $wpdb->esc_like($table_name);
		$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));

		return $found === $table_name;
	}

	private static function column_exists(string $table_name, string $column_name): bool
	{
		global $wpdb;
		$found = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", $column_name));

		return !empty($found);
	}

	private static function index_exists(string $table_name, string $index_name): bool
	{
		global $wpdb;
		$found = $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM {$table_name} WHERE Key_name = %s", $index_name));

		return !empty($found);
	}

	private static function ensure_column_exists(string $table, string $column, string $definition): void
	{
		global $wpdb;
		$exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
		if ($exists) {
			return;
		}
		$wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
	}

	private static function ensure_payroll_staff_schema($wpdb): void
	{
		$staff_table = $wpdb->prefix . 'gl_staff';
		$payroll_table = $wpdb->prefix . 'gl_payroll';

		self::ensure_column_exists($staff_table, 'base_salary', "decimal(10,2) DEFAULT '0.00' NOT NULL");
		self::ensure_column_exists($payroll_table, 'appointment_id', 'bigint(20) DEFAULT NULL');
		self::ensure_column_exists($payroll_table, 'total_pay', "decimal(10,2) DEFAULT '0.00' NOT NULL");
		self::ensure_column_exists($payroll_table, 'paid_at', 'datetime DEFAULT NULL');
	}

	private static function ensure_shift_schema($wpdb): void
	{
		$shifts_table = $wpdb->prefix . 'gl_shifts';
		self::ensure_column_exists($shifts_table, 'start_time', 'time DEFAULT NULL');
		self::ensure_column_exists($shifts_table, 'end_time', 'time DEFAULT NULL');
		self::ensure_column_exists($shifts_table, 'status', "varchar(50) DEFAULT 'scheduled' NOT NULL");
	}

	/**
	 * Create an index only when the table exists and index is missing.
	 */
	private static function ensure_index_with_guard(string $table_name, string $index_name, string $columns): void
	{
		global $wpdb;

		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
		if ($table_exists !== $table_name) {
			return;
		}

		$index_exists = $wpdb->get_var(
			$wpdb->prepare("SHOW INDEX FROM {$table_name} WHERE Key_name = %s", $index_name)
		);

		if (!$index_exists) {
			$wpdb->query("ALTER TABLE {$table_name} ADD INDEX {$index_name} ({$columns})");
		}
	}

	/**
	 * Update role capabilities — safe to call on every plugins_loaded.
	 * Keeps capabilities in sync without requiring plugin re-activation.
	 */
	public static function update_role_capabilities()
	{
		// ── Standard Administrator: Grant all GlamLux platform capabilities ──
		$admin = get_role('administrator');
		if ($admin) {
			$admin->add_cap('manage_glamlux_platform');
			$admin->add_cap('manage_glamlux_franchise');
			$admin->add_cap('manage_glamlux_appointments');
			$admin->add_cap('view_state_reports');
		}

		// ── Super Admin: Full WordPress editorial + platform management ──────
		$super = get_role('glamlux_super_admin');
		if ($super) {
			$super_caps = [
				// WordPress core editorial
				'read' => true,
				'edit_posts' => true,
				'publish_posts' => true,
				'edit_others_posts' => true,
				'delete_posts' => true,
				'delete_others_posts' => true,
				'edit_pages' => true,
				'publish_pages' => true,
				'edit_others_pages' => true,
				'delete_pages' => true,
				'manage_categories' => true,
				'upload_files' => true,
				'manage_options' => true,
				'moderate_comments' => true,
				'manage_links' => true,
				'unfiltered_html' => true,
				'install_plugins' => false,
				'update_plugins' => false,
				// GlamLux custom
				'manage_glamlux_platform' => true,
			];
			foreach ($super_caps as $cap => $grant) {
				if ($grant)
					$super->add_cap($cap);
				else
					$super->remove_cap($cap);
			}
		}

		// ── Franchise Admin: Own-salon editorial + content creation ─────────
		$franchise = get_role('glamlux_franchise_admin');
		if ($franchise) {
			$franchise_caps = [
				// WordPress core editorial (manage own salon blog/pages)
				'read' => true,
				'edit_posts' => true,
				'publish_posts' => true,
				'edit_others_posts' => false,
				'delete_posts' => true,
				'edit_pages' => true,
				'publish_pages' => true,
				'delete_pages' => true,
				'manage_categories' => true,
				'upload_files' => true,
				'moderate_comments' => true,
				// GlamLux custom
				'manage_glamlux_franchise' => true,
			];
			foreach ($franchise_caps as $cap => $grant) {
				if ($grant)
					$franchise->add_cap($cap);
				else
					$franchise->remove_cap($cap);
			}
		}

		// ── Staff: Read-only + appointment management ────────────────────────
		$staff = get_role('glamlux_staff');
		if ($staff) {
			$staff->add_cap('read');
			$staff->add_cap('upload_files'); // allow uploading before/after images
			$staff->add_cap('edit_posts'); // allow basic post drafts
			$staff->add_cap('manage_glamlux_appointments');
			$staff->add_cap('glamlux_check_attendance'); // Phase 1.1: allow clock-in/out
		}
	}

	/**
	 * Create all 10 required custom database tables for the platform.
	 */
	private static function create_custom_tables()
	{
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		// 1. Franchises
		$sql_franchises = "CREATE TABLE {$wpdb->prefix}gl_franchises (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(150) NOT NULL,
			location varchar(255) NOT NULL,
			admin_id bigint(20) NOT NULL,
			territory_state varchar(100) DEFAULT NULL,
			central_price_override decimal(10,2) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY territory_state (territory_state)
		) $charset_collate;";

		// 2. Salons
		$sql_salons = "CREATE TABLE {$wpdb->prefix}gl_salons (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			franchise_id bigint(20) NOT NULL,
			name varchar(150) NOT NULL,
			address varchar(255) NOT NULL,
			is_active tinyint(1) DEFAULT 1 NOT NULL,
			interior_image_url varchar(255) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY franchise_id (franchise_id)
		) $charset_collate;";

		// 3. Staff
		$sql_staff = "CREATE TABLE {$wpdb->prefix}gl_staff (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			wp_user_id bigint(20) NOT NULL,
			salon_id bigint(20) NOT NULL,
			commission_rate decimal(5,2) DEFAULT '0.00' NOT NULL,
			specializations text,
			job_role varchar(120) DEFAULT NULL,
			profile_image_url varchar(255) DEFAULT NULL,
			is_active tinyint(1) DEFAULT 1 NOT NULL,
			PRIMARY KEY  (id),
			KEY salon_id (salon_id),
			KEY wp_user_id (wp_user_id)
		) $charset_collate;";

		// 4. Memberships
		$sql_memberships = "CREATE TABLE {$wpdb->prefix}gl_memberships (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(150) NOT NULL,
			tier_level int(11) DEFAULT 1 NOT NULL,
			price decimal(10,2) NOT NULL,
			duration_months int(11) DEFAULT 1 NOT NULL,
			benefits text,
			banner_image_url varchar(255) DEFAULT NULL,
			is_active tinyint(1) DEFAULT 1 NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 5. Clients (Client Metadata beyond standard wp_users)
		$sql_clients = "CREATE TABLE {$wpdb->prefix}gl_clients (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			wp_user_id bigint(20) NOT NULL,
			membership_id bigint(20) DEFAULT NULL,
			membership_expiry datetime DEFAULT NULL,
			total_spent decimal(10,2) DEFAULT '0.00' NOT NULL,
			notes text,
			PRIMARY KEY  (id),
			KEY wp_user_id (wp_user_id),
			KEY idx_membership_expiry (membership_id, membership_expiry)
		) $charset_collate;";

		// 6. Appointments
		$sql_appointments = "CREATE TABLE {$wpdb->prefix}gl_appointments (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			client_id bigint(20) NOT NULL,
			salon_id bigint(20) NOT NULL,
			staff_id bigint(20) NOT NULL,
			service_name varchar(255) NOT NULL,
			appointment_time datetime NOT NULL,
			duration_minutes int(11) NOT NULL,
			status varchar(50) DEFAULT 'confirmed' NOT NULL,
			amount decimal(10,2) NOT NULL,
			payment_status varchar(50) DEFAULT 'pending' NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY staff_time (staff_id, appointment_time),
			KEY salon_time (salon_id, appointment_time),
			KEY idx_salon_time_status (salon_id, appointment_time, status)
		) $charset_collate;";

		// 7. Payroll
		$sql_payroll = "CREATE TABLE {$wpdb->prefix}gl_payroll (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			staff_id bigint(20) NOT NULL,
			salon_id bigint(20) NOT NULL,
			period_start date NOT NULL,
			period_end date NOT NULL,
			total_services decimal(10,2) DEFAULT '0.00' NOT NULL,
			commission_earned decimal(10,2) DEFAULT '0.00' NOT NULL,
			status varchar(50) DEFAULT 'pending' NOT NULL,
			processed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY staff_period (staff_id, period_start),
			KEY idx_staff_period_status (staff_id, period_start, period_end, status)
		) $charset_collate;";

		// 8. Product Sales
		$sql_product_sales = "CREATE TABLE {$wpdb->prefix}gl_product_sales (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			salon_id bigint(20) NOT NULL,
			client_id bigint(20) DEFAULT NULL,
			wc_order_id bigint(20) NOT NULL,
			product_name varchar(255) DEFAULT NULL,
			product_image_url varchar(255) DEFAULT NULL,
			total_amount decimal(10,2) NOT NULL,
			sale_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY salon_id (salon_id)
		) $charset_collate;";

		// 9. Service Logs
		$sql_service_logs = "CREATE TABLE {$wpdb->prefix}gl_service_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			appointment_id bigint(20) NOT NULL,
			notes text,
			before_image_url varchar(255) DEFAULT NULL,
			after_image_url varchar(255) DEFAULT NULL,
			logged_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 10. Financial Reports
		$sql_financial = "CREATE TABLE {$wpdb->prefix}gl_financial_reports (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			franchise_id bigint(20) DEFAULT NULL,
			salon_id bigint(20) DEFAULT NULL,
			report_month varchar(10) NOT NULL,
			total_revenue decimal(12,2) DEFAULT '0.00' NOT NULL,
			total_expenses decimal(12,2) DEFAULT '0.00' NOT NULL,
			net_profit decimal(12,2) DEFAULT '0.00' NOT NULL,
			report_chart_image_url varchar(255) DEFAULT NULL,
			generated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY report_month (report_month)
		) $charset_collate;";

		// 11. Service Pricing (global catalogue + franchise overrides)
		$sql_service_pricing = "CREATE TABLE {$wpdb->prefix}gl_service_pricing (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			service_name varchar(255) DEFAULT NULL,
			category varchar(100) DEFAULT NULL,
			base_price decimal(10,2) DEFAULT NULL,
			custom_price decimal(10,2) DEFAULT NULL,
			duration_minutes int(11) DEFAULT 60,
			description text DEFAULT NULL,
			image_url varchar(255) DEFAULT NULL,
			is_active tinyint(1) DEFAULT 1 NOT NULL,
			service_id bigint(20) DEFAULT NULL,
			franchise_id bigint(20) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY service_id (service_id),
			KEY franchise_id (franchise_id)
		) $charset_collate;";

		// 12. Inventory
		$sql_inventory = "CREATE TABLE {$wpdb->prefix}gl_inventory (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			salon_id bigint(20) NOT NULL,
			product_name varchar(255) NOT NULL,
			category varchar(100) DEFAULT NULL,
			quantity int(11) DEFAULT 0 NOT NULL,
			reorder_threshold int(11) DEFAULT 5 NOT NULL,
			unit_cost decimal(10,2) DEFAULT '0.00',
			last_restocked datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY salon_id (salon_id)
		) $charset_collate;";

		// 13. CRM: Leads
		$sql_leads = "CREATE TABLE {$wpdb->prefix}gl_leads (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(150) NOT NULL,
			email varchar(150) NOT NULL,
			phone varchar(30) NOT NULL,
			state varchar(100) DEFAULT NULL,
			interest_type varchar(80) DEFAULT 'franchise' NOT NULL,
			message text,
			avatar_url varchar(255) DEFAULT NULL,
			status varchar(50) DEFAULT 'new' NOT NULL,
			assigned_to bigint(20) DEFAULT NULL,
			source varchar(80) DEFAULT 'website' NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY assigned_to (assigned_to),
			KEY idx_status_assigned_created (status, assigned_to, created_at)
		) $charset_collate;";

		// 14. CRM: Follow-ups (activity log per lead)
		$sql_followups = "CREATE TABLE {$wpdb->prefix}gl_followups (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) NOT NULL,
			type varchar(80) NOT NULL,
			notes text,
			due_at datetime DEFAULT NULL,
			status varchar(50) DEFAULT 'pending' NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY due_at (due_at)
		) $charset_collate;";

		// 15. Territories (formal entity for conflict detection)
		$sql_territories = "CREATE TABLE {$wpdb->prefix}gl_territories (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			franchise_id bigint(20) NOT NULL,
			state varchar(100) NOT NULL,
			district varchar(150) DEFAULT NULL,
			effective_from date NOT NULL,
			status varchar(50) DEFAULT 'active' NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY territory_state (state),
			KEY franchise_id (franchise_id)
		) $charset_collate;";

		// 16. Analytics: KPI Metrics Cache (replaces real-time recalculation)
		$sql_metrics_cache = "CREATE TABLE {$wpdb->prefix}gl_metrics_cache (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			metric_key varchar(100) NOT NULL,
			metric_value text NOT NULL,
			cached_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY metric_key (metric_key)
		) $charset_collate;";

		// 17. Membership Purchases
		$sql_membership_purchases = "CREATE TABLE {$wpdb->prefix}gl_membership_purchases (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL,
			membership_id bigint(20) unsigned NOT NULL,
			source varchar(50) NOT NULL DEFAULT 'manual',
			wc_order_id bigint(20) unsigned DEFAULT 0,
			granted_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'active',
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY membership_id (membership_id),
			KEY granted_at (granted_at),
			KEY membership_granted (membership_id, granted_at)
		) $charset_collate;";

		// 18. Attendance
		$sql_attendance = "CREATE TABLE {$wpdb->prefix}gl_attendance (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			staff_id bigint(20) unsigned NOT NULL,
			salon_id bigint(20) unsigned NOT NULL,
			shift_date date NOT NULL,
			check_in datetime DEFAULT NULL,
			check_out datetime DEFAULT NULL,
			hours_worked decimal(5,2) DEFAULT 0.00,
			overtime_minutes int(11) DEFAULT 0,
			is_late tinyint(1) DEFAULT 0,
			late_minutes int(11) DEFAULT 0,
			status varchar(50) DEFAULT 'present' NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY staff_shift (staff_id, shift_date),
			KEY staff_id (staff_id),
			KEY shift_date (shift_date),
			KEY salon_status_date (salon_id, status, shift_date)
		) $charset_collate;";

		// 19. Shifts
		$sql_shifts = "CREATE TABLE {$wpdb->prefix}gl_shifts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			staff_id bigint(20) unsigned NOT NULL,
			salon_id bigint(20) unsigned NOT NULL,
			shift_date date NOT NULL,
			shift_start time NOT NULL,
			shift_end time NOT NULL,
			type varchar(50) DEFAULT 'scheduled' NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY staff_shift (staff_id, shift_date),
			KEY staff_id (staff_id),
			KEY shift_date (shift_date),
			KEY salon_date (salon_id, shift_date)
		) $charset_collate;";

		// 20. Salon Hours
		$sql_salon_hours = "CREATE TABLE {$wpdb->prefix}gl_salon_hours (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			salon_id bigint(20) NOT NULL,
			day_of_week tinyint(1) NOT NULL,
			open_time time NOT NULL,
			close_time time NOT NULL,
			PRIMARY KEY  (id),
			KEY salon_id (salon_id)
		) $charset_collate;";

		// Execute all dbDelta calls
		dbDelta($sql_franchises);
		dbDelta($sql_salons);
		dbDelta($sql_staff);
		dbDelta($sql_memberships);
		dbDelta($sql_clients);
		dbDelta($sql_appointments);
		dbDelta($sql_payroll);
		dbDelta($sql_product_sales);
		dbDelta($sql_service_logs);
		dbDelta($sql_financial);
		dbDelta($sql_service_pricing);
		dbDelta($sql_inventory);
		dbDelta($sql_leads);
		dbDelta($sql_followups);
		dbDelta($sql_territories);
		dbDelta($sql_metrics_cache);
		dbDelta($sql_membership_purchases);
		dbDelta($sql_attendance);
		dbDelta($sql_shifts);
		dbDelta($sql_salon_hours);
	}

	/**
	 * Register Custom Roles.
	 */
	private static function add_custom_roles()
	{
		// Super Admin
		add_role(
			'glamlux_super_admin',
			__('GlamLux Super Admin', 'glamlux-core'),
			array(
			'read' => true,
			'manage_glamlux_platform' => true,
		)
		);

		// Franchise Admin
		add_role(
			'glamlux_franchise_admin',
			__('Franchise Admin', 'glamlux-core'),
			array(
			'read' => true,
			'manage_glamlux_franchise' => true,
		)
		);

		// Franchise Staff
		add_role(
			'glamlux_staff',
			__('Franchise Staff', 'glamlux-core'),
			array(
			'read' => true,
			'manage_glamlux_appointments' => true,
		)
		);

		// Client
		add_role(
			'glamlux_client',
			__('GlamLux Client', 'glamlux-core'),
			array(
			'read' => true,
			'book_appointments' => true,
		)
		);

		// State Manager
		if (!get_role('glamlux_state_manager')) {
			add_role(
				'glamlux_state_manager',
				__('State Manager', 'glamlux-core'),
				array(
				'read' => true,
				'view_state_reports' => true,
				'manage_glamlux_franchise' => true,
			)
			);
		}

		// Salon Manager (Phase 1.3)
		if (!get_role('glamlux_salon_manager')) {
			add_role(
				'glamlux_salon_manager',
				__('Salon Manager', 'glamlux-core'),
				array(
				'read' => true,
				'manage_glamlux_salon' => true,
				'view_salon_reports' => true,
				'manage_glamlux_appointments' => true,
				'manage_glamlux_inventory' => true,
			)
			);
		}

		// After add_role, sync all caps to current definitions
		self::update_role_capabilities();
	}
}
