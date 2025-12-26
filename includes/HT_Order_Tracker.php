<?php
/**
 * Order Tracker - Smart Order Tracking System
 *
 * @package HomayeTabesh
 * @since PR12
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * سیستم پیگیری هوشمند سفارش
 * 
 * این کلاس به دیتابیس WooCommerce متصل می‌شود و اطلاعات سفارش را استخراج می‌کند.
 * همچنین وضعیت رهگیری مرسولات را از سامانه‌های پست و تیپاکس دریافت می‌کند.
 */
class HT_Order_Tracker
{
    /**
     * Get order status by order ID
     * 
     * @param int $order_id شناسه سفارش
     * @return array اطلاعات سفارش
     */
    public function get_order_status(int $order_id): array
    {
        if (!$this->is_woocommerce_active()) {
            return [
                'success' => false,
                'message' => 'ووکامرس فعال نیست.'
            ];
        }

        $order = wc_get_order($order_id);
        
        if (!$order) {
            return [
                'success' => false,
                'message' => 'سفارشی با این شماره پیدا نشد.'
            ];
        }

        $status = $order->get_status();
        $tracking_code = $order->get_meta('_shipping_tracking_number');
        $shipping_method = $order->get_shipping_method();
        
        // دریافت اطلاعات مشتری
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $customer_phone = $order->get_billing_phone();

        // محاسبه پیشرفت سفارش
        $progress_percentage = $this->calculate_order_progress($status);
        
        return [
            'success' => true,
            'order_id' => $order_id,
            'status' => $status,
            'status_label' => $this->get_status_label($status),
            'tracking_code' => $tracking_code ?: 'هنوز صادر نشده',
            'shipping_method' => $shipping_method,
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'items' => $this->get_order_items($order),
            'progress_percentage' => $progress_percentage,
            'human_message' => $this->generate_human_message($order, $status, $tracking_code)
        ];
    }

    /**
     * Get orders by customer phone number
     * 
     * @param string $phone_number شماره تلفن مشتری
     * @return array لیست سفارش‌های مشتری
     */
    public function get_orders_by_phone(string $phone_number): array
    {
        if (!$this->is_woocommerce_active()) {
            return [
                'success' => false,
                'message' => 'ووکامرس فعال نیست.'
            ];
        }

        // نرمال‌سازی شماره تلفن
        $normalized_phone = Homa_SMS_Provider::normalize_phone($phone_number);

        // جستجوی سفارش‌ها
        $orders = wc_get_orders([
            'billing_phone' => $normalized_phone,
            'limit' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (empty($orders)) {
            return [
                'success' => true,
                'count' => 0,
                'orders' => [],
                'message' => 'هیچ سفارشی با این شماره تلفن پیدا نشد.'
            ];
        }

        $orders_data = [];
        foreach ($orders as $order) {
            $orders_data[] = [
                'order_id' => $order->get_id(),
                'status' => $order->get_status(),
                'status_label' => $this->get_status_label($order->get_status()),
                'total' => $order->get_total(),
                'date' => $order->get_date_created()->format('Y-m-d H:i:s'),
                'tracking_code' => $order->get_meta('_shipping_tracking_number') ?: 'ندارد',
            ];
        }

        return [
            'success' => true,
            'count' => count($orders_data),
            'orders' => $orders_data
        ];
    }

    /**
     * Get order items summary
     * 
     * @param \WC_Order $order سفارش
     * @return array آیتم‌های سفارش
     */
    private function get_order_items(\WC_Order $order): array
    {
        $items = [];
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            $items[] = [
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
                'product_id' => $product ? $product->get_id() : null,
            ];
        }

        return $items;
    }

    /**
     * Calculate order progress percentage
     * 
     * @param string $status وضعیت سفارش
     * @return int درصد پیشرفت
     */
    private function calculate_order_progress(string $status): int
    {
        $progress_map = [
            'pending' => 10,
            'processing' => 30,
            'on-hold' => 20,
            'preparing' => 50,
            'shipped' => 80,
            'completed' => 100,
            'cancelled' => 0,
            'refunded' => 0,
            'failed' => 0,
        ];

        return $progress_map[$status] ?? 0;
    }

    /**
     * Get human-readable status label
     * 
     * @param string $status وضعیت سفارش
     * @return string برچسب فارسی
     */
    private function get_status_label(string $status): string
    {
        $labels = [
            'pending' => 'در انتظار پرداخت',
            'processing' => 'در حال پردازش',
            'on-hold' => 'معلق',
            'preparing' => 'در حال آماده‌سازی',
            'shipped' => 'ارسال شده',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
            'refunded' => 'بازگشت داده شده',
            'failed' => 'ناموفق',
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Generate human-friendly message for Homa
     * 
     * @param \WC_Order $order سفارش
     * @param string $status وضعیت
     * @param string|null $tracking_code کد رهگیری
     * @return string پیام انسانی
     */
    private function generate_human_message(\WC_Order $order, string $status, ?string $tracking_code): string
    {
        $customer_name = $order->get_billing_first_name();
        $order_id = $order->get_id();
        
        $messages = [
            'pending' => "{$customer_name} جان، سفارش شماره {$order_id} در انتظار پرداخت است. لطفاً پرداخت را تکمیل کن.",
            'processing' => "{$customer_name} عزیز، سفارش {$order_id} در حال پردازش است. بزودی برای چاپ آماده می‌شود.",
            'preparing' => "{$customer_name} جان، سفارش {$order_id} الان در مرحله چاپ و آماده‌سازی است.",
            'shipped' => "{$customer_name} عزیز، خبر خوب! سفارش {$order_id} ارسال شده. کد رهگیری: {$tracking_code}",
            'completed' => "{$customer_name} جان، سفارش {$order_id} تحویل شده. امیدوارم راضی باشی! 🌟",
            'cancelled' => "سفارش {$order_id} لغو شده است.",
            'on-hold' => "{$customer_name} جان، سفارش {$order_id} معلق است. لطفاً با پشتیبانی تماس بگیر.",
        ];

        return $messages[$status] ?? "وضعیت سفارش {$order_id}: " . $this->get_status_label($status);
    }

    /**
     * Check if WooCommerce is active
     * 
     * @return bool
     */
    private function is_woocommerce_active(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * Get order by tracking code
     * 
     * @param string $tracking_code کد رهگیری
     * @return array|null
     */
    public function get_order_by_tracking_code(string $tracking_code): ?array
    {
        if (!$this->is_woocommerce_active()) {
            return null;
        }

        $orders = wc_get_orders([
            'meta_key' => '_shipping_tracking_number',
            'meta_value' => $tracking_code,
            'limit' => 1,
        ]);

        if (empty($orders)) {
            return null;
        }

        return $this->get_order_status($orders[0]->get_id());
    }
}
