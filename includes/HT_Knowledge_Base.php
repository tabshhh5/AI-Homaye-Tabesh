<?php
/**
 * Knowledge Base Controller
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * کنترلر پایگاه دانش
 * مدیریت قوانین بیزینس و تبدیل آنها به System Instructions
 */
class HT_Knowledge_Base
{
    /**
     * Knowledge base directory
     */
    private string $kb_dir;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->kb_dir = HT_PLUGIN_DIR . 'knowledge-base/';
    }

    /**
     * Load business rules from JSON file
     *
     * @param string $rule_type Rule type (e.g., 'products', 'personas', 'responses')
     * @return array Rules data
     */
    public function load_rules(string $rule_type): array
    {
        $file_path = $this->kb_dir . $rule_type . '.json';

        if (!file_exists($file_path)) {
            error_log(sprintf('Homaye Tabesh - Knowledge base file not found: %s', basename($file_path)));
            return [];
        }

        $content = file_get_contents($file_path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log(sprintf('Homaye Tabesh - Failed to parse knowledge base file: %s', basename($file_path)));
            return [];
        }

        return $data;
    }

    /**
     * Get all available knowledge bases
     *
     * @return array List of available knowledge bases
     */
    public function get_available_bases(): array
    {
        if (!is_dir($this->kb_dir)) {
            return [];
        }

        $files = glob($this->kb_dir . '*.json');
        $bases = [];

        foreach ($files as $file) {
            $bases[] = basename($file, '.json');
        }

        return $bases;
    }

    /**
     * Convert rules to prompt instructions
     *
     * @param string $rule_type Rule type
     * @return string Prompt instructions
     */
    public function rules_to_prompt(string $rule_type): string
    {
        $rules = $this->load_rules($rule_type);

        if (empty($rules)) {
            return '';
        }

        $prompt = '';

        switch ($rule_type) {
            case 'products':
                $prompt = $this->format_product_rules($rules);
                break;
            case 'personas':
                $prompt = $this->format_persona_rules($rules);
                break;
            case 'responses':
                $prompt = $this->format_response_rules($rules);
                break;
            default:
                $prompt = wp_json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return $prompt;
    }

    /**
     * Format product rules for prompt
     *
     * @param array $rules Product rules
     * @return string Formatted prompt
     */
    private function format_product_rules(array $rules): string
    {
        $prompt = "قوانین محصولات:\n\n";

        foreach ($rules as $category => $items) {
            $prompt .= "دسته‌بندی: $category\n";
            
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (is_array($item)) {
                        $prompt .= sprintf(
                            "  - %s: %s\n",
                            $item['name'] ?? '',
                            $item['description'] ?? ''
                        );
                    }
                }
            }
            
            $prompt .= "\n";
        }

        return $prompt;
    }

    /**
     * Format persona rules for prompt
     *
     * @param array $rules Persona rules
     * @return string Formatted prompt
     */
    private function format_persona_rules(array $rules): string
    {
        $prompt = "قوانین شناسایی پرسونا:\n\n";

        foreach ($rules as $persona => $config) {
            $prompt .= "پرسونای $persona:\n";
            
            if (isset($config['indicators'])) {
                $prompt .= "  نشانه‌ها:\n";
                foreach ($config['indicators'] as $indicator) {
                    $prompt .= "    - $indicator\n";
                }
            }
            
            if (isset($config['recommendations'])) {
                $prompt .= "  توصیه‌ها:\n";
                foreach ($config['recommendations'] as $recommendation) {
                    $prompt .= "    - $recommendation\n";
                }
            }
            
            $prompt .= "\n";
        }

        return $prompt;
    }

    /**
     * Format response rules for prompt
     *
     * @param array $rules Response rules
     * @return string Formatted prompt
     */
    private function format_response_rules(array $rules): string
    {
        $prompt = "قوانین پاسخ‌دهی:\n\n";

        if (isset($rules['tone'])) {
            $prompt .= "لحن: " . $rules['tone'] . "\n";
        }

        if (isset($rules['style'])) {
            $prompt .= "سبک: " . $rules['style'] . "\n";
        }

        if (isset($rules['guidelines'])) {
            $prompt .= "\nدستورالعمل‌ها:\n";
            foreach ($rules['guidelines'] as $guideline) {
                $prompt .= "  - $guideline\n";
            }
        }

        if (isset($rules['forbidden'])) {
            $prompt .= "\nممنوعیت‌ها:\n";
            foreach ($rules['forbidden'] as $item) {
                $prompt .= "  - $item\n";
            }
        }

        return $prompt;
    }

    /**
     * Get complete system instruction
     *
     * @param array $include_rules Rule types to include
     * @return string Complete system instruction
     */
    public function get_system_instruction(array $include_rules = ['products', 'personas', 'responses']): string
    {
        $instruction = "شما دستیار هوشمند همای تابش برای وبسایت چاپکو هستید.\n\n";

        foreach ($include_rules as $rule_type) {
            $rules_text = $this->rules_to_prompt($rule_type);
            if (!empty($rules_text)) {
                $instruction .= $rules_text . "\n";
            }
        }

        return $instruction;
    }

    /**
     * Save rules to JSON file
     *
     * @param string $rule_type Rule type
     * @param array $rules Rules data
     * @return bool Success status
     */
    public function save_rules(string $rule_type, array $rules): bool
    {
        // Create directory if it doesn't exist
        if (!is_dir($this->kb_dir)) {
            wp_mkdir_p($this->kb_dir);
        }

        $file_path = $this->kb_dir . $rule_type . '.json';
        $content = wp_json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return (bool) file_put_contents($file_path, $content);
    }

    /**
     * Initialize default knowledge base files
     *
     * @return void
     */
    public function init_default_knowledge_base(): void
    {
        // Create directory if it doesn't exist
        if (!is_dir($this->kb_dir)) {
            wp_mkdir_p($this->kb_dir);
        }

        // Initialize default personas
        if (!file_exists($this->kb_dir . 'personas.json')) {
            $default_personas = [
                'author' => [
                    'indicators' => [
                        'مشاهده بخش مجوزهای چاپ',
                        'بررسی قیمت‌های عمده',
                        'جستجوی ISBN و حق مؤلف',
                    ],
                    'recommendations' => [
                        'پکیج‌های چاپ کتاب',
                        'خدمات ویراستاری',
                        'طراحی جلد حرفه‌ای',
                    ],
                ],
                'business' => [
                    'indicators' => [
                        'بررسی سفارش عمده',
                        'مشاهده قیمت‌های فله‌ای',
                        'جستجوی فاکتور رسمی',
                    ],
                    'recommendations' => [
                        'چاپ کاتالوگ',
                        'چاپ بروشور شرکتی',
                        'چاپ کارت ویزیت انبوه',
                    ],
                ],
                'designer' => [
                    'indicators' => [
                        'بررسی نمونه‌های طراحی',
                        'مشاهده اسپک فنی چاپ',
                        'جستجوی CMYK و رنگ‌بندی',
                    ],
                    'recommendations' => [
                        'چاپ پوستر',
                        'چاپ استند',
                        'چاپ بنر با کیفیت بالا',
                    ],
                ],
            ];
            $this->save_rules('personas', $default_personas);
        }

        // Initialize default response rules
        if (!file_exists($this->kb_dir . 'responses.json')) {
            $default_responses = [
                'tone' => 'دوستانه، حرفه‌ای و راهنما',
                'style' => 'مستقیم با ارائه اطلاعات دقیق',
                'guidelines' => [
                    'همیشه با سلام شروع کن',
                    'از زبان ساده و روان استفاده کن',
                    'اطلاعات فنی را به زبان ساده توضیح بده',
                    'پیشنهادات را با دلیل ارائه کن',
                ],
                'forbidden' => [
                    'استفاده از اصطلاحات تخصصی بدون توضیح',
                    'پاسخ‌های مبهم یا کلی',
                    'وعده‌های غیرواقعی',
                ],
            ];
            $this->save_rules('responses', $default_responses);
        }
    }
}
