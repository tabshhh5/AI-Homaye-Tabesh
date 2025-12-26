<?php
/**
 * Numerical Formatter - Anti-Hallucination Shield
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * محافظ اعداد برای جلوگیری از توهم در اعداد
 * فرمتر ثابت برای قیمت، موجودی، شماره سفارش و دیگر دادههای عددی
 */
class HT_Numerical_Formatter
{
    /**
     * Format price with currency
     *
     * @param float|int $price Price value
     * @param string $currency Currency code
     * @return array Formatted data
     */
    public function format_price($price, string $currency = 'IRR'): array
    {
        $price = (float) $price;

        return [
            'raw_value' => $price,
            'formatted' => $this->format_number($price) . ' ' . $this->get_currency_symbol($currency),
            'currency' => $currency,
            'type' => 'price',
            'hallucination_protected' => true,
        ];
    }

    /**
     * Format stock quantity
     *
     * @param int $quantity Stock quantity
     * @return array Formatted data
     */
    public function format_stock(int $quantity): array
    {
        $status = $this->get_stock_status($quantity);

        return [
            'raw_value' => $quantity,
            'formatted' => $this->format_number($quantity) . ' عدد',
            'status' => $status['status'],
            'status_label' => $status['label'],
            'type' => 'stock',
            'hallucination_protected' => true,
        ];
    }

    /**
     * Format order number
     *
     * @param int $order_number Order number
     * @return array Formatted data
     */
    public function format_order_number(int $order_number): array
    {
        return [
            'raw_value' => $order_number,
            'formatted' => '#' . str_pad((string)$order_number, 6, '0', STR_PAD_LEFT),
            'type' => 'order_number',
            'hallucination_protected' => true,
        ];
    }

    /**
     * Format product ID
     *
     * @param int $product_id Product ID
     * @return array Formatted data
     */
    public function format_product_id(int $product_id): array
    {
        return [
            'raw_value' => $product_id,
            'formatted' => 'کد محصول: ' . $product_id,
            'type' => 'product_id',
            'hallucination_protected' => true,
        ];
    }

    /**
     * Format phone number
     *
     * @param string $phone Phone number
     * @return array Formatted data
     */
    public function format_phone(string $phone): array
    {
        // Clean phone number
        $clean = preg_replace('/[^0-9+]/', '', $phone);

        // Format Iranian phone numbers
        if (preg_match('/^(0)?9\d{9}$/', $clean)) {
            $formatted = preg_replace('/^(0)?(\d{3})(\d{3})(\d{4})$/', '0$2-$3-$4', $clean);
        } else {
            $formatted = $clean;
        }

        return [
            'raw_value' => $clean,
            'formatted' => $formatted,
            'type' => 'phone',
            'hallucination_protected' => true,
        ];
    }

    /**
     * Format percentage
     *
     * @param float $percentage Percentage value
     * @param int $decimals Decimal places
     * @return array Formatted data
     */
    public function format_percentage(float $percentage, int $decimals = 1): array
    {
        return [
            'raw_value' => $percentage,
            'formatted' => number_format($percentage, $decimals) . '%',
            'type' => 'percentage',
            'hallucination_protected' => true,
        ];
    }

    /**
     * Format date
     *
     * @param string $date Date string
     * @param string $format Output format
     * @return array Formatted data
     */
    public function format_date(string $date, string $format = 'Y/m/d H:i'): array
    {
        $timestamp = strtotime($date);
        $jalali = $this->to_jalali($timestamp);

        return [
            'raw_value' => $date,
            'timestamp' => $timestamp,
            'formatted' => $jalali,
            'gregorian' => date($format, $timestamp),
            'type' => 'date',
            'hallucination_protected' => true,
        ];
    }

    /**
     * Format weight
     *
     * @param float $weight Weight value
     * @param string $unit Weight unit
     * @return array Formatted data
     */
    public function format_weight(float $weight, string $unit = 'kg'): array
    {
        return [
            'raw_value' => $weight,
            'formatted' => $this->format_number($weight) . ' ' . $this->get_weight_unit_label($unit),
            'unit' => $unit,
            'type' => 'weight',
            'hallucination_protected' => true,
        ];
    }

    /**
     * Format dimensions
     *
     * @param float $length Length
     * @param float $width Width
     * @param float $height Height
     * @param string $unit Dimension unit
     * @return array Formatted data
     */
    public function format_dimensions(float $length, float $width, float $height, string $unit = 'cm'): array
    {
        $unit_label = $this->get_dimension_unit_label($unit);

        return [
            'raw_value' => compact('length', 'width', 'height'),
            'formatted' => sprintf(
                '%s × %s × %s %s',
                $this->format_number($length),
                $this->format_number($width),
                $this->format_number($height),
                $unit_label
            ),
            'unit' => $unit,
            'type' => 'dimensions',
            'hallucination_protected' => true,
        ];
    }

    /**
     * Extract and format product data safely
     *
     * @param int $product_id Product ID
     * @return array|null Formatted product data
     */
    public function get_safe_product_data(int $product_id): ?array
    {
        if (!function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return null;
        }

        return [
            'id' => $this->format_product_id($product_id),
            'name' => [
                'raw_value' => $product->get_name(),
                'formatted' => $product->get_name(),
                'type' => 'text',
            ],
            'price' => $this->format_price($product->get_price()),
            'regular_price' => $this->format_price($product->get_regular_price()),
            'sale_price' => $product->get_sale_price() ? $this->format_price($product->get_sale_price()) : null,
            'stock' => $this->format_stock($product->get_stock_quantity() ?? 0),
            'sku' => [
                'raw_value' => $product->get_sku(),
                'formatted' => 'شناسه: ' . $product->get_sku(),
                'type' => 'sku',
            ],
        ];
    }

    /**
     * Extract and format order data safely
     *
     * @param int $order_id Order ID
     * @return array|null Formatted order data
     */
    public function get_safe_order_data(int $order_id): ?array
    {
        if (!function_exists('wc_get_order')) {
            return null;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }

        return [
            'order_number' => $this->format_order_number($order->get_id()),
            'status' => [
                'raw_value' => $order->get_status(),
                'formatted' => wc_get_order_status_name($order->get_status()),
                'type' => 'status',
            ],
            'total' => $this->format_price($order->get_total(), $order->get_currency()),
            'date' => $this->format_date($order->get_date_created()->format('Y-m-d H:i:s')),
            'customer_phone' => $this->format_phone($order->get_billing_phone()),
        ];
    }

    /**
     * Format a number with Persian digits and thousand separator
     *
     * @param float|int $number Number to format
     * @param int $decimals Decimal places
     * @return string Formatted number
     */
    private function format_number($number, int $decimals = 0): string
    {
        $formatted = number_format((float)$number, $decimals, '.', ',');
        
        // Convert to Persian digits
        return $this->to_persian_digits($formatted);
    }

    /**
     * Convert numbers to Persian digits
     *
     * @param string $string String with English digits
     * @return string String with Persian digits
     */
    private function to_persian_digits(string $string): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        return str_replace($english, $persian, $string);
    }

    /**
     * Get currency symbol
     *
     * @param string $currency Currency code
     * @return string Currency symbol
     */
    private function get_currency_symbol(string $currency): string
    {
        $symbols = [
            'IRR' => 'تومان',
            'IRT' => 'تومان',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
        ];

        return $symbols[$currency] ?? $currency;
    }

    /**
     * Get stock status
     *
     * @param int $quantity Stock quantity
     * @return array Status data
     */
    private function get_stock_status(int $quantity): array
    {
        if ($quantity <= 0) {
            return ['status' => 'out_of_stock', 'label' => 'ناموجود'];
        } elseif ($quantity <= 5) {
            return ['status' => 'low_stock', 'label' => 'موجودی کم'];
        } else {
            return ['status' => 'in_stock', 'label' => 'موجود'];
        }
    }

    /**
     * Get weight unit label
     *
     * @param string $unit Unit code
     * @return string Unit label
     */
    private function get_weight_unit_label(string $unit): string
    {
        $labels = [
            'kg' => 'کیلوگرم',
            'g' => 'گرم',
            'lb' => 'پوند',
            'oz' => 'اونس',
        ];

        return $labels[$unit] ?? $unit;
    }

    /**
     * Get dimension unit label
     *
     * @param string $unit Unit code
     * @return string Unit label
     */
    private function get_dimension_unit_label(string $unit): string
    {
        $labels = [
            'cm' => 'سانتیمتر',
            'm' => 'متر',
            'mm' => 'میلیمتر',
            'in' => 'اینچ',
        ];

        return $labels[$unit] ?? $unit;
    }

    /**
     * Convert Gregorian to Jalali date
     * 
     * Note: This is a simplified conversion for display purposes only.
     * For production use, consider integrating a proper Jalali library.
     *
     * @param int $timestamp Unix timestamp
     * @return string Jalali date
     */
    private function to_jalali(int $timestamp): string
    {
        // Simple Jalali conversion (basic implementation)
        // TODO: Use a proper Jalali library for accurate conversion
        $g_y = (int) date('Y', $timestamp);
        $g_m = (int) date('m', $timestamp);
        $g_d = (int) date('d', $timestamp);

        $j_date = $this->gregorian_to_jalali($g_y, $g_m, $g_d);
        
        return sprintf(
            '%04d/%02d/%02d %s',
            $j_date[0],
            $j_date[1],
            $j_date[2],
            date('H:i', $timestamp)
        );
    }

    /**
     * Simple Gregorian to Jalali converter
     * 
     * WARNING: This is a highly simplified approximation.
     * For accurate Jalali conversion, use a proper library.
     */
    private function gregorian_to_jalali(int $g_y, int $g_m, int $g_d): array
    {
        // Simple approximation - for production use a proper library
        $j_y = $g_y - 621;
        $j_m = $g_m;
        $j_d = $g_d;

        return [$j_y, $j_m, $j_d];
    }

    /**
     * Build structured response for AI with protected numbers
     *
     * @param string $text_template Response template with placeholders
     * @param array $data Formatted data array
     * @return array Structured response
     */
    public function build_protected_response(string $text_template, array $data): array
    {
        $formatted_text = $text_template;

        // Replace placeholders with formatted values
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['formatted'])) {
                $placeholder = '{' . $key . '}';
                $formatted_text = str_replace($placeholder, $value['formatted'], $formatted_text);
            }
        }

        return [
            'response' => $formatted_text,
            'structured_data' => $data,
            'protected' => true,
        ];
    }
}
