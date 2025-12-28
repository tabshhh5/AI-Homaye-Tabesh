<?php
/**
 * Inference Engine
 * Main decision-making engine combining all contexts
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * موتور استنتاج اصلی
 * ترکیب تمام منابع دانش و تصمیم‌گیری هوشمند
 */
class HT_Inference_Engine
{
    /**
     * Prompt builder service
     */
    private HT_Prompt_Builder_Service $prompt_builder;

    /**
     * Gemini client
     */
    private HT_Gemini_Client $brain;

    /**
     * Action parser
     */
    private HT_Action_Parser $action_parser;

    /**
     * Knowledge base
     */
    private HT_Knowledge_Base $knowledge;

    /**
     * Persona manager
     */
    private HT_Persona_Manager $memory;

    /**
     * WooCommerce context
     */
    private HT_WooCommerce_Context $woo_context;

    /**
     * Constructor
     *
     * @param HT_Gemini_Client $brain Gemini client instance
     * @param HT_Knowledge_Base $knowledge Knowledge base instance
     * @param HT_Persona_Manager $memory Persona manager instance
     * @param HT_WooCommerce_Context $woo_context WooCommerce context instance
     */
    public function __construct(
        HT_Gemini_Client $brain,
        HT_Knowledge_Base $knowledge,
        HT_Persona_Manager $memory,
        HT_WooCommerce_Context $woo_context
    ) {
        // Create dependencies that don't need HT_Core
        $this->prompt_builder = new HT_Prompt_Builder_Service($knowledge, $memory, $woo_context);
        $this->action_parser = new HT_Action_Parser();
        
        // Store passed dependencies
        $this->brain = $brain;
        $this->knowledge = $knowledge;
        $this->memory = $memory;
        $this->woo_context = $woo_context;
    }

    /**
     * Generate decision based on user context
     *
     * @param array $user_context User context data
     * @return array Decision result
     */
    public function generate_decision(array $user_context): array
    {
        // Extract user identifier
        $user_identifier = $user_context['user_identifier'] ?? '';
        
        if (empty($user_identifier)) {
            return $this->get_error_response('Missing user identifier');
        }

        try {
            // 1. Gather relevant knowledge chunks based on current page
            $kb_context = $this->gather_knowledge_context($user_context);

            // 2. Build comprehensive system instruction
            $system_instruction = $this->prompt_builder->build_system_instruction(
                $user_identifier,
                ['knowledge_types' => ['products', 'personas', 'responses', 'pricing']]
            );

            // 3. Build user prompt
            $user_message = $user_context['message'] ?? 'کاربر در حال بررسی وبسایت است';
            $user_prompt = $this->prompt_builder->build_user_prompt(
                $user_message,
                $user_identifier,
                [
                    'current_page' => $user_context['current_page'] ?? '',
                    'current_element' => $user_context['current_element'] ?? '',
                ]
            );

            // 4. Call Gemini with structured output schema
            $schema = $this->get_response_schema();
            $raw_response = $this->brain->get_json_response(
                $user_prompt,
                $system_instruction,
                $schema
            );

            // 5. Parse response and extract actions
            $parsed_response = $this->action_parser->parse_response($raw_response);

            // 6. Validate response
            if (!$this->action_parser->is_valid_response($parsed_response)) {
                return $this->get_error_response('Invalid AI response format');
            }

            // 7. Update persona if needed
            if (!empty($parsed_response['persona_update'])) {
                $this->update_persona($user_identifier, $parsed_response['persona_update']);
            }

            // 8. Log action if present
            if (!empty($parsed_response['action'])) {
                $this->action_parser->log_action($user_identifier, $parsed_response['action'], true);
            }

            // 9. Return formatted response
            return $this->action_parser->to_frontend_format($parsed_response);

        } catch (\Exception $e) {
            error_log('Homaye Tabesh - Inference Engine Error: ' . $e->getMessage());
            return $this->get_error_response('خطا در پردازش درخواست: ' . $e->getMessage());
        }
    }

    /**
     * Gather knowledge context based on user context
     *
     * @param array $user_context User context
     * @return array Knowledge context
     */
    private function gather_knowledge_context(array $user_context): array
    {
        $context = [];

        // Get relevant knowledge based on current page
        $current_page = $user_context['current_page'] ?? '';
        
        if (strpos($current_page, 'product') !== false) {
            $context['products'] = $this->knowledge->load_rules('products');
        }

        if (strpos($current_page, 'pricing') !== false || 
            strpos($current_page, 'calculator') !== false) {
            $context['pricing'] = $this->knowledge->load_rules('pricing');
        }

        // Always include response guidelines
        $context['responses'] = $this->knowledge->load_rules('responses');

        return $context;
    }

    /**
     * Get JSON schema for structured output
     *
     * @return array JSON schema
     */
    private function get_response_schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'thought' => [
                    'type' => 'string',
                    'description' => 'تحلیل داخلی از وضعیت کاربر و نیاز او'
                ],
                'response' => [
                    'type' => 'string',
                    'description' => 'پاسخ متنی که به کاربر نمایش داده می‌شود'
                ],
                'action' => [
                    'type' => 'string',
                    'description' => 'نوع اکشن UI (اختیاری)',
                    'enum' => [
                        'highlight_element',
                        'show_tooltip',
                        'scroll_to',
                        'open_modal',
                        'update_calculator',
                        'suggest_product',
                        'show_discount',
                        'change_css',
                        'redirect',
                        'none'
                    ]
                ],
                'target' => [
                    'type' => 'string',
                    'description' => 'هدف اکشن (CSS selector یا ID)'
                ],
                'data' => [
                    'type' => 'object',
                    'description' => 'داده‌های اضافی برای اکشن'
                ],
                'persona_update' => [
                    'type' => 'string',
                    'description' => 'به‌روزرسانی پرسونا (اختیاری)'
                ]
            ],
            'required' => ['thought', 'response']
        ];
    }

    /**
     * Update user persona
     *
     * @param string $user_identifier User identifier
     * @param string $new_persona New persona type
     * @return void
     */
    private function update_persona(string $user_identifier, string $new_persona): void
    {
        // Add score to the new persona
        $this->memory->add_score($user_identifier, $new_persona, 20);
    }

    /**
     * Get error response
     *
     * @param string $error Error message
     * @return array Error response
     */
    private function get_error_response(string $error): array
    {
        $message = 'متأسفانه در حال حاضر نمی‌توانم پاسخگوی شما باشم.';
        return [
            'success' => false,
            'response' => $message,  // Add response key for consistency
            'message' => $message,
            'error' => $error,
            'timestamp' => current_time('mysql'),
        ];
    }

    /**
     * Get context-aware suggestion
     *
     * @param string $user_identifier User identifier
     * @param array $context Current context
     * @return array Suggestion result
     */
    public function get_context_suggestion(string $user_identifier, array $context): array
    {
        $persona = $this->memory->get_dominant_persona($user_identifier);
        
        // Get persona-specific knowledge
        $persona_rules = $this->knowledge->load_rules('personas');
        $recommendations = $persona_rules[$persona['type']]['recommendations'] ?? [];

        if (empty($recommendations)) {
            return [
                'success' => false,
                'message' => 'پیشنهادی موجود نیست',
            ];
        }

        // Build suggestion message
        $suggestion_message = sprintf(
            'بر اساس علایق شما به عنوان "%s"، پیشنهادات ما:\n',
            $persona['type']
        );

        foreach ($recommendations as $recommendation) {
            $suggestion_message .= "• $recommendation\n";
        }

        return [
            'success' => true,
            'message' => $suggestion_message,
            'persona' => $persona['type'],
            'confidence' => $persona['confidence'],
        ];
    }

    /**
     * Analyze user intent from behavior
     *
     * @param string $user_identifier User identifier
     * @return array Intent analysis
     */
    public function analyze_user_intent(string $user_identifier): array
    {
        $behavior_summary = $this->memory->get_behavior_summary($user_identifier, 10);
        $persona = $this->memory->get_dominant_persona($user_identifier);

        // Simple intent detection based on persona and behavior
        $intent = 'browsing';
        $confidence = 0;

        if ($persona['confidence'] > 70) {
            if ($persona['type'] === 'author') {
                $intent = 'book_printing';
                $confidence = $persona['confidence'];
            } elseif ($persona['type'] === 'business') {
                $intent = 'bulk_order';
                $confidence = $persona['confidence'];
            } elseif ($persona['type'] === 'designer') {
                $intent = 'print_design';
                $confidence = $persona['confidence'];
            }
        }

        return [
            'intent' => $intent,
            'confidence' => $confidence,
            'persona' => $persona['type'],
            'behavior_summary' => $behavior_summary,
        ];
    }
}
