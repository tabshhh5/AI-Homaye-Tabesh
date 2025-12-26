<?php
/**
 * Dynamic Chat Capabilities - Role-Based UI Control
 *
 * @package HomayeTabesh
 * @since PR15
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * کنترل دسترسی پویا برای رابط کاربری هما
 * Capability-based UI filtering
 */
class HT_Dynamic_Chat_Capabilities
{
    /**
     * User Role Resolver instance
     */
    private HT_User_Role_Resolver $role_resolver;

    /**
     * Capability definitions for each role
     */
    private const CAPABILITIES_MAP = [
        'admin' => [
            'tools' => ['analytics', 'sales_report', 'user_management', 'atlas_shortcuts', 'security_monitor'],
            'features' => ['advanced_chat', 'intervention', 'export_data', 'system_settings'],
            'ui_elements' => ['admin_dashboard', 'revenue_widget', 'user_list', 'security_alerts'],
        ],
        'customer' => [
            'tools' => ['order_tracker', 'invoice_renewal', 'shipping_tracker', 'support_ticket'],
            'features' => ['basic_chat', 'order_history', 'account_info'],
            'ui_elements' => ['order_status', 'tracking_button', 'invoice_actions', 'support_form'],
        ],
        'guest' => [
            'tools' => ['product_explorer', 'service_info', 'otp_registration'],
            'features' => ['basic_chat', 'lead_capture', 'guided_tour'],
            'ui_elements' => ['welcome_message', 'explore_widget', 'signup_prompt'],
        ],
        'intruder' => [
            'tools' => [],
            'features' => ['warning_display'],
            'ui_elements' => ['security_warning', 'blocked_message'],
        ],
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->role_resolver = new HT_User_Role_Resolver();
    }

    /**
     * Get available tools for current user
     *
     * @param array|null $user_context Optional user context
     * @return array Available tools
     */
    public function get_available_tools(?array $user_context = null): array
    {
        $context = $user_context ?? $this->role_resolver->get_homa_user_context();
        $role = $context['role'] ?? 'guest';

        $tools = self::CAPABILITIES_MAP[$role]['tools'] ?? [];
        
        return array_map(function($tool) {
            return [
                'id' => $tool,
                'label' => $this->get_tool_label($tool),
                'icon' => $this->get_tool_icon($tool),
            ];
        }, $tools);
    }

    /**
     * Get available features for current user
     *
     * @param array|null $user_context Optional user context
     * @return array Available features
     */
    public function get_available_features(?array $user_context = null): array
    {
        $context = $user_context ?? $this->role_resolver->get_homa_user_context();
        $role = $context['role'] ?? 'guest';

        return self::CAPABILITIES_MAP[$role]['features'] ?? [];
    }

    /**
     * Get available UI elements for current user
     *
     * @param array|null $user_context Optional user context
     * @return array Available UI elements
     */
    public function get_available_ui_elements(?array $user_context = null): array
    {
        $context = $user_context ?? $this->role_resolver->get_homa_user_context();
        $role = $context['role'] ?? 'guest';

        return self::CAPABILITIES_MAP[$role]['ui_elements'] ?? [];
    }

    /**
     * Check if user can access specific tool
     *
     * @param string $tool_id Tool identifier
     * @param array|null $user_context Optional user context
     * @return bool Can access
     */
    public function can_access_tool(string $tool_id, ?array $user_context = null): bool
    {
        $tools = $this->get_available_tools($user_context);
        
        foreach ($tools as $tool) {
            if ($tool['id'] === $tool_id) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if user can access specific feature
     *
     * @param string $feature Feature identifier
     * @param array|null $user_context Optional user context
     * @return bool Can access
     */
    public function can_access_feature(string $feature, ?array $user_context = null): bool
    {
        $features = $this->get_available_features($user_context);
        return in_array($feature, $features, true);
    }

    /**
     * Check if user can see specific UI element
     *
     * @param string $element Element identifier
     * @param array|null $user_context Optional user context
     * @return bool Can see
     */
    public function can_see_ui_element(string $element, ?array $user_context = null): bool
    {
        $elements = $this->get_available_ui_elements($user_context);
        return in_array($element, $elements, true);
    }

    /**
     * Get tool label in Farsi
     *
     * @param string $tool_id Tool identifier
     * @return string Farsi label
     */
    private function get_tool_label(string $tool_id): string
    {
        $labels = [
            'analytics' => 'تحلیل و آمار',
            'sales_report' => 'گزارش فروش',
            'user_management' => 'مدیریت کاربران',
            'atlas_shortcuts' => 'میانبرهای اطلس',
            'security_monitor' => 'مانیتور امنیتی',
            'order_tracker' => 'پیگیری سفارش',
            'invoice_renewal' => 'تمدید فاکتور',
            'shipping_tracker' => 'پیگیری مرسوله',
            'support_ticket' => 'تیکت پشتیبانی',
            'product_explorer' => 'کاوشگر محصولات',
            'service_info' => 'معرفی خدمات',
            'otp_registration' => 'ثبت‌نام سریع',
        ];

        return $labels[$tool_id] ?? ucfirst(str_replace('_', ' ', $tool_id));
    }

    /**
     * Get tool icon identifier
     *
     * @param string $tool_id Tool identifier
     * @return string Icon identifier
     */
    private function get_tool_icon(string $tool_id): string
    {
        $icons = [
            'analytics' => 'chart-bar',
            'sales_report' => 'currency-dollar',
            'user_management' => 'users',
            'atlas_shortcuts' => 'lightning-bolt',
            'security_monitor' => 'shield-check',
            'order_tracker' => 'truck',
            'invoice_renewal' => 'receipt-refund',
            'shipping_tracker' => 'location-marker',
            'support_ticket' => 'chat',
            'product_explorer' => 'search',
            'service_info' => 'information-circle',
            'otp_registration' => 'user-add',
        ];

        return $icons[$tool_id] ?? 'cube';
    }

    /**
     * Filter AI response based on user capabilities
     *
     * @param array $response AI response
     * @param array|null $user_context Optional user context
     * @return array Filtered response
     */
    public function filter_ai_response(array $response, ?array $user_context = null): array
    {
        $context = $user_context ?? $this->role_resolver->get_homa_user_context();
        
        // If user is intruder, return security warning
        if (($context['role'] ?? '') === 'intruder') {
            return [
                'success' => true,
                'response' => 'دسترسی شما به دلیل فعالیت‌های مشکوک محدود شده است. لطفاً با مدیر سایت تماس بگیرید.',
                'actions' => [],
                'blocked' => true,
            ];
        }

        // Filter actions based on capabilities
        if (isset($response['actions']) && is_array($response['actions'])) {
            $response['actions'] = array_filter($response['actions'], function($action) use ($context) {
                $required_capability = $action['required_capability'] ?? '';
                
                if (empty($required_capability)) {
                    return true; // No capability required
                }
                
                return in_array($required_capability, $context['capabilities'] ?? [], true);
            });
        }

        return $response;
    }

    /**
     * Get contextual welcome message based on role
     *
     * @param array|null $user_context Optional user context
     * @return string Welcome message
     */
    public function get_contextual_welcome_message(?array $user_context = null): string
    {
        $context = $user_context ?? $this->role_resolver->get_homa_user_context();
        $role = $context['role'] ?? 'guest';
        $identity = $context['identity'] ?? '';

        $messages = [
            'admin' => "سلام {$identity} عزیز! 👋\n\nبه پنل مدیریتی خوش آمدید. من می‌توانم گزارش‌های فروش، وضعیت سرور و لیست کاربران آنلاین را برای شما نمایش دهم.",
            'customer' => "سلام {$identity} عزیز! 👋\n\nخوشحالیم که دوباره می‌بینیمتون. می‌توانم وضعیت سفارش‌ها، پیگیری مرسوله یا تمدید فاکتورهای شما را بررسی کنم.",
            'guest' => "سلام! به چاپکو خوش آمدید 👋\n\nمن هما هستم، دستیار هوشمند شما. می‌توانم در انتخاب محصولات، محاسبه تیراژ و آشنایی با خدمات کمکتان کنم.",
            'intruder' => "⚠️ هشدار امنیتی\n\nدسترسی شما به دلیل فعالیت‌های مشکوک محدود شده است.",
        ];

        return $messages[$role] ?? $messages['guest'];
    }

    /**
     * Get suggested actions based on role
     *
     * @param array|null $user_context Optional user context
     * @return array Suggested actions
     */
    public function get_suggested_actions(?array $user_context = null): array
    {
        $context = $user_context ?? $this->role_resolver->get_homa_user_context();
        $role = $context['role'] ?? 'guest';

        $suggestions = [
            'admin' => [
                ['label' => 'نمایش آمار امروز', 'action' => 'show_daily_stats'],
                ['label' => 'لیست کاربران آنلاین', 'action' => 'show_online_users'],
                ['label' => 'هشدارهای امنیتی', 'action' => 'show_security_alerts'],
            ],
            'customer' => [
                ['label' => 'سفارش‌های من', 'action' => 'show_my_orders'],
                ['label' => 'پیگیری آخرین سفارش', 'action' => 'track_latest_order'],
                ['label' => 'ایجاد تیکت جدید', 'action' => 'create_ticket'],
            ],
            'guest' => [
                ['label' => 'معرفی خدمات چاپکو', 'action' => 'show_services'],
                ['label' => 'محاسبه تیراژ کتاب', 'action' => 'calculate_tirage'],
                ['label' => 'ثبت‌نام سریع', 'action' => 'start_registration'],
            ],
            'intruder' => [],
        ];

        return $suggestions[$role] ?? [];
    }

    /**
     * Register REST API endpoints
     *
     * @return void
     */
    public function register_endpoints(): void
    {
        register_rest_route('homaye-tabesh/v1', '/capabilities/tools', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_tools'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('homaye-tabesh/v1', '/capabilities/features', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_features'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('homaye-tabesh/v1', '/capabilities/context', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_user_context'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle get tools endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function handle_get_tools(\WP_REST_Request $request): \WP_REST_Response
    {
        $tools = $this->get_available_tools();
        
        return new \WP_REST_Response([
            'success' => true,
            'tools' => $tools,
        ], 200);
    }

    /**
     * Handle get features endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function handle_get_features(\WP_REST_Request $request): \WP_REST_Response
    {
        $features = $this->get_available_features();
        
        return new \WP_REST_Response([
            'success' => true,
            'features' => $features,
        ], 200);
    }

    /**
     * Handle get user context endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function handle_get_user_context(\WP_REST_Request $request): \WP_REST_Response
    {
        $context = $this->role_resolver->get_homa_user_context();
        $tools = $this->get_available_tools($context);
        $features = $this->get_available_features($context);
        $welcome_message = $this->get_contextual_welcome_message($context);
        $suggested_actions = $this->get_suggested_actions($context);
        
        return new \WP_REST_Response([
            'success' => true,
            'context' => $context,
            'tools' => $tools,
            'features' => $features,
            'welcome_message' => $welcome_message,
            'suggested_actions' => $suggested_actions,
        ], 200);
    }
}
