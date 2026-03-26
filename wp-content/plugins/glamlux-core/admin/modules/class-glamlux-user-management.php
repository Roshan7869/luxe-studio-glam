<?php
/**
 * GlamLux User Management Admin Module
 *
 * Provides a WordPress admin UI to create, edit, and deactivate users with the
 * franchise hierarchy roles: Chairperson, Franchise Manager, Franchise Employee.
 * Tenant isolation is enforced throughout.
 *
 * @package GlamLux
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

class GlamLux_User_Management
{
    /**
     * Roles managed by this UI, in display order.
     */
    const MANAGED_ROLES = [
        'glamlux_chairperson'        => 'Chairperson',
        'glamlux_franchise_manager'  => 'Franchise Manager',
        'glamlux_franchise_employee' => 'Franchise Employee',
        'glamlux_staff'              => 'Staff',
    ];

    public function __construct()
    {
        add_action('admin_post_glamlux_create_managed_user',     [$this, 'handle_create']);
        add_action('admin_post_glamlux_update_managed_user',     [$this, 'handle_update']);
        add_action('admin_post_glamlux_deactivate_managed_user', [$this, 'handle_deactivate']);
        add_action('admin_post_glamlux_reactivate_managed_user', [$this, 'handle_reactivate']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin Page Render
    // ─────────────────────────────────────────────────────────────────────────

    public function render_admin_page(): void
    {
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to manage users.', 'glamlux-core'));
        }

        $editing_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        $editing    = $editing_id ? get_userdata($editing_id) : null;

        // Notices
        if (isset($_GET['gl_notice'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['gl_notice'])) . '</p></div>';
        }
        if (isset($_GET['gl_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($_GET['gl_error'])) . '</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('User Management', 'glamlux-core') . '</h1>';
        echo '<a href="#glamlux-user-form" class="page-title-action" id="add-user-btn">' . esc_html__('+ Add User', 'glamlux-core') . '</a>';
        echo '<hr class="wp-header-end">';

        $this->render_form($editing);
        $this->render_table();

        echo '</div>';
        $this->render_scripts();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Form
    // ─────────────────────────────────────────────────────────────────────────

    private function render_form(?WP_User $editing): void
    {
        $is_edit     = (bool) $editing;
        $form_action = $is_edit ? 'glamlux_update_managed_user' : 'glamlux_create_managed_user';
        $form_title  = $is_edit ? __('Edit User', 'glamlux-core') : __('Add New User', 'glamlux-core');
        $display     = $is_edit ? 'block' : 'none';

        echo '<div id="glamlux-user-form" style="display:' . esc_attr($display) . ';background:#fff;padding:24px;border:1px solid #ccd0d4;border-radius:8px;margin:20px 0;max-width:640px;">';
        echo '<h2 style="margin-top:0">' . esc_html($form_title) . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('glamlux_managed_user_form', '_glamlux_nonce');
        echo '<input type="hidden" name="action" value="' . esc_attr($form_action) . '">';
        if ($is_edit) {
            echo '<input type="hidden" name="user_id" value="' . esc_attr($editing->ID) . '">';
        }

        echo '<table class="form-table">';

        // Username (create only)
        if (!$is_edit) {
            $this->row('Username', '<input type="text" name="username" id="username" class="regular-text" required autocomplete="off" minlength="3">');
        }

        // Email
        $email_val = $is_edit ? esc_attr($editing->user_email) : '';
        $this->row('Email', '<input type="email" name="email" id="email" class="regular-text" value="' . $email_val . '" required>');

        // Password (create only)
        if (!$is_edit) {
            $this->row(
                'Password',
                '<input type="password" name="password" id="password" class="regular-text" required minlength="8" autocomplete="new-password">' .
                '<p class="description">' . esc_html__('Minimum 8 characters.', 'glamlux-core') . '</p>'
            );
        }

        // First Name
        $fn_val = $is_edit ? esc_attr($editing->first_name) : '';
        $this->row('First Name', '<input type="text" name="first_name" class="regular-text" value="' . $fn_val . '">');

        // Last Name
        $ln_val = $is_edit ? esc_attr($editing->last_name) : '';
        $this->row('Last Name', '<input type="text" name="last_name" class="regular-text" value="' . $ln_val . '">');

        // Role
        $current_role  = $is_edit ? ($editing->roles[0] ?? '') : '';
        $assignable    = $this->get_assignable_roles();
        $role_select   = '<select name="role" id="role" required>';
        $role_select  .= '<option value="">' . esc_html__('— Select Role —', 'glamlux-core') . '</option>';
        foreach ($assignable as $slug => $label) {
            $sel         = selected($current_role, $slug, false);
            $role_select .= sprintf('<option value="%s"%s>%s</option>', esc_attr($slug), $sel, esc_html($label));
        }
        $role_select .= '</select>';
        $this->row('Role', $role_select);

        // Franchise Association
        global $wpdb;
        $franchises = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}gl_franchises WHERE is_active = 1 ORDER BY name");
        if ($franchises) {
            $current_franchise = $is_edit ? (int) get_user_meta($editing->ID, 'glamlux_managed_franchise_id', true) : 0;

            // Chairperson/FranchiseManager: pre-select caller's franchise if not super admin
            if (!$current_franchise && !current_user_can('manage_options')) {
                $current_franchise = (int) get_user_meta(get_current_user_id(), 'glamlux_managed_franchise_id', true);
            }

            $fran_select  = '<select name="franchise_id" id="franchise_id">';
            $fran_select .= '<option value="">' . esc_html__('— Select Franchise —', 'glamlux-core') . '</option>';
            foreach ($franchises as $f) {
                // Chairpersons / Franchise Managers can only assign to their own franchise
                if (!current_user_can('manage_options') && !current_user_can('manage_glamlux_platform')) {
                    $caller_franchise = (int) get_user_meta(get_current_user_id(), 'glamlux_managed_franchise_id', true);
                    if ($caller_franchise && (int) $f->id !== $caller_franchise) {
                        continue;
                    }
                }
                $sel          = selected($current_franchise, (int) $f->id, false);
                $fran_select .= sprintf('<option value="%d"%s>%s</option>', $f->id, $sel, esc_html($f->name));
            }
            $fran_select .= '</select>';
            $this->row('Franchise', $fran_select);
        }

        // Salon Association
        $salons = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}gl_salons WHERE is_active = 1 ORDER BY name");
        if ($salons) {
            $current_salon = $is_edit ? (int) get_user_meta($editing->ID, 'glamlux_managed_salon_id', true) : 0;
            $salon_select  = '<select name="salon_id" id="salon_id">';
            $salon_select .= '<option value="">' . esc_html__('— Select Salon (optional) —', 'glamlux-core') . '</option>';
            foreach ($salons as $s) {
                $sel          = selected($current_salon, (int) $s->id, false);
                $salon_select .= sprintf('<option value="%d"%s>%s</option>', $s->id, $sel, esc_html($s->name));
            }
            $salon_select .= '</select>';
            $this->row('Salon', $salon_select);
        }

        echo '</table>';
        echo '<p class="submit">';
        submit_button($is_edit ? __('Update User', 'glamlux-core') : __('Create User', 'glamlux-core'), 'primary', 'submit', false);
        if ($is_edit) {
            echo ' <a href="' . esc_url(remove_query_arg('edit')) . '" class="button">' . esc_html__('Cancel', 'glamlux-core') . '</a>';
        }
        echo '</p>';
        echo '</form>';
        echo '</div>';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Users Table
    // ─────────────────────────────────────────────────────────────────────────

    private function render_table(): void
    {
        $users = $this->get_scoped_users();

        echo '<h2 style="margin-top:30px;">' . esc_html__('Franchise Users', 'glamlux-core') . '</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        foreach ([
            __('Name', 'glamlux-core'),
            __('Username', 'glamlux-core'),
            __('Email', 'glamlux-core'),
            __('Role', 'glamlux-core'),
            __('Franchise', 'glamlux-core'),
            __('Status', 'glamlux-core'),
            __('Actions', 'glamlux-core'),
        ] as $col) {
            echo '<th>' . esc_html($col) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if (empty($users)) {
            echo '<tr><td colspan="7">' . esc_html__('No users found.', 'glamlux-core') . '</td></tr>';
        } else {
            foreach ($users as $user) {
                $this->render_user_row($user);
            }
        }

        echo '</tbody></table>';
    }

    private function render_user_row(WP_User $user): void
    {
        $is_deactivated = (bool) get_user_meta($user->ID, 'glamlux_user_deactivated', true);
        $status_badge   = $is_deactivated
            ? '<span style="color:#c62828;font-weight:600;">Inactive</span>'
            : '<span style="color:#2e7d32;font-weight:600;">Active</span>';

        $franchise_id   = (int) get_user_meta($user->ID, 'glamlux_managed_franchise_id', true);
        $franchise_name = '';
        if ($franchise_id) {
            global $wpdb;
            $franchise_name = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}gl_franchises WHERE id = %d",
                $franchise_id
            )) ?? '—';
        }

        $role_label = self::MANAGED_ROLES[$user->roles[0] ?? ''] ?? ($user->roles[0] ?? 'Unknown');
        $edit_url   = add_query_arg('edit', $user->ID);

        echo '<tr' . ($is_deactivated ? ' style="opacity:0.6"' : '') . '>';
        printf('<td><strong>%s</strong></td>', esc_html($user->display_name));
        printf('<td>%s</td>', esc_html($user->user_login));
        printf('<td>%s</td>', esc_html($user->user_email));
        printf('<td>%s</td>', esc_html($role_label));
        printf('<td>%s</td>', esc_html($franchise_name ?: '—'));
        printf('<td>%s</td>', $status_badge);
        echo '<td>';
        echo '<a href="' . esc_url($edit_url) . '" class="button button-small">' . esc_html__('Edit', 'glamlux-core') . '</a> ';

        if (!$is_deactivated) {
            $deact_url = wp_nonce_url(
                admin_url('admin-post.php?action=glamlux_deactivate_managed_user&user_id=' . $user->ID),
                'glamlux_deactivate_managed_user_' . $user->ID
            );
            echo '<a href="' . esc_url($deact_url) . '" class="button button-small" style="color:#c62828;"'
                . ' onclick="return confirm(\'' . esc_js(__('Deactivate this user?', 'glamlux-core')) . '\')">'
                . esc_html__('Deactivate', 'glamlux-core') . '</a>';
        } else {
            $react_url = wp_nonce_url(
                admin_url('admin-post.php?action=glamlux_reactivate_managed_user&user_id=' . $user->ID),
                'glamlux_reactivate_managed_user_' . $user->ID
            );
            echo '<a href="' . esc_url($react_url) . '" class="button button-small" style="color:#1565c0;">'
                . esc_html__('Reactivate', 'glamlux-core') . '</a>';
        }

        echo '</td></tr>';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Form Handlers
    // ─────────────────────────────────────────────────────────────────────────

    public function handle_create(): void
    {
        if (!check_admin_referer('glamlux_managed_user_form', '_glamlux_nonce')) {
            wp_die(esc_html__('Security check failed.', 'glamlux-core'));
        }
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('Access denied.', 'glamlux-core'));
        }

        $role = sanitize_text_field($_POST['role'] ?? '');
        if (!array_key_exists($role, $this->get_assignable_roles())) {
            $this->redirect_error(__('You are not permitted to assign this role.', 'glamlux-core'));
        }

        $email = sanitize_email($_POST['email'] ?? '');
        if (!is_email($email)) {
            $this->redirect_error(__('Invalid email address.', 'glamlux-core'));
        }

        if (email_exists($email)) {
            $this->redirect_error(__('This email is already registered.', 'glamlux-core'));
        }

        $username = sanitize_user($_POST['username'] ?? '');
        if (!$username || username_exists($username)) {
            $this->redirect_error(__('Username is invalid or already taken.', 'glamlux-core'));
        }

        $password = $_POST['password'] ?? '';
        if (strlen($password) < 8) {
            $this->redirect_error(__('Password must be at least 8 characters.', 'glamlux-core'));
        }

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => $password,
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name'  => sanitize_text_field($_POST['last_name'] ?? ''),
            'role'       => $role,
        ]);

        if (is_wp_error($user_id)) {
            $this->redirect_error($user_id->get_error_message());
        }

        $franchise_id = absint($_POST['franchise_id'] ?? 0);
        if (!$franchise_id && !current_user_can('manage_options')) {
            $franchise_id = (int) get_user_meta(get_current_user_id(), 'glamlux_managed_franchise_id', true);
        }
        if ($franchise_id) {
            update_user_meta($user_id, 'glamlux_managed_franchise_id', $franchise_id);
        }
        $salon_id = absint($_POST['salon_id'] ?? 0);
        if ($salon_id) {
            update_user_meta($user_id, 'glamlux_managed_salon_id', $salon_id);
        }

        $this->redirect_success(__('User created successfully.', 'glamlux-core'));
    }

    public function handle_update(): void
    {
        if (!check_admin_referer('glamlux_managed_user_form', '_glamlux_nonce')) {
            wp_die(esc_html__('Security check failed.', 'glamlux-core'));
        }
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('Access denied.', 'glamlux-core'));
        }

        $user_id = absint($_POST['user_id'] ?? 0);
        $user    = get_userdata($user_id);
        if (!$user) {
            $this->redirect_error(__('User not found.', 'glamlux-core'));
        }

        $update_args = ['ID' => $user_id];

        $email = sanitize_email($_POST['email'] ?? '');
        if (is_email($email)) {
            $update_args['user_email'] = $email;
        }

        $update_args['first_name'] = sanitize_text_field($_POST['first_name'] ?? '');
        $update_args['last_name']  = sanitize_text_field($_POST['last_name'] ?? '');

        $result = wp_update_user($update_args);
        if (is_wp_error($result)) {
            $this->redirect_error($result->get_error_message());
        }

        $role = sanitize_text_field($_POST['role'] ?? '');
        if ($role && array_key_exists($role, $this->get_assignable_roles())) {
            $wp_user = new WP_User($user_id);
            $wp_user->set_role($role);
        }

        $franchise_id = absint($_POST['franchise_id'] ?? 0);
        if ($franchise_id) {
            update_user_meta($user_id, 'glamlux_managed_franchise_id', $franchise_id);
        }
        $salon_id = absint($_POST['salon_id'] ?? 0);
        if ($salon_id) {
            update_user_meta($user_id, 'glamlux_managed_salon_id', $salon_id);
        }

        $this->redirect_success(__('User updated successfully.', 'glamlux-core'));
    }

    public function handle_deactivate(): void
    {
        $user_id = absint($_GET['user_id'] ?? 0);
        if (!check_admin_referer('glamlux_deactivate_managed_user_' . $user_id)) {
            wp_die(esc_html__('Security check failed.', 'glamlux-core'));
        }
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('Access denied.', 'glamlux-core'));
        }
        if ($user_id === get_current_user_id()) {
            $this->redirect_error(__('You cannot deactivate your own account.', 'glamlux-core'));
        }

        update_user_meta($user_id, 'glamlux_user_deactivated', 1);
        update_user_meta($user_id, 'glamlux_user_deactivated_at', current_time('mysql'));
        update_user_meta($user_id, 'glamlux_user_deactivated_by', get_current_user_id());

        $this->redirect_success(__('User deactivated.', 'glamlux-core'));
    }

    public function handle_reactivate(): void
    {
        $user_id = absint($_GET['user_id'] ?? 0);
        if (!check_admin_referer('glamlux_reactivate_managed_user_' . $user_id)) {
            wp_die(esc_html__('Security check failed.', 'glamlux-core'));
        }
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('Access denied.', 'glamlux-core'));
        }

        delete_user_meta($user_id, 'glamlux_user_deactivated');
        delete_user_meta($user_id, 'glamlux_user_deactivated_at');
        delete_user_meta($user_id, 'glamlux_user_deactivated_by');

        $this->redirect_success(__('User reactivated.', 'glamlux-core'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Access Control Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function current_user_can_manage(): bool
    {
        return current_user_can('manage_options')
            || current_user_can('manage_glamlux_platform')
            || current_user_can('manage_glamlux_franchise_managers')
            || current_user_can('manage_glamlux_franchise_employees');
    }

    /**
     * Returns the roles the current user is allowed to assign, with display labels.
     */
    private function get_assignable_roles(): array
    {
        if (current_user_can('manage_options') || current_user_can('manage_glamlux_platform')) {
            return self::MANAGED_ROLES;
        }
        if (current_user_can('manage_glamlux_franchise_managers')) {
            return array_intersect_key(self::MANAGED_ROLES, array_flip([
                'glamlux_franchise_manager',
                'glamlux_franchise_employee',
                'glamlux_staff',
            ]));
        }
        if (current_user_can('manage_glamlux_franchise_employees')) {
            return array_intersect_key(self::MANAGED_ROLES, array_flip([
                'glamlux_franchise_employee',
                'glamlux_staff',
            ]));
        }
        return [];
    }

    /**
     * Get users scoped to the caller's franchise.
     *
     * @return WP_User[]
     */
    private function get_scoped_users(): array
    {
        $query_args = [
            'role__in' => array_keys(self::MANAGED_ROLES),
            'number'   => 200,
        ];

        if (!current_user_can('manage_options') && !current_user_can('manage_glamlux_platform')) {
            $caller_franchise = (int) get_user_meta(get_current_user_id(), 'glamlux_managed_franchise_id', true);
            if ($caller_franchise) {
                $query_args['meta_query'] = [[
                    'key'   => 'glamlux_managed_franchise_id',
                    'value' => $caller_franchise,
                ]];
            }

            // Franchise Manager: restrict to assignable roles only
            if (!current_user_can('manage_glamlux_franchise_managers')) {
                $query_args['role__in'] = ['glamlux_franchise_employee', 'glamlux_staff'];
            }
        }

        return get_users($query_args);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilities
    // ─────────────────────────────────────────────────────────────────────────

    private function row(string $label, string $control): void
    {
        printf(
            '<tr><th><label>%s</label></th><td>%s</td></tr>',
            esc_html__($label, 'glamlux-core'),
            $control // already escaped by callers
        );
    }

    private function get_return_url(): string
    {
        return admin_url('admin.php?page=glamlux-user-management');
    }

    private function redirect_success(string $message): void
    {
        wp_redirect(add_query_arg('gl_notice', urlencode($message), $this->get_return_url()));
        exit;
    }

    private function redirect_error(string $message): void
    {
        wp_redirect(add_query_arg('gl_error', urlencode($message), $this->get_return_url()));
        exit;
    }

    private function render_scripts(): void
    {
        ?>
        <script>
        (function () {
            var btn = document.getElementById('add-user-btn');
            var form = document.getElementById('glamlux-user-form');
            if (btn && form) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    form.style.display = form.style.display === 'none' ? 'block' : 'none';
                });
            }
        })();
        </script>
        <?php
    }
}
