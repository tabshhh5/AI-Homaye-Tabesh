<?php
/**
 * OTP Core Engine
 *
 * @package HomayeTabesh
 * @since PR11
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * موتور احراز هویت OTP
 * 
 * این کلاس مدیریت کامل فرآیند احراز هویت با کد یکبار مصرف را انجام می‌دهد
 */
class Homa_OTP_Core_Engine
{
    /**
     * مدت اعتبار کد OTP (ثانیه)
     */
    private const OTP_VALIDITY_DURATION = 120; // 2 دقیقه

    /**
     * حداکثر تعداد تلاش برای ورود کد
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * محدودیت تعداد درخواست OTP در بازه زمانی (Rate Limiting)
     */
    private const RATE_LIMIT_REQUESTS = 3;
    private const RATE_LIMIT_WINDOW = 3600; // 1 ساعت

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
     * تولید و ارسال کد OTP
     * 
     * @param string $phone_number شماره موبایل
     * @param string|null $session_token توکن نشست (اختیاری)
     * @return array نتیجه عملیات
     */
    public function send_otp(string $phone_number, ?string $session_token = null): array
    {
        global $wpdb;

        // نرمال‌سازی شماره موبایل
        $phone_number = Homa_SMS_Provider::normalize_phone($phone_number);

        // اعتبارسنجی شماره
        if (!Homa_SMS_Provider::validate_iranian_phone($phone_number)) {
            return [
                'success' => false,
                'message' => 'شماره موبایل نامعتبر است'
            ];
        }

        // بررسی Rate Limiting
        if (!$this->check_rate_limit($phone_number)) {
            return [
                'success' => false,
                'message' => 'تعداد درخواست‌های شما از حد مجاز بیشتر است. لطفاً بعداً تلاش کنید'
            ];
        }

        // تولید کد 6 رقمی تصادفی
        $otp_code = $this->generate_otp_code();

        // محاسبه زمان انقضا
        $expires_at = date('Y-m-d H:i:s', time() + self::OTP_VALIDITY_DURATION);

        // ذخیره در دیتابیس
        $table_name = $wpdb->prefix . 'homa_otp';
        
        $inserted = $wpdb->insert(
            $table_name,
            [
                'phone_number' => $phone_number,
                'otp_code' => $otp_code,
                'session_token' => $session_token,
                'attempts' => 0,
                'is_verified' => 0,
                'expires_at' => $expires_at,
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s']
        );

        if (!$inserted) {
            error_log('Homa OTP: Failed to save OTP to database');
            return [
                'success' => false,
                'message' => 'خطا در ذخیره‌سازی کد تایید'
            ];
        }

        // ارسال پیامک
        $sms_sent = $this->sms_provider->send_otp($phone_number, $otp_code);

        if (!$sms_sent) {
            return [
                'success' => false,
                'message' => 'خطا در ارسال پیامک. لطفاً بعداً تلاش کنید'
            ];
        }

        // ذخیره در transient برای سرعت بیشتر (cache)
        $transient_key = 'homa_otp_' . md5($phone_number);
        set_transient($transient_key, [
            'code' => $otp_code,
            'expires' => time() + self::OTP_VALIDITY_DURATION,
        ], self::OTP_VALIDITY_DURATION);

        return [
            'success' => true,
            'message' => 'کد تایید به شماره شما ارسال شد',
            'expires_in' => self::OTP_VALIDITY_DURATION,
        ];
    }

    /**
     * تایید کد OTP
     * 
     * @param string $phone_number شماره موبایل
     * @param string $otp_code کد وارد شده
     * @return array نتیجه عملیات
     */
    public function verify_otp(string $phone_number, string $otp_code): array
    {
        global $wpdb;

        $phone_number = Homa_SMS_Provider::normalize_phone($phone_number);
        $table_name = $wpdb->prefix . 'homa_otp';

        // جستجوی کد معتبر
        $otp_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE phone_number = %s 
             AND otp_code = %s 
             AND is_verified = 0 
             AND expires_at > NOW()
             ORDER BY created_at DESC
             LIMIT 1",
            $phone_number,
            $otp_code
        ));

        if (!$otp_record) {
            // بررسی تعداد تلاش‌های ناموفق
            $this->increment_attempts($phone_number);

            return [
                'success' => false,
                'message' => 'کد تایید نامعتبر یا منقضی شده است'
            ];
        }

        // بررسی تعداد تلاش‌ها
        if ($otp_record->attempts >= self::MAX_ATTEMPTS) {
            return [
                'success' => false,
                'message' => 'تعداد تلاش‌های شما بیش از حد مجاز است. لطفاً کد جدید درخواست کنید'
            ];
        }

        // علامت‌گذاری به عنوان تایید شده
        $wpdb->update(
            $table_name,
            ['is_verified' => 1],
            ['id' => $otp_record->id],
            ['%d'],
            ['%d']
        );

        // حذف transient
        $transient_key = 'homa_otp_' . md5($phone_number);
        delete_transient($transient_key);

        return [
            'success' => true,
            'message' => 'شماره موبایل شما تایید شد',
            'session_token' => $otp_record->session_token,
        ];
    }

    /**
     * تولید کد OTP 6 رقمی
     */
    private function generate_otp_code(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * بررسی محدودیت نرخ درخواست (Rate Limiting)
     */
    private function check_rate_limit(string $phone_number): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homa_otp';
        $window_start = date('Y-m-d H:i:s', time() - self::RATE_LIMIT_WINDOW);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE phone_number = %s 
             AND created_at > %s",
            $phone_number,
            $window_start
        ));

        return $count < self::RATE_LIMIT_REQUESTS;
    }

    /**
     * افزایش تعداد تلاش‌های ناموفق
     */
    private function increment_attempts(string $phone_number): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homa_otp';

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} 
             SET attempts = attempts + 1 
             WHERE phone_number = %s 
             AND is_verified = 0 
             AND expires_at > NOW()",
            $phone_number
        ));
    }

    /**
     * پاکسازی کدهای منقضی شده (برای Cron Job)
     */
    public static function cleanup_expired_otps(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homa_otp';
        
        $deleted = $wpdb->query(
            "DELETE FROM {$table_name} 
             WHERE expires_at < NOW() 
             OR (is_verified = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY))"
        );

        if ($deleted) {
            error_log("Homa OTP Cleanup: Removed {$deleted} expired records");
        }
    }

    /**
     * ثبت نام خودکار یا لاگین کاربر پس از تایید OTP
     * 
     * @param string $phone_number شماره موبایل تایید شده
     * @param array $user_data اطلاعات اضافی کاربر
     * @return array نتیجه عملیات
     */
    public function register_or_login_user(string $phone_number, array $user_data = []): array
    {
        $phone_number = Homa_SMS_Provider::normalize_phone($phone_number);

        // بررسی اینکه آیا کاربر از قبل وجود دارد
        $existing_user = get_user_by('login', $phone_number);

        if ($existing_user) {
            // کاربر موجود است - فقط لاگین
            wp_set_current_user($existing_user->ID);
            wp_set_auth_cookie($existing_user->ID, true);
            
            return [
                'success' => true,
                'action' => 'login',
                'user_id' => $existing_user->ID,
                'message' => 'با موفقیت وارد شدید'
            ];
        }

        // ساخت کاربر جدید
        $user_data_insert = [
            'user_login' => $phone_number,
            'user_pass' => wp_generate_password(16, true, true),
            'role' => 'customer',
            'user_email' => $phone_number . '@temp.chapko.ir', // ایمیل موقت
        ];

        // افزودن اطلاعات اضافی
        if (!empty($user_data['first_name'])) {
            $user_data_insert['first_name'] = sanitize_text_field($user_data['first_name']);
        }

        if (!empty($user_data['last_name'])) {
            $user_data_insert['last_name'] = sanitize_text_field($user_data['last_name']);
        }

        $user_id = wp_insert_user($user_data_insert);

        if (is_wp_error($user_id)) {
            error_log('Homa OTP: User creation failed - ' . $user_id->get_error_message());
            return [
                'success' => false,
                'message' => 'خطا در ایجاد حساب کاربری'
            ];
        }

        // ذخیره شماره موبایل به عنوان متا
        update_user_meta($user_id, 'billing_phone', $phone_number);
        update_user_meta($user_id, 'homa_registered_via_otp', true);

        // لاگین خودکار (Silent Login)
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        return [
            'success' => true,
            'action' => 'register',
            'user_id' => $user_id,
            'message' => 'حساب کاربری شما ایجاد و وارد شدید'
        ];
    }
}
