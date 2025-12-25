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
     * @return array Generation config
     */
    private function build_generation_config(array $schema): array
    {
        $config = [
            'temperature' => 0.7,
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
}
