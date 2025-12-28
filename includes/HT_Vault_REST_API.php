<?php
/**
 * Vault REST API - Cross-Device Sync
 *
 * @package HomayeTabesh
 * @since PR7
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * REST API برای همگامسازی حافظه بین دستگاه‌ها
 */
class HT_Vault_REST_API
{
    /**
     * Namespace for REST API routes
     */
    private const NAMESPACE = 'homaye-tabesh/v1';

    /**
     * Initialize REST API routes
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /**
     * Register REST API routes
     *
     * @return void
     */
    public static function register_routes(): void
    {
        // Sync vault data
        register_rest_route(self::NAMESPACE, '/vault/sync', [
            'methods' => 'POST',
            'callback' => [self::class, 'sync_vault'],
            'permission_callback' => '__return_true',
            'args' => [
                'field' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'value' => [
                    'required' => true
                ],
                'page_url' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw'
                ]
            ]
        ]);

        // Restore vault data
        register_rest_route(self::NAMESPACE, '/vault/restore', [
            'methods' => 'GET',
            'callback' => [self::class, 'restore_vault'],
            'permission_callback' => '__return_true',
            'args' => [
                'session_token' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        // Clear vault data
        register_rest_route(self::NAMESPACE, '/vault/clear', [
            'methods' => 'POST',
            'callback' => [self::class, 'clear_vault'],
            'permission_callback' => '__return_true'
        ]);

        // Save session snapshot
        register_rest_route(self::NAMESPACE, '/session/snapshot', [
            'methods' => 'POST',
            'callback' => [self::class, 'save_snapshot'],
            'permission_callback' => '__return_true',
            'args' => [
                'form_snapshot' => [
                    'required' => true,
                    'type' => 'object'
                ],
                'chat_summary' => [
                    'required' => false,
                    'type' => 'string'
                ]
            ]
        ]);

        // Get persona analysis
        register_rest_route(self::NAMESPACE, '/persona/analyze', [
            'methods' => 'GET',
            'callback' => [self::class, 'analyze_persona'],
            'permission_callback' => '__return_true'
        ]);

        // Track interest
        register_rest_route(self::NAMESPACE, '/interest/track', [
            'methods' => 'POST',
            'callback' => [self::class, 'track_interest'],
            'permission_callback' => '__return_true',
            'args' => [
                'category' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'score' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1
                ],
                'source' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'organic',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        // Get user interests (for Explore Widget)
        register_rest_route(self::NAMESPACE, '/vault/interests', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_user_interests'],
            'permission_callback' => '__return_true'
        ]);

        // Get memory summary
        register_rest_route(self::NAMESPACE, '/memory/summary', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_memory_summary'],
            'permission_callback' => '__return_true'
        ]);

        // Compress context
        register_rest_route(self::NAMESPACE, '/context/compress', [
            'methods' => 'POST',
            'callback' => [self::class, 'compress_context'],
            'permission_callback' => '__return_true',
            'args' => [
                'messages' => [
                    'required' => true,
                    'type' => 'array'
                ]
            ]
        ]);
    }

    /**
     * Sync vault data endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public static function sync_vault(\WP_REST_Request $request): \WP_REST_Response
    {
        $field = $request->get_param('field');
        $value = $request->get_param('value');
        $page_url = $request->get_param('page_url') ?? '';

        // Store in vault
        $success = HT_Vault_Manager::store($field, $value);

        // Also update session snapshot with current URL
        if ($page_url) {
            $current_snapshot = HT_Vault_Manager::get_session_snapshot();
            $form_data = $current_snapshot['form_snapshot'] ?? [];
            $form_data[$field] = $value;

            HT_Vault_Manager::save_session_snapshot($page_url, $form_data);
        }

        if ($success) {
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Data synced successfully',
                'session_token' => HT_Vault_Manager::get_session_token()
            ], 200);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Failed to sync data'
        ], 500);
    }

    /**
     * Restore vault data endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public static function restore_vault(\WP_REST_Request $request): \WP_REST_Response
    {
        $session_token = $request->get_param('session_token');
        
        $vault_data = HT_Vault_Manager::get_all($session_token);
        $session = HT_Vault_Manager::get_session_snapshot($session_token);
        $interests = HT_Vault_Manager::get_user_interests();

        return new \WP_REST_Response([
            'success' => true,
            'vault_data' => $vault_data,
            'session' => $session,
            'interests' => $interests,
            'session_token' => HT_Vault_Manager::get_session_token()
        ], 200);
    }

    /**
     * Clear vault data endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public static function clear_vault(\WP_REST_Request $request): \WP_REST_Response
    {
        $success = HT_Vault_Manager::clear_session();

        return new \WP_REST_Response([
            'success' => $success,
            'message' => $success ? 'Vault cleared successfully' : 'Failed to clear vault'
        ], $success ? 200 : 500);
    }

    /**
     * Save session snapshot endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public static function save_snapshot(\WP_REST_Request $request): \WP_REST_Response
    {
        $form_snapshot = $request->get_param('form_snapshot');
        $chat_summary = $request->get_param('chat_summary');
        $current_url = $_SERVER['HTTP_REFERER'] ?? home_url();

        $success = HT_Vault_Manager::save_session_snapshot(
            $current_url,
            $form_snapshot,
            $chat_summary
        );

        return new \WP_REST_Response([
            'success' => $success,
            'message' => $success ? 'Snapshot saved' : 'Failed to save snapshot'
        ], $success ? 200 : 500);
    }

    /**
     * Analyze persona endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public static function analyze_persona(\WP_REST_Request $request): \WP_REST_Response
    {
        $persona_data = HT_Persona_Engine::analyze_user_persona();
        $strategy = HT_Persona_Engine::get_persona_strategy($persona_data['persona']);

        return new \WP_REST_Response([
            'success' => true,
            'persona' => $persona_data,
            'strategy' => $strategy,
            'from_torob' => HT_Persona_Engine::is_from_torob()
        ], 200);
    }

    /**
     * Track interest endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public static function track_interest(\WP_REST_Request $request): \WP_REST_Response
    {
        $category = $request->get_param('category');
        $score = $request->get_param('score') ?? 1;
        $source = $request->get_param('source') ?? 'organic';

        $success = HT_Vault_Manager::track_interest($category, $score, $source);

        return new \WP_REST_Response([
            'success' => $success,
            'message' => $success ? 'Interest tracked' : 'Failed to track interest'
        ], $success ? 200 : 500);
    }

    /**
     * Get memory summary endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public static function get_memory_summary(\WP_REST_Request $request): \WP_REST_Response
    {
        $summary = HT_Vault_Manager::get_memory_summary();
        $persona_prefix = HT_Persona_Engine::get_persona_prompt_prefix();

        return new \WP_REST_Response([
            'success' => true,
            'memory_summary' => $summary,
            'persona_prefix' => $persona_prefix
        ], 200);
    }

    /**
     * Get user interests endpoint (for Explore Widget)
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public static function get_user_interests(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            // Get persona data which includes interests
            $persona = HT_Persona_Engine::get_current_persona();
            $interests_data = [];
            
            // Get browsing history and interests from vault
            $vault_data = HT_Vault_Manager::get_all();
            
            // Extract interests based on recent interactions
            $interests = $persona['interests'] ?? [];
            
            // If interests exist, format them for the widget
            if (!empty($interests)) {
                foreach ($interests as $category => $score) {
                    $interests_data[] = [
                        'category' => $category,
                        'score' => $score,
                        'context' => '', // Could be enhanced with more context
                        'related_url' => '', // Could link to relevant pages
                    ];
                }
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'interests' => $interests_data,
                'persona' => $persona
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compress context endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public static function compress_context(\WP_REST_Request $request): \WP_REST_Response
    {
        $messages = $request->get_param('messages');

        $compressed = HT_Context_Compressor::compress_messages($messages);
        $metrics = HT_Context_Compressor::extract_metrics($messages);

        return new \WP_REST_Response([
            'success' => true,
            'compressed_summary' => $compressed,
            'metrics' => $metrics
        ], 200);
    }
}
