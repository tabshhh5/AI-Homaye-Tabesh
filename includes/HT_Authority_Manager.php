<?php
/**
 * Knowledge Authority Manager
 *
 * @package HomayeTabesh
 * @since 1.0.0 (PR17)
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * مدیریت سلسله‌مراتب اعتبار دانش
 * حل تضاد اطلاعات با سیستم اولویت‌بندی
 * 
 * Authority Levels:
 * Level 1 (Highest): Manual Admin Overrides
 * Level 2: Direct Homa Panel Settings
 * Level 3: Live Data from Tabesh & WooCommerce
 * Level 4 (Lowest): General Gemini Knowledge
 */
class HT_Authority_Manager
{
    /**
     * Database table name for manual overrides
     */
    private string $table_name;

    /**
     * Authority level constants
     */
    public const LEVEL_MANUAL_OVERRIDE = 1;
    public const LEVEL_PANEL_SETTINGS = 2;
    public const LEVEL_LIVE_DATA = 3;
    public const LEVEL_GENERAL_KNOWLEDGE = 4;

    /**
     * WooCommerce context provider
     */
    private ?HT_WooCommerce_Context $woo_context = null;

    /**
     * Knowledge base
     */
    private ?HT_Knowledge_Base $knowledge_base = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'homa_authority_overrides';
        
        // Initialize dependencies if available
        if (class_exists('\HomayeTabesh\HT_WooCommerce_Context')) {
            $this->woo_context = new HT_WooCommerce_Context();
        }
        
        if (class_exists('\HomayeTabesh\HT_Knowledge_Base')) {
            $this->knowledge_base = new HT_Knowledge_Base();
        }
    }

    /**
     * Get final fact value with conflict resolution
     *
     * @param string $key Fact key (e.g., 'product_price_101', 'shipping_cost')
     * @param array $context Additional context for decision making
     * @return mixed Final fact value or null if not found
     */
    public function get_final_fact(string $key, array $context = []): mixed
    {
        // Level 1: Manual Admin Override (Highest Priority)
        $manual_override = $this->get_manual_override($key);
        if ($manual_override !== null) {
            $this->log_authority_decision($key, self::LEVEL_MANUAL_OVERRIDE, $manual_override);
            return $manual_override;
        }

        // Level 2: Panel Settings
        $panel_setting = $this->get_panel_setting($key, $context);
        if ($panel_setting !== null) {
            $this->log_authority_decision($key, self::LEVEL_PANEL_SETTINGS, $panel_setting);
            return $panel_setting;
        }

        // Level 3: Live Data from Tabesh & WooCommerce
        $live_data = $this->get_live_data($key, $context);
        if ($live_data !== null) {
            $this->log_authority_decision($key, self::LEVEL_LIVE_DATA, $live_data);
            return $live_data;
        }

        // Level 4: General Knowledge (from Gemini or Knowledge Base)
        $general_knowledge = $this->get_general_knowledge($key, $context);
        if ($general_knowledge !== null) {
            $this->log_authority_decision($key, self::LEVEL_GENERAL_KNOWLEDGE, $general_knowledge);
            return $general_knowledge;
        }

        return null;
    }

    /**
     * Get manual override from database
     *
     * @param string $key Override key
     * @return mixed Override value or null
     */
    private function get_manual_override(string $key): mixed
    {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT override_value, value_type FROM {$this->table_name} 
             WHERE override_key = %s AND is_active = 1 
             ORDER BY updated_at DESC LIMIT 1",
            $key
        ));

        if (!$result) {
            return null;
        }

        return $this->decode_value($result->override_value, $result->value_type);
    }

    /**
     * Get panel setting
     *
     * @param string $key Setting key
     * @param array $context Context data
     * @return mixed Setting value or null
     */
    private function get_panel_setting(string $key, array $context): mixed
    {
        // Check WordPress options for direct settings
        $option_key = 'ht_' . $key;
        $value = get_option($option_key, null);
        
        if ($value === null || $value === false) {
            return null;
        }

        return $value;
    }

    /**
     * Get live data from WooCommerce or other sources
     *
     * @param string $key Data key
     * @param array $context Context data
     * @return mixed Live data or null
     */
    private function get_live_data(string $key, array $context): mixed
    {
        // Parse key to determine data source
        if (str_starts_with($key, 'product_')) {
            return $this->get_product_data($key, $context);
        }

        if (str_starts_with($key, 'order_')) {
            return $this->get_order_data($key, $context);
        }

        if (str_starts_with($key, 'shipping_')) {
            return $this->get_shipping_data($key, $context);
        }

        if (str_starts_with($key, 'user_')) {
            return $this->get_user_data($key, $context);
        }

        return null;
    }

    /**
     * Get product data from WooCommerce
     *
     * @param string $key Product key
     * @param array $context Context data
     * @return mixed Product data or null
     */
    private function get_product_data(string $key, array $context): mixed
    {
        if (!$this->woo_context || !class_exists('WooCommerce')) {
            return null;
        }

        // Extract product ID from key (e.g., 'product_price_101' -> 101)
        if (preg_match('/product_(\w+)_(\d+)/', $key, $matches)) {
            $attribute = $matches[1];
            $product_id = (int)$matches[2];
            
            $product = wc_get_product($product_id);
            if (!$product) {
                return null;
            }

            switch ($attribute) {
                case 'price':
                    return $product->get_price();
                case 'stock':
                    return $product->get_stock_quantity();
                case 'name':
                    return $product->get_name();
                case 'description':
                    return $product->get_description();
                default:
                    return null;
            }
        }

        return null;
    }

    /**
     * Get order data
     *
     * @param string $key Order key
     * @param array $context Context data
     * @return mixed Order data or null
     */
    private function get_order_data(string $key, array $context): mixed
    {
        if (!class_exists('WooCommerce')) {
            return null;
        }

        // Extract order ID from key
        if (preg_match('/order_(\w+)_(\d+)/', $key, $matches)) {
            $attribute = $matches[1];
            $order_id = (int)$matches[2];
            
            $order = wc_get_order($order_id);
            if (!$order) {
                return null;
            }

            switch ($attribute) {
                case 'status':
                    return $order->get_status();
                case 'total':
                    return $order->get_total();
                case 'date':
                    return $order->get_date_created()->date('Y-m-d H:i:s');
                default:
                    return null;
            }
        }

        return null;
    }

    /**
     * Get shipping data
     *
     * @param string $key Shipping key
     * @param array $context Context data
     * @return mixed Shipping data or null
     */
    private function get_shipping_data(string $key, array $context): mixed
    {
        // Implement shipping data retrieval
        // This can be extended to fetch from shipping provider APIs
        return null;
    }

    /**
     * Get user data
     *
     * @param string $key User key
     * @param array $context Context data
     * @return mixed User data or null
     */
    private function get_user_data(string $key, array $context): mixed
    {
        // Extract user ID from key
        if (preg_match('/user_(\w+)_(\d+)/', $key, $matches)) {
            $attribute = $matches[1];
            $user_id = (int)$matches[2];
            
            $user = get_user_by('ID', $user_id);
            if (!$user) {
                return null;
            }

            switch ($attribute) {
                case 'name':
                    return $user->display_name;
                case 'email':
                    return $user->user_email;
                case 'role':
                    return $user->roles[0] ?? null;
                default:
                    return null;
            }
        }

        return null;
    }

    /**
     * Get general knowledge from knowledge base
     *
     * @param string $key Knowledge key
     * @param array $context Context data
     * @return mixed Knowledge value or null
     */
    private function get_general_knowledge(string $key, array $context): mixed
    {
        if (!$this->knowledge_base) {
            return null;
        }

        // Try to find in knowledge base files
        $kb_types = ['products', 'pricing', 'faq', 'responses'];
        
        foreach ($kb_types as $type) {
            $rules = $this->knowledge_base->load_rules($type);
            if (isset($rules[$key])) {
                return $rules[$key];
            }
        }

        return null;
    }

    /**
     * Set manual override
     *
     * @param string $key Override key
     * @param mixed $value Override value
     * @param string $reason Reason for override
     * @param int $admin_user_id Admin user ID who set the override
     * @return bool Success
     */
    public function set_manual_override(string $key, mixed $value, string $reason = '', int $admin_user_id = 0): bool
    {
        global $wpdb;

        $value_type = $this->detect_value_type($value);
        $encoded_value = $this->encode_value($value, $value_type);

        $result = $wpdb->insert(
            $this->table_name,
            [
                'override_key' => $key,
                'override_value' => $encoded_value,
                'value_type' => $value_type,
                'reason' => $reason,
                'admin_user_id' => $admin_user_id,
                'is_active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );

        if ($result) {
            do_action('homa_manual_override_set', $key, $value, $reason, $admin_user_id);
        }

        return $result !== false;
    }

    /**
     * Remove manual override
     *
     * @param string $key Override key
     * @return bool Success
     */
    public function remove_manual_override(string $key): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            ['is_active' => 0, 'updated_at' => current_time('mysql')],
            ['override_key' => $key],
            ['%d', '%s'],
            ['%s']
        );

        if ($result) {
            do_action('homa_manual_override_removed', $key);
        }

        return $result !== false;
    }

    /**
     * Get all manual overrides
     *
     * @param bool $active_only Get only active overrides
     * @return array List of overrides
     */
    public function get_all_overrides(bool $active_only = true): array
    {
        global $wpdb;

        $where = $active_only ? 'WHERE is_active = 1' : '';
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} {$where} ORDER BY updated_at DESC"
        );

        $overrides = [];
        foreach ($results as $row) {
            $overrides[] = [
                'id' => $row->id,
                'key' => $row->override_key,
                'value' => $this->decode_value($row->override_value, $row->value_type),
                'value_type' => $row->value_type,
                'reason' => $row->reason,
                'admin_user_id' => $row->admin_user_id,
                'is_active' => $row->is_active,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        return $overrides;
    }

    /**
     * Detect value type
     *
     * @param mixed $value Value to detect
     * @return string Value type
     */
    private function detect_value_type(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return 'json';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        return 'string';
    }

    /**
     * Encode value for storage
     *
     * @param mixed $value Value to encode
     * @param string $type Value type
     * @return string Encoded value
     */
    private function encode_value(mixed $value, string $type): string
    {
        if ($type === 'json') {
            return json_encode($value);
        }
        if ($type === 'boolean') {
            return $value ? '1' : '0';
        }
        return (string)$value;
    }

    /**
     * Decode value from storage
     *
     * @param string $value Encoded value
     * @param string $type Value type
     * @return mixed Decoded value
     */
    private function decode_value(string $value, string $type): mixed
    {
        switch ($type) {
            case 'json':
                return json_decode($value, true);
            case 'integer':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'boolean':
                return (bool)$value;
            default:
                return $value;
        }
    }

    /**
     * Log authority decision for debugging
     *
     * @param string $key Fact key
     * @param int $level Authority level used
     * @param mixed $value Final value
     * @return void
     */
    private function log_authority_decision(string $key, int $level, mixed $value): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $level_names = [
                self::LEVEL_MANUAL_OVERRIDE => 'Manual Override',
                self::LEVEL_PANEL_SETTINGS => 'Panel Settings',
                self::LEVEL_LIVE_DATA => 'Live Data',
                self::LEVEL_GENERAL_KNOWLEDGE => 'General Knowledge',
            ];

            error_log(sprintf(
                'Homa Authority Decision - Key: %s, Level: %s, Value: %s',
                $key,
                $level_names[$level] ?? 'Unknown',
                is_scalar($value) ? $value : json_encode($value)
            ));
        }
    }

    /**
     * Create database table for manual overrides
     *
     * @return void
     */
    public function create_table(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            override_key varchar(255) NOT NULL,
            override_value text NOT NULL,
            value_type varchar(20) DEFAULT 'string',
            reason text,
            admin_user_id bigint(20) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY override_key (override_key),
            KEY is_active (is_active),
            KEY admin_user_id (admin_user_id),
            KEY updated_at (updated_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get authority level name
     *
     * @param int $level Authority level
     * @return string Level name
     */
    public static function get_level_name(int $level): string
    {
        $names = [
            self::LEVEL_MANUAL_OVERRIDE => __('Manual Override', 'homaye-tabesh'),
            self::LEVEL_PANEL_SETTINGS => __('Panel Settings', 'homaye-tabesh'),
            self::LEVEL_LIVE_DATA => __('Live Data', 'homaye-tabesh'),
            self::LEVEL_GENERAL_KNOWLEDGE => __('General Knowledge', 'homaye-tabesh'),
        ];

        return $names[$level] ?? __('Unknown', 'homaye-tabesh');
    }
}
