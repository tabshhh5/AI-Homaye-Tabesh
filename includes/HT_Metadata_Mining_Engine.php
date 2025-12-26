<?php
/**
 * Metadata Mining Engine - Plugin Data Extraction
 *
 * @package HomayeTabesh
 * @since PR12
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * موتور استخراج متادیتا از افزونه‌ها
 * 
 * این کلاس بدون دخالت در کد افزونه‌ها، از طریق دیتابیس و Hookها
 * اطلاعات مفید را استخراج می‌کند.
 */
class HT_Metadata_Mining_Engine
{
    /**
     * Plugin scanner instance
     */
    private HT_Plugin_Scanner $scanner;

    /**
     * Safety sanitizer
     */
    private HT_Safety_Data_Sanitizer $sanitizer;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->scanner = new HT_Plugin_Scanner();
        $this->sanitizer = new HT_Safety_Data_Sanitizer();
    }

    /**
     * Extract metadata from monitored plugins
     * 
     * @return array متادیتای استخراج شده
     */
    public function mine_all_plugins_metadata(): array
    {
        $monitored = $this->scanner->get_monitored_plugins_details();
        $all_metadata = [];

        foreach ($monitored as $plugin) {
            $metadata = $this->mine_plugin_metadata($plugin['slug']);
            
            if (!empty($metadata)) {
                $all_metadata[$plugin['slug']] = $metadata;
            }
        }

        return $all_metadata;
    }

    /**
     * Extract metadata from specific plugin
     * 
     * @param string $plugin_slug اسلاگ افزونه
     * @return array متادیتا
     */
    public function mine_plugin_metadata(string $plugin_slug): array
    {
        $metadata = [
            'plugin_slug' => $plugin_slug,
            'extraction_time' => current_time('mysql'),
            'options' => [],
            'options_human' => [],
            'tables' => [],
            'capabilities' => [],
            'facts' => [],
        ];

        // استخراج تنظیمات
        $metadata['options'] = $this->scanner->get_plugin_options($plugin_slug);

        // تبدیل به متن انسانی (Commit 2 Enhancement)
        $metadata['options_human'] = $this->convert_options_to_human($metadata['options'], $plugin_slug);

        // استخراج جداول
        $metadata['tables'] = $this->scanner->scan_plugin_tables($plugin_slug);

        // استخراج قابلیت‌ها
        $metadata['capabilities'] = $this->scanner->get_plugin_capabilities($plugin_slug);

        // استخراج فکت‌های خاص افزونه
        $metadata['facts'] = $this->extract_plugin_facts($plugin_slug);

        return $metadata;
    }

    /**
     * Convert technical options to human-readable text
     * 
     * @param array $options تنظیمات فنی
     * @param string $plugin_slug اسلاگ افزونه
     * @return array متن انسانی
     */
    private function convert_options_to_human(array $options, string $plugin_slug): array
    {
        $human_readable = [];

        // نگاشت‌های عمومی
        $general_mappings = [
            'enabled' => 'وضعیت: %s',
            'enable' => 'فعال‌سازی: %s',
            'disabled' => 'غیرفعال: %s',
            'currency' => 'واحد پول: %s',
            'price' => 'قیمت: %s تومان',
            'cost' => 'هزینه: %s تومان',
            'tax' => 'مالیات: %s',
            'shipping' => 'ارسال: %s',
            'status' => 'وضعیت: %s',
        ];

        // نگاشت‌های خاص WooCommerce
        $woo_mappings = [
            'woocommerce_currency' => 'واحد پول فروشگاه: %s',
            'woocommerce_enable_guest_checkout' => 'خرید مهمان: %s',
            'woocommerce_enable_signup_and_login_from_checkout' => 'ثبت‌نام از صفحه پرداخت: %s',
            'woocommerce_enable_reviews' => 'نظرات محصولات: %s',
            'woocommerce_calc_taxes' => 'محاسبه مالیات: %s',
            'woocommerce_prices_include_tax' => 'قیمت‌ها شامل مالیات',
            'woocommerce_ship_to_countries' => 'ارسال به کشورها: %s',
            'woocommerce_allowed_countries' => 'کشورهای مجاز: %s',
            'woocommerce_default_country' => 'کشور پیش‌فرض: %s',
        ];

        foreach ($options as $key => $value) {
            // چک کردن نگاشت‌های خاص WooCommerce
            if ($plugin_slug === 'woocommerce' && isset($woo_mappings[$key])) {
                $formatted = sprintf($woo_mappings[$key], $this->format_value($value));
                $human_readable[$key] = $formatted;
                continue;
            }

            // چک کردن نگاشت‌های عمومی
            foreach ($general_mappings as $pattern => $template) {
                if (stripos($key, $pattern) !== false) {
                    $formatted = sprintf($template, $this->format_value($value));
                    $human_readable[$key] = $formatted;
                    continue 2;
                }
            }

            // برای option های بدون نگاشت مشخص
            $human_readable[$key] = $this->generate_generic_human_text($key, $value);
        }

        return $human_readable;
    }

    /**
     * Format option value for display
     * 
     * @param mixed $value مقدار
     * @return string مقدار فرمت شده
     */
    private function format_value($value): string
    {
        if ($value === 'yes' || $value === true || $value === '1') {
            return 'فعال';
        }

        if ($value === 'no' || $value === false || $value === '0' || $value === '') {
            return 'غیرفعال';
        }

        if (is_array($value)) {
            return implode(', ', array_slice($value, 0, 3));
        }

        if (is_numeric($value)) {
            return number_format((float)$value, 0, '.', ',');
        }

        return (string) $value;
    }

    /**
     * Generate generic human-readable text
     * 
     * @param string $key کلید
     * @param mixed $value مقدار
     * @return string متن انسانی
     */
    private function generate_generic_human_text(string $key, $value): string
    {
        // پاک‌سازی نام کلید
        $clean_key = str_replace(['_', '-'], ' ', $key);
        $formatted_value = $this->format_value($value);

        return "{$clean_key}: {$formatted_value}";
    }

    /**
     * Extract specific facts about a plugin
     * 
     * @param string $plugin_slug اسلاگ افزونه
     * @return array فکت‌ها
     */
    private function extract_plugin_facts(string $plugin_slug): array
    {
        // استخراج فکت‌های خاص برای افزونه‌های شناخته شده
        return match ($plugin_slug) {
            'woocommerce' => $this->extract_woocommerce_facts(),
            'tabesh-order-system' => $this->extract_tabesh_facts(),
            default => [],
        };
    }

    /**
     * Extract WooCommerce-specific facts
     * 
     * @return array فکت‌های ووکامرس
     */
    private function extract_woocommerce_facts(): array
    {
        if (!class_exists('WooCommerce')) {
            return [];
        }

        $facts = [];

        // وضعیت‌های سفارش سفارشی
        $order_statuses = wc_get_order_statuses();
        $facts['order_statuses'] = array_values($order_statuses);

        // روش‌های حمل و نقل فعال
        $shipping_zones = \WC_Shipping_Zones::get_zones();
        $facts['shipping_zones_count'] = count($shipping_zones);

        // روش‌های پرداخت فعال
        $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $facts['payment_methods'] = array_keys($payment_gateways);

        // تعداد محصولات
        $products_count = wp_count_posts('product');
        $facts['products_count'] = $products_count->publish ?? 0;

        // واحد پول
        $facts['currency'] = get_woocommerce_currency();

        // آدرس فروشگاه
        $facts['store_address'] = [
            'country' => WC()->countries->get_base_country(),
            'state' => WC()->countries->get_base_state(),
            'postcode' => WC()->countries->get_base_postcode(),
        ];

        return $facts;
    }

    /**
     * Extract Tabesh-specific facts (Custom printing plugin)
     * 
     * @return array فکت‌های تابش
     */
    private function extract_tabesh_facts(): array
    {
        // استخراج اطلاعات از افزونه تابش (در صورت وجود)
        $facts = [];

        // چک کردن وجود تنظیمات تابش
        $tabesh_statuses = get_option('tabesh_custom_statuses', []);
        if (!empty($tabesh_statuses)) {
            $facts['custom_statuses'] = $tabesh_statuses;
        }

        // قیمت‌های طراحی
        $design_prices = get_option('tabesh_design_prices', []);
        if (!empty($design_prices)) {
            $facts['design_pricing'] = $design_prices;
        }

        // انواع کاغذ
        $paper_types = get_option('tabesh_paper_types', []);
        if (!empty($paper_types)) {
            $facts['paper_types'] = array_values($paper_types);
        }

        return $facts;
    }

    /**
     * Generate human-readable knowledge base from metadata
     * 
     * @param array $metadata متادیتا
     * @return string دانش قابل فهم برای AI
     */
    public function generate_knowledge_base(array $metadata): string
    {
        if (empty($metadata)) {
            return '';
        }

        $kb = "دانش استخراج شده از افزونه‌ها:\n\n";

        foreach ($metadata as $plugin_slug => $data) {
            $kb .= "=== " . strtoupper($plugin_slug) . " ===\n";

            // فکت‌ها
            if (!empty($data['facts'])) {
                $kb .= "قابلیت‌ها و تنظیمات:\n";
                
                foreach ($data['facts'] as $key => $value) {
                    if (is_array($value)) {
                        $kb .= "- {$key}: " . implode(', ', $value) . "\n";
                    } else {
                        $kb .= "- {$key}: {$value}\n";
                    }
                }
            }

            // تنظیمات قابل فهم انسانی (Commit 2 Enhancement)
            if (!empty($data['options_human'])) {
                $kb .= "\nتنظیمات فعلی:\n";
                $count = 0;
                foreach ($data['options_human'] as $key => $human_text) {
                    $kb .= "- {$human_text}\n";
                    $count++;
                    // محدود کردن به 10 تنظیم مهم برای جلوگیری از شلوغی
                    if ($count >= 10) {
                        break;
                    }
                }
            }

            // قابلیت‌های کلی
            if (!empty($data['capabilities']['features'])) {
                $kb .= "\nقابلیت‌های اصلی: " . implode(', ', $data['capabilities']['features']) . "\n";
            }

            $kb .= "\n";
        }

        return $kb;
    }

    /**
     * Cache metadata to reduce database queries
     * 
     * @param array $metadata متادیتا
     * @return void
     */
    public function cache_metadata(array $metadata): void
    {
        set_transient('ht_plugin_metadata_cache', $metadata, 6 * HOUR_IN_SECONDS);
        
        // ذخیره در option برای استفاده طولانی مدت
        update_option('ht_plugin_metadata_snapshot', [
            'data' => $metadata,
            'timestamp' => current_time('mysql'),
        ]);
    }

    /**
     * Get cached metadata
     * 
     * @return array|null
     */
    public function get_cached_metadata(): ?array
    {
        $cached = get_transient('ht_plugin_metadata_cache');
        
        if ($cached !== false) {
            return $cached;
        }

        // بازیابی از snapshot
        $snapshot = get_option('ht_plugin_metadata_snapshot', null);
        
        if ($snapshot && isset($snapshot['data'])) {
            return $snapshot['data'];
        }

        return null;
    }

    /**
     * Force refresh metadata cache
     * 
     * @return array متادیتای جدید
     */
    public function refresh_metadata(): array
    {
        delete_transient('ht_plugin_metadata_cache');
        
        $metadata = $this->mine_all_plugins_metadata();
        $this->cache_metadata($metadata);
        
        return $metadata;
    }

    /**
     * Get metadata for AI context (optimized)
     * 
     * @return array متادیتا برای AI
     */
    public function get_metadata_for_ai(): array
    {
        // استفاده از کش در صورت وجود
        $cached = $this->get_cached_metadata();
        
        if ($cached !== null) {
            // سانیتایز قبل از ارسال به AI (Commit 5)
            return $this->sanitizer->sanitize_metadata($cached);
        }

        // استخراج و کش کردن
        $metadata = $this->mine_all_plugins_metadata();
        $this->cache_metadata($metadata);
        
        // سانیتایز قبل از ارسال به AI (Commit 5)
        return $this->sanitizer->sanitize_metadata($metadata);
    }

    /**
     * Schedule periodic metadata refresh
     * 
     * @return void
     */
    public static function schedule_metadata_refresh(): void
    {
        if (!wp_next_scheduled('homa_refresh_plugin_metadata')) {
            wp_schedule_event(time(), 'twicedaily', 'homa_refresh_plugin_metadata');
        }
    }

    /**
     * Metadata refresh cron callback
     * 
     * @return void
     */
    public static function metadata_refresh_cron(): void
    {
        $engine = new self();
        $metadata = $engine->refresh_metadata();
        
        error_log('Homa Plugin Metadata: Refreshed ' . count($metadata) . ' plugins');

        // Auto-sync to knowledge base (Commit 3)
        $kb = new HT_Knowledge_Base();
        $kb->sync_plugin_metadata_to_kb($metadata);
    }
}
