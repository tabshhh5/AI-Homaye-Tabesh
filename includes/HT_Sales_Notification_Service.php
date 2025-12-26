<?php
/**
 * Sales Notification Service
 *
 * @package HomayeTabesh
 * @since PR11
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * سرویس اطلاع‌رسانی فروش
 * 
 * این کلاس برای لیدهای Hot (امتیاز بالا) به تیم فروش اطلاع می‌دهد
 */
class HT_Sales_Notification_Service
{
    /**
     * SMS Provider
     */
    private Homa_SMS_Provider $sms_provider;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->sms_provider = new Homa_SMS_Provider();
    }

    /**
     * ارسال نوتیفیکیشن برای لید جدید
     * 
     * @param array $lead_data اطلاعات لید
     * @return void
     */
    public function notify_new_lead(array $lead_data): void
    {
        // بررسی فعال بودن نوتیفیکیشن
        if (!get_option('ht_lead_notification_enabled', true)) {
            return;
        }

        $lead_score = $lead_data['lead_score'] ?? 0;

        // فقط برای لیدهای Hot نوتیفیکیشن ارسال می‌شود
        if (!HT_Lead_Scoring_Algorithm::needs_immediate_notification($lead_score)) {
            return;
        }

        // ارسال به کانال‌های مختلف
        $this->send_sms_notification($lead_data);
        $this->send_email_notification($lead_data);
        $this->send_admin_dashboard_notification($lead_data);
    }

    /**
     * ارسال نوتیفیکیشن پیامکی
     */
    private function send_sms_notification(array $lead_data): void
    {
        $admin_phone = get_option('ht_admin_phone_number', '');
        
        if (empty($admin_phone)) {
            return;
        }

        $message_data = [
            'name' => $lead_data['contact_name'] ?? 'مشتری جدید',
            'score' => $lead_data['lead_score'] ?? 0,
            'contact' => $lead_data['contact_info'] ?? 'نامشخص',
        ];

        $sent = $this->sms_provider->send_lead_notification($admin_phone, $message_data);

        if ($sent) {
            error_log("Homa Sales Notification: SMS sent to admin for lead #{$lead_data['id']}");
        }
    }

    /**
     * ارسال نوتیفیکیشن ایمیل
     */
    private function send_email_notification(array $lead_data): void
    {
        $admin_email = get_option('admin_email');
        
        if (empty($admin_email)) {
            return;
        }

        $subject = '🔥 لید جدید با اولویت بالا - هما';
        
        $message = $this->format_email_message($lead_data);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: هما <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>',
        ];

        $sent = wp_mail($admin_email, $subject, $message, $headers);

        if ($sent) {
            error_log("Homa Sales Notification: Email sent to admin for lead #{$lead_data['id']}");
        }
    }

    /**
     * فرمت‌بندی پیام ایمیل
     */
    private function format_email_message(array $lead_data): string
    {
        $score = $lead_data['lead_score'] ?? 0;
        $status = HT_Lead_Scoring_Algorithm::get_lead_status($score);
        $name = $lead_data['contact_name'] ?? 'نامشخص';
        $contact = $lead_data['contact_info'] ?? 'نامشخص';
        $source = $lead_data['source_referral'] ?? 'organic';
        
        $requirements_html = '';
        if (!empty($lead_data['requirements_summary'])) {
            $requirements = is_string($lead_data['requirements_summary']) 
                ? json_decode($lead_data['requirements_summary'], true) 
                : $lead_data['requirements_summary'];
            
            if (is_array($requirements)) {
                $requirements_html = '<ul>';
                foreach ($requirements as $key => $value) {
                    $requirements_html .= "<li><strong>{$key}:</strong> {$value}</li>";
                }
                $requirements_html .= '</ul>';
            }
        }

        $draft_order_link = '';
        if (!empty($lead_data['draft_order_id'])) {
            $order_url = admin_url('post.php?post=' . $lead_data['draft_order_id'] . '&action=edit');
            $draft_order_link = "<p><a href='{$order_url}' style='background: #2271b1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block;'>مشاهده سفارش پیش‌نویس</a></p>";
        }

        $status_emoji = [
            'hot' => '🔥',
            'warm' => '⚡',
            'medium' => '💼',
            'cold' => '❄️',
        ];

        return "
        <html dir='rtl'>
        <head>
            <meta charset='UTF-8'>
        </head>
        <body style='font-family: Tahoma, sans-serif; direction: rtl; text-align: right;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 10px 10px 0 0;'>
                <h2 style='color: white; margin: 0;'>🤖 دستیار هوشمند هما</h2>
            </div>
            <div style='background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 10px 10px;'>
                <h3 style='color: #333;'>{$status_emoji[$status]} لید جدید با اولویت بالا</h3>
                
                <table style='width: 100%; margin: 20px 0; border-collapse: collapse;'>
                    <tr style='background: #f0f0f0;'>
                        <td style='padding: 10px; font-weight: bold; border: 1px solid #ddd;'>امتیاز لید</td>
                        <td style='padding: 10px; border: 1px solid #ddd;'>{$score}/100</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; font-weight: bold; border: 1px solid #ddd;'>وضعیت</td>
                        <td style='padding: 10px; border: 1px solid #ddd;'>{$status}</td>
                    </tr>
                    <tr style='background: #f0f0f0;'>
                        <td style='padding: 10px; font-weight: bold; border: 1px solid #ddd;'>نام</td>
                        <td style='padding: 10px; border: 1px solid #ddd;'>{$name}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; font-weight: bold; border: 1px solid #ddd;'>اطلاعات تماس</td>
                        <td style='padding: 10px; border: 1px solid #ddd; direction: ltr; text-align: left;'>{$contact}</td>
                    </tr>
                    <tr style='background: #f0f0f0;'>
                        <td style='padding: 10px; font-weight: bold; border: 1px solid #ddd;'>منبع</td>
                        <td style='padding: 10px; border: 1px solid #ddd;'>{$source}</td>
                    </tr>
                </table>

                " . (!empty($requirements_html) ? "
                <h4 style='color: #333;'>مشخصات درخواست:</h4>
                {$requirements_html}
                " : "") . "

                {$draft_order_link}

                <p style='color: #666; font-size: 12px; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px;'>
                    این ایمیل به صورت خودکار توسط دستیار هوشمند هما ارسال شده است.
                </p>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * ارسال نوتیفیکیشن به داشبورد مدیریت
     */
    private function send_admin_dashboard_notification(array $lead_data): void
    {
        // ذخیره نوتیفیکیشن در transient برای نمایش در داشبورد Atlas
        $notifications = get_transient('homa_admin_notifications') ?: [];
        
        $notifications[] = [
            'id' => $lead_data['id'] ?? 0,
            'type' => 'hot_lead',
            'title' => 'لید جدید با اولویت بالا',
            'message' => sprintf(
                'مشتری %s با امتیاز %d ثبت شد',
                $lead_data['contact_name'] ?? 'جدید',
                $lead_data['lead_score'] ?? 0
            ),
            'timestamp' => time(),
            'data' => $lead_data,
        ];

        // نگهداری فقط 10 نوتیفیکیشن اخیر
        $notifications = array_slice($notifications, -10);

        set_transient('homa_admin_notifications', $notifications, DAY_IN_SECONDS);
    }

    /**
     * دریافت نوتیفیکیشن‌های مدیریت
     */
    public function get_admin_notifications(): array
    {
        return get_transient('homa_admin_notifications') ?: [];
    }

    /**
     * پاکسازی نوتیفیکیشن‌ها
     */
    public function clear_notifications(): void
    {
        delete_transient('homa_admin_notifications');
    }
}
