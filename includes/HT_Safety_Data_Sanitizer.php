<?php
/**
 * Safety Data Sanitizer - Sensitive Data Filter
 *
 * @package HomayeTabesh
 * @since PR13
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * لایه امنیتی برای فیلتر کردن اطلاعات حساس
 * 
 * این کلاس قبل از ارسال هر داده‌ای به AI یا ذخیره در knowledge base،
 * اطمینان می‌دهد که اطلاعات حساس (API Keys، Passwords، etc) حذف شده‌اند.
 */
class HT_Safety_Data_Sanitizer
{
    /**
     * لیست کلمات کلیدی حساس
     */
    private const SENSITIVE_KEYWORDS = [
        'password',
        'passwd',
        'pwd',
        'api_key',
        'apikey',
        'secret',
        'token',
        'access_token',
        'refresh_token',
        'private_key',
        'public_key',
        'access_key',
        'secret_key',
        'auth',
        'authentication',
        'authorization',
        'credential',
        'salt',
        'hash',
        'session',
        'cookie',
        'csrf',
        'nonce',
        'license_key',
        'activation_key',
    ];

    /**
     * الگوهای regex برای شناسایی داده‌های حساس
     */
    private const SENSITIVE_PATTERNS = [
        // Credit card numbers (basic pattern)
        '/\b\d{4}[\s\-]?\d{4}[\s\-]?\d{4}[\s\-]?\d{4}\b/',
        // Email addresses
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        // IP addresses (private and public)
        '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
        // API keys (common formats)
        '/\b[A-Za-z0-9]{32,64}\b/',
        // JWT tokens
        '/eyJ[A-Za-z0-9_-]+\.eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/',
    ];

    /**
     * Check if a key contains sensitive information
     * 
     * @param string $key کلید
     * @return bool
     */
    public function is_sensitive_key(string $key): bool
    {
        $key_lower = strtolower($key);

        foreach (self::SENSITIVE_KEYWORDS as $keyword) {
            if (strpos($key_lower, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value contains sensitive data
     * 
     * @param mixed $value مقدار
     * @return bool
     */
    public function is_sensitive_value($value): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        $value_str = (string) $value;

        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (preg_match($pattern, $value_str)) {
                return true;
            }
        }

        // Check for long alphanumeric strings (likely keys/tokens)
        if (strlen($value_str) > 30 && preg_match('/^[A-Za-z0-9]+$/', $value_str)) {
            return true;
        }

        return false;
    }

    /**
     * Sanitize array by removing sensitive data
     * 
     * @param array $data داده‌ها
     * @return array داده‌های سانیتایز شده
     */
    public function sanitize_array(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // چک کردن کلید
            if ($this->is_sensitive_key($key)) {
                $sanitized[$key] = '[FILTERED]';
                continue;
            }

            // چک کردن مقدار
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_array($value);
            } elseif (is_object($value)) {
                $sanitized[$key] = '[OBJECT]';
            } elseif ($this->is_sensitive_value($value)) {
                $sanitized[$key] = '[FILTERED]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize text content
     * 
     * @param string $text متن
     * @return string متن سانیتایز شده
     */
    public function sanitize_text(string $text): string
    {
        $sanitized = $text;

        // حذف ایمیل‌ها
        $sanitized = preg_replace(
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
            '[EMAIL_FILTERED]',
            $sanitized
        );

        // حذف شماره کارت‌های احتمالی
        $sanitized = preg_replace(
            '/\b\d{4}[\s\-]?\d{4}[\s\-]?\d{4}[\s\-]?\d{4}\b/',
            '[CARD_FILTERED]',
            $sanitized
        );

        // حذف IP addresses
        $sanitized = preg_replace(
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            '[IP_FILTERED]',
            $sanitized
        );

        // حذف JWT tokens
        $sanitized = preg_replace(
            '/eyJ[A-Za-z0-9_-]+\.eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/',
            '[TOKEN_FILTERED]',
            $sanitized
        );

        return $sanitized;
    }

    /**
     * Sanitize metadata before sending to AI
     * 
     * @param array $metadata متادیتا
     * @return array متادیتای سانیتایز شده
     */
    public function sanitize_metadata(array $metadata): array
    {
        $sanitized = [];

        foreach ($metadata as $plugin_slug => $data) {
            $sanitized[$plugin_slug] = [
                'plugin_slug' => $plugin_slug,
                'extraction_time' => $data['extraction_time'] ?? current_time('mysql'),
            ];

            // Options - فیلتر کامل
            if (!empty($data['options'])) {
                $sanitized[$plugin_slug]['options'] = $this->sanitize_array($data['options']);
            }

            // Human-readable options - بررسی دقیق‌تر
            if (!empty($data['options_human'])) {
                $sanitized_human = [];
                foreach ($data['options_human'] as $key => $text) {
                    if ($this->is_sensitive_key($key)) {
                        continue; // حذف کامل
                    }
                    $sanitized_human[$key] = $this->sanitize_text($text);
                }
                $sanitized[$plugin_slug]['options_human'] = $sanitized_human;
            }

            // Tables - اسامی جداول مشکلی ندارند
            if (!empty($data['tables'])) {
                $sanitized[$plugin_slug]['tables'] = $data['tables'];
            }

            // Capabilities - امن
            if (!empty($data['capabilities'])) {
                $sanitized[$plugin_slug]['capabilities'] = $data['capabilities'];
            }

            // Facts - بررسی متن
            if (!empty($data['facts'])) {
                $sanitized_facts = [];
                foreach ($data['facts'] as $key => $value) {
                    if ($this->is_sensitive_key($key)) {
                        continue;
                    }
                    if (is_array($value)) {
                        $sanitized_facts[$key] = array_map([$this, 'sanitize_text'], $value);
                    } else {
                        $sanitized_facts[$key] = $this->sanitize_text((string)$value);
                    }
                }
                $sanitized[$plugin_slug]['facts'] = $sanitized_facts;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize context before sending to AI
     * 
     * @param string $context کانتکست
     * @return string کانتکست سانیتایز شده
     */
    public function sanitize_context(string $context): string
    {
        return $this->sanitize_text($context);
    }

    /**
     * Check if data is safe to send to AI
     * 
     * @param mixed $data داده
     * @return bool
     */
    public function is_safe_for_ai($data): bool
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($this->is_sensitive_key($key)) {
                    return false;
                }
                if (!$this->is_safe_for_ai($value)) {
                    return false;
                }
            }
            return true;
        }

        if (is_string($data) || is_numeric($data)) {
            return !$this->is_sensitive_value($data);
        }

        return true;
    }

    /**
     * Filter plugin options for safe extraction
     * 
     * @param array $options تنظیمات افزونه
     * @return array تنظیمات فیلتر شده
     */
    public function filter_plugin_options(array $options): array
    {
        $filtered = [];

        foreach ($options as $key => $value) {
            // حذف کامل option های حساس
            if ($this->is_sensitive_key($key)) {
                continue;
            }

            // بررسی مقدار
            if (is_array($value)) {
                $filtered[$key] = $this->sanitize_array($value);
            } elseif ($this->is_sensitive_value($value)) {
                continue; // حذف کامل
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Get sanitization report
     * 
     * @param array $original داده اصلی
     * @param array $sanitized داده سانیتایز شده
     * @return array گزارش
     */
    public function get_sanitization_report(array $original, array $sanitized): array
    {
        $original_count = $this->count_keys_recursive($original);
        $sanitized_count = $this->count_keys_recursive($sanitized);

        return [
            'original_keys' => $original_count,
            'sanitized_keys' => $sanitized_count,
            'filtered_keys' => $original_count - $sanitized_count,
            'filter_percentage' => $original_count > 0 
                ? round((($original_count - $sanitized_count) / $original_count) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Count keys recursively in array
     * 
     * @param array $array آرایه
     * @return int تعداد کلیدها
     */
    private function count_keys_recursive(array $array): int
    {
        $count = count($array);

        foreach ($array as $value) {
            if (is_array($value)) {
                $count += $this->count_keys_recursive($value);
            }
        }

        return $count;
    }

    /**
     * Validate API key format (for Gemini, OpenAI, etc.)
     * 
     * @param string $api_key کلید API
     * @return bool
     */
    public function is_valid_api_key_format(string $api_key): bool
    {
        // حداقل طول
        if (strlen($api_key) < 20) {
            return false;
        }

        // فرمت کلی: alphanumeric with possible dashes or underscores
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $api_key)) {
            return false;
        }

        return true;
    }

    /**
     * Mask sensitive data for display
     * 
     * @param string $data داده حساس
     * @param int $visible_chars تعداد کاراکترهای قابل نمایش
     * @return string داده ماسک شده
     */
    public function mask_sensitive_data(string $data, int $visible_chars = 4): string
    {
        $length = strlen($data);

        if ($length <= $visible_chars) {
            return str_repeat('*', $length);
        }

        $visible_part = substr($data, 0, $visible_chars);
        $masked_part = str_repeat('*', $length - $visible_chars);

        return $visible_part . $masked_part;
    }
}
