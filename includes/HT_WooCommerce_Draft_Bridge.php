<?php
/**
 * WooCommerce Draft Order Bridge
 *
 * @package HomayeTabesh
 * @since PR11
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * پل اتصال هوشمند به ووکامرس
 * 
 * این کلاس داده‌های استخراج شده از چت را به سفارش پیش‌نویس در ووکامرس تبدیل می‌کند
 */
class HT_WooCommerce_Draft_Bridge
{
    /**
     * ایجاد سفارش پیش‌نویس از داده‌های چت
     * 
     * @param array $chat_data داده‌های استخراج شده از چت
     * @return int|false شناسه سفارش یا false در صورت خطا
     */
    public function create_draft_order(array $chat_data)
    {
        if (!function_exists('wc_create_order')) {
            error_log('Homa Draft Bridge: WooCommerce not active');
            return false;
        }

        try {
            // ایجاد سفارش خالی
            $order = wc_create_order();

            // افزودن محصولات
            if (!empty($chat_data['products'])) {
                $this->add_products_to_order($order, $chat_data['products']);
            }

            // تنظیم مشتری
            if (!empty($chat_data['user_id'])) {
                $order->set_customer_id($chat_data['user_id']);
            }

            // تنظیم اطلاعات تماس
            $this->set_billing_info($order, $chat_data);

            // افزودن یادداشت
            $note = $this->generate_order_note($chat_data);
            $order->add_order_note($note, false, true);

            // تنظیم متاداده سفارش
            $order->update_meta_data('_homa_generated', true);
            $order->update_meta_data('_homa_lead_score', $chat_data['lead_score'] ?? 0);
            $order->update_meta_data('_homa_requirements', json_encode($chat_data['requirements'] ?? []));
            
            if (!empty($chat_data['session_token'])) {
                $order->update_meta_data('_homa_session_token', $chat_data['session_token']);
            }

            // محاسبه مجموع
            $order->calculate_totals();

            // تنظیم وضعیت سفارش به "در انتظار"
            $order->update_status('pending', 'ایجاد شده توسط دستیار هوشمند هما', true);

            // ذخیره سفارش
            $order->save();

            error_log("Homa Draft Bridge: Created order #{$order->get_id()}");

            return $order->get_id();

        } catch (\Exception $e) {
            error_log('Homa Draft Bridge Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * افزودن محصولات به سفارش
     */
    private function add_products_to_order($order, array $products): void
    {
        foreach ($products as $product_data) {
            $product_id = $product_data['id'] ?? 0;
            $quantity = $product_data['quantity'] ?? 1;
            
            if ($product_id > 0) {
                $product = wc_get_product($product_id);
                
                if ($product) {
                    $order->add_product($product, $quantity);
                }
            }
        }
    }

    /**
     * تنظیم اطلاعات صورتحساب
     */
    private function set_billing_info($order, array $chat_data): void
    {
        $billing_data = [];

        if (!empty($chat_data['contact_name'])) {
            $name_parts = explode(' ', $chat_data['contact_name'], 2);
            $billing_data['first_name'] = $name_parts[0];
            $billing_data['last_name'] = $name_parts[1] ?? '';
        }

        if (!empty($chat_data['contact_info'])) {
            // اگر شماره موبایل است
            if (Homa_SMS_Provider::validate_iranian_phone($chat_data['contact_info'])) {
                $billing_data['phone'] = $chat_data['contact_info'];
            } else {
                // ممکن است ایمیل باشد
                if (is_email($chat_data['contact_info'])) {
                    $billing_data['email'] = $chat_data['contact_info'];
                }
            }
        }

        if (!empty($billing_data)) {
            $order->set_address($billing_data, 'billing');
        }
    }

    /**
     * تولید یادداشت سفارش
     */
    private function generate_order_note(array $chat_data): string
    {
        $note = "سفارش پیش‌نویس ایجاد شده توسط هما 🤖\n\n";

        if (!empty($chat_data['requirements'])) {
            $note .= "مشخصات درخواست:\n";
            
            foreach ($chat_data['requirements'] as $key => $value) {
                $label = $this->translate_requirement_key($key);
                $note .= "• {$label}: {$value}\n";
            }
        }

        if (!empty($chat_data['lead_score'])) {
            $note .= "\nامتیاز لید: {$chat_data['lead_score']}/100";
        }

        if (!empty($chat_data['source_referral'])) {
            $note .= "\nمنبع: {$chat_data['source_referral']}";
        }

        return $note;
    }

    /**
     * ترجمه کلیدهای فنی به فارسی
     */
    private function translate_requirement_key(string $key): string
    {
        $translations = [
            'volume' => 'تیراژ',
            'paper_type' => 'نوع کاغذ',
            'print_type' => 'نوع چاپ',
            'coating' => 'پوشش',
            'binding' => 'صحافی',
            'size' => 'ابعاد',
            'pages' => 'تعداد صفحات',
            'colors' => 'رنگ‌بندی',
            'delivery_date' => 'تاریخ تحویل',
            'budget' => 'بودجه',
            'notes' => 'توضیحات',
        ];

        return $translations[$key] ?? $key;
    }

    /**
     * به‌روزرسانی سفارش موجود
     */
    public function update_draft_order(int $order_id, array $chat_data): bool
    {
        if (!function_exists('wc_get_order')) {
            return false;
        }

        try {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                return false;
            }

            // به‌روزرسانی متاداده
            if (isset($chat_data['lead_score'])) {
                $order->update_meta_data('_homa_lead_score', $chat_data['lead_score']);
            }

            if (isset($chat_data['requirements'])) {
                $order->update_meta_data('_homa_requirements', json_encode($chat_data['requirements']));
            }

            // افزودن یادداشت جدید
            $note = "به‌روزرسانی از هما:\n" . json_encode($chat_data, JSON_UNESCAPED_UNICODE);
            $order->add_order_note($note, false, true);

            $order->save();

            return true;

        } catch (\Exception $e) {
            error_log('Homa Draft Bridge Update Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت سفارش‌های پیش‌نویس کاربر
     */
    public function get_user_draft_orders(int $user_id): array
    {
        if (!function_exists('wc_get_orders')) {
            return [];
        }

        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => 'pending',
            'meta_key' => '_homa_generated',
            'meta_value' => true,
            'limit' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return $orders;
    }
}
