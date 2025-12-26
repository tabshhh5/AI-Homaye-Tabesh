<?php
/**
 * Translation Cache Manager - Smart Caching for Translated Content
 *
 * @package HomayeTabesh
 * @since PR14
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * مدیر کش ترجمه
 * 
 * این کلاس ترجمه‌های انجام شده را در دیتابیس ذخیره می‌کند
 * تا از مصرف دوباره توکن جلوگیری شود
 */
class HT_Translation_Cache_Manager
{
    /**
     * Maximum length for original text storage
     */
    private const MAX_ORIGINAL_TEXT_LENGTH = 1000;

    /**
     * Estimated tokens per translation (for savings calculation)
     */
    private const ESTIMATED_TOKENS_PER_TRANSLATION = 50;

    /**
     * Gemini client instance
     */
    private HT_Gemini_Client $gemini;

    /**
     * Cache hit statistics
     */
    private int $cache_hits = 0;
    private int $cache_misses = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->gemini = new HT_Gemini_Client();
    }

    /**
     * Get translation from cache or generate new one
     * 
     * @param string $text Original text
     * @param string $target_lang Target language (default: ar)
     * @return string Translated text
     */
    public function get_translation(string $text, string $target_lang = 'ar'): string
    {
        // Trim and check if empty
        $text = trim($text);
        
        if (empty($text) || strlen($text) < 3) {
            return $text;
        }

        // Generate hash for caching
        $text_hash = $this->generate_hash($text, $target_lang);

        // Try to get from cache
        $cached = $this->get_from_cache($text_hash, $target_lang);
        
        if ($cached !== false) {
            $this->cache_hits++;
            return $cached;
        }

        // Generate new translation
        $this->cache_misses++;
        $translated = $this->translate_with_gemini($text, $target_lang);

        // Save to cache
        if (!empty($translated)) {
            $this->save_to_cache($text_hash, $text, $translated, $target_lang);
        }

        return $translated;
    }

    /**
     * Generate hash for text
     * 
     * @param string $text
     * @param string $target_lang
     * @return string
     */
    private function generate_hash(string $text, string $target_lang): string
    {
        // Using sha256 for better collision resistance
        return hash('sha256', $text . '|' . $target_lang);
    }

    /**
     * Get translation from cache
     * 
     * @param string $text_hash
     * @param string $target_lang
     * @return string|false
     */
    private function get_from_cache(string $text_hash, string $target_lang): string|false
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'homa_translations';

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT translated_text FROM $table_name 
            WHERE text_hash = %s AND lang = %s AND is_valid = 1
            LIMIT 1",
            $text_hash,
            $target_lang
        ));

        // Update usage statistics if found
        if ($result !== null) {
            $this->update_usage_stats($text_hash, $target_lang);
        }

        return $result !== null ? $result : false;
    }

    /**
     * Save translation to cache
     * 
     * @param string $text_hash
     * @param string $original_text
     * @param string $translated_text
     * @param string $target_lang
     * @return void
     */
    private function save_to_cache(
        string $text_hash,
        string $original_text,
        string $translated_text,
        string $target_lang
    ): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'homa_translations';

        // Log if text will be truncated
        if (mb_strlen($original_text) > self::MAX_ORIGINAL_TEXT_LENGTH) {
            error_log(sprintf(
                'Homa Translation: Text truncated from %d to %d characters',
                mb_strlen($original_text),
                self::MAX_ORIGINAL_TEXT_LENGTH
            ));
        }

        $wpdb->insert(
            $table_name,
            [
                'text_hash' => $text_hash,
                'original_text' => mb_substr($original_text, 0, self::MAX_ORIGINAL_TEXT_LENGTH),
                'translated_text' => $translated_text,
                'lang' => $target_lang,
                'is_valid' => 1,
                'created_at' => current_time('mysql'),
                'last_used' => current_time('mysql'),
                'use_count' => 1,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d']
        );
    }

    /**
     * Translate text using Gemini AI
     * 
     * @param string $text
     * @param string $target_lang
     * @return string
     */
    private function translate_with_gemini(string $text, string $target_lang): string
    {
        $lang_name = $target_lang === 'ar' ? 'Arabic' : 'English';
        
        $prompt = "Translate the following Persian/Farsi text to {$lang_name}. 
        Maintain the same tone and style. 
        If there are HTML tags, preserve them exactly.
        Only return the translated text, nothing else.
        
        Text to translate:
        {$text}";

        try {
            $response = $this->gemini->generate_content($prompt, [], []);
            
            if (isset($response['text'])) {
                return trim($response['text']);
            }
        } catch (\Exception $e) {
            // Log error but don't break the site
            error_log('Homa Translation Error: ' . $e->getMessage());
        }

        // Return original text if translation fails
        return $text;
    }

    /**
     * Batch translate multiple texts
     * 
     * @param array $texts Array of texts to translate
     * @param string $target_lang
     * @return array Translated texts
     */
    public function batch_translate(array $texts, string $target_lang = 'ar'): array
    {
        $results = [];
        
        foreach ($texts as $key => $text) {
            $results[$key] = $this->get_translation($text, $target_lang);
        }

        return $results;
    }

    /**
     * Invalidate cache for specific content
     * 
     * @param string $text
     * @param string $target_lang
     * @return bool
     */
    public function invalidate_cache(string $text, string $target_lang = 'ar'): bool
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'homa_translations';
        
        $text_hash = $this->generate_hash($text, $target_lang);

        $result = $wpdb->update(
            $table_name,
            ['is_valid' => 0],
            ['text_hash' => $text_hash, 'lang' => $target_lang],
            ['%d'],
            ['%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Invalidate all cache for a language
     * 
     * @param string $target_lang
     * @return int Number of invalidated entries
     */
    public function invalidate_all_cache(string $target_lang = 'ar'): int
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'homa_translations';

        $result = $wpdb->update(
            $table_name,
            ['is_valid' => 0],
            ['lang' => $target_lang],
            ['%d'],
            ['%s']
        );

        return $result !== false ? $result : 0;
    }

    /**
     * Update usage statistics for cached translation
     * 
     * @param string $text_hash
     * @param string $target_lang
     * @return void
     */
    private function update_usage_stats(string $text_hash, string $target_lang): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'homa_translations';

        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
            SET use_count = use_count + 1, last_used = %s
            WHERE text_hash = %s AND lang = %s",
            current_time('mysql'),
            $text_hash,
            $target_lang
        ));
    }

    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function get_cache_stats(): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'homa_translations';

        $total_entries = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_valid = 1");
        $total_uses = $wpdb->get_var("SELECT SUM(use_count) FROM $table_name WHERE is_valid = 1");
        $arabic_entries = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE lang = 'ar' AND is_valid = 1");

        $cache_hit_rate = 0;
        if ($this->cache_hits + $this->cache_misses > 0) {
            $cache_hit_rate = ($this->cache_hits / ($this->cache_hits + $this->cache_misses)) * 100;
        }

        return [
            'total_entries' => (int) $total_entries,
            'total_uses' => (int) $total_uses,
            'arabic_entries' => (int) $arabic_entries,
            'cache_hits' => $this->cache_hits,
            'cache_misses' => $this->cache_misses,
            'cache_hit_rate' => round($cache_hit_rate, 2),
            'estimated_token_savings' => ((int) $total_uses - (int) $total_entries) * self::ESTIMATED_TOKENS_PER_TRANSLATION,
        ];
    }

    /**
     * Clean old unused cache entries
     * 
     * @param int $days Number of days of inactivity
     * @return int Number of deleted entries
     */
    public function cleanup_old_cache(int $days = 90): int
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'homa_translations';

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name 
            WHERE last_used < DATE_SUB(NOW(), INTERVAL %d DAY) 
            AND use_count < 2",
            $days
        ));

        return $result !== false ? $result : 0;
    }

    /**
     * Get most used translations
     * 
     * @param int $limit
     * @return array
     */
    public function get_most_used_translations(int $limit = 10): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'homa_translations';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT original_text, translated_text, use_count, lang
            FROM $table_name
            WHERE is_valid = 1
            ORDER BY use_count DESC
            LIMIT %d",
            $limit
        ), ARRAY_A);

        return $results ?: [];
    }
}
