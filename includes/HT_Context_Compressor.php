<?php
/**
 * Context Compressor - Summarization Engine
 *
 * @package HomayeTabesh
 * @since PR7
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * موتور تلخیص (Context Compression)
 * 
 * Converts long conversations into token-efficient facts
 * for AI prompt enrichment without overloading context window
 * 
 * Example: 2500 tokens → 500 tokens (~80% reduction)
 */
class HT_Context_Compressor
{
    /**
     * Maximum tokens to allow in compressed context
     */
    private const MAX_TOKENS = 500;

    /**
     * Approximate token-to-character ratio for Persian text
     * Persian text typically uses ~4 characters per token
     */
    private const TOKEN_CHAR_RATIO = 4;

    /**
     * Compress chat messages into concise facts
     *
     * @param array $messages Array of chat messages
     * @return string Compressed summary
     */
    public static function compress_messages(array $messages): string
    {
        if (empty($messages)) {
            return '';
        }

        $facts = [];
        $user_queries = [];
        $ai_responses = [];

        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $content = $message['content'] ?? '';

            if ($role === 'user') {
                $user_queries[] = $content;
            } elseif ($role === 'assistant') {
                $ai_responses[] = $content;
            }
        }

        // Extract key facts from conversation
        $facts = self::extract_facts_from_conversation($user_queries, $ai_responses);

        // Build compressed summary
        $summary = implode('. ', $facts);

        // Truncate if too long
        $summary = self::truncate_to_token_limit($summary, self::MAX_TOKENS);

        return $summary;
    }

    /**
     * Extract factual information from conversation
     *
     * @param array $user_queries User messages
     * @param array $ai_responses AI messages
     * @return array Array of extracted facts
     */
    private static function extract_facts_from_conversation(array $user_queries, array $ai_responses): array
    {
        $facts = [];

        // Analyze user queries for key information
        foreach ($user_queries as $query) {
            // Extract numbers (potential prices, quantities)
            if (preg_match_all('/(\d+(?:,\d{3})*(?:\.\d+)?)\s*(?:تومان|ریال|عدد|نسخه)?/u', $query, $matches)) {
                foreach ($matches[0] as $match) {
                    $facts[] = "کاربر درخواست کرد: {$match}";
                }
            }

            // Extract product/service keywords
            $keywords = ['چاپ', 'کتاب', 'کاغذ', 'بالک', 'فاکتور', 'تیراژ', 'صحافی', 'جلد'];
            foreach ($keywords as $keyword) {
                if (mb_strpos($query, $keyword) !== false) {
                    $facts[] = "کاربر علاقه‌مند به {$keyword} است";
                }
            }
        }

        // De-duplicate facts
        $facts = array_unique($facts);

        // Limit number of facts
        if (count($facts) > 10) {
            $facts = array_slice($facts, 0, 10);
        }

        return $facts;
    }

    /**
     * Extract structured data from form snapshot
     *
     * @param array $form_snapshot Form field data
     * @return string Human-readable summary
     */
    public static function compress_form_data(array $form_snapshot): string
    {
        if (empty($form_snapshot)) {
            return '';
        }

        $parts = [];

        foreach ($form_snapshot as $field => $value) {
            if (empty($value)) {
                continue;
            }

            // Format field names to be more readable
            $readable_field = self::format_field_name($field);
            $parts[] = "{$readable_field}: {$value}";
        }

        return implode(', ', $parts);
    }

    /**
     * Format field name to be more readable
     *
     * @param string $field Field name
     * @return string Formatted field name
     */
    private static function format_field_name(string $field): string
    {
        // Common field name mappings
        $mappings = [
            'tirage' => 'تیراژ',
            'paper_type' => 'نوع کاغذ',
            'binding' => 'صحافی',
            'color' => 'رنگ',
            'pages' => 'تعداد صفحات',
            'size' => 'اندازه',
            'quantity' => 'تعداد',
            'price' => 'قیمت',
            'delivery' => 'زمان تحویل'
        ];

        return $mappings[$field] ?? $field;
    }

    /**
     * Truncate text to approximate token limit
     *
     * @param string $text Text to truncate
     * @param int $max_tokens Maximum tokens (approximate)
     * @return string Truncated text
     */
    private static function truncate_to_token_limit(string $text, int $max_tokens): string
    {
        // Use class constant for token-to-character ratio
        $max_chars = $max_tokens * self::TOKEN_CHAR_RATIO;

        if (mb_strlen($text) <= $max_chars) {
            return $text;
        }

        // Truncate and add ellipsis
        return mb_substr($text, 0, $max_chars - 3) . '...';
    }

    /**
     * Generate enriched prompt with compressed context
     *
     * @param string $user_message Current user message
     * @param array $context_data Context data from vault
     * @return string Enriched prompt
     */
    public static function enrich_prompt(string $user_message, array $context_data = []): string
    {
        $enriched_parts = [];

        // Add compressed session context
        if (!empty($context_data['session'])) {
            $session_summary = self::compress_form_data($context_data['session']);
            if ($session_summary) {
                $enriched_parts[] = "زمینه جلسه فعلی: {$session_summary}";
            }
        }

        // Add compressed chat history
        if (!empty($context_data['messages'])) {
            $chat_summary = self::compress_messages($context_data['messages']);
            if ($chat_summary) {
                $enriched_parts[] = "خلاصه گفتگوی قبلی: {$chat_summary}";
            }
        }

        // Add user interests
        if (!empty($context_data['interests'])) {
            $interest_text = implode(', ', array_column($context_data['interests'], 'category'));
            $enriched_parts[] = "علایق کاربر: {$interest_text}";
        }

        // Add current message
        $enriched_parts[] = "پیام فعلی: {$user_message}";

        return implode("\n\n", $enriched_parts);
    }

    /**
     * Extract key metrics from conversation for analytics
     *
     * @param array $messages Chat messages
     * @return array Metrics data
     */
    public static function extract_metrics(array $messages): array
    {
        $metrics = [
            'total_messages' => count($messages),
            'user_messages' => 0,
            'ai_messages' => 0,
            'avg_message_length' => 0,
            'keywords_mentioned' => []
        ];

        $total_length = 0;
        $keywords = ['چاپ', 'کتاب', 'تیراژ', 'قیمت', 'سفارش', 'فاکتور', 'بالک'];

        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $content = $message['content'] ?? '';
            
            if ($role === 'user') {
                $metrics['user_messages']++;
            } elseif ($role === 'assistant') {
                $metrics['ai_messages']++;
            }

            $total_length += mb_strlen($content);

            // Check for keywords
            foreach ($keywords as $keyword) {
                if (mb_strpos($content, $keyword) !== false) {
                    if (!in_array($keyword, $metrics['keywords_mentioned'])) {
                        $metrics['keywords_mentioned'][] = $keyword;
                    }
                }
            }
        }

        if (count($messages) > 0) {
            $metrics['avg_message_length'] = round($total_length / count($messages));
        }

        return $metrics;
    }
}
