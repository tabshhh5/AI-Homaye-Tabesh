<?php
/**
 * Console Analytics API
 * 
 * REST API endpoints for Super Console analytics and data orchestration
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * Class HT_Console_Analytics_API
 * 
 * APIهای اختصاصی برای تغذیه نمودارهای سوپر پنل
 */
class HT_Console_Analytics_API
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void
    {
        // System status endpoint
        register_rest_route('homaye/v1/console', '/system/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_system_status'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Analytics endpoint
        register_rest_route('homaye/v1/console', '/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_analytics_data'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Users list endpoint
        register_rest_route('homaye/v1/console', '/users', [
            'methods' => 'GET',
            'callback' => [$this, 'get_users_list'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // User details endpoint
        register_rest_route('homaye/v1/console', '/users/(?P<id>[\\d\\w-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_details'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Diagnostics endpoint
        register_rest_route('homaye/v1/console', '/diagnostics', [
            'methods' => 'GET',
            'callback' => [$this, 'run_diagnostics'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Auto-fix endpoint
        register_rest_route('homaye/v1/console', '/diagnostics/fix', [
            'methods' => 'POST',
            'callback' => [$this, 'run_auto_fix'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Knowledge stats endpoint
        register_rest_route('homaye/v1/console', '/knowledge/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_knowledge_stats'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Knowledge facts list
        register_rest_route('homaye/v1/console', '/knowledge/facts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_knowledge_facts'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Update fact
        register_rest_route('homaye/v1/console', '/knowledge/facts/(?P<id>\\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_fact'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Delete fact
        register_rest_route('homaye/v1/console', '/knowledge/facts/(?P<id>\\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_fact'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Verify fact
        register_rest_route('homaye/v1/console', '/knowledge/facts/(?P<id>\\d+)/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'verify_fact'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Order Management Endpoints
        // List orders
        register_rest_route('homaye/v1/console', '/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'get_orders'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Create order
        register_rest_route('homaye/v1/console', '/orders', [
            'methods' => 'POST',
            'callback' => [$this, 'create_order'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Get single order
        register_rest_route('homaye/v1/console', '/orders/(?P<id>\\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_order'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Update order
        register_rest_route('homaye/v1/console', '/orders/(?P<id>\\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_order'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Delete order
        register_rest_route('homaye/v1/console', '/orders/(?P<id>\\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_order'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Settings endpoints
        register_rest_route('homaye/v1/console', '/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_admin_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_admin_permission']
            ]
        ]);
    }

    /**
     * Check if user has admin permission
     */
    public function check_admin_permission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Get system status
     */
    public function get_system_status(\WP_REST_Request $request): \WP_REST_Response
    {
        $diagnostics = new HT_System_Diagnostics();
        $status = $diagnostics->check_system_integrity();

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'overall_health' => $status['overall_health'],
                'last_check' => current_time('mysql')
            ]
        ]);
    }

    /**
     * Get analytics data
     */
    public function get_analytics_data(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        
        $range = $request->get_param('range') ?: '7days';
        $date_filter = $this->get_date_filter($range);

        // Token usage
        $token_usage = [
            'total' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(tokens_used) FROM {$wpdb->prefix}homaye_ai_requests 
                    WHERE created_at >= %s",
                    $date_filter
                )
            ) ?: 0,
            'by_section' => [
                'chat' => $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT SUM(tokens_used) FROM {$wpdb->prefix}homaye_ai_requests 
                        WHERE request_type = %s AND created_at >= %s",
                        'chat',
                        $date_filter
                    )
                ) ?: 0,
                'translation' => $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT SUM(tokens_used) FROM {$wpdb->prefix}homaye_ai_requests 
                        WHERE request_type = %s AND created_at >= %s",
                        'translation',
                        $date_filter
                    )
                ) ?: 0,
                'index' => $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT SUM(tokens_used) FROM {$wpdb->prefix}homaye_ai_requests 
                        WHERE request_type = %s AND created_at >= %s",
                        'indexing',
                        $date_filter
                    )
                ) ?: 0
            ]
        ];

        // Leads and conversion
        $total_leads = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}homaye_leads 
                WHERE created_at >= %s",
                $date_filter
            )
        ) ?: 0;

        $converted_leads = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}homaye_leads 
                WHERE status = %s AND created_at >= %s",
                'converted',
                $date_filter
            )
        ) ?: 0;

        $conversion_rate = $total_leads > 0 ? round(($converted_leads / $total_leads) * 100, 1) : 0;

        // Top interests
        $interests = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT topic, COUNT(*) as count 
                FROM {$wpdb->prefix}homaye_user_interests 
                WHERE created_at >= %s
                GROUP BY topic 
                ORDER BY count DESC 
                LIMIT 10",
                $date_filter
            ),
            ARRAY_A
        );

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'token_usage' => $token_usage,
                'leads' => [
                    'total' => (int) $total_leads,
                    'conversion_rate' => $conversion_rate
                ],
                'interests' => $interests
            ]
        ]);
    }

    /**
     * Get users list
     */
    public function get_users_list(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        
        $filter = $request->get_param('filter') ?: 'all';
        
        $query = "SELECT DISTINCT u.ID, u.display_name, u.user_email as email, 
                  COALESCE(100 - s.threat_score, 100) as security_score
                  FROM {$wpdb->users} u
                  LEFT JOIN {$wpdb->prefix}homaye_security_scores s ON u.ID = s.user_id";

        if ($filter === 'admins') {
            $query .= " INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
                       WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%administrator%'";
        } elseif ($filter === 'staff') {
            $query .= " INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
                       WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%editor%'";
        } elseif ($filter === 'suspicious') {
            $query .= " WHERE s.threat_score > 50";
        }

        $query .= " LIMIT 100";

        $users = $wpdb->get_results($query, ARRAY_A);

        return new \WP_REST_Response([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Get user details
     */
    public function get_user_details(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        
        $user_id = $request->get_param('id');
        
        $user = get_userdata($user_id);
        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'کاربر یافت نشد'
            ], 404);
        }

        // Get security score (threat_score inverted to security_score)
        $threat_score = $wpdb->get_var($wpdb->prepare(
            "SELECT threat_score FROM {$wpdb->prefix}homaye_security_scores WHERE user_id = %d",
            $user_id
        ));
        $security_score = $threat_score !== null ? (100 - (int)$threat_score) : 100;

        // Get conversation history
        $conversations = $wpdb->get_results($wpdb->prepare(
            "SELECT request_data as preview, created_at as date, request_type as type 
            FROM {$wpdb->prefix}homaye_ai_requests 
            WHERE user_id = %d 
            ORDER BY created_at DESC 
            LIMIT 20",
            $user_id
        ), ARRAY_A);

        // Get interests
        $interests = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT topic FROM {$wpdb->prefix}homaye_user_interests WHERE user_id = %d",
            $user_id
        ));

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'roles' => $user->roles,
                'security_score' => (int) $security_score,
                'conversations' => $conversations,
                'interests' => $interests,
                'conversation_count' => count($conversations),
                'last_active' => $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(created_at) FROM {$wpdb->prefix}homaye_ai_requests WHERE user_id = %d",
                    $user_id
                ))
            ]
        ]);
    }

    /**
     * Run system diagnostics
     */
    public function run_diagnostics(\WP_REST_Request $request): \WP_REST_Response
    {
        $diagnostics = new HT_System_Diagnostics();
        $results = $diagnostics->check_system_integrity();

        return new \WP_REST_Response([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Run auto-fix
     */
    public function run_auto_fix(\WP_REST_Request $request): \WP_REST_Response
    {
        $diagnostics = new HT_System_Diagnostics();
        $results = $diagnostics->auto_fix_issues();

        return new \WP_REST_Response([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Get knowledge stats
     */
    public function get_knowledge_stats(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $total_facts = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}homaye_knowledge_facts"
        ) ?: 0;

        $by_category = $wpdb->get_results(
            "SELECT category, COUNT(*) as count 
            FROM {$wpdb->prefix}homaye_knowledge_facts 
            GROUP BY category",
            ARRAY_A
        );

        $category_map = [];
        foreach ($by_category as $cat) {
            $category_map[$cat['category']] = (int) $cat['count'];
        }

        $pending_verification = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}homaye_knowledge_facts WHERE verified = 0"
        ) ?: 0;

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'total_facts' => (int) $total_facts,
                'by_category' => $category_map,
                'pending_verification' => (int) $pending_verification,
                'pages_indexed' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}homaye_indexed_pages"
                ),
                'plugins_monitored' => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}homaye_monitored_plugins WHERE is_monitored = 1"
                )
            ]
        ]);
    }

    /**
     * Get knowledge facts
     */
    public function get_knowledge_facts(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        
        $filter = $request->get_param('filter') ?: 'all';
        $search = $request->get_param('search') ?: '';

        $query = "SELECT * FROM {$wpdb->prefix}homaye_knowledge_facts WHERE 1=1";

        if ($filter === 'pending') {
            $query .= " AND verified = 0";
        } elseif ($filter === 'verified') {
            $query .= " AND verified = 1";
        }

        if (!empty($search)) {
            $search = $wpdb->esc_like($search);
            $query .= $wpdb->prepare(" AND (fact LIKE %s OR category LIKE %s)", 
                '%' . $search . '%', '%' . $search . '%');
        }

        $query .= " ORDER BY created_at DESC LIMIT 50";

        $facts = $wpdb->get_results($query, ARRAY_A);

        // Parse tags
        foreach ($facts as &$fact) {
            if (!empty($fact['tags'])) {
                $fact['tags'] = json_decode($fact['tags'], true) ?: [];
            } else {
                $fact['tags'] = [];
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $facts
        ]);
    }

    /**
     * Update fact
     */
    public function update_fact(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        
        $fact_id = $request->get_param('id');
        $body = $request->get_json_params();

        $updated = $wpdb->update(
            $wpdb->prefix . 'homaye_knowledge_facts',
            [
                'fact' => sanitize_text_field($body['fact']),
                'category' => sanitize_text_field($body['category']),
                'source' => sanitize_text_field($body['source'] ?? ''),
                'tags' => json_encode($body['tags'] ?? [])
            ],
            ['id' => $fact_id]
        );

        return new \WP_REST_Response([
            'success' => $updated !== false,
            'message' => $updated !== false ? 'فکت بروزرسانی شد' : 'خطا در بروزرسانی'
        ]);
    }

    /**
     * Delete fact
     */
    public function delete_fact(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        
        $fact_id = $request->get_param('id');

        $deleted = $wpdb->delete(
            $wpdb->prefix . 'homaye_knowledge_facts',
            ['id' => $fact_id]
        );

        return new \WP_REST_Response([
            'success' => $deleted !== false,
            'message' => $deleted !== false ? 'فکت حذف شد' : 'خطا در حذف'
        ]);
    }

    /**
     * Verify fact
     */
    public function verify_fact(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        
        $fact_id = $request->get_param('id');
        $body = $request->get_json_params();

        $updated = $wpdb->update(
            $wpdb->prefix . 'homaye_knowledge_facts',
            ['verified' => $body['verified'] ? 1 : 0],
            ['id' => $fact_id]
        );

        return new \WP_REST_Response([
            'success' => $updated !== false
        ]);
    }

    /**
     * Get settings
     */
    public function get_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        $settings = [
            'core' => [
                'ai_provider' => get_option('ht_ai_provider', 'gapgpt'),
                'model' => get_option('ht_ai_model', 'gemini-2.5-flash'),
                'gapgpt_api_key' => get_option('ht_gapgpt_api_key', ''),
                'gapgpt_base_url' => get_option('ht_gapgpt_base_url', 'https://api.gapgpt.app/v1'),
                'max_tokens' => (int) get_option('ht_max_tokens', 2048),
                'temperature' => (float) get_option('ht_temperature', 0.7)
            ],
            'visual' => [
                'primary_color' => get_option('ht_primary_color', '#667eea'),
                'chat_icon' => get_option('ht_chat_icon', 'default'),
                'scroll_speed' => (int) get_option('ht_scroll_speed', 300),
                'highlight_intensity' => (int) get_option('ht_highlight_intensity', 50)
            ],
            'database' => [
                'target_tables' => get_option('ht_target_tables', ['posts', 'pages']),
                'scan_interval' => (int) get_option('ht_scan_interval', 60),
                'excluded_categories' => get_option('ht_excluded_categories', [])
            ],
            'modules' => [
                'waf_enabled' => (bool) get_option('ht_waf_enabled', true),
                'otp_enabled' => (bool) get_option('ht_otp_enabled', true),
                'arabic_translation' => (bool) get_option('ht_translation_enabled', true),
                'order_tracking' => (bool) get_option('ht_order_tracking_enabled', true)
            ],
            'enabled_modules' => get_option('ht_enabled_modules', [
                'chat_widget',
                'behavior_tracking',
                'persona_engine',
                'knowledge_base',
                'security_center',
                'atlas_dashboard',
                'global_observer'
            ]),
            'otp' => [
                'melipayamak_username' => get_option('ht_melipayamak_username', ''),
                'melipayamak_password' => get_option('ht_melipayamak_password', ''),
                'melipayamak_from_number' => get_option('ht_melipayamak_from_number', ''),
                'otp_enabled' => (bool) get_option('ht_otp_enabled', true),
                'otp_expiry_minutes' => (int) get_option('ht_otp_expiry_minutes', 5)
            ],
            'localization' => [
                'locale' => get_option('ht_locale', 'fa_IR'),
                'rtl_enabled' => (bool) get_option('ht_rtl_enabled', true),
                'translation_enabled' => (bool) get_option('ht_translation_enabled', true)
            ],
            'firewall' => [
                'waf_enabled' => (bool) get_option('ht_waf_enabled', true),
                'waf_sensitivity' => get_option('ht_waf_sensitivity', 'medium'),
                'waf_whitelist_ips' => get_option('ht_waf_whitelist_ips', []),
                'waf_blacklist_ips' => get_option('ht_waf_blacklist_ips', []),
                'waf_rate_limit' => (int) get_option('ht_waf_rate_limit', 100)
            ],
            'messages' => [
                'welcome_lead' => get_option('ht_welcome_lead_message', ''),
                'firewall_warning' => get_option('ht_firewall_warning_message', ''),
                'otp_sms' => get_option('ht_otp_sms_template', '')
            ],
            'security' => [
                'sensitivity' => get_option('ht_waf_sensitivity', 'medium'),
                'block_threshold' => (int) get_option('ht_security_block_threshold', 30),
                'block_duration' => (int) get_option('ht_ip_block_duration', 24)
            ]
        ];

        return new \WP_REST_Response([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update settings
     */
    public function update_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_json_params();

        // Update core settings
        if (isset($body['core'])) {
            foreach ($body['core'] as $key => $value) {
                update_option('ht_' . $key, $value);
            }
        }

        // Update visual settings
        if (isset($body['visual'])) {
            foreach ($body['visual'] as $key => $value) {
                update_option('ht_' . $key, $value);
            }
        }

        // Update database settings
        if (isset($body['database'])) {
            foreach ($body['database'] as $key => $value) {
                update_option('ht_' . $key, $value);
            }
        }

        // Update modules
        if (isset($body['modules'])) {
            foreach ($body['modules'] as $key => $value) {
                update_option('ht_' . $key, $value);
            }
        }

        // Update messages
        if (isset($body['messages'])) {
            foreach ($body['messages'] as $key => $value) {
                update_option('ht_' . $key . '_message', $value);
            }
        }

        // Update security
        if (isset($body['security'])) {
            foreach ($body['security'] as $key => $value) {
                update_option('ht_' . $key, $value);
            }
        }

        // Update enabled_modules
        if (isset($body['enabled_modules'])) {
            update_option('ht_enabled_modules', $body['enabled_modules']);
        }

        // Update OTP settings
        if (isset($body['otp'])) {
            foreach ($body['otp'] as $key => $value) {
                update_option('ht_' . $key, $value);
            }
        }

        // Update localization
        if (isset($body['localization'])) {
            foreach ($body['localization'] as $key => $value) {
                update_option('ht_' . $key, $value);
            }
        }

        // Update firewall
        if (isset($body['firewall'])) {
            foreach ($body['firewall'] as $key => $value) {
                update_option('ht_' . $key, $value);
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'تنظیمات با موفقیت ذخیره شد'
        ]);
    }

    /**
     * Helper to get date filter
     */
    private function get_date_filter(string $range): string
    {
        switch ($range) {
            case '24hours':
                return date('Y-m-d H:i:s', strtotime('-24 hours'));
            case '7days':
                return date('Y-m-d H:i:s', strtotime('-7 days'));
            case '30days':
                return date('Y-m-d H:i:s', strtotime('-30 days'));
            default:
                return date('Y-m-d H:i:s', strtotime('-7 days'));
        }
    }

    /**
     * Get orders list
     */
    public function get_orders(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->is_woocommerce_active()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'ووکامرس فعال نیست'
            ], 400);
        }

        $filter = $request->get_param('filter') ?: 'all';
        
        $args = [
            'limit' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if ($filter !== 'all') {
            $args['status'] = $filter;
        }

        $orders = wc_get_orders($args);
        $orders_data = [];

        foreach ($orders as $order) {
            $orders_data[] = $this->format_order_for_list($order);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $orders_data
        ]);
    }

    /**
     * Get single order details
     */
    public function get_order(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->is_woocommerce_active()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'ووکامرس فعال نیست'
            ], 400);
        }

        $order_id = (int) $request['id'];
        $order = wc_get_order($order_id);

        if (!$order) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'سفارش پیدا نشد'
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $this->format_order_details($order)
        ]);
    }

    /**
     * Create new order
     */
    public function create_order(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->is_woocommerce_active()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'ووکامرس فعال نیست'
            ], 400);
        }

        try {
            $data = $request->get_json_params();
            
            // Validate required fields
            if (empty($data['customer_name'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'نام مشتری الزامی است'
                ], 400);
            }
            
            if (empty($data['customer_phone'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'شماره تماس الزامی است'
                ], 400);
            }
            
            // Create order
            $order = wc_create_order();
            
            // Set customer information
            $order->set_billing_first_name($data['customer_name'] ?? '');
            $order->set_billing_email($data['customer_email'] ?? '');
            $order->set_billing_phone($data['customer_phone'] ?? '');
            
            if (!empty($data['billing_address'])) {
                $order->set_billing_address_1($data['billing_address']);
            }
            
            if (!empty($data['shipping_address'])) {
                $order->set_shipping_address_1($data['shipping_address']);
            }
            
            // Add items
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    if (empty($item['product_name'])) continue;
                    
                    // Create a manual order item
                    $order_item = new \WC_Order_Item_Product();
                    $order_item->set_name($item['product_name']);
                    $order_item->set_quantity($item['quantity'] ?? 1);
                    $order_item->set_subtotal($item['price'] ?? 0);
                    $order_item->set_total(($item['price'] ?? 0) * ($item['quantity'] ?? 1));
                    $order->add_item($order_item);
                }
            }
            
            // Set order status
            $order->set_status($data['status'] ?? 'pending');
            
            // Set payment method
            $order->set_payment_method($data['payment_method'] ?? 'bank');
            
            // Set shipping method
            $order->set_shipping_method($data['shipping_method'] ?? 'standard');
            
            // Add notes
            if (!empty($data['notes'])) {
                $order->add_order_note($data['notes']);
            }
            
            // Calculate totals
            $order->calculate_totals();
            
            // Save order
            $order->save();
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'سفارش با موفقیت ثبت شد',
                'data' => [
                    'id' => $order->get_id(),
                    'order_number' => $order->get_order_number()
                ]
            ]);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'خطا در ثبت سفارش: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing order
     */
    public function update_order(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->is_woocommerce_active()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'ووکامرس فعال نیست'
            ], 400);
        }

        $order_id = (int) $request['id'];
        $order = wc_get_order($order_id);

        if (!$order) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'سفارش پیدا نشد'
            ], 404);
        }

        try {
            $data = $request->get_json_params();
            
            // Update customer information
            if (isset($data['customer_name'])) {
                $order->set_billing_first_name($data['customer_name']);
            }
            
            if (isset($data['customer_email'])) {
                $order->set_billing_email($data['customer_email']);
            }
            
            if (isset($data['customer_phone'])) {
                $order->set_billing_phone($data['customer_phone']);
            }
            
            if (isset($data['billing_address'])) {
                $order->set_billing_address_1($data['billing_address']);
            }
            
            if (isset($data['shipping_address'])) {
                $order->set_shipping_address_1($data['shipping_address']);
            }
            
            // Update status
            if (isset($data['status'])) {
                $order->set_status($data['status']);
            }
            
            // Update payment method
            if (isset($data['payment_method'])) {
                $order->set_payment_method($data['payment_method']);
            }
            
            // Add notes
            if (!empty($data['notes'])) {
                $order->add_order_note($data['notes']);
            }
            
            // Save changes
            $order->save();
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'سفارش با موفقیت بروزرسانی شد'
            ]);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'خطا در بروزرسانی سفارش: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete order
     */
    public function delete_order(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->is_woocommerce_active()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'ووکامرس فعال نیست'
            ], 400);
        }

        $order_id = (int) $request['id'];
        $order = wc_get_order($order_id);

        if (!$order) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'سفارش پیدا نشد'
            ], 404);
        }

        try {
            // Move to trash instead of permanent delete
            $order->delete(false);
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'سفارش به سطل زباله منتقل شد'
            ]);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'خطا در حذف سفارش: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format order for list view
     */
    private function format_order_for_list($order): array
    {
        return [
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'date_created' => $order->get_date_created()->date_i18n('Y/m/d H:i'),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'shipping_method' => $order->get_shipping_method(),
            'shipping_method_title' => $order->get_shipping_method()
        ];
    }

    /**
     * Format order details
     */
    private function format_order_details($order): array
    {
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = [
                'product_id' => $product ? $product->get_id() : 0,
                'product_name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $item->get_total() / $item->get_quantity(),
                'total' => $item->get_total()
            ];
        }

        return [
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'billing_address' => $order->get_billing_address_1(),
            'shipping_address' => $order->get_shipping_address_1(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'date_created' => $order->get_date_created()->date_i18n('Y/m/d H:i'),
            'date_modified' => $order->get_date_modified() ? $order->get_date_modified()->date_i18n('Y/m/d H:i') : null,
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'payment_status' => $order->is_paid() ? 'پرداخت شده' : 'پرداخت نشده',
            'shipping_method' => $order->get_shipping_method(),
            'shipping_method_title' => $order->get_shipping_method(),
            'tracking_code' => $order->get_meta('_shipping_tracking_number') ?: null,
            'items' => $items,
            'notes' => $this->get_order_notes($order)
        ];
    }

    /**
     * Get order notes
     */
    private function get_order_notes($order): string
    {
        $notes = wc_get_order_notes([
            'order_id' => $order->get_id(),
            'type' => 'customer'
        ]);

        $notes_text = [];
        foreach ($notes as $note) {
            $notes_text[] = $note->content;
        }

        return implode("\n", $notes_text);
    }

    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active(): bool
    {
        return class_exists('WooCommerce');
    }
}
