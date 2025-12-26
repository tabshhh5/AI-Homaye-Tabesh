<?php
/**
 * Smart Lead Scoring Algorithm
 *
 * @package HomayeTabesh
 * @since PR11
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * الگوریتم امتیازدهی هوشمند لیدها
 * 
 * این کلاس امتیاز کیفیت کاربر را بر اساس پارامترهای مختلف محاسبه می‌کند
 */
class HT_Lead_Scoring_Algorithm
{
    /**
     * محاسبه امتیاز لید بر اساس پارامترهای مختلف
     * 
     * @param array $params پارامترهای امتیازدهی
     * @return int امتیاز بین 0 تا 100
     */
    public static function calculate_score(array $params): int
    {
        $score = 0;

        // امتیاز بر اساس منبع ورودی (Source Referral)
        $score += self::score_source_referral($params['source_referral'] ?? 'organic');

        // امتیاز بر اساس تیراژ (Volume)
        $score += self::score_volume($params['volume'] ?? 0);

        // امتیاز بر اساس نوع محصول (Product Type)
        $score += self::score_product_type($params['product_type'] ?? '');

        // امتیاز بر اساس تعامل (Engagement)
        $score += self::score_engagement($params['engagement'] ?? []);

        // امتیاز بر اساس کامل بودن اطلاعات (Completeness)
        $score += self::score_completeness($params);

        // امتیاز بر اساس سرعت تصمیم‌گیری (Decision Speed)
        $score += self::score_decision_speed($params['decision_time'] ?? 0);

        // محدود کردن امتیاز به بازه 0-100
        return min(100, max(0, $score));
    }

    /**
     * امتیازدهی بر اساس منبع ورودی
     */
    private static function score_source_referral(string $source): int
    {
        $scores = [
            'instagram' => 15,  // شبکه اجتماعی - Hot Lead
            'telegram' => 15,   // شبکه اجتماعی - Hot Lead
            'google_ads' => 12, // تبلیغات - Warm Lead
            'facebook' => 10,   // شبکه اجتماعی - Warm Lead
            'direct' => 8,      // مستقیم - متوسط
            'organic' => 5,     // ارگانیک - Cold Lead
            'referral' => 18,   // معرفی - Very Hot Lead
        ];

        return $scores[$source] ?? 5;
    }

    /**
     * امتیازدهی بر اساس تیراژ سفارش
     */
    private static function score_volume(int $volume): int
    {
        if ($volume >= 10000) {
            return 25; // سفارش بزرگ
        } elseif ($volume >= 5000) {
            return 20; // سفارش متوسط-بزرگ
        } elseif ($volume >= 1000) {
            return 15; // سفارش متوسط
        } elseif ($volume >= 500) {
            return 10; // سفارش کوچک-متوسط
        } elseif ($volume > 0) {
            return 5;  // سفارش کوچک
        }

        return 0; // بدون تیراژ مشخص
    }

    /**
     * امتیازدهی بر اساس نوع محصول
     */
    private static function score_product_type(string $product_type): int
    {
        $scores = [
            'gold_foil' => 15,      // طلاکوب - محصول گران‌قیمت
            'uv_coating' => 12,     // UV - محصول پریمیوم
            'embossing' => 12,      // برجسته - محصول پریمیوم
            'luxury_paper' => 10,   // کاغذ لوکس
            'spot_uv' => 10,        // UV لکه‌ای
            'lamination' => 8,      // سلفون
            'standard_print' => 5,  // چاپ استاندارد
        ];

        return $scores[$product_type] ?? 5;
    }

    /**
     * امتیازدهی بر اساس میزان تعامل
     */
    private static function score_engagement(array $engagement): int
    {
        $score = 0;

        // تعداد پیام‌های چت
        $message_count = $engagement['message_count'] ?? 0;
        if ($message_count >= 10) {
            $score += 10;
        } elseif ($message_count >= 5) {
            $score += 7;
        } elseif ($message_count >= 3) {
            $score += 5;
        }

        // مشاهده محصولات
        $viewed_products = $engagement['viewed_products'] ?? 0;
        if ($viewed_products >= 5) {
            $score += 8;
        } elseif ($viewed_products >= 3) {
            $score += 5;
        } elseif ($viewed_products >= 1) {
            $score += 3;
        }

        // مشاهده فاکتورها
        $viewed_invoices = $engagement['viewed_invoices'] ?? 0;
        if ($viewed_invoices >= 3) {
            $score += 10; // علاقه زیاد به قیمت‌گذاری
        } elseif ($viewed_invoices >= 1) {
            $score += 5;
        }

        return $score;
    }

    /**
     * امتیازدهی بر اساس کامل بودن اطلاعات
     */
    private static function score_completeness(array $params): int
    {
        $score = 0;

        // دارای شماره تماس
        if (!empty($params['contact_info'])) {
            $score += 10;
        }

        // دارای نام
        if (!empty($params['contact_name'])) {
            $score += 5;
        }

        // دارای مشخصات فنی سفارش
        if (!empty($params['requirements_summary'])) {
            $score += 8;
        }

        // دارای بودجه مشخص
        if (!empty($params['budget'])) {
            $score += 7;
        }

        return $score;
    }

    /**
     * امتیازدهی بر اساس سرعت تصمیم‌گیری
     * 
     * @param int $decision_time زمان از شروع چت تا درخواست اطلاعات تماس (ثانیه)
     */
    private static function score_decision_speed(int $decision_time): int
    {
        if ($decision_time > 0 && $decision_time <= 300) {
            return 10; // کمتر از 5 دقیقه - Very Hot
        } elseif ($decision_time <= 600) {
            return 7;  // کمتر از 10 دقیقه - Hot
        } elseif ($decision_time <= 1800) {
            return 5;  // کمتر از 30 دقیقه - Warm
        }

        return 0; // بیش از 30 دقیقه یا نامشخص
    }

    /**
     * تعیین وضعیت لید بر اساس امتیاز
     */
    public static function get_lead_status(int $score): string
    {
        if ($score >= 80) {
            return 'hot';      // لید داغ - اولویت فوری
        } elseif ($score >= 60) {
            return 'warm';     // لید گرم - اولویت بالا
        } elseif ($score >= 40) {
            return 'medium';   // لید متوسط - اولویت متوسط
        } else {
            return 'cold';     // لید سرد - اولویت پایین
        }
    }

    /**
     * بررسی نیاز به اطلاع‌رسانی فوری
     */
    public static function needs_immediate_notification(int $score): bool
    {
        $threshold = (int) get_option('ht_lead_hot_score_threshold', 70);
        return $score >= $threshold;
    }
}
