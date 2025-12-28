<?php
/**
 * Prompt Builder Service
 * Combines business knowledge, product data, and behavioral data into optimized prompts
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * سرویس ساخت پرومپت دینامیک
 * ترکیب دانش بیزینس + دیتای محصول + دیتای رفتاری
 */
class HT_Prompt_Builder_Service
{
    /**
     * Knowledge base instance
     */
    private HT_Knowledge_Base $knowledge_base;

    /**
     * Persona manager instance
     */
    private HT_Persona_Manager $persona_manager;

    /**
     * WooCommerce context instance
     */
    private HT_WooCommerce_Context $woo_context;

    /**
     * Constructor
     *
     * @param HT_Knowledge_Base $knowledge_base Knowledge base instance
     * @param HT_Persona_Manager $persona_manager Persona manager instance
     * @param HT_WooCommerce_Context $woo_context WooCommerce context instance
     */
    public function __construct(
        HT_Knowledge_Base $knowledge_base,
        HT_Persona_Manager $persona_manager,
        HT_WooCommerce_Context $woo_context
    ) {
        $this->knowledge_base = $knowledge_base;
        $this->persona_manager = $persona_manager;
        $this->woo_context = $woo_context;
    }

    /**
     * Build comprehensive system instruction
     *
     * @param string $user_identifier User identifier
     * @param array $options Additional options
     * @return string System instruction
     */
    public function build_system_instruction(string $user_identifier, array $options = []): string
    {
        $instruction = "شما هما (Homa) هستید، دستیار هوشمند وبسایت چاپکو (Tabesh Printing).\n\n";
        
        // Add core identity
        $instruction .= $this->get_core_identity();
        
        // Add business knowledge
        $instruction .= $this->get_business_knowledge($options);
        
        // Add persona context
        $instruction .= $this->get_persona_context($user_identifier);
        
        // Add Omni-Store memory context (PR7)
        $instruction .= $this->get_memory_context();
        
        // Add WooCommerce context
        if ($this->woo_context->is_woocommerce_active()) {
            $instruction .= $this->get_woocommerce_context();
        }
        
        // Add behavioral context
        $instruction .= $this->get_behavioral_context($user_identifier);
        
        // Add response guidelines
        $instruction .= $this->get_response_guidelines();
        
        return $instruction;
    }

    /**
     * Get core identity of Homa
     *
     * @return string Core identity
     */
    private function get_core_identity(): string
    {
        return <<<IDENTITY
## هویت شما
شما یک دستیار هوشمند تخصصی برای صنعت چاپ هستید که:
- درک عمیقی از نیازهای مشتریان چاپی دارید
- می‌توانید بهترین گزینه را بر اساس بودجه و نیاز مشتری پیشنهاد دهید
- قادر به صدور دستورات UI برای راهنمایی کاربر هستید
- همیشه صادق و شفاف هستید و قیمت‌های دقیق ارائه می‌دهید

IDENTITY;
    }

    /**
     * Get business knowledge from knowledge base
     *
     * @param array $options Options for knowledge selection
     * @return string Business knowledge
     */
    private function get_business_knowledge(array $options): string
    {
        $knowledge = "## دانش بیزینس چاپکو\n\n";
        
        // Load relevant knowledge bases
        $kb_types = $options['knowledge_types'] ?? ['products', 'personas', 'responses'];
        
        foreach ($kb_types as $type) {
            $rules_text = $this->knowledge_base->rules_to_prompt($type);
            if (!empty($rules_text)) {
                $knowledge .= $rules_text . "\n";
            }
        }
        
        // Add pricing rules if available
        $pricing_rules = $this->knowledge_base->load_rules('pricing');
        if (!empty($pricing_rules)) {
            $knowledge .= $this->format_pricing_rules($pricing_rules);
        }
        
        return $knowledge;
    }

    /**
     * Format pricing rules for prompt
     *
     * @param array $pricing_rules Pricing rules
     * @return string Formatted pricing rules
     */
    private function format_pricing_rules(array $pricing_rules): string
    {
        $output = "### قوانین قیمت‌گذاری\n";
        
        foreach ($pricing_rules as $category => $rules) {
            if (is_array($rules)) {
                $output .= "**$category:**\n";
                foreach ($rules as $key => $value) {
                    if (is_array($value)) {
                        // Handle nested arrays by converting to JSON or flattening
                        $formatted_value = $this->format_array_value($value);
                        $output .= "  - $key: $formatted_value\n";
                    } else {
                        $output .= "  - $key: $value\n";
                    }
                }
                $output .= "\n";
            }
        }
        
        return $output;
    }

    /**
     * Format array value for display
     * Handles nested arrays safely
     *
     * @param mixed $value Value to format
     * @return string Formatted value
     */
    private function format_array_value($value): string
    {
        if (!is_array($value)) {
            return (string) $value;
        }
        
        // Check if array contains only scalar values
        $has_only_scalars = true;
        foreach ($value as $item) {
            if (is_array($item) || is_object($item)) {
                $has_only_scalars = false;
                break;
            }
        }
        
        if ($has_only_scalars) {
            // Simple array - convert items to strings safely using foreach for better performance
            $string_values = [];
            foreach ($value as $item) {
                // Ensure item is convertible to string
                if (is_scalar($item) || (is_object($item) && method_exists($item, '__toString'))) {
                    $string_values[] = (string) $item;
                }
            }
            return implode(', ', $string_values);
        } else {
            // Complex nested array - convert to JSON for safety
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Get persona context for user
     *
     * @param string $user_identifier User identifier
     * @return string Persona context
     */
    private function get_persona_context(string $user_identifier): string
    {
        $persona = $this->persona_manager->get_dominant_persona($user_identifier);
        
        $context = "## پرسونای کاربر\n";
        $context .= sprintf(
            "- نوع: %s\n- امتیاز: %d\n- اطمینان: %.1f%%\n\n",
            $persona['type'],
            $persona['score'],
            $persona['confidence']
        );
        
        // Add persona-specific recommendations
        $persona_rules = $this->knowledge_base->load_rules('personas');
        if (isset($persona_rules[$persona['type']]['recommendations'])) {
            $context .= "### پیشنهادات مرتبط با این پرسونا:\n";
            foreach ($persona_rules[$persona['type']]['recommendations'] as $recommendation) {
                $context .= "- $recommendation\n";
            }
            $context .= "\n";
        }
        
        return $context;
    }

    /**
     * Get Omni-Store memory context (PR7)
     *
     * @return string Memory context
     */
    private function get_memory_context(): string
    {
        $memory_summary = HT_Vault_Manager::get_memory_summary();
        $persona_prefix = HT_Persona_Engine::get_persona_prompt_prefix();
        
        $context = "## حافظه و زمینه (Omni-Store Memory)\n";
        
        if (!empty($memory_summary)) {
            $context .= $memory_summary . "\n\n";
        }
        
        if (!empty($persona_prefix)) {
            $context .= $persona_prefix . "\n\n";
        }
        
        return $context;
    }

    /**
     * Get WooCommerce context
     *
     * @return string WooCommerce context
     */
    private function get_woocommerce_context(): string
    {
        $full_context = $this->woo_context->get_full_context();
        return "## وضعیت WooCommerce\n" . $this->woo_context->format_for_ai($full_context) . "\n";
    }

    /**
     * Get behavioral context for user
     *
     * @param string $user_identifier User identifier
     * @return string Behavioral context
     */
    private function get_behavioral_context(string $user_identifier): string
    {
        $behavior_summary = $this->persona_manager->get_behavior_summary($user_identifier, 15);
        return "## رفتار اخیر کاربر\n" . $behavior_summary . "\n\n";
    }

    /**
     * Get response guidelines
     *
     * @return string Response guidelines
     */
    private function get_response_guidelines(): string
    {
        return <<<GUIDELINES
## دستورالعمل‌های پاسخ‌دهی

### فرمت خروجی
شما باید پاسخ خود را در قالب JSON با ساختار زیر ارائه دهید:
```json
{
  "thought": "تحلیل شما از وضعیت کاربر و نیاز او",
  "response": "پاسخ متنی که به کاربر نمایش داده می‌شود",
  "action": "نام اکشن UI (اختیاری)",
  "target": "هدف اکشن (selector یا ID)",
  "data": {
    "key": "value"
  },
  "persona_update": "نام پرسونای به‌روز شده (اختیاری)"
}
```

### اکشن‌های مجاز:
- `highlight_element`: هایلایت کردن یک المان
- `show_tooltip`: نمایش tooltip روی المان
- `scroll_to`: اسکرول به یک بخش
- `open_modal`: باز کردن مدال
- `update_calculator`: به‌روزرسانی محاسبه‌گر قیمت
- `suggest_product`: پیشنهاد محصول خاص
- `show_discount`: نمایش تخفیف ویژه

### قوانین مهم:
1. همیشه صادق باشید - قیمت‌های دقیق و واقعی ارائه دهید
2. اگر اطلاعات کافی ندارید، از کاربر سوال کنید
3. پیشنهادات را با دلیل منطقی ارائه کنید
4. از زبان ساده و دوستانه استفاده کنید
5. HALLUCINATION ممنوع است - فقط بر اساس دانش موجود پاسخ دهید

GUIDELINES;
    }

    /**
     * Build user prompt with context
     *
     * @param string $user_message User's message
     * @param string $user_identifier User identifier
     * @param array $additional_context Additional context data
     * @return string Complete prompt
     */
    public function build_user_prompt(
        string $user_message,
        string $user_identifier,
        array $additional_context = []
    ): string {
        $prompt = "";
        
        // Add current page context if provided
        if (!empty($additional_context['current_page'])) {
            $prompt .= "صفحه فعلی کاربر: " . $additional_context['current_page'] . "\n";
        }
        
        // Add current element context if provided
        if (!empty($additional_context['current_element'])) {
            $prompt .= "المان در حال بررسی: " . $additional_context['current_element'] . "\n";
        }
        
        // Add user's question/message
        $prompt .= "\nپیام کاربر: " . $user_message . "\n";
        
        return $prompt;
    }

    /**
     * Get relevant knowledge chunks based on context
     *
     * @param string $context_type Context type (e.g., 'pricing', 'products')
     * @param array $filters Filters for knowledge retrieval
     * @return string Relevant knowledge
     */
    public function get_relevant_knowledge(string $context_type, array $filters = []): string
    {
        $rules = $this->knowledge_base->load_rules($context_type);
        
        if (empty($rules)) {
            return '';
        }
        
        // Apply filters if provided
        if (!empty($filters)) {
            $rules = $this->filter_knowledge($rules, $filters);
        }
        
        return $this->knowledge_base->rules_to_prompt($context_type);
    }

    /**
     * Filter knowledge based on criteria
     *
     * @param array $knowledge Knowledge data
     * @param array $filters Filters to apply
     * @return array Filtered knowledge
     */
    private function filter_knowledge(array $knowledge, array $filters): array
    {
        // Simple filtering implementation
        // Can be extended based on specific needs
        
        if (isset($filters['category']) && isset($knowledge[$filters['category']])) {
            return [$filters['category'] => $knowledge[$filters['category']]];
        }
        
        return $knowledge;
    }

    /**
     * Sanitize user input to prevent prompt injection
     *
     * @param string $input User input
     * @return string Sanitized input
     */
    public function sanitize_input(string $input): string
    {
        // Remove potential prompt injection patterns
        $input = trim($input);
        
        // Remove system instruction injection attempts
        $dangerous_patterns = [
            '/ignore\s+previous\s+instructions?/i',
            '/system\s*:\s*/i',
            '/you\s+are\s+now/i',
            '/forget\s+everything/i',
            '/disregard\s+all/i',
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }
        
        // Limit length to prevent token overflow
        $max_length = 1000;
        if (mb_strlen($input) > $max_length) {
            $input = mb_substr($input, 0, $max_length);
        }
        
        return $input;
    }
}
