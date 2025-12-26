<?php
/**
 * Auto Cleanup - Self-Optimization System
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * سیستم خود-بهینهسازی برای شناسایی و پاکسازی دادههای تکراری و منقضی
 */
class HT_Auto_Cleanup
{
    /**
     * Knowledge base instance
     */
    private ?HT_Knowledge_Base $knowledge_base = null;

    /**
     * Cleanup reports table
     */
    private string $reports_table;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->reports_table = $wpdb->prefix . 'homa_cleanup_reports';

        if (class_exists('\HomayeTabesh\HT_Knowledge_Base')) {
            $this->knowledge_base = new HT_Knowledge_Base();
        }
    }

    /**
     * Create reports table
     */
    public function create_table(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->reports_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            report_type varchar(50) NOT NULL,
            findings longtext NOT NULL,
            recommendations longtext NOT NULL,
            actions_taken longtext,
            status varchar(20) DEFAULT 'pending',
            reviewed_by bigint(20) DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_type (report_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Run full cleanup analysis
     *
     * @return array Analysis results
     */
    public function run_analysis(): array
    {
        $findings = [];

        // Find duplicate facts
        $duplicates = $this->find_duplicate_facts();
        if (!empty($duplicates)) {
            $findings['duplicates'] = $duplicates;
        }

        // Find stale facts
        $stale = $this->find_stale_facts();
        if (!empty($stale)) {
            $findings['stale'] = $stale;
        }

        // Find outdated price facts
        $outdated_prices = $this->find_outdated_prices();
        if (!empty($outdated_prices)) {
            $findings['outdated_prices'] = $outdated_prices;
        }

        // Find inactive facts
        $inactive = $this->find_inactive_facts();
        if (!empty($inactive)) {
            $findings['inactive'] = $inactive;
        }

        // Check database size
        $db_size = $this->check_database_size();
        $findings['database_size'] = $db_size;

        // Generate recommendations
        $recommendations = $this->generate_recommendations($findings);

        // Save report
        $report_id = $this->save_report('full_analysis', $findings, $recommendations);

        return [
            'report_id' => $report_id,
            'findings' => $findings,
            'recommendations' => $recommendations,
            'severity' => $this->calculate_severity($findings),
        ];
    }

    /**
     * Find duplicate facts
     *
     * @return array Duplicate facts
     */
    private function find_duplicate_facts(): array
    {
        if (!$this->knowledge_base) {
            return [];
        }

        global $wpdb;
        $kb_table = $wpdb->prefix . 'homa_knowledge';

        // Find facts with same key and value
        $duplicates = $wpdb->get_results(
            "SELECT fact_key, fact_value, COUNT(*) as count, GROUP_CONCAT(id) as ids
             FROM {$kb_table}
             WHERE is_active = 1
             GROUP BY fact_key, fact_value
             HAVING count > 1",
            ARRAY_A
        );

        $result = [];
        foreach ($duplicates as $dup) {
            $ids = explode(',', $dup['ids']);
            $result[] = [
                'fact_key' => $dup['fact_key'],
                'fact_value' => $dup['fact_value'],
                'count' => (int) $dup['count'],
                'ids' => array_map('intval', $ids),
                'recommendation' => 'حذف موارد تکراری و نگهداری یک نسخه',
            ];
        }

        return $result;
    }

    /**
     * Find stale facts (not accessed in 90+ days)
     *
     * @return array Stale facts
     */
    private function find_stale_facts(): array
    {
        if (!$this->knowledge_base) {
            return [];
        }

        global $wpdb;
        $kb_table = $wpdb->prefix . 'homa_knowledge';

        $ninety_days_ago = date('Y-m-d H:i:s', strtotime('-90 days'));

        $stale = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, fact_key, fact_value, last_accessed_at, access_count
                 FROM {$kb_table}
                 WHERE is_active = 1
                 AND (last_accessed_at < %s OR last_accessed_at IS NULL)
                 AND access_count < 5
                 ORDER BY last_accessed_at ASC
                 LIMIT 50",
                $ninety_days_ago
            ),
            ARRAY_A
        );

        return array_map(function($fact) {
            return [
                'id' => (int) $fact['id'],
                'fact_key' => $fact['fact_key'],
                'last_accessed' => $fact['last_accessed_at'] ?? 'never',
                'access_count' => (int) $fact['access_count'],
                'recommendation' => 'بررسی و حذف در صورت عدم نیاز',
            ];
        }, $stale);
    }

    /**
     * Find outdated price facts
     *
     * @return array Outdated price facts
     */
    private function find_outdated_prices(): array
    {
        if (!$this->knowledge_base) {
            return [];
        }

        global $wpdb;
        $kb_table = $wpdb->prefix . 'homa_knowledge';

        // Get price-related facts
        $price_facts = $wpdb->get_results(
            "SELECT id, fact_key, fact_value, created_at
             FROM {$kb_table}
             WHERE is_active = 1
             AND (fact_key LIKE '%price%' OR fact_key LIKE '%قیمت%')
             ORDER BY created_at ASC",
            ARRAY_A
        );

        $outdated = [];
        foreach ($price_facts as $fact) {
            // Extract product ID from key
            if (preg_match('/product_(\d+)_price/', $fact['fact_key'], $matches)) {
                $product_id = (int) $matches[1];
                
                // Compare with actual product price
                if (function_exists('wc_get_product')) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $actual_price = $product->get_price();
                        $stored_price = (float) $fact['fact_value'];
                        
                        if (abs($actual_price - $stored_price) > 0.01) {
                            $outdated[] = [
                                'id' => (int) $fact['id'],
                                'fact_key' => $fact['fact_key'],
                                'stored_price' => $stored_price,
                                'actual_price' => $actual_price,
                                'difference' => $actual_price - $stored_price,
                                'recommendation' => 'بروزرسانی قیمت',
                            ];
                        }
                    }
                }
            }
        }

        return $outdated;
    }

    /**
     * Find inactive facts
     *
     * @return array Inactive facts
     */
    private function find_inactive_facts(): array
    {
        if (!$this->knowledge_base) {
            return [];
        }

        global $wpdb;
        $kb_table = $wpdb->prefix . 'homa_knowledge';

        $inactive = $wpdb->get_results(
            "SELECT id, fact_key, fact_value, created_at
             FROM {$kb_table}
             WHERE is_active = 0
             ORDER BY created_at DESC
             LIMIT 20",
            ARRAY_A
        );

        return array_map(function($fact) {
            return [
                'id' => (int) $fact['id'],
                'fact_key' => $fact['fact_key'],
                'created_at' => $fact['created_at'],
                'recommendation' => 'حذف دائمی در صورت عدم نیاز',
            ];
        }, $inactive);
    }

    /**
     * Check database size
     *
     * @return array Size information
     */
    private function check_database_size(): array
    {
        global $wpdb;

        $tables = [
            'homa_knowledge' => 'پایگاه دانش',
            'homa_blackbox_logs' => 'لاگهای جعبه سیاه',
            'homa_feedback' => 'بازخوردها',
            'homa_authority_overrides' => 'اصلاحات دستی',
        ];

        $sizes = [];
        $total_size = 0;

        foreach ($tables as $suffix => $label) {
            $table = $wpdb->prefix . $suffix;
            
            $size = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2)
                     FROM information_schema.TABLES
                     WHERE table_schema = DATABASE() AND table_name = %s",
                    $table
                )
            );

            if ($size !== null) {
                $sizes[$label] = (float) $size;
                $total_size += (float) $size;
            }
        }

        return [
            'tables' => $sizes,
            'total_mb' => round($total_size, 2),
            'recommendation' => $total_size > 100 ? 'بهینهسازی جداول توصیه میشود' : 'حجم مناسب',
        ];
    }

    /**
     * Generate recommendations based on findings
     *
     * @param array $findings Analysis findings
     * @return array Recommendations
     */
    private function generate_recommendations(array $findings): array
    {
        $recommendations = [];

        // Duplicates
        if (!empty($findings['duplicates'])) {
            $count = count($findings['duplicates']);
            $recommendations[] = [
                'priority' => 'high',
                'type' => 'duplicates',
                'message' => "{$count} مورد تکراری یافت شد. حذف آنها باعث بهبود سرعت میشود.",
                'action' => 'remove_duplicates',
            ];
        }

        // Stale facts
        if (!empty($findings['stale'])) {
            $count = count($findings['stale']);
            $recommendations[] = [
                'priority' => 'medium',
                'type' => 'stale',
                'message' => "{$count} فکت قدیمی و کم استفاده یافت شد.",
                'action' => 'review_stale_facts',
            ];
        }

        // Outdated prices
        if (!empty($findings['outdated_prices'])) {
            $count = count($findings['outdated_prices']);
            $recommendations[] = [
                'priority' => 'high',
                'type' => 'outdated_prices',
                'message' => "{$count} قیمت منقضی شده یافت شد که نیاز به بروزرسانی دارد.",
                'action' => 'update_prices',
            ];
        }

        // Database size
        if (isset($findings['database_size']) && $findings['database_size']['total_mb'] > 100) {
            $recommendations[] = [
                'priority' => 'medium',
                'type' => 'database_size',
                'message' => "حجم دیتابیس {$findings['database_size']['total_mb']} مگابایت است. بهینهسازی توصیه میشود.",
                'action' => 'optimize_database',
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate severity level
     *
     * @param array $findings Analysis findings
     * @return string Severity level
     */
    private function calculate_severity(array $findings): string
    {
        $score = 0;

        if (!empty($findings['duplicates'])) {
            $score += count($findings['duplicates']) * 2;
        }
        if (!empty($findings['outdated_prices'])) {
            $score += count($findings['outdated_prices']) * 3;
        }
        if (!empty($findings['stale'])) {
            $score += count($findings['stale']);
        }

        if ($score >= 20) {
            return 'critical';
        } elseif ($score >= 10) {
            return 'high';
        } elseif ($score >= 5) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Save cleanup report
     */
    private function save_report(string $type, array $findings, array $recommendations): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->reports_table,
            [
                'report_type' => $type,
                'findings' => wp_json_encode($findings),
                'recommendations' => wp_json_encode($recommendations),
                'status' => 'pending',
            ],
            ['%s', '%s', '%s', '%s']
        );

        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * Auto-fix safe issues
     *
     * @param int $report_id Report ID
     * @return array Fix results
     */
    public function auto_fix(int $report_id): array
    {
        global $wpdb;

        $report = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->reports_table} WHERE id = %d", $report_id),
            ARRAY_A
        );

        if (!$report) {
            return ['success' => false, 'message' => 'Report not found'];
        }

        $findings = json_decode($report['findings'], true);
        $actions_taken = [];

        // Auto-fix duplicates (keep first, remove rest)
        if (!empty($findings['duplicates'])) {
            foreach ($findings['duplicates'] as $dup) {
                $ids_to_remove = array_slice($dup['ids'], 1); // Keep first
                foreach ($ids_to_remove as $id) {
                    $this->knowledge_base->delete_fact($id);
                    $actions_taken[] = "Removed duplicate fact ID: {$id}";
                }
            }
        }

        // Update report
        $wpdb->update(
            $this->reports_table,
            [
                'actions_taken' => wp_json_encode($actions_taken),
                'status' => 'fixed',
            ],
            ['id' => $report_id],
            ['%s', '%s'],
            ['%d']
        );

        return [
            'success' => true,
            'actions_taken' => $actions_taken,
        ];
    }

    /**
     * Get cleanup reports
     *
     * @param array $filters Filter criteria
     * @return array Reports
     */
    public function get_reports(array $filters = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $values = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $values[] = $filters['status'];
        }

        $limit = absint($filters['limit'] ?? 20);
        $offset = absint($filters['offset'] ?? 0);

        $where_sql = implode(' AND ', $where);
        $query = "SELECT * FROM {$this->reports_table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = $limit;
        $values[] = $offset;

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        $reports = $wpdb->get_results($query, ARRAY_A);

        foreach ($reports as &$report) {
            $report['findings'] = json_decode($report['findings'], true);
            $report['recommendations'] = json_decode($report['recommendations'], true);
            if (!empty($report['actions_taken'])) {
                $report['actions_taken'] = json_decode($report['actions_taken'], true);
            }
        }

        return $reports;
    }

    /**
     * Schedule automatic cleanup analysis
     */
    public function schedule_analysis(): void
    {
        if (!wp_next_scheduled('ht_auto_cleanup_analysis')) {
            wp_schedule_event(time(), 'weekly', 'ht_auto_cleanup_analysis');
        }
    }

    /**
     * Unschedule automatic cleanup analysis
     */
    public function unschedule_analysis(): void
    {
        $timestamp = wp_next_scheduled('ht_auto_cleanup_analysis');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ht_auto_cleanup_analysis');
        }
    }
}
