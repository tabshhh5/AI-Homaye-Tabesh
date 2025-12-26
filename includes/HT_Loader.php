<?php
/**
 * Safe Plugin Loader - Boot Shield Protection
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * Safe Loader with Dependency Checks
 * 
 * این کلاس مسئول لود امن افزونه با چک کردن وابستگی‌ها و
 * جلوگیری از کرش کل سایت است.
 */
class HT_Loader
{
    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Core instance
     */
    private ?HT_Core $core = null;

    /**
     * Boot status
     */
    private bool $booted = false;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Private constructor
     */
    private function __construct()
    {
        // Empty constructor - actual boot happens via boot()
    }

    /**
     * Boot the plugin with safety checks
     *
     * @return bool True if booted successfully, false otherwise
     */
    public function boot(): bool
    {
        // Prevent double boot
        if ($this->booted) {
            return true;
        }

        try {
            // Check critical dependencies
            if (!$this->check_dependencies()) {
                return false;
            }

            // Initialize core only after all checks pass
            $this->core = HT_Core::instance();
            $this->booted = true;

            return true;
        } catch (\Throwable $e) {
            // Log but don't crash
            $this->emergency_log('Boot failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if all required dependencies are available
     *
     * @return bool
     */
    private function check_dependencies(): bool
    {
        // WooCommerce is not strictly required, but if other code depends on it,
        // we should verify it's available before those classes are instantiated
        // For now, we'll just log if it's missing but still proceed
        if (!class_exists('WooCommerce')) {
            $this->emergency_log('WooCommerce not detected - some features may be limited');
        }

        // Check if core classes are available
        if (!class_exists('HomayeTabesh\HT_Core')) {
            $this->emergency_log('HT_Core class not found');
            return false;
        }

        return true;
    }

    /**
     * Emergency logging that doesn't rely on WordPress or error handlers
     * Uses pure PHP file operations with error suppression
     *
     * @param string $message
     * @return void
     */
    private function emergency_log(string $message): void
    {
        try {
            $log_file = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/homa-emergency-log.txt' : sys_get_temp_dir() . '/homa-emergency-log.txt';
            $timestamp = date('Y-m-d H:i:s');
            $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
            
            // Use file_put_contents with FILE_APPEND flag and error suppression
            @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            
            // Also use pure error_log as backup - never call HT_Error_Handler
            @error_log('[Homaye Tabesh - Loader] ' . $message);
        } catch (\Throwable $e) {
            // Absolute last resort - silently fail to prevent cascade
            // Any error here would be catastrophic, so we just abort
        }
    }

    /**
     * Get core instance if booted
     *
     * @return HT_Core|null
     */
    public function get_core(): ?HT_Core
    {
        return $this->core;
    }

    /**
     * Check if plugin is booted
     *
     * @return bool
     */
    public function is_booted(): bool
    {
        return $this->booted;
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \LogicException('Unserialization of HT_Loader is not allowed');
    }
}
