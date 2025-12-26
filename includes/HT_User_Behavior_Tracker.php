<?php
/**
 * User Behavior Tracker - Security Scoring System
 *
 * @package HomayeTabesh
 * @since PR16
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * ردیابی رفتار کاربران و امتیازدهی امنیتی
 * Tracks user behavior and calculates security score
 */
class HT_User_Behavior_Tracker
{
    /**
     * Security events table name
     */
    private const TABLE_BEHAVIOR = 'homa_user_behavior';

    /**
     * Security score thresholds
     */
    private const SCORE_MAX = 100; // Perfect score
    private const SCORE_SUSPICIOUS = 50; // Warning threshold
    private const SCORE_BLOCKED = 20; // Auto-block threshold

    /**
     * Event types and their penalties
     */
    private const EVENT_PENALTIES = [
        'waf_block' => 30,
        'llm_shield_block' => 25,
        'sensitive_file_access' => 35,
        'sql_injection' => 40,
        'xss_attempt' => 35,
        'rce_attempt' => 50,
        'rapid_scanning' => 25,
        'brute_force' => 30,
        '404_spam' => 10,
        'suspicious_query' => 15,
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Create behavior tracking table
        add_action('init', [$this, 'maybe_create_behavior_table'], 5);
    }

    /**
     * Create behavior tracking table
     *
     * @return void
     */
    public function maybe_create_behavior_table(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BEHAVIOR;
        $charset_collate = $wpdb->get_charset_collate();

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if ($table_exists) {
            return;
        }

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_identifier varchar(255) NOT NULL,
            ip_address varchar(45) NOT NULL,
            fingerprint varchar(64) DEFAULT NULL,
            event_type varchar(50) NOT NULL,
            event_data text,
            penalty_points int(11) DEFAULT 0,
            current_score int(11) DEFAULT 100,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_identifier (user_identifier),
            KEY ip_address (ip_address),
            KEY event_type (event_type),
            KEY current_score (current_score),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Get user identifier (user ID or IP + fingerprint)
     *
     * @return string
     */
    public function get_user_identifier(): string
    {
        // Use WordPress user ID if logged in
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }

        // Use IP + browser fingerprint for guests
        $ip = $this->get_client_ip();
        $fingerprint = $this->get_browser_fingerprint();
        
        return 'guest_' . md5($ip . $fingerprint);
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip(): string
    {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
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
     * Get browser fingerprint
     *
     * @return string
     */
    private function get_browser_fingerprint(): string
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $accept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        return md5($user_agent . $accept_language . $accept_encoding);
    }

    /**
     * Get current security score for user
     *
     * @param string|null $user_identifier User identifier (null = current user)
     * @return int Security score (0-100)
     */
    public function get_security_score(?string $user_identifier = null): int
    {
        if ($user_identifier === null) {
            $user_identifier = $this->get_user_identifier();
        }

        // Check transient cache first (5 minutes)
        $cache_key = 'homa_security_score_' . md5($user_identifier);
        $cached_score = get_transient($cache_key);
        
        if ($cached_score !== false) {
            return (int) $cached_score;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BEHAVIOR;

        // Get latest score from database
        $latest_score = $wpdb->get_var($wpdb->prepare(
            "SELECT current_score FROM {$table_name} 
            WHERE user_identifier = %s 
            ORDER BY created_at DESC 
            LIMIT 1",
            $user_identifier
        ));

        $score = $latest_score !== null ? (int) $latest_score : self::SCORE_MAX;
        
        // Cache for 5 minutes
        set_transient($cache_key, $score, 300);
        
        return $score;
    }

    /**
     * Record suspicious activity
     *
     * @param string $user_identifier User identifier
     * @param string $event_type Event type
     * @param int    $custom_penalty Custom penalty (0 = use default)
     * @param array  $event_data Additional event data
     * @return bool Success
     */
    public function record_suspicious_activity(
        string $user_identifier, 
        string $event_type, 
        int $custom_penalty = 0,
        array $event_data = []
    ): bool {
        // Get penalty points
        $penalty = $custom_penalty > 0 
            ? $custom_penalty 
            : (self::EVENT_PENALTIES[$event_type] ?? 10);

        // Get current score
        $current_score = $this->get_security_score($user_identifier);
        
        // Calculate new score
        $new_score = max(0, $current_score - $penalty);

        // Insert event record
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BEHAVIOR;

        $result = $wpdb->insert(
            $table_name,
            [
                'user_identifier' => $user_identifier,
                'ip_address' => $this->get_client_ip(),
                'fingerprint' => $this->get_browser_fingerprint(),
                'event_type' => $event_type,
                'event_data' => !empty($event_data) ? wp_json_encode($event_data) : null,
                'penalty_points' => $penalty,
                'current_score' => $new_score,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
        );

        // Clear cache
        $cache_key = 'homa_security_score_' . md5($user_identifier);
        delete_transient($cache_key);

        // Auto-block if score is too low
        if ($new_score <= self::SCORE_BLOCKED) {
            $this->auto_block_user($user_identifier, "Security score dropped to {$new_score}");
        }

        return $result !== false;
    }

    /**
     * Auto-block user
     *
     * @param string $user_identifier User identifier
     * @param string $reason Block reason
     * @return void
     */
    private function auto_block_user(string $user_identifier, string $reason): void
    {
        // Block IP using WAF
        if (class_exists('\HomayeTabesh\HT_WAF_Core_Engine')) {
            $waf = new HT_WAF_Core_Engine();
            $waf->auto_block_ip($this->get_client_ip(), $reason, 24);
        }

        // Log security event
        if (class_exists('\HomayeTabesh\HT_Admin_Security_Alerts')) {
            $security_alerts = new HT_Admin_Security_Alerts();
            $security_alerts->log_security_event([
                'event_type' => 'user_auto_blocked',
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'detection_reason' => $reason,
                'severity' => 'critical',
            ]);
        }
    }

    /**
     * Check if user is blocked
     *
     * @param string|null $user_identifier User identifier
     * @return bool
     */
    public function is_user_blocked(?string $user_identifier = null): bool
    {
        if ($user_identifier === null) {
            $user_identifier = $this->get_user_identifier();
        }

        $score = $this->get_security_score($user_identifier);
        return $score <= self::SCORE_BLOCKED;
    }

    /**
     * Get user behavior history
     *
     * @param string $user_identifier User identifier
     * @param int    $limit Limit
     * @return array
     */
    public function get_behavior_history(string $user_identifier, int $limit = 20): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BEHAVIOR;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
            WHERE user_identifier = %s 
            ORDER BY created_at DESC 
            LIMIT %d",
            $user_identifier,
            $limit
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Get recent suspicious activities
     *
     * @param int $limit Limit
     * @param int $hours Hours to look back
     * @return array
     */
    public function get_recent_suspicious_activities(int $limit = 50, int $hours = 24): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BEHAVIOR;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL %d HOUR)
            AND current_score < %d
            ORDER BY created_at DESC 
            LIMIT %d",
            $hours,
            self::SCORE_SUSPICIOUS,
            $limit
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Reset user security score
     *
     * @param string $user_identifier User identifier
     * @param string $reason Reset reason
     * @return bool
     */
    public function reset_security_score(string $user_identifier, string $reason = 'Manual reset'): bool
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BEHAVIOR;

        // Insert reset event
        $result = $wpdb->insert(
            $table_name,
            [
                'user_identifier' => $user_identifier,
                'ip_address' => $this->get_client_ip(),
                'fingerprint' => $this->get_browser_fingerprint(),
                'event_type' => 'score_reset',
                'event_data' => wp_json_encode(['reason' => $reason]),
                'penalty_points' => 0,
                'current_score' => self::SCORE_MAX,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
        );

        // Clear cache
        $cache_key = 'homa_security_score_' . md5($user_identifier);
        delete_transient($cache_key);

        return $result !== false;
    }

    /**
     * Get score color (for UI display)
     *
     * @param int $score Security score
     * @return string Color name
     */
    public function get_score_color(int $score): string
    {
        if ($score >= 80) {
            return 'green'; // Good
        } elseif ($score >= 50) {
            return 'orange'; // Warning
        } else {
            return 'red'; // Critical
        }
    }

    /**
     * Get score label (for UI display)
     *
     * @param int $score Security score
     * @return string Label
     */
    public function get_score_label(int $score): string
    {
        if ($score >= 80) {
            return 'ایمن';
        } elseif ($score >= 50) {
            return 'مشکوک';
        } else {
            return 'خطرناک';
        }
    }

    /**
     * Track 404 errors (potential scanning)
     *
     * @return void
     */
    public function track_404_error(): void
    {
        $user_identifier = $this->get_user_identifier();
        
        // Count 404s in last 5 minutes
        $cache_key = 'homa_404_count_' . md5($user_identifier);
        $count = get_transient($cache_key);
        
        if ($count === false) {
            set_transient($cache_key, 1, 300);
        } else {
            $count = (int) $count + 1;
            set_transient($cache_key, $count, 300);
            
            // If more than 10 404s in 5 minutes, penalize
            if ($count > 10) {
                $this->record_suspicious_activity(
                    $user_identifier,
                    '404_spam',
                    15,
                    ['404_count' => $count]
                );
            }
        }
    }

    /**
     * Cleanup old behavior records
     *
     * @param int $days Days to keep
     * @return int Number of deleted records
     */
    public function cleanup_old_records(int $days = 90): int
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BEHAVIOR;

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        return (int) $result;
    }

    /**
     * Get statistics
     *
     * @return array
     */
    public function get_statistics(): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_BEHAVIOR;

        $stats = [
            'total_events' => 0,
            'blocked_users' => 0,
            'suspicious_users' => 0,
            'safe_users' => 0,
            'events_24h' => 0,
            'top_events' => [],
        ];

        // Total events
        $stats['total_events'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name}"
        );

        // Events in last 24 hours
        $stats['events_24h'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        // User counts by score
        $user_scores = $wpdb->get_results(
            "SELECT user_identifier, current_score 
            FROM {$table_name} 
            WHERE id IN (
                SELECT MAX(id) 
                FROM {$table_name} 
                GROUP BY user_identifier
            )",
            ARRAY_A
        );

        foreach ($user_scores as $record) {
            $score = (int) $record['current_score'];
            if ($score <= self::SCORE_BLOCKED) {
                $stats['blocked_users']++;
            } elseif ($score <= self::SCORE_SUSPICIOUS) {
                $stats['suspicious_users']++;
            } else {
                $stats['safe_users']++;
            }
        }

        // Top event types
        $stats['top_events'] = $wpdb->get_results(
            "SELECT event_type, COUNT(*) as count 
            FROM {$table_name} 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY event_type 
            ORDER BY count DESC 
            LIMIT 10",
            ARRAY_A
        );

        return $stats;
    }
}
