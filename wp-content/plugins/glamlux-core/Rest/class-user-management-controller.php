<?php
/**
 * GlamLux User Management REST Controller
 *
 * Provides secure CRUD endpoints for managing Chairpersons, Franchise Managers,
 * and Franchise Employees with strict role-based access and tenant isolation.
 *
 * Hierarchy:
 *  glamlux_super_admin / administrator
 *    └── glamlux_chairperson      (manages franchise managers + employees across their franchises)
 *          └── glamlux_franchise_manager  (manages employees within their franchise)
 *                └── glamlux_franchise_employee
 *
 * Endpoints:
 *   GET    /glamlux/v1/users              → List users (scoped by caller's role)
 *   POST   /glamlux/v1/users              → Create a user
 *   GET    /glamlux/v1/users/{id}         → Get a single user
 *   PUT    /glamlux/v1/users/{id}         → Update user details / role
 *   DELETE /glamlux/v1/users/{id}         → Deactivate (block) user
 *
 * @package GlamLux
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

class GlamLux_User_Management_Controller extends GlamLux_Base_Controller
{
    /**
     * Roles that can be assigned/managed via this API.
     * Ordered from highest to lowest privilege.
     */
    const MANAGED_ROLES = [
        'glamlux_chairperson',
        'glamlux_franchise_manager',
        'glamlux_franchise_employee',
        'glamlux_staff',
        'glamlux_franchise_admin',
        'glamlux_salon_manager',
        'glamlux_state_manager',
    ];

    /**
     * Maximum roles a Chairperson can assign (cannot escalate to their own level or above).
     */
    const CHAIRPERSON_ASSIGNABLE_ROLES = [
        'glamlux_franchise_manager',
        'glamlux_franchise_employee',
        'glamlux_staff',
    ];

    /**
     * Roles a Franchise Manager can assign.
     */
    const FRANCHISE_MANAGER_ASSIGNABLE_ROLES = [
        'glamlux_franchise_employee',
        'glamlux_staff',
    ];

    public function register_routes(): void
    {
        // Collection
        register_rest_route('glamlux/v1', '/users', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'list_users'],
                'permission_callback' => [$this, 'can_manage_users'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_user'],
                'permission_callback' => [$this, 'can_manage_users'],
                'args'                => [
                    'username'   => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_user'],
                    'email'      => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_email'],
                    'password'   => ['type' => 'string', 'required' => true],
                    'first_name' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'last_name'  => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'role'       => ['type' => 'string', 'required' => true],
                    'franchise_id' => ['type' => 'integer'],
                    'salon_id'   => ['type' => 'integer'],
                ],
            ],
        ]);

        // Single user
        register_rest_route('glamlux/v1', '/users/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_user'],
                'permission_callback' => [$this, 'can_manage_users'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_user'],
                'permission_callback' => [$this, 'can_manage_users'],
                'args'                => [
                    'email'      => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                    'first_name' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'last_name'  => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'role'       => ['type' => 'string'],
                    'franchise_id' => ['type' => 'integer'],
                    'salon_id'   => ['type' => 'integer'],
                ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'deactivate_user'],
                'permission_callback' => [$this, 'can_manage_users'],
            ],
        ]);

        // Roles reference endpoint — returns roles the caller can assign
        register_rest_route('glamlux/v1', '/users/assignable-roles', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_assignable_roles'],
            'permission_callback' => [$this, 'can_manage_users'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Permission Callbacks
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Allow Super Admin, Chairperson, and Franchise Manager to manage users.
     */
    public function can_manage_users($request): bool|WP_Error
    {
        $result = $this->require_franchise_manager();
        if (is_wp_error($result)) {
            return $result;
        }
        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Endpoints
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * List users scoped by the caller's role:
     * - Super Admin / Platform Admin → all franchise users
     * - Chairperson → users in their franchises
     * - Franchise Manager → employees in their franchise
     */
    public function list_users(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $role_filter    = sanitize_text_field($request->get_param('role') ?? '');
        $franchise_id   = absint($request->get_param('franchise_id') ?? 0);
        $caller         = wp_get_current_user();

        $query_args = [
            'role__in' => self::MANAGED_ROLES,
            'number'   => 100,
            'fields'   => 'all',
        ];

        if ($role_filter && in_array($role_filter, self::MANAGED_ROLES, true)) {
            $query_args['role__in'] = [$role_filter];
        }

        // Tenant scoping
        if (!current_user_can('manage_options') && !current_user_can('manage_glamlux_platform')) {
            $caller_franchise = (int) get_user_meta($caller->ID, 'glamlux_managed_franchise_id', true);

            if (current_user_can('manage_glamlux_franchise_managers')) {
                // Chairperson: scope to their franchise(s)
                if ($caller_franchise) {
                    $query_args['meta_query'] = [[
                        'key'   => 'glamlux_managed_franchise_id',
                        'value' => $caller_franchise,
                    ]];
                }
            } elseif (current_user_can('manage_glamlux_franchise_employees')) {
                // Franchise Manager: scope to employees only in their franchise
                $query_args['role__in'] = self::FRANCHISE_MANAGER_ASSIGNABLE_ROLES;
                if ($caller_franchise) {
                    $query_args['meta_query'] = [[
                        'key'   => 'glamlux_managed_franchise_id',
                        'value' => $caller_franchise,
                    ]];
                }
            }
        } elseif ($franchise_id) {
            $query_args['meta_query'] = [[
                'key'   => 'glamlux_managed_franchise_id',
                'value' => $franchise_id,
            ]];
        }

        $users = get_users($query_args);
        $data  = array_map([$this, 'format_user'], $users);

        return rest_ensure_response(['users' => $data, 'count' => count($data)]);
    }

    /**
     * Get a single user (with tenant isolation).
     */
    public function get_user(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id   = (int) $request->get_param('id');
        $user = get_userdata($id);

        if (!$user) {
            return new WP_Error('not_found', 'User not found.', ['status' => 404]);
        }

        $error = $this->assert_can_access_user($user);
        if (is_wp_error($error)) {
            return $error;
        }

        return rest_ensure_response($this->format_user($user));
    }

    /**
     * Create a new franchise user.
     */
    public function create_user(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $role = sanitize_text_field($request->get_param('role'));

        $error = $this->assert_can_assign_role($role);
        if (is_wp_error($error)) {
            return $error;
        }

        $email = sanitize_email($request->get_param('email'));
        if (!is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email address.', ['status' => 400]);
        }

        if (email_exists($email)) {
            return new WP_Error('email_exists', 'This email is already registered.', ['status' => 409]);
        }

        $username = sanitize_user($request->get_param('username'));
        if (username_exists($username)) {
            return new WP_Error('username_exists', 'This username is already taken.', ['status' => 409]);
        }

        // Password strength is enforced here; REST API will pass the raw string
        $password = $request->get_param('password');
        if (empty($password) || strlen($password) < 8) {
            return new WP_Error('weak_password', 'Password must be at least 8 characters.', ['status' => 400]);
        }

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => $password,
            'first_name' => sanitize_text_field($request->get_param('first_name') ?? ''),
            'last_name'  => sanitize_text_field($request->get_param('last_name') ?? ''),
            'role'       => $role,
        ]);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Store franchise/salon association
        $franchise_id = absint($request->get_param('franchise_id') ?? 0);
        $salon_id     = absint($request->get_param('salon_id') ?? 0);

        if (!$franchise_id && !current_user_can('manage_options')) {
            // Auto-assign caller's franchise for Chairperson / Franchise Manager
            $franchise_id = (int) get_user_meta(get_current_user_id(), 'glamlux_managed_franchise_id', true);
        }

        if ($franchise_id) {
            update_user_meta($user_id, 'glamlux_managed_franchise_id', $franchise_id);
        }
        if ($salon_id) {
            update_user_meta($user_id, 'glamlux_managed_salon_id', $salon_id);
        }

        glamlux_log_error('User created via API', [
            'new_user_id' => $user_id,
            'role'        => $role,
            'created_by'  => get_current_user_id(),
        ]);

        return rest_ensure_response([
            'success' => true,
            'user_id' => $user_id,
            'message' => 'User created successfully.',
        ]);
    }

    /**
     * Update an existing user's details or role.
     */
    public function update_user(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id   = (int) $request->get_param('id');
        $user = get_userdata($id);

        if (!$user) {
            return new WP_Error('not_found', 'User not found.', ['status' => 404]);
        }

        $error = $this->assert_can_access_user($user);
        if (is_wp_error($error)) {
            return $error;
        }

        $update_args = ['ID' => $id];

        $email = $request->get_param('email');
        if ($email !== null) {
            $email = sanitize_email($email);
            if (!is_email($email)) {
                return new WP_Error('invalid_email', 'Invalid email address.', ['status' => 400]);
            }
            $update_args['user_email'] = $email;
        }

        $first_name = $request->get_param('first_name');
        if ($first_name !== null) {
            $update_args['first_name'] = sanitize_text_field($first_name);
        }

        $last_name = $request->get_param('last_name');
        if ($last_name !== null) {
            $update_args['last_name'] = sanitize_text_field($last_name);
        }

        $result = wp_update_user($update_args);
        if (is_wp_error($result)) {
            return $result;
        }

        // Role change
        $new_role = $request->get_param('role');
        if ($new_role !== null) {
            $new_role = sanitize_text_field($new_role);
            $role_error = $this->assert_can_assign_role($new_role);
            if (is_wp_error($role_error)) {
                return $role_error;
            }
            $wp_user = new WP_User($id);
            $wp_user->set_role($new_role);
        }

        // Franchise / salon association
        $franchise_id = $request->get_param('franchise_id');
        if ($franchise_id !== null) {
            update_user_meta($id, 'glamlux_managed_franchise_id', absint($franchise_id));
        }
        $salon_id = $request->get_param('salon_id');
        if ($salon_id !== null) {
            update_user_meta($id, 'glamlux_managed_salon_id', absint($salon_id));
        }

        return rest_ensure_response(['success' => true, 'message' => 'User updated successfully.']);
    }

    /**
     * Deactivate (block) a user within the caller's scope.
     */
    public function deactivate_user(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id   = (int) $request->get_param('id');
        $user = get_userdata($id);

        if (!$user) {
            return new WP_Error('not_found', 'User not found.', ['status' => 404]);
        }

        // Prevent self-deactivation
        if ($id === get_current_user_id()) {
            return new WP_Error('self_deactivation', 'You cannot deactivate your own account.', ['status' => 403]);
        }

        $error = $this->assert_can_access_user($user);
        if (is_wp_error($error)) {
            return $error;
        }

        // Block the user by setting the user_status flag via user_meta
        update_user_meta($id, 'glamlux_user_deactivated', 1);
        update_user_meta($id, 'glamlux_user_deactivated_at', current_time('mysql'));
        update_user_meta($id, 'glamlux_user_deactivated_by', get_current_user_id());

        glamlux_log_error('User deactivated via API', [
            'target_user_id' => $id,
            'deactivated_by' => get_current_user_id(),
        ]);

        return rest_ensure_response(['success' => true, 'message' => 'User deactivated successfully.']);
    }

    /**
     * Return the list of roles the current user is allowed to assign.
     */
    public function get_assignable_roles(WP_REST_Request $request): WP_REST_Response
    {
        $roles = $this->get_caller_assignable_roles();
        $role_labels = [];
        global $wp_roles;
        foreach ($roles as $slug) {
            $role_labels[] = [
                'slug'  => $slug,
                'label' => isset($wp_roles->roles[$slug]) ? translate_user_role($wp_roles->roles[$slug]['name']) : $slug,
            ];
        }
        return rest_ensure_response(['roles' => $role_labels]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Format a WP_User object for API output (no passwords, sensitive data removed).
     */
    private function format_user(WP_User $user): array
    {
        return [
            'id'           => $user->ID,
            'username'     => $user->user_login,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'roles'        => $user->roles,
            'franchise_id' => (int) get_user_meta($user->ID, 'glamlux_managed_franchise_id', true),
            'salon_id'     => (int) get_user_meta($user->ID, 'glamlux_managed_salon_id', true),
            'is_active'    => !(bool) get_user_meta($user->ID, 'glamlux_user_deactivated', true),
            'registered'   => $user->user_registered,
        ];
    }

    /**
     * Assert that the current caller can access or modify the given target user.
     * Enforces tenant isolation.
     */
    private function assert_can_access_user(WP_User $target): true|WP_Error
    {
        if (current_user_can('manage_options') || current_user_can('manage_glamlux_platform')) {
            return true; // Super admins can access any user
        }

        $caller_id         = get_current_user_id();
        $caller_franchise  = (int) get_user_meta($caller_id, 'glamlux_managed_franchise_id', true);
        $target_franchise  = (int) get_user_meta($target->ID, 'glamlux_managed_franchise_id', true);

        // Chairperson can access users in their franchise(s)
        if (current_user_can('manage_glamlux_franchise_managers')) {
            if ($caller_franchise && $target_franchise === $caller_franchise) {
                return true;
            }
            return new WP_Error('glamlux_forbidden', 'Access denied: user is not in your franchise.', ['status' => 403]);
        }

        // Franchise Manager can only access Franchise Employees in their franchise
        if (current_user_can('manage_glamlux_franchise_employees')) {
            $allowed_target_roles = self::FRANCHISE_MANAGER_ASSIGNABLE_ROLES;
            $target_roles = (array) $target->roles;
            $role_ok = !empty(array_intersect($target_roles, $allowed_target_roles));
            $franchise_ok = $caller_franchise && $target_franchise === $caller_franchise;

            if ($role_ok && $franchise_ok) {
                return true;
            }
            return new WP_Error('glamlux_forbidden', 'Access denied: insufficient permission for this user.', ['status' => 403]);
        }

        return new WP_Error('glamlux_forbidden', 'Access denied.', ['status' => 403]);
    }

    /**
     * Assert that the caller is permitted to assign the given role to a user.
     */
    private function assert_can_assign_role(string $role): true|WP_Error
    {
        $assignable = $this->get_caller_assignable_roles();
        if (!in_array($role, $assignable, true)) {
            return new WP_Error(
                'glamlux_forbidden',
                sprintf('You are not permitted to assign the "%s" role.', esc_html($role)),
                ['status' => 403]
            );
        }
        return true;
    }

    /**
     * Get roles that the current caller is allowed to assign.
     */
    private function get_caller_assignable_roles(): array
    {
        if (current_user_can('manage_options') || current_user_can('manage_glamlux_platform')) {
            return self::MANAGED_ROLES;
        }
        if (current_user_can('manage_glamlux_franchise_managers')) {
            return self::CHAIRPERSON_ASSIGNABLE_ROLES;
        }
        if (current_user_can('manage_glamlux_franchise_employees')) {
            return self::FRANCHISE_MANAGER_ASSIGNABLE_ROLES;
        }
        return [];
    }
}
