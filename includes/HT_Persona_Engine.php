<?php
/**
 * Persona Engine - User Behavior Analysis
 *
 * @package HomayeTabesh
 * @since PR7
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * موتور تحلیل پرسونا (Persona Engine)
 * 
 * Analyzes user behavior to identify persona:
 * - نویسنده (Author): Interested in book printing
 * - ناشر (Publisher): High volume, business client
 * - گرافیست (Designer): Interested in design quality
 * - مشتری وفادار (Loyal Customer): Repeat visitor
 * - استعلامگیرنده گذرا (Casual Browser): Price shopping
 */
class HT_Persona_Engine
{
    /**
     * Persona type constants
     */
    public const PERSONA_AUTHOR = 'author';
    public const PERSONA_PUBLISHER = 'publisher';
    public const PERSONA_DESIGNER = 'designer';
    public const PERSONA_LOYAL = 'loyal_customer';
    public const PERSONA_CASUAL = 'casual_browser';
    public const PERSONA_PRICE_SENSITIVE = 'price_sensitive';

    /**
     * Minimum confidence threshold for persona detection
     */
    private const MIN_CONFIDENCE_THRESHOLD = 10;

    /**
     * Analyze user and determine primary persona
     *
     * @return array Persona data with type and confidence
     */
    public static function analyze_user_persona(): array
    {
        $interests = HT_Vault_Manager::get_user_interests(20);
        $session = HT_Vault_Manager::get_session_snapshot();
        $vault_data = HT_Vault_Manager::get_all();

        $scores = [
            self::PERSONA_AUTHOR => 0,
            self::PERSONA_PUBLISHER => 0,
            self::PERSONA_DESIGNER => 0,
            self::PERSONA_LOYAL => 0,
            self::PERSONA_CASUAL => 0,
            self::PERSONA_PRICE_SENSITIVE => 0
        ];

        // Analyze interests
        foreach ($interests as $interest) {
            $category = $interest['category'];
            $score = $interest['score'];

            // Author indicators
            if (strpos($category, 'book') !== false || strpos($category, 'novel') !== false) {
                $scores[self::PERSONA_AUTHOR] += $score * 2;
            }

            // Publisher indicators
            if ($score > 10) { // High engagement
                $scores[self::PERSONA_PUBLISHER] += $score;
            }

            // Designer indicators
            if (strpos($category, 'design') !== false || strpos($category, 'cover') !== false) {
                $scores[self::PERSONA_DESIGNER] += $score * 2;
            }

            // Loyal customer (multiple interests)
            if (count($interests) > 5) {
                $scores[self::PERSONA_LOYAL] += 5;
            }
        }

        // Analyze session data
        if ($session) {
            $form_data = $session['form_snapshot'] ?? [];

            // Price sensitivity
            if (isset($form_data['budget']) || isset($form_data['discount'])) {
                $scores[self::PERSONA_PRICE_SENSITIVE] += 10;
            }

            // Check for high-value indicators (Publisher)
            if (isset($form_data['tirage']) && (int)$form_data['tirage'] > 1000) {
                $scores[self::PERSONA_PUBLISHER] += 15;
            }
        }

        // Analyze vault data
        if (!empty($vault_data)) {
            // Multiple interactions = not casual
            if (count($vault_data) > 5) {
                $scores[self::PERSONA_CASUAL] -= 10;
                $scores[self::PERSONA_LOYAL] += 10;
            } else {
                $scores[self::PERSONA_CASUAL] += 5;
            }
        }

        // Get top persona
        arsort($scores);
        $primary_persona = array_key_first($scores);
        $confidence = $scores[$primary_persona];

        return [
            'persona' => $primary_persona,
            'confidence' => $confidence,
            'all_scores' => $scores,
            'label' => self::get_persona_label($primary_persona),
            'description' => self::get_persona_description($primary_persona)
        ];
    }

    /**
     * Get Persian label for persona
     *
     * @param string $persona Persona type
     * @return string Persian label
     */
    public static function get_persona_label(string $persona): string
    {
        $labels = [
            self::PERSONA_AUTHOR => 'نویسنده',
            self::PERSONA_PUBLISHER => 'ناشر',
            self::PERSONA_DESIGNER => 'گرافیست',
            self::PERSONA_LOYAL => 'مشتری وفادار',
            self::PERSONA_CASUAL => 'استعلامگیرنده گذرا',
            self::PERSONA_PRICE_SENSITIVE => 'حساس به قیمت'
        ];

        return $labels[$persona] ?? 'ناشناخته';
    }

    /**
     * Get description for persona
     *
     * @param string $persona Persona type
     * @return string Description
     */
    public static function get_persona_description(string $persona): string
    {
        $descriptions = [
            self::PERSONA_AUTHOR => 'نویسنده‌ای که به دنبال چاپ کتاب خود است، معمولاً با تیراژ پایین',
            self::PERSONA_PUBLISHER => 'ناشر یا مشتری تجاری با تیراژ بالا و نیاز به خدمات حرفه‌ای',
            self::PERSONA_DESIGNER => 'گرافیست یا طراح که به کیفیت چاپ و جزئیات بصری اهمیت می‌دهد',
            self::PERSONA_LOYAL => 'مشتری وفاداری که قبلاً با ما کار کرده و به سایت برمی‌گردد',
            self::PERSONA_CASUAL => 'بازدیدکننده‌ای که در حال مقایسه قیمت و بررسی گزینه‌ها است',
            self::PERSONA_PRICE_SENSITIVE => 'مشتری‌ای که قیمت برای او اهمیت بالایی دارد'
        ];

        return $descriptions[$persona] ?? '';
    }

    /**
     * Get recommended strategy for persona
     *
     * @param string $persona Persona type
     * @return array Strategy recommendations
     */
    public static function get_persona_strategy(string $persona): array
    {
        $strategies = [
            self::PERSONA_AUTHOR => [
                'tone' => 'friendly and supportive',
                'focus' => 'quality and personal attention',
                'discount' => 'offer first-time author discount',
                'upsell' => 'professional editing and design services'
            ],
            self::PERSONA_PUBLISHER => [
                'tone' => 'professional and efficient',
                'focus' => 'volume pricing and delivery speed',
                'discount' => 'bulk discount',
                'upsell' => 'dedicated account manager'
            ],
            self::PERSONA_DESIGNER => [
                'tone' => 'technical and detailed',
                'focus' => 'print quality and color accuracy',
                'discount' => 'portfolio discount',
                'upsell' => 'premium paper and finishing options'
            ],
            self::PERSONA_LOYAL => [
                'tone' => 'warm and appreciative',
                'focus' => 'loyalty rewards',
                'discount' => 'returning customer discount',
                'upsell' => 'new services and products'
            ],
            self::PERSONA_CASUAL => [
                'tone' => 'informative and helpful',
                'focus' => 'competitive pricing',
                'discount' => 'limited-time offer',
                'upsell' => 'quality benefits over cheaper alternatives'
            ],
            self::PERSONA_PRICE_SENSITIVE => [
                'tone' => 'value-focused',
                'focus' => 'cost savings and discounts',
                'discount' => 'price match or special offer',
                'upsell' => 'bundle deals for better value'
            ]
        ];

        return $strategies[$persona] ?? $strategies[self::PERSONA_CASUAL];
    }

    /**
     * Get persona-aware prompt prefix for AI
     *
     * @return string Prompt prefix with persona context
     */
    public static function get_persona_prompt_prefix(): string
    {
        $persona_data = self::analyze_user_persona();
        
        if ($persona_data['confidence'] < self::MIN_CONFIDENCE_THRESHOLD) {
            return ''; // Not enough data
        }

        $persona = $persona_data['persona'];
        $label = $persona_data['label'];
        $description = $persona_data['description'];
        $strategy = self::get_persona_strategy($persona);

        $prefix = "توجه: تحلیل رفتار نشان می‌دهد که کاربر احتمالاً یک {$label} است ({$description}). ";
        $prefix .= "استراتژی پیشنهادی: لحن {$strategy['tone']}، تمرکز بر {$strategy['focus']}. ";
        
        if (!empty($strategy['discount'])) {
            $prefix .= "پیشنهاد تخفیف: {$strategy['discount']}. ";
        }

        return $prefix;
    }

    /**
     * Check if user came from Torob (price comparison site)
     *
     * @return bool True if from Torob
     */
    public static function is_from_torob(): bool
    {
        $interests = HT_Vault_Manager::get_user_interests();
        
        foreach ($interests as $interest) {
            if ($interest['source'] === 'torob') {
                return true;
            }
        }

        // Check referrer
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        if (strpos($referrer, 'torob.com') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Update persona score manually (for explicit signals)
     *
     * @param string $persona Persona type
     * @param int $score Score to add
     * @return bool Success status
     */
    public static function update_persona_score(string $persona, int $score): bool
    {
        global $wpdb;
        
        $session_token = HT_Vault_Manager::get_session_token();
        $table_name = $wpdb->prefix . 'homaye_persona_scores';

        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, score FROM $table_name WHERE user_identifier = %s AND persona_type = %s",
            $session_token,
            $persona
        ));

        if ($existing) {
            // Update existing score
            $new_score = $existing->score + $score;
            $result = $wpdb->update(
                $table_name,
                [
                    'score' => $new_score,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $existing->id],
                ['%d', '%s'],
                ['%d']
            );
        } else {
            // Insert new score
            $result = $wpdb->insert(
                $table_name,
                [
                    'user_identifier' => $session_token,
                    'persona_type' => $persona,
                    'score' => $score,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%s', '%s', '%d', '%s', '%s']
            );
        }

        return $result !== false;
    }

    /**
     * Generate "Who am I?" response based on persona
     *
     * @return string Personalized response
     */
    public static function generate_who_am_i_response(): string
    {
        $persona_data = self::analyze_user_persona();
        $interests = HT_Vault_Manager::get_user_interests(3);
        $session = HT_Vault_Manager::get_session_snapshot();

        if ($persona_data['confidence'] < self::MIN_CONFIDENCE_THRESHOLD) {
            return 'شما تازه با ما آشنا شده‌اید. هنوز اطلاعات کافی برای شناخت شما ندارم، اما خوشحال می‌شوم بیشتر با شما آشنا شوم!';
        }

        $label = $persona_data['label'];
        $response = "بر اساس تحلیل رفتار شما، به نظر می‌رسد که شما یک {$label} هستید. ";

        // Add interests
        if (!empty($interests)) {
            $categories = array_column($interests, 'category');
            $response .= "شما علاقه‌مند به " . implode('، ', $categories) . " هستید. ";
        }

        // Add session info
        if ($session && !empty($session['form_snapshot'])) {
            $response .= "در این جلسه، شما در حال بررسی محصولات ما بوده‌اید. ";
        }

        $response .= "چه کمکی می‌توانم به شما بکنم؟";

        return $response;
    }
}
