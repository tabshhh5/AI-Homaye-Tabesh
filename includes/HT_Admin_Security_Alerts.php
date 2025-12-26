<?php
/**
 * Admin Security Alerts - Intruder Notification System
 *
 * @package HomayeTabesh
 * @since PR15
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * سیستم اطلاع‌رسانی امنیتی به مدیر
 * Real-time security alerts and logging
 */
class HT_Admin_Security_Alerts
{
    /**
     * Security log table name
     */
    private const TABLE_NAME = 'homa_security_log';

    /**
     * Alert types
     */
    private const ALERT_INTRUDER = 'intruder_detected';
    private const ALERT_BRUTE_FORCE = 'brute_force_attempt';
    private const ALERT_SUSPICIOUS_ACTIVITY = 'suspicious_activity';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize database table if needed
        add_action('init', [$this, 'maybe_create_table']);
        
        // Register AJAX endpoints for admin
        add_action('wp_ajax_homa_get_security_alerts', [$this, 'ajax_get_security_alerts']);
        add_action('wp_ajax_homa_dismiss_security_alert', [$this, 'ajax_dismiss_alert']);
        add_action('wp_ajax_homa_get_alert_count', [$this, 'ajax_get_alert_count']);
    }

    /**
     * Create security log table if it doesn't exist
     *
     * @return void
     */
    public function maybe_create_table(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if ($table_exists) {
            return;
        }

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            request_uri text,
            detection_reason text,
            severity varchar(20) DEFAULT 'medium',
            dismissed tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY ip_address (ip_address),
            KEY dismissed (dismissed),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Log security event
     *
     * @param array $event_data Event data
     * @return int|false Insert ID or false on failure
     */
    public function log_security_event(array $event_data)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $defaults = [
            'event_type' => self::ALERT_SUSPICIOUS_ACTIVITY,
            'ip_address' => '',
            'user_agent' => '',
            'request_uri' => '',
            'detection_reason' => '',
            'severity' => 'medium',
            'created_at' => current_time('mysql'),
        ];

        $data = wp_parse_args($event_data, $defaults);

        // Determine severity based on event type
        if ($data['event_type'] === self::ALERT_INTRUDER) {
            $data['severity'] = 'high';
        }

        $result = $wpdb->insert($table_name, $data);

        if ($result === false) {
            return false;
        }

        $insert_id = $wpdb->insert_id;

        // Send real-time notification to admin
        $this->send_realtime_notification($data);

        // Store in transient for quick access
        $this->update_alert_count();

        return $insert_id;
    }

    /**
     * Send real-time notification to admin users
     *
     * @param array $event_data Event data
     * @return void
     */
    private function send_realtime_notification(array $event_data): void
    {
        // Store in transient for admin dashboard widget
        $alert_key = 'homa_latest_security_alert';
        set_transient($alert_key, $event_data, 3600);

        // If admin intervention system is available, send notification
        if (class_exists('HomayeTabesh\HT_Admin_Intervention')) {
            $admin_intervention = HT_Admin_Intervention::instance();
            
            $message = $this->format_alert_message($event_data);
            
            // Get all admin users
            $admins = get_users(['role' => 'administrator']);
            
            foreach ($admins as $admin) {
                $admin_intervention->send_alert_to_admin($admin->ID, [
                    'type' => 'security_alert',
                    'severity' => $event_data['severity'] ?? 'medium',
                    'message' => $message,
                    'timestamp' => current_time('mysql'),
                ]);
            }
        }
    }

    /**
     * Format alert message for display
     *
     * @param array $event_data Event data
     * @return string Formatted message
     */
    private function format_alert_message(array $event_data): string
    {
        $type_labels = [
            self::ALERT_INTRUDER => '🚨 مهاجم شناسایی شد',
            self::ALERT_BRUTE_FORCE => '⚠️ تلاش Brute Force',
            self::ALERT_SUSPICIOUS_ACTIVITY => '⚡ فعالیت مشکوک',
        ];

        $label = $type_labels[$event_data['event_type']] ?? 'هشدار امنیتی';
        $ip = $event_data['ip_address'] ?? 'نامشخص';
        $reason = $event_data['detection_reason'] ?? 'دلیل مشخص نیست';

        return "{$label}\nآی‌پی: {$ip}\nدلیل: {$reason}";
    }

    /**
     * Get recent security alerts
     *
     * @param int $limit Number of alerts to fetch
     * @param bool $undismissed_only Only fetch undismissed alerts
     * @return array Security alerts
     */
    public function get_recent_alerts(int $limit = 20, bool $undismissed_only = false): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $where = $undismissed_only ? 'WHERE dismissed = 0' : '';

        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} {$where} ORDER BY created_at DESC LIMIT %d",
            $limit
        );

        $results = $wpdb->get_results($query, ARRAY_A);

        return $results ?: [];
    }

    /**
     * Get alert count
     *
     * @param bool $undismissed_only Only count undismissed alerts
     * @return int Alert count
     */
    public function get_alert_count(bool $undismissed_only = true): int
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $where = $undismissed_only ? 'WHERE dismissed = 0' : '';

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} {$where}");

        return (int)$count;
    }

    /**
     * Update alert count in transient
     *
     * @return void
     */
    private function update_alert_count(): void
    {
        $count = $this->get_alert_count(true);
        set_transient('homa_security_alert_count', $count, 3600);
    }

    /**
     * Dismiss security alert
     *
     * @param int $alert_id Alert ID
     * @return bool Success
     */
    public function dismiss_alert(int $alert_id): bool
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $result = $wpdb->update(
            $table_name,
            ['dismissed' => 1],
            ['id' => $alert_id],
            ['%d'],
            ['%d']
        );

        if ($result !== false) {
            $this->update_alert_count();
            return true;
        }

        return false;
    }

    /**
     * Get security statistics
     *
     * @param string $period Time period (today, week, month)
     * @return array Statistics
     */
    public function get_security_statistics(string $period = 'today'): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Determine date range
        $date_condition = match($period) {
            'today' => "created_at >= CURDATE()",
            'week' => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => "created_at >= CURDATE()",
        };

        // Total events
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE {$date_condition}");

        // Events by type
        $by_type = $wpdb->get_results(
            "SELECT event_type, COUNT(*) as count FROM {$table_name} 
             WHERE {$date_condition} GROUP BY event_type",
            ARRAY_A
        );

        // Top attacking IPs
        $top_ips = $wpdb->get_results(
            "SELECT ip_address, COUNT(*) as count FROM {$table_name} 
             WHERE {$date_condition} GROUP BY ip_address ORDER BY count DESC LIMIT 10",
            ARRAY_A
        );

        return [
            'period' => $period,
            'total_events' => (int)$total,
            'events_by_type' => $by_type ?: [],
            'top_attacking_ips' => $top_ips ?: [],
            'generated_at' => current_time('mysql'),
        ];
    }

    /**
     * Clean up old security logs
     *
     * @param int $days_to_keep Number of days to keep logs
     * @return int Number of deleted records
     */
    public function cleanup_old_logs(int $days_to_keep = 90): int
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days_to_keep
            )
        );

        return $deleted ?: 0;
    }

    /**
     * AJAX handler: Get security alerts
     *
     * @return void
     */
    public function ajax_get_security_alerts(): void
    {
        check_ajax_referer('wp_rest', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز'], 403);
        }

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $undismissed_only = isset($_GET['undismissed_only']) && $_GET['undismissed_only'] === 'true';

        $alerts = $this->get_recent_alerts($limit, $undismissed_only);

        wp_send_json_success([
            'alerts' => $alerts,
            'total_count' => $this->get_alert_count($undismissed_only),
        ]);
    }

    /**
     * AJAX handler: Dismiss alert
     *
     * @return void
     */
    public function ajax_dismiss_alert(): void
    {
        check_ajax_referer('wp_rest', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز'], 403);
        }

        $alert_id = isset($_POST['alert_id']) ? (int)$_POST['alert_id'] : 0;

        if ($alert_id <= 0) {
            wp_send_json_error(['message' => 'شناسه نامعتبر'], 400);
        }

        $result = $this->dismiss_alert($alert_id);

        if ($result) {
            wp_send_json_success(['message' => 'هشدار نادیده گرفته شد']);
        } else {
            wp_send_json_error(['message' => 'خطا در نادیده گرفتن هشدار'], 500);
        }
    }

    /**
     * AJAX handler: Get alert count
     *
     * @return void
     */
    public function ajax_get_alert_count(): void
    {
        check_ajax_referer('wp_rest', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز'], 403);
        }

        $count = $this->get_alert_count(true);

        wp_send_json_success([
            'count' => $count,
        ]);
    }

    /**
     * Register REST API endpoints
     *
     * @return void
     */
    public function register_endpoints(): void
    {
        register_rest_route('homaye-tabesh/v1', '/security/alerts', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_alerts'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('homaye-tabesh/v1', '/security/statistics', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_statistics'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Handle get alerts REST endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function handle_get_alerts(\WP_REST_Request $request): \WP_REST_Response
    {
        $limit = $request->get_param('limit') ?: 20;
        $undismissed_only = $request->get_param('undismissed_only') === 'true';

        $alerts = $this->get_recent_alerts((int)$limit, $undismissed_only);

        return new \WP_REST_Response([
            'success' => true,
            'alerts' => $alerts,
            'total_count' => $this->get_alert_count($undismissed_only),
        ], 200);
    }

    /**
     * Handle get statistics REST endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function handle_get_statistics(\WP_REST_Request $request): \WP_REST_Response
    {
        $period = $request->get_param('period') ?: 'today';
        
        $stats = $this->get_security_statistics($period);

        return new \WP_REST_Response([
            'success' => true,
            'statistics' => $stats,
        ], 200);
    }
}
