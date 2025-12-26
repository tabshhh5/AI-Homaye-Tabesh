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
     * Recursion depth counter - tracks nesting level of error handling calls
     * This is a multi-layered safety mechanism to prevent stack overflow
     * 
     * Unlike a simple boolean flag, this counter allows us to:
     * 1. Detect ANY level of nesting, not just immediate recursion
     * 2. Set a maximum depth threshold before aborting
     * 3. Track and log recursion patterns for debugging
     * 
     * The counter is incremented at method entry and decremented at exit.
     * If depth exceeds MAX_RECURSION_DEPTH, all logging is immediately aborted.
     */
    private static int $recursion_depth = 0;
    
    /**
     * Maximum allowed recursion depth
     * If error logging calls exceed this depth, emergency abort is triggered
     */
    private const MAX_RECURSION_DEPTH = 2;
    
    /**
     * Emergency flag - set when critical failure is detected
     * When true, ALL error handling is disabled to prevent cascade failures
     */
    private static bool $emergency_mode = false;

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
        // EMERGENCY ABORT: If emergency mode is active, do nothing
        if (self::$emergency_mode) {
            return;
        }
        
        // RECURSION DEPTH CHECK: Increment depth counter
        self::$recursion_depth++;
        
        // If depth exceeds maximum, trigger emergency mode and abort
        if (self::$recursion_depth > self::MAX_RECURSION_DEPTH) {
            self::$emergency_mode = true;
            self::$recursion_depth = 0;
            
            // Use absolute minimal emergency logging - no function calls, no formatting
            @error_log('[Homaye Tabesh - EMERGENCY] Recursion depth exceeded in error handler - aborting all error logging');
            return;
        }

        try {
            // Build log message using only safe operations
            $log_message = '[Homaye Tabesh - ' . $context . '] ' . $message;

            // Only add data if provided and depth is 1 (avoid complex operations in nested calls)
            if ($data !== null && self::$recursion_depth === 1) {
                $log_message .= ' | Data: ' . self::safe_format_data($data);
            }

            // Use pure PHP error_log - no WordPress functions, no custom handlers
            @error_log($log_message);
        } catch (\Throwable $e) {
            // If ANY error occurs, enter emergency mode immediately
            self::$emergency_mode = true;
            // Last-resort logging with no dependencies
            @error_log('[Homaye Tabesh - CRITICAL] Error handler itself failed');
        } finally {
            // Always decrement depth counter
            self::$recursion_depth--;
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
        // EMERGENCY ABORT: If emergency mode is active, do nothing
        if (self::$emergency_mode) {
            return;
        }
        
        // RECURSION DEPTH CHECK: Increment depth counter
        self::$recursion_depth++;
        
        // If depth exceeds maximum, trigger emergency mode and abort
        if (self::$recursion_depth > self::MAX_RECURSION_DEPTH) {
            self::$emergency_mode = true;
            self::$recursion_depth = 0;
            
            // Use absolute minimal emergency logging
            @error_log('[Homaye Tabesh - EMERGENCY] Recursion depth exceeded in exception handler');
            return;
        }

        try {
            // Build message using only safe string operations - no sprintf, no complex calls
            $message = '[Homaye Tabesh - ' . $context . '] Exception: ' . $exception->getMessage() 
                     . ' in ' . $exception->getFile() . ':' . $exception->getLine();

            // Only add trace if at depth 1 to avoid expensive operations in nested calls
            if (self::$recursion_depth === 1) {
                $message .= ' | Trace: ' . $exception->getTraceAsString();
            }
            
            // Use pure PHP error_log with error suppression
            @error_log($message);
        } catch (\Throwable $e) {
            // If ANY error occurs, enter emergency mode
            self::$emergency_mode = true;
            @error_log('[Homaye Tabesh - CRITICAL] Exception handler itself failed');
        } finally {
            // Always decrement depth counter
            self::$recursion_depth--;
        }
    }

    /**
     * Format data for logging (simplified and safe version)
     *
     * @param mixed $data Data to format
     * @return string Formatted data string
     */
    private static function safe_format_data($data): string
    {
        // Emergency mode - return minimal string
        if (self::$emergency_mode) {
            return '[emergency-mode]';
        }
        
        try {
            // Use simple type checking and formatting
            if (is_string($data)) {
                return $data;
            }
            
            if (is_numeric($data)) {
                return (string) $data;
            }
            
            if (is_bool($data)) {
                return $data ? 'true' : 'false';
            }
            
            if (is_null($data)) {
                return 'null';
            }
            
            // For arrays and objects, use json_encode with error suppression
            if (is_array($data) || is_object($data)) {
                $encoded = @json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
                return $encoded !== false ? $encoded : '[json-encode-failed]';
            }
            
            // Fallback for unknown types
            return '[unknown-type]';
        } catch (\Throwable $e) {
            // If formatting fails, enter emergency mode
            self::$emergency_mode = true;
            return '[format-failed]';
        }
    }

    /**
     * Display admin notice for critical errors
     * 
     * NOTE: This method does NOT log errors to prevent recursion
     * It only schedules an admin notice to be displayed
     *
     * @param string $message Error message to display
     * @param string $type Notice type (error, warning, info)
     * @return void
     */
    public static function admin_notice(string $message, string $type = 'error'): void
    {
        // Never call error logging from admin_notice to prevent recursion
        // This is a display-only function
        
        try {
            add_action('admin_notices', function () use ($message, $type) {
                // Use simple string concatenation instead of printf to avoid errors
                echo '<div class="notice notice-' . esc_attr($type) . '"><p>'
                   . '<strong>' . esc_html__('همای تابش', 'homaye-tabesh') . ':</strong> '
                   . esc_html($message)
                   . '</p></div>';
            });
        } catch (\Throwable $e) {
            // Silently fail - don't log here to prevent recursion
            // This is a display-only function, failure is not critical
        }
    }

    /**
     * Safe wrapper for executing code with error handling
     * 
     * NOTE: Errors from the callback are logged, which could trigger recursion
     * if the callback itself involves error logging. Use with caution.
     *
     * @param callable $callback Function to execute
     * @param string $context Context for error logging
     * @param mixed $default Default value to return on error
     * @return mixed Result of callback or default value on error
     */
    public static function safe_execute(callable $callback, string $context = 'general', $default = null)
    {
        // Don't execute if in emergency mode
        if (self::$emergency_mode) {
            return $default;
        }
        
        try {
            return $callback();
        } catch (\Throwable $e) {
            // This call is safe because log_exception has its own recursion protection
            self::log_exception($e, $context);
            return $default;
        }
    }
    
    /**
     * Check if error handler is in emergency mode
     * 
     * @return bool True if emergency mode is active
     */
    public static function is_emergency_mode(): bool
    {
        return self::$emergency_mode;
    }
    
    /**
     * Reset emergency mode
     * 
     * WARNING: This method should ONLY be used in these scenarios:
     * 1. Unit testing - to reset state between tests
     * 2. Manual recovery - by system administrator after investigating root cause
     * 
     * DO NOT call this in production code as it could mask recurring issues.
     * Emergency mode is automatically reset on the next request.
     * 
     * @internal This method is for testing and manual recovery only
     * @return void
     */
    public static function reset_emergency_mode(): void
    {
        // Log the reset for audit trail
        @error_log('[Homaye Tabesh - WARNING] Emergency mode manually reset - investigate root cause');
        
        self::$emergency_mode = false;
        self::$recursion_depth = 0;
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
