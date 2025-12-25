<?php
/**
 * Asynchronous Decision Trigger
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * منطق تشخیص زمان درخواست Action از Gemini
 * Intelligent AI Invocation Logic
 */
class HT_Decision_Trigger
{
    /**
     * Threshold for triggering AI decision
     */
    private const AI_TRIGGER_THRESHOLD = 50;

    /**
     * Minimum events before triggering
     */
    private const MIN_EVENTS_COUNT = 5;

    /**
     * Time window for activity check (seconds)
     */
    private const ACTIVITY_WINDOW = 300; // 5 minutes
    
    /**
     * Minimum dwell time for high-intent detection (milliseconds)
     */
    private const HIGH_INTENT_DWELL_TIME = 5000; // 5 seconds

    /**
     * Check if AI decision should be triggered
     *
     * @param string $user_identifier User identifier
     * @return array Decision result with trigger status and reason
     */
    public function should_trigger_ai(string $user_identifier): array
    {
        // Get persona manager
        $persona_manager = HT_Core::instance()->memory;
        
        // Get dominant persona
        $persona = $persona_manager->get_dominant_persona($user_identifier);
        
        // Check persona score threshold
        if ($persona['score'] < self::AI_TRIGGER_THRESHOLD) {
            return [
                'trigger' => false,
                'reason' => 'insufficient_score',
                'score' => $persona['score'],
                'threshold' => self::AI_TRIGGER_THRESHOLD,
            ];
        }

        // Check recent activity
        $recent_events = $this->get_recent_events($user_identifier);
        
        if (count($recent_events) < self::MIN_EVENTS_COUNT) {
            return [
                'trigger' => false,
                'reason' => 'insufficient_activity',
                'event_count' => count($recent_events),
                'min_required' => self::MIN_EVENTS_COUNT,
            ];
        }

        // Check for high-intent events
        $has_high_intent = $this->has_high_intent_events($recent_events);
        
        if (!$has_high_intent) {
            return [
                'trigger' => false,
                'reason' => 'no_high_intent_events',
            ];
        }

        // All conditions met - trigger AI
        return [
            'trigger' => true,
            'reason' => 'conditions_met',
            'persona' => $persona,
            'event_count' => count($recent_events),
            'context' => $this->build_trigger_context($user_identifier, $recent_events),
        ];
    }

    /**
     * Get recent events for user
     *
     * @param string $user_identifier User identifier
     * @return array Recent events
     */
    private function get_recent_events(string $user_identifier): array
    {
        global $wpdb;

        $events_table = $wpdb->prefix . 'homaye_telemetry_events';
        $time_threshold = gmdate('Y-m-d H:i:s', time() - self::ACTIVITY_WINDOW);

        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $events_table 
             WHERE user_identifier = %s 
             AND timestamp >= %s 
             ORDER BY timestamp DESC",
            $user_identifier,
            $time_threshold
        ), ARRAY_A);

        return $events ?: [];
    }

    /**
     * Check if recent events contain high-intent signals
     *
     * @param array $events Recent events
     * @return bool True if high-intent events found
     */
    private function has_high_intent_events(array $events): bool
    {
        $high_intent_patterns = [
            'pricing',
            'calculator',
            'add_to_cart',
            'license',
            'contact',
            'checkout',
        ];

        foreach ($events as $event) {
            $element_class = strtolower($event['element_class'] ?? '');
            $element_data = json_decode($event['element_data'] ?? '{}', true);
            $text = strtolower($element_data['text'] ?? '');

            foreach ($high_intent_patterns as $pattern) {
                if (strpos($element_class, $pattern) !== false || 
                    strpos($text, $pattern) !== false) {
                    return true;
                }
            }

            // Check event type
            if (in_array($event['event_type'], ['click', 'long_view', 'module_dwell'])) {
                // High-value event types
                if ($event['event_type'] === 'module_dwell') {
                    $dwell_time = $element_data['dwell_time'] ?? 0;
                    if ($dwell_time > self::HIGH_INTENT_DWELL_TIME) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Build context for AI trigger
     *
     * @param string $user_identifier User identifier
     * @param array $recent_events Recent events
     * @return array Trigger context
     */
    private function build_trigger_context(string $user_identifier, array $recent_events): array
    {
        $persona_manager = HT_Core::instance()->memory;
        $woo_context = HT_Core::instance()->woo_context;

        return [
            'user' => $user_identifier,
            'persona' => $persona_manager->get_full_analysis($user_identifier),
            'recent_activity' => $this->summarize_events($recent_events),
            'woocommerce' => $woo_context->get_full_context(),
            'timestamp' => current_time('mysql'),
        ];
    }

    /**
     * Summarize events for context
     *
     * @param array $events Events to summarize
     * @return array Event summary
     */
    private function summarize_events(array $events): array
    {
        $summary = [
            'total_events' => count($events),
            'event_types' => [],
            'focused_modules' => [],
            'total_dwell_time' => 0,
        ];

        foreach ($events as $event) {
            $event_type = $event['event_type'];
            $summary['event_types'][$event_type] = ($summary['event_types'][$event_type] ?? 0) + 1;

            $element_data = json_decode($event['element_data'] ?? '{}', true);
            
            if (isset($element_data['module_id'])) {
                $summary['focused_modules'][] = $element_data['module_id'];
            }

            if (isset($element_data['dwell_time'])) {
                $summary['total_dwell_time'] += $element_data['dwell_time'];
            }
        }

        return $summary;
    }

    /**
     * Execute AI decision
     *
     * @param string $user_identifier User identifier
     * @param string $prompt User prompt or intent
     * @return array AI response
     */
    public function execute_ai_decision(string $user_identifier, string $prompt = ''): array
    {
        $trigger_check = $this->should_trigger_ai($user_identifier);

        if (!$trigger_check['trigger']) {
            return [
                'success' => false,
                'reason' => $trigger_check['reason'],
                'data' => $trigger_check,
            ];
        }

        // Get Gemini client
        $gemini = HT_Core::instance()->brain;
        
        // Build context
        $context = $trigger_check['context'];
        
        // Generate prompt with context
        $full_prompt = $this->build_ai_prompt($prompt, $context);
        
        // Call Gemini
        $response = $gemini->generate_content($full_prompt, $context);

        return [
            'success' => true,
            'response' => $response,
            'context' => $context,
            'triggered_at' => current_time('mysql'),
        ];
    }

    /**
     * Build AI prompt with context
     *
     * @param string $user_prompt User prompt
     * @param array $context Trigger context
     * @return string Full prompt
     */
    private function build_ai_prompt(string $user_prompt, array $context): string
    {
        $prompt = "با توجه به رفتار کاربر:\n\n";

        // Add persona info
        if (isset($context['persona']['dominant'])) {
            $persona = $context['persona']['dominant'];
            $prompt .= sprintf(
                "پرسونای شناسایی‌شده: %s (امتیاز: %d، اطمینان: %.1f%%)\n\n",
                $persona['type'],
                $persona['score'],
                $persona['confidence']
            );
        }

        // Add activity summary
        if (isset($context['recent_activity'])) {
            $activity = $context['recent_activity'];
            $prompt .= sprintf(
                "فعالیت اخیر: %d رویداد، زمان تمرکز کل: %.1f ثانیه\n\n",
                $activity['total_events'],
                $activity['total_dwell_time'] / 1000
            );
        }

        // Add WooCommerce context
        if (isset($context['woocommerce'])) {
            $woo_context = HT_Core::instance()->woo_context;
            $prompt .= $woo_context->format_for_ai($context['woocommerce']) . "\n\n";
        }

        // Add user prompt
        if (!empty($user_prompt)) {
            $prompt .= "سوال کاربر: " . $user_prompt . "\n\n";
        }

        $prompt .= "لطفاً بهترین پیشنهاد یا راهنمایی را ارائه کن.";

        return $prompt;
    }

    /**
     * Get trigger statistics
     *
     * @param string $user_identifier User identifier
     * @return array Statistics
     */
    public function get_trigger_stats(string $user_identifier): array
    {
        $persona_manager = HT_Core::instance()->memory;
        $persona = $persona_manager->get_dominant_persona($user_identifier);
        $recent_events = $this->get_recent_events($user_identifier);

        return [
            'score' => $persona['score'],
            'threshold' => self::AI_TRIGGER_THRESHOLD,
            'score_percentage' => min(100, ($persona['score'] / self::AI_TRIGGER_THRESHOLD) * 100),
            'event_count' => count($recent_events),
            'min_events' => self::MIN_EVENTS_COUNT,
            'ready_to_trigger' => $this->should_trigger_ai($user_identifier)['trigger'],
        ];
    }
}
