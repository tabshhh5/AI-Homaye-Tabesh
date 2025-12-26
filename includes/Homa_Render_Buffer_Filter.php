<?php
/**
 * Render Buffer Filter - Output Buffering Translation System
 *
 * @package HomayeTabesh
 * @since PR14
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * فیلتر بافر رندر
 * 
 * این کلاس از output buffering استفاده می‌کند تا محتوای نهایی را
 * قبل از ارسال به مرورگر دریافت و ترجمه کند
 */
class Homa_Render_Buffer_Filter
{
    /**
     * Translation cache manager
     */
    private HT_Translation_Cache_Manager $cache_manager;

    /**
     * GeoLocation service
     */
    private HT_GeoLocation_Service $geo_service;

    /**
     * Is translation enabled for current request
     */
    private bool $translation_enabled = false;

    /**
     * Target language
     */
    private string $target_language = 'ar';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->cache_manager = new HT_Translation_Cache_Manager();
        $this->geo_service = new HT_GeoLocation_Service();

        $this->init();
    }

    /**
     * Initialize the filter
     * 
     * @return void
     */
    private function init(): void
    {
        // Check if user requested translation
        if (isset($_COOKIE['homa_translate_to'])) {
            $this->translation_enabled = true;
            $this->target_language = sanitize_text_field($_COOKIE['homa_translate_to']);
        }

        // Don't translate admin pages, AJAX requests, or cron jobs
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        // Don't start buffering during plugin initialization
        // Only start buffering in template_redirect hook to ensure WordPress is fully loaded
        if ($this->translation_enabled) {
            // Hook into template_redirect for safe output buffering
            add_action('template_redirect', [$this, 'start_buffer'], 1);
            add_action('shutdown', [$this, 'end_buffer'], 999);
        }
    }

    /**
     * Start output buffering
     * 
     * @return void
     */
    public function start_buffer(): void
    {
        // Double-check we're not in admin/ajax/cron context
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        // Only start buffering if headers haven't been sent
        if (!headers_sent()) {
            ob_start([$this, 'translate_buffer']);
        }
    }

    /**
     * End output buffering
     * 
     * @return void
     */
    public function end_buffer(): void
    {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    /**
     * Translate the buffered content
     * 
     * @param string $buffer
     * @return string
     */
    public function translate_buffer(string $buffer): string
    {
        // Don't translate if buffer is too small or empty
        if (strlen($buffer) < 100) {
            return $buffer;
        }

        // Don't translate JSON responses
        if ($this->is_json($buffer)) {
            return $buffer;
        }

        // Load HTML into DOMDocument
        $dom = new \DOMDocument('1.0', 'UTF-8');
        
        // Suppress errors for malformed HTML
        libxml_use_internal_errors(true);
        
        // Add UTF-8 meta tag to ensure proper encoding
        $html = mb_convert_encoding($buffer, 'HTML-ENTITIES', 'UTF-8');
        
        // Load full HTML document properly
        @$dom->loadHTML($html);
        
        libxml_clear_errors();

        // Translate text nodes
        $this->translate_dom_nodes($dom);

        // Get the modified HTML
        $translated_html = $dom->saveHTML();

        // Add RTL attributes for Arabic
        if ($this->target_language === 'ar') {
            $translated_html = $this->add_rtl_attributes($translated_html);
        }

        return $translated_html;
    }

    /**
     * Translate DOM text nodes recursively
     * 
     * @param \DOMNode $node
     * @return void
     */
    private function translate_dom_nodes(\DOMNode $node): void
    {
        // Skip script and style tags
        if ($node->nodeName === 'script' || $node->nodeName === 'style') {
            return;
        }

        // Skip nodes with data-no-translate attribute
        if ($node instanceof \DOMElement && $node->hasAttribute('data-no-translate')) {
            return;
        }

        // Translate text nodes
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = trim($node->nodeValue);
            
            if (!empty($text) && strlen($text) > 2 && !$this->is_code_or_data($text)) {
                $translated = $this->cache_manager->get_translation($text, $this->target_language);
                $node->nodeValue = $translated;
            }
        }

        // Recursively process child nodes
        if ($node->hasChildNodes()) {
            $children = [];
            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }
            
            foreach ($children as $child) {
                $this->translate_dom_nodes($child);
            }
        }

        // Translate certain attributes
        if ($node instanceof \DOMElement) {
            $this->translate_element_attributes($node);
        }
    }

    /**
     * Translate element attributes like title, alt, placeholder
     * 
     * @param \DOMElement $element
     * @return void
     */
    private function translate_element_attributes(\DOMElement $element): void
    {
        $attributes_to_translate = ['title', 'alt', 'placeholder', 'aria-label'];

        foreach ($attributes_to_translate as $attr) {
            if ($element->hasAttribute($attr)) {
                $value = $element->getAttribute($attr);
                
                if (!empty($value) && strlen($value) > 2) {
                    $translated = $this->cache_manager->get_translation($value, $this->target_language);
                    $element->setAttribute($attr, $translated);
                }
            }
        }
    }

    /**
     * Add RTL attributes for Arabic
     * 
     * @param string $html
     * @return string
     */
    private function add_rtl_attributes(string $html): string
    {
        // Use DOMDocument for safer attribute manipulation
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        
        @$dom->loadHTML($html);
        libxml_clear_errors();

        // Get html element and add dir attribute
        $html_elements = $dom->getElementsByTagName('html');
        if ($html_elements->length > 0) {
            $html_element = $html_elements->item(0);
            if ($html_element instanceof \DOMElement) {
                $html_element->setAttribute('dir', 'rtl');
            }
        }

        // Get body element and add class
        $body_elements = $dom->getElementsByTagName('body');
        if ($body_elements->length > 0) {
            $body_element = $body_elements->item(0);
            if ($body_element instanceof \DOMElement) {
                $existing_class = $body_element->getAttribute('class');
                $new_class = trim($existing_class . ' homa-rtl-arabic');
                $body_element->setAttribute('class', $new_class);
            }
        }

        return $dom->saveHTML();
    }

    /**
     * Check if content is JSON
     * 
     * @param string $string
     * @return bool
     */
    private function is_json(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Check if text is code or data (should not be translated)
     * 
     * @param string $text
     * @return bool
     */
    private function is_code_or_data(string $text): bool
    {
        // Check for common code patterns
        $code_patterns = [
            '/^\d+$/',                    // Only numbers
            '/^[0-9\.\,\-\+]+$/',        // Numbers with separators
            '/^[\{\[\(]/',                // Starts with bracket
            '/^\$|€|£|¥|₹/',             // Currency symbols
            '/^https?:\/\//',             // URLs
            '/^[a-zA-Z0-9_\-\.]+@/',     // Email addresses
            '/^[A-Z_]+$/',                // ALL CAPS (likely constants)
            '/function\s*\(/',            // JavaScript functions
            '/class\s*=/',                // HTML attributes
            '/<\?php/',                   // PHP code
        ];

        foreach ($code_patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if translation should be shown
     * 
     * @return bool
     */
    public function should_show_translation_popup(): bool
    {
        // Don't show if already decided
        if (isset($_COOKIE['homa_translation_decided'])) {
            return false;
        }

        // Don't show on admin pages
        if (is_admin()) {
            return false;
        }

        // Check if visitor is from Arabic country
        return $this->geo_service->is_arabic_visitor();
    }

    /**
     * Get detected country info
     * 
     * @return array
     */
    public function get_detected_country_info(): array
    {
        $country_code = $this->geo_service->detect_country();
        
        if ($country_code === false) {
            return [
                'detected' => false,
                'country_code' => '',
                'country_name_persian' => '',
                'country_name_arabic' => '',
            ];
        }

        return [
            'detected' => true,
            'country_code' => $country_code,
            'country_name_persian' => $this->geo_service->get_country_name_persian($country_code),
            'country_name_arabic' => $this->geo_service->get_country_name_arabic($country_code),
        ];
    }

    /**
     * Enable translation for current session
     * 
     * @param string $target_lang
     * @return void
     */
    public function enable_translation(string $target_lang = 'ar'): void
    {
        $this->translation_enabled = true;
        $this->target_language = $target_lang;
        
        // Set cookie for 30 days with security attributes
        $secure = is_ssl();
        $options = [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'secure' => $secure,
            'httponly' => false, // Need to be accessible by JS
            'samesite' => 'Lax'
        ];
        
        setcookie('homa_translate_to', $target_lang, $options);
        setcookie('homa_translation_decided', '1', $options);
    }

    /**
     * Disable translation for current session
     * 
     * @return void
     */
    public function disable_translation(): void
    {
        $this->translation_enabled = false;
        
        // Clear translation cookie
        $secure = is_ssl();
        $options = [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Lax'
        ];
        
        setcookie('homa_translate_to', '', $options);
        
        // Set decision cookie
        $options['expires'] = time() + (30 * 24 * 60 * 60);
        setcookie('homa_translation_decided', '1', $options);
    }

    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function get_cache_stats(): array
    {
        return $this->cache_manager->get_cache_stats();
    }
}
