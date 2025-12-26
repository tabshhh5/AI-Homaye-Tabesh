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
if (file_exists(HT_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once HT_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    require_once HT_PLUGIN_DIR . 'includes/autoload.php';
}

// Verify core classes are available
if (!class_exists('HomayeTabesh\HT_Core')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . esc_html__('خطای همای تابش:', 'homaye-tabesh') . '</strong> ';
        echo esc_html__('فایلهای هسته یافت نشدند. لطفاً افزونه را مجدداً نصب کنید یا از نسخه Release استفاده کنید.', 'homaye-tabesh');
        echo '</p></div>';
    });
    return;
}

// Initialize the plugin
add_action('plugins_loaded', function () {
    \HomayeTabesh\HT_Core::instance();
}, 10);

// Activation hook
register_activation_hook(__FILE__, function () {
    if (!class_exists('HomayeTabesh\HT_Activator')) {
        wp_die(
            esc_html__('افزونه همای تابش به درستی نصب نشده است. لطفاً از نسخه Release استفاده کنید یا دستورات نصب را دنبال کنید.', 'homaye-tabesh'),
            esc_html__('خطای نصب افزونه', 'homaye-tabesh'),
            ['back_link' => true]
        );
    }
    \HomayeTabesh\HT_Activator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    if (class_exists('HomayeTabesh\HT_Deactivator')) {
        \HomayeTabesh\HT_Deactivator::deactivate();
    }
});
