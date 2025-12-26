<?php
/**
 * Admin Settings Page
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * Admin settings and configuration
 */
class HT_Admin
{
    /**
     * Initialize admin hooks
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu(): void
    {
        add_menu_page(
            __('همای تابش', 'homaye-tabesh'),
            __('همای تابش', 'homaye-tabesh'),
            'manage_options',
            'homaye-tabesh',
            [$this, 'render_settings_page'],
            'dashicons-superhero',
            30
        );

        add_submenu_page(
            'homaye-tabesh',
            __('تنظیمات', 'homaye-tabesh'),
            __('تنظیمات', 'homaye-tabesh'),
            'manage_options',
            'homaye-tabesh',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'homaye-tabesh',
            __('آمار پرسونا', 'homaye-tabesh'),
            __('آمار پرسونا', 'homaye-tabesh'),
            'manage_options',
            'homaye-tabesh-personas',
            [$this, 'render_personas_page']
        );

        add_submenu_page(
            'homaye-tabesh',
            __('مرکز کنترل اطلس', 'homaye-tabesh'),
            __('🗺️ مرکز کنترل اطلس', 'homaye-tabesh'),
            'manage_options',
            'homaye-tabesh-atlas',
            [$this, 'render_atlas_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings(): void
    {
        register_setting('homaye_tabesh_settings', 'ht_gemini_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('homaye_tabesh_settings', 'ht_tracking_enabled', [
            'type' => 'boolean',
            'default' => true,
        ]);

        register_setting('homaye_tabesh_settings', 'ht_divi_integration', [
            'type' => 'boolean',
            'default' => true,
        ]);

        register_setting('homaye_tabesh_settings', 'ht_min_score_threshold', [
            'type' => 'integer',
            'default' => 50,
            'sanitize_callback' => 'absint',
        ]);

        // Add settings section
        add_settings_section(
            'ht_main_section',
            __('تنظیمات اصلی', 'homaye-tabesh'),
            null,
            'homaye-tabesh'
        );

        // Add settings fields
        add_settings_field(
            'ht_gemini_api_key',
            __('کلید API گوگل Gemini', 'homaye-tabesh'),
            [$this, 'render_api_key_field'],
            'homaye-tabesh',
            'ht_main_section'
        );

        add_settings_field(
            'ht_tracking_enabled',
            __('ردیابی رفتار', 'homaye-tabesh'),
            [$this, 'render_tracking_field'],
            'homaye-tabesh',
            'ht_main_section'
        );

        add_settings_field(
            'ht_divi_integration',
            __('یکپارچه‌سازی با Divi', 'homaye-tabesh'),
            [$this, 'render_divi_field'],
            'homaye-tabesh',
            'ht_main_section'
        );

        add_settings_field(
            'ht_min_score_threshold',
            __('حداقل امتیاز پرسونا', 'homaye-tabesh'),
            [$this, 'render_threshold_field'],
            'homaye-tabesh',
            'ht_main_section'
        );
    }

    /**
     * Render API key field
     */
    public function render_api_key_field(): void
    {
        $value = get_option('ht_gemini_api_key', '');
        ?>
        <input type="text" 
               id="ht_gemini_api_key" 
               name="ht_gemini_api_key" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               placeholder="AIza...">
        <p class="description">
            کلید API خود را از 
            <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a> 
            دریافت کنید.
        </p>
        <?php
    }

    /**
     * Render tracking field
     */
    public function render_tracking_field(): void
    {
        $value = get_option('ht_tracking_enabled', true);
        ?>
        <label>
            <input type="checkbox" 
                   name="ht_tracking_enabled" 
                   value="1" 
                   <?php checked($value); ?>>
            فعال‌سازی ردیابی رفتار کاربران
        </label>
        <?php
    }

    /**
     * Render Divi integration field
     */
    public function render_divi_field(): void
    {
        $value = get_option('ht_divi_integration', true);
        ?>
        <label>
            <input type="checkbox" 
                   name="ht_divi_integration" 
                   value="1" 
                   <?php checked($value); ?>>
            فعال‌سازی ردیابی خودکار المان‌های Divi
        </label>
        <?php
    }

    /**
     * Render threshold field
     */
    public function render_threshold_field(): void
    {
        $value = get_option('ht_min_score_threshold', 50);
        ?>
        <input type="number" 
               id="ht_min_score_threshold" 
               name="ht_min_score_threshold" 
               value="<?php echo esc_attr($value); ?>" 
               min="0" 
               max="1000"
               step="10">
        <p class="description">
            حداقل امتیازی که یک کاربر باید کسب کند تا پرسونا شناسایی شود.
        </p>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap homaye-tabesh-admin">
            <h1><?php echo esc_html__('تنظیمات همای تابش', 'homaye-tabesh'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('homaye_tabesh_settings');
                do_settings_sections('homaye-tabesh');
                submit_button('ذخیره تنظیمات');
                ?>
            </form>

            <hr>

            <h2>وضعیت سیستم</h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong>نسخه PHP:</strong></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>نسخه WordPress:</strong></td>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>قالب فعال:</strong></td>
                        <td><?php echo wp_get_theme()->get('Name'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>WooCommerce:</strong></td>
                        <td><?php echo class_exists('WooCommerce') ? '✓ نصب شده' : '✗ نصب نشده'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Divi Theme:</strong></td>
                        <td><?php 
                            $theme = wp_get_theme();
                            echo ($theme->get('Name') === 'Divi' || $theme->get('Template') === 'Divi') ? '✓ فعال' : '✗ غیرفعال'; 
                        ?></td>
                    </tr>
                    <tr>
                        <td><strong>API Key تنظیم شده:</strong></td>
                        <td><?php echo !empty(get_option('ht_gemini_api_key')) ? '✓ بله' : '✗ خیر'; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render personas statistics page
     */
    public function render_personas_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'homaye_persona_scores';

        // Get statistics
        $stats = $wpdb->get_results(
            "SELECT persona_type, COUNT(*) as count, AVG(score) as avg_score, MAX(score) as max_score 
             FROM $table_name 
             GROUP BY persona_type 
             ORDER BY count DESC"
        );

        ?>
        <div class="wrap homaye-tabesh-admin">
            <h1><?php echo esc_html__('آمار پرسونا', 'homaye-tabesh'); ?></h1>
            
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>نوع پرسونا</th>
                        <th>تعداد کاربران</th>
                        <th>میانگین امتیاز</th>
                        <th>بیشترین امتیاز</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stats)): ?>
                        <tr>
                            <td colspan="4">هنوز داده‌ای وجود ندارد.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stats as $stat): ?>
                            <tr>
                                <td><strong><?php echo esc_html($stat->persona_type); ?></strong></td>
                                <td><?php echo esc_html($stat->count); ?></td>
                                <td><?php echo round($stat->avg_score, 2); ?></td>
                                <td>
                                    <span class="homaye-tabesh-persona-score <?php 
                                        echo $stat->max_score >= 100 ? 'high' : ($stat->max_score >= 50 ? 'medium' : 'low'); 
                                    ?>">
                                        <?php echo esc_html($stat->max_score); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2>رویدادهای اخیر</h2>
            <?php
            $events_table = $wpdb->prefix . 'homaye_telemetry_events';
            $recent_events = $wpdb->get_results(
                "SELECT * FROM $events_table ORDER BY timestamp DESC LIMIT 20"
            );
            ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>شناسه کاربر</th>
                        <th>نوع رویداد</th>
                        <th>کلاس المان</th>
                        <th>زمان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_events)): ?>
                        <tr>
                            <td colspan="4">هنوز رویدادی ثبت نشده است.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_events as $event): ?>
                            <tr>
                                <td><?php echo esc_html(substr($event->user_identifier, 0, 20)); ?>...</td>
                                <td><?php echo esc_html($event->event_type); ?></td>
                                <td><?php echo esc_html($event->element_class); ?></td>
                                <td><?php echo esc_html($event->timestamp); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render Atlas Control Center page
     */
    public function render_atlas_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Enqueue Atlas React app
        wp_enqueue_script(
            'atlas-dashboard',
            HT_PLUGIN_URL . 'assets/build/atlas-dashboard.js',
            ['wp-element'],
            HT_VERSION,
            true
        );

        wp_enqueue_style(
            'atlas-dashboard',
            HT_PLUGIN_URL . 'assets/css/atlas-dashboard.css',
            [],
            HT_VERSION
        );

        // Localize script with API endpoints
        wp_localize_script('atlas-dashboard', 'atlasConfig', [
            'apiUrl' => rest_url('homaye/v1/atlas'),
            'nonce' => wp_create_nonce('wp_rest'),
            'userRole' => current_user_can('administrator') ? 'administrator' : 'manager',
        ]);

        ?>
        <div class="wrap homaye-tabesh-atlas">
            <h1><?php echo esc_html__('🗺️ مرکز کنترل اطلس (Atlas Control Center)', 'homaye-tabesh'); ?></h1>
            <p class="description">
                <?php echo esc_html__('سیستم هوش تجاری و موتور تصمیم‌گیری داده‌محور', 'homaye-tabesh'); ?>
            </p>
            <div id="atlas-dashboard-root"></div>
        </div>
        <?php
    }
}
