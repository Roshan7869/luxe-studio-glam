<?php
/**
 * GlamLux Service Lead — CRM Service Layer
 *
 * LAYER: Application / Business Logic
 * RULE:  No $wpdb allowed. Uses GlamLux_Repo_Lead for all data.
 *        Events dispatched via do_action for decoupled side effects.
 */
class GlamLux_Service_Lead
{

	private $repo;
	private $dispatcher;

	public function __construct(
		GlamLux_Event_Dispatcher $dispatcher = null,
		GlamLux_Repo_Lead $repo = null
		)
	{
		$this->repo = $repo ?: new GlamLux_Repo_Lead();
		$this->dispatcher = $dispatcher;
	}

	// ─────────────────────────────────────────────────────────────────
	// QUERY Operations
	// ─────────────────────────────────────────────────────────────────

	public function get_all(array $filters = []): array
	{
		return $this->repo->get_all($filters);
	}

	public function get_by_id(int $id): ?array
	{
		return $this->repo->get_by_id($id);
	}

	public function get_funnel_summary(): array
	{
		$rows = $this->repo->get_funnel_summary();
		$funnel = ['new' => 0, 'contacted' => 0, 'qualified' => 0, 'proposal_sent' => 0, 'converted' => 0, 'lost' => 0];
		foreach ($rows as $r) {
			$funnel[$r['status']] = (int)$r['count'];
		}
		return $funnel;
	}

	public function get_overdue_followups(): array
	{
		return $this->repo->get_overdue_followups();
	}

	// ─────────────────────────────────────────────────────────────────
	// BUSINESS LOGIC Operations
	// ─────────────────────────────────────────────────────────────────

	public function capture_lead(array $data): int|WP_Error
	{
		// --- Input Validation ---
		foreach (['name', 'email', 'phone'] as $field) {
			if (empty($data[$field])) {
				return new WP_Error('glamlux_lead_missing', "Missing required field: {$field}", ['status' => 400]);
			}
		}

		$email = sanitize_email($data['email']);
		$phone = sanitize_text_field($data['phone']);

		// --- Duplicate Prevention (48h window) ---
		if ($this->repo->get_recent_duplicate($email, $phone)) {
			return new WP_Error('glamlux_lead_duplicate', 'A lead with this contact already exists.', ['status' => 409]);
		}

		// --- Territory Auto-Assignment ---
		$state = sanitize_text_field($data['state'] ?? '');
		$assigned_to = null;
		if (class_exists('GlamLux_Service_Territory') && $state) {
			$assigned_to = (new GlamLux_Service_Territory())->auto_assign_by_territory($state);
		}

		// --- Insert Lead ---
		$lead_id = $this->repo->insert_lead([
			'name' => sanitize_text_field($data['name']),
			'email' => $email,
			'phone' => $phone,
			'state' => $state,
			'interest_type' => sanitize_text_field($data['interest_type'] ?? 'franchise'),
			'message' => sanitize_textarea_field($data['message'] ?? ''),
			'source' => sanitize_text_field($data['source'] ?? 'website'),
			'status' => 'new',
			'assigned_to' => $assigned_to,
		]);

		if (!$lead_id) {
			return new WP_Error('glamlux_lead_db', 'Failed to save lead.', ['status' => 500]);
		}

		// --- Auto-schedule Initial Follow-up ---
		$this->schedule_followup($lead_id, 'initial_contact', '+24 hours');

		// --- Dispatch Event ---
		do_action('glamlux_lead_captured', $lead_id, [
			'name' => $data['name'],
			'email' => $email,
			'state' => $state,
			'assigned_to' => $assigned_to,
		]);
		if ($this->dispatcher) {
			$this->dispatcher->dispatch('lead_captured', ['lead_id' => $lead_id]);
		}

		return $lead_id;
	}

	public function update_status(int $id, string $status, string $notes = ''): bool|WP_Error
	{
		$allowed = ['new', 'contacted', 'qualified', 'proposal_sent', 'converted', 'lost'];
		if (!in_array($status, $allowed, true)) {
			return new WP_Error('glamlux_lead_invalid_status', 'Invalid lead status.', ['status' => 400]);
		}

		$existing = $this->repo->get_by_id($id);
		if (!$existing) {
			return new WP_Error('glamlux_lead_not_found', 'Lead not found.', ['status' => 404]);
		}

		// PHASE 2: Wrap update + followup insert in atomic transaction
		// Prevents audit trail corruption if secondary insert fails
		global $wpdb;
		$wpdb->query('START TRANSACTION');
		
		try {
			$updated = $this->repo->update_lead_status($id, $status);
			if (!$updated) {
				$wpdb->query('ROLLBACK');
				return false;
			}

			// Log a completed follow-up note for audit trail
			if ($notes) {
				$inserted = $this->repo->insert_followup([
					'lead_id' => $id,
					'type' => 'status_change_' . $status,
					'notes' => sanitize_textarea_field($notes),
					'status' => 'completed',
					'due_at' => current_time('mysql'),
					'completed_at' => current_time('mysql'),
				]);
				
				if (!$inserted) {
					$wpdb->query('ROLLBACK');
					return new WP_Error('audit_trail_failed', 'Failed to log status change.', ['status' => 500]);
				}
			}

			$wpdb->query('COMMIT');
			
			// Fire conversion event for downstream listeners (notify, analytics)
			if ($status === 'converted') {
				do_action('glamlux_lead_converted', $id);
				if ($this->dispatcher) {
					$this->dispatcher->dispatch('lead_converted', ['lead_id' => $id]);
				}
			}
			
			return true;
		} catch (Exception $e) {
			$wpdb->query('ROLLBACK');
			return new WP_Error('transaction_failed', $e->getMessage(), ['status' => 500]);
		}
	}

		return true;
	}

	public function assign(int $id, int $user_id): bool|WP_Error
	{
		$existing = $this->repo->get_by_id($id);
		if (!$existing) {
			return new WP_Error('glamlux_lead_not_found', 'Lead not found.', ['status' => 404]);
		}

		return $this->repo->assign_lead($id, $user_id);
	}

	public function schedule_followup(int $lead_id, string $type, string $due = '+1 day'): bool
	{
		return (bool)$this->repo->insert_followup([
			'lead_id' => $lead_id,
			'type' => sanitize_text_field($type),
			'due_at' => date('Y-m-d H:i:s', strtotime($due)),
			'status' => 'pending',
		]);
	}

	public function complete_followup(int $followup_id, string $notes = ''): bool
	{
		return $this->repo->complete_followup($followup_id, $notes);
	}
}