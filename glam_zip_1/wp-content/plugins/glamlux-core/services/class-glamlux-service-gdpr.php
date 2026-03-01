<?php
class GlamLux_Service_GDPR
{
    private $repo;

    public function __construct(GlamLux_Repo_GDPR $repo = null)
    {
        $this->repo = $repo ?: new GlamLux_Repo_GDPR();
    }

    public function export_user_data($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('not_found', 'User not found.', ['status' => 404]);
        }

        $profile = ['id' => $user->ID, 'username' => $user->user_login, 'email' => $user->user_email];
        $client = $this->repo->get_client_by_user_id($user_id);
        $appointments = $this->repo->get_client_appointments($user_id);

        return [
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'profile' => $profile,
            'client' => $client,
            'appointments' => $appointments
        ];
    }

    public function delete_user_account($user_id)
    {
        if (user_can($user_id, 'manage_options')) {
            return new WP_Error('forbidden', 'Super admin cannot delete via API.', ['status' => 403]);
        }

        $client = $this->repo->get_client_by_user_id($user_id);
        if ($client) {
            $this->repo->delete_client_data($client['id']);
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id, 1);

        do_action('glamlux_account_deleted', $user_id);

        return true;
    }
}
