<?php
/**
 * AI Client (GapGPT Gateway)
 * Uses GapGPT API for access to multiple AI models
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * موتور استنتاج هوش مصنوعی
 * پشتیبانی از GapGPT API برای دسترسی به مدل‌های متنوع
 */
class HT_Gemini_Client
{
    /**
     * API key
     */
    private string $api_key;

    /**
     * Provider (always gapgpt now)
     */
    private string $provider;

    /**
     * Model name
     */
    private string $model;

    /**
     * Base URL (for GapGPT)
     */
    private string $base_url;

    /**
     * WooCommerce availability cache
     */
    private ?bool $woocommerce_active = null;

    /**
     * Constructor
     *
     * Note: Constructor must not call WordPress functions as they may not be available yet.
     * API key is loaded on-demand via get_api_key() method.
     */
    public function __construct()
    {
        // Defer WordPress function calls - check WooCommerce availability
        $this->woocommerce_active = class_exists('WooCommerce');
        // Do NOT call get_option() here - API key loaded on-demand
        $this->api_key = '';
        $this->provider = '';
        $this->model = '';
        $this->base_url = '';
    }
    
    /**
     * Get API key and configuration (lazy loading)
     *
     * @return void
     */
    private function load_config(): void
    {
        if (empty($this->provider) && function_exists('get_option')) {
            // Always use GapGPT
            $this->provider = 'gapgpt';
            $this->model = get_option('ht_ai_model', 'gemini-2.5-flash');
            $this->base_url = get_option('ht_gapgpt_base_url', 'https://api.gapgpt.app/v1');
            $this->api_key = get_option('ht_gapgpt_api_key', '');
        }
    }

    /**
     * Get API key (legacy method for backward compatibility)
     *
     * @return string
     */
    private function get_api_key(): string
    {
        $this->load_config();
        return $this->api_key;
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
        // PR18: Check fallback engine status
        $fallback_engine = null;
        if (class_exists('\HomayeTabesh\HT_Fallback_Engine')) {
            $fallback_engine = new HT_Fallback_Engine();
            if ($fallback_engine->is_offline()) {
                return $fallback_engine->get_fallback_response($prompt, $context);
            }
        }

        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            return $this->get_fallback_response('API key not configured');
        }

        // PR18: Start timing for latency tracking
        $start_time = microtime(true);

        try {
            // PR17: Enhance context with authority-checked facts
            $context = $this->enhance_context_with_authority($context);

            // PR16: Apply LLM Shield - Input Firewall
            if (class_exists('\HomayeTabesh\HT_LLM_Shield_Layer')) {
                $shield = new HT_LLM_Shield_Layer();
                
                // Skip shield for trusted users (administrators)
                if (!$shield->is_trusted_user()) {
                    $user_identifier = $this->get_user_identifier();
                    $filter_result = $shield->filter_input($prompt, $user_identifier);
                    
                    // Check if 'allowed' key exists before accessing
                    if (isset($filter_result['allowed']) && !$filter_result['allowed']) {
                        return $this->get_fallback_response(
                            'متاسفم، این درخواست مجاز نیست. لطفاً سوال دیگری بپرسید.'
                        );
                    }
                    
                    // Use filtered prompt if available, otherwise keep original
                    $prompt = $filter_result['prompt'] ?? $prompt;
                }
            }

            $system_instruction = $this->build_system_instruction($context);
            
            // PR16: Enhance system instruction with safety rules
            if (class_exists('\HomayeTabesh\HT_LLM_Shield_Layer')) {
                $shield = new HT_LLM_Shield_Layer();
                $system_instruction = $shield->enhance_system_instruction($system_instruction);
            }
            
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
            $parsed_response = $this->parse_response($response);
            
            // PR18: Calculate latency
            $latency_ms = (int) ((microtime(true) - $start_time) * 1000);
            
            // PR16: Apply LLM Shield - Output Firewall
            if (class_exists('\HomayeTabesh\HT_LLM_Shield_Layer')) {
                $shield = new HT_LLM_Shield_Layer();
                
                if (!$shield->is_trusted_user()) {
                    $user_identifier = $this->get_user_identifier();
                    $response_text = $parsed_response['response'] ?? '';
                    
                    $filter_result = $shield->filter_output($response_text, $user_identifier);
                    
                    // Use the filtered response (which may be sanitized or blocked)
                    if (isset($filter_result['response'])) {
                        $parsed_response['response'] = $filter_result['response'];
                    }
                }
            }

            // PR17: Execute actions if present using Orchestrator
            if (isset($parsed_response['actions']) && !empty($parsed_response['actions'])) {
                if (class_exists('\HomayeTabesh\HT_Action_Orchestrator')) {
                    $orchestrator = HT_Core::instance()->action_orchestrator;
                    $action_result = $orchestrator->execute_actions($parsed_response['actions'], $context);
                    
                    // Update response with action results
                    // Check if 'success' key exists before accessing
                    if (isset($action_result['success']) && $action_result['success']) {
                        $parsed_response['response'] = $action_result['message'] ?? '';
                        $parsed_response['action_results'] = $action_result['results'] ?? [];
                    } else {
                        $parsed_response['response'] = 'خطا در انجام عملیات: ' . ($action_result['error'] ?? 'خطای نامشخص');
                        $parsed_response['action_error'] = $action_result;
                    }
                }
            }

            // PR18: Log successful transaction to BlackBox
            if (class_exists('\HomayeTabesh\HT_BlackBox_Logger')) {
                $this->load_config();
                $logger = new HT_BlackBox_Logger();
                $logger->log_ai_transaction([
                    'log_type' => 'ai_transaction',
                    'user_prompt' => $prompt,
                    'raw_prompt' => wp_json_encode($payload),
                    'ai_response' => $parsed_response['response'] ?? '',
                    'raw_response' => wp_json_encode($response),
                    'latency_ms' => $latency_ms,
                    'tokens_used' => $response['usageMetadata']['totalTokenCount'] ?? null,
                    'model_name' => $this->model ?? 'unknown',
                    'context_data' => $context,
                    'status' => 'success',
                ]);
            }

            // PR18: Record success in fallback engine
            if ($fallback_engine) {
                $fallback_engine->record_api_result(true);
            }

            return $parsed_response;
        } catch (\Exception $e) {
            error_log('Homaye Tabesh - GapGPT API Error: ' . $e->getMessage());
            
            // PR18: Log error to BlackBox
            if (class_exists('\HomayeTabesh\HT_BlackBox_Logger')) {
                $logger = new HT_BlackBox_Logger();
                $logger->log_error($e, [
                    'user_prompt' => $prompt,
                    'context' => $context,
                ]);
            }

            // PR18: Record failure in fallback engine
            if ($fallback_engine) {
                $fallback_engine->record_api_result(false);
            }

            return $this->get_fallback_response($e->getMessage());
        }
    }
    
    /**
     * Generate simple response (legacy method for backward compatibility)
     * This is an alias for generate_content with simpler parameters
     *
     * @param string $prompt User prompt
     * @param array $context Additional context data (optional)
     * @return array Response data with 'response' key
     */
    public function generate_response(string $prompt, array $context = []): array
    {
        $result = $this->generate_content($prompt, $context);
        
        // Ensure result always has 'success' key
        if (!isset($result['success'])) {
            $result['success'] = false;
            $result['error'] = 'Invalid response structure';
        }
        
        // Ensure response has 'response' key for backward compatibility
        if ($result['success'] && !isset($result['response'])) {
            if (isset($result['raw_text'])) {
                $result['response'] = $result['raw_text'];
            } elseif (isset($result['data']['message'])) {
                $result['response'] = $result['data']['message'];
            } else {
                $result['response'] = 'متأسفانه پاسخی دریافت نشد.';
            }
        } elseif (!$result['success']) {
            $result['response'] = $result['data']['message'] ?? 'خطا در دریافت پاسخ';
        }
        
        return $result;
    }

    /**
     * Get user identifier for tracking
     *
     * @return string User identifier
     */
    private function get_user_identifier(): string
    {
        if (class_exists('\HomayeTabesh\HT_User_Behavior_Tracker')) {
            $tracker = new HT_User_Behavior_Tracker();
            return $tracker->get_user_identifier();
        }
        
        return is_user_logged_in() ? 'user_' . get_current_user_id() : 'guest';
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

        // PR17: Add Authority System Instructions
        $instruction .= "سیستم سلسله‌مراتب اعتبار دانش:\n";
        $instruction .= "1. بالاترین اولویت: اصلاحات دستی مدیر (Manual Overrides)\n";
        $instruction .= "2. تنظیمات مستقیم پنل مدیریت\n";
        $instruction .= "3. داده‌های زنده از سیستم و WooCommerce\n";
        $instruction .= "4. دانش عمومی شما\n";
        $instruction .= "در صورت تضاد اطلاعات، همیشه از سطح بالاتر استفاده کنید.\n\n";

        // PR17: Add Action Orchestration Instructions
        $instruction .= "قابلیت اجرای عملیات چندگانه:\n";
        $instruction .= "می‌توانید چندین عملیات را به صورت زنجیره‌ای انجام دهید:\n";
        $instruction .= "- verify_otp: تایید کد یکبار مصرف\n";
        $instruction .= "- create_order: ثبت سفارش\n";
        $instruction .= "- add_to_cart: افزودن به سبد خرید\n";
        $instruction .= "- send_sms: ارسال پیامک\n";
        $instruction .= "- save_lead: ذخیره سرنخ\n";
        $instruction .= "برای انجام عملیات، در پاسخ خود یک آرایه 'actions' قرار دهید.\n\n";

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

        // PR17: Add authority-checked facts to context
        if (!empty($context['checked_facts'])) {
            $instruction .= "\nفکت‌های تایید شده (با بالاترین اعتبار):\n";
            foreach ($context['checked_facts'] as $key => $value) {
                $instruction .= sprintf("- %s: %s\n", $key, $value);
            }
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
     * Make API request to GapGPT
     *
     * @param array $payload Request payload
     * @return array Response data
     * @throws \Exception
     */
    private function make_request(array $payload): array
    {
        $this->load_config();
        
        if (empty($this->api_key)) {
            throw new \Exception('API key not configured for GapGPT');
        }

        // Always use GapGPT
        return $this->make_gapgpt_request($payload);
    }

    /**
     * Make request to GapGPT (OpenAI-compatible)
     *
     * @param array $payload Gemini-style payload
     * @return array Response data
     * @throws \Exception
     */
    private function make_gapgpt_request(array $gemini_payload): array
    {
        // Convert Gemini payload to OpenAI format
        $messages = [];
        
        // Add system instruction if present (with proper validation)
        if (isset($gemini_payload['systemInstruction']['parts']) && 
            is_array($gemini_payload['systemInstruction']['parts']) &&
            isset($gemini_payload['systemInstruction']['parts'][0]['text'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $gemini_payload['systemInstruction']['parts'][0]['text']
            ];
        }
        
        // Add user message (with proper validation)
        if (isset($gemini_payload['contents']) && 
            is_array($gemini_payload['contents']) &&
            isset($gemini_payload['contents'][0]['parts']) &&
            is_array($gemini_payload['contents'][0]['parts']) &&
            isset($gemini_payload['contents'][0]['parts'][0]['text'])) {
            $messages[] = [
                'role' => 'user',
                'content' => $gemini_payload['contents'][0]['parts'][0]['text']
            ];
        }
        
        // Ensure at least one message exists
        if (empty($messages)) {
            throw new \Exception('No valid messages found in payload');
        }
        
        // Build OpenAI-compatible payload
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
        ];
        
        // Add temperature if specified
        if (isset($gemini_payload['generationConfig']['temperature'])) {
            $payload['temperature'] = $gemini_payload['generationConfig']['temperature'];
        }
        
        $url = rtrim($this->base_url, '/') . '/chat/completions';
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Handle error codes
        if ($status_code === 429) {
            throw new \Exception('quota_exceeded:سهمیه API تمام شده است. لطفاً بعداً تلاش کنید.');
        }
        
        if ($status_code === 401) {
            throw new \Exception('auth_failed:کلید API نامعتبر است. لطفاً تنظیمات را بررسی کنید.');
        }
        
        if ($status_code === 403) {
            throw new \Exception('access_denied:دسترسی مسدود شده است.');
        }
        
        if ($status_code !== 200) {
            $error_details = json_decode($body, true);
            $error_message = $error_details['error']['message'] ?? 'API request failed';
            throw new \Exception("API request failed with status $status_code: $error_message");
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse API response');
        }

        // Validate response structure
        if (empty($data['choices']) || !is_array($data['choices']) || !isset($data['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid response structure from GapGPT API');
        }

        // Convert OpenAI response format to Gemini-compatible format
        $converted = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => $data['choices'][0]['message']['content']
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        // Add usage metadata if available
        if (!empty($data['usage'])) {
            $converted['usageMetadata'] = [
                'promptTokenCount' => $data['usage']['prompt_tokens'] ?? 0,
                'candidatesTokenCount' => $data['usage']['completion_tokens'] ?? 0,
                'totalTokenCount' => $data['usage']['total_tokens'] ?? 0,
            ];
        }

        return $converted;
    }


    /**
     * Parse API response
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
        // Parse error with prefix pattern (error_type:message)
        $error_types = [
            'quota_exceeded' => 'quota_exceeded',
            'auth_failed' => 'auth_failed',
            'access_denied' => 'access_denied',
            'service_unavailable' => 'service_unavailable',
        ];
        
        foreach ($error_types as $prefix => $error_code) {
            if (str_starts_with($error, $prefix . ':')) {
                $message = substr($error, strlen($prefix) + 1);
                return [
                    'success' => false,
                    'error' => $error_code,
                    'data' => [
                        'message' => $message,
                    ],
                ];
            }
        }
        
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
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
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
            error_log('Homaye Tabesh - GapGPT JSON Response Error: ' . $e->getMessage());
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
            if (isset($parsed['success']) && $parsed['success'] && !empty($parsed['raw_text'])) {
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

    /**
     * Enhance context with authority-checked facts (PR17)
     * 
     * @param array $context Original context
     * @return array Enhanced context with checked facts
     */
    private function enhance_context_with_authority(array $context): array
    {
        if (!class_exists('\HomayeTabesh\HT_Authority_Manager')) {
            return $context;
        }

        $authority_manager = HT_Core::instance()->authority_manager;
        $checked_facts = [];

        // Define facts that should be checked with authority manager
        $fact_keys_to_check = [
            'shipping_cost',
            'min_order_value',
            'delivery_time',
            'support_phone',
            'support_email',
        ];

        // Check product prices if products are in context
        if (!empty($context['products'])) {
            foreach ($context['products'] as $product) {
                if (isset($product['id'])) {
                    $fact_key = 'product_price_' . $product['id'];
                    $fact_keys_to_check[] = $fact_key;
                }
            }
        }

        // Get checked facts from authority manager
        foreach ($fact_keys_to_check as $key) {
            $value = $authority_manager->get_final_fact($key, $context);
            if ($value !== null) {
                $checked_facts[$key] = $value;
            }
        }

        // Add checked facts to context
        if (!empty($checked_facts)) {
            $context['checked_facts'] = $checked_facts;
        }

        return $context;
    }
}
