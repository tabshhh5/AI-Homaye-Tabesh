<?php
/**
 * Query Optimizer - Database Caching & Performance
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * بهینهساز کوئریهای دیتابیس با استفاده از کش و ایندکسگذاری
 */
class HT_Query_Optimizer
{
    /**
     * Default cache expiry (seconds)
     */
    private const DEFAULT_CACHE_EXPIRY = 600; // 10 minutes

    /**
     * Cache key prefix
     */
    private const CACHE_PREFIX = 'ht_query_cache_';

    /**
     * Knowledge base instance
     */
    private ?HT_Knowledge_Base $knowledge_base = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        if (class_exists('\HomayeTabesh\HT_Knowledge_Base')) {
            $this->knowledge_base = new HT_Knowledge_Base();
        }
    }

    /**
     * Get cached query result or execute and cache
     *
     * @param string $cache_key Unique cache key
     * @param callable $callback Function to execute if cache miss
     * @param int $expiry Cache expiry in seconds
     * @return mixed Query result
     */
    public function get_cached($cache_key, callable $callback, int $expiry = self::DEFAULT_CACHE_EXPIRY)
    {
        $full_key = self::CACHE_PREFIX . $cache_key;

        // Try to get from cache
        $cached = get_transient($full_key);
        if ($cached !== false) {
            return $cached;
        }

        // Execute callback
        $result = $callback();

        // Store in cache
        if ($result !== false && $result !== null) {
            set_transient($full_key, $result, $expiry);
        }

        return $result;
    }

    /**
     * Get knowledge facts with caching
     *
     * @param array $filters Filter criteria
     * @param int $cache_duration Cache duration in seconds
     * @return array Knowledge facts
     */
    public function get_cached_knowledge(array $filters = [], int $cache_duration = self::DEFAULT_CACHE_EXPIRY): array
    {
        if (!$this->knowledge_base) {
            return [];
        }

        $cache_key = 'knowledge_' . md5(serialize($filters));

        return $this->get_cached($cache_key, function() use ($filters) {
            return $this->knowledge_base->get_facts($filters);
        }, $cache_duration);
    }

    /**
     * Get frequently accessed facts (hot cache)
     *
     * @return array Popular facts
     */
    public function get_hot_facts(): array
    {
        if (!$this->knowledge_base) {
            return [];
        }

        return $this->get_cached('hot_facts', function() {
            return $this->knowledge_base->get_facts([
                'is_active' => 1,
                'orderby' => 'access_count',
                'order' => 'DESC',
                'limit' => 50,
            ]);
        }, 1800); // 30 minutes
    }

    /**
     * Search knowledge with caching
     *
     * @param string $query Search query
     * @param int $limit Result limit
     * @return array Search results
     */
    public function search_cached_knowledge(string $query, int $limit = 10): array
    {
        if (!$this->knowledge_base) {
            return [];
        }

        $cache_key = 'search_' . md5($query . '_' . $limit);

        return $this->get_cached($cache_key, function() use ($query, $limit) {
            return $this->knowledge_base->search_facts($query, $limit);
        }, 300); // 5 minutes for search results
    }

    /**
     * Get product data with caching
     *
     * @param int $product_id Product ID
     * @return array|null Product data
     */
    public function get_cached_product(int $product_id): ?array
    {
        if (!function_exists('wc_get_product')) {
            return null;
        }

        $cache_key = 'product_' . $product_id;

        return $this->get_cached($cache_key, function() use ($product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                return null;
            }

            return [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'stock_status' => $product->get_stock_status(),
                'stock_quantity' => $product->get_stock_quantity(),
                'description' => $product->get_description(),
                'short_description' => $product->get_short_description(),
                'sku' => $product->get_sku(),
                'permalink' => $product->get_permalink(),
            ];
        }, 300); // 5 minutes for product data
    }

    /**
     * Get order data with caching
     *
     * @param int $order_id Order ID
     * @return array|null Order data
     */
    public function get_cached_order(int $order_id): ?array
    {
        if (!function_exists('wc_get_order')) {
            return null;
        }

        $cache_key = 'order_' . $order_id;

        return $this->get_cached($cache_key, function() use ($order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                return null;
            }

            return [
                'id' => $order->get_id(),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'date_created' => $order->get_date_created()->format('Y-m-d H:i:s'),
                'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_email' => $order->get_billing_email(),
                'customer_phone' => $order->get_billing_phone(),
                'items' => $this->get_order_items($order),
            ];
        }, 120); // 2 minutes for order data (more dynamic)
    }

    /**
     * Get order items
     *
     * @param \WC_Order $order Order object
     * @return array Order items
     */
    private function get_order_items($order): array
    {
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
            ];
        }
        return $items;
    }

    /**
     * Invalidate cache by key
     *
     * @param string $cache_key Cache key to invalidate
     * @return bool Success
     */
    public function invalidate_cache(string $cache_key): bool
    {
        $full_key = self::CACHE_PREFIX . $cache_key;
        return delete_transient($full_key);
    }

    /**
     * Invalidate all knowledge caches
     */
    public function invalidate_knowledge_cache(): void
    {
        global $wpdb;

        // Delete all knowledge-related transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . 'knowledge_%'
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . self::CACHE_PREFIX . 'knowledge_%'
            )
        );
    }

    /**
     * Invalidate product cache
     *
     * @param int $product_id Product ID
     */
    public function invalidate_product_cache(int $product_id): void
    {
        $this->invalidate_cache('product_' . $product_id);
    }

    /**
     * Invalidate order cache
     *
     * @param int $order_id Order ID
     */
    public function invalidate_order_cache(int $order_id): void
    {
        $this->invalidate_cache('order_' . $order_id);
    }

    /**
     * Clear all caches
     */
    public function clear_all_caches(): void
    {
        global $wpdb;

        // Delete all plugin-related transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%'
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . self::CACHE_PREFIX . '%'
            )
        );
    }

    /**
     * Optimize database tables
     *
     * @return array Optimization results
     */
    public function optimize_tables(): array
    {
        global $wpdb;

        $results = [];
        
        // Get all Homa tables
        $tables = $wpdb->get_col(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $wpdb->prefix . 'homa_%'
            )
        );

        foreach ($tables as $table) {
            $result = $wpdb->query("OPTIMIZE TABLE {$table}");
            $results[$table] = $result !== false ? 'success' : 'failed';
        }

        return $results;
    }

    /**
     * Add database indexes for better performance
     */
    public function add_indexes(): void
    {
        global $wpdb;

        $indexes = [
            // Note: The homaye_knowledge table (wp_homaye_knowledge with prefix) already has 
            // all necessary indexes defined in its CREATE TABLE statement in HT_Activator.php
            // No need to add them again here to avoid "column doesn't exist" errors
            
            // Authority overrides indexes (only if table exists)
            $wpdb->prefix . 'homa_authority_overrides' => [
                ['name' => 'idx_override_key', 'column' => 'override_key'],
                ['name' => 'idx_active', 'column' => 'is_active'],
            ],
            // Feedback indexes (only if table exists)
            $wpdb->prefix . 'homa_feedback' => [
                ['name' => 'idx_user_id', 'column' => 'user_id'],
                ['name' => 'idx_rating', 'column' => 'rating'],
                ['name' => 'idx_status', 'column' => 'status'],
                ['name' => 'idx_created_at', 'column' => 'created_at'],
            ],
        ];

        foreach ($indexes as $table => $table_indexes) {
            // Check if table exists
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if (!$table_exists) {
                continue;
            }

            foreach ($table_indexes as $index) {
                // Check if index already exists
                $index_exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SHOW INDEX FROM {$table} WHERE Key_name = %s",
                        $index['name']
                    )
                );

                if (!$index_exists) {
                    // Sanitize index and column names
                    $safe_index_name = sanitize_key($index['name']);
                    $safe_column_name = sanitize_key($index['column']);
                    
                    // Validate that table name matches expected pattern
                    if (strpos($table, $wpdb->prefix . 'homa_') === 0) {
                        $wpdb->query("ALTER TABLE {$table} ADD INDEX {$safe_index_name} ({$safe_column_name})");
                    }
                }
            }
        }
    }

    /**
     * Get cache statistics
     *
     * @return array Statistics
     */
    public function get_cache_statistics(): array
    {
        global $wpdb;

        $stats = [];

        // Count cached items
        $stats['total_cached_items'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%'
            )
        );

        // Get cache size
        $stats['cache_size_bytes'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%'
            )
        );

        $stats['cache_size_mb'] = round($stats['cache_size_bytes'] / 1024 / 1024, 2);

        // Get table sizes
        $tables = $wpdb->get_col(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $wpdb->prefix . 'homa_%'
            )
        );

        $stats['table_sizes'] = [];
        foreach ($tables as $table) {
            $size = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
                     FROM information_schema.TABLES 
                     WHERE table_schema = DATABASE() AND table_name = %s",
                    $table
                )
            );
            $stats['table_sizes'][$table] = (float) $size;
        }

        return $stats;
    }

    /**
     * Warm up cache (preload frequently used data)
     */
    public function warmup_cache(): void
    {
        // Preload hot facts
        $this->get_hot_facts();

        // Preload active knowledge
        $this->get_cached_knowledge(['is_active' => 1, 'limit' => 100]);

        // Log warmup event
        if (class_exists('\HomayeTabesh\HT_BlackBox_Logger')) {
            $logger = new HT_BlackBox_Logger();
            $logger->log_ai_transaction([
                'log_type' => 'system_event',
                'error_message' => 'Cache warmed up',
                'status' => 'info',
            ]);
        }
    }

    /**
     * Schedule cache warmup
     */
    public function schedule_warmup(): void
    {
        if (!wp_next_scheduled('ht_cache_warmup')) {
            wp_schedule_event(time(), 'hourly', 'ht_cache_warmup');
        }
    }

    /**
     * Unschedule cache warmup
     */
    public function unschedule_warmup(): void
    {
        $timestamp = wp_next_scheduled('ht_cache_warmup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ht_cache_warmup');
        }
    }
}
