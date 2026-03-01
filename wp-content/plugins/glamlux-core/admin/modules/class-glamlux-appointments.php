<?php
/**
 * GlamLux Appointments Module
 *
 * Handles appointment scheduling, conflict checking, status management,
 * and the admin-facing schedule dashboard.
 *
 * Phase 12 Hardening:
 * - All create/update operations wrapped in atomic SQL transactions.
 * - Full WP_Error propagation for admin UI feedback.
 * - Strict `current_user_can` checks before any data mutation.
 * - All inputs prepared via $wpdb->prepare().
 */
class GlamLux_Appointments
{

	public function __construct()
	{
	// AJAX hooks migrated to class-glamlux-ajax.php (Phase 1.4)
	}


	// -------------------------------------------------------------------------
	// Admin Dashboard View
	// -------------------------------------------------------------------------

	/**
	 * Render the Appointments management page in the WP Admin dashboard.
	 * Only accessible to users with the manage_appointments capability.
	 */
	public function render_admin_dashboard()
	{

		if (!current_user_can('glamlux_manage_appointments') && !current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'glamlux-core'));
		}

		global $wpdb;

		// Fetch upcoming appointments with joined salon/service info
		$appointments = $wpdb->get_results(
			"SELECT a.id, a.appointment_time, a.status, a.notes,
			        u.display_name AS client_name,
			        s.name AS salon_name
			 FROM {$wpdb->prefix}gl_appointments a
			 LEFT JOIN {$wpdb->prefix}gl_clients c  ON a.client_id = c.id
			 LEFT JOIN {$wpdb->users} u             ON c.wp_user_id = u.ID
			 LEFT JOIN {$wpdb->prefix}gl_salons s   ON a.salon_id = s.id
			 WHERE a.appointment_time >= NOW()
			 ORDER BY a.appointment_time ASC
			 LIMIT 100"
		);

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__('Appointments', 'glamlux-core') . '</h1>';
		echo '<hr class="wp-header-end">';

		if (empty($appointments)) {
			echo '<div class="notice notice-info"><p>' . esc_html__('No upcoming appointments found.', 'glamlux-core') . '</p></div>';
		}
		else {
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr><th>ID</th><th>Client</th><th>Salon</th><th>Date & Time</th><th>Status</th><th>Notes</th></tr></thead><tbody>';
			foreach ($appointments as $a) {
				$status_class = 'pending' === $a->status ? 'notice-warning' : ('confirmed' === $a->status ? 'notice-success' : '');
				printf(
					'<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td><span class="%s">%s</span></td><td>%s</td></tr>',
					esc_html($a->id),
					esc_html($a->client_name),
					esc_html($a->salon_name),
					esc_html($a->appointment_time),
					esc_attr($status_class),
					esc_html(ucfirst($a->status)),
					esc_html($a->notes)
				);
			}
			echo '</tbody></table>';
		}

		echo '</div>';
	}

	/**
	 * For Staff login to view their own schedule.
	 */
	public function render_staff_dashboard()
	{

		if (!is_user_logged_in()) {
			wp_die(esc_html__('Please log in to view your schedule.', 'glamlux-core'));
		}

		$user_id = get_current_user_id();
		global $wpdb;

		$staff = $wpdb->get_row(
			$wpdb->prepare("SELECT id FROM {$wpdb->prefix}gl_staff WHERE wp_user_id = %d LIMIT 1", $user_id)
		);

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__('My Appointments', 'glamlux-core') . '</h1>';
		echo '<hr class="wp-header-end">';

		if (!$staff) {
			echo '<div class="notice notice-warning"><p>' . esc_html__('No staff profile linked to your account.', 'glamlux-core') . '</p></div>';
			echo '</div>';
			return;
		}

		$appointments = $wpdb->get_results(
			$wpdb->prepare(
			"SELECT a.*, s.name AS salon_name, u.display_name AS client_name
				 FROM {$wpdb->prefix}gl_appointments a
				 LEFT JOIN {$wpdb->prefix}gl_salons s  ON a.salon_id = s.id
				 LEFT JOIN {$wpdb->prefix}gl_clients c ON a.client_id = c.id
				 LEFT JOIN {$wpdb->users} u            ON c.wp_user_id = u.ID
				 WHERE a.staff_id = %d AND DATE(a.appointment_time) = CURDATE()
				 ORDER BY a.appointment_time ASC",
			$staff->id
		)
		);

		if (empty($appointments)) {
			echo '<div class="notice notice-info"><p>' . esc_html__("No appointments scheduled for today.", 'glamlux-core') . '</p></div>';
		}
		else {
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr><th>Time</th><th>Client</th><th>Salon</th><th>Status</th></tr></thead><tbody>';
			foreach ($appointments as $a) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html(date('g:i A', strtotime($a->appointment_time))),
					esc_html($a->client_name),
					esc_html($a->salon_name),
					esc_html(ucfirst($a->status))
				);
			}
			echo '</tbody></table>';
		}

		echo '</div>';
	}

	// -------------------------------------------------------------------------
	// Core Business Logic
	// -------------------------------------------------------------------------

	/**
	 * Create a new appointment atomically.
	 *
	 * Wraps both the conflict check AND the insert in a single DB transaction.
	 * If either step fails, a ROLLBACK is issued so no partial data is saved.
	 *
	 * @param int    $client_id        ID from wp_gl_clients.
	 * @param int    $salon_id         ID from wp_gl_salons.
	 * @param string $service_id       ID (or slug) from service catalogue.
	 * @param string $appointment_time MySQL datetime string.
	 * @param string $notes            Optional client notes.
	 * @return int|WP_Error            New appointment ID on success, WP_Error on failure.
	 */
	public function create_appointment($client_id, $salon_id, $service_id, $appointment_time, $notes = '')
	{
		global $wpdb;

		// Fallback for staff_id if not dynamically assigned by UI
		$staff_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}gl_staff WHERE salon_id = %d LIMIT 1", $salon_id));
		if (!$staff_id) {
			$staff_id = 1;
		}

		$repo = new GlamLux_Repo_Appointment();
		$service = new GlamLux_Service_Booking($repo);

		return $service->secure_book_appointment($staff_id, $client_id, $service_id, $salon_id, $appointment_time, $notes);
	}

	/**
	 * Mark an appointment as completed, triggering the Event Dispatcher
	 * to orchestrate payroll generation automatically.
	 *
	 * @param int $appointment_id
	 * @return bool
	 */
	public function complete_appointment($appointment_id)
	{
		$repo = new GlamLux_Repo_Appointment();
		$service = new GlamLux_Service_Booking($repo);

		return $service->mark_completed($appointment_id);
	}

	/**
	 * Stateless availability checker — no transaction needed, read-only.
	 *
	 * @param int    $salon_id         Salon to check.
	 * @param string $appointment_time Date-time string.
	 * @return bool True if slot is open, false if taken.
	 */
	public function check_availability($salon_id, $appointment_time)
	{
		$availabilityService = new GlamLux_Service_Availability();

		// Defaulting to 30 mins for the frontend availability checker 
		// if frontend isn't yet sending duration.
		return $availabilityService->is_salon_slot_available($salon_id, $appointment_time, 30);
	}

// -------------------------------------------------------------------------
// AJAX Handlers Migrated to class-glamlux-ajax.php
// -------------------------------------------------------------------------

}
