<?php
/**
 * Fallback Engine - Offline Mode & Resilience
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * موتور پاسخ پشتیبان برای زمان قطعی API
 * شامل: حالت آفلاین، جمعآوری لید، پیامهای از پیش تعیین شده
 */
class HT_Fallback_Engine
{
    /**
     * Fallback mode status
     */
    private bool $is_offline = false;

    /**
     * Consecutive failure count
     */
    private int $failure_count = 0;

    /**
     * Failure threshold to trigger offline mode
     */
    private const FAILURE_THRESHOLD = 3;

    /**
     * Offline mode cache key
     */
    private const OFFLINE_MODE_KEY = 'ht_offline_mode';

    /**
     * Failure count cache key
     */
    private const FAILURE_COUNT_KEY = 'ht_api_failure_count';

    /**
     * Cache expiry time (seconds)
     */
    private const CACHE_EXPIRY = 300; // 5 minutes

    /**
     * Lead collection table
     */
    private string $leads_table;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->leads_table = $wpdb->prefix . 'homa_offline_leads';
        $this->check_offline_status();
    }

    /**
     * Create leads table
     */
    public function create_table(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->leads_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            full_name varchar(255) NOT NULL,
            phone varchar(20) NOT NULL,
            email varchar(255) DEFAULT NULL,
            user_message text,
            collected_at datetime DEFAULT CURRENT_TIMESTAMP,
            contacted tinyint(1) DEFAULT 0,
            contacted_at datetime DEFAULT NULL,
            notes text,
            PRIMARY KEY (id),
            KEY phone (phone),
            KEY contacted (contacted),
            KEY collected_at (collected_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Check if system is in offline mode
     */
    private function check_offline_status(): void
    {
        $this->is_offline = (bool) get_transient(self::OFFLINE_MODE_KEY);
        $this->failure_count = (int) get_transient(self::FAILURE_COUNT_KEY);
    }

    /**
     * Handle API response (success or failure)
     *
     * @param bool $success Whether API call was successful
     */
    public function record_api_result(bool $success): void
    {
        if ($success) {
            // Reset counters on success
            $this->failure_count = 0;
            $this->is_offline = false;
            delete_transient(self::FAILURE_COUNT_KEY);
            delete_transient(self::OFFLINE_MODE_KEY);
        } else {
            // Increment failure count
            $this->failure_count++;
            set_transient(self::FAILURE_COUNT_KEY, $this->failure_count, self::CACHE_EXPIRY);

            // Check if we need to enter offline mode
            if ($this->failure_count >= self::FAILURE_THRESHOLD) {
                $this->enter_offline_mode();
            }
        }
    }

    /**
     * Enter offline mode
     */
    private function enter_offline_mode(): void
    {
        $this->is_offline = true;
        set_transient(self::OFFLINE_MODE_KEY, true, self::CACHE_EXPIRY);

        // Log the event
        if (class_exists('\HomayeTabesh\HT_BlackBox_Logger')) {
            $logger = new HT_BlackBox_Logger();
            $logger->log_ai_transaction([
                'log_type' => 'system_event',
                'error_message' => 'Entered offline mode after ' . $this->failure_count . ' failures',
                'status' => 'warning',
            ]);
        }

        // Notify admin
        $this->notify_admin_offline();
    }

    /**
     * Check if currently in offline mode
     *
     * @return bool True if offline
     */
    public function is_offline(): bool
    {
        return $this->is_offline;
    }

    /**
     * Get fallback response for user
     *
     * @param string $user_input Original user input
     * @param array $context Request context
     * @return array Fallback response
     */
    public function get_fallback_response(string $user_input, array $context = []): array
    {
        // Detect if user is trying to make a purchase or inquiry
        $intent = $this->detect_intent($user_input);

        if ($intent === 'purchase' || $intent === 'inquiry') {
            return $this->get_lead_collection_response($user_input, $context);
        }

        return $this->get_general_offline_response();
    }

    /**
     * Detect user intent from input
     *
     * @param string $input User input
     * @return string Intent type
     */
    private function detect_intent(string $input): string
    {
        $input_lower = mb_strtolower($input);

        // Purchase intent keywords
        $purchase_keywords = [
            'خرید', 'سفارش', 'ثبت', 'محصول', 'قیمت', 'موجود',
            'buy', 'purchase', 'order', 'price'
        ];

        foreach ($purchase_keywords as $keyword) {
            if (strpos($input_lower, $keyword) !== false) {
                return 'purchase';
            }
        }

        // Inquiry intent keywords
        $inquiry_keywords = [
            'سوال', 'پرسش', 'اطلاعات', 'راهنما', 'کمک', 'مشاوره',
            'question', 'help', 'info', 'support'
        ];

        foreach ($inquiry_keywords as $keyword) {
            if (strpos($input_lower, $keyword) !== false) {
                return 'inquiry';
            }
        }

        return 'general';
    }

    /**
     * Get lead collection response
     *
     * @param string $user_input Original input
     * @param array $context Context data
     * @return array Response with lead form
     */
    private function get_lead_collection_response(string $user_input, array $context): array
    {
        return [
            'response' => 'متاسفم، در حال حاضر سیستم هوشمند به‌طور موقت در دسترس نیست. 🙏' . "\n\n" .
                         'اما نگران نباشید! من میتونم اطلاعات شما رو ثبت کنم تا همکارای ما در اولین فرصت باهاتون تماس بگیرن. 📞' . "\n\n" .
                         'لطفاً اطلاعات خودتون رو وارد کنید:',
            'actions' => [
                [
                    'type' => 'show_lead_form',
                    'params' => [
                        'fields' => [
                            ['name' => 'full_name', 'label' => 'نام و نام خانوادگی', 'required' => true],
                            ['name' => 'phone', 'label' => 'شماره تماس', 'required' => true],
                            ['name' => 'email', 'label' => 'ایمیل (اختیاری)', 'required' => false],
                            ['name' => 'message', 'label' => 'پیام شما', 'value' => $user_input, 'required' => false],
                        ],
                        'submit_text' => 'ثبت اطلاعات',
                    ],
                ],
            ],
            'mode' => 'offline',
            'requires_callback' => true,
        ];
    }

    /**
     * Get general offline response
     *
     * @return array Response
     */
    private function get_general_offline_response(): array
    {
        $messages = [
            'سلام! 👋 متاسفم، در حال حاضر به‌صورت موقت قادر به پاسخگویی کامل نیستم.',
            'اگر سوال فوری دارید، میتونید با تیم پشتیبانی ما تماس بگیرید.',
            'یا اطلاعات تماستون رو بذارید تا زودتر باهاتون تماس بگیریم. 📞',
        ];

        return [
            'response' => implode("\n\n", $messages),
            'mode' => 'offline',
            'support_contact' => [
                'phone' => get_option('ht_support_phone', ''),
                'email' => get_option('ht_support_email', get_option('admin_email')),
            ],
        ];
    }

    /**
     * Save lead information
     *
     * @param array $lead_data Lead data
     * @return int|false Lead ID or false on failure
     */
    public function save_lead(array $lead_data): int|false
    {
        global $wpdb;

        $data = [
            'full_name' => sanitize_text_field($lead_data['full_name'] ?? ''),
            'phone' => sanitize_text_field($lead_data['phone'] ?? ''),
            'email' => sanitize_email($lead_data['email'] ?? ''),
            'user_message' => sanitize_textarea_field($lead_data['message'] ?? ''),
        ];

        // Validate required fields
        if (empty($data['full_name']) || empty($data['phone'])) {
            return false;
        }

        $result = $wpdb->insert(
            $this->leads_table,
            $data,
            ['%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return false;
        }

        // Notify admin about new lead
        $this->notify_admin_new_lead($data);

        return (int) $wpdb->insert_id;
    }

    /**
     * Get collected leads
     *
     * @param array $filters Filter criteria
     * @return array Leads
     */
    public function get_leads(array $filters = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $values = [];

        if (isset($filters['contacted'])) {
            $where[] = 'contacted = %d';
            $values[] = $filters['contacted'] ? 1 : 0;
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'collected_at >= %s';
            $values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'collected_at <= %s';
            $values[] = $filters['date_to'];
        }

        $limit = absint($filters['limit'] ?? 100);
        $offset = absint($filters['offset'] ?? 0);

        $where_sql = implode(' AND ', $where);
        $query = "SELECT * FROM {$this->leads_table} WHERE {$where_sql} ORDER BY collected_at DESC LIMIT %d OFFSET %d";
        $values[] = $limit;
        $values[] = $offset;

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Mark lead as contacted
     *
     * @param int $lead_id Lead ID
     * @param string $notes Optional notes
     * @return bool Success
     */
    public function mark_lead_contacted(int $lead_id, string $notes = ''): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->leads_table,
            [
                'contacted' => 1,
                'contacted_at' => current_time('mysql'),
                'notes' => sanitize_textarea_field($notes),
            ],
            ['id' => $lead_id],
            ['%d', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Notify admin about offline mode
     */
    private function notify_admin_offline(): void
    {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');

        $subject = sprintf('[%s] هشدار: هما وارد حالت آفلاین شد', $site_name);
        $message = "سیستم هوشمند هما به دلیل خطاهای متوالی در API، به‌طور خودکار وارد حالت آفلاین شده است.\n\n";
        $message .= "تعداد خطاهای متوالی: {$this->failure_count}\n";
        $message .= "زمان: " . current_time('mysql') . "\n\n";
        $message .= "لطفاً تنظیمات API و اتصال به اینترنت را بررسی کنید.\n\n";
        $message .= "پنل مدیریت: " . admin_url('admin.php?page=homaye-tabesh');

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Notify admin about new lead
     *
     * @param array $lead_data Lead data
     */
    private function notify_admin_new_lead(array $lead_data): void
    {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');

        $subject = sprintf('[%s] لید جدید در حالت آفلاین', $site_name);
        $message = "یک مشتری بالقوه در زمان آفلاین بودن هما، اطلاعات خود را ثبت کرده است:\n\n";
        $message .= "نام: {$lead_data['full_name']}\n";
        $message .= "تلفن: {$lead_data['phone']}\n";
        if (!empty($lead_data['email'])) {
            $message .= "ایمیل: {$lead_data['email']}\n";
        }
        if (!empty($lead_data['user_message'])) {
            $message .= "پیام: {$lead_data['user_message']}\n";
        }
        $message .= "\nلطفاً در اولین فرصت با ایشان تماس بگیرید.";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Get fallback statistics
     *
     * @return array Statistics
     */
    public function get_statistics(): array
    {
        global $wpdb;

        $stats = [
            'is_offline' => $this->is_offline,
            'failure_count' => $this->failure_count,
            'total_leads' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->leads_table}"),
            'contacted_leads' => (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$this->leads_table} WHERE contacted = %d", 1)
            ),
            'pending_leads' => (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$this->leads_table} WHERE contacted = %d", 0)
            ),
            'last_24h_leads' => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->leads_table} WHERE collected_at >= %s",
                    date('Y-m-d H:i:s', strtotime('-24 hours'))
                )
            ),
        ];

        return $stats;
    }

    /**
     * Force exit offline mode (manual recovery)
     */
    public function force_online_mode(): void
    {
        $this->is_offline = false;
        $this->failure_count = 0;
        delete_transient(self::FAILURE_COUNT_KEY);
        delete_transient(self::OFFLINE_MODE_KEY);

        // Log the event
        if (class_exists('\HomayeTabesh\HT_BlackBox_Logger')) {
            $logger = new HT_BlackBox_Logger();
            $logger->log_ai_transaction([
                'log_type' => 'system_event',
                'error_message' => 'Manually forced online mode',
                'status' => 'info',
            ]);
        }
    }
}
