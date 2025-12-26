<?php
/**
 * Post-Purchase REST API
 *
 * @package HomayeTabesh
 * @since PR12
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * REST API برای قابلیت‌های پس از خرید
 * 
 * این کلاس endpoints مربوط به رهگیری سفارش، تیکتینگ و بازگشت مشتری را فراهم می‌کند.
 */
class HT_PostPurchase_REST_API
{
    /**
     * Register REST API endpoints
     * 
     * @return void
     */
    public function register_endpoints(): void
    {
        // Track Order
        register_rest_route('homaye-tabesh/v1', '/order/track', [
            'methods' => 'POST',
            'callback' => [$this, 'track_order'],
            'permission_callback' => '__return_true',
        ]);

        // Track Shipping
        register_rest_route('homaye-tabesh/v1', '/shipping/track', [
            'methods' => 'POST',
            'callback' => [$this, 'track_shipping'],
            'permission_callback' => '__return_true',
        ]);

        // Create Support Ticket
        register_rest_route('homaye-tabesh/v1', '/support/ticket', [
            'methods' => 'POST',
            'callback' => [$this, 'create_ticket'],
            'permission_callback' => '__return_true',
        ]);

        // Get User Tickets
        register_rest_route('homaye-tabesh/v1', '/support/tickets', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_tickets'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // Retention Analytics
        register_rest_route('homaye-tabesh/v1', '/retention/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_retention_analytics'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Plugin Scanner
        register_rest_route('homaye-tabesh/v1', '/plugins/scan', [
            'methods' => 'GET',
            'callback' => [$this, 'scan_plugins'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Update Monitored Plugins
        register_rest_route('homaye-tabesh/v1', '/plugins/monitor', [
            'methods' => 'POST',
            'callback' => [$this, 'update_monitored_plugins'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Get Plugin Metadata
        register_rest_route('homaye-tabesh/v1', '/plugins/metadata', [
            'methods' => 'GET',
            'callback' => [$this, 'get_plugin_metadata'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
    }

    /**
     * Track order endpoint
     * 
     * @param \WP_REST_Request $request درخواست
     * @return \WP_REST_Response
     */
    public function track_order(\WP_REST_Request $request): \WP_REST_Response
    {
        $order_id = $request->get_param('order_id');
        $phone = $request->get_param('phone');

        $tracker = new HT_Order_Tracker();

        // رهگیری با شماره سفارش
        if ($order_id) {
            $result = $tracker->get_order_status((int) $order_id);
        }
        // رهگیری با شماره تلفن
        elseif ($phone) {
            $result = $tracker->get_orders_by_phone($phone);
        } else {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'شماره سفارش یا تلفن الزامی است.',
            ], 400);
        }

        return new \WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Track shipping endpoint
     * 
     * @param \WP_REST_Request $request درخواست
     * @return \WP_REST_Response
     */
    public function track_shipping(\WP_REST_Request $request): \WP_REST_Response
    {
        $tracking_code = $request->get_param('tracking_code');
        $service = $request->get_param('service') ?? 'post';

        if (empty($tracking_code)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'کد رهگیری الزامی است.',
            ], 400);
        }

        $bridge = new HT_Shipping_API_Bridge();
        $result = $bridge->get_tracking_status($tracking_code, $service);

        return new \WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Create support ticket endpoint
     * 
     * @param \WP_REST_Request $request درخواست
     * @return \WP_REST_Response
     */
    public function create_ticket(\WP_REST_Request $request): \WP_REST_Response
    {
        $message = $request->get_param('message');
        $user_id = get_current_user_id();
        $context = $request->get_param('context') ?? [];

        if (empty($message)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'متن پیام الزامی است.',
            ], 400);
        }

        $ticketing = new HT_Support_Ticketing();
        $result = $ticketing->create_ticket_from_conversation([
            'user_id' => $user_id,
            'message' => $message,
            'context' => $context,
        ]);

        return new \WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get user tickets endpoint
     * 
     * @param \WP_REST_Request $request درخواست
     * @return \WP_REST_Response
     */
    public function get_user_tickets(\WP_REST_Request $request): \WP_REST_Response
    {
        $user_id = get_current_user_id();

        if ($user_id === 0) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'احراز هویت الزامی است.',
            ], 401);
        }

        $ticketing = new HT_Support_Ticketing();
        $tickets = $ticketing->get_user_tickets($user_id);

        return new \WP_REST_Response([
            'success' => true,
            'tickets' => $tickets,
        ], 200);
    }

    /**
     * Get retention analytics endpoint
     * 
     * @param \WP_REST_Request $request درخواست
     * @return \WP_REST_Response
     */
    public function get_retention_analytics(\WP_REST_Request $request): \WP_REST_Response
    {
        $engine = new HT_Retention_Engine();
        $analytics = $engine->get_retention_analytics();

        return new \WP_REST_Response($analytics, 200);
    }

    /**
     * Scan plugins endpoint
     * 
     * @param \WP_REST_Request $request درخواست
     * @return \WP_REST_Response
     */
    public function scan_plugins(\WP_REST_Request $request): \WP_REST_Response
    {
        $scanner = new HT_Plugin_Scanner();
        $plugins = $scanner->get_installed_plugins();

        return new \WP_REST_Response([
            'success' => true,
            'plugins' => $plugins,
        ], 200);
    }

    /**
     * Update monitored plugins endpoint
     * 
     * @param \WP_REST_Request $request درخواست
     * @return \WP_REST_Response
     */
    public function update_monitored_plugins(\WP_REST_Request $request): \WP_REST_Response
    {
        $action = $request->get_param('action'); // 'add' or 'remove'
        $plugin_path = $request->get_param('plugin_path');

        if (empty($action) || empty($plugin_path)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'پارامترهای الزامی مشخص نشده‌اند.',
            ], 400);
        }

        $scanner = new HT_Plugin_Scanner();

        if ($action === 'add') {
            $result = $scanner->add_monitored_plugin($plugin_path);
        } elseif ($action === 'remove') {
            $result = $scanner->remove_monitored_plugin($plugin_path);
        } else {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'عملیات نامعتبر است.',
            ], 400);
        }

        // Refresh metadata after change
        $metadata_engine = new HT_Metadata_Mining_Engine();
        $metadata_engine->refresh_metadata();

        return new \WP_REST_Response([
            'success' => $result,
            'message' => $result ? 'عملیات موفق بود.' : 'خطا در انجام عملیات.',
        ], $result ? 200 : 500);
    }

    /**
     * Get plugin metadata endpoint
     * 
     * @param \WP_REST_Request $request درخواست
     * @return \WP_REST_Response
     */
    public function get_plugin_metadata(\WP_REST_Request $request): \WP_REST_Response
    {
        $force_refresh = $request->get_param('refresh') === 'true';

        $metadata_engine = new HT_Metadata_Mining_Engine();

        if ($force_refresh) {
            $metadata = $metadata_engine->refresh_metadata();
        } else {
            $metadata = $metadata_engine->get_metadata_for_ai();
        }

        return new \WP_REST_Response([
            'success' => true,
            'metadata' => $metadata,
            'knowledge_base' => $metadata_engine->generate_knowledge_base($metadata),
        ], 200);
    }

    /**
     * Check admin permission
     * 
     * @return bool
     */
    public function check_admin_permission(): bool
    {
        return current_user_can('manage_options');
    }
}
