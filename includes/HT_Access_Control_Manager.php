<?php
/**
 * Access Control Manager - Internal Team Management
 *
 * @package HomayeTabesh
 * @since PR16
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * مدیریت سطوح دسترسی تیم داخلی
 * Manages access control for internal team members
 */
class HT_Access_Control_Manager
{
    /**
     * Option key for authorized roles
     */
    private const OPTION_AUTHORIZED_ROLES = 'homa_authorized_roles';

    /**
     * Option key for authorized users
     */
    private const OPTION_AUTHORIZED_USERS = 'homa_authorized_users';

    /**
     * Default authorized roles
     */
    private const DEFAULT_ROLES = ['administrator', 'shop_manager'];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_endpoints']);

        // Register admin settings
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Register REST API endpoints
     *
     * @return void
     */
    public function register_endpoints(): void
    {
        // Get authorized roles
        register_rest_route('homaye/v1', '/access-control/roles', [
            'methods' => 'GET',
            'callback' => [$this, 'get_authorized_roles'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Update authorized roles
        register_rest_route('homaye/v1', '/access-control/roles', [
            'methods' => 'POST',
            'callback' => [$this, 'update_authorized_roles'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Search users
        register_rest_route('homaye/v1', '/access-control/users/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_users'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Get authorized users
        register_rest_route('homaye/v1', '/access-control/users', [
            'methods' => 'GET',
            'callback' => [$this, 'get_authorized_users'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Add authorized user
        register_rest_route('homaye/v1', '/access-control/users', [
            'methods' => 'POST',
            'callback' => [$this, 'add_authorized_user'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Remove authorized user
        register_rest_route('homaye/v1', '/access-control/users/(?P<user_id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'remove_authorized_user'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Register admin settings
     *
     * @return void
     */
    public function register_settings(): void
    {
        register_setting('homaye_tabesh_settings', self::OPTION_AUTHORIZED_ROLES, [
            'type' => 'array',
            'default' => self::DEFAULT_ROLES,
        ]);

        register_setting('homaye_tabesh_settings', self::OPTION_AUTHORIZED_USERS, [
            'type' => 'array',
            'default' => [],
        ]);
    }

    /**
     * Check if current user is internal team member
     *
     * @param int|null $user_id User ID (null = current user)
     * @return bool
     */
    public function is_internal_team_member(?int $user_id = null): bool
    {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        // Not logged in
        if ($user_id === 0) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Check if user role is authorized
        $authorized_roles = $this->get_authorized_roles_list();
        foreach ($user->roles as $role) {
            if (in_array($role, $authorized_roles, true)) {
                return true;
            }
        }

        // Check if user is individually authorized
        $authorized_users = $this->get_authorized_users_list();
        if (in_array($user_id, $authorized_users, true)) {
            return true;
        }

        return false;
    }

    /**
     * Get authorized roles list
     *
     * @return array
     */
    private function get_authorized_roles_list(): array
    {
        $roles = get_option(self::OPTION_AUTHORIZED_ROLES, self::DEFAULT_ROLES);
        return is_array($roles) ? $roles : self::DEFAULT_ROLES;
    }

    /**
     * Get authorized users list
     *
     * @return array
     */
    private function get_authorized_users_list(): array
    {
        $users = get_option(self::OPTION_AUTHORIZED_USERS, []);
        return is_array($users) ? array_map('intval', $users) : [];
    }

    /**
     * Get authorized roles (REST API)
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function get_authorized_roles(\WP_REST_Request $request): \WP_REST_Response
    {
        $authorized_roles = $this->get_authorized_roles_list();
        
        // Get all available roles
        $all_roles = wp_roles()->get_names();
        
        $roles_data = [];
        foreach ($all_roles as $role_key => $role_name) {
            $roles_data[] = [
                'key' => $role_key,
                'name' => translate_user_role($role_name),
                'authorized' => in_array($role_key, $authorized_roles, true),
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'roles' => $roles_data,
        ], 200);
    }

    /**
     * Update authorized roles (REST API)
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function update_authorized_roles(\WP_REST_Request $request): \WP_REST_Response
    {
        $roles = $request->get_param('roles');
        
        if (!is_array($roles)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid roles data',
            ], 400);
        }

        // Validate roles
        $all_roles = array_keys(wp_roles()->get_names());
        $valid_roles = array_intersect($roles, $all_roles);

        // Always include administrator
        if (!in_array('administrator', $valid_roles, true)) {
            $valid_roles[] = 'administrator';
        }

        update_option(self::OPTION_AUTHORIZED_ROLES, $valid_roles);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Authorized roles updated',
            'roles' => $valid_roles,
        ], 200);
    }

    /**
     * Search users (REST API)
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function search_users(\WP_REST_Request $request): \WP_REST_Response
    {
        $search = $request->get_param('search');
        
        if (empty($search) || strlen($search) < 2) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Search query too short',
            ], 400);
        }

        $users = get_users([
            'search' => '*' . $search . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number' => 20,
        ]);

        $users_data = [];
        foreach ($users as $user) {
            $users_data[] = [
                'id' => $user->ID,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'roles' => $user->roles,
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'users' => $users_data,
        ], 200);
    }

    /**
     * Get authorized users (REST API)
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function get_authorized_users(\WP_REST_Request $request): \WP_REST_Response
    {
        $user_ids = $this->get_authorized_users_list();
        
        $users_data = [];
        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);
            if ($user) {
                $users_data[] = [
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'display_name' => $user->display_name,
                    'email' => $user->user_email,
                    'roles' => $user->roles,
                ];
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'users' => $users_data,
        ], 200);
    }

    /**
     * Add authorized user (REST API)
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function add_authorized_user(\WP_REST_Request $request): \WP_REST_Response
    {
        $user_id = (int) $request->get_param('user_id');
        
        if ($user_id <= 0) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid user ID',
            ], 400);
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $authorized_users = $this->get_authorized_users_list();
        
        if (in_array($user_id, $authorized_users, true)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User already authorized',
            ], 400);
        }

        $authorized_users[] = $user_id;
        update_option(self::OPTION_AUTHORIZED_USERS, $authorized_users);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'User added to authorized list',
            'user' => [
                'id' => $user->ID,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
            ],
        ], 200);
    }

    /**
     * Remove authorized user (REST API)
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function remove_authorized_user(\WP_REST_Request $request): \WP_REST_Response
    {
        $user_id = (int) $request->get_param('user_id');
        
        if ($user_id <= 0) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid user ID',
            ], 400);
        }

        $authorized_users = $this->get_authorized_users_list();
        $key = array_search($user_id, $authorized_users, true);
        
        if ($key === false) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not in authorized list',
            ], 404);
        }

        unset($authorized_users[$key]);
        update_option(self::OPTION_AUTHORIZED_USERS, array_values($authorized_users));

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'User removed from authorized list',
        ], 200);
    }

    /**
     * Get capabilities for user based on role/authorization
     *
     * @param int|null $user_id User ID (null = current user)
     * @return array
     */
    public function get_user_capabilities(?int $user_id = null): array
    {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $is_internal = $this->is_internal_team_member($user_id);
        $is_admin = user_can($user_id, 'administrator');

        $capabilities = [
            'view_atlas' => $is_internal,
            'view_security_alerts' => $is_internal,
            'view_analytics' => $is_internal,
            'manage_users' => $is_admin,
            'manage_settings' => $is_admin,
            'export_data' => $is_internal,
            'view_system_info' => $is_internal,
            'access_advanced_chat' => $is_internal,
        ];

        return $capabilities;
    }

    /**
     * Filter chat capabilities based on user role
     *
     * @param array $capabilities Default capabilities
     * @param int   $user_id User ID
     * @return array Filtered capabilities
     */
    public function filter_chat_capabilities(array $capabilities, int $user_id): array
    {
        if (!$this->is_internal_team_member($user_id)) {
            // Remove internal tools for non-team members
            $internal_tools = [
                'atlas_shortcuts',
                'security_monitor',
                'user_management',
                'system_settings',
                'intervention',
                'export_data',
            ];

            foreach ($internal_tools as $tool) {
                if (isset($capabilities['tools']) && is_array($capabilities['tools'])) {
                    $key = array_search($tool, $capabilities['tools'], true);
                    if ($key !== false) {
                        unset($capabilities['tools'][$key]);
                    }
                }
            }
        }

        return $capabilities;
    }

    /**
     * Check if user can access feature
     *
     * @param string   $feature Feature name
     * @param int|null $user_id User ID (null = current user)
     * @return bool
     */
    public function can_access_feature(string $feature, ?int $user_id = null): bool
    {
        $capabilities = $this->get_user_capabilities($user_id);
        return $capabilities[$feature] ?? false;
    }
}
