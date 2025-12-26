<?php
/**
 * Error Handler and Logger
 *
 * Centralized error handling and logging for the Homaye Tabesh plugin.
 * Ensures all errors are logged to WordPress debug.log and prevents
 * fatal errors from crashing the entire site.
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * Centralized error handler for the plugin
 */
class HT_Error_Handler
{
    /**
     * Recursion guard - prevents infinite loops in error handling
     */
    private static bool $is_processing = false;

    /**
     * Log an error message to WordPress debug.log
     *
     * @param string $message Error message
     * @param string $context Context (e.g., 'activation', 'init', 'core')
     * @param mixed $data Additional data to log (optional)
     * @return void
     */
    public static function log_error(string $message, string $context = 'general', $data = null): void
    {
        // Prevent infinite recursion in error logging
        if (self::$is_processing) {
            return;
        }

        self::$is_processing = true;

        try {
            $log_message = sprintf(
                '[Homaye Tabesh - %s] %s',
                $context,
                $message
            );

            if ($data !== null) {
                $log_message .= ' | Data: ' . self::format_data($data);
            }

            // Use WordPress error_log - only log once
            error_log($log_message);
        } finally {
            self::$is_processing = false;
        }
    }

    /**
     * Log an exception
     *
     * @param \Throwable $exception Exception to log
     * @param string $context Context where exception occurred
     * @return void
     */
    public static function log_exception(\Throwable $exception, string $context = 'general'): void
    {
        // Prevent infinite recursion
        if (self::$is_processing) {
            return;
        }

        self::$is_processing = true;

        try {
            $message = sprintf(
                'Exception in %s: %s in %s:%d',
                $context,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );

            // Temporarily release lock to allow log_error to work
            self::$is_processing = false;
            
            self::log_error($message, $context, [
                'trace' => $exception->getTraceAsString()
            ]);
        } finally {
            self::$is_processing = false;
        }
    }

    /**
     * Format data for logging
     *
     * @param mixed $data Data to format
     * @return string Formatted data string
     */
    private static function format_data($data): string
    {
        if (is_array($data) || is_object($data)) {
            $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $encoded !== false ? $encoded : 'JSON_ENCODE_ERROR';
        }

        return (string) $data;
    }

    /**
     * Display admin notice for critical errors
     *
     * @param string $message Error message to display
     * @param string $type Notice type (error, warning, info)
     * @return void
     */
    public static function admin_notice(string $message, string $type = 'error'): void
    {
        add_action('admin_notices', function () use ($message, $type) {
            printf(
                '<div class="notice notice-%s"><p><strong>%s:</strong> %s</p></div>',
                esc_attr($type),
                esc_html__('همای تابش', 'homaye-tabesh'),
                esc_html($message)
            );
        });
    }

    /**
     * Safe wrapper for executing code with error handling
     *
     * @param callable $callback Function to execute
     * @param string $context Context for error logging
     * @param mixed $default Default value to return on error
     * @return mixed Result of callback or default value on error
     */
    public static function safe_execute(callable $callback, string $context = 'general', $default = null)
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            self::log_exception($e, $context);
            return $default;
        }
    }

    /**
     * Check if WordPress debugging is enabled
     *
     * @return bool True if debugging is enabled
     */
    public static function is_debug_enabled(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Check if WordPress debug logging is enabled
     *
     * @return bool True if debug logging is enabled
     */
    public static function is_debug_log_enabled(): bool
    {
        return defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
    }
}
