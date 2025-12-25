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
     * Add score to user's persona
     *
     * @param string $user_identifier User identifier
     * @param string $persona_type Persona type
     * @param int $score Score to add
     * @return bool Success status
     */
    public function add_score(string $user_identifier, string $persona_type, int $score): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homaye_persona_scores';

        // Get existing score
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_identifier = %s AND persona_type = %s",
            $user_identifier,
            $persona_type
        ));

        if ($existing) {
            // Update existing score
            return (bool) $wpdb->update(
                $table_name,
                [
                    'score' => $existing->score + $score,
                    'updated_at' => current_time('mysql'),
                ],
                [
                    'id' => $existing->id,
                ],
                ['%d', '%s'],
                ['%d']
            );
        } else {
            // Insert new score
            return (bool) $wpdb->insert(
                $table_name,
                [
                    'user_identifier' => $user_identifier,
                    'persona_type' => $persona_type,
                    'score' => $score,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%d', '%s', '%s']
            );
        }
    }

    /**
     * Get user's persona scores
     *
     * @param string $user_identifier User identifier
     * @return array Persona scores
     */
    public function get_scores(string $user_identifier): array
    {
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
}
