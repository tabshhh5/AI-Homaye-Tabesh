<?php
/**
 * User Role Resolver - Multi-Role Intelligence
 *
 * @package HomayeTabesh
 * @since PR15
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * کلاس تشخیص نقش کاربر و تولید توکن امنیتی
 * Multi-Role Intelligence System
 */
class HT_User_Role_Resolver
{
    /**
     * Role types supported by Homa
     */
    private const ROLE_ADMIN = 'admin';
    private const ROLE_CUSTOMER = 'customer';
    private const ROLE_GUEST = 'guest';
    private const ROLE_INTRUDER = 'intruder';

    /**
     * Intruder Pattern Matcher instance
     */
    private ?HT_Intruder_Pattern_Matcher $pattern_matcher = null;

    /**
     * Session key for storing role context
     */
    private const SESSION_KEY = 'homa_user_role_context';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->pattern_matcher = new HT_Intruder_Pattern_Matcher();
    }

    /**
     * Get user context for Homa
     * تشخیص نقش کاربر و اطلاعات مرتبط
     *
     * @return array User context array
     */
    public function get_homa_user_context(): array
    {
        // Check if user is logged in
        if (is_user_logged_in()) {
            return $this->get_logged_in_user_context();
        }

        // Check for suspicious behavior (intruder detection)
        if ($this->pattern_matcher->is_suspicious_behavior()) {
            return $this->get_intruder_context();
        }

        // Return guest context
        return $this->get_guest_context();
    }

    /**
     * Get context for logged-in users
     *
     * @return array User context
     */
    private function get_logged_in_user_context(): array
    {
        $user = wp_get_current_user();
        
        // Determine primary role
        $role = $this->determine_primary_role($user);
        
        // Get user capabilities
        $capabilities = $this->get_relevant_capabilities($user);

        return [
            'role' => $role,
            'identity' => $user->display_name,
            'user_id' => $user->ID,
            'user_email' => $user->user_email,
            'capabilities' => $capabilities,
            'security_token' => $this->generate_security_token($user->ID, $role),
            'is_authenticated' => true,
        ];
    }

    /**
     * Get context for guest users
     *
     * @return array Guest context
     */
    private function get_guest_context(): array
    {
        return [
            'role' => self::ROLE_GUEST,
            'identity' => 'بازدیدکننده',
            'user_id' => 0,
            'user_email' => '',
            'capabilities' => ['view_content', 'use_chat', 'register'],
            'security_token' => $this->generate_guest_token(),
            'is_authenticated' => false,
        ];
    }

    /**
     * Get context for intruder/suspicious users
     *
     * @return array Intruder context
     */
    private function get_intruder_context(): array
    {
        // Log security event
        $this->log_intruder_attempt();

        return [
            'role' => self::ROLE_INTRUDER,
            'identity' => 'عامل ناشناس',
            'user_id' => 0,
            'user_email' => '',
            'capabilities' => ['view_warning'], // Very limited access
            'security_token' => null,
            'is_authenticated' => false,
            'blocked' => true,
        ];
    }

    /**
     * Determine primary role from WordPress user
     *
     * @param \WP_User $user WordPress user object
     * @return string Primary role
     */
    private function determine_primary_role(\WP_User $user): string
    {
        // Check if user is administrator
        if (in_array('administrator', $user->roles, true)) {
            return self::ROLE_ADMIN;
        }

        // Check if user is shop manager (WooCommerce)
        if (in_array('shop_manager', $user->roles, true)) {
            return self::ROLE_ADMIN;
        }

        // Check if user has customer role or has made purchases
        if (in_array('customer', $user->roles, true)) {
            return self::ROLE_CUSTOMER;
        }

        // Check if user has any orders (WooCommerce)
        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders([
                'customer_id' => $user->ID,
                'limit' => 1,
            ]);
            if (!empty($orders)) {
                return self::ROLE_CUSTOMER;
            }
        }

        // Default to customer for logged-in users
        return self::ROLE_CUSTOMER;
    }

    /**
     * Get relevant capabilities for the user
     *
     * @param \WP_User $user WordPress user object
     * @return array List of Homa capabilities
     */
    private function get_relevant_capabilities(\WP_User $user): array
    {
        $role = $this->determine_primary_role($user);
        
        if ($role === self::ROLE_ADMIN) {
            return [
                'view_analytics',
                'view_sales_data',
                'manage_interventions',
                'view_user_list',
                'access_atlas',
                'manage_settings',
                'view_security_alerts',
                'use_advanced_chat',
            ];
        }

        if ($role === self::ROLE_CUSTOMER) {
            return [
                'view_orders',
                'track_shipments',
                'renew_invoices',
                'create_tickets',
                'use_chat',
                'view_account',
            ];
        }

        return ['use_chat'];
    }

    /**
     * Generate security token for authenticated users
     *
     * @param int $user_id User ID
     * @param string $role User role
     * @return string Security token
     */
    private function generate_security_token(int $user_id, string $role): string
    {
        $data = $user_id . '|' . $role . '|' . time();
        return hash_hmac('sha256', $data, wp_salt('auth'));
    }

    /**
     * Generate token for guest users
     *
     * @return string Guest token
     */
    private function generate_guest_token(): string
    {
        $session_id = $this->get_or_create_session_id();
        return hash_hmac('sha256', $session_id, wp_salt('nonce'));
    }

    /**
     * Get or create session ID for guest users
     *
     * @return string Session ID
     */
    private function get_or_create_session_id(): string
    {
        if (isset($_COOKIE['ht_session_id'])) {
            return sanitize_text_field($_COOKIE['ht_session_id']);
        }

        $session_id = wp_generate_password(32, false);
        setcookie('ht_session_id', $session_id, time() + (86400 * 30), '/');
        return $session_id;
    }

    /**
     * Log intruder attempt
     *
     * @return void
     */
    private function log_intruder_attempt(): void
    {
        $alert_service = new HT_Admin_Security_Alerts();
        
        $alert_service->log_security_event([
            'event_type' => 'intruder_detected',
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'detection_reason' => $this->pattern_matcher->get_last_detection_reason(),
            'timestamp' => current_time('mysql'),
        ]);
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip(): string
    {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field(trim($ip));
    }

    /**
     * Check if user has specific capability
     *
     * @param string $capability Capability to check
     * @param array|null $context Optional user context
     * @return bool Has capability
     */
    public function user_has_capability(string $capability, ?array $context = null): bool
    {
        $context = $context ?? $this->get_homa_user_context();
        return in_array($capability, $context['capabilities'] ?? [], true);
    }

    /**
     * Get role label in Farsi
     *
     * @param string $role Role identifier
     * @return string Farsi label
     */
    public function get_role_label(string $role): string
    {
        $labels = [
            self::ROLE_ADMIN => 'مدیر',
            self::ROLE_CUSTOMER => 'مشتری',
            self::ROLE_GUEST => 'میهمان',
            self::ROLE_INTRUDER => 'مهاجم',
        ];

        return $labels[$role] ?? 'نامشخص';
    }
}
