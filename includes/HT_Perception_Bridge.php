<?php
/**
 * Perception Bridge - Server-side Integration for Core Intelligence Layer
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * HT_Perception_Bridge Class
 * 
 * Bridges the frontend perception layer (Indexer, Input Observer, etc.) 
 * with the backend inference engine and knowledge base.
 */
class HT_Perception_Bridge
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
        // Register REST API endpoints for perception layer
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Enqueue perception layer scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_perception_scripts'], 20);
    }

    /**
     * Register REST API routes for perception layer
     *
     * @return void
     */
    public function register_rest_routes(): void
    {
        // Analyze user intent from input
        register_rest_route('homaye/v1', '/ai/analyze-intent', [
            'methods' => 'POST',
            'callback' => [$this, 'analyze_intent'],
            'permission_callback' => '__return_true',
            'args' => [
                'field_name' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'field_value' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field'
                ],
                'concepts' => [
                    'required' => false,
                    'type' => 'object'
                ],
                'is_final' => [
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false
                ]
            ]
        ]);

        // Get semantic navigation suggestions
        register_rest_route('homaye/v1', '/navigation/suggest', [
            'methods' => 'POST',
            'callback' => [$this, 'get_navigation_suggestions'],
            'permission_callback' => '__return_true',
            'args' => [
                'current_location' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'user_context' => [
                    'required' => false,
                    'type' => 'object'
                ]
            ]
        ]);

        // Get tour steps for a specific workflow
        register_rest_route('homaye/v1', '/tour/get-steps', [
            'methods' => 'GET',
            'callback' => [$this, 'get_tour_steps'],
            'permission_callback' => '__return_true',
            'args' => [
                'workflow' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
    }

    /**
     * Analyze user intent from input field
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function analyze_intent(\WP_REST_Request $request): \WP_REST_Response
    {
        $field_name = $request->get_param('field_name');
        $field_value = $request->get_param('field_value');
        $concepts = $request->get_param('concepts');
        $is_final = $request->get_param('is_final');

        // Build context for AI analysis
        $user_context = [
            'query' => "کاربر در فیلد «{$field_name}» متن زیر را وارد کرده است: {$field_value}",
            'field_name' => $field_name,
            'field_value' => $field_value,
            'concepts' => $concepts,
            'is_final' => $is_final,
            'page_url' => $request->get_header('referer') ?: '',
            'timestamp' => time()
        ];

        // Get persona if available
        $session_id = $this->get_session_id();
        if ($session_id) {
            $persona_data = $this->core->memory->get_persona($session_id);
            if ($persona_data) {
                $user_context['persona'] = $persona_data['persona_type'] ?? 'unknown';
                $user_context['persona_confidence'] = $persona_data['confidence_score'] ?? 0;
            }
        }

        try {
            // Use inference engine to generate suggestions
            $decision = $this->core->inference_engine->generate_decision($user_context);

            // Extract actionable suggestions
            $suggestions = [];
            if (isset($decision['actions']) && is_array($decision['actions'])) {
                foreach ($decision['actions'] as $action) {
                    if ($this->is_suggestion_action($action)) {
                        $suggestions[] = $action;
                    }
                }
            }

            return new \WP_REST_Response([
                'success' => true,
                'field_name' => $field_name,
                'suggestions' => $suggestions,
                'message' => $decision['message'] ?? '',
                'confidence' => $decision['confidence'] ?? 0
            ], 200);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'خطا در تحلیل نیت کاربر',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get navigation suggestions based on user context
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_navigation_suggestions(\WP_REST_Request $request): \WP_REST_Response
    {
        $current_location = $request->get_param('current_location');
        $user_context = $request->get_param('user_context') ?: [];

        // Get persona
        $session_id = $this->get_session_id();
        $persona_type = 'general';
        
        if ($session_id) {
            $persona_data = $this->core->memory->get_persona($session_id);
            if ($persona_data) {
                $persona_type = $persona_data['persona_type'] ?? 'general';
            }
        }

        // Build suggestions based on persona and location
        $suggestions = $this->build_navigation_suggestions($persona_type, $current_location);

        return new \WP_REST_Response([
            'success' => true,
            'current_location' => $current_location,
            'persona' => $persona_type,
            'suggestions' => $suggestions
        ], 200);
    }

    /**
     * Get tour steps for a specific workflow
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_tour_steps(\WP_REST_Request $request): \WP_REST_Response
    {
        $workflow = $request->get_param('workflow');

        // Load tour definitions
        $tours = $this->load_tour_definitions();

        if (!isset($tours[$workflow])) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'تور درخواستی یافت نشد'
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'workflow' => $workflow,
            'tour' => $tours[$workflow]
        ], 200);
    }

    /**
     * Enqueue perception layer JavaScript files
     *
     * @return void
     */
    public function enqueue_perception_scripts(): void
    {
        // Only load on frontend
        if (is_admin()) {
            return;
        }

        // Enqueue perception layer scripts
        wp_enqueue_script(
            'homa-indexer',
            HT_PLUGIN_URL . 'assets/js/homa-indexer.js',
            [],
            HT_VERSION,
            true
        );

        wp_enqueue_script(
            'homa-input-observer',
            HT_PLUGIN_URL . 'assets/js/homa-input-observer.js',
            [],
            HT_VERSION,
            true
        );

        wp_enqueue_script(
            'homa-spatial-navigator',
            HT_PLUGIN_URL . 'assets/js/homa-spatial-navigator.js',
            [],
            HT_VERSION,
            true
        );

        wp_enqueue_script(
            'homa-tour-manager',
            HT_PLUGIN_URL . 'assets/js/homa-tour-manager.js',
            ['jquery'],
            HT_VERSION,
            true
        );

        // Add configuration
        $config = [
            'apiUrl' => rest_url('homaye/v1/ai/analyze-intent'),
            'navigationUrl' => rest_url('homaye/v1/navigation/suggest'),
            'tourUrl' => rest_url('homaye/v1/tour/get-steps'),
            'nonce' => wp_create_nonce('wp_rest'),
            'enableIntentAnalysis' => true,
            'enableSemanticMapping' => true,
            'enableTours' => true
        ];

        wp_localize_script('homa-indexer', 'homayePerceptionConfig', $config);
    }

    /**
     * Check if action is a suggestion type
     *
     * @param array $action
     * @return bool
     */
    private function is_suggestion_action(array $action): bool
    {
        $suggestion_types = [
            'show_tooltip',
            'suggest_product',
            'show_discount',
            'scroll_to',
            'open_modal'
        ];

        return isset($action['type']) && in_array($action['type'], $suggestion_types, true);
    }

    /**
     * Build navigation suggestions based on persona and location
     *
     * @param string $persona_type
     * @param string $current_location
     * @return array
     */
    private function build_navigation_suggestions(string $persona_type, string $current_location): array
    {
        $suggestions = [];

        // Load knowledge base
        $knowledge = $this->core->knowledge->get_all_knowledge();

        // Persona-based suggestions
        $persona_suggestions = [
            'author' => [
                ['selector' => '.et_pb_pricing', 'label' => 'جدول قیمت چاپ کتاب', 'priority' => 10],
                ['selector' => '[href*="book-printing"]', 'label' => 'خدمات چاپ کتاب', 'priority' => 9]
            ],
            'business' => [
                ['selector' => '[href*="bulk"]', 'label' => 'سفارش عمده', 'priority' => 10],
                ['selector' => '.et_pb_wc_price', 'label' => 'قیمت محصولات', 'priority' => 8]
            ],
            'designer' => [
                ['selector' => '[href*="design"]', 'label' => 'خدمات طراحی', 'priority' => 10],
                ['selector' => '[href*="portfolio"]', 'label' => 'نمونه کارها', 'priority' => 9]
            ],
            'student' => [
                ['selector' => '[href*="discount"]', 'label' => 'تخفیفات دانشجویی', 'priority' => 10],
                ['selector' => '[href*="simple-printing"]', 'label' => 'چاپ ساده', 'priority' => 8]
            ]
        ];

        if (isset($persona_suggestions[$persona_type])) {
            $suggestions = $persona_suggestions[$persona_type];
        }

        return $suggestions;
    }

    /**
     * Load tour definitions
     *
     * @return array
     */
    private function load_tour_definitions(): array
    {
        return [
            'book_printing' => [
                'title' => 'راهنمای سفارش چاپ کتاب',
                'description' => 'مراحل سفارش چاپ کتاب را گام به گام با هما یاد بگیرید',
                'steps' => [
                    [
                        'selector' => '#book_title',
                        'title' => 'عنوان کتاب',
                        'message' => 'ابتدا نام کتاب خود را در این فیلد وارد کنید'
                    ],
                    [
                        'selector' => '#book_pages',
                        'title' => 'تعداد صفحات',
                        'message' => 'تعداد صفحات کتاب خود را مشخص کنید'
                    ],
                    [
                        'selector' => '#book_quantity',
                        'title' => 'تیراژ',
                        'message' => 'تیراژ مورد نیاز خود را وارد کنید. تیراژ بالاتر = قیمت هر نسخه کمتر!'
                    ],
                    [
                        'selector' => '.et_pb_pricing',
                        'title' => 'جدول قیمت',
                        'message' => 'بر اساس اطلاعات وارد شده، قیمت نهایی در اینجا نمایش داده می‌شود'
                    ]
                ]
            ],
            'price_calculator' => [
                'title' => 'راهنمای استفاده از ماشین‌حساب قیمت',
                'description' => 'نحوه محاسبه قیمت چاپ را یاد بگیرید',
                'steps' => [
                    [
                        'selector' => '[name="paper_type"]',
                        'title' => 'نوع کاغذ',
                        'message' => 'نوع کاغذ مورد نظر خود را از این لیست انتخاب کنید'
                    ],
                    [
                        'selector' => '[name="cover_type"]',
                        'title' => 'نوع جلد',
                        'message' => 'جلد گالینگور برای کتاب‌های مهم و شومیز برای کتاب‌های معمولی پیشنهاد می‌شود'
                    ],
                    [
                        'selector' => '.calculate-btn',
                        'title' => 'محاسبه قیمت',
                        'message' => 'با کلیک روی این دکمه، قیمت نهایی محاسبه و نمایش داده می‌شود'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get session ID from cookie or generate new one
     *
     * @return string
     */
    private function get_session_id(): string
    {
        if (isset($_COOKIE['homaye_session_id'])) {
            return sanitize_text_field($_COOKIE['homaye_session_id']);
        }

        $session_id = 'session_' . wp_generate_password(16, false);
        setcookie('homaye_session_id', $session_id, time() + DAY_IN_SECONDS * 30, COOKIEPATH, COOKIE_DOMAIN);
        
        return $session_id;
    }
}
