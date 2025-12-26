<?php
/**
 * Plugin Scanner - Global Plugin Discovery & Selection
 *
 * @package HomayeTabesh
 * @since PR12
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * ناظر کل - سیستم اسکن و انتخاب افزونه‌ها
 * 
 * این کلاس لیست افزونه‌های نصب شده را نمایش می‌دهد و
 * امکان انتخاب افزونه‌های هدف برای استخراج متادیتا را فراهم می‌کند.
 */
class HT_Plugin_Scanner
{
    /**
     * افزونه‌های پیش‌فرض برای مانیتورینگ
     */
    private const DEFAULT_MONITORED_PLUGINS = [
        'woocommerce/woocommerce.php',
        'wordpress-seo/wp-seo.php',
        'contact-form-7/wp-contact-form-7.php',
    ];

    /**
     * Get all installed plugins
     * 
     * @return array لیست افزونه‌های نصب شده
     */
    public function get_installed_plugins(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        $monitored_plugins = $this->get_monitored_plugins();

        $plugins = [];

        foreach ($all_plugins as $plugin_path => $plugin_data) {
            $plugins[] = [
                'path' => $plugin_path,
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'author' => $plugin_data['Author'],
                'description' => $plugin_data['Description'],
                'is_active' => in_array($plugin_path, $active_plugins),
                'is_monitored' => in_array($plugin_path, $monitored_plugins),
                'slug' => dirname($plugin_path),
            ];
        }

        return $plugins;
    }

    /**
     * Get list of monitored plugins
     * 
     * @return array لیست افزونه‌های تحت نظر
     */
    public function get_monitored_plugins(): array
    {
        $monitored = get_option('ht_monitored_plugins', self::DEFAULT_MONITORED_PLUGINS);
        
        // اطمینان از اینکه آرایه است
        if (!is_array($monitored)) {
            $monitored = self::DEFAULT_MONITORED_PLUGINS;
            update_option('ht_monitored_plugins', $monitored);
        }

        return $monitored;
    }

    /**
     * Add plugin to monitoring list
     * 
     * @param string $plugin_path مسیر افزونه
     * @return bool
     */
    public function add_monitored_plugin(string $plugin_path): bool
    {
        $monitored = $this->get_monitored_plugins();
        
        if (in_array($plugin_path, $monitored)) {
            return true; // قبلاً اضافه شده
        }

        $monitored[] = $plugin_path;
        return update_option('ht_monitored_plugins', $monitored);
    }

    /**
     * Remove plugin from monitoring list
     * 
     * @param string $plugin_path مسیر افزونه
     * @return bool
     */
    public function remove_monitored_plugin(string $plugin_path): bool
    {
        $monitored = $this->get_monitored_plugins();
        
        $key = array_search($plugin_path, $monitored);
        if ($key === false) {
            return true; // وجود ندارد
        }

        unset($monitored[$key]);
        return update_option('ht_monitored_plugins', array_values($monitored));
    }

    /**
     * Get monitored plugins with details
     * 
     * @return array افزونه‌های تحت نظر با جزئیات
     */
    public function get_monitored_plugins_details(): array
    {
        $all_plugins = $this->get_installed_plugins();
        $monitored_paths = $this->get_monitored_plugins();

        $monitored_details = [];

        foreach ($all_plugins as $plugin) {
            if (in_array($plugin['path'], $monitored_paths)) {
                $monitored_details[] = $plugin;
            }
        }

        return $monitored_details;
    }

    /**
     * Check if a plugin is active
     * 
     * @param string $plugin_path مسیر افزونه
     * @return bool
     */
    public function is_plugin_active(string $plugin_path): bool
    {
        return is_plugin_active($plugin_path);
    }

    /**
     * Detect WooCommerce presence
     * 
     * @return bool
     */
    public function has_woocommerce(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * Detect Tabesh plugin presence (Custom printing management)
     * 
     * @return bool
     */
    public function has_tabesh(): bool
    {
        // چک کردن وجود افزونه تابش (فرضی)
        // در صورت وجود افزونه واقعی، کلاس یا تابع مشخصی را چک کنید
        return function_exists('tabesh_init') || class_exists('Tabesh_Core');
    }

    /**
     * Get plugin information by slug
     * 
     * @param string $plugin_slug اسلاگ افزونه
     * @return array|null
     */
    public function get_plugin_info(string $plugin_slug): ?array
    {
        $plugins = $this->get_installed_plugins();

        foreach ($plugins as $plugin) {
            if ($plugin['slug'] === $plugin_slug) {
                return $plugin;
            }
        }

        return null;
    }

    /**
     * Get plugin capabilities summary
     * 
     * @param string $plugin_slug اسلاگ افزونه
     * @return array قابلیت‌های افزونه
     */
    public function get_plugin_capabilities(string $plugin_slug): array
    {
        // شناسایی قابلیت‌های شناخته شده افزونه‌ها
        $known_capabilities = [
            'woocommerce' => [
                'type' => 'ecommerce',
                'features' => ['products', 'orders', 'customers', 'payments'],
                'has_rest_api' => true,
                'has_webhooks' => true,
            ],
            'wordpress-seo' => [
                'type' => 'seo',
                'features' => ['meta_tags', 'sitemaps', 'redirects'],
                'has_rest_api' => false,
                'has_webhooks' => false,
            ],
            'contact-form-7' => [
                'type' => 'forms',
                'features' => ['contact_forms', 'submissions'],
                'has_rest_api' => false,
                'has_webhooks' => false,
            ],
        ];

        return $known_capabilities[$plugin_slug] ?? [
            'type' => 'unknown',
            'features' => [],
            'has_rest_api' => false,
            'has_webhooks' => false,
        ];
    }

    /**
     * Scan for custom database tables created by plugins
     * 
     * @param string $plugin_slug اسلاگ افزونه
     * @return array لیست جداول
     */
    public function scan_plugin_tables(string $plugin_slug): array
    {
        global $wpdb;

        // جستجوی جداول با پیشوند مرتبط با افزونه
        $search_prefix = $wpdb->prefix . str_replace('-', '_', $plugin_slug);
        
        $tables = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $wpdb->esc_like($search_prefix) . '%'
            ),
            ARRAY_N
        );

        $table_names = [];
        foreach ($tables as $table) {
            $table_names[] = $table[0];
        }

        return $table_names;
    }

    /**
     * Get plugin options from wp_options table
     * 
     * @param string $plugin_slug اسلاگ افزونه
     * @return array تنظیمات افزونه
     */
    public function get_plugin_options(string $plugin_slug): array
    {
        global $wpdb;

        // جستجوی تنظیمات با نام‌های مرتبط با افزونه
        $search_pattern = '%' . $wpdb->esc_like($plugin_slug) . '%';
        
        $options = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 50",
                $search_pattern
            ),
            ARRAY_A
        );

        $plugin_options = [];
        foreach ($options as $option) {
            // حذف داده‌های حساس (کلیدهای API، پسوردها) - اولیه
            if ($this->is_sensitive_option($option['option_name'])) {
                continue;
            }

            $plugin_options[$option['option_name']] = $option['option_value'];
        }

        // فیلتر پیشرفته با Sanitizer (Commit 5)
        $sanitizer = new HT_Safety_Data_Sanitizer();
        return $sanitizer->filter_plugin_options($plugin_options);
    }

    /**
     * Check if option name contains sensitive data
     * 
     * @param string $option_name نام تنظیم
     * @return bool
     */
    private function is_sensitive_option(string $option_name): bool
    {
        $sensitive_keywords = [
            'password',
            'api_key',
            'secret',
            'token',
            'private_key',
            'access_key',
        ];

        $option_lower = strtolower($option_name);

        foreach ($sensitive_keywords as $keyword) {
            if (strpos($option_lower, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get summary of monitored plugins for AI context
     * 
     * @return string خلاصه متنی برای AI
     */
    public function get_monitoring_summary(): string
    {
        $monitored = $this->get_monitored_plugins_details();

        if (empty($monitored)) {
            return "هیچ افزونه‌ای تحت نظارت نیست.";
        }

        $summary = "افزونه‌های تحت نظارت هما:\n\n";

        foreach ($monitored as $plugin) {
            $status = $plugin['is_active'] ? 'فعال' : 'غیرفعال';
            $summary .= "- {$plugin['name']} (نسخه {$plugin['version']}) - {$status}\n";
        }

        return $summary;
    }
}
