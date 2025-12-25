<?php
/**
 * Parallel UI Manager - React Sidebar Integration
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * HT_Parallel_UI Class
 * 
 * Manages the React-based parallel UI system where the chatbot sidebar
 * appears alongside the main Divi site content.
 */
class HT_Parallel_UI
{
    /**
     * Core instance reference
     */
    private HT_Core $core;

    /**
     * Constructor
     */
    public function __construct(HT_Core $core)
    {
        $this->core = $core;
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // PR 6.5: Enqueue event bus early (priority 5) so all other scripts can depend on it
        add_action('wp_enqueue_scripts', [$this, 'enqueue_event_bus'], 5);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 30);
        
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Add body class
        add_filter('body_class', [$this, 'add_body_class']);
    }

    /**
     * Enqueue Event Bus early (PR 6.5)
     * This is loaded first so all other scripts can depend on it
     *
     * @return void
     */
    public function enqueue_event_bus(): void
    {
        // Only load on frontend
        if (is_admin()) {
            return;
        }

        // PR 6.5: Enqueue Event Bus first (dependency for all other scripts)
        wp_enqueue_script(
            'homa-event-bus',
            HT_PLUGIN_URL . 'assets/js/homa-event-bus.js',
            [],
            HT_VERSION,
            true
        );

        // PR 6.5: Enqueue Command Interpreter
        wp_enqueue_script(
            'homa-command-interpreter',
            HT_PLUGIN_URL . 'assets/js/homa-command-interpreter.js',
            ['homa-event-bus'],
            HT_VERSION,
            true
        );
    }

    /**
     * Enqueue React and parallel UI assets
     *
     * @return void
     */
    public function enqueue_assets(): void
    {
        // Enqueue React (from CDN for better caching)
        wp_enqueue_script(
            'react',
            'https://unpkg.com/react@18/umd/react.production.min.js',
            [],
            '18.2.0',
            true
        );

        wp_enqueue_script(
            'react-dom',
            'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js',
            ['react'],
            '18.2.0',
            true
        );

        // Enqueue orchestrator (vanilla JS) - depends on event bus (loaded at priority 5)
        wp_enqueue_script(
            'homa-orchestrator',
            HT_PLUGIN_URL . 'assets/js/homa-orchestrator.js',
            ['homa-event-bus'],
            HT_VERSION,
            true
        );

        // Enqueue FAB (floating action button)
        wp_enqueue_script(
            'homa-fab',
            HT_PLUGIN_URL . 'assets/js/homa-fab.js',
            ['homa-orchestrator'],
            HT_VERSION,
            true
        );

        // Enqueue React sidebar bundle - depends on event bus
        $build_file = HT_PLUGIN_DIR . 'assets/build/homa-sidebar.js';
        if (file_exists($build_file)) {
            wp_enqueue_script(
                'homa-sidebar',
                HT_PLUGIN_URL . 'assets/build/homa-sidebar.js',
                ['react', 'react-dom', 'homa-event-bus', 'homa-orchestrator'],
                HT_VERSION,
                true
            );
        } else {
            // Development fallback - log warning
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Homa Parallel UI] Build file not found. Run: npm run build');
            }
        }

        // Localize script with config
        wp_localize_script(
            'homa-orchestrator',
            'homayeParallelUIConfig',
            [
                'nonce' => wp_create_nonce('wp_rest'),
                'restUrl' => rest_url('homaye/v1'),
                'pluginUrl' => HT_PLUGIN_URL,
                'isUserLoggedIn' => is_user_logged_in(),
                'userId' => get_current_user_id(),
                'ajaxUrl' => admin_url('admin-ajax.php')
            ]
        );

        // Enqueue parallel UI CSS
        wp_enqueue_style(
            'homa-parallel-ui',
            HT_PLUGIN_URL . 'assets/css/homa-parallel-ui.css',
            [],
            HT_VERSION
        );
    }

    /**
     * Register REST API routes
     *
     * @return void
     */
    public function register_rest_routes(): void
    {
        // Chat endpoint
        register_rest_route('homaye/v1', '/ai/chat', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chat_request'],
            'permission_callback' => function() {
                return $this->verify_request();
            },
            'args' => [
                'message' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field'
                ],
                'persona' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'context' => [
                    'required' => false,
                    'type' => 'object'
                ]
            ]
        ]);

        // Sidebar state endpoint
        register_rest_route('homaye/v1', '/sidebar/state', [
            'methods' => 'GET',
            'callback' => [$this, 'get_sidebar_state'],
            'permission_callback' => function() {
                return $this->verify_request();
            }
        ]);
    }

    /**
     * Handle chat request from React sidebar
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_chat_request(\WP_REST_Request $request): \WP_REST_Response
    {
        $message = $request->get_param('message');
        $persona = $request->get_param('persona');
        $context = $request->get_param('context');

        try {
            // Get user identifier
            $user_id = get_current_user_id();
            if (!$user_id) {
                $user_id = $this->get_guest_identifier();
            }

            // Build context for AI
            $full_context = [
                'user_message' => $message,
                'persona' => $persona,
                'page_context' => $context,
                'woocommerce_data' => $this->core->woo_context->get_product_context(),
                'user_behavior' => $this->get_user_behavior($user_id)
            ];

            // Get AI response
            $ai_response = $this->core->ai_controller->process_chat_message(
                $message,
                $full_context
            );

            // Extract actions from response
            $actions = $this->extract_actions($ai_response);

            return new \WP_REST_Response([
                'success' => true,
                'response' => $ai_response['response'] ?? $ai_response,
                'actions' => $actions,
                'persona' => $ai_response['detected_persona'] ?? $persona
            ], 200);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'خطا در پردازش پیام. لطفاً دوباره تلاش کنید.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sidebar state
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_sidebar_state(\WP_REST_Request $request): \WP_REST_Response
    {
        $user_id = get_current_user_id() ?: $this->get_guest_identifier();
        
        return new \WP_REST_Response([
            'success' => true,
            'state' => [
                'persona' => $this->core->memory->get_user_persona($user_id),
                'chat_enabled' => true,
                'features' => [
                    'form_sync' => true,
                    'smart_chips' => true,
                    'dom_control' => true
                ]
            ]
        ], 200);
    }

    /**
     * Extract UI actions from AI response
     *
     * @param mixed $response
     * @return array
     */
    private function extract_actions($response): array
    {
        $actions = [];

        if (is_array($response) && isset($response['actions'])) {
            return $response['actions'];
        }

        // Parse response text for action commands
        // Format: [ACTION:type:selector] or [ACTION:type:field:value]
        if (is_string($response)) {
            preg_match_all('/\[ACTION:(.*?)\]/', $response, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $action_str) {
                    $parts = explode(':', $action_str);
                    if (count($parts) >= 2) {
                        $actions[] = [
                            'type' => $parts[0],
                            'selector' => $parts[1] ?? null,
                            'value' => $parts[2] ?? null
                        ];
                    }
                }
            }
        }

        return $actions;
    }

    /**
     * Get user behavior data
     *
     * @param string $user_id
     * @return array
     */
    private function get_user_behavior(string $user_id): array
    {
        // Get from telemetry
        $behavior = $this->core->eyes->get_user_journey($user_id);
        
        return [
            'page_views' => $behavior['page_views'] ?? 0,
            'time_on_site' => $behavior['time_on_site'] ?? 0,
            'interactions' => $behavior['interactions'] ?? [],
            'last_activity' => $behavior['last_activity'] ?? null
        ];
    }

    /**
     * Verify REST request
     *
     * @return bool
     */
    private function verify_request(): bool
    {
        // Allow logged-in users
        if (is_user_logged_in()) {
            return true;
        }

        // Allow guests with valid session
        if (isset($_COOKIE['homa_session'])) {
            return true;
        }

        return false;
    }

    /**
     * Get guest identifier
     *
     * @return string
     */
    private function get_guest_identifier(): string
    {
        if (isset($_COOKIE['homa_guest_id'])) {
            return sanitize_text_field($_COOKIE['homa_guest_id']);
        }

        // Generate new guest ID
        $guest_id = 'guest_' . wp_generate_password(16, false);
        
        // Set cookie with security attributes
        setcookie(
            'homa_guest_id',
            $guest_id,
            [
                'expires' => time() + (86400 * 30), // 30 days
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
        
        return $guest_id;
    }

    /**
     * Add body class
     *
     * @param array $classes
     * @return array
     */
    public function add_body_class(array $classes): array
    {
        $classes[] = 'homa-parallel-ui-enabled';
        return $classes;
    }
}
