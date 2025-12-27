<?php
/**
 * Admin Live Intervention Bridge
 * Allows administrators to send real-time messages to active user chats
 *
 * @package HomayeTabesh
 * @since PR10
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * پل ارتباط مداخله زنده ادمین
 * Enables admin-to-user real-time messaging
 */
class HT_Admin_Intervention
{
    /**
     * Singleton instance
     */
    private static ?HT_Admin_Intervention $instance = null;

    /**
     * Messages table name
     */
    private string $table_name;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'homa_admin_interventions';
        
        $this->init();
    }

    /**
     * Initialize
     */
    private function init(): void
    {
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Create database table on plugin activation
        add_action('plugins_loaded', [$this, 'maybe_create_table']);
        
        // Add admin menu
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_submenu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        }
    }

    /**
     * Create interventions table if it doesn't exist
     */
    public function maybe_create_table(): void
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            admin_id bigint(20) NOT NULL,
            message text NOT NULL,
            visual_commands longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            delivered_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes(): void
    {
        // Send intervention message (Admin only)
        register_rest_route('homaye/v1', '/intervention/send', [
            'methods' => 'POST',
            'callback' => [$this, 'send_intervention'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'args' => [
                'session_id' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'message' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'visual_commands' => [
                    'required' => false,
                    'type' => 'array'
                ]
            ]
        ]);

        // Poll for pending messages (Client side)
        register_rest_route('homaye/v1', '/intervention/poll', [
            'methods' => 'GET',
            'callback' => [$this, 'poll_interventions'],
            'permission_callback' => '__return_true'
        ]);

        // Get active sessions (Admin only)
        register_rest_route('homaye/v1', '/intervention/sessions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_active_sessions'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        // Mark intervention as delivered
        register_rest_route('homaye/v1', '/intervention/(?P<id>\d+)/delivered', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_delivered'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Send intervention message (REST API callback)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function send_intervention(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $session_id = $request->get_param('session_id');
        $message = $request->get_param('message');
        $visual_commands = $request->get_param('visual_commands') ?? [];
        $admin_id = get_current_user_id();

        // Insert intervention
        $result = $wpdb->insert(
            $this->table_name,
            [
                'session_id' => $session_id,
                'admin_id' => $admin_id,
                'message' => $message,
                'visual_commands' => wp_json_encode($visual_commands),
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'خطا در ارسال پیام'
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'پیام با موفقیت ارسال شد',
            'intervention_id' => $wpdb->insert_id
        ], 200);
    }

    /**
     * Poll for pending interventions (REST API callback)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function poll_interventions(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        // Get session ID from cookie or request
        $session_id = $_COOKIE['homa_session_id'] ?? $request->get_param('session_id');
        
        if (empty($session_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'interventions' => []
            ], 200);
        }

        // Get pending interventions for this session
        $interventions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE session_id = %s AND status = 'pending' 
            ORDER BY created_at ASC",
            $session_id
        ));

        $result = [];
        foreach ($interventions as $intervention) {
            $result[] = [
                'id' => $intervention->id,
                'message' => $intervention->message,
                'visual_commands' => json_decode($intervention->visual_commands, true) ?? [],
                'created_at' => $intervention->created_at
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'interventions' => $result
        ], 200);
    }

    /**
     * Get active sessions (REST API callback)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_active_sessions(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        // Get sessions from the last 24 hours
        $sessions_table = $wpdb->prefix . 'homa_sessions';
        
        $sessions = $wpdb->get_results(
            "SELECT DISTINCT session_id, user_id, last_activity 
            FROM {$sessions_table} 
            WHERE last_activity > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY last_activity DESC
            LIMIT 50"
        );

        $result = [];
        foreach ($sessions as $session) {
            $user_info = get_userdata($session->user_id);
            
            $result[] = [
                'session_id' => $session->session_id,
                'user_id' => $session->user_id,
                'user_name' => $user_info ? $user_info->display_name : 'مهمان',
                'user_email' => $user_info ? $user_info->user_email : '',
                'last_activity' => $session->last_activity
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'sessions' => $result
        ], 200);
    }

    /**
     * Mark intervention as delivered (REST API callback)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function mark_delivered(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $intervention_id = $request->get_param('id');

        $result = $wpdb->update(
            $this->table_name,
            [
                'status' => 'delivered',
                'delivered_at' => current_time('mysql')
            ],
            ['id' => $intervention_id],
            ['%s', '%s'],
            ['%d']
        );

        return new \WP_REST_Response([
            'success' => $result !== false,
            'message' => $result !== false ? 'تحویل تایید شد' : 'خطا در به‌روزرسانی'
        ], 200);
    }

    /**
     * Add admin submenu
     */
    public function add_admin_submenu(): void
    {
        add_submenu_page(
            'homaye-tabesh',
            'مداخله زنده',
            '💬 مداخله زنده',
            'manage_options',
            'homaye-intervention',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page(): void
    {
        ?>
        <div class="wrap" id="homa-intervention-root">
            <h1>مداخله زنده در چت کاربران</h1>
            <p>از این صفحه می‌توانید مستقیماً به چت کاربران فعال پیام ارسال کنید.</p>
            <div id="homa-intervention-app"></div>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts(string $hook): void
    {
        // Only load on intervention page
        if ($hook !== 'homaye-tabesh_page_homaye-intervention') {
            return;
        }

        wp_enqueue_script(
            'homa-intervention-admin',
            HT_PLUGIN_URL . 'assets/js/homa-intervention-admin.js',
            ['jquery'],
            HT_VERSION,
            true
        );

        wp_enqueue_style(
            'homa-intervention-admin',
            HT_PLUGIN_URL . 'assets/css/homa-intervention-admin.css',
            [],
            HT_VERSION
        );

        wp_localize_script('homa-intervention-admin', 'homaInterventionConfig', [
            'restUrl' => rest_url('homaye/v1/intervention'),
            'nonce' => wp_create_nonce('wp_rest'),
            'pollInterval' => 5000 // 5 seconds
        ]);
    }

    /**
     * Send alert to admin
     * Called by HT_Admin_Security_Alerts to notify admins of security events
     *
     * @param int $admin_id Admin user ID
     * @param array $alert_data Alert data including type, severity, message, timestamp
     * @return bool True on success, false on failure
     */
    public function send_alert_to_admin(int $admin_id, array $alert_data): bool
    {
        global $wpdb;

        // Validate admin_id
        if ($admin_id <= 0) {
            return false;
        }

        // Validate user exists and has admin capabilities
        $user = get_userdata($admin_id);
        if ($user === false || !$user || !$user->has_cap('manage_options')) {
            return false;
        }

        // Default values for alert data
        $defaults = [
            'type' => 'security_alert',
            'severity' => 'medium',
            'message' => '',
            'timestamp' => current_time('mysql'),
        ];

        $alert = wp_parse_args($alert_data, $defaults);

        // Create a special session for admin notifications with entropy
        $session_id = 'admin_alert_' . $admin_id . '_' . time() . '_' . wp_generate_password(8, false);

        // Insert alert as an intervention message
        $result = $wpdb->insert(
            $this->table_name,
            [
                'session_id' => $session_id,
                'user_id' => $admin_id,
                'admin_id' => 0, // System-generated
                'message' => $alert['message'],
                'visual_commands' => wp_json_encode([
                    'type' => $alert['type'],
                    'severity' => $alert['severity'],
                ]),
                'status' => 'pending',
                'created_at' => $alert['timestamp']
            ],
            ['%s', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        return $result !== false;
    }
}
