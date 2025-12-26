<?php
/**
 * DOM Action Controller
 * Central controller for managing DOM interactions and visual guidance
 *
 * @package HomayeTabesh
 * @since PR10
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * کنترلر مرکزی برای مدیریت اکشن‌های DOM
 * Controls scroll, highlight, click, and other visual interactions
 */
class HT_DOM_Action_Controller
{
    /**
     * Singleton instance
     */
    private static ?HT_DOM_Action_Controller $instance = null;

    /**
     * Action queue for tracking executed actions
     */
    private array $action_queue = [];

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init();
    }

    /**
     * Initialize the controller
     */
    private function init(): void
    {
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Enqueue scripts for visual guidance
        add_action('wp_enqueue_scripts', [$this, 'enqueue_visual_scripts']);
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes(): void
    {
        // Execute visual action endpoint
        register_rest_route('homaye/v1', '/visual/action', [
            'methods' => 'POST',
            'callback' => [$this, 'execute_visual_action'],
            'permission_callback' => function() {
                return true; // Allow all users to execute visual actions
            },
            'args' => [
                'action_type' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['highlight', 'scroll', 'tooltip', 'glow', 'pulse']
                ],
                'selector' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'message' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'duration' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 5000
                ]
            ]
        ]);

        // Get action history
        register_rest_route('homaye/v1', '/visual/history', [
            'methods' => 'GET',
            'callback' => [$this, 'get_action_history'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    /**
     * Execute visual action (REST API callback)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function execute_visual_action(\WP_REST_Request $request): \WP_REST_Response
    {
        $action_type = $request->get_param('action_type');
        $selector = $request->get_param('selector');
        $message = $request->get_param('message');
        $duration = $request->get_param('duration') ?? 5000;

        // Create action object
        $action = [
            'action_type' => $action_type,
            'selector' => $selector,
            'message' => $message,
            'duration' => $duration,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'session_id' => $this->get_session_id()
        ];

        // Store in action queue
        $this->action_queue[] = $action;

        // Return action for client-side execution
        return new \WP_REST_Response([
            'success' => true,
            'action' => $action,
            'message' => 'Visual action queued successfully'
        ], 200);
    }

    /**
     * Get action history (REST API callback)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_action_history(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'actions' => $this->action_queue,
            'count' => count($this->action_queue)
        ], 200);
    }

    /**
     * Enqueue visual guidance scripts
     */
    public function enqueue_visual_scripts(): void
    {
        // Enqueue visual guidance JS
        wp_enqueue_script(
            'homa-visual-guidance',
            HT_PLUGIN_URL . 'assets/js/homa-visual-guidance.js',
            ['jquery', 'homa-event-bus', 'homa-command-interpreter'],
            HT_VERSION,
            true
        );

        // Enqueue visual effects CSS
        wp_enqueue_style(
            'homa-visual-effects',
            HT_PLUGIN_URL . 'assets/css/homa-visual-effects.css',
            [],
            HT_VERSION
        );

        // Localize script with configuration
        wp_localize_script('homa-visual-guidance', 'homaVisualConfig', [
            'restUrl' => rest_url('homaye/v1/visual'),
            'nonce' => wp_create_nonce('wp_rest'),
            'defaultDuration' => 5000,
            'animationSpeed' => 300,
            'scrollOffset' => 100
        ]);
    }

    /**
     * Create visual command for Gemini
     * Formats action into JSON structure that Gemini can generate
     *
     * @param string $action_type Type of visual action
     * @param string $selector CSS selector
     * @param string $message Optional message
     * @return array
     */
    public function create_visual_command(string $action_type, string $selector, string $message = ''): array
    {
        return [
            'action_type' => 'ui_interaction',
            'command' => strtoupper($action_type),
            'target_selector' => $selector,
            'message' => $message,
            'timestamp' => time()
        ];
    }

    /**
     * Parse Gemini response for visual commands
     * Extracts visual action commands from AI response
     *
     * @param string $response Gemini response text
     * @return array Array of visual commands
     */
    public function parse_visual_commands(string $response): array
    {
        $commands = [];

        // Pattern 1: ACTION: HIGHLIGHT[selector]
        if (preg_match_all('/ACTION:\s*HIGHLIGHT\[([^\]]+)\]/i', $response, $matches)) {
            foreach ($matches[1] as $selector) {
                $commands[] = $this->create_visual_command('highlight', trim($selector));
            }
        }

        // Pattern 2: ACTION: SCROLL_TO[selector]
        if (preg_match_all('/ACTION:\s*SCROLL_TO\[([^\]]+)\]/i', $response, $matches)) {
            foreach ($matches[1] as $selector) {
                $commands[] = $this->create_visual_command('scroll_to', trim($selector));
            }
        }

        // Pattern 3: ACTION: TOOLTIP[selector, message]
        if (preg_match_all('/ACTION:\s*TOOLTIP\[([^,]+),\s*([^\]]+)\]/i', $response, $matches)) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                $selector = trim($matches[1][$i]);
                $message = trim($matches[2][$i]);
                $commands[] = $this->create_visual_command('show_tooltip', $selector, $message);
            }
        }

        return $commands;
    }

    /**
     * Get current session ID
     *
     * @return string
     */
    private function get_session_id(): string
    {
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }

    /**
     * Clear action queue
     */
    public function clear_action_queue(): void
    {
        $this->action_queue = [];
    }

    /**
     * Get action queue
     *
     * @return array
     */
    public function get_action_queue(): array
    {
        return $this->action_queue;
    }
}
