<?php
/**
 * Global Observer Core - Central Plugin Monitoring System
 *
 * @package HomayeTabesh
 * @since PR13
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * ناظر کل - هسته مرکزی مدیریت نظارت بر افزونه‌ها
 * 
 * این کلاس سیستم مرکزی برای مدیریت لیست افزونه‌های تحت نظر است
 * و به صورت خودکار تغییرات معنادار را شناسایی می‌کند.
 */
class HT_Global_Observer_Core
{
    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Recursion guard - prevents infinite loops
     */
    private static bool $is_processing = false;

    /**
     * Plugin scanner instance
     */
    private HT_Plugin_Scanner $scanner;

    /**
     * Metadata mining engine
     */
    private HT_Metadata_Mining_Engine $mining_engine;

    /**
     * Hook observer service
     */
    private HT_Hook_Observer_Service $hook_observer;

    /**
     * Knowledge base
     */
    private HT_Knowledge_Base $knowledge_base;

    /**
     * Get singleton instance
     * 
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->scanner = new HT_Plugin_Scanner();
        $this->mining_engine = new HT_Metadata_Mining_Engine();
        $this->hook_observer = new HT_Hook_Observer_Service();
        $this->knowledge_base = new HT_Knowledge_Base();

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     * 
     * @return void
     */
    private function init_hooks(): void
    {
        // شنود به‌روزرسانی تنظیمات
        add_action('updated_option', [$this, 'on_option_updated'], 10, 3);

        // شنود فعال/غیرفعال شدن افزونه‌ها
        add_action('activated_plugin', [$this, 'on_plugin_activated'], 10, 1);
        add_action('deactivated_plugin', [$this, 'on_plugin_deactivated'], 10, 1);

        // شنود به‌روزرسانی افزونه‌ها
        add_action('upgrader_process_complete', [$this, 'on_plugin_upgraded'], 10, 2);
    }

    /**
     * Check if an option is being monitored
     * 
     * @param string $option_name نام option
     * @return bool
     */
    public function is_monitored_option(string $option_name): bool
    {
        $monitored_plugins = $this->scanner->get_monitored_plugins();

        foreach ($monitored_plugins as $plugin_path) {
            $plugin_slug = dirname($plugin_path);
            $plugin_slug_clean = str_replace('-', '_', $plugin_slug);

            // چک کردن اینکه آیا option مربوط به افزونه تحت نظر است
            if (
                strpos($option_name, $plugin_slug) !== false ||
                strpos($option_name, $plugin_slug_clean) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle option update event
     * 
     * @param string $option نام option
     * @param mixed $old_value مقدار قبلی
     * @param mixed $value مقدار جدید
     * @return void
     */
    public function on_option_updated(string $option, $old_value, $value): void
    {
        // Prevent infinite recursion
        if (self::$is_processing) {
            return;
        }

        self::$is_processing = true;

        try {
            // فقط اگر option مربوط به افزونه تحت نظر باشد
            if (!$this->is_monitored_option($option)) {
                return;
            }

            // فیلتر کردن داده‌های حساس
            if ($this->is_sensitive_data($option)) {
                return;
            }

            // ثبت تغییر در لاگ
            $this->log_change('option_updated', [
                'option' => $option,
                'old_value' => $this->sanitize_value($old_value),
                'new_value' => $this->sanitize_value($value),
            ]);

            // همگام‌سازی فوری با Knowledge Base
            $this->sync_immediately($option, $value);
        } finally {
            self::$is_processing = false;
        }
    }

    /**
     * Handle plugin activation event
     * 
     * @param string $plugin_path مسیر افزونه
     * @return void
     */
    public function on_plugin_activated(string $plugin_path): void
    {
        // Prevent infinite recursion
        if (self::$is_processing) {
            return;
        }

        self::$is_processing = true;

        try {
            $monitored = $this->scanner->get_monitored_plugins();

            if (in_array($plugin_path, $monitored)) {
                $plugin_info = $this->scanner->get_plugin_info(dirname($plugin_path));
                
                $this->log_change('plugin_activated', [
                    'plugin' => $plugin_path,
                    'name' => $plugin_info['name'] ?? 'Unknown',
                ]);

                // به‌روزرسانی متادیتا
                $this->mining_engine->refresh_metadata();

                // اضافه کردن فکت به Knowledge Base
                $fact = "افزونه {$plugin_info['name']} فعال شد.";
                $this->add_fact_to_kb($fact, 'plugin_activation');
            }
        } finally {
            self::$is_processing = false;
        }
    }

    /**
     * Handle plugin deactivation event
     * 
     * @param string $plugin_path مسیر افزونه
     * @return void
     */
    public function on_plugin_deactivated(string $plugin_path): void
    {
        // Prevent infinite recursion
        if (self::$is_processing) {
            return;
        }

        self::$is_processing = true;

        try {
            $monitored = $this->scanner->get_monitored_plugins();

            if (in_array($plugin_path, $monitored)) {
                $plugin_info = $this->scanner->get_plugin_info(dirname($plugin_path));
                
                $this->log_change('plugin_deactivated', [
                    'plugin' => $plugin_path,
                    'name' => $plugin_info['name'] ?? 'Unknown',
                ]);

                $fact = "افزونه {$plugin_info['name']} غیرفعال شد.";
                $this->add_fact_to_kb($fact, 'plugin_deactivation');
            }
        } finally {
            self::$is_processing = false;
        }
    }

    /**
     * Handle plugin upgrade event
     * 
     * @param \WP_Upgrader $upgrader WP Upgrader instance
     * @param array $options آپشن‌های upgrade
     * @return void
     */
    public function on_plugin_upgraded($upgrader, array $options): void
    {
        // Prevent infinite recursion
        if (self::$is_processing) {
            return;
        }

        if ($options['type'] !== 'plugin' || !isset($options['plugins'])) {
            return;
        }

        self::$is_processing = true;

        try {

            $monitored = $this->scanner->get_monitored_plugins();

            foreach ($options['plugins'] as $plugin_path) {
                if (in_array($plugin_path, $monitored)) {
                    $plugin_info = $this->scanner->get_plugin_info(dirname($plugin_path));
                    
                    $this->log_change('plugin_upgraded', [
                        'plugin' => $plugin_path,
                        'name' => $plugin_info['name'] ?? 'Unknown',
                        'version' => $plugin_info['version'] ?? 'Unknown',
                    ]);

                    // به‌روزرسانی متادیتا بعد از upgrade
                    $this->mining_engine->refresh_metadata();

                    $fact = "افزونه {$plugin_info['name']} به نسخه {$plugin_info['version']} به‌روزرسانی شد.";
                    $this->add_fact_to_kb($fact, 'plugin_upgrade');
                }
            }
        } finally {
            self::$is_processing = false;
        }
    }

    /**
     * Synchronize option change with Knowledge Base immediately
     * 
     * @param string $option نام option
     * @param mixed $value مقدار جدید
     * @return void
     */
    public function sync_immediately(string $option, $value): void
    {
        // تبدیل تغییر به فکت قابل فهم
        $fact = $this->convert_to_human_fact($option, $value);

        if (!empty($fact)) {
            $this->add_fact_to_kb($fact, 'option_change');
        }
    }

    /**
     * Convert technical option to human-readable fact
     * 
     * @param string $option نام option
     * @param mixed $value مقدار
     * @return string فکت انسانی
     */
    private function convert_to_human_fact(string $option, $value): string
    {
        // نگاشت option های معروف به متون فارسی
        $mappings = [
            'woocommerce_currency' => 'واحد پول فروشگاه به %s تغییر کرد.',
            'woocommerce_enable_guest_checkout' => 'خرید مهمان %s شد.',
            'woocommerce_enable_signup_and_login_from_checkout' => 'ثبت‌نام از صفحه پرداخت %s شد.',
            'woocommerce_enable_reviews' => 'نظرات محصولات %s شد.',
            'woocommerce_calc_taxes' => 'محاسبه مالیات %s شد.',
            'woocommerce_prices_include_tax' => 'قیمت‌ها شامل مالیات %s.',
        ];

        // چک کردن نگاشت‌های از پیش تعریف شده
        foreach ($mappings as $pattern => $template) {
            if (strpos($option, $pattern) !== false) {
                $formatted_value = $this->format_value_for_fact($value);
                return sprintf($template, $formatted_value);
            }
        }

        // برای option های ناشناخته، یک فکت عمومی
        if (is_string($value) || is_numeric($value)) {
            return "تنظیم {$option} به {$value} تغییر کرد.";
        }

        return "تنظیم {$option} به‌روزرسانی شد.";
    }

    /**
     * Format value for human-readable fact
     * 
     * @param mixed $value مقدار
     * @return string مقدار فرمت شده
     */
    private function format_value_for_fact($value): string
    {
        if ($value === 'yes' || $value === true || $value === 1) {
            return 'فعال';
        }

        if ($value === 'no' || $value === false || $value === 0) {
            return 'غیرفعال';
        }

        if (is_array($value)) {
            return 'به حالت جدید';
        }

        return (string) $value;
    }

    /**
     * Add fact to knowledge base
     * 
     * @param string $fact فکت
     * @param string $category دسته‌بندی
     * @return void
     */
    private function add_fact_to_kb(string $fact, string $category): void
    {
        // ذخیره در transient برای دسترسی سریع
        $recent_facts = get_transient('homa_recent_facts') ?: [];
        $recent_facts[] = [
            'fact' => $fact,
            'category' => $category,
            'timestamp' => current_time('mysql'),
        ];

        // نگهداری فقط 50 فکت اخیر
        $recent_facts = array_slice($recent_facts, -50);
        set_transient('homa_recent_facts', $recent_facts, 12 * HOUR_IN_SECONDS);

        // ذخیره در دیتابیس برای ماندگاری
        $this->save_fact_to_database($fact, $category);

        // لاگ برای دیباگ
        error_log("Homa Observer: {$fact}");
    }

    /**
     * Save fact to database
     * 
     * @param string $fact فکت
     * @param string $category دسته‌بندی
     * @return void
     */
    private function save_fact_to_database(string $fact, string $category): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_knowledge';

        // ایجاد جدول در صورت عدم وجود
        $this->maybe_create_knowledge_table();

        $wpdb->insert($table, [
            'fact' => $fact,
            'category' => $category,
            'source' => 'global_observer',
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Create knowledge table if not exists
     * 
     * @return void
     */
    private function maybe_create_knowledge_table(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_knowledge';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            fact text NOT NULL,
            category varchar(50) NOT NULL,
            source varchar(50) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY category (category),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Check if data is sensitive
     * 
     * @param string $key کلید
     * @return bool
     */
    private function is_sensitive_data(string $key): bool
    {
        $sensitive_patterns = [
            'password',
            'api_key',
            'secret',
            'token',
            'private_key',
            'access_key',
            'auth',
            'credential',
        ];

        $key_lower = strtolower($key);

        foreach ($sensitive_patterns as $pattern) {
            if (strpos($key_lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize value for logging
     * 
     * @param mixed $value مقدار
     * @return mixed مقدار سانیتایز شده
     */
    private function sanitize_value($value)
    {
        if (is_array($value)) {
            return '[array]';
        }

        if (is_object($value)) {
            return '[object]';
        }

        if (is_string($value) && strlen($value) > 100) {
            return '[long_string]';
        }

        return $value;
    }

    /**
     * Log change event
     * 
     * @param string $event_type نوع رویداد
     * @param array $data داده‌ها
     * @return void
     */
    private function log_change(string $event_type, array $data): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_observer_log';

        $this->maybe_create_log_table();

        $wpdb->insert($table, [
            'event_type' => $event_type,
            'event_data' => wp_json_encode($data),
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Create observer log table if not exists
     * 
     * @return void
     */
    private function maybe_create_log_table(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_observer_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data text,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Get monitoring summary for admin display
     * 
     * @return array خلاصه وضعیت
     */
    public function get_monitoring_summary(): array
    {
        $monitored_plugins = $this->scanner->get_monitored_plugins_details();
        
        return [
            'monitored_count' => count($monitored_plugins),
            'active_count' => count(array_filter($monitored_plugins, fn($p) => $p['is_active'])),
            'monitored_plugins' => $monitored_plugins,
            'last_sync' => get_option('homa_last_metadata_sync', 'هرگز'),
        ];
    }

    /**
     * Get recent changes log
     * 
     * @param int $limit تعداد
     * @return array لاگ تغییرات
     */
    public function get_recent_changes(int $limit = 20): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_observer_log';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get recent facts from knowledge base
     * 
     * @param int $limit تعداد
     * @return array فکت‌های اخیر
     */
    public function get_recent_facts(int $limit = 20): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_knowledge';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE source = 'global_observer' ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $results ?: [];
    }
}
