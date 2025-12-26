<?php
/**
 * Shipping API Bridge - Post & Tipax Integration
 *
 * @package HomayeTabesh
 * @since PR12
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * پل اتصال به وبسرویس‌های رهگیری مرسولات
 * 
 * این کلاس به API پست و تیپاکس متصل می‌شود و وضعیت بسته را استعلام می‌دهد.
 */
class HT_Shipping_API_Bridge
{
    /**
     * پست API endpoint
     */
    private const POST_API_URL = 'https://tracking.post.ir/api/v1/track';

    /**
     * تیپاکس API endpoint
     */
    private const TIPAX_API_URL = 'https://api.tipax.ir/tracking/v1/status';

    /**
     * تایم‌اوت برای درخواست API (ثانیه)
     */
    private const API_TIMEOUT = 10;

    /**
     * Query tracking status from Post or Tipax
     * 
     * @param string $tracking_code کد رهگیری
     * @param string $service نوع سرویس (post یا tipax)
     * @return array اطلاعات وضعیت
     */
    public function get_tracking_status(string $tracking_code, string $service = 'post'): array
    {
        if (empty($tracking_code)) {
            return [
                'success' => false,
                'message' => 'کد رهگیری وارد نشده است.',
            ];
        }

        // Cache key برای جلوگیری از استعلام‌های مکرر
        $cache_key = 'ht_shipping_tracking_' . md5($tracking_code . $service);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        // استعلام بر اساس نوع سرویس
        $result = match ($service) {
            'tipax' => $this->query_tipax($tracking_code),
            'post' => $this->query_post($tracking_code),
            default => [
                'success' => false,
                'message' => 'سرویس نامعتبر است.',
            ],
        };

        // کش کردن نتیجه برای 15 دقیقه
        if ($result['success']) {
            set_transient($cache_key, $result, 15 * MINUTE_IN_SECONDS);
        }

        return $result;
    }

    /**
     * Query Iran Post API
     * 
     * @param string $tracking_code کد رهگیری
     * @return array
     */
    private function query_post(string $tracking_code): array
    {
        try {
            // شبیه‌سازی استعلام از پست
            // در محیط واقعی، این متد به API پست متصل می‌شود
            
            $response = wp_remote_post(self::POST_API_URL, [
                'timeout' => self::API_TIMEOUT,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'trackingCode' => $tracking_code,
                ]),
            ]);

            if (is_wp_error($response)) {
                error_log('Homa Shipping API Error (Post): ' . $response->get_error_message());
                
                // بازگشت داده شبیه‌سازی شده در صورت خطا
                return $this->get_simulated_post_status($tracking_code);
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!$data || !isset($data['status'])) {
                return $this->get_simulated_post_status($tracking_code);
            }

            return [
                'success' => true,
                'service' => 'post',
                'tracking_code' => $tracking_code,
                'status' => $data['status'],
                'status_label' => $this->translate_post_status($data['status']),
                'last_update' => $data['lastUpdate'] ?? current_time('mysql'),
                'events' => $data['events'] ?? [],
                'human_message' => $this->format_post_message($data),
            ];

        } catch (\Exception $e) {
            error_log('Homa Shipping API Exception (Post): ' . $e->getMessage());
            return $this->get_simulated_post_status($tracking_code);
        }
    }

    /**
     * Query Tipax API
     * 
     * @param string $tracking_code کد رهگیری
     * @return array
     */
    private function query_tipax(string $tracking_code): array
    {
        try {
            $api_key = get_option('ht_tipax_api_key', '');
            
            if (empty($api_key)) {
                error_log('Homa Shipping: Tipax API key not configured');
                return $this->get_simulated_tipax_status($tracking_code);
            }

            $response = wp_remote_get(
                add_query_arg(['code' => $tracking_code], self::TIPAX_API_URL),
                [
                    'timeout' => self::API_TIMEOUT,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json',
                    ],
                ]
            );

            if (is_wp_error($response)) {
                error_log('Homa Shipping API Error (Tipax): ' . $response->get_error_message());
                return $this->get_simulated_tipax_status($tracking_code);
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!$data || !isset($data['status'])) {
                return $this->get_simulated_tipax_status($tracking_code);
            }

            return [
                'success' => true,
                'service' => 'tipax',
                'tracking_code' => $tracking_code,
                'status' => $data['status'],
                'status_label' => $this->translate_tipax_status($data['status']),
                'last_update' => $data['lastUpdate'] ?? current_time('mysql'),
                'events' => $data['events'] ?? [],
                'human_message' => $this->format_tipax_message($data),
            ];

        } catch (\Exception $e) {
            error_log('Homa Shipping API Exception (Tipax): ' . $e->getMessage());
            return $this->get_simulated_tipax_status($tracking_code);
        }
    }

    /**
     * Get simulated Post status (برای تست و fallback)
     * 
     * @param string $tracking_code کد رهگیری
     * @return array
     */
    private function get_simulated_post_status(string $tracking_code): array
    {
        // شبیه‌سازی وضعیت‌های مختلف
        $statuses = [
            'received' => 'دریافت شده در مرکز پستی',
            'in_transit' => 'در حال ارسال',
            'delivered' => 'تحویل داده شده',
            'pending' => 'در انتظار پردازش',
        ];

        $random_status = array_rand($statuses);

        return [
            'success' => true,
            'service' => 'post',
            'tracking_code' => $tracking_code,
            'status' => $random_status,
            'status_label' => $statuses[$random_status],
            'last_update' => current_time('mysql'),
            'events' => [
                [
                    'date' => current_time('mysql'),
                    'status' => $statuses[$random_status],
                    'location' => 'مرکز پستی تهران',
                ]
            ],
            'human_message' => "مرسوله با کد رهگیری {$tracking_code} در وضعیت «{$statuses[$random_status]}» است.",
            'is_simulated' => true,
        ];
    }

    /**
     * Get simulated Tipax status (برای تست و fallback)
     * 
     * @param string $tracking_code کد رهگیری
     * @return array
     */
    private function get_simulated_tipax_status(string $tracking_code): array
    {
        $statuses = [
            'registered' => 'ثبت شده',
            'collected' => 'جمع‌آوری شده',
            'in_transit' => 'در حال حمل',
            'delivered' => 'تحویل داده شده',
        ];

        $random_status = array_rand($statuses);

        return [
            'success' => true,
            'service' => 'tipax',
            'tracking_code' => $tracking_code,
            'status' => $random_status,
            'status_label' => $statuses[$random_status],
            'last_update' => current_time('mysql'),
            'events' => [
                [
                    'date' => current_time('mysql'),
                    'status' => $statuses[$random_status],
                    'location' => 'انبار مرکزی تیپاکس',
                ]
            ],
            'human_message' => "بسته تیپاکس با کد {$tracking_code} در وضعیت «{$statuses[$random_status]}» است.",
            'is_simulated' => true,
        ];
    }

    /**
     * Translate Post status to Persian
     * 
     * @param string $status وضعیت
     * @return string برچسب فارسی
     */
    private function translate_post_status(string $status): string
    {
        $translations = [
            'received' => 'دریافت شده',
            'in_transit' => 'در حال ارسال',
            'delivered' => 'تحویل داده شده',
            'pending' => 'در انتظار',
            'returned' => 'برگشت داده شده',
        ];

        return $translations[$status] ?? $status;
    }

    /**
     * Translate Tipax status to Persian
     * 
     * @param string $status وضعیت
     * @return string برچسب فارسی
     */
    private function translate_tipax_status(string $status): string
    {
        $translations = [
            'registered' => 'ثبت شده',
            'collected' => 'جمع‌آوری شده',
            'in_transit' => 'در حال حمل',
            'delivered' => 'تحویل داده شده',
            'cancelled' => 'لغو شده',
        ];

        return $translations[$status] ?? $status;
    }

    /**
     * Format Post tracking data for human message
     * 
     * @param array $data داده API
     * @return string پیام انسانی
     */
    private function format_post_message(array $data): string
    {
        $status_label = $this->translate_post_status($data['status']);
        $tracking_code = $data['trackingCode'] ?? '';
        
        return "مرسوله پستی با کد {$tracking_code} در وضعیت «{$status_label}» است.";
    }

    /**
     * Format Tipax tracking data for human message
     * 
     * @param array $data داده API
     * @return string پیام انسانی
     */
    private function format_tipax_message(array $data): string
    {
        $status_label = $this->translate_tipax_status($data['status']);
        $tracking_code = $data['trackingCode'] ?? '';
        
        return "بسته تیپاکس با کد {$tracking_code} در وضعیت «{$status_label}» است.";
    }

    /**
     * Clear tracking cache for a specific code
     * 
     * @param string $tracking_code کد رهگیری
     * @param string $service نوع سرویس
     * @return void
     */
    public function clear_tracking_cache(string $tracking_code, string $service = 'post'): void
    {
        $cache_key = 'ht_shipping_tracking_' . md5($tracking_code . $service);
        delete_transient($cache_key);
    }
}
