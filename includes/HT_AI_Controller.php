<?php
/**
 * AI Controller - REST API Endpoint
 * Main endpoint for frontend to communicate with Homa
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * کنترلر REST API برای ارتباط با هما
 * نقطه ورود اصلی برای فرانتئند
 */
class HT_AI_Controller
{
    /**
     * Inference engine
     */
    private HT_Inference_Engine $inference_engine;

    /**
     * Prompt builder
     */
    private HT_Prompt_Builder_Service $prompt_builder;

    /**
     * Constructor
     *
     * @param HT_Inference_Engine $inference_engine Inference engine instance
     * @param HT_Prompt_Builder_Service $prompt_builder Prompt builder instance
     */
    public function __construct(
        HT_Inference_Engine $inference_engine,
        HT_Prompt_Builder_Service $prompt_builder
    ) {
        $this->inference_engine = $inference_engine;
        $this->prompt_builder = $prompt_builder;
    }

    /**
     * Register REST API endpoints
     *
     * @return void
     */
    public function register_endpoints(): void
    {
        // Main AI query endpoint
        register_rest_route('homaye/v1', '/ai/query', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_ai_query'],
            'permission_callback' => '__return_true',
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'message' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'context' => [
                    'required' => false,
                    'type' => 'object',
                    'default' => [],
                ],
            ],
        ]);

        // Get context suggestion endpoint
        register_rest_route('homaye/v1', '/ai/suggestion', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_suggestion_request'],
            'permission_callback' => '__return_true',
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Analyze user intent endpoint
        register_rest_route('homaye/v1', '/ai/intent', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_intent_analysis'],
            'permission_callback' => '__return_true',
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Health check endpoint
        register_rest_route('homaye/v1', '/ai/health', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_health_check'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle AI query from frontend
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public function handle_ai_query(\WP_REST_Request $request): \WP_REST_Response
    {
        $user_id = $request->get_param('user_id');
        $message = $request->get_param('message');
        $context = $request->get_param('context') ?: [];

        // Sanitize user input
        $message = $this->prompt_builder->sanitize_input($message);

        // Get user role context (PR15)
        $role_resolver = HT_Core::instance()->role_resolver;
        $user_role_context = $role_resolver->get_homa_user_context();

        // Check if user is blocked (intruder)
        if (isset($user_role_context['blocked']) && $user_role_context['blocked']) {
            return new \WP_REST_Response([
                'success' => false,
                'response' => 'دسترسی شما به دلیل فعالیت‌های مشکوک محدود شده است.',
                'blocked' => true,
            ], 403);
        }

        // Build user context
        $user_context = [
            'user_identifier' => $user_id,
            'message' => $message,
            'current_page' => $context['page'] ?? '',
            'current_element' => $context['element'] ?? '',
            'timestamp' => current_time('mysql'),
            'user_role_context' => $user_role_context, // Add role context
        ];

        // Generate decision
        $result = $this->inference_engine->generate_decision($user_context);

        // Filter response based on user capabilities (PR15)
        $chat_capabilities = HT_Core::instance()->chat_capabilities;
        $result = $chat_capabilities->filter_ai_response($result, $user_role_context);

        // Ensure result has 'success' key
        $success = isset($result['success']) && $result['success'];
        
        // Return response
        return new \WP_REST_Response($result, $success ? 200 : 500);
    }

    /**
     * Process chat message from parallel UI sidebar
     * 
     * @param string $message User message
     * @param array $context Full context including persona, page, etc.
     * @return array AI response with keys: 'success' (bool), 'response' (string), 'actions' (array), 'blocked' (bool, optional)
     */
    public function process_chat_message(string $message, array $context = []): array
    {
        // Sanitize user input
        $message = $this->prompt_builder->sanitize_input($message);

        // Get user role context
        $role_resolver = HT_Core::instance()->role_resolver;
        $user_role_context = $role_resolver->get_homa_user_context();

        // Check if user is blocked
        if (isset($user_role_context['blocked']) && $user_role_context['blocked']) {
            return [
                'success' => false,
                'response' => 'دسترسی شما به دلیل فعالیت‌های مشکوک محدود شده است.',
                'blocked' => true,
            ];
        }

        // Build comprehensive context
        $user_context = [
            'user_identifier' => $context['user_behavior']['user_id'] ?? 'guest',
            'message' => $message,
            'persona' => $context['persona'] ?? null,
            'page_context' => $context['page_context'] ?? [],
            'woocommerce_data' => $context['woocommerce_data'] ?? [],
            'user_behavior' => $context['user_behavior'] ?? [],
            'timestamp' => current_time('mysql'),
            'user_role_context' => $user_role_context,
        ];

        // Generate AI response
        $result = $this->inference_engine->generate_decision($user_context);

        // Filter response based on user capabilities
        $chat_capabilities = HT_Core::instance()->chat_capabilities;
        $result = $chat_capabilities->filter_ai_response($result, $user_role_context);

        // Ensure consistent response format
        if (!isset($result['response'])) {
            $result['response'] = $result['text'] ?? $result['message'] ?? 'متأسفم، نتوانستم پاسخی تولید کنم.';
        }

        return $result;
    }

    /**
     * Handle suggestion request
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public function handle_suggestion_request(\WP_REST_Request $request): \WP_REST_Response
    {
        $user_id = $request->get_param('user_id');

        $result = $this->inference_engine->get_context_suggestion($user_id, []);

        // Ensure result has 'success' key
        $success = isset($result['success']) && $result['success'];

        return new \WP_REST_Response($result, $success ? 200 : 404);
    }

    /**
     * Handle intent analysis request
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public function handle_intent_analysis(\WP_REST_Request $request): \WP_REST_Response
    {
        $user_id = $request->get_param('user_id');

        $result = $this->inference_engine->analyze_user_intent($user_id);

        return new \WP_REST_Response($result, 200);
    }

    /**
     * Handle health check
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public function handle_health_check(\WP_REST_Request $request): \WP_REST_Response
    {
        $api_key = get_option('ht_gemini_api_key', '');
        
        $health = [
            'status' => 'ok',
            'timestamp' => current_time('mysql'),
            'api_configured' => !empty($api_key),
            'components' => [
                'inference_engine' => 'operational',
                'knowledge_base' => 'operational',
                'action_parser' => 'operational',
            ],
        ];

        return new \WP_REST_Response($health, 200);
    }

    /**
     * Validate nonce for authenticated requests
     *
     * @param \WP_REST_Request $request Request object
     * @return bool True if valid
     */
    public function validate_nonce(\WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');
        
        if (empty($nonce)) {
            return false;
        }

        return wp_verify_nonce($nonce, 'wp_rest');
    }
}
