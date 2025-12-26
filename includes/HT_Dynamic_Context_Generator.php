<?php
/**
 * Dynamic Context Generator - AI Context Builder
 *
 * @package HomayeTabesh
 * @since PR12
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * مولد کانتکست پویا برای AI
 * 
 * این کلاس داده‌های خام از افزونه‌ها را به «فکت‌های انسانی» 
 * قابل فهم برای Gemini تبدیل می‌کند.
 */
class HT_Dynamic_Context_Generator
{
    /**
     * Metadata mining engine
     */
    private HT_Metadata_Mining_Engine $mining_engine;

    /**
     * Plugin scanner
     */
    private HT_Plugin_Scanner $scanner;

    /**
     * Hook observer
     */
    private HT_Hook_Observer_Service $hook_observer;

    /**
     * Safety sanitizer (Commit 5)
     */
    private HT_Safety_Data_Sanitizer $sanitizer;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->mining_engine = new HT_Metadata_Mining_Engine();
        $this->scanner = new HT_Plugin_Scanner();
        $this->hook_observer = new HT_Hook_Observer_Service();
        $this->sanitizer = new HT_Safety_Data_Sanitizer();
    }

    /**
     * Generate complete AI context from all sources
     * 
     * @param array $additional_context کانتکست اضافی
     * @return string کانتکست کامل برای AI
     */
    public function generate_full_context(array $additional_context = []): string
    {
        $context = "=== بستر سیستم و قابلیت‌های فعال ===\n\n";

        // 1. اطلاعات افزونه‌های فعال
        $context .= $this->generate_plugins_context();

        // 2. اطلاعات متادیتای استخراج شده
        $context .= $this->generate_metadata_context();

        // 3. فکت‌های Knowledge Base (Commit 3 Enhancement)
        $context .= $this->generate_kb_facts_context();

        // 4. رویدادهای اخیر
        $context .= $this->generate_recent_events_context();

        // 5. وضعیت WooCommerce
        if ($this->scanner->has_woocommerce()) {
            $context .= $this->generate_woocommerce_context();
        }

        // 6. وضعیت Tabesh
        if ($this->scanner->has_tabesh()) {
            $context .= $this->generate_tabesh_context();
        }

        // 7. کانتکست اضافی
        if (!empty($additional_context)) {
            $context .= "\n=== اطلاعات اضافی ===\n";
            $context .= $this->format_additional_context($additional_context);
        }

        // سانیتایز نهایی قبل از ارسال به AI (Commit 5)
        return $this->sanitizer->sanitize_context($context);
    }

    /**
     * Generate knowledge base facts context
     * 
     * @return string
     */
    private function generate_kb_facts_context(): string
    {
        $kb = new HT_Knowledge_Base();
        $facts_text = $kb->get_plugin_facts_for_ai();

        if (empty($facts_text)) {
            return "";
        }

        return $facts_text . "\n";
    }

    /**
     * Generate plugins context section
     * 
     * @return string
     */
    private function generate_plugins_context(): string
    {
        $monitored = $this->scanner->get_monitored_plugins_details();

        if (empty($monitored)) {
            return "افزونه‌های تحت نظارت: هیچ کدام\n\n";
        }

        $context = "افزونه‌های فعال و تحت نظارت:\n";

        foreach ($monitored as $plugin) {
            if ($plugin['is_active']) {
                $context .= "✓ {$plugin['name']} ({$plugin['version']})\n";
            }
        }

        $context .= "\n";

        return $context;
    }

    /**
     * Generate metadata context section
     * 
     * @return string
     */
    private function generate_metadata_context(): string
    {
        $metadata = $this->mining_engine->get_metadata_for_ai();

        if (empty($metadata)) {
            return "";
        }

        $kb = $this->mining_engine->generate_knowledge_base($metadata);

        return $kb . "\n";
    }

    /**
     * Generate recent events context
     * 
     * @return string
     */
    private function generate_recent_events_context(): string
    {
        $recent_events = $this->hook_observer->get_recent_events(5);

        if (empty($recent_events)) {
            return "";
        }

        $context = "رویدادهای اخیر سیستم:\n";

        foreach ($recent_events as $event) {
            $context .= "- {$event['hook_name']} در {$event['event_time']}\n";
        }

        // فکت‌های اخیر
        $recent_facts = get_transient('homa_recent_facts') ?: [];
        
        if (!empty($recent_facts)) {
            $context .= "\nاطلاعات تازه:\n";
            foreach (array_slice($recent_facts, -5) as $fact_entry) {
                $context .= "- {$fact_entry['fact']}\n";
            }
        }

        $context .= "\n";

        return $context;
    }

    /**
     * Generate WooCommerce-specific context
     * 
     * @return string
     */
    private function generate_woocommerce_context(): string
    {
        $woo_context = HT_Core::instance()->woo_context;
        $full_context = $woo_context->get_full_context();

        return $woo_context->format_for_ai($full_context) . "\n";
    }

    /**
     * Generate Tabesh-specific context
     * 
     * @return string
     */
    private function generate_tabesh_context(): string
    {
        // اگر افزونه تابش فعال باشد
        $context = "=== سیستم تابش (مدیریت چاپخانه) ===\n";

        // استخراج اطلاعات از متادیتای تابش
        $metadata = $this->mining_engine->get_metadata_for_ai();
        
        if (isset($metadata['tabesh-order-system'])) {
            $tabesh_data = $metadata['tabesh-order-system'];
            
            if (!empty($tabesh_data['facts'])) {
                foreach ($tabesh_data['facts'] as $key => $value) {
                    if (is_array($value)) {
                        $context .= "- {$key}: " . implode(', ', $value) . "\n";
                    } else {
                        $context .= "- {$key}: {$value}\n";
                    }
                }
            }
        }

        $context .= "\n";

        return $context;
    }

    /**
     * Format additional context
     * 
     * @param array $additional کانتکست اضافی
     * @return string
     */
    private function format_additional_context(array $additional): string
    {
        $context = "";

        foreach ($additional as $key => $value) {
            if (is_array($value)) {
                $context .= "{$key}:\n";
                foreach ($value as $k => $v) {
                    $context .= "  - {$k}: {$v}\n";
                }
            } else {
                $context .= "- {$key}: {$value}\n";
            }
        }

        return $context;
    }

    /**
     * Generate context for specific user query
     * 
     * @param string $query سوال کاربر
     * @param int $user_id شناسه کاربر
     * @return string کانتکست مخصوص این سوال
     */
    public function generate_query_specific_context(string $query, int $user_id = 0): string
    {
        $context = $this->generate_full_context();

        // اضافه کردن اطلاعات کاربر
        if ($user_id > 0) {
            $context .= $this->generate_user_context($user_id);
        }

        // تحلیل سوال و اضافه کردن کانتکست مرتبط
        if ($this->is_order_related_query($query)) {
            $context .= $this->generate_order_tracking_context($user_id);
        }

        return $context;
    }

    /**
     * Generate user-specific context
     * 
     * @param int $user_id شناسه کاربر
     * @return string
     */
    private function generate_user_context(int $user_id): string
    {
        $context = "\n=== اطلاعات کاربر فعلی ===\n";

        $user = get_userdata($user_id);
        
        if (!$user) {
            return $context . "کاربر مهمان (وارد نشده)\n\n";
        }

        $context .= "نام: {$user->display_name}\n";
        $context .= "ایمیل: {$user->user_email}\n";

        // سفارشات اخیر
        if (class_exists('WooCommerce')) {
            $tracker = new HT_Order_Tracker();
            $orders = wc_get_orders([
                'customer_id' => $user_id,
                'limit' => 3,
                'orderby' => 'date',
                'order' => 'DESC',
            ]);

            if (!empty($orders)) {
                $context .= "سفارشات اخیر:\n";
                foreach ($orders as $order) {
                    $context .= "  - سفارش #{$order->get_id()}: {$order->get_status()}\n";
                }
            }
        }

        $context .= "\n";

        return $context;
    }

    /**
     * Check if query is order-related
     * 
     * @param string $query سوال
     * @return bool
     */
    private function is_order_related_query(string $query): bool
    {
        $order_keywords = [
            'سفارش',
            'ارسال',
            'پست',
            'کجاست',
            'رسید',
            'تحویل',
            'رهگیری',
        ];

        $query_lower = mb_strtolower($query, 'UTF-8');

        foreach ($order_keywords as $keyword) {
            if (strpos($query_lower, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate order tracking context
     * 
     * @param int $user_id شناسه کاربر
     * @return string
     */
    private function generate_order_tracking_context(int $user_id): string
    {
        if (!class_exists('WooCommerce') || $user_id === 0) {
            return "";
        }

        $context = "\n=== اطلاعات رهگیری سفارشات ===\n";

        $tracker = new HT_Order_Tracker();
        $user = get_userdata($user_id);
        
        if (!$user) {
            return $context;
        }

        $phone = get_user_meta($user_id, 'billing_phone', true);
        
        if (!empty($phone)) {
            $orders_result = $tracker->get_orders_by_phone($phone);
            
            if ($orders_result['success'] && !empty($orders_result['orders'])) {
                $context .= "سفارشات فعال:\n";
                foreach ($orders_result['orders'] as $order) {
                    $context .= "  - سفارش #{$order['order_id']}: {$order['status_label']} ";
                    $context .= "(کد رهگیری: {$order['tracking_code']})\n";
                }
            }
        }

        $context .= "\n";

        return $context;
    }

    /**
     * Generate lightweight context (برای استفاده در چت سریع)
     * 
     * @return string
     */
    public function generate_lightweight_context(): string
    {
        $context = "";

        // فقط فکت‌های ضروری
        $context .= "سایت: " . get_bloginfo('name') . "\n";
        
        if ($this->scanner->has_woocommerce()) {
            $context .= "فروشگاه: فعال\n";
        }

        if ($this->scanner->has_tabesh()) {
            $context .= "سیستم چاپخانه: فعال\n";
        }

        return $context;
    }
}
