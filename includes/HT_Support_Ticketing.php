<?php
/**
 * Support Ticketing - Conversation-based Support System
 *
 * @package HomayeTabesh
 * @since PR12
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * سیستم تیکتینگ بدون فرم (Conversation-based Support)
 * 
 * کاربر به جای پر کردن فرم تیکت، با هما حرف می‌زند.
 * هما متن را تحلیل کرده و تیکت دسته‌بندی شده ایجاد می‌کند.
 */
class HT_Support_Ticketing
{
    /**
     * سطوح فوریت تیکت
     */
    private const URGENCY_CRITICAL = 'critical';
    private const URGENCY_HIGH = 'high';
    private const URGENCY_MEDIUM = 'medium';
    private const URGENCY_LOW = 'low';

    /**
     * دسته‌بندی تیکت‌ها
     */
    private const CATEGORY_QUALITY_COMPLAINT = 'quality_complaint';
    private const CATEGORY_TECHNICAL_ISSUE = 'technical_issue';
    private const CATEGORY_SHIPPING_INQUIRY = 'shipping_inquiry';
    private const CATEGORY_ORDER_MODIFICATION = 'order_modification';
    private const CATEGORY_REFUND_REQUEST = 'refund_request';
    private const CATEGORY_GENERAL_INQUIRY = 'general_inquiry';

    /**
     * Create support ticket from conversation
     * 
     * @param array $conversation_data داده‌های مکالمه
     * @return array نتیجه ایجاد تیکت
     */
    public function create_ticket_from_conversation(array $conversation_data): array
    {
        $user_id = $conversation_data['user_id'] ?? 0;
        $message = $conversation_data['message'] ?? '';
        $context = $conversation_data['context'] ?? [];

        if (empty($message)) {
            return [
                'success' => false,
                'message' => 'متن پیام خالی است.',
            ];
        }

        // تحلیل متن برای تشخیص دسته و فوریت
        $analysis = $this->analyze_message($message, $context);

        // ایجاد تیکت
        $ticket_id = $this->insert_ticket([
            'user_id' => $user_id,
            'subject' => $analysis['subject'],
            'message' => $message,
            'category' => $analysis['category'],
            'urgency' => $analysis['urgency'],
            'status' => 'open',
            'created_at' => current_time('mysql'),
            'metadata' => json_encode($context),
        ]);

        if (!$ticket_id) {
            return [
                'success' => false,
                'message' => 'خطا در ایجاد تیکت.',
            ];
        }

        // ارسال نوتیفیکیشن به ادمین
        $this->notify_admin_new_ticket($ticket_id, $analysis);

        return [
            'success' => true,
            'ticket_id' => $ticket_id,
            'category' => $analysis['category'],
            'category_label' => $analysis['category_label'],
            'urgency' => $analysis['urgency'],
            'urgency_label' => $analysis['urgency_label'],
            'message' => 'تیکت پشتیبانی با موفقیت ثبت شد. تیم ما به زودی بررسی می‌کنند.',
        ];
    }

    /**
     * Analyze message to detect category and urgency
     * 
     * @param string $message متن پیام
     * @param array $context کانتکست مکالمه
     * @return array نتیجه تحلیل
     */
    private function analyze_message(string $message, array $context): array
    {
        $message_lower = mb_strtolower($message, 'UTF-8');

        // تشخیص دسته
        $category = $this->detect_category($message_lower, $context);

        // تشخیص فوریت
        $urgency = $this->detect_urgency($message_lower, $context, $category);

        // تولید موضوع خودکار
        $subject = $this->generate_subject($category, $message);

        return [
            'category' => $category,
            'category_label' => $this->get_category_label($category),
            'urgency' => $urgency,
            'urgency_label' => $this->get_urgency_label($urgency),
            'subject' => $subject,
        ];
    }

    /**
     * Detect ticket category from message
     * 
     * @param string $message متن پیام (lowercase)
     * @param array $context کانتکست
     * @return string دسته
     */
    private function detect_category(string $message, array $context): string
    {
        // کلمات کلیدی برای هر دسته
        $keywords = [
            self::CATEGORY_QUALITY_COMPLAINT => [
                'کیفیت', 'مشکل', 'خراب', 'معیوب', 'ضعیف', 'بد', 'ناراضی', 'شکایت'
            ],
            self::CATEGORY_SHIPPING_INQUIRY => [
                'ارسال', 'پست', 'رسید', 'کجاست', 'تحویل', 'رهگیری', 'گیر کرده'
            ],
            self::CATEGORY_REFUND_REQUEST => [
                'بازگشت', 'پول', 'مرجوع', 'کنسل', 'لغو', 'استرداد', 'پس بده'
            ],
            self::CATEGORY_ORDER_MODIFICATION => [
                'تغییر', 'ویرایش', 'اصلاح', 'اشتباه', 'آدرس', 'مشخصات'
            ],
            self::CATEGORY_TECHNICAL_ISSUE => [
                'فنی', 'باگ', 'خطا', 'نمیشه', 'کار نمی‌کند', 'مشکل فنی'
            ],
        ];

        // امتیازدهی به هر دسته
        $scores = [];
        foreach ($keywords as $category => $words) {
            $score = 0;
            foreach ($words as $word) {
                if (strpos($message, $word) !== false) {
                    $score++;
                }
            }
            $scores[$category] = $score;
        }

        // انتخاب دسته با بالاترین امتیاز
        arsort($scores);
        $detected_category = array_key_first($scores);

        // اگر هیچ کلمه‌ای مطابقت نداشت، دسته عمومی
        return $scores[$detected_category] > 0 ? $detected_category : self::CATEGORY_GENERAL_INQUIRY;
    }

    /**
     * Detect urgency level from message
     * 
     * @param string $message متن پیام (lowercase)
     * @param array $context کانتکست
     * @param string $category دسته تیکت
     * @return string سطح فوریت
     */
    private function detect_urgency(string $message, array $context, string $category): string
    {
        // کلمات کلیدی برای فوریت بالا
        $urgent_keywords = [
            'فوری', 'اضطراری', 'سریع', 'زود', 'فوراً', 'خیلی مهم', 'ضروری', 'حتماً'
        ];

        // کلمات کلیدی برای عصبانیت (Critical)
        $angry_keywords = [
            'عصبانی', 'ناراضی', 'بد', 'افتضاح', 'وحشتناک', 'غیرقابل قبول', 'شکایت'
        ];

        // چک کردن کلمات عصبانیت
        foreach ($angry_keywords as $word) {
            if (strpos($message, $word) !== false) {
                return self::URGENCY_CRITICAL;
            }
        }

        // چک کردن کلمات فوریت
        foreach ($urgent_keywords as $word) {
            if (strpos($message, $word) !== false) {
                return self::URGENCY_HIGH;
            }
        }

        // اگر دسته شکایت کیفیت باشد، فوریت بالا
        if ($category === self::CATEGORY_QUALITY_COMPLAINT) {
            return self::URGENCY_HIGH;
        }

        // اگر درخواست بازگشت پول باشد، فوریت متوسط به بالا
        if ($category === self::CATEGORY_REFUND_REQUEST) {
            return self::URGENCY_MEDIUM;
        }

        // سایر موارد
        return self::URGENCY_MEDIUM;
    }

    /**
     * Generate automatic subject for ticket
     * 
     * @param string $category دسته
     * @param string $message متن پیام
     * @return string موضوع
     */
    private function generate_subject(string $category, string $message): string
    {
        $category_label = $this->get_category_label($category);
        
        // استخراج 50 کاراکتر اول پیام
        $short_message = mb_substr($message, 0, 50, 'UTF-8');
        
        return $category_label . ': ' . $short_message . '...';
    }

    /**
     * Get category label in Persian
     * 
     * @param string $category کد دسته
     * @return string برچسب فارسی
     */
    private function get_category_label(string $category): string
    {
        $labels = [
            self::CATEGORY_QUALITY_COMPLAINT => 'شکایت از کیفیت',
            self::CATEGORY_TECHNICAL_ISSUE => 'مشکل فنی',
            self::CATEGORY_SHIPPING_INQUIRY => 'استعلام ارسال',
            self::CATEGORY_ORDER_MODIFICATION => 'تغییر سفارش',
            self::CATEGORY_REFUND_REQUEST => 'درخواست بازگشت وجه',
            self::CATEGORY_GENERAL_INQUIRY => 'سوال عمومی',
        ];

        return $labels[$category] ?? 'نامشخص';
    }

    /**
     * Get urgency label in Persian
     * 
     * @param string $urgency کد فوریت
     * @return string برچسب فارسی
     */
    private function get_urgency_label(string $urgency): string
    {
        $labels = [
            self::URGENCY_CRITICAL => 'بحرانی',
            self::URGENCY_HIGH => 'فوری',
            self::URGENCY_MEDIUM => 'متوسط',
            self::URGENCY_LOW => 'عادی',
        ];

        return $labels[$urgency] ?? 'متوسط';
    }

    /**
     * Insert ticket into database
     * 
     * @param array $data داده‌های تیکت
     * @return int|false شناسه تیکت یا false
     */
    private function insert_ticket(array $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_support_tickets';

        // ایجاد جدول در صورت عدم وجود
        $this->maybe_create_table();

        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            error_log('Homa Support Ticketing: Failed to insert ticket - ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Create support tickets table if not exists
     * 
     * @return void
     */
    private function maybe_create_table(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_support_tickets';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            subject text NOT NULL,
            message longtext NOT NULL,
            category varchar(50) NOT NULL,
            urgency varchar(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'open',
            assigned_to bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            resolved_at datetime DEFAULT NULL,
            metadata longtext,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY category (category),
            KEY urgency (urgency),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Notify admin about new ticket
     * 
     * @param int $ticket_id شناسه تیکت
     * @param array $analysis تحلیل تیکت
     * @return void
     */
    private function notify_admin_new_ticket(int $ticket_id, array $analysis): void
    {
        // ارسال پیامک به مدیر در صورت فوریت بالا
        if (in_array($analysis['urgency'], [self::URGENCY_CRITICAL, self::URGENCY_HIGH])) {
            $admin_phone = get_option('ht_admin_notification_phone', '');
            
            if (!empty($admin_phone)) {
                $sms_provider = new Homa_SMS_Provider();
                $message = "هما: تیکت {$analysis['urgency_label']} جدید\n" .
                           "دسته: {$analysis['category_label']}\n" .
                           "شماره تیکت: #{$ticket_id}";
                
                $sms_provider->send_simple_sms($admin_phone, $message);
            }
        }

        // ثبت لاگ
        error_log("Homa Support: New ticket #{$ticket_id} created with urgency {$analysis['urgency']}");
    }

    /**
     * Get ticket by ID
     * 
     * @param int $ticket_id شناسه تیکت
     * @return array|null
     */
    public function get_ticket(int $ticket_id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_support_tickets';

        $ticket = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $ticket_id),
            ARRAY_A
        );

        if (!$ticket) {
            return null;
        }

        // اضافه کردن برچسب‌ها
        $ticket['category_label'] = $this->get_category_label($ticket['category']);
        $ticket['urgency_label'] = $this->get_urgency_label($ticket['urgency']);

        return $ticket;
    }

    /**
     * Get user tickets
     * 
     * @param int $user_id شناسه کاربر
     * @param int $limit تعداد نتایج
     * @return array
     */
    public function get_user_tickets(int $user_id, int $limit = 10): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_support_tickets';

        $tickets = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        );

        // اضافه کردن برچسب‌ها
        foreach ($tickets as &$ticket) {
            $ticket['category_label'] = $this->get_category_label($ticket['category']);
            $ticket['urgency_label'] = $this->get_urgency_label($ticket['urgency']);
        }

        return $tickets;
    }
}
