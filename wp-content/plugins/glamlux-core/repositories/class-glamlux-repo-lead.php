<?php
/**
 * GlamLux Lead Repository
 *
 * LAYER: Repository (Data Access Layer)
 * RULE:  All SQL for CRM leads, follow-ups, and funnel reporting lives here.
 */
class GlamLux_Repo_Lead
{

	// ─────────────────────────────────────────────────────────────────
	// READ Operations
	// ─────────────────────────────────────────────────────────────────

	public function get_all(array $filters = []): array
	{
		global $wpdb;
		$where = '1=1';
		$args = [];

		if (!empty($filters['status'])) {
			$where .= ' AND status = %s';
			$args[] = $filters['status'];
		}
		if (!empty($filters['assigned_to'])) {
			$where .= ' AND assigned_to = %d';
			$args[] = (int)$filters['assigned_to'];
		}
		if (!empty($filters['state'])) {
			$where .= ' AND state = %s';
			$args[] = $filters['state'];
		}

		$sql = "SELECT * FROM {$wpdb->prefix}gl_leads WHERE {$where} ORDER BY created_at DESC LIMIT 200";

		if (!empty($args)) {
			return $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
		}
		return $wpdb->get_results($sql, ARRAY_A) ?: [];
	}

	public function get_by_id(int $id): ?array
	{
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$wpdb->prefix}gl_leads WHERE id = %d LIMIT 1", $id),
			ARRAY_A
		) ?: null;
	}

	public function get_recent_duplicate(string $email, string $phone): ?int
	{
		global $wpdb;
		$id = $wpdb->get_var(
			$wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}gl_leads
				 WHERE (email = %s OR phone = %s)
				   AND created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
				 LIMIT 1",
			$email, $phone
		)
		);
		return $id ? (int)$id : null;
	}

	public function get_funnel_summary(): array
	{
		global $wpdb;
		return $wpdb->get_results(
			"SELECT status, COUNT(*) AS count FROM {$wpdb->prefix}gl_leads GROUP BY status",
			ARRAY_A
		) ?: [];
	}

	public function get_followups_for_lead(int $lead_id): array
	{
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gl_followups WHERE lead_id = %d ORDER BY due_at ASC",
			$lead_id
		),
			ARRAY_A
		) ?: [];
	}

	public function get_overdue_followups(): array
	{
		global $wpdb;
		return $wpdb->get_results(
			"SELECT f.*, l.name AS lead_name, l.email, l.phone
			 FROM {$wpdb->prefix}gl_followups f
			 INNER JOIN {$wpdb->prefix}gl_leads l ON f.lead_id = l.id
			 WHERE f.status = 'pending' AND f.due_at < NOW()
			 ORDER BY f.due_at ASC LIMIT 100",
			ARRAY_A
		) ?: [];
	}

	// ─────────────────────────────────────────────────────────────────
	// WRITE Operations
	// ─────────────────────────────────────────────────────────────────

	public function insert_lead(array $data): int
	{
		global $wpdb;
		$data['created_at'] = current_time('mysql');
		$data['updated_at'] = current_time('mysql');
		$wpdb->insert($wpdb->prefix . 'gl_leads', $data);
		return (int)$wpdb->insert_id;
	}

	public function update_lead(int $id, array $data): bool
	{
		global $wpdb;
		$data['updated_at'] = current_time('mysql');
		return false !== $wpdb->update($wpdb->prefix . 'gl_leads', $data, ['id' => $id]);
	}

	public function update_lead_status(int $id, string $status): bool
	{
		return $this->update_lead($id, ['status' => $status]);
	}

	public function assign_lead(int $id, int $user_id): bool
	{
		return $this->update_lead($id, ['assigned_to' => $user_id]);
	}

	public function insert_followup(array $data): int
	{
		global $wpdb;
		$data['created_at'] = current_time('mysql');
		$wpdb->insert($wpdb->prefix . 'gl_followups', $data);
		return (int)$wpdb->insert_id;
	}

	public function complete_followup(int $followup_id, string $notes = ''): bool
	{
		global $wpdb;
		return false !== $wpdb->update(
			$wpdb->prefix . 'gl_followups',
		['status' => 'completed', 'notes' => sanitize_textarea_field($notes), 'completed_at' => current_time('mysql')],
		['id' => $followup_id]
		);
	}
}