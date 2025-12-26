<?php
/**
 * Retention Engine - Customer Retention & Re-engagement System
 *
 * @package HomayeTabesh
 * @since PR12
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * موتور بازگشت مشتری (Retention Engine)
 * 
 * سیستم هوشمند برای شناسایی مشتریان قدیمی و ارسال پیام‌های بازگشت
 * این کلاس مشتریانی را که مدتی خرید نکرده‌اند شناسایی کرده و پیام می‌فرستد.
 */
class HT_Retention_Engine
{
    /**
     * بازه زمانی شناسایی مشتریان غیرفعال (روز)
     */
    private const INACTIVE_DAYS = 30;

    /**
     * حداکثر تعداد پیامک در هر اجرای Cron
     */
    private const MAX_SMS_PER_RUN = 50;

    /**
     * Schedule feedback SMS after order completion
     * 
     * @param int $order_id شناسه سفارش
     * @return void
     */
    public function schedule_feedback_sms(int $order_id): void
    {
        // زمان‌بندی ارسال پیامک نظرسنجی 48 ساعت بعد
        if (!wp_next_scheduled('homa_send_feedback_sms', [$order_id])) {
            wp_schedule_single_event(
                time() + (48 * HOUR_IN_SECONDS),
                'homa_send_feedback_sms',
                [$order_id]
            );
        }

        error_log("Homa Retention: Scheduled feedback SMS for order #{$order_id}");
    }

    /**
     * Send feedback SMS to customer
     * 
     * @param int $order_id شناسه سفارش
     * @return bool
     */
    public function send_feedback_sms(int $order_id): bool
    {
        if (!class_exists('WooCommerce')) {
            return false;
        }

        $order = wc_get_order($order_id);
        
        if (!$order) {
            error_log("Homa Retention: Order #{$order_id} not found for feedback SMS");
            return false;
        }

        $phone = $order->get_billing_phone();
        $customer_name = $order->get_billing_first_name();

        if (empty($phone)) {
            error_log("Homa Retention: No phone number for order #{$order_id}");
            return false;
        }

        // نرمال‌سازی شماره
        $phone = Homa_SMS_Provider::normalize_phone($phone);

        // ارسال پیامک نظرسنجی
        $sms_provider = new Homa_SMS_Provider();
        $pattern_code = get_option('ht_melipayamak_feedback_pattern', '');

        if (!empty($pattern_code)) {
            $data = [
                'customer-name' => $customer_name,
                'order-id' => $order_id,
            ];

            $result = $sms_provider->send_pattern($phone, $pattern_code, $data);
        } else {
            // Fallback به پیامک ساده
            $message = "سلام {$customer_name} عزیز،\n" .
                       "از خریدت ممنونیم! نظرت درباره سفارش #{$order_id} چیه؟\n" .
                       "خوشحال میشیم بازخوردت رو بشنویم.";
            
            $result = $sms_provider->send_simple_sms($phone, $message);
        }

        if ($result) {
            // ثبت لاگ
            error_log("Homa Retention: Feedback SMS sent for order #{$order_id}");
            
            // ذخیره متادیتا
            $order->update_meta_data('_homa_feedback_sms_sent', current_time('mysql'));
            $order->save();
        }

        return (bool) $result;
    }

    /**
     * Find inactive customers for retention campaign
     * 
     * @param int $days تعداد روز عدم فعالیت
     * @return array لیست مشتریان غیرفعال
     */
    public function find_inactive_customers(int $days = self::INACTIVE_DAYS): array
    {
        if (!class_exists('WooCommerce')) {
            return [];
        }

        global $wpdb;

        // جستجوی مشتریانی که آخرین سفارششان بیش از X روز پیش بوده
        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $query = "
            SELECT 
                p.ID as order_id,
                pm1.meta_value as customer_phone,
                pm2.meta_value as customer_name,
                p.post_date as last_order_date,
                p.post_modified as last_modified
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_billing_phone'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_billing_first_name'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed')
            AND p.post_date <= %s
            AND p.ID IN (
                SELECT MAX(ID) 
                FROM {$wpdb->posts} 
                WHERE post_type = 'shop_order' 
                GROUP BY (
                    SELECT meta_value 
                    FROM {$wpdb->postmeta} 
                    WHERE post_id = {$wpdb->posts}.ID 
                    AND meta_key = '_billing_phone' 
                    LIMIT 1
                )
            )
            AND NOT EXISTS (
                SELECT 1 
                FROM {$wpdb->postmeta} pm3 
                WHERE pm3.post_id = p.ID 
                AND pm3.meta_key = '_homa_retention_sms_sent'
            )
            LIMIT %d
        ";

        $results = $wpdb->get_results(
            $wpdb->prepare($query, $date_threshold, self::MAX_SMS_PER_RUN),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Send retention SMS to inactive customers
     * 
     * @return array نتیجه ارسال
     */
    public function send_retention_campaign(): array
    {
        $inactive_customers = $this->find_inactive_customers();

        if (empty($inactive_customers)) {
            return [
                'success' => true,
                'sent' => 0,
                'message' => 'هیچ مشتری غیرفعالی پیدا نشد.',
            ];
        }

        $sms_provider = new Homa_SMS_Provider();
        $pattern_code = get_option('ht_melipayamak_retention_pattern', '');
        
        $sent_count = 0;
        $failed_count = 0;

        foreach ($inactive_customers as $customer) {
            $phone = Homa_SMS_Provider::normalize_phone($customer['customer_phone']);
            $name = $customer['customer_name'];
            $order_id = $customer['order_id'];

            if (empty($phone) || !Homa_SMS_Provider::validate_iranian_phone($phone)) {
                $failed_count++;
                continue;
            }

            // ارسال پیامک بازگشت
            if (!empty($pattern_code)) {
                $data = [
                    'customer-name' => $name,
                    'discount-code' => $this->generate_retention_discount_code($phone),
                ];

                $result = $sms_provider->send_pattern($phone, $pattern_code, $data);
            } else {
                // Fallback: پیامک ساده
                $message = "سلام {$name} عزیز،\n" .
                           "دلمون برات تنگ شده! 🌟\n" .
                           "برای خرید بعدیت یک تخفیف ویژه داریم.";
                
                $result = $sms_provider->send_simple_sms($phone, $message);
            }

            if ($result) {
                $sent_count++;
                
                // ثبت متادیتا برای جلوگیری از ارسال مجدد
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->update_meta_data('_homa_retention_sms_sent', current_time('mysql'));
                    $order->save();
                }

                error_log("Homa Retention: SMS sent to {$phone} (Order #{$order_id})");
            } else {
                $failed_count++;
            }

            // تاخیر کوچک برای جلوگیری از فشار به API
            usleep(500000); // 0.5 ثانیه
        }

        return [
            'success' => true,
            'sent' => $sent_count,
            'failed' => $failed_count,
            'total' => count($inactive_customers),
            'message' => "کمپین بازگشت مشتری: {$sent_count} پیامک ارسال شد.",
        ];
    }

    /**
     * Generate unique discount code for retention campaign
     * 
     * @param string $phone شماره تلفن
     * @return string کد تخفیف
     */
    private function generate_retention_discount_code(string $phone): string
    {
        // تولید کد تخفیف یکتا
        $unique_hash = substr(md5($phone . time()), 0, 6);
        return 'COMEBACK' . strtoupper($unique_hash);
    }

    /**
     * Get retention analytics
     * 
     * @return array آمار بازگشت مشتری
     */
    public function get_retention_analytics(): array
    {
        if (!class_exists('WooCommerce')) {
            return [
                'success' => false,
                'message' => 'ووکامرس فعال نیست.',
            ];
        }

        global $wpdb;

        // تعداد مشتریان غیرفعال
        $inactive_count = count($this->find_inactive_customers());

        // تعداد کل پیامک‌های بازگشت ارسال شده
        $sent_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_homa_retention_sms_sent'
        ");

        // تعداد پیامک‌های نظرسنجی ارسال شده
        $feedback_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_homa_feedback_sms_sent'
        ");

        // محاسبه نرخ بازگشت (مشتریانی که بعد از پیامک بازگشت کرده‌اند)
        // این محاسبه ساده است و می‌تواند پیچیده‌تر شود
        $return_rate = $sent_count > 0 ? round(($sent_count / ($sent_count + $inactive_count)) * 100, 2) : 0;

        return [
            'success' => true,
            'inactive_customers' => $inactive_count,
            'retention_sms_sent' => (int) $sent_count,
            'feedback_sms_sent' => (int) $feedback_count,
            'estimated_return_rate' => $return_rate . '%',
            'last_campaign_run' => get_option('ht_last_retention_campaign_run', 'هرگز'),
        ];
    }

    /**
     * Schedule retention campaign cron job
     * 
     * @return void
     */
    public static function schedule_retention_cron(): void
    {
        if (!wp_next_scheduled('homa_run_retention_campaign')) {
            wp_schedule_event(time(), 'daily', 'homa_run_retention_campaign');
        }
    }

    /**
     * Run retention campaign (Cron callback)
     * 
     * @return void
     */
    public static function run_retention_campaign_cron(): void
    {
        $engine = new self();
        $result = $engine->send_retention_campaign();

        // ذخیره زمان آخرین اجرا
        update_option('ht_last_retention_campaign_run', current_time('mysql'));

        error_log('Homa Retention Campaign: ' . json_encode($result));
    }
}
