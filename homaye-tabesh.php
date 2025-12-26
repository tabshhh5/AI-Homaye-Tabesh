<?php
/**
 * Plugin Name: همای تابش (Homaye Tabesh)
 * Plugin URI: https://github.com/tabshhh4-sketch/AI-Homaye-Tabesh
 * Description: هاب هوشمند هماهنگی، تصمیم‌گیری و راهنمایی تمام فرآیندهای کاربران وبسایت با استفاده از Gemini 2.5 Flash و ردیابی رفتاری کاربران در قالب Divi
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Author: Tabshhh4
 * Author URI: https://github.com/tabshhh4-sketch
 * Text Domain: homaye-tabesh
 * Domain Path: /languages
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HT_VERSION', '1.0.0');
define('HT_PLUGIN_FILE', __FILE__);
define('HT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check PHP version requirement
if (version_compare(PHP_VERSION, '8.2', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('همای تابش نیاز به PHP نسخه 8.2 یا بالاتر دارد.', 'homaye-tabesh');
        echo '</p></div>';
    });
    return;
}

// Load autoloader - use Composer's if available, otherwise use fallback
try {
    if (file_exists(HT_PLUGIN_DIR . 'vendor/autoload.php')) {
        require_once HT_PLUGIN_DIR . 'vendor/autoload.php';
    } else {
        require_once HT_PLUGIN_DIR . 'includes/autoload.php';
    }
} catch (\Throwable $e) {
    error_log('[Homaye Tabesh - Autoload Error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    add_action('admin_notices', function () use ($e) {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . esc_html__('خطای همای تابش:', 'homaye-tabesh') . '</strong> ';
        echo esc_html__('خطا در بارگذاری autoloader. لطفاً افزونه را مجدداً نصب کنید.', 'homaye-tabesh');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<br><small>' . esc_html($e->getMessage()) . '</small>';
        }
        echo '</p></div>';
    });
    return;
}

// Verify core classes are available
if (!class_exists('HomayeTabesh\HT_Core')) {
    add_action('admin_notices', function () {
        static $notice_shown = false;
        if ($notice_shown) {
            return;
        }
        $notice_shown = true;
        
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . esc_html__('خطای همای تابش:', 'homaye-tabesh') . '</strong> ';
        echo esc_html__('فایلهای هسته یافت نشدند. لطفاً افزونه را مجدداً نصب کنید یا از نسخه Release استفاده کنید.', 'homaye-tabesh');
        echo '</p></div>';
    });
    return;
}

// Initialize the plugin
add_action('plugins_loaded', function () {
    try {
        \HomayeTabesh\HT_Core::instance();
    } catch (\Throwable $e) {
        // Log the error - use native error_log as fallback if HT_Error_Handler not available
        if (class_exists('\HomayeTabesh\HT_Error_Handler')) {
            \HomayeTabesh\HT_Error_Handler::log_exception($e, 'plugin_init');
            
            // Display admin notice
            \HomayeTabesh\HT_Error_Handler::admin_notice(
                sprintf(
                    __('خطا در راه‌اندازی افزونه: %s', 'homaye-tabesh'),
                    $e->getMessage()
                )
            );
        } else {
            error_log('[Homaye Tabesh - plugin_init] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        
        // Prevent further execution but don't crash the site
        return;
    }
}, 10);

// Activation hook
register_activation_hook(__FILE__, function () {
    try {
        if (!class_exists('HomayeTabesh\HT_Activator')) {
            // Use native error_log as HT_Error_Handler may not be available yet
            error_log('[Homaye Tabesh - activation] HT_Activator class not found during activation');
            wp_die(
                esc_html__('افزونه همای تابش به درستی نصب نشده است. لطفاً از نسخه Release استفاده کنید یا دستورات نصب را دنبال کنید.', 'homaye-tabesh'),
                esc_html__('خطای نصب افزونه', 'homaye-tabesh'),
                ['back_link' => true]
            );
        }
        \HomayeTabesh\HT_Activator::activate();
    } catch (\Throwable $e) {
        // Use HT_Error_Handler if available, otherwise fallback to error_log
        if (class_exists('\HomayeTabesh\HT_Error_Handler')) {
            \HomayeTabesh\HT_Error_Handler::log_exception($e, 'activation');
        } else {
            error_log('[Homaye Tabesh - activation] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        
        wp_die(
            sprintf(
                esc_html__('خطا در فعال‌سازی افزونه همای تابش: %s', 'homaye-tabesh'),
                $e->getMessage()
            ),
            esc_html__('خطای فعال‌سازی', 'homaye-tabesh'),
            ['back_link' => true]
        );
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    try {
        if (class_exists('HomayeTabesh\HT_Deactivator')) {
            \HomayeTabesh\HT_Deactivator::deactivate();
        }
    } catch (\Throwable $e) {
        // Use HT_Error_Handler if available, otherwise fallback to error_log
        if (class_exists('\HomayeTabesh\HT_Error_Handler')) {
            \HomayeTabesh\HT_Error_Handler::log_exception($e, 'deactivation');
        } else {
            error_log('[Homaye Tabesh - deactivation] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        // Don't wp_die on deactivation to allow users to deactivate broken plugins
    }
});
