<?php
/**
 * WAF Core Engine - Web Application Firewall
 *
 * @package HomayeTabesh
 * @since PR16
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * هسته فایروال وب اپلیکیشن
 * Web Application Firewall (WAF) Core Engine
 */
class HT_WAF_Core_Engine
{
    /**
     * Blacklisted IPs table name
     */
    private const TABLE_BLACKLIST = 'homa_ip_blacklist';

    /**
     * Attack patterns for SQL Injection
     */
    private const SQL_PATTERNS = [
        'UNION\s+SELECT',
        'DROP\s+TABLE',
        'INSERT\s+INTO',
        'DELETE\s+FROM',
        'UPDATE\s+.*SET',
        'SELECT\s+.*FROM',
        ';\s*DROP',
        'OR\s+1\s*=\s*1',
        'OR\s+\'1\'\s*=\s*\'1\'',
        '--\s*$',
        '/\*.*\*/',
        'xp_cmdshell',
        'exec\s*\(',
        'execute\s*\(',
    ];

    /**
     * Attack patterns for XSS
     */
    private const XSS_PATTERNS = [
        '<script[^>]*>.*?</script>',
        '<iframe[^>]*>',
        'javascript:',
        'onerror\s*=',
        'onload\s*=',
        'onclick\s*=',
        'onmouseover\s*=',
        '<embed[^>]*>',
        '<object[^>]*>',
        'eval\s*\(',
        'expression\s*\(',
        'vbscript:',
    ];

    /**
     * Attack patterns for RCE (Remote Code Execution)
     */
    private const RCE_PATTERNS = [
        'base64_decode',
        'gzinflate',
        'str_rot13',
        'system\s*\(',
        'exec\s*\(',
        'shell_exec',
        'passthru',
        'proc_open',
        'popen',
        'eval\s*\(',
        'assert\s*\(',
        'create_function',
        'file_get_contents.*php://',
        '\.\./\.\.',
        'php://input',
    ];

    /**
     * Sensitive file patterns
     */
    private const SENSITIVE_FILES = [
        'wp-config\.php',
        '\.env',
        '\.git',
        '\.htaccess',
        'phpinfo\.php',
        'install\.php',
        'xmlrpc\.php',
        'readme\.html',
        'license\.txt',
        '/bin/',
        '/vendor/',
        '/node_modules/',
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Create blacklist table if needed
        add_action('init', [$this, 'maybe_create_blacklist_table'], 5);
        
        // Hook into WordPress request processing
        add_action('init', [$this, 'inspect_request'], 1);
    }

    /**
     * Create IP blacklist table
     *
     * @return void
     */
    public function maybe_create_blacklist_table(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BLACKLIST;
        $charset_collate = $wpdb->get_charset_collate();

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if ($table_exists) {
            return;
        }

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            reason text,
            blocked_at datetime NOT NULL,
            expires_at datetime DEFAULT NULL,
            auto_blocked tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY ip_address (ip_address),
            KEY blocked_at (blocked_at),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Inspect incoming request for malicious patterns
     *
     * @return void
     */
    public function inspect_request(): void
    {
        // Skip for administrators (whitelist)
        if (current_user_can('administrator')) {
            return;
        }

        // Check if IP is blacklisted
        $client_ip = $this->get_client_ip();
        if ($this->is_ip_blacklisted($client_ip)) {
            $this->block_request('IP address is blacklisted');
            return;
        }

        // Check for sensitive file access
        if ($this->is_accessing_sensitive_file()) {
            $this->log_attack('Sensitive File Access', $client_ip);
            $this->increment_threat_score($client_ip, 80);
            $this->block_request('Access to sensitive files is prohibited');
            return;
        }

        // Inspect GET parameters
        if (!empty($_GET)) {
            $this->inspect_parameters($_GET, 'GET', $client_ip);
        }

        // Inspect POST parameters
        if (!empty($_POST)) {
            $this->inspect_parameters($_POST, 'POST', $client_ip);
        }

        // Check for rapid scanning
        if ($this->is_rapid_scanning($client_ip)) {
            $this->log_attack('Rapid Scanning', $client_ip);
            $this->auto_block_ip($client_ip, 'Rapid scanning detected');
        }
    }

    /**
     * Inspect parameters for attack patterns
     *
     * @param array  $params Parameters to inspect
     * @param string $method HTTP method (GET/POST)
     * @param string $client_ip Client IP address
     * @return void
     */
    private function inspect_parameters(array $params, string $method, string $client_ip): void
    {
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $this->inspect_parameters($value, $method, $client_ip);
                continue;
            }

            $value_str = (string) $value;

            // Check SQL Injection
            if ($this->contains_sql_injection($value_str)) {
                $this->log_attack("SQL Injection in {$method}", $client_ip);
                $this->increment_threat_score($client_ip, 60);
                $this->sanitize_parameter($key);
            }

            // Check XSS
            if ($this->contains_xss($value_str)) {
                $this->log_attack("XSS in {$method}", $client_ip);
                $this->increment_threat_score($client_ip, 60);
                $this->sanitize_parameter($key);
            }

            // Check RCE
            if ($this->contains_rce($value_str)) {
                $this->log_attack("RCE Attempt in {$method}", $client_ip);
                $this->increment_threat_score($client_ip, 80);
                $this->block_request('Malicious code execution attempt detected');
            }
        }
    }

    /**
     * Check if value contains SQL injection patterns
     *
     * @param string $value Value to check
     * @return bool
     */
    private function contains_sql_injection(string $value): bool
    {
        foreach (self::SQL_PATTERNS as $pattern) {
            if (preg_match('~' . $pattern . '~i', $value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if value contains XSS patterns
     *
     * @param string $value Value to check
     * @return bool
     */
    private function contains_xss(string $value): bool
    {
        foreach (self::XSS_PATTERNS as $pattern) {
            if (preg_match('~' . $pattern . '~i', $value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if value contains RCE patterns
     *
     * @param string $value Value to check
     * @return bool
     */
    private function contains_rce(string $value): bool
    {
        foreach (self::RCE_PATTERNS as $pattern) {
            if (preg_match('~' . $pattern . '~i', $value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if accessing sensitive file
     *
     * @return bool
     */
    private function is_accessing_sensitive_file(): bool
    {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        foreach (self::SENSITIVE_FILES as $pattern) {
            if (preg_match('~' . $pattern . '~i', $request_uri)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if IP is performing rapid scanning
     *
     * @param string $client_ip Client IP
     * @return bool
     */
    private function is_rapid_scanning(string $client_ip): bool
    {
        $cache_key = 'homa_request_count_' . md5($client_ip);
        $request_count = get_transient($cache_key);
        
        if ($request_count === false) {
            set_transient($cache_key, 1, 60); // 60 seconds window
            return false;
        }
        
        $request_count = (int) $request_count + 1;
        set_transient($cache_key, $request_count, 60);
        
        // More than 20 requests in 60 seconds
        return $request_count > 20;
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip(): string
    {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Get first IP if comma-separated
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Check if IP is blacklisted
     *
     * @param string $ip IP address
     * @return bool
     */
    private function is_ip_blacklisted(string $ip): bool
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BLACKLIST;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
            WHERE ip_address = %s 
            AND (expires_at IS NULL OR expires_at > NOW())",
            $ip
        ));
        
        return (int) $result > 0;
    }

    /**
     * Auto-block IP address
     *
     * @param string $ip IP address
     * @param string $reason Block reason
     * @param int    $duration Duration in hours (0 = permanent)
     * @return bool
     */
    public function auto_block_ip(string $ip, string $reason, int $duration = 24): bool
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BLACKLIST;
        
        $expires_at = $duration > 0 
            ? gmdate('Y-m-d H:i:s', time() + ($duration * 3600))
            : null;
        
        $result = $wpdb->replace(
            $table_name,
            [
                'ip_address' => $ip,
                'reason' => $reason,
                'blocked_at' => current_time('mysql'),
                'expires_at' => $expires_at,
                'auto_blocked' => 1,
            ],
            ['%s', '%s', '%s', '%s', '%d']
        );
        
        return $result !== false;
    }

    /**
     * Increment threat score for IP
     *
     * @param string $ip IP address
     * @param int    $points Points to add
     * @return void
     */
    private function increment_threat_score(string $ip, int $points): void
    {
        $cache_key = 'homa_threat_score_' . md5($ip);
        $current_score = get_transient($cache_key);
        
        if ($current_score === false) {
            $current_score = 0;
        }
        
        $new_score = (int) $current_score + $points;
        set_transient($cache_key, $new_score, 3600); // 1 hour
        
        // Auto-block if score exceeds threshold
        if ($new_score >= 100) {
            $this->auto_block_ip($ip, 'High threat score: ' . $new_score);
        }
    }

    /**
     * Log attack attempt
     *
     * @param string $attack_type Attack type
     * @param string $client_ip Client IP
     * @return void
     */
    private function log_attack(string $attack_type, string $client_ip): void
    {
        // Use existing security alerts system from PR15
        if (class_exists('\HomayeTabesh\HT_Admin_Security_Alerts')) {
            $security_alerts = new HT_Admin_Security_Alerts();
            $security_alerts->log_security_event([
                'event_type' => 'waf_block',
                'ip_address' => $client_ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'detection_reason' => $attack_type,
                'severity' => 'high',
            ]);
        }
    }

    /**
     * Sanitize parameter by removing it
     *
     * @param string $key Parameter key
     * @return void
     */
    private function sanitize_parameter(string $key): void
    {
        unset($_GET[$key], $_POST[$key], $_REQUEST[$key]);
    }

    /**
     * Block request and send 403 response
     *
     * @param string $reason Block reason
     * @return void
     */
    private function block_request(string $reason): void
    {
        status_header(403);
        nocache_headers();
        
        wp_die(
            esc_html__('دسترسی شما به دلیل فعالیت مشکوک مسدود شده است.', 'homaye-tabesh'),
            esc_html__('دسترسی غیرمجاز', 'homaye-tabesh'),
            [
                'response' => 403,
                'back_link' => true,
            ]
        );
    }

    /**
     * Check if user agent is a legitimate search engine bot
     *
     * @return bool
     */
    public function is_legitimate_bot(): bool
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Known legitimate bots
        $legitimate_bots = [
            'Googlebot',
            'Bingbot',
            'Slurp', // Yahoo
            'DuckDuckBot',
            'Baiduspider',
            'YandexBot',
            'facebookexternalhit',
        ];
        
        foreach ($legitimate_bots as $bot) {
            if (stripos($user_agent, $bot) !== false) {
                // Verify by reverse DNS for Google and Bing
                if (in_array($bot, ['Googlebot', 'Bingbot'])) {
                    return $this->verify_bot_by_dns();
                }
                return true;
            }
        }
        
        return false;
    }

    /**
     * Verify bot by reverse DNS lookup
     *
     * @return bool
     */
    private function verify_bot_by_dns(): bool
    {
        $client_ip = $this->get_client_ip();
        
        // Reverse DNS lookup
        $hostname = gethostbyaddr($client_ip);
        
        // Verify domain
        $valid_domains = [
            '.googlebot.com',
            '.google.com',
            '.search.msn.com',
        ];
        
        foreach ($valid_domains as $domain) {
            if (strpos($hostname, $domain) !== false) {
                // Forward DNS lookup to verify
                $forward_ip = gethostbyname($hostname);
                return $forward_ip === $client_ip;
            }
        }
        
        return false;
    }

    /**
     * Unblock IP address
     *
     * @param string $ip IP address
     * @return bool
     */
    public function unblock_ip(string $ip): bool
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BLACKLIST;
        
        $result = $wpdb->delete(
            $table_name,
            ['ip_address' => $ip],
            ['%s']
        );
        
        return $result !== false;
    }

    /**
     * Get blacklisted IPs
     *
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array
     */
    public function get_blacklisted_ips(int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BLACKLIST;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} 
                WHERE expires_at IS NULL OR expires_at > NOW()
                ORDER BY blocked_at DESC 
                LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );
        
        return $results ?: [];
    }

    /**
     * Cleanup expired blacklist entries
     *
     * @return int Number of deleted entries
     */
    public function cleanup_expired_blocks(): int
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BLACKLIST;
        
        $result = $wpdb->query(
            "DELETE FROM {$table_name} 
            WHERE expires_at IS NOT NULL 
            AND expires_at < NOW()"
        );
        
        return (int) $result;
    }
}
