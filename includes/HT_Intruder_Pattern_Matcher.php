<?php
/**
 * Intruder Pattern Matcher - Security Detection System
 *
 * @package HomayeTabesh
 * @since PR15
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * سیستم تشخیص مهاجم و رفتارهای مشکوک
 * Intruder Detection System (IDS)
 */
class HT_Intruder_Pattern_Matcher
{
    /**
     * Suspicious file patterns (security vulnerabilities)
     */
    private const SUSPICIOUS_FILES = [
        'wp-config.php',
        'wp-config.php~',
        '.env',
        '.git/config',
        'phpinfo.php',
        '.htaccess',
        'install.php',
        'xmlrpc.php',
    ];

    /**
     * Suspicious query patterns (SQL injection, XSS)
     */
    private const SUSPICIOUS_PATTERNS = [
        'eval(',
        'base64_decode(',
        'gzinflate(',
        'str_rot13(',
        'system(',
        'exec(',
        'shell_exec(',
        'passthru(',
        '../',
        '..\\',
        'UNION SELECT',
        'DROP TABLE',
        'INSERT INTO',
        '<script',
        'javascript:',
        'onerror=',
        'onload=',
        '%3Cscript',
    ];

    /**
     * Admin path patterns without authentication
     */
    private const ADMIN_PATHS = [
        '/wp-admin/',
        '/wp-login.php',
    ];

    /**
     * Scoring thresholds
     */
    private const SCORE_THRESHOLD = 100; // Points needed to be flagged as intruder
    private const SCORE_SUSPICIOUS_FILE = 80;
    private const SCORE_SUSPICIOUS_PATTERN = 60;
    private const SCORE_ADMIN_ACCESS = 40;
    private const SCORE_RAPID_SCANNING = 50;
    private const SCORE_BOT_USER_AGENT = 30;

    /**
     * Last detection reason
     */
    private string $last_detection_reason = '';

    /**
     * Session key for tracking behavior
     */
    private const BEHAVIOR_SESSION_KEY = 'homa_behavior_score';

    /**
     * Check if current request shows suspicious behavior
     *
     * @return bool Is suspicious
     */
    public function is_suspicious_behavior(): bool
    {
        $score = $this->calculate_suspicion_score();
        return $score >= self::SCORE_THRESHOLD;
    }

    /**
     * Calculate suspicion score for current request
     *
     * @return int Suspicion score
     */
    private function calculate_suspicion_score(): int
    {
        $score = 0;
        $reasons = [];

        // Check request URI for suspicious files
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        foreach (self::SUSPICIOUS_FILES as $file) {
            if (stripos($request_uri, $file) !== false) {
                $score += self::SCORE_SUSPICIOUS_FILE;
                $reasons[] = "Attempted access to sensitive file: {$file}";
                break;
            }
        }

        // Check for suspicious patterns in URI and query string
        $full_request = $request_uri . ($_SERVER['QUERY_STRING'] ?? '');
        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (stripos($full_request, $pattern) !== false) {
                $score += self::SCORE_SUSPICIOUS_PATTERN;
                $reasons[] = "Suspicious pattern detected: {$pattern}";
                break;
            }
        }

        // Check for unauthorized admin access
        if ($this->is_unauthorized_admin_access()) {
            $score += self::SCORE_ADMIN_ACCESS;
            $reasons[] = "Unauthorized admin area access attempt";
        }

        // Check for rapid page scanning
        if ($this->is_rapid_scanning()) {
            $score += self::SCORE_RAPID_SCANNING;
            $reasons[] = "Rapid page scanning detected";
        }

        // Check for bot/malicious user agents
        if ($this->has_suspicious_user_agent()) {
            $score += self::SCORE_BOT_USER_AGENT;
            $reasons[] = "Suspicious user agent detected";
        }

        // Check POST data for injection attempts
        if ($this->has_suspicious_post_data()) {
            $score += self::SCORE_SUSPICIOUS_PATTERN;
            $reasons[] = "Suspicious POST data detected";
        }

        // Store detection reason
        if (!empty($reasons)) {
            $this->last_detection_reason = implode('; ', $reasons);
        }

        // Add to session score (cumulative)
        $session_score = $this->get_session_behavior_score();
        $total_score = $session_score + $score;
        $this->update_session_behavior_score($total_score);

        return $total_score;
    }

    /**
     * Check if user is trying to access admin area without authentication
     *
     * @return bool Is unauthorized admin access
     */
    private function is_unauthorized_admin_access(): bool
    {
        if (is_user_logged_in()) {
            return false;
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        foreach (self::ADMIN_PATHS as $path) {
            if (stripos($request_uri, $path) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is performing rapid page scanning
     *
     * @return bool Is rapid scanning
     */
    private function is_rapid_scanning(): bool
    {
        $ip = $this->get_client_ip();
        $cache_key = 'homa_request_count_' . md5($ip);
        
        $request_data = get_transient($cache_key);
        
        if ($request_data === false) {
            // First request in window
            set_transient($cache_key, ['count' => 1, 'urls' => [$_SERVER['REQUEST_URI'] ?? '']], 60);
            return false;
        }

        $request_data['count']++;
        $request_data['urls'][] = $_SERVER['REQUEST_URI'] ?? '';
        
        // More than 20 requests in 60 seconds from same IP
        if ($request_data['count'] > 20) {
            // Check if they're accessing different URLs (scanning behavior)
            $unique_urls = array_unique($request_data['urls']);
            if (count($unique_urls) > 10) {
                return true;
            }
        }

        set_transient($cache_key, $request_data, 60);
        return false;
    }

    /**
     * Check for suspicious user agent
     *
     * @return bool Has suspicious user agent
     */
    private function has_suspicious_user_agent(): bool
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Empty user agent is suspicious
        if (empty($user_agent)) {
            return true;
        }

        // Known malicious user agent patterns
        $suspicious_agents = [
            'sqlmap',
            'nikto',
            'nmap',
            'masscan',
            'acunetix',
            'nessus',
            'openvas',
            'metasploit',
            'havij',
        ];

        $user_agent_lower = strtolower($user_agent);
        foreach ($suspicious_agents as $agent) {
            if (stripos($user_agent_lower, $agent) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check POST data for suspicious content
     *
     * @return bool Has suspicious POST data
     */
    private function has_suspicious_post_data(): bool
    {
        if (empty($_POST)) {
            return false;
        }

        $post_string = json_encode($_POST);
        
        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (stripos($post_string, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip(): string
    {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field(trim($ip));
    }

    /**
     * Get session behavior score
     *
     * @return int Session score
     */
    private function get_session_behavior_score(): int
    {
        $ip = $this->get_client_ip();
        $cache_key = 'homa_behavior_score_' . md5($ip);
        
        $score = get_transient($cache_key);
        return is_numeric($score) ? (int)$score : 0;
    }

    /**
     * Update session behavior score
     *
     * @param int $score New score
     * @return void
     */
    private function update_session_behavior_score(int $score): void
    {
        $ip = $this->get_client_ip();
        $cache_key = 'homa_behavior_score_' . md5($ip);
        
        // Store for 1 hour
        set_transient($cache_key, $score, 3600);
    }

    /**
     * Get last detection reason
     *
     * @return string Detection reason
     */
    public function get_last_detection_reason(): string
    {
        return $this->last_detection_reason;
    }

    /**
     * Reset behavior score for current session/IP
     *
     * @return void
     */
    public function reset_behavior_score(): void
    {
        $ip = $this->get_client_ip();
        $cache_key = 'homa_behavior_score_' . md5($ip);
        delete_transient($cache_key);
    }

    /**
     * Check if IP is in whitelist
     *
     * @param string $ip IP address
     * @return bool Is whitelisted
     */
    public function is_ip_whitelisted(string $ip): bool
    {
        $whitelist = get_option('homa_ip_whitelist', []);
        return in_array($ip, $whitelist, true);
    }

    /**
     * Add IP to whitelist
     *
     * @param string $ip IP address
     * @return bool Success
     */
    public function add_to_whitelist(string $ip): bool
    {
        $whitelist = get_option('homa_ip_whitelist', []);
        if (!in_array($ip, $whitelist, true)) {
            $whitelist[] = $ip;
            return update_option('homa_ip_whitelist', $whitelist);
        }
        return true;
    }

    /**
     * Get suspicion score details for debugging
     *
     * @return array Score breakdown
     */
    public function get_score_details(): array
    {
        $score = $this->calculate_suspicion_score();
        
        return [
            'total_score' => $score,
            'threshold' => self::SCORE_THRESHOLD,
            'is_suspicious' => $score >= self::SCORE_THRESHOLD,
            'detection_reason' => $this->last_detection_reason,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        ];
    }
}
