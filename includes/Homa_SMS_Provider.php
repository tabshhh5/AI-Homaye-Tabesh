<?php
/**
 * MeliPayamak SMS Provider
 *
 * @package HomayeTabesh
 * @since PR11
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * سرویس ارسال پیامک از طریق ملی‌پیامک
 * 
 * این کلاس با استفاده از الگوهای (Pattern) ملی‌پیامک، پیامک‌های مختلف را ارسال می‌کند
 * معماری مشابه با مخزن Tabesh برای جلوگیری از بلک‌لیست
 */
class Homa_SMS_Provider
{
    /**
     * SOAP Client برای ارتباط با ملی‌پیامک
     */
    private ?\SoapClient $client = null;

    /**
     * تنظیمات اتصال
     */
    private string $username;
    private string $password;
    private string $from_number;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->username = get_option('ht_melipayamak_username', '');
        $this->password = get_option('ht_melipayamak_password', '');
        $this->from_number = get_option('ht_melipayamak_from_number', '');
    }

    /**
     * ارسال پیامک بر اساس الگو (Pattern)
     * 
     * @param string $to شماره موبایل گیرنده
     * @param string $pattern_code کد الگوی ملی‌پیامک
     * @param array $data داده‌های الگو
     * @return bool|string موفقیت یا پیغام خطا
     */
    public function send_pattern(string $to, string $pattern_code, array $data)
    {
        if (empty($this->username) || empty($this->password)) {
            error_log('Homa SMS Provider: MeliPayamak credentials not configured');
            return false;
        }

        try {
            $client = $this->get_client();
            
            // آماده‌سازی پارامترها
            $parameters = [
                'username' => $this->username,
                'password' => $this->password,
                'to' => $to,
                'from' => $this->from_number,
                'text' => $pattern_code,
                'isFlash' => false
            ];

            // افزودن داده‌های الگو
            $pattern_values = [];
            foreach ($data as $key => $value) {
                $pattern_values[] = $value;
            }
            $parameters['pattern_values'] = $pattern_values;

            // ارسال درخواست
            $response = $client->__soapCall('sendPatternSms', [$parameters]);

            // بررسی پاسخ
            if (isset($response->sendPatternSmsResult) && $response->sendPatternSmsResult > 0) {
                error_log("Homa SMS: Pattern sent successfully to {$to}");
                return true;
            } else {
                error_log('Homa SMS: Failed to send pattern - ' . print_r($response, true));
                return false;
            }

        } catch (\Exception $e) {
            error_log('Homa SMS Provider Error: ' . $e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     * ارسال کد OTP
     * 
     * @param string $phone_number شماره موبایل
     * @param string $otp_code کد تایید
     * @return bool موفقیت عملیات
     */
    public function send_otp(string $phone_number, string $otp_code): bool
    {
        $pattern_code = get_option('ht_melipayamak_otp_pattern', '');
        
        if (empty($pattern_code)) {
            error_log('Homa SMS: OTP pattern code not configured');
            return false;
        }

        $data = [
            'verification-code' => $otp_code,
        ];

        $result = $this->send_pattern($phone_number, $pattern_code, $data);
        return $result === true;
    }

    /**
     * ارسال اطلاع‌رسانی لید جدید به مدیر
     * 
     * @param string $admin_phone شماره موبایل مدیر
     * @param array $lead_data اطلاعات لید
     * @return bool موفقیت عملیات
     */
    public function send_lead_notification(string $admin_phone, array $lead_data): bool
    {
        $pattern_code = get_option('ht_melipayamak_lead_notification_pattern', '');
        
        if (empty($pattern_code)) {
            // fallback به ارسال پیامک ساده
            return $this->send_simple_sms($admin_phone, $this->format_lead_message($lead_data));
        }

        $data = [
            'customer-name' => $lead_data['name'] ?? 'مشتری جدید',
            'lead-score' => $lead_data['score'] ?? 0,
            'contact-info' => $lead_data['contact'] ?? '',
        ];

        $result = $this->send_pattern($admin_phone, $pattern_code, $data);
        return $result === true;
    }

    /**
     * ارسال پیامک ساده (بدون الگو)
     * برای مواقع اضطراری یا زمانی که الگو تنظیم نشده
     */
    private function send_simple_sms(string $to, string $message): bool
    {
        try {
            $client = $this->get_client();
            
            $parameters = [
                'username' => $this->username,
                'password' => $this->password,
                'to' => [$to],
                'from' => $this->from_number,
                'text' => $message,
                'isFlash' => false
            ];

            $response = $client->__soapCall('SendSimpleSMS2', [$parameters]);

            if (isset($response->SendSimpleSMS2Result) && $response->SendSimpleSMS2Result > 0) {
                return true;
            }

            return false;

        } catch (\Exception $e) {
            error_log('Homa SMS Simple Send Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * فرمت‌بندی پیغام لید برای پیامک
     */
    private function format_lead_message(array $lead_data): string
    {
        $name = $lead_data['name'] ?? 'مشتری جدید';
        $score = $lead_data['score'] ?? 0;
        $contact = $lead_data['contact'] ?? 'نامشخص';

        return "هما: لید جدید\n" .
               "نام: {$name}\n" .
               "امتیاز: {$score}\n" .
               "تماس: {$contact}";
    }

    /**
     * دریافت SOAP Client
     * 
     * @return \SoapClient
     * @throws \Exception
     */
    private function get_client(): \SoapClient
    {
        if ($this->client === null) {
            $wsdl = 'http://ippanel.com/class/sms/wsdlservice/server.php?wsdl';
            
            $this->client = new \SoapClient($wsdl, [
                'encoding' => 'UTF-8',
                'trace' => true,
                'exceptions' => true,
                'connection_timeout' => 10,
            ]);
        }

        return $this->client;
    }

    /**
     * بررسی اعتبار شماره موبایل ایران
     */
    public static function validate_iranian_phone(string $phone): bool
    {
        // حذف فاصله و کاراکترهای اضافی
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // فرمت‌های قابل قبول:
        // 09123456789
        // +989123456789
        // 989123456789
        
        if (preg_match('/^(?:\+98|98|0)?9\d{9}$/', $phone)) {
            return true;
        }

        return false;
    }

    /**
     * نرمال‌سازی شماره موبایل به فرمت استاندارد
     */
    public static function normalize_phone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // تبدیل به فرمت 09xxxxxxxxx
        if (preg_match('/^(\+98|98)(\d{10})$/', $phone, $matches)) {
            return '0' . $matches[2];
        }
        
        if (preg_match('/^0?9\d{9}$/', $phone)) {
            return '0' . ltrim($phone, '0');
        }

        return $phone;
    }
}
