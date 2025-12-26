<?php
/**
 * Gemini 2.5 Flash API Client
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * موتور استنتاج Gemini 2.5 Flash
 * پشتیبانی از Structured Outputs و Context Injection
 */
class HT_Gemini_Client
{
    /**
     * API base URL
     */
    private const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * Model name
     */
    private const MODEL_NAME = 'gemini-2.0-flash-exp';

    /**
     * API key
     */
    private string $api_key;

    /**
     * WooCommerce availability cache
     */
    private ?bool $woocommerce_active = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api_key = get_option('ht_gemini_api_key', '');
        $this->woocommerce_active = class_exists('WooCommerce');
    }

    /**
     * Generate content with structured JSON output
     *
     * @param string $prompt User prompt
     * @param array $context Additional context data
     * @param array $schema JSON schema for response structure
     * @return array Response data
     */
    public function generate_content(string $prompt, array $context = [], array $schema = []): array
    {
        if (empty($this->api_key)) {
            return $this->get_fallback_response('API key not configured');
        }

        try {
            $system_instruction = $this->build_system_instruction($context);
            $generation_config = $this->build_generation_config($schema);

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $system_instruction]
                    ]
                ],
                'generationConfig' => $generation_config
            ];

            $response = $this->make_request($payload);

            return $this->parse_response($response);
        } catch (\Exception $e) {
            error_log('Homaye Tabesh - Gemini API Error: ' . $e->getMessage());
            return $this->get_fallback_response($e->getMessage());
        }
    }

    /**
     * Build system instruction from context
     *
     * @param array $context Context data
     * @return string System instruction
     */
    private function build_system_instruction(array $context): string
    {
        $instruction = "شما یک دستیار هوشمند فروش برای وبسایت چاپکو هستید. ";
        $instruction .= "وظیفه شما کمک به کاربران در انتخاب بهترین محصولات چاپی است.\n\n";

        // Add WooCommerce product context
        if (!empty($context['products'])) {
            $instruction .= "محصولات موجود:\n";
            foreach ($context['products'] as $product) {
                $instruction .= sprintf(
                    "- %s: %s (%s)\n",
                    $product['name'] ?? '',
                    $product['description'] ?? '',
                    $product['price'] ?? ''
                );
            }
        }

        // Add user behavior context
        if (!empty($context['behavior'])) {
            $instruction .= "\nرفتار کاربر:\n";
            $instruction .= $context['behavior'];
        }

        // Add persona context
        if (!empty($context['persona'])) {
            $instruction .= sprintf(
                "\nپرسونای احتمالی کاربر: %s (امتیاز: %d)\n",
                $context['persona']['type'] ?? 'نامشخص',
                $context['persona']['score'] ?? 0
            );
        }

        return $instruction;
    }

    /**
     * Build generation config with JSON schema
     *
     * @param array $schema JSON schema
     * @param float $temperature Temperature setting (default 0.7)
     * @return array Generation config
     */
    private function build_generation_config(array $schema, float $temperature = 0.7): array
    {
        $config = [
            'temperature' => $temperature,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 2048,
        ];

        if (!empty($schema)) {
            $config['responseMimeType'] = 'application/json';
            $config['responseSchema'] = $schema;
        }

        return $config;
    }

    /**
     * Make HTTP request to Gemini API
     *
     * @param array $payload Request payload
     * @return array Response data
     * @throws \Exception If request fails
     */
    private function make_request(array $payload): array
    {
        $url = self::API_BASE_URL . self::MODEL_NAME . ':generateContent?key=' . $this->api_key;

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            throw new \Exception("API request failed with status $status_code: $body");
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse API response');
        }

        return $data;
    }

    /**
     * Parse Gemini API response
     *
     * @param array $response Raw response
     * @return array Parsed data
     */
    private function parse_response(array $response): array
    {
        if (empty($response['candidates'][0]['content']['parts'][0]['text'])) {
            return $this->get_fallback_response('Empty response from API');
        }

        $text = $response['candidates'][0]['content']['parts'][0]['text'];

        // Try to parse as JSON if it looks like JSON
        if (str_starts_with(trim($text), '{') || str_starts_with(trim($text), '[')) {
            $json = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'success' => true,
                    'data' => $json,
                    'raw_text' => $text,
                ];
            }
        }

        return [
            'success' => true,
            'data' => null,
            'raw_text' => $text,
        ];
    }

    /**
     * Get fallback response on error
     *
     * @param string $error Error message
     * @return array Fallback response
     */
    private function get_fallback_response(string $error): array
    {
        return [
            'success' => false,
            'error' => $error,
            'data' => [
                'message' => 'متأسفانه در حال حاضر امکان پاسخگویی وجود ندارد. لطفاً بعداً تلاش کنید.',
            ],
        ];
    }

    /**
     * Inject WooCommerce product data as context
     *
     * @param array $product_ids Product IDs to include
     * @return array Product data
     */
    public function get_woocommerce_context(array $product_ids = []): array
    {
        if (!$this->woocommerce_active) {
            return [];
        }

        $products = [];

        if (empty($product_ids)) {
            // Get featured products if no IDs specified
            $args = [
                'post_type' => 'product',
                'posts_per_page' => 10,
                'tax_query' => [
                    [
                        'taxonomy' => 'product_visibility',
                        'field' => 'name',
                        'terms' => 'featured',
                    ],
                ],
            ];
            $query = new \WP_Query($args);
            $product_ids = wp_list_pluck($query->posts, 'ID');
        }

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $products[] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'description' => wp_strip_all_tags($product->get_short_description()),
                    'price' => $product->get_price_html(),
                    'categories' => wp_list_pluck($product->get_category_ids(), 'name'),
                ];
            }
        }

        return $products;
    }

    /**
     * Get JSON response with custom system instruction
     * Optimized for inference engine with low temperature
     *
     * @param string $prompt User prompt
     * @param string $system_instruction Custom system instruction
     * @param array $schema JSON schema for structured output
     * @return array Response data
     */
    public function get_json_response(
        string $prompt,
        string $system_instruction,
        array $schema = []
    ): array {
        if (empty($this->api_key)) {
            return $this->get_fallback_response('API key not configured');
        }

        try {
            // Use low temperature for accuracy (anti-hallucination)
            $generation_config = $this->build_generation_config($schema, 0.1);

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $system_instruction]
                    ]
                ],
                'generationConfig' => $generation_config
            ];

            $response = $this->make_request($payload);

            return $this->parse_response($response);
        } catch (\Exception $e) {
            error_log('Homaye Tabesh - Gemini JSON Response Error: ' . $e->getMessage());
            return $this->get_fallback_response($e->getMessage());
        }
    }

    /**
     * Generate response with visual commands
     * Instructs Gemini to include visual guidance commands in response
     * 
     * @param string $prompt User prompt
     * @param array $context Additional context
     * @param array $page_elements Available page elements for targeting
     * @return array Response with visual commands
     */
    public function generate_with_visual_commands(
        string $prompt,
        array $context = [],
        array $page_elements = []
    ): array {
        $system_instruction = $this->build_visual_guidance_instruction($context, $page_elements);
        
        try {
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $system_instruction]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 2048,
                ]
            ];

            $response = $this->make_request($payload);
            $parsed = $this->parse_response($response);

            // Extract visual commands from response
            if ($parsed['success'] && !empty($parsed['raw_text'])) {
                $visual_commands = $this->extract_visual_commands($parsed['raw_text']);
                $parsed['visual_commands'] = $visual_commands;
            }

            return $parsed;
        } catch (\Exception $e) {
            error_log('Homaye Tabesh - Visual Commands Generation Error: ' . $e->getMessage());
            return $this->get_fallback_response($e->getMessage());
        }
    }

    /**
     * Build system instruction for visual guidance
     * 
     * @param array $context Context data
     * @param array $page_elements Available page elements
     * @return string System instruction
     */
    private function build_visual_guidance_instruction(array $context, array $page_elements): string
    {
        $instruction = "شما یک دستیار هوشمند هستید که می‌تواند کاربر را در صفحه وب به صورت بصری راهنمایی کنید.\n\n";
        
        $instruction .= "قابلیت‌های شما:\n";
        $instruction .= "1. هایلایت کردن المان‌های صفحه\n";
        $instruction .= "2. اسکرول کردن صفحه به المان خاص\n";
        $instruction .= "3. نمایش تولتیپ راهنما\n\n";

        $instruction .= "دستورات بصری:\n";
        $instruction .= "- برای هایلایت: ACTION: HIGHLIGHT[selector]\n";
        $instruction .= "- برای اسکرول: ACTION: SCROLL_TO[selector]\n";
        $instruction .= "- برای تولتیپ: ACTION: TOOLTIP[selector, message]\n\n";

        if (!empty($page_elements)) {
            $instruction .= "المان‌های موجود در صفحه:\n";
            foreach ($page_elements as $element) {
                $instruction .= sprintf(
                    "- %s: selector = %s\n",
                    $element['label'] ?? 'نامشخص',
                    $element['selector'] ?? ''
                );
            }
            $instruction .= "\n";
        }

        $instruction .= "مثال:\n";
        $instruction .= "کاربر: چطوری سفارش بدم؟\n";
        $instruction .= "شما: برای ثبت سفارش، روی دکمه زیر کلیک کنید.\nACTION: HIGHLIGHT[.checkout-button]\nACTION: SCROLL_TO[.checkout-button]\n\n";

        $instruction .= "نکات مهم:\n";
        $instruction .= "1. فقط زمانی از دستورات بصری استفاده کنید که واقعاً لازم باشد\n";
        $instruction .= "2. از selector های معتبر CSS استفاده کنید\n";
        $instruction .= "3. دستورات را در خط جداگانه بعد از متن پاسخ بنویسید\n";
        $instruction .= "4. در هر پاسخ حداکثر 2-3 دستور بصری استفاده کنید\n\n";

        // Add context
        if (!empty($context['page_type'])) {
            $instruction .= "صفحه فعلی: " . $context['page_type'] . "\n";
        }

        if (!empty($context['user_intent'])) {
            $instruction .= "هدف احتمالی کاربر: " . $context['user_intent'] . "\n";
        }

        return $instruction;
    }

    /**
     * Extract visual commands from AI response text
     * 
     * @param string $text Response text
     * @return array Visual commands
     */
    private function extract_visual_commands(string $text): array
    {
        $commands = [];

        // Pattern 1: ACTION: HIGHLIGHT[selector]
        if (preg_match_all('/ACTION:\s*HIGHLIGHT\[([^\]]+)\]/i', $text, $matches)) {
            foreach ($matches[1] as $selector) {
                $commands[] = [
                    'action_type' => 'ui_interaction',
                    'command' => 'HIGHLIGHT',
                    'target_selector' => trim($selector)
                ];
            }
        }

        // Pattern 2: ACTION: SCROLL_TO[selector]
        if (preg_match_all('/ACTION:\s*SCROLL_TO\[([^\]]+)\]/i', $text, $matches)) {
            foreach ($matches[1] as $selector) {
                $commands[] = [
                    'action_type' => 'ui_interaction',
                    'command' => 'SCROLL_TO',
                    'target_selector' => trim($selector)
                ];
            }
        }

        // Pattern 3: ACTION: TOOLTIP[selector, message]
        if (preg_match_all('/ACTION:\s*TOOLTIP\[([^,]+),\s*([^\]]+)\]/i', $text, $matches)) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                $commands[] = [
                    'action_type' => 'ui_interaction',
                    'command' => 'SHOW_TOOLTIP',
                    'target_selector' => trim($matches[1][$i]),
                    'message' => trim($matches[2][$i])
                ];
            }
        }

        return $commands;
    }

    /**
     * Clean response text by removing visual command syntax
     * 
     * @param string $text Text with visual commands
     * @return string Clean text
     */
    public function clean_visual_commands(string $text): string
    {
        // Remove all visual command patterns
        $text = preg_replace('/ACTION:\s*HIGHLIGHT\[[^\]]+\]/i', '', $text);
        $text = preg_replace('/ACTION:\s*SCROLL_TO\[[^\]]+\]/i', '', $text);
        $text = preg_replace('/ACTION:\s*TOOLTIP\[[^\]]+\]/i', '', $text);
        
        // Clean up extra whitespace
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);
        
        return $text;
    }
}
