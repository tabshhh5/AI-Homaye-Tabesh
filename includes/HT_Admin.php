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
        add_action('wp_ajax_test_gapgpt_connection', [$this, 'test_gapgpt_connection']);
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

        add_submenu_page(
            'homaye-tabesh',
            __('ناظر کل افزونه‌ها', 'homaye-tabesh'),
            __('🔍 ناظر کل', 'homaye-tabesh'),
            'manage_options',
            'homaye-tabesh-observer',
            [$this, 'render_observer_page']
        );

        add_submenu_page(
            'homaye-tabesh',
            __('مرکز امنیت - هما گاردین', 'homaye-tabesh'),
            __('🛡️ مرکز امنیت', 'homaye-tabesh'),
            'manage_options',
            'homaye-tabesh-security',
            [$this, 'render_security_page']
        );

        add_submenu_page(
            'homaye-tabesh',
            __('سوپر پنل هما', 'homaye-tabesh'),
            __('🎛️ سوپر پنل', 'homaye-tabesh'),
            'manage_options',
            'homaye-tabesh-super-console',
            [$this, 'render_super_console_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings(): void
    {
        // Ensure database tables exist before loading settings
        if (class_exists('\HomayeTabesh\HT_Activator')) {
            \HomayeTabesh\HT_Activator::ensure_tables_exist();
        }

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

        // Smart Diplomacy settings (PR14)
        register_setting('homaye_tabesh_settings', 'ht_translation_enabled', [
            'type' => 'boolean',
            'default' => true,
        ]);

        register_setting('homaye_tabesh_settings', 'ht_show_translation_popup', [
            'type' => 'boolean',
            'default' => true,
        ]);

        register_setting('homaye_tabesh_settings', 'ht_auto_translate_arabic_visitors', [
            'type' => 'boolean',
            'default' => false,
        ]);

        register_setting('homaye_tabesh_settings', 'ht_arabic_countries', [
            'type' => 'array',
            'default' => HT_GeoLocation_Service::get_default_arabic_countries(),
        ]);

        // Knowledge Base indexing settings (PR21)
        register_setting('homaye_tabesh_settings', 'ht_index_post_types', [
            'type' => 'array',
            'default' => ['post', 'page', 'product'],
            'sanitize_callback' => function($value) {
                if (!is_array($value)) {
                    return ['post', 'page', 'product'];
                }
                return array_map('sanitize_text_field', $value);
            },
        ]);

        register_setting('homaye_tabesh_settings', 'ht_auto_index_enabled', [
            'type' => 'boolean',
            'default' => true,
        ]);

        // Global AI Configuration settings (GapGPT)
        register_setting('homaye_tabesh_settings', 'ht_ai_provider', [
            'type' => 'string',
            'default' => 'gapgpt',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('homaye_tabesh_settings', 'ht_ai_model', [
            'type' => 'string',
            'default' => 'gemini-2.5-flash',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('homaye_tabesh_settings', 'ht_gapgpt_base_url', [
            'type' => 'string',
            'default' => 'https://api.gapgpt.app/v1',
            'sanitize_callback' => 'esc_url_raw',
        ]);

        register_setting('homaye_tabesh_settings', 'ht_gapgpt_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        // Add settings sections
        add_settings_section(
            'ht_ai_config_section',
            __('پیکربندی سراسری هوش مصنوعی', 'homaye-tabesh'),
            [$this, 'render_ai_config_section_info'],
            'homaye-tabesh'
        );

        add_settings_section(
            'ht_main_section',
            __('تنظیمات اصلی', 'homaye-tabesh'),
            null,
            'homaye-tabesh'
        );

        // Add AI Configuration fields
        add_settings_field(
            'ht_ai_provider',
            __('انتخاب سرویس‌دهنده', 'homaye-tabesh'),
            [$this, 'render_ai_provider_field'],
            'homaye-tabesh',
            'ht_ai_config_section'
        );

        add_settings_field(
            'ht_ai_model',
            __('انتخاب مدل هوشمند', 'homaye-tabesh'),
            [$this, 'render_ai_model_field'],
            'homaye-tabesh',
            'ht_ai_config_section'
        );

        add_settings_field(
            'ht_gapgpt_base_url',
            __('آدرس پایه API', 'homaye-tabesh'),
            [$this, 'render_gapgpt_base_url_field'],
            'homaye-tabesh',
            'ht_ai_config_section'
        );

        add_settings_field(
            'ht_gapgpt_api_key',
            __('کلید API GapGPT', 'homaye-tabesh'),
            [$this, 'render_gapgpt_api_key_field'],
            'homaye-tabesh',
            'ht_ai_config_section'
        );

        // Add settings fields
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

        // Knowledge Base indexing fields (PR21)
        add_settings_field(
            'ht_auto_index_enabled',
            __('ایندکس خودکار محتوا', 'homaye-tabesh'),
            [$this, 'render_auto_index_field'],
            'homaye-tabesh',
            'ht_main_section'
        );

        add_settings_field(
            'ht_index_post_types',
            __('نوع محتوای قابل ایندکس', 'homaye-tabesh'),
            [$this, 'render_index_post_types_field'],
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
        <div style="display: flex; gap: 10px; align-items: flex-start;">
            <div style="flex: 1;">
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
            </div>
            <?php if (!empty($value)): ?>
            <button type="button" 
                    id="test-gemini-connection" 
                    class="button button-secondary"
                    style="white-space: nowrap;">
                🔍 تست اتصال
            </button>
            <?php endif; ?>
        </div>
        <div id="test-connection-result" style="margin-top: 10px;"></div>
        <script>
        jQuery(document).ready(function($) {
            $('#test-gemini-connection').on('click', function() {
                var button = $(this);
                var result = $('#test-connection-result');
                
                button.prop('disabled', true).text('در حال تست...');
                result.html('<div class="notice notice-info inline"><p>در حال اتصال به Gemini API...</p></div>');
                
                $.ajax({
                    url: '<?php echo esc_url(rest_url('homaye/v1/test-gemini')); ?>',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html(
                                '<div class="notice notice-success inline"><p>' +
                                '<strong>✅ موفق:</strong> ' + response.message +
                                '<br><small>زمان پاسخ: ' + response.data.duration_ms + ' میلی‌ثانیه</small>' +
                                '</p></div>'
                            );
                        } else {
                            result.html(
                                '<div class="notice notice-error inline"><p>' +
                                '<strong>❌ خطا:</strong> ' + response.message +
                                (response.error ? '<br><small>' + response.error + '</small>' : '') +
                                '</p></div>'
                            );
                        }
                    },
                    error: function(xhr) {
                        var errorMsg = 'خطای ارتباطی با سرور';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        result.html(
                            '<div class="notice notice-error inline"><p>' +
                            '<strong>❌ خطا:</strong> ' + errorMsg +
                            '</p></div>'
                        );
                    },
                    complete: function() {
                        button.prop('disabled', false).text('🔍 تست اتصال');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render AI configuration section info
     */
    public function render_ai_config_section_info(): void
    {
        ?>
        <p class="description">
            تنظیم مدل هوش مصنوعی برای تمام عملیات «هما» از طریق GapGPT API.
            GapGPT دسترسی به طیف گسترده‌ای از مدل‌های هوش مصنوعی از شرکت‌های مختلف را فراهم می‌کند.
        </p>
        <?php
    }

    /**
     * Render AI provider selection field
     */
    public function render_ai_provider_field(): void
    {
        // Set the provider to gapgpt (hidden, for backward compatibility)
        ?>
        <input type="hidden" id="ht_ai_provider" name="ht_ai_provider" value="gapgpt">
        <div class="notice notice-info inline" style="margin: 0; padding: 10px;">
            <p style="margin: 0;">
                <strong>🔌 GapGPT API</strong> - دروازه یکپارچه به مدل‌های هوش مصنوعی<br>
                <small>سازگار با OpenAI API و دسترسی به مدل‌های OpenAI، Google Gemini، Anthropic Claude، DeepSeek، XAI و بیشتر</small>
            </p>
        </div>
        <?php
    }

    /**
     * Render AI model selection field
     */
    public function render_ai_model_field(): void
    {
        $value = get_option('ht_ai_model', 'gemini-2.5-flash');
        
        // Models organized by provider
        $model_groups = [
            'Google Gemini' => [
                'gemini-2.5-flash' => 'Gemini 2.5 Flash (توصیه شده)',
                'gemini-2.5-pro' => 'Gemini 2.5 Pro',
                'gemini-2.0-flash' => 'Gemini 2.0 Flash',
                'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite',
                'gemini-3-pro-preview' => 'Gemini 3 Pro Preview',
            ],
            'OpenAI' => [
                'gpt-4o' => 'GPT-4o',
                'gpt-4o-mini' => 'GPT-4o Mini',
                'chatgpt-4o-latest' => 'ChatGPT-4o Latest',
                'o1' => 'O1',
                'o1-mini' => 'O1 Mini',
                'o3-mini' => 'O3 Mini',
                'o3-mini-high' => 'O3 Mini High',
                'o3-mini-low' => 'O3 Mini Low',
                'o4-mini' => 'O4 Mini',
                'gpt-5' => 'GPT-5',
                'gpt-5-mini' => 'GPT-5 Mini',
                'gpt-5-nano' => 'GPT-5 Nano',
            ],
            'Anthropic Claude' => [
                'claude-opus-4-5-20251101' => 'Claude Opus 4.5',
                'claude-opus-4-1-20250805' => 'Claude Opus 4.1',
            ],
            'XAI' => [
                'grok-3' => 'Grok 3',
                'grok-3-mini' => 'Grok 3 Mini',
                'grok-3-fast' => 'Grok 3 Fast',
                'grok-3-mini-fast' => 'Grok 3 Mini Fast',
                'grok-4' => 'Grok 4',
            ],
            'DeepSeek' => [
                'deepseek-chat' => 'DeepSeek Chat',
                'deepseek-reasoner' => 'DeepSeek Reasoner',
            ],
        ];
        ?>
        <select id="ht_ai_model" name="ht_ai_model" style="min-width: 300px;">
            <?php foreach ($model_groups as $provider => $models): ?>
                <optgroup label="<?php echo esc_attr($provider); ?>">
                    <?php foreach ($models as $model_value => $model_label): ?>
                        <option value="<?php echo esc_attr($model_value); ?>" <?php selected($value, $model_value); ?>>
                            <?php echo esc_html($model_label); ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <p class="description">
            انتخاب مدل هوش مصنوعی برای پردازش درخواست‌ها. 
            همه مدل‌ها از طریق GapGPT API در دسترس هستند.
            <br>
            <a href="https://gapgpt.app/models" target="_blank">مشاهده لیست کامل مدل‌ها و قیمت‌ها →</a>
        </p>
        <?php
    }

    /**
     * Render GapGPT base URL field
     */
    public function render_gapgpt_base_url_field(): void
    {
        $value = get_option('ht_gapgpt_base_url', 'https://api.gapgpt.app/v1');
        ?>
        <input type="url" 
               id="ht_gapgpt_base_url" 
               name="ht_gapgpt_base_url" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               placeholder="https://api.gapgpt.app/v1">
        <p class="description">
            آدرس پایه API برای GapGPT. می‌توانید از https://api.gapapi.com/v1 برای CDN خارجی استفاده کنید.
        </p>
        <?php
    }

    /**
     * Render GapGPT API key field
     */
    public function render_gapgpt_api_key_field(): void
    {
        $value = get_option('ht_gapgpt_api_key', '');
        ?>
        <input type="text" 
               id="ht_gapgpt_api_key" 
               name="ht_gapgpt_api_key" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               placeholder="gapgpt_...">
        <button type="button" id="test-gapgpt-connection" class="button button-secondary" style="margin-left: 10px;">
            🔌 تست اتصال
        </button>
        <span id="gapgpt-test-result" style="margin-left: 10px;"></span>
        <p class="description">
            کلید API خود را از 
            <a href="https://gapgpt.app" target="_blank">پنل توسعه‌دهندگان GapGPT</a> 
            دریافت کنید. این فیلد فقط برای GapGPT Gateway نیاز است.
        </p>
        <script>
        jQuery(document).ready(function($) {
            $('#test-gapgpt-connection').on('click', function() {
                var $button = $(this);
                var $result = $('#gapgpt-test-result');
                var apiKey = $('#ht_gapgpt_api_key').val();
                var baseUrl = $('#ht_gapgpt_base_url').val();
                var provider = $('#ht_ai_provider').val();
                
                if (!apiKey && provider === 'gapgpt') {
                    $result.html('<span style="color: #d63638;">❌ لطفاً ابتدا کلید API را وارد کنید</span>');
                    return;
                }
                
                $button.prop('disabled', true).text('⏳ در حال تست...');
                $result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'test_gapgpt_connection',
                        api_key: apiKey,
                        base_url: baseUrl,
                        provider: provider,
                        nonce: '<?php echo wp_create_nonce('test_gapgpt_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color: #00a32a;">✅ ' + response.data.message + '</span>');
                        } else {
                            $result.html('<span style="color: #d63638;">❌ ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        $result.html('<span style="color: #d63638;">❌ خطا در برقراری ارتباط با سرور</span>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('🔌 تست اتصال');
                    }
                });
            });
        });
        </script>
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
     * Render auto index field (PR21)
     */
    public function render_auto_index_field(): void
    {
        $value = get_option('ht_auto_index_enabled', true);
        ?>
        <label>
            <input type="checkbox" 
                   name="ht_auto_index_enabled" 
                   value="1" 
                   <?php checked($value); ?>>
            ایندکس خودکار محتوای جدید برای استفاده در هوش مصنوعی
        </label>
        <p class="description">
            محتوای جدید به صورت خودکار در پایگاه دانش هوش مصنوعی ایندکس می‌شود.
        </p>
        <?php
    }

    /**
     * Render index post types field (PR21)
     */
    public function render_index_post_types_field(): void
    {
        $selected = get_option('ht_index_post_types', ['post', 'page', 'product']);
        $post_types = get_post_types(['public' => true], 'objects');
        
        ?>
        <fieldset>
            <?php foreach ($post_types as $post_type): ?>
                <label style="display: block; margin-bottom: 8px;">
                    <input type="checkbox" 
                           name="ht_index_post_types[]" 
                           value="<?php echo esc_attr($post_type->name); ?>"
                           <?php checked(in_array($post_type->name, $selected)); ?>>
                    <?php echo esc_html($post_type->label); ?> 
                    <small>(<?php echo esc_html($post_type->name); ?>)</small>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="description">
            انواع محتوایی که می‌خواهید برای استفاده هوش مصنوعی ایندکس شوند را انتخاب کنید.
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
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', (array) $user->roles, true);
        
        wp_localize_script('atlas-dashboard', 'atlasConfig', [
            'apiUrl' => rest_url('homaye/v1/atlas'),
            'nonce' => wp_create_nonce('wp_rest'),
            'userRole' => $is_admin ? 'administrator' : 'manager',
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

    /**
     * Render Global Observer page (PR13 - Modernized with React)
     */
    public function render_observer_page(): void
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('شما دسترسی لازم به این صفحه را ندارید.', 'homaye-tabesh'));
            return;
        }

        // Enqueue Observer CSS
        wp_enqueue_style(
            'observer-styles',
            HT_PLUGIN_URL . 'assets/css/observer.css',
            [],
            HT_VERSION
        );

        // Enqueue Observer React app
        wp_enqueue_script(
            'observer',
            HT_PLUGIN_URL . 'assets/build/observer.js',
            ['wp-element'],
            HT_VERSION,
            true
        );

        // Localize script with API endpoints
        wp_localize_script('observer', 'homaObserverConfig', [
            'apiUrl' => esc_url_raw(rest_url('homaye/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        ?>
        <div class="wrap homaye-tabesh-observer">
            <div id="homa-observer-root"></div>
        </div>
        <?php
    }

    /**
     * Render security center page (PR16)
     *
     * @return void
     */
    public function render_security_page(): void
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('شما دسترسی لازم به این صفحه را ندارید.', 'homaye-tabesh'));
            return;
        }

        // Enqueue Security Center CSS
        wp_enqueue_style(
            'security-center-styles',
            HT_PLUGIN_URL . 'assets/css/security-center.css',
            [],
            HT_VERSION
        );

        // Enqueue Security Center React app
        wp_enqueue_script(
            'security-center',
            HT_PLUGIN_URL . 'assets/build/security-center.js',
            ['wp-element'],
            HT_VERSION,
            true
        );

        // Localize script with API endpoints
        wp_localize_script('security-center', 'wpApiSettings', [
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        ?>
        <div class="wrap homaye-tabesh-security-center">
            <div id="homa-security-center-root"></div>
        </div>
        <?php
    }

    /**
     * LEGACY: Old render_security_page (kept for reference - can be removed after testing)
     */
    private function render_security_page_legacy(): void
    {
        // Get security stats
        $core = HT_Core::instance();
        $waf = $core->waf_engine;
        $behavior_tracker = $core->behavior_tracker;
        $access_control = $core->access_control;
        
        $stats = $behavior_tracker ? $behavior_tracker->get_statistics() : [];
        $blacklisted_ips = $waf ? $waf->get_blacklisted_ips(10) : [];
        $recent_activities = $behavior_tracker ? $behavior_tracker->get_recent_suspicious_activities(20) : [];
        
        ?>
        <div class="wrap" id="security-center-container">
            <h1>🛡️ مرکز امنیت - هما گاردین (Homa Guardian)</h1>
            <p class="description">سیستم امنیتی پیشرفته با فایروال چندلایه، محافظت از مدل زبانی و امتیازدهی رفتاری کاربران</p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                <!-- Security Score Stats -->
                <div class="card">
                    <h2>📊 آمار امنیتی</h2>
                    <div style="margin: 15px 0;">
                        <div style="margin: 10px 0;">
                            <strong>کل رویدادها:</strong> <?php echo number_format((float)($stats['total_events'] ?? 0)); ?>
                        </div>
                        <div style="margin: 10px 0;">
                            <strong>رویدادهای 24 ساعت:</strong> <?php echo number_format((float)($stats['events_24h'] ?? 0)); ?>
                        </div>
                        <div style="margin: 10px 0; color: #d63638;">
                            <strong>کاربران مسدود شده:</strong> <?php echo number_format((float)($stats['blocked_users'] ?? 0)); ?>
                        </div>
                        <div style="margin: 10px 0; color: #dba617;">
                            <strong>کاربران مشکوک:</strong> <?php echo number_format((float)($stats['suspicious_users'] ?? 0)); ?>
                        </div>
                        <div style="margin: 10px 0; color: #00a32a;">
                            <strong>کاربران ایمن:</strong> <?php echo number_format((float)($stats['safe_users'] ?? 0)); ?>
                        </div>
                    </div>
                </div>

                <!-- WAF Status -->
                <div class="card">
                    <h2>🔥 فایروال (WAF)</h2>
                    <div style="margin: 15px 0;">
                        <div style="margin: 10px 0; padding: 10px; background: #00a32a; color: white; border-radius: 4px; text-align: center;">
                            <strong>✓ فعال</strong>
                        </div>
                        <div style="margin: 10px 0;">
                            <strong>IPهای مسدود شده:</strong> <?php echo count($blacklisted_ips); ?>
                        </div>
                        <button type="button" class="button button-secondary" onclick="refreshBlacklist()">
                            🔄 بروزرسانی
                        </button>
                    </div>
                </div>

                <!-- LLM Shield Status -->
                <div class="card">
                    <h2>🛡️ سپر مدل زبانی</h2>
                    <div style="margin: 15px 0;">
                        <div style="margin: 10px 0; padding: 10px; background: #00a32a; color: white; border-radius: 4px; text-align: center;">
                            <strong>✓ فعال</strong>
                        </div>
                        <p style="font-size: 13px; color: #666;">محافظت از ورودی و خروجی Gemini API در برابر:</p>
                        <ul style="font-size: 12px; color: #666; margin: 5px 0; padding-right: 20px;">
                            <li>Prompt Injection</li>
                            <li>Data Leaking</li>
                            <li>PII Protection</li>
                        </ul>
                    </div>
                </div>

                <!-- Access Control -->
                <div class="card">
                    <h2>👥 کنترل دسترسی</h2>
                    <div style="margin: 15px 0;">
                        <p style="font-size: 13px;">مدیریت دسترسی تیم داخلی به ابزارهای اطلس و مانیتورینگ</p>
                        <a href="#access-control-section" class="button button-primary" style="margin-top: 10px;">
                            ⚙️ تنظیمات دسترسی
                        </a>
                    </div>
                </div>
            </div>

            <!-- Blacklisted IPs -->
            <div class="card" style="margin: 20px 0;">
                <h2>🚫 IPهای مسدود شده</h2>
                <div id="blacklisted-ips-container">
                    <?php if (empty($blacklisted_ips)): ?>
                        <p style="color: #666;">هیچ IP مسدود شده‌ای وجود ندارد.</p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>آدرس IP</th>
                                    <th>دلیل مسدودسازی</th>
                                    <th>زمان مسدودسازی</th>
                                    <th>انقضا</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blacklisted_ips as $ip_data): ?>
                                    <tr>
                                        <td><code><?php echo esc_html($ip_data['ip_address']); ?></code></td>
                                        <td><?php echo esc_html($ip_data['reason']); ?></td>
                                        <td><?php echo esc_html($ip_data['blocked_at']); ?></td>
                                        <td><?php echo $ip_data['expires_at'] ? esc_html($ip_data['expires_at']) : 'دائمی'; ?></td>
                                        <td>
                                            <button class="button button-small" onclick="unblockIP('<?php echo esc_js($ip_data['ip_address']); ?>')">
                                                🔓 رفع مسدودیت
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Suspicious Activities -->
            <div class="card" style="margin: 20px 0;">
                <h2>⚠️ فعالیت‌های مشکوک اخیر</h2>
                <div id="suspicious-activities-container">
                    <?php if (empty($recent_activities)): ?>
                        <p style="color: #00a32a;">فعالیت مشکوکی در 24 ساعت اخیر ثبت نشده است. ✓</p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>شناسه کاربر</th>
                                    <th>نوع رویداد</th>
                                    <th>امتیاز کسر شده</th>
                                    <th>امتیاز فعلی</th>
                                    <th>زمان</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <?php 
                                    $score = (int) $activity['current_score'];
                                    $color = $score >= 80 ? '#00a32a' : ($score >= 50 ? '#dba617' : '#d63638');
                                    ?>
                                    <tr>
                                        <td><code><?php echo esc_html($activity['user_identifier']); ?></code></td>
                                        <td><?php echo esc_html($activity['event_type']); ?></td>
                                        <td style="color: #d63638;">-<?php echo esc_html($activity['penalty_points']); ?></td>
                                        <td style="color: <?php echo $color; ?>;">
                                            <strong><?php echo esc_html($activity['current_score']); ?></strong>
                                        </td>
                                        <td><?php echo esc_html($activity['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Event Types -->
            <?php if (!empty($stats['top_events'])): ?>
            <div class="card" style="margin: 20px 0;">
                <h2>📈 انواع رویدادهای امنیتی (7 روز اخیر)</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>نوع رویداد</th>
                            <th>تعداد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['top_events'] as $event): ?>
                            <tr>
                                <td><?php echo esc_html($event['event_type']); ?></td>
                                <td><strong><?php echo number_format((float)$event['count']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Access Control Section -->
            <div class="card" id="access-control-section" style="margin: 20px 0;">
                <h2>👥 مدیریت سطوح دسترسی تیم داخلی</h2>
                <p class="description">تنظیم دسترسی کارمندان و تیم عملیاتی به ابزارهای اطلس، گزارشات و امکانات مدیریتی هما</p>
                
                <h3>نقش‌های کاربری مجاز:</h3>
                <div id="authorized-roles-container">
                    <p><em>در حال بارگذاری...</em></p>
                </div>

                <h3 style="margin-top: 30px;">کاربران مجاز (انتخاب فردی):</h3>
                <div id="authorized-users-container">
                    <p><em>در حال بارگذاری...</em></p>
                </div>
                
                <div style="margin-top: 20px;">
                    <h4>افزودن کاربر جدید:</h4>
                    <input type="text" id="user-search-input" placeholder="جستجوی کاربر..." style="width: 300px;" />
                    <div id="user-search-results" style="margin-top: 10px;"></div>
                </div>
            </div>

            <script>
                jQuery(document).ready(function($) {
                    // Load access control data
                    loadAuthorizedRoles();
                    loadAuthorizedUsers();

                    // User search with debounce
                    let searchTimeout;
                    $('#user-search-input').on('input', function() {
                        clearTimeout(searchTimeout);
                        const query = $(this).val();
                        
                        if (query.length < 2) {
                            $('#user-search-results').html('');
                            return;
                        }

                        searchTimeout = setTimeout(function() {
                            searchUsers(query);
                        }, 500);
                    });
                });

                function loadAuthorizedRoles() {
                    jQuery.get('<?php echo rest_url('homaye/v1/access-control/roles'); ?>', {
                        _wpnonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
                    })
                    .done(function(response) {
                        if (response.success) {
                            let html = '<div style="display: flex; flex-wrap: wrap; gap: 10px;">';
                            response.roles.forEach(function(role) {
                                const checked = role.authorized ? 'checked' : '';
                                html += `
                                    <label style="display: flex; align-items: center; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background: ${role.authorized ? '#e7f7e7' : '#f5f5f5'};">
                                        <input type="checkbox" ${checked} onchange="toggleRole('${role.key}')" style="margin-left: 8px;" />
                                        <span>${role.name}</span>
                                    </label>
                                `;
                            });
                            html += '</div>';
                            jQuery('#authorized-roles-container').html(html);
                        }
                    });
                }

                function loadAuthorizedUsers() {
                    jQuery.get('<?php echo rest_url('homaye/v1/access-control/users'); ?>', {
                        _wpnonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
                    })
                    .done(function(response) {
                        if (response.success) {
                            if (response.users.length === 0) {
                                jQuery('#authorized-users-container').html('<p style="color: #666;">هیچ کاربر فردی اضافه نشده است.</p>');
                            } else {
                                let html = '<ul style="list-style: none; padding: 0;">';
                                response.users.forEach(function(user) {
                                    html += `
                                        <li style="padding: 10px; border: 1px solid #ddd; margin: 5px 0; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <strong>${user.display_name}</strong> (${user.username})
                                                <br><small style="color: #666;">${user.email}</small>
                                            </div>
                                            <button class="button button-small" onclick="removeUser(${user.id})">حذف</button>
                                        </li>
                                    `;
                                });
                                html += '</ul>';
                                jQuery('#authorized-users-container').html(html);
                            }
                        }
                    });
                }

                function searchUsers(query) {
                    jQuery.get('<?php echo rest_url('homaye/v1/access-control/users/search'); ?>', {
                        search: query,
                        _wpnonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
                    })
                    .done(function(response) {
                        if (response.success && response.users.length > 0) {
                            let html = '<div style="border: 1px solid #ddd; border-radius: 4px; max-height: 300px; overflow-y: auto;">';
                            response.users.forEach(function(user) {
                                html += `
                                    <div style="padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
                                         onmouseover="this.style.background='#f5f5f5'"
                                         onmouseout="this.style.background='white'">
                                        <div>
                                            <strong>${user.display_name}</strong> (${user.username})
                                            <br><small style="color: #666;">${user.email}</small>
                                        </div>
                                        <button class="button button-small button-primary" onclick="addUser(${user.id})">افزودن</button>
                                    </div>
                                `;
                            });
                            html += '</div>';
                            jQuery('#user-search-results').html(html);
                        } else {
                            jQuery('#user-search-results').html('<p style="color: #666;">کاربری یافت نشد.</p>');
                        }
                    });
                }

                function addUser(userId) {
                    jQuery.post('<?php echo rest_url('homaye/v1/access-control/users'); ?>', {
                        user_id: userId,
                        _wpnonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
                    })
                    .done(function(response) {
                        if (response.success) {
                            alert('کاربر با موفقیت اضافه شد.');
                            jQuery('#user-search-input').val('');
                            jQuery('#user-search-results').html('');
                            loadAuthorizedUsers();
                        } else {
                            alert('خطا: ' + response.message);
                        }
                    });
                }

                function removeUser(userId) {
                    if (!confirm('آیا از حذف این کاربر مطمئن هستید؟')) {
                        return;
                    }

                    jQuery.ajax({
                        url: '<?php echo rest_url('homaye/v1/access-control/users/'); ?>' + userId,
                        method: 'DELETE',
                        data: {
                            _wpnonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
                        }
                    })
                    .done(function(response) {
                        if (response.success) {
                            alert('کاربر با موفقیت حذف شد.');
                            loadAuthorizedUsers();
                        } else {
                            alert('خطا: ' + response.message);
                        }
                    });
                }

                function toggleRole(roleKey) {
                    // Get all checked roles
                    const roles = [];
                    jQuery('input[type="checkbox"]:checked').each(function() {
                        const label = jQuery(this).parent();
                        const span = label.find('span').text();
                        // Find role key from original response
                        roles.push(roleKey);
                    });

                    // This is simplified - in production you'd track all role keys
                    alert('برای ذخیره تغییرات نقش‌ها، باید API کامل را فراخوانی کنید.');
                }

                function unblockIP(ip) {
                    if (!confirm('آیا از رفع مسدودیت این IP مطمئن هستید؟')) {
                        return;
                    }
                    alert('این قابلیت در نسخه آینده اضافه می‌شود.');
                }

                function refreshBlacklist() {
                    location.reload();
                }
            </script>

            <style>
                .card {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    box-shadow: 0 1px 1px rgba(0,0,0,.04);
                    padding: 20px;
                    border-radius: 4px;
                }
                .card h2 {
                    margin-top: 0;
                    font-size: 18px;
                    font-weight: 600;
                    border-bottom: 1px solid #eee;
                    padding-bottom: 10px;
                    margin-bottom: 15px;
                }
            </style>
        </div>
        <?php
    }

    /**
     * Render Super Console page (PR19)
     *
     * @return void
     */
    public function render_super_console_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Enqueue Super Console CSS (fix for CSP eval issue)
        wp_enqueue_style(
            'super-console-styles',
            HT_PLUGIN_URL . 'assets/css/super-console.css',
            [],
            HT_VERSION
        );

        // Enqueue Super Console React app
        wp_enqueue_script(
            'super-console',
            HT_PLUGIN_URL . 'assets/build/super-console.js',
            ['wp-element'],
            HT_VERSION,
            true
        );

        // Localize script with API endpoints
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', (array) $user->roles, true);
        
        wp_localize_script('super-console', 'homaConsoleConfig', [
            'apiUrl' => rest_url('homaye/v1/console'),
            'nonce' => wp_create_nonce('wp_rest'),
            'userRole' => $is_admin ? 'administrator' : 'manager',
            'userId' => get_current_user_id(),
        ]);

        ?>
        <div class="wrap homaye-tabesh-super-console">
            <div id="homa-super-console-root"></div>
        </div>
        <?php
    }

    /**
     * Test GapGPT connection via AJAX
     *
     * @return void
     */
    public function test_gapgpt_connection(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'test_gapgpt_connection')) {
            wp_send_json_error(['message' => 'امنیت: درخواست نامعتبر است']);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'شما دسترسی لازم را ندارید']);
            return;
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $base_url = isset($_POST['base_url']) ? esc_url_raw($_POST['base_url']) : 'https://api.gapgpt.app/v1';

        if (empty($api_key)) {
            wp_send_json_error(['message' => 'کلید API برای GapGPT الزامی است']);
            return;
        }

        // Test GapGPT connection
        $test_url = rtrim($base_url, '/') . '/chat/completions';
        $response = wp_remote_post($test_url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode([
                'model' => 'gemini-2.5-flash',
                'messages' => [
                    ['role' => 'user', 'content' => 'سلام']
                ],
                'max_tokens' => 10,
            ]),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'خطا در ارتباط: ' . $response->get_error_message()]);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code === 200 || $status_code === 201) {
            wp_send_json_success(['message' => 'اتصال موفق! GapGPT به درستی کار می‌کند']);
        } elseif ($status_code === 401) {
            wp_send_json_error(['message' => 'کلید API نامعتبر است']);
        } elseif ($status_code === 429) {
            wp_send_json_error(['message' => 'محدودیت درخواست: لطفاً کمی صبر کنید']);
        } else {
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : 'خطای ناشناخته';
            wp_send_json_error(['message' => 'خطا (' . $status_code . '): ' . $error_msg]);
        }
    }
}
