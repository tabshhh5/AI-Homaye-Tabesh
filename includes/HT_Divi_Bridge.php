<?php
/**
 * Divi Bridge Controller
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * هماهنگسازی کلاسهای CSS دیوی با منطق شناسایی هما
 * Mapping Divi Classes to Business Logic
 */
class HT_Divi_Bridge
{
    /**
     * Divi CSS class to business logic mapping
     */
    private const MODULE_MAPPING = [
        'et_pb_pricing_table' => [
            'type' => 'pricing',
            'category' => 'commercial',
            'intent' => 'purchase_consideration',
            'persona_weight' => ['business' => 15, 'author' => 10],
        ],
        'et_pb_contact_form' => [
            'type' => 'contact',
            'category' => 'engagement',
            'intent' => 'inquiry',
            'persona_weight' => ['general' => 5],
        ],
        'et_pb_wc_price' => [
            'type' => 'product_price',
            'category' => 'commercial',
            'intent' => 'purchase_interest',
            'persona_weight' => ['business' => 10, 'author' => 8],
        ],
        'et_pb_wc_add_to_cart' => [
            'type' => 'add_to_cart',
            'category' => 'conversion',
            'intent' => 'purchase_action',
            'persona_weight' => ['business' => 20, 'author' => 15],
        ],
        'et_pb_button' => [
            'type' => 'button',
            'category' => 'interaction',
            'intent' => 'navigation',
            'persona_weight' => ['general' => 3],
        ],
        'et_pb_cta' => [
            'type' => 'call_to_action',
            'category' => 'conversion',
            'intent' => 'engagement',
            'persona_weight' => ['general' => 8],
        ],
        'et_pb_gallery' => [
            'type' => 'gallery',
            'category' => 'content',
            'intent' => 'exploration',
            'persona_weight' => ['designer' => 10, 'general' => 5],
        ],
        'et_pb_portfolio' => [
            'type' => 'portfolio',
            'category' => 'content',
            'intent' => 'exploration',
            'persona_weight' => ['designer' => 15, 'business' => 8],
        ],
        'et_pb_testimonial' => [
            'type' => 'testimonial',
            'category' => 'social_proof',
            'intent' => 'trust_building',
            'persona_weight' => ['general' => 5],
        ],
    ];

    /**
     * Special content-based detection rules
     */
    private const CONTENT_PATTERNS = [
        'calculator' => [
            'keywords' => ['محاسبه', 'قیمت', 'تیراژ', 'calculator', 'price'],
            'type' => 'calculator',
            'category' => 'tool',
            'intent' => 'price_calculation',
            'persona_weight' => ['author' => 20, 'business' => 15],
        ],
        'licensing' => [
            'keywords' => ['مجوز', 'حق', 'کپی‌رایت', 'license', 'permission', 'ISBN'],
            'type' => 'licensing',
            'category' => 'legal',
            'intent' => 'rights_inquiry',
            'persona_weight' => ['author' => 25],
        ],
        'bulk_order' => [
            'keywords' => ['عمده', 'انبوه', 'تیراژ بالا', 'bulk', 'wholesale'],
            'type' => 'bulk_order',
            'category' => 'commercial',
            'intent' => 'bulk_purchase',
            'persona_weight' => ['business' => 20, 'author' => 10],
        ],
        'design_specs' => [
            'keywords' => ['طراحی', 'CMYK', 'DPI', 'رنگ', 'design', 'color'],
            'type' => 'design_specs',
            'category' => 'technical',
            'intent' => 'design_inquiry',
            'persona_weight' => ['designer' => 20, 'author' => 8],
        ],
        'student_discount' => [
            'keywords' => ['دانشجویی', 'تخفیف', 'student', 'discount'],
            'type' => 'student_offer',
            'category' => 'pricing',
            'intent' => 'discount_inquiry',
            'persona_weight' => ['student' => 15],
        ],
    ];

    /**
     * Check if Divi is active
     *
     * @return bool
     */
    public function is_divi_active(): bool
    {
        static $is_active = null;
        
        if ($is_active === null) {
            $theme = wp_get_theme();
            $is_active = $theme->get('Name') === 'Divi' || 
                        $theme->get('Template') === 'Divi' ||
                        defined('ET_BUILDER_VERSION');
        }
        
        return $is_active;
    }

    /**
     * Identify module from CSS class
     *
     * @param string $class_string CSS class string
     * @return array|null Module data or null if not identified
     */
    public function identify_module(string $class_string): ?array
    {
        foreach (self::MODULE_MAPPING as $class => $data) {
            if (strpos($class_string, $class) !== false) {
                return array_merge(['class' => $class], $data);
            }
        }

        return null;
    }

    /**
     * Detect special content patterns
     *
     * @param string $content Content to analyze
     * @param string $class_string CSS classes
     * @return array|null Pattern data or null if not detected
     */
    public function detect_content_pattern(string $content, string $class_string = ''): ?array
    {
        $content_lower = mb_strtolower($content, 'UTF-8');
        $class_lower = mb_strtolower($class_string, 'UTF-8');
        
        foreach (self::CONTENT_PATTERNS as $pattern_name => $pattern_data) {
            foreach ($pattern_data['keywords'] as $keyword) {
                $keyword_lower = mb_strtolower($keyword, 'UTF-8');
                
                if (strpos($content_lower, $keyword_lower) !== false ||
                    strpos($class_lower, $keyword_lower) !== false) {
                    return array_merge(
                        ['pattern' => $pattern_name],
                        $pattern_data
                    );
                }
            }
        }

        return null;
    }

    /**
     * Get persona weights for event
     *
     * @param string $element_class Element CSS class
     * @param array $element_data Element data
     * @return array Persona weights
     */
    public function get_persona_weights(string $element_class, array $element_data): array
    {
        $weights = [];

        // Check module mapping
        $module = $this->identify_module($element_class);
        if ($module && isset($module['persona_weight'])) {
            $weights = $module['persona_weight'];
        }

        // Check content patterns
        $content = $element_data['text'] ?? '';
        $pattern = $this->detect_content_pattern($content, $element_class);
        if ($pattern && isset($pattern['persona_weight'])) {
            // Merge weights, content patterns have higher priority
            foreach ($pattern['persona_weight'] as $persona => $weight) {
                $weights[$persona] = ($weights[$persona] ?? 0) + $weight;
            }
        }

        return $weights;
    }

    /**
     * Get module intent
     *
     * @param string $element_class Element CSS class
     * @param array $element_data Element data
     * @return string Module intent
     */
    public function get_module_intent(string $element_class, array $element_data): string
    {
        // Check content patterns first (higher priority)
        $content = $element_data['text'] ?? '';
        $pattern = $this->detect_content_pattern($content, $element_class);
        if ($pattern) {
            return $pattern['intent'];
        }

        // Check module mapping
        $module = $this->identify_module($element_class);
        if ($module) {
            return $module['intent'];
        }

        return 'unknown';
    }

    /**
     * Get module category
     *
     * @param string $element_class Element CSS class
     * @param array $element_data Element data
     * @return string Module category
     */
    public function get_module_category(string $element_class, array $element_data): string
    {
        // Check content patterns first
        $content = $element_data['text'] ?? '';
        $pattern = $this->detect_content_pattern($content, $element_class);
        if ($pattern) {
            return $pattern['category'];
        }

        // Check module mapping
        $module = $this->identify_module($element_class);
        if ($module) {
            return $module['category'];
        }

        return 'general';
    }

    /**
     * Enrich event data with Divi context
     *
     * @param string $event_type Event type
     * @param string $element_class Element CSS class
     * @param array $element_data Element data
     * @return array Enriched event data
     */
    public function enrich_event_data(
        string $event_type,
        string $element_class,
        array $element_data
    ): array {
        $enriched = [
            'original_event' => $event_type,
            'element_class' => $element_class,
            'module' => $this->identify_module($element_class),
            'content_pattern' => $this->detect_content_pattern(
                $element_data['text'] ?? '',
                $element_class
            ),
            'intent' => $this->get_module_intent($element_class, $element_data),
            'category' => $this->get_module_category($element_class, $element_data),
            'persona_weights' => $this->get_persona_weights($element_class, $element_data),
        ];

        return $enriched;
    }

    /**
     * Get all Divi modules data
     *
     * @return array All module mappings
     */
    public function get_all_module_mappings(): array
    {
        return self::MODULE_MAPPING;
    }

    /**
     * Get all content patterns
     *
     * @return array All content patterns
     */
    public function get_all_content_patterns(): array
    {
        return self::CONTENT_PATTERNS;
    }
}
