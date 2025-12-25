<?php
/**
 * Telemetry Tracking System
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * زیرساخت ردگیری رفتار کاربران
 * سازگار با Divi Visual Builder
 */
class HT_Telemetry
{
    /**
     * Cookie name for user identification
     */
    private const USER_COOKIE_NAME = 'ht_user_id';

    /**
     * Register REST API endpoints
     *
     * @return void
     */
    public function register_endpoints(): void
    {
        register_rest_route('homaye/v1', '/telemetry', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_telemetry_event'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('homaye/v1', '/telemetry/batch', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_batch_events'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Enqueue tracking scripts
     *
     * @return void
     */
    public function enqueue_tracker(): void
    {
        if (!get_option('ht_tracking_enabled', true)) {
            return;
        }

        // Don't track admin or logged-in editors
        if (current_user_can('edit_posts')) {
            return;
        }

        wp_enqueue_script(
            'homaye-tracker',
            HT_PLUGIN_URL . 'assets/js/tracker.js',
            [],
            HT_VERSION,
            true
        );

        wp_localize_script('homaye-tracker', 'homayeConfig', [
            'apiUrl' => rest_url('homaye/v1/telemetry'),
            'batchUrl' => rest_url('homaye/v1/telemetry/batch'),
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => $this->get_user_identifier(),
            'diviEnabled' => $this->is_divi_active(),
        ]);
    }

    /**
     * Handle single telemetry event
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function handle_telemetry_event(\WP_REST_Request $request): \WP_REST_Response
    {
        $event_type = $request->get_param('event_type');
        $element_class = $request->get_param('element_class');
        $element_data = $request->get_param('element_data');
        $user_identifier = $request->get_param('user_id') ?? $this->get_user_identifier();

        if (empty($event_type)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Event type is required',
            ], 400);
        }

        $saved = $this->save_event($user_identifier, $event_type, $element_class, $element_data);

        if ($saved) {
            // Update persona score based on event
            $this->update_persona_score($user_identifier, $event_type, $element_class, $element_data);

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Event recorded',
            ], 200);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Failed to record event',
        ], 500);
    }

    /**
     * Handle batch telemetry events
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function handle_batch_events(\WP_REST_Request $request): \WP_REST_Response
    {
        $events = $request->get_param('events');
        $user_identifier = $request->get_param('user_id') ?? $this->get_user_identifier();

        if (!is_array($events) || empty($events)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Events array is required',
            ], 400);
        }

        $success_count = 0;
        foreach ($events as $event) {
            if ($this->save_event(
                $user_identifier,
                $event['event_type'] ?? '',
                $event['element_class'] ?? '',
                $event['element_data'] ?? []
            )) {
                $success_count++;
                $this->update_persona_score(
                    $user_identifier,
                    $event['event_type'] ?? '',
                    $event['element_class'] ?? '',
                    $event['element_data'] ?? []
                );
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'recorded' => $success_count,
            'total' => count($events),
        ], 200);
    }

    /**
     * Save event to database
     *
     * @param string $user_identifier User identifier
     * @param string $event_type Event type
     * @param string $element_class Element class
     * @param mixed $element_data Element data
     * @return bool Success status
     */
    private function save_event(
        string $user_identifier,
        string $event_type,
        string $element_class,
        $element_data
    ): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homaye_telemetry_events';

        return (bool) $wpdb->insert(
            $table_name,
            [
                'user_identifier' => $user_identifier,
                'event_type' => $event_type,
                'element_class' => $element_class,
                'element_data' => is_array($element_data) ? wp_json_encode($element_data) : $element_data,
                'timestamp' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Update persona score based on event
     *
     * @param string $user_identifier User identifier
     * @param string $event_type Event type
     * @param string $element_class Element class
     * @param mixed $element_data Element data
     * @return void
     */
    private function update_persona_score(
        string $user_identifier,
        string $event_type,
        string $element_class,
        $element_data
    ): void {
        $persona_manager = \HomayeTabesh\HT_Core::instance()->memory;
        
        // Calculate score based on event type and element
        $score_delta = $this->calculate_score_delta($event_type, $element_class, $element_data);
        
        if ($score_delta !== 0) {
            $persona_type = $this->determine_persona_type($element_class, $element_data);
            $persona_manager->add_score($user_identifier, $persona_type, $score_delta);
        }
    }

    /**
     * Calculate score change based on event
     *
     * @param string $event_type Event type
     * @param string $element_class Element class
     * @param mixed $element_data Element data
     * @return int Score delta
     */
    private function calculate_score_delta(string $event_type, string $element_class, $element_data): int
    {
        $score = 0;

        // Event type scoring
        switch ($event_type) {
            case 'hover':
                $score += 1;
                break;
            case 'click':
                $score += 5;
                break;
            case 'long_view':
                $score += 10;
                break;
            case 'scroll_to':
                $score += 2;
                break;
        }

        // Element-specific scoring
        if (str_contains($element_class, 'price')) {
            $score += 3;
        }
        if (str_contains($element_class, 'product')) {
            $score += 5;
        }
        if (str_contains($element_class, 'license') || str_contains($element_class, 'permission')) {
            $score += 10; // Strong signal for author persona
        }

        return $score;
    }

    /**
     * Determine persona type from element
     *
     * @param string $element_class Element class
     * @param mixed $element_data Element data
     * @return string Persona type
     */
    private function determine_persona_type(string $element_class, $element_data): string
    {
        if (str_contains($element_class, 'license') || str_contains($element_class, 'permission')) {
            return 'author';
        }
        if (str_contains($element_class, 'bulk') || str_contains($element_class, 'wholesale')) {
            return 'business';
        }
        if (str_contains($element_class, 'custom') || str_contains($element_class, 'design')) {
            return 'designer';
        }

        return 'general';
    }

    /**
     * Get unique user identifier
     *
     * @return string User identifier
     */
    private function get_user_identifier(): string
    {
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }

        // Use WordPress cookie for guest identification
        if (isset($_COOKIE[self::USER_COOKIE_NAME])) {
            return sanitize_text_field($_COOKIE[self::USER_COOKIE_NAME]);
        }

        // Generate new identifier
        $user_id = 'guest_' . wp_generate_uuid4();
        
        // Set cookie for 30 days
        if (!headers_sent()) {
            setcookie(
                self::USER_COOKIE_NAME,
                $user_id,
                time() + (30 * DAY_IN_SECONDS),
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }

        return $user_id;
    }

    /**
     * Check if Divi theme is active
     *
     * @return bool
     */
    private function is_divi_active(): bool
    {
        $theme = wp_get_theme();
        return $theme->get('Name') === 'Divi' || $theme->get('Template') === 'Divi';
    }
}
