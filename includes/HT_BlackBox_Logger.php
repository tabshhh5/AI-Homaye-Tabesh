<?php
/**
 * BlackBox Logger - Advanced Logging System
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * سیستم جعبه سیاه برای ثبت دقیق تراکنشهای AI
 * شامل: پرامپت، پاسخ، latency، توکن، خطاها
 */
class HT_BlackBox_Logger
{
    /**
     * Database table name
     */
    private string $table_name;

    /**
     * Log retention days
     */
    private const LOG_RETENTION_DAYS = 30;

    /**
     * Sensitive data patterns for masking
     */
    private const SENSITIVE_PATTERNS = [
        'credit_card' => '/\b\d{4}[\s\-]?\d{4}[\s\-]?\d{4}[\s\-]?\d{4}\b/',
        'national_id' => '/\b\d{10}\b/',
        'phone' => '/\b09\d{9}\b/',
        'email' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        'password' => '/(password|رمز|پسورد|گذرواژه)[\s:=]+([^\s]+)/i',
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'homa_blackbox_logs';
    }

    /**
     * Create database table
     */
    public function create_table(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            log_type varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            user_identifier varchar(255) DEFAULT NULL,
            user_prompt text,
            raw_prompt text,
            ai_response text,
            raw_response text,
            latency_ms int DEFAULT NULL,
            tokens_used int DEFAULT NULL,
            model_name varchar(100) DEFAULT NULL,
            context_data longtext,
            error_message text,
            error_trace text,
            environment_state longtext,
            request_method varchar(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            status varchar(20) DEFAULT 'success',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY log_type (log_type),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Log AI transaction
     *
     * @param array $data Transaction data
     * @return int|false Log ID or false on failure
     */
    public function log_ai_transaction(array $data): int|false
    {
        // Use try-catch to prevent errors from cascading
        try {
            global $wpdb;

            $defaults = [
                'log_type' => 'ai_transaction',
                'user_id' => $this->safe_get_user_id(),
                'user_identifier' => $this->get_user_identifier(),
                'user_prompt' => '',
                'raw_prompt' => '',
                'ai_response' => '',
                'raw_response' => '',
                'latency_ms' => null,
                'tokens_used' => null,
                'model_name' => 'gemini-2.0-flash-exp',
                'context_data' => null,
                'error_message' => null,
                'error_trace' => null,
                'environment_state' => null,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'status' => 'success',
            ];

            $log_data = wp_parse_args($data, $defaults);

            // Mask sensitive data
            $log_data['user_prompt'] = $this->mask_sensitive_data($log_data['user_prompt']);
            $log_data['ai_response'] = $this->mask_sensitive_data($log_data['ai_response']);

            // Serialize complex data - use simple json_encode instead of wp_json_encode
            if (is_array($log_data['context_data'])) {
                $log_data['context_data'] = json_encode($log_data['context_data']);
            }
            if (is_array($log_data['environment_state'])) {
                $log_data['environment_state'] = json_encode($log_data['environment_state']);
            }

            $result = $wpdb->insert(
                $this->table_name,
                $log_data,
                [
                    '%s', // log_type
                    '%d', // user_id
                    '%s', // user_identifier
                    '%s', // user_prompt
                    '%s', // raw_prompt
                    '%s', // ai_response
                    '%s', // raw_response
                    '%d', // latency_ms
                    '%d', // tokens_used
                    '%s', // model_name
                    '%s', // context_data
                    '%s', // error_message
                    '%s', // error_trace
                    '%s', // environment_state
                    '%s', // request_method
                    '%s', // ip_address
                    '%s', // user_agent
                    '%s', // status
                ]
            );

            if ($result === false) {
                // Use pure PHP error_log to avoid triggering HT_Error_Handler recursion
                @error_log('HT_BlackBox_Logger: Failed to insert log - ' . $wpdb->last_error);
                return false;
            }

            return (int) $wpdb->insert_id;
        } catch (\Throwable $e) {
            // Emergency logging without HT_Error_Handler to prevent recursion
            @error_log('HT_BlackBox_Logger: Critical error in log_ai_transaction - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log error with full environment state
     *
     * @param \Exception|\Throwable $exception Exception object
     * @param array $additional_context Additional context data
     * @return int|false Log ID or false on failure
     */
    public function log_error(\Throwable $exception, array $additional_context = []): int|false
    {
        try {
            $environment_state = $this->capture_environment_state();

            $data = [
                'log_type' => 'error',
                'error_message' => $exception->getMessage(),
                'error_trace' => $exception->getTraceAsString(),
                'environment_state' => $environment_state,
                'context_data' => $additional_context,
                'status' => 'error',
            ];

            return $this->log_ai_transaction($data);
        } catch (\Throwable $e) {
            // Emergency logging without recursion
            @error_log('HT_BlackBox_Logger: Failed to log error - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Capture current environment state
     *
     * @return array Environment state data
     */
    private function capture_environment_state(): array
    {
        // Wrap all WordPress function calls in try-catch to prevent cascading errors
        try {
            return [
                'php_version' => PHP_VERSION,
                'wp_version' => function_exists('get_bloginfo') ? get_bloginfo('version') : 'unknown',
                'plugin_version' => HT_VERSION ?? 'unknown',
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'time' => gmdate('Y-m-d H:i:s'), // Use UTC time for consistency
                'timezone' => function_exists('wp_timezone_string') ? wp_timezone_string() : date_default_timezone_get(),
                'is_admin' => function_exists('is_admin') ? is_admin() : false,
                'is_ajax' => function_exists('wp_doing_ajax') ? wp_doing_ajax() : false,
                'is_cron' => function_exists('wp_doing_cron') ? wp_doing_cron() : false,
                'active_plugins' => function_exists('get_option') ? get_option('active_plugins', []) : [],
                'theme' => function_exists('wp_get_theme') ? wp_get_theme()->get('Name') : 'unknown',
                'locale' => function_exists('get_locale') ? get_locale() : 'unknown',
                'site_url' => function_exists('get_site_url') ? get_site_url() : 'unknown',
            ];
        } catch (\Throwable $e) {
            // Return minimal state if WordPress functions fail
            return [
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'time' => gmdate('Y-m-d H:i:s'),
                'error' => 'Failed to capture full state: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Safely get current user ID without triggering errors
     *
     * @return int|null User ID or null
     */
    private function safe_get_user_id(): ?int
    {
        try {
            if (!function_exists('get_current_user_id')) {
                return null;
            }
            $user_id = get_current_user_id();
            return $user_id > 0 ? $user_id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Mask sensitive data in text
     *
     * @param string $text Text to mask
     * @return string Masked text
     */
    private function mask_sensitive_data(string $text): string
    {
        foreach (self::SENSITIVE_PATTERNS as $type => $pattern) {
            $text = preg_replace_callback($pattern, function($matches) use ($type) {
                if ($type === 'password' && isset($matches[2])) {
                    return $matches[1] . ': ****';
                }
                // Use consistent mask length to prevent length-based disclosure
                return '********';
            }, $text);
        }

        return $text;
    }

    /**
     * Get user identifier (session or fingerprint)
     *
     * @return string User identifier
     */
    private function get_user_identifier(): string
    {
        try {
            $user_id = $this->safe_get_user_id();
            if ($user_id) {
                return 'user_' . $user_id;
            }

            // Generate fingerprint for guests
            $components = [
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
                $this->get_client_ip(),
            ];

            return 'guest_' . md5(implode('|', $components));
        } catch (\Throwable $e) {
            // Fallback to simple identifier
            return 'guest_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        }
    }

    /**
     * Get client IP address
     *
     * @return string Client IP
     */
    private function get_client_ip(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }

        return 'unknown';
    }

    /**
     * Get logs with filters
     *
     * @param array $filters Filter criteria
     * @return array Logs
     */
    public function get_logs(array $filters = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $values = [];

        if (!empty($filters['log_type'])) {
            $where[] = 'log_type = %s';
            $values[] = $filters['log_type'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $values[] = $filters['status'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $values[] = $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $values[] = $filters['date_to'];
        }

        $limit = absint($filters['limit'] ?? 100);
        $offset = absint($filters['offset'] ?? 0);

        $where_sql = implode(' AND ', $where);
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = $limit;
        $values[] = $offset;

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        $results = $wpdb->get_results($query, ARRAY_A);

        // Decode JSON fields
        foreach ($results as &$result) {
            if (!empty($result['context_data'])) {
                $result['context_data'] = json_decode($result['context_data'], true);
            }
            if (!empty($result['environment_state'])) {
                $result['environment_state'] = json_decode($result['environment_state'], true);
            }
        }

        return $results;
    }

    /**
     * Get statistics
     *
     * @return array Statistics data
     */
    public function get_statistics(): array
    {
        global $wpdb;

        $stats = [];

        // Total logs
        $stats['total_logs'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        // Success/Error ratio
        $stats['success_count'] = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s", 'success')
        );
        $stats['error_count'] = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s", 'error')
        );

        // Average latency
        $stats['avg_latency_ms'] = (float) $wpdb->get_var(
            "SELECT AVG(latency_ms) FROM {$this->table_name} WHERE latency_ms IS NOT NULL"
        );

        // Total tokens used
        $stats['total_tokens'] = (int) $wpdb->get_var(
            "SELECT SUM(tokens_used) FROM {$this->table_name} WHERE tokens_used IS NOT NULL"
        );

        // Logs by type
        $stats['logs_by_type'] = $wpdb->get_results(
            "SELECT log_type, COUNT(*) as count FROM {$this->table_name} GROUP BY log_type",
            ARRAY_A
        );

        // Recent errors
        $stats['recent_errors'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT created_at, error_message FROM {$this->table_name} WHERE status = %s ORDER BY created_at DESC LIMIT 5",
                'error'
            ),
            ARRAY_A
        );

        return $stats;
    }

    /**
     * Clean old logs (older than retention period)
     *
     * @return int Number of deleted logs
     */
    public function clean_old_logs(): int
    {
        global $wpdb;

        $retention_date = date('Y-m-d H:i:s', strtotime('-' . self::LOG_RETENTION_DAYS . ' days'));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $retention_date
            )
        );

        return (int) $deleted;
    }

    /**
     * Schedule automatic cleanup
     */
    public function schedule_cleanup(): void
    {
        if (!wp_next_scheduled('ht_blackbox_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ht_blackbox_cleanup');
        }
    }

    /**
     * Unschedule automatic cleanup
     */
    public function unschedule_cleanup(): void
    {
        $timestamp = wp_next_scheduled('ht_blackbox_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ht_blackbox_cleanup');
        }
    }
}
