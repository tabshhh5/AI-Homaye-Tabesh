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
        ]);
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings
        if (isset($_POST['submit']) && check_admin_referer('homaye_tabesh_settings')) {
            update_option('ht_gemini_api_key', sanitize_text_field($_POST['ht_gemini_api_key'] ?? ''));
            update_option('ht_tracking_enabled', isset($_POST['ht_tracking_enabled']));
            update_option('ht_divi_integration', isset($_POST['ht_divi_integration']));
            update_option('ht_min_score_threshold', absint($_POST['ht_min_score_threshold'] ?? 50));

            echo '<div class="notice notice-success"><p>تنظیمات با موفقیت ذخیره شد.</p></div>';
        }

        $api_key = get_option('ht_gemini_api_key', '');
        $tracking_enabled = get_option('ht_tracking_enabled', true);
        $divi_integration = get_option('ht_divi_integration', true);
        $min_threshold = get_option('ht_min_score_threshold', 50);
        ?>
        <div class="wrap homaye-tabesh-admin">
            <h1><?php echo esc_html__('تنظیمات همای تابش', 'homaye-tabesh'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('homaye_tabesh_settings'); ?>
                
                <table class="form-table homaye-tabesh-settings">
                    <tr>
                        <th scope="row">
                            <label for="ht_gemini_api_key">کلید API گوگل Gemini</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="ht_gemini_api_key" 
                                   name="ht_gemini_api_key" 
                                   value="<?php echo esc_attr($api_key); ?>" 
                                   class="regular-text"
                                   placeholder="AIza...">
                            <p class="description">
                                کلید API خود را از 
                                <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a> 
                                دریافت کنید.
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">ردیابی رفتار</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="ht_tracking_enabled" 
                                       value="1" 
                                       <?php checked($tracking_enabled); ?>>
                                فعال‌سازی ردیابی رفتار کاربران
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">یکپارچه‌سازی با Divi</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="ht_divi_integration" 
                                       value="1" 
                                       <?php checked($divi_integration); ?>>
                                فعال‌سازی ردیابی خودکار المان‌های Divi
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ht_min_score_threshold">حداقل امتیاز پرسونا</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="ht_min_score_threshold" 
                                   name="ht_min_score_threshold" 
                                   value="<?php echo esc_attr($min_threshold); ?>" 
                                   min="0" 
                                   max="1000"
                                   step="10">
                            <p class="description">
                                حداقل امتیازی که یک کاربر باید کسب کند تا پرسونا شناسایی شود.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('ذخیره تنظیمات'); ?>
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
                        <td><?php echo !empty($api_key) ? '✓ بله' : '✗ خیر'; ?></td>
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
}
