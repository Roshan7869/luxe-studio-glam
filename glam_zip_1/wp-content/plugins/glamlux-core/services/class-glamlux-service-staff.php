<?php
/**
 * GlamLux Staff Service
 *
 * LAYER: Business Logic (Service Layer)
 * RULE:  Zero SQL. All data access delegates to GlamLux_Repo_Staff.
 */
class GlamLux_Service_Staff
{
    private GlamLux_Repo_Staff $repo;

    public function __construct(GlamLux_Repo_Staff $repo = null)
    {
        $this->repo = $repo ?: new GlamLux_Repo_Staff();
    }

    // ─────────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────────

    public function get_all(array $filters = []): array
    {
        return $this->repo->get_all($filters);
    }

    public function get_by_id(int $id): ?array
    {
        return $this->repo->get_by_id($id);
    }

    public function get_performance(int $id, string $from, string $to): array
    {
        $staff = $this->repo->get_by_id($id);
        if (!$staff) {
            return ['error' => 'Staff member not found.'];
        }
        $stats = $this->repo->get_performance_stats($id, $from, $to);
        return array_merge(
        ['staff_id' => $id, 'name' => $staff['name'], 'from' => $from, 'to' => $to],
            $stats
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // CREATE
    // ─────────────────────────────────────────────────────────────────

    public function create(array $data): int|WP_Error
    {
        $required = ['wp_user_id', 'salon_id', 'job_role'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error(
                    'missing_field',
                    sprintf('Field "%s" is required.', $field),
                ['status' => 400]
                    );
            }
        }

        // Prevent duplicate staff records for same user
        $existing = $this->repo->get_by_user_id((int)$data['wp_user_id']);
        if ($existing) {
            return new WP_Error(
                'duplicate_staff',
                'A staff record already exists for this user.',
            ['status' => 409]
                );
        }

        $allowed = [
            'wp_user_id', 'salon_id', 'job_role', 'specializations',
            'commission_rate', 'profile_image_url',
        ];
        $insert = array_intersect_key($data, array_flip($allowed));
        $insert['is_active'] = 1;

        $id = $this->repo->insert($insert);
        if (!$id) {
            return new WP_Error('db_error', 'Failed to create staff record.', ['status' => 500]);
        }

        // Assign glamlux_staff role to the WP user
        $user = get_user_by('ID', (int)$data['wp_user_id']);
        if ($user) {
            $user->add_role('glamlux_staff');
        }

        // Purge cache
        delete_transient('gl_api_staff_profiles_0');
        delete_transient('gl_api_staff_profiles_' . (int)$data['salon_id']);

        return $id;
    }

    // ─────────────────────────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────────────────────────

    public function update(int $id, array $data): bool|WP_Error
    {
        $staff = $this->repo->get_by_id($id);
        if (!$staff) {
            return new WP_Error('not_found', 'Staff member not found.', ['status' => 404]);
        }

        $allowed = ['salon_id', 'job_role', 'specializations', 'commission_rate', 'profile_image_url'];
        $update = array_intersect_key($data, array_flip($allowed));

        if (empty($update)) {
            return new WP_Error('no_data', 'No valid fields provided for update.', ['status' => 400]);
        }

        $success = $this->repo->update($id, $update);

        // Purge cache
        delete_transient('gl_api_staff_profiles_0');
        delete_transient('gl_api_staff_profiles_' . $staff['salon_id']);

        return $success;
    }

    // ─────────────────────────────────────────────────────────────────
    // DEACTIVATE
    // ─────────────────────────────────────────────────────────────────

    public function deactivate(int $id): bool|WP_Error
    {
        $staff = $this->repo->get_by_id($id);
        if (!$staff) {
            return new WP_Error('not_found', 'Staff member not found.', ['status' => 404]);
        }

        $success = $this->repo->deactivate($id);

        // Purge cache
        delete_transient('gl_api_staff_profiles_0');
        delete_transient('gl_api_staff_profiles_' . $staff['salon_id']);

        return $success;
    }
}
