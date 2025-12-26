<?php
/**
 * Hook Observer Service - Action & Filter Listener
 *
 * @package HomayeTabesh
 * @since PR12
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * سرویس شنونده Hookها
 * 
 * این کلاس به اکشن‌ها و فیلترهای افزونه‌ها گوش می‌دهد
 * و تغییرات مهم را ثبت می‌کند.
 */
class HT_Hook_Observer_Service
{
    /**
     * Recursion guard - prevents infinite loops
     * Note: This protects against same-request recursion. WordPress/PHP
     * runs in a single-threaded per-request model, so this is sufficient.
     */
    private static bool $is_processing = false;

    /**
     * لیست Hookهای مهم برای مانیتور
     */
    private const MONITORED_HOOKS = [
        // WooCommerce Hooks
        'woocommerce_order_status_changed',
        'woocommerce_new_order',
        'woocommerce_payment_complete',
        'woocommerce_thankyou',
        'woocommerce_product_options_pricing',
        
        // WordPress Core
        'wp_login',
        'user_register',
        'save_post',
        
        // Tabesh (Custom Plugin)
        'tabesh_order_approved',
        'tabesh_order_status_changed',
        'tabesh_design_completed',
    ];

    /**
     * لاگ رویدادها (در مموری)
     */
    private array $event_log = [];

    /**
     * Initialize hook observers
     * 
     * @return void
     */
    public function init_observers(): void
    {
        foreach (self::MONITORED_HOOKS as $hook) {
            add_action($hook, [$this, 'observe_hook'], 10, 10);
        }
    }

    /**
     * Observe and log hook execution
     * 
     * @param mixed ...$args آرگومان‌های hook
     * @return void
     */
    public function observe_hook(...$args): void
    {
        // Prevent infinite recursion
        if (self::$is_processing) {
            return;
        }

        self::$is_processing = true;

        try {
            $hook_name = current_filter();
            
            // ثبت رویداد
            $event = [
                'hook' => $hook_name,
                'timestamp' => current_time('mysql'),
                'args_summary' => $this->summarize_args($args),
            ];

            // افزودن به لاگ در مموری
            $this->event_log[] = $event;

            // ذخیره رویدادهای مهم در دیتابیس
            if ($this->is_critical_hook($hook_name)) {
                $this->store_event($event);
            }

            // اگر hook مربوط به تغییر وضعیت سفارش است
            if ($hook_name === 'woocommerce_order_status_changed') {
                $this->handle_order_status_change($args);
            }

            // اگر hook مربوط به Tabesh است
            if (strpos($hook_name, 'tabesh_') === 0) {
                $this->handle_tabesh_event($hook_name, $args);
            }
        } finally {
            self::$is_processing = false;
        }
    }

    /**
     * Handle WooCommerce order status change
     * 
     * @param array $args آرگومان‌های hook
     * @return void
     */
    private function handle_order_status_change(array $args): void
    {
        // $args[0] = order_id
        // $args[1] = old_status
        // $args[2] = new_status
        
        if (count($args) < 3) {
            return;
        }

        $order_id = $args[0];
        $new_status = $args[2];

        // اگر سفارش completed شد، زمان‌بندی نظرسنجی
        if ($new_status === 'completed') {
            $retention_engine = new HT_Retention_Engine();
            $retention_engine->schedule_feedback_sms($order_id);
        }

        error_log("Homa Hook Observer: Order #{$order_id} status changed to {$new_status}");
    }

    /**
     * Handle Tabesh-specific events
     * 
     * @param string $hook_name نام hook
     * @param array $args آرگومان‌ها
     * @return void
     */
    private function handle_tabesh_event(string $hook_name, array $args): void
    {
        // مدیریت رویدادهای افزونه تابش
        
        if ($hook_name === 'tabesh_order_approved') {
            // وقتی سفارش در تابش تایید شد
            $order_id = $args[0] ?? null;
            
            if ($order_id) {
                error_log("Homa Hook Observer: Tabesh order #{$order_id} approved");
                
                // به‌روزرسانی دانش هما
                $this->update_homa_knowledge("سفارش #{$order_id} در سیستم تابش تایید شد و آماده چاپ است.");
            }
        }

        if ($hook_name === 'tabesh_design_completed') {
            // وقتی طراحی تکمیل شد
            $design_id = $args[0] ?? null;
            
            if ($design_id) {
                error_log("Homa Hook Observer: Tabesh design #{$design_id} completed");
            }
        }
    }

    /**
     * Summarize hook arguments for logging
     * 
     * @param array $args آرگومان‌ها
     * @return string خلاصه
     */
    private function summarize_args(array $args): string
    {
        if (empty($args)) {
            return 'no args';
        }

        $summary = [];
        
        foreach ($args as $arg) {
            if (is_object($arg)) {
                $summary[] = get_class($arg);
            } elseif (is_array($arg)) {
                $summary[] = 'array[' . count($arg) . ']';
            } else {
                $summary[] = (string) $arg;
            }
        }

        return implode(', ', array_slice($summary, 0, 5));
    }

    /**
     * Check if hook is critical and should be stored
     * 
     * @param string $hook_name نام hook
     * @return bool
     */
    private function is_critical_hook(string $hook_name): bool
    {
        $critical_hooks = [
            'woocommerce_order_status_changed',
            'woocommerce_payment_complete',
            'tabesh_order_approved',
            'tabesh_order_status_changed',
        ];

        return in_array($hook_name, $critical_hooks);
    }

    /**
     * Store event in database
     * 
     * @param array $event رویداد
     * @return void
     */
    private function store_event(array $event): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_hook_events';

        // ایجاد جدول در صورت عدم وجود
        $this->maybe_create_events_table();

        $wpdb->insert($table, [
            'hook_name' => $event['hook'],
            'args_summary' => $event['args_summary'],
            'event_time' => $event['timestamp'],
        ]);
    }

    /**
     * Create events table if not exists
     * 
     * @return void
     */
    private function maybe_create_events_table(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_hook_events';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            hook_name varchar(100) NOT NULL,
            args_summary text,
            event_time datetime NOT NULL,
            PRIMARY KEY (id),
            KEY hook_name (hook_name),
            KEY event_time (event_time)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Update Homa's knowledge base with new information
     * 
     * @param string $fact فکت جدید
     * @return void
     */
    private function update_homa_knowledge(string $fact): void
    {
        // به‌روزرسانی دانش موقت (برای استفاده در مکالمه‌های فعلی)
        $current_facts = get_transient('homa_recent_facts') ?: [];
        $current_facts[] = [
            'fact' => $fact,
            'timestamp' => current_time('mysql'),
        ];
        
        // نگهداری فقط 20 فکت اخیر
        $current_facts = array_slice($current_facts, -20);
        
        set_transient('homa_recent_facts', $current_facts, HOUR_IN_SECONDS);
    }

    /**
     * Get recent events log
     * 
     * @param int $limit تعداد رویدادها
     * @return array
     */
    public function get_recent_events(int $limit = 10): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_hook_events';

        $events = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY event_time DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $events ?: [];
    }

    /**
     * Get events for specific hook
     * 
     * @param string $hook_name نام hook
     * @param int $limit تعداد
     * @return array
     */
    public function get_hook_events(string $hook_name, int $limit = 10): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_hook_events';

        $events = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE hook_name = %s ORDER BY event_time DESC LIMIT %d",
                $hook_name,
                $limit
            ),
            ARRAY_A
        );

        return $events ?: [];
    }

    /**
     * Get in-memory event log
     * 
     * @return array
     */
    public function get_event_log(): array
    {
        return $this->event_log;
    }

    /**
     * Clear old events (Cron job)
     * 
     * @return void
     */
    public static function cleanup_old_events(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_hook_events';

        // حذف رویدادهای قدیمی‌تر از 30 روز
        $wpdb->query(
            "DELETE FROM {$table} WHERE event_time < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        error_log('Homa Hook Observer: Cleaned up old events');
    }
}
