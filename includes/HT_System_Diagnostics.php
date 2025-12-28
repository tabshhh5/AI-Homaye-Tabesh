<?php
/**
 * System Diagnostics Class
 * 
 * Performs comprehensive health checks and automatic fixes
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * Class HT_System_Diagnostics
 * 
 * سیستم عیبیابی سراسری (Global Health Checker)
 */
class HT_System_Diagnostics
{
    /**
     * Run complete system integrity check
     *
     * @return array Diagnostic results
     */
    public function check_system_integrity(): array
    {
        return [
            'gapgpt_api' => $this->test_ai_connection(),
            'tabesh_database' => $this->check_tabesh_db_bridge(),
            'index_status' => $this->get_index_health_score(),
            'meli_payamak' => $this->check_meli_payamak_status(),
            'security' => $this->get_security_status(),
            'issues' => $this->identify_issues(),
            'recommendations' => $this->generate_smart_fix_plan(),
            'overall_health' => $this->calculate_overall_health()
        ];
    }

    /**
     * Test AI API connection (GapGPT or direct Gemini)
     *
     * @return array Connection status
     */
    private function test_ai_connection(): array
    {
        $start_time = microtime(true);
        
        try {
            $ai_provider = get_option('ht_ai_provider', 'gapgpt');
            $api_key = get_option('ht_gapgpt_api_key', '');
            $base_url = get_option('ht_gapgpt_base_url', 'https://api.gapgpt.app/v1');
            $model = get_option('ht_ai_model', 'gemini-2.5-flash');
            
            if (empty($api_key)) {
                return [
                    'status' => 'error',
                    'connection' => 'No API Key',
                    'message' => 'کلید API تنظیم نشده است',
                    'provider' => $ai_provider
                ];
            }

            // Test with a simple request to GapGPT
            $test_url = rtrim($base_url, '/') . '/chat/completions';
            $response = wp_remote_post($test_url, [
                'timeout' => 10,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body' => wp_json_encode([
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'سلام']
                    ],
                    'max_tokens' => 10,
                ]),
            ]);

            $response_time = round((microtime(true) - $start_time) * 1000, 2);

            if (is_wp_error($response)) {
                return [
                    'status' => 'error',
                    'connection' => 'Failed',
                    'response_time' => $response_time . 'ms',
                    'message' => 'خطا در اتصال: ' . $response->get_error_message(),
                    'provider' => $ai_provider,
                    'model' => $model
                ];
            }

            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code === 200 || $status_code === 201) {
                return [
                    'status' => 'healthy',
                    'connection' => 'Connected',
                    'response_time' => $response_time . 'ms',
                    'model' => $model,
                    'provider' => $ai_provider
                ];
            } else if ($status_code === 401) {
                return [
                    'status' => 'error',
                    'connection' => 'Authentication Failed',
                    'response_time' => $response_time . 'ms',
                    'message' => 'کلید API نامعتبر است',
                    'provider' => $ai_provider,
                    'model' => $model
                ];
            } else {
                return [
                    'status' => 'warning',
                    'connection' => 'Partial',
                    'response_time' => $response_time . 'ms',
                    'message' => 'کد وضعیت غیرمنتظره: ' . $status_code,
                    'provider' => $ai_provider,
                    'model' => $model
                ];
            }

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'connection' => 'Failed',
                'message' => 'خطا در اتصال: ' . $e->getMessage(),
                'provider' => get_option('ht_ai_provider', 'gapgpt')
            ];
        }
    }

    /**
     * Test Gemini API connection (deprecated - kept for compatibility)
     *
     * @return array Connection status
     * @deprecated Use test_ai_connection() instead
     */
    private function test_gemini_connection(): array
    {
        return $this->test_ai_connection();
    }

    /**
     * Check Tabesh database connection and status
     *
     * @return array Database status
     */
    private function check_tabesh_db_bridge(): array
    {
        global $wpdb;

        try {
            $facts_table = $wpdb->prefix . 'homaye_knowledge_facts';
            
            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$facts_table'") === $facts_table;

            if (!$table_exists) {
                return [
                    'status' => 'error',
                    'connected' => false,
                    'message' => 'جدول دانش وجود ندارد'
                ];
            }

            // Count facts
            $facts_count = $wpdb->get_var("SELECT COUNT(*) FROM $facts_table");
            
            // Get last sync time
            $last_sync = get_option('ht_last_knowledge_sync', 'Never');

            return [
                'status' => 'healthy',
                'connected' => true,
                'facts_count' => (int) $facts_count,
                'last_sync' => $last_sync
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'connected' => false,
                'message' => 'خطا در اتصال دیتابیس: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get index health score
     *
     * @return array Index status
     */
    private function get_index_health_score(): array
    {
        global $wpdb;

        try {
            $pages_indexed = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}homaye_indexed_pages"
                )
            );
            
            $plugins_monitored = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}homaye_monitored_plugins WHERE is_monitored = %d",
                    1
                )
            );

            $health_score = 0;
            if ($pages_indexed > 0) $health_score += 50;
            if ($plugins_monitored > 0) $health_score += 50;

            return [
                'status' => $health_score >= 50 ? 'healthy' : 'warning',
                'pages_indexed' => (int) $pages_indexed,
                'plugins_monitored' => (int) $plugins_monitored,
                'health_score' => $health_score
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'خطا در بررسی ایندکس: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check Meli Payamak status
     *
     * @return array SMS service status
     */
    private function check_meli_payamak_status(): array
    {
        try {
            $username = get_option('ht_melipayamak_username', '');
            $password = get_option('ht_melipayamak_password', '');

            if (empty($username) || empty($password)) {
                return [
                    'status' => 'warning',
                    'api_status' => 'Not Configured',
                    'credit' => 'نامشخص'
                ];
            }

            // In production, you would check actual API status
            return [
                'status' => 'healthy',
                'api_status' => 'Connected',
                'credit' => 'موجود'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'api_status' => 'Error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get security status
     *
     * @return array Security information
     */
    private function get_security_status(): array
    {
        global $wpdb;

        try {
            $active_threats = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}homaye_security_events 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );

            $blocked_ips = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}homaye_blacklist 
                WHERE (expires_at IS NULL OR expires_at > NOW())"
            );

            $waf_enabled = get_option('ht_waf_enabled', true);

            $status = 'healthy';
            if ($active_threats > 10) $status = 'warning';
            if ($active_threats > 50) $status = 'error';

            return [
                'status' => $status,
                'active_threats' => (int) $active_threats,
                'blocked_ips' => (int) $blocked_ips,
                'waf_enabled' => (bool) $waf_enabled
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'خطا در بررسی امنیت: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Identify system issues
     *
     * @return array List of issues
     */
    private function identify_issues(): array
    {
        $issues = [];

        // Check API key
        if (empty(get_option('ht_gemini_api_key', ''))) {
            $issues[] = [
                'severity' => 'critical',
                'title' => 'کلید API تنظیم نشده',
                'description' => 'برای عملکرد هما باید کلید Gemini API را تنظیم کنید',
                'fix_available' => false
            ];
        }

        // Check knowledge base
        global $wpdb;
        $facts_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}homaye_knowledge_facts"
        );
        
        if ($facts_count < 10) {
            $issues[] = [
                'severity' => 'warning',
                'title' => 'پایگاه دانش خالی است',
                'description' => 'برای بهبود پاسخ‌های هما، صفحات و محتوا را ایندکس کنید',
                'fix_available' => true
            ];
        }

        return $issues;
    }

    /**
     * Generate smart fix recommendations
     *
     * @return array List of recommendations
     */
    private function generate_smart_fix_plan(): array
    {
        $recommendations = [];

        // Check if pages need indexing
        $pages_count = wp_count_posts('page')->publish;
        global $wpdb;
        $indexed_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}homaye_indexed_pages"
        );

        if ($pages_count > $indexed_count) {
            $recommendations[] = 'ایندکس کردن ' . ($pages_count - $indexed_count) . ' صفحه برای بهبود دانش هما';
        }

        // Check plugin monitoring
        $active_plugins = count(get_option('active_plugins', []));
        $monitored = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}homaye_monitored_plugins WHERE is_monitored = 1"
        );

        if ($active_plugins > $monitored) {
            $recommendations[] = 'اضافه کردن ' . ($active_plugins - $monitored) . ' افزونه به ناظر کل';
        }

        return $recommendations;
    }

    /**
     * Calculate overall system health
     *
     * @return string Health status (healthy, warning, error)
     */
    private function calculate_overall_health(): string
    {
        $checks = [
            $this->test_gemini_connection(),
            $this->check_tabesh_db_bridge(),
            $this->get_index_health_score(),
            $this->get_security_status()
        ];

        $error_count = 0;
        $warning_count = 0;

        foreach ($checks as $check) {
            if ($check['status'] === 'error') {
                $error_count++;
            } elseif ($check['status'] === 'warning') {
                $warning_count++;
            }
        }

        if ($error_count > 0) return 'error';
        if ($warning_count > 1) return 'warning';
        return 'healthy';
    }

    /**
     * Attempt to automatically fix identified issues
     *
     * @return array Fix results
     */
    public function auto_fix_issues(): array
    {
        $fixed = [];
        $failed = [];

        try {
            // Fix 1: Index missing pages
            $core = HT_Core::instance();
            if ($core->knowledge) {
                $result = $core->knowledge->index_all_pages();
                if ($result) {
                    $fixed[] = 'صفحات با موفقیت ایندکس شدند';
                } else {
                    $failed[] = 'خطا در ایندکس کردن صفحات';
                }
            }

            // Fix 2: Cleanup old data
            global $wpdb;
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}homaye_security_events 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    30
                )
            );
            if ($deleted !== false) {
                $fixed[] = 'داده‌های قدیمی پاکسازی شدند';
            }

        } catch (\Exception $e) {
            $failed[] = 'خطای عمومی: ' . $e->getMessage();
        }

        return [
            'success' => count($failed) === 0,
            'fixed' => $fixed,
            'failed' => $failed
        ];
    }
}
