<?php
/**
 * Persona Manager - User Scoring and Identity
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * موتور پرسونا و امتیازدهی
 * مدیریت Lead Scoring و Identity Storage
 */
class HT_Persona_Manager
{
    /**
     * Persona types and their thresholds
     */
    private const PERSONA_TYPES = [
        'author' => 100,      // نویسنده/محقق
        'business' => 80,     // کسب‌وکار
        'designer' => 70,     // طراح
        'student' => 50,      // دانشجو
        'general' => 0,       // عمومی
    ];

    /**
     * Dynamic scoring rules based on problem statement
     */
    private const SCORING_RULES = [
        'view_calculator' => ['author' => 10, 'publisher' => 5, 'business' => 5],
        'view_licensing' => ['author' => 20],
        'high_price_stay' => ['business' => 15, 'author' => 10],
        'pricing_table_focus' => ['business' => 12, 'author' => 8],
        'bulk_order_interest' => ['business' => 18],
        'design_specs_view' => ['designer' => 15],
        'student_discount_check' => ['student' => 12],
        'tirage_calculator' => ['author' => 15, 'business' => 10],
        'isbn_search' => ['author' => 20],
        'cart_add_high_value' => ['business' => 15, 'author' => 10],
    ];

    /**
     * Divi Bridge for module detection
     */
    private ?HT_Divi_Bridge $divi_bridge = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize Divi Bridge on first use
    }
    
    /**
     * Get Divi Bridge instance
     *
     * @return HT_Divi_Bridge
     */
    private function get_divi_bridge(): HT_Divi_Bridge
    {
        if ($this->divi_bridge === null) {
            $this->divi_bridge = HT_Core::instance()->divi_bridge;
        }
        return $this->divi_bridge;
    }

    /**
     * Initialize session
     *
     * @return void
     */
    public function init_session(): void
    {
        // Session initialization is no longer needed
        // We use cookies for guest identification
    }

    /**
     * Add score to user's persona with dynamic scoring
     *
     * @param string $user_identifier User identifier
     * @param string $persona_type Persona type
     * @param int $score Score to add
     * @param string $event_type Optional event type for rule-based scoring
     * @param string $element_class Optional element class
     * @param array $element_data Optional element data
     * @return bool Success status
     */
    public function add_score(
        string $user_identifier,
        string $persona_type,
        int $score,
        string $event_type = '',
        string $element_class = '',
        array $element_data = []
    ): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homaye_persona_scores';
        
        // Calculate dynamic score based on rules
        $dynamic_scores = $this->calculate_dynamic_scores(
            $event_type,
            $element_class,
            $element_data
        );
        
        // Apply scores to all relevant personas
        $success = true;
        foreach ($dynamic_scores as $persona => $dynamic_score) {
            $total_score = ($persona === $persona_type) ? $score + $dynamic_score : $dynamic_score;
            
            if ($total_score <= 0) {
                continue;
            }

            // Get existing score
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_identifier = %s AND persona_type = %s",
                $user_identifier,
                $persona
            ));

            if ($existing) {
                // Update existing score
                $result = (bool) $wpdb->update(
                    $table_name,
                    [
                        'score' => $existing->score + $total_score,
                        'updated_at' => current_time('mysql'),
                    ],
                    [
                        'id' => $existing->id,
                    ],
                    ['%d', '%s'],
                    ['%d']
                );
                $success = $success && $result;
            } else {
                // Insert new score
                $result = (bool) $wpdb->insert(
                    $table_name,
                    [
                        'user_identifier' => $user_identifier,
                        'persona_type' => $persona,
                        'score' => $total_score,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ],
                    ['%s', '%s', '%d', '%s', '%s']
                );
                $success = $success && $result;
            }
        }
        
        // Store in transient for fast access
        $this->cache_persona_scores($user_identifier);

        return $success;
    }
    
    /**
     * Calculate dynamic scores based on event and context
     *
     * @param string $event_type Event type
     * @param string $element_class Element class
     * @param array $element_data Element data
     * @return array Persona scores
     */
    private function calculate_dynamic_scores(
        string $event_type,
        string $element_class,
        array $element_data
    ): array {
        $scores = [];
        
        // Get weights from Divi Bridge
        $divi_bridge = $this->get_divi_bridge();
        $weights = $divi_bridge->get_persona_weights($element_class, $element_data);
        
        // Adjust weights based on event type
        $event_multiplier = $this->get_event_multiplier($event_type);
        
        foreach ($weights as $persona => $weight) {
            $scores[$persona] = (int) round($weight * $event_multiplier);
        }
        
        // Apply rule-based scoring
        $rule_key = $this->detect_scoring_rule($element_class, $element_data);
        if ($rule_key && isset(self::SCORING_RULES[$rule_key])) {
            foreach (self::SCORING_RULES[$rule_key] as $persona => $rule_score) {
                $scores[$persona] = ($scores[$persona] ?? 0) + $rule_score;
            }
        }
        
        return $scores;
    }
    
    /**
     * Get event type multiplier
     *
     * @param string $event_type Event type
     * @return float Multiplier
     */
    private function get_event_multiplier(string $event_type): float
    {
        return match ($event_type) {
            'click' => 1.5,
            'long_view' => 1.3,
            'module_dwell' => 1.2,
            'hover' => 0.8,
            'scroll_to' => 0.6,
            default => 1.0,
        };
    }
    
    /**
     * Detect scoring rule from element data
     *
     * @param string $element_class Element class
     * @param array $element_data Element data
     * @return string|null Rule key
     */
    private function detect_scoring_rule(string $element_class, array $element_data): ?string
    {
        $content = mb_strtolower($element_data['text'] ?? '', 'UTF-8');
        $class_lower = mb_strtolower($element_class, 'UTF-8');
        
        // Calculator detection
        if (strpos($content, 'محاسبه') !== false || strpos($class_lower, 'calculator') !== false) {
            if (strpos($content, 'تیراژ') !== false) {
                return 'tirage_calculator';
            }
            return 'view_calculator';
        }
        
        // Licensing detection
        if (strpos($content, 'مجوز') !== false || strpos($content, 'license') !== false) {
            return 'view_licensing';
        }
        
        // ISBN detection
        if (strpos($content, 'isbn') !== false) {
            return 'isbn_search';
        }
        
        // Bulk order detection
        if (strpos($content, 'عمده') !== false || strpos($content, 'انبوه') !== false || 
            strpos($content, 'bulk') !== false) {
            return 'bulk_order_interest';
        }
        
        // Design specs detection
        if (strpos($content, 'cmyk') !== false || strpos($content, 'dpi') !== false || 
            strpos($class_lower, 'design') !== false) {
            return 'design_specs_view';
        }
        
        // Student discount detection
        if (strpos($content, 'دانشجویی') !== false || strpos($content, 'student') !== false) {
            return 'student_discount_check';
        }
        
        // Pricing table detection
        if (strpos($class_lower, 'pricing') !== false) {
            return 'pricing_table_focus';
        }
        
        return null;
    }
    
    /**
     * Cache persona scores in transient
     *
     * @param string $user_identifier User identifier
     * @return void
     */
    private function cache_persona_scores(string $user_identifier): void
    {
        $scores = $this->get_scores($user_identifier);
        $transient_key = 'ht_persona_' . md5($user_identifier);
        set_transient($transient_key, $scores, HOUR_IN_SECONDS);
    }
    
    /**
     * Get cached persona scores
     *
     * @param string $user_identifier User identifier
     * @return array|null Cached scores or null
     */
    private function get_cached_scores(string $user_identifier): ?array
    {
        $transient_key = 'ht_persona_' . md5($user_identifier);
        $cached = get_transient($transient_key);
        return $cached !== false ? $cached : null;
    }

    /**
     * Get user's persona scores
     *
     * @param string $user_identifier User identifier
     * @return array Persona scores
     */
    public function get_scores(string $user_identifier): array
    {
        // Try to get from cache first
        $cached = $this->get_cached_scores($user_identifier);
        if ($cached !== null) {
            return $cached;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'homaye_persona_scores';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT persona_type, score FROM $table_name WHERE user_identifier = %s ORDER BY score DESC",
            $user_identifier
        ), ARRAY_A);

        $scores = [];
        foreach ($results as $row) {
            $scores[$row['persona_type']] = (int) $row['score'];
        }
        
        // Cache the results if not empty
        if (!empty($scores)) {
            $transient_key = 'ht_persona_' . md5($user_identifier);
            set_transient($transient_key, $scores, HOUR_IN_SECONDS);
        }

        return $scores;
    }

    /**
     * Get dominant persona for user
     *
     * @param string $user_identifier User identifier
     * @return array Persona data with type and score
     */
    public function get_dominant_persona(string $user_identifier): array
    {
        $scores = $this->get_scores($user_identifier);

        if (empty($scores)) {
            return [
                'type' => 'general',
                'score' => 0,
                'confidence' => 0,
            ];
        }

        arsort($scores);
        $dominant_type = array_key_first($scores);
        $dominant_score = $scores[$dominant_type];

        // Calculate confidence based on score threshold
        $threshold = self::PERSONA_TYPES[$dominant_type] ?? 0;
        $confidence = $threshold > 0 ? min(100, ($dominant_score / $threshold) * 100) : 0;

        return [
            'type' => $dominant_type,
            'score' => $dominant_score,
            'confidence' => round($confidence, 2),
            'all_scores' => $scores,
        ];
    }

    /**
     * Store session data for user
     *
     * @param string $user_identifier User identifier
     * @param array $data Session data
     * @return bool Success status
     */
    public function store_session_data(string $user_identifier, array $data): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homaye_persona_scores';

        // Get dominant persona
        $persona = $this->get_dominant_persona($user_identifier);

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_identifier = %s AND persona_type = %s",
            $user_identifier,
            $persona['type']
        ));

        if ($existing) {
            return (bool) $wpdb->update(
                $table_name,
                [
                    'session_data' => wp_json_encode($data),
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $existing->id],
                ['%s', '%s'],
                ['%d']
            );
        }

        return false;
    }

    /**
     * Get session data for user
     *
     * @param string $user_identifier User identifier
     * @return array Session data
     */
    public function get_session_data(string $user_identifier): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homaye_persona_scores';

        $persona = $this->get_dominant_persona($user_identifier);

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT session_data FROM $table_name WHERE user_identifier = %s AND persona_type = %s",
            $user_identifier,
            $persona['type']
        ));

        if ($result) {
            $data = json_decode($result, true);
            return is_array($data) ? $data : [];
        }

        return [];
    }

    /**
     * Get user behavior summary for AI context
     *
     * @param string $user_identifier User identifier
     * @param int $limit Event limit
     * @return string Behavior summary
     */
    public function get_behavior_summary(string $user_identifier, int $limit = 20): string
    {
        global $wpdb;

        $events_table = $wpdb->prefix . 'homaye_telemetry_events';

        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, element_class, timestamp 
             FROM $events_table 
             WHERE user_identifier = %s 
             ORDER BY timestamp DESC 
             LIMIT %d",
            $user_identifier,
            $limit
        ), ARRAY_A);

        if (empty($events)) {
            return 'کاربر جدید بدون تاریخچه رفتاری';
        }

        $summary = "رفتارهای اخیر کاربر:\n";
        $event_counts = [];

        foreach ($events as $event) {
            $key = $event['event_type'] . '_' . $event['element_class'];
            $event_counts[$key] = ($event_counts[$key] ?? 0) + 1;
        }

        foreach ($event_counts as $key => $count) {
            // Validate key format before exploding
            if (strpos($key, '_') === false) {
                continue;
            }
            
            list($type, $class) = explode('_', $key, 2);
            $summary .= sprintf("- %s روی %s: %d بار\n", $type, $class, $count);
        }

        $persona = $this->get_dominant_persona($user_identifier);
        $summary .= sprintf(
            "\nپرسونای شناسایی‌شده: %s (اطمینان: %.1f%%)",
            $persona['type'],
            $persona['confidence']
        );

        return $summary;
    }

    /**
     * Reset user scores
     *
     * @param string $user_identifier User identifier
     * @return bool Success status
     */
    public function reset_scores(string $user_identifier): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homaye_persona_scores';

        return (bool) $wpdb->delete(
            $table_name,
            ['user_identifier' => $user_identifier],
            ['%s']
        );
    }

    /**
     * Get all personas with their scores for a user
     *
     * @param string $user_identifier User identifier
     * @return array Full persona analysis
     */
    public function get_full_analysis(string $user_identifier): array
    {
        return [
            'dominant' => $this->get_dominant_persona($user_identifier),
            'scores' => $this->get_scores($user_identifier),
            'behavior' => $this->get_behavior_summary($user_identifier),
            'session' => $this->get_session_data($user_identifier),
        ];
    }

    /**
     * Save conversion session metadata
     * Used for tracking abandoned carts and conversion paths
     *
     * @param string $user_identifier User identifier
     * @param array $session_data Session data to save
     * @return bool Success status
     */
    public function save_conversion_session(string $user_identifier, array $session_data): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homaye_conversion_sessions';

        // Check if table exists, if not create it
        $this->maybe_create_conversion_sessions_table();

        // Prepare data
        $data = [
            'user_identifier' => $user_identifier,
            'session_data' => wp_json_encode($session_data),
            'form_completion' => $session_data['form_completion'] ?? 0,
            'cart_value' => $session_data['cart_value'] ?? 0,
            'conversion_status' => $session_data['conversion_status'] ?? 'in_progress',
            'last_activity' => current_time('mysql'),
            'created_at' => current_time('mysql')
        ];

        // Check if session exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_identifier = %s AND conversion_status = 'in_progress' ORDER BY id DESC LIMIT 1",
            $user_identifier
        ));

        if ($existing) {
            // Update existing session
            return (bool) $wpdb->update(
                $table_name,
                $data,
                ['id' => $existing],
                ['%s', '%s', '%d', '%f', '%s', '%s'],
                ['%d']
            );
        } else {
            // Insert new session
            return (bool) $wpdb->insert($table_name, $data, ['%s', '%s', '%d', '%f', '%s', '%s', '%s']);
        }
    }

    /**
     * Get conversion session data
     *
     * @param string $user_identifier User identifier
     * @return array|null Session data or null
     */
    public function get_conversion_session(string $user_identifier): ?array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homaye_conversion_sessions';

        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_identifier = %s ORDER BY last_activity DESC LIMIT 1",
            $user_identifier
        ), ARRAY_A);

        if ($session) {
            $session['session_data'] = json_decode($session['session_data'], true);
            return $session;
        }

        return null;
    }

    /**
     * Mark conversion session as completed
     *
     * @param string $user_identifier User identifier
     * @param int $order_id Optional order ID
     * @return bool Success status
     */
    public function complete_conversion_session(string $user_identifier, int $order_id = 0): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homaye_conversion_sessions';

        return (bool) $wpdb->update(
            $table_name,
            [
                'conversion_status' => 'completed',
                'order_id' => $order_id,
                'completed_at' => current_time('mysql')
            ],
            [
                'user_identifier' => $user_identifier,
                'conversion_status' => 'in_progress'
            ],
            ['%s', '%d', '%s'],
            ['%s', '%s']
        );
    }

    /**
     * Get abandoned conversion sessions
     * Sessions that haven't been updated in the last hour
     *
     * @param int $hours Hours of inactivity to consider abandoned
     * @return array Abandoned sessions
     */
    public function get_abandoned_sessions(int $hours = 1): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homaye_conversion_sessions';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE conversion_status = 'in_progress' 
             AND last_activity < DATE_SUB(NOW(), INTERVAL %d HOUR)
             ORDER BY last_activity DESC
             LIMIT 100",
            $hours
        ), ARRAY_A);

        foreach ($results as &$session) {
            $session['session_data'] = json_decode($session['session_data'], true);
        }

        return $results;
    }

    /**
     * Maybe create conversion sessions table
     *
     * @return void
     */
    private function maybe_create_conversion_sessions_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homaye_conversion_sessions';
        $charset_collate = $wpdb->get_charset_collate();

        // Check if table exists (using $wpdb->prepare for security)
        $table_check = $wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
        if ($wpdb->get_var($table_check) === $table_name) {
            return;
        }

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_identifier varchar(255) NOT NULL,
            session_data longtext NOT NULL,
            form_completion int(11) DEFAULT 0,
            cart_value decimal(10,2) DEFAULT 0.00,
            conversion_status varchar(50) DEFAULT 'in_progress',
            order_id bigint(20) UNSIGNED DEFAULT 0,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_identifier (user_identifier),
            KEY conversion_status (conversion_status),
            KEY last_activity (last_activity)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
