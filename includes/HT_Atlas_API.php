<?php
/**
 * Atlas Control Center REST API
 *
 * @package HomayeTabesh
 * @since PR9
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * مرکز کنترل استراتژیک اطلس - API Layer
 * 
 * این کلاس API های لازم برای داشبورد اطلس را فراهم می‌کند
 * و داده‌های تحلیلی را به فرمت قابل استفاده تبدیل می‌کند
 */
class HT_Atlas_API
{
    /**
     * Register REST API endpoints
     *
     * @return void
     */
    public function register_endpoints(): void
    {
        // Executive Health Overview
        register_rest_route('homaye/v1', '/atlas/health', [
            'methods' => 'GET',
            'callback' => [$this, 'get_health_overview'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // User Flow Analysis
        register_rest_route('homaye/v1', '/atlas/flow-analysis', [
            'methods' => 'GET',
            'callback' => [$this, 'get_flow_analysis'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Bottleneck Detection
        register_rest_route('homaye/v1', '/atlas/bottlenecks', [
            'methods' => 'GET',
            'callback' => [$this, 'get_bottlenecks'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Recommendations
        register_rest_route('homaye/v1', '/atlas/recommendations', [
            'methods' => 'GET',
            'callback' => [$this, 'get_recommendations'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Decision Simulation
        register_rest_route('homaye/v1', '/atlas/simulate', [
            'methods' => 'POST',
            'callback' => [$this, 'simulate_decision'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Atlas Settings (Administrator only)
        register_rest_route('homaye/v1', '/atlas/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => [$this, 'check_administrator_permission'],
        ]);

        register_rest_route('homaye/v1', '/atlas/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => [$this, 'check_administrator_permission'],
        ]);

        // Export Reports
        register_rest_route('homaye/v1', '/atlas/export/pdf', [
            'methods' => 'POST',
            'callback' => [$this, 'export_pdf_report'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route('homaye/v1', '/atlas/export/csv', [
            'methods' => 'POST',
            'callback' => [$this, 'export_csv_report'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
    }

    /**
     * Check if user has admin permissions
     *
     * @return bool
     */
    public function check_admin_permission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Check if user has administrator permissions (for advanced settings)
     *
     * @return bool
     */
    public function check_administrator_permission(): bool
    {
        // Check if current user has administrator role
        $user = wp_get_current_user();
        return in_array('administrator', (array) $user->roles, true);
    }

    /**
     * Get executive health overview
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_health_overview(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        // Get data from last 30 days
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

        // Total sessions
        $sessions_table = $wpdb->prefix . 'homa_sessions';
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table WHERE updated_at > %s",
            $thirty_days_ago
        ));

        // Conversion sessions
        $conversions_table = $wpdb->prefix . 'homaye_conversion_sessions';
        $total_conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $conversions_table WHERE conversion_status = 'completed' AND created_at > %s",
            $thirty_days_ago
        ));

        $in_progress_conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $conversions_table WHERE conversion_status = 'in_progress' AND created_at > %s",
            $thirty_days_ago
        ));

        // Calculate conversion rate
        $conversion_rate = $total_sessions > 0 ? ($total_conversions / $total_sessions) * 100 : 0;

        // Average cart value
        $avg_cart_value = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(cart_value) FROM $conversions_table WHERE cart_value > 0 AND created_at > %s",
            $thirty_days_ago
        )) ?? 0;

        // Active users (last 7 days)
        $seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
        $active_users = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_token) FROM $sessions_table WHERE updated_at > %s",
            $seven_days_ago
        ));

        // Telemetry events
        $events_table = $wpdb->prefix . 'homaye_telemetry_events';
        $total_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $events_table WHERE timestamp > %s",
            $thirty_days_ago
        ));

        // Health score calculation (0-100)
        $health_score = $this->calculate_health_score([
            'conversion_rate' => $conversion_rate,
            'active_users' => $active_users,
            'total_events' => $total_events,
        ]);

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'health_score' => round($health_score, 1),
                'health_status' => $this->get_health_status($health_score),
                'metrics' => [
                    'total_sessions' => (int)$total_sessions,
                    'total_conversions' => (int)$total_conversions,
                    'in_progress_conversions' => (int)$in_progress_conversions,
                    'conversion_rate' => round($conversion_rate, 2),
                    'avg_cart_value' => round($avg_cart_value, 2),
                    'active_users_7d' => (int)$active_users,
                    'total_events' => (int)$total_events,
                ],
                'insights' => $this->generate_health_insights($conversion_rate, $active_users, $health_score),
                'timestamp' => current_time('mysql'),
            ]
        ], 200);
    }

    /**
     * Calculate overall health score
     *
     * @param array $metrics
     * @return float
     */
    private function calculate_health_score(array $metrics): float
    {
        $score = 0;

        // Conversion rate weight: 40%
        if ($metrics['conversion_rate'] > 0) {
            $score += min(($metrics['conversion_rate'] / 5) * 40, 40); // 5% = 40 points
        }

        // Active users weight: 35%
        if ($metrics['active_users'] > 0) {
            $score += min(($metrics['active_users'] / 50) * 35, 35); // 50 users = 35 points
        }

        // Total events weight: 25%
        if ($metrics['total_events'] > 0) {
            $score += min(($metrics['total_events'] / 1000) * 25, 25); // 1000 events = 25 points
        }

        return $score;
    }

    /**
     * Get health status text
     *
     * @param float $score
     * @return string
     */
    private function get_health_status(float $score): string
    {
        if ($score >= 80) {
            return 'excellent';
        } elseif ($score >= 60) {
            return 'good';
        } elseif ($score >= 40) {
            return 'warning';
        } else {
            return 'critical';
        }
    }

    /**
     * Generate human-readable health insights
     *
     * @param float $conversion_rate
     * @param int $active_users
     * @param float $health_score
     * @return array
     */
    private function generate_health_insights(float $conversion_rate, int $active_users, float $health_score): array
    {
        $insights = [];

        if ($health_score < 40) {
            $insights[] = [
                'type' => 'critical',
                'title' => 'سلامت سایت در وضعیت بحرانی است',
                'description' => 'نرخ تبدیل و تعامل کاربران بسیار پایین است. نیاز به بررسی فوری دارد.',
                'action' => 'بررسی فوری گلوگاه‌های سایت در لایه تحلیل رفتار'
            ];
        }

        if ($conversion_rate < 2) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'نرخ تبدیل پایین‌تر از حد انتظار',
                'description' => sprintf('نرخ تبدیل فعلی %.2f%% است. میانگین صنعت حدود 2-3%% می‌باشد.', $conversion_rate),
                'action' => 'بررسی پیشنهادات اطلس برای بهبود نرخ تبدیل'
            ];
        } elseif ($conversion_rate > 5) {
            $insights[] = [
                'type' => 'success',
                'title' => 'نرخ تبدیل عالی',
                'description' => sprintf('نرخ تبدیل %.2f%% بالاتر از میانگین صنعت است. وضعیت خوبی دارید!', $conversion_rate),
                'action' => null
            ];
        }

        if ($active_users < 10) {
            $insights[] = [
                'type' => 'info',
                'title' => 'حجم نمونه کم است',
                'description' => 'تعداد کاربران فعال برای تحلیل دقیق کافی نیست. داده‌های بیشتری نیاز است.',
                'action' => 'افزایش ترافیک سایت یا صبر برای جمع‌آوری داده بیشتر'
            ];
        }

        if (empty($insights)) {
            $insights[] = [
                'type' => 'success',
                'title' => 'همه چیز در حال کار است',
                'description' => 'سایت شما عملکرد خوبی دارد. به مانیتورینگ ادامه دهید.',
                'action' => null
            ];
        }

        return $insights;
    }

    /**
     * Get user flow analysis
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_flow_analysis(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $events_table = $wpdb->prefix . 'homaye_telemetry_events';
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

        // Get event type distribution
        $event_distribution = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COUNT(*) as count 
             FROM $events_table 
             WHERE timestamp > %s 
             GROUP BY event_type 
             ORDER BY count DESC",
            $thirty_days_ago
        ), ARRAY_A);

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'event_distribution' => $event_distribution,
                'analysis_period' => '30 days',
                'timestamp' => current_time('mysql'),
            ]
        ], 200);
    }

    /**
     * Get bottlenecks detection
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_bottlenecks(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $conversions_table = $wpdb->prefix . 'homaye_conversion_sessions';
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

        // Find sessions with high drop-off
        $incomplete_sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT conversion_status, COUNT(*) as count, AVG(form_completion) as avg_completion 
             FROM $conversions_table 
             WHERE created_at > %s 
             GROUP BY conversion_status",
            $thirty_days_ago
        ), ARRAY_A);

        $bottlenecks = [];

        foreach ($incomplete_sessions as $session) {
            if ($session['conversion_status'] !== 'completed' && $session['avg_completion'] > 0) {
                $exit_rate = ($session['count'] / array_sum(array_column($incomplete_sessions, 'count'))) * 100;
                
                if ($exit_rate > 30) {
                    $bottlenecks[] = [
                        'location' => $session['conversion_status'],
                        'exit_rate' => round($exit_rate, 2),
                        'avg_completion' => round($session['avg_completion'], 2),
                        'insight' => $this->generate_bottleneck_insight($session['conversion_status'], $exit_rate, $session['avg_completion']),
                        'severity' => $exit_rate > 60 ? 'high' : ($exit_rate > 40 ? 'medium' : 'low'),
                    ];
                }
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'bottlenecks' => $bottlenecks,
                'total_detected' => count($bottlenecks),
                'timestamp' => current_time('mysql'),
            ]
        ], 200);
    }

    /**
     * Generate bottleneck insight
     *
     * @param string $status
     * @param float $exit_rate
     * @param float $avg_completion
     * @return string
     */
    private function generate_bottleneck_insight(string $status, float $exit_rate, float $avg_completion): string
    {
        $insights = [
            'in_progress' => sprintf(
                'کاربران در مرحله "%s" با نرخ خروج %.1f%% دچار تردید می‌شوند. میانگین تکمیل فرم: %.1f%%. پیشنهاد: ساده‌سازی فرآیند و اضافه کردن راهنمایی بیشتر.',
                $status, $exit_rate, $avg_completion
            ),
            'abandoned' => sprintf(
                'نرخ رها شدن در این مرحله %.1f%% است. کاربران معمولاً در %.1f%% مسیر متوقف می‌شوند. پیشنهاد: بررسی زمان بارگذاری صفحه و سادگی فرم.',
                $exit_rate, $avg_completion
            ),
        ];

        return $insights[$status] ?? sprintf('نرخ خروج %.1f%% در مرحله "%s" نیاز به بررسی دارد.', $exit_rate, $status);
    }

    /**
     * Get recommendations
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_recommendations(\WP_REST_Request $request): \WP_REST_Response
    {
        // Get health data
        $health_response = $this->get_health_overview($request);
        $health_data = $health_response->get_data()['data'];
        
        // Get bottlenecks
        $bottlenecks_response = $this->get_bottlenecks($request);
        $bottlenecks_data = $bottlenecks_response->get_data()['data'];

        $recommendations = [];

        // Generate recommendations based on health metrics
        if ($health_data['metrics']['conversion_rate'] < 2) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'conversion',
                'title' => 'بهبود نرخ تبدیل',
                'description' => 'نرخ تبدیل فعلی پایین است. با ساده‌سازی فرم‌ها و بهبود CTA ها می‌توانید این نرخ را افزایش دهید.',
                'actions' => [
                    'کاهش تعداد فیلدهای اجباری در فرم‌ها',
                    'افزودن توضیحات بیشتر در کنار دکمه‌های خرید',
                    'استفاده از تخفیف‌های محدود زمانی',
                ],
                'expected_impact' => '+50% افزایش نرخ تبدیل',
            ];
        }

        if ($health_data['metrics']['active_users_7d'] < 20) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'traffic',
                'title' => 'افزایش ترافیک سایت',
                'description' => 'تعداد کاربران فعال پایین است. نیاز به افزایش ترافیک دارید.',
                'actions' => [
                    'بهینه‌سازی SEO محتوا',
                    'اجرای کمپین‌های تبلیغاتی هدفمند',
                    'استفاده از شبکه‌های اجتماعی',
                ],
                'expected_impact' => 'افزایش 2x ترافیک ماهانه',
            ];
        }

        // Add bottleneck-based recommendations
        if (!empty($bottlenecks_data['bottlenecks'])) {
            foreach ($bottlenecks_data['bottlenecks'] as $bottleneck) {
                if ($bottleneck['severity'] === 'high') {
                    $recommendations[] = [
                        'priority' => 'high',
                        'category' => 'user_experience',
                        'title' => 'رفع گلوگاه در مسیر کاربر',
                        'description' => $bottleneck['insight'],
                        'actions' => [
                            'بررسی دقیق صفحه مربوطه',
                            'تست A/B برای بهبود تجربه کاربری',
                            'افزودن راهنمایی بیشتر',
                        ],
                        'expected_impact' => sprintf('کاهش %.1f%% نرخ خروج', $bottleneck['exit_rate'] * 0.3),
                    ];
                }
            }
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'priority' => 'low',
                'category' => 'general',
                'title' => 'ادامه مانیتورینگ',
                'description' => 'عملکرد سایت در حد مطلوب است. به مانیتورینگ و بهینه‌سازی مداوم ادامه دهید.',
                'actions' => [],
                'expected_impact' => 'حفظ وضعیت فعلی',
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'recommendations' => $recommendations,
                'total' => count($recommendations),
                'timestamp' => current_time('mysql'),
            ]
        ], 200);
    }

    /**
     * Simulate decision impact
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function simulate_decision(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        
        $decision_type = $params['decision_type'] ?? '';
        $current_value = floatval($params['current_value'] ?? 0);
        $risk_level = floatval($params['risk_level'] ?? 0.5);

        // Simple prediction model
        $impact_factor = 1 + ($risk_level * 0.2); // 20% max increase
        $predicted_value = $current_value * $impact_factor;

        $confidence = 100 - ($risk_level * 30); // Lower confidence for higher risk

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'decision_type' => $decision_type,
                'current_value' => $current_value,
                'predicted_value' => round($predicted_value, 2),
                'expected_change' => round((($predicted_value - $current_value) / $current_value) * 100, 2),
                'confidence_level' => round($confidence, 2),
                'risk_assessment' => $risk_level > 0.7 ? 'high' : ($risk_level > 0.4 ? 'medium' : 'low'),
                'recommendation' => $this->generate_simulation_recommendation($risk_level, $predicted_value - $current_value),
                'timestamp' => current_time('mysql'),
            ]
        ], 200);
    }

    /**
     * Generate simulation recommendation
     *
     * @param float $risk_level
     * @param float $change
     * @return string
     */
    private function generate_simulation_recommendation(float $risk_level, float $change): string
    {
        if ($risk_level > 0.7) {
            return 'با توجه به ریسک بالا، پیشنهاد می‌شود ابتدا در یک گروه کوچک تست شود.';
        } elseif ($change > 0) {
            return 'با توجه به تاثیر مثبت پیش‌بینی شده، این تغییر مثبت ارزیابی می‌شود.';
        } else {
            return 'پیش‌بینی می‌شود این تغییر تاثیر منفی داشته باشد. توصیه نمی‌شود.';
        }
    }

    /**
     * Get Atlas settings
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        $settings = [
            'auto_index_enabled' => get_option('ht_atlas_auto_index', false),
            'scan_interval' => get_option('ht_atlas_scan_interval', 3600), // seconds
            'intelligence_level' => get_option('ht_atlas_intelligence_level', 'standard'), // basic, standard, advanced
            'alert_threshold' => get_option('ht_atlas_alert_threshold', 40), // health score threshold
            'data_retention_days' => get_option('ht_atlas_data_retention', 90),
            // PR11: MeliPayamak SMS Settings
            'melipayamak_username' => get_option('ht_melipayamak_username', ''),
            'melipayamak_password' => get_option('ht_melipayamak_password', ''),
            'melipayamak_from_number' => get_option('ht_melipayamak_from_number', ''),
            'melipayamak_otp_pattern' => get_option('ht_melipayamak_otp_pattern', ''),
            'melipayamak_lead_notification_pattern' => get_option('ht_melipayamak_lead_notification_pattern', ''),
            'admin_phone_number' => get_option('ht_admin_phone_number', ''),
            'lead_notification_enabled' => get_option('ht_lead_notification_enabled', true),
            'lead_hot_score_threshold' => get_option('ht_lead_hot_score_threshold', 70),
        ];

        return new \WP_REST_Response([
            'success' => true,
            'data' => $settings,
        ], 200);
    }

    /**
     * Update Atlas settings
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        if (isset($params['auto_index_enabled'])) {
            update_option('ht_atlas_auto_index', (bool)$params['auto_index_enabled']);
        }

        if (isset($params['scan_interval'])) {
            $interval = max(300, intval($params['scan_interval'])); // Minimum 5 minutes
            update_option('ht_atlas_scan_interval', $interval);
        }

        if (isset($params['intelligence_level'])) {
            $level = in_array($params['intelligence_level'], ['basic', 'standard', 'advanced']) 
                ? $params['intelligence_level'] 
                : 'standard';
            update_option('ht_atlas_intelligence_level', $level);
        }

        if (isset($params['alert_threshold'])) {
            $threshold = max(0, min(100, intval($params['alert_threshold'])));
            update_option('ht_atlas_alert_threshold', $threshold);
        }

        if (isset($params['data_retention_days'])) {
            $days = max(7, min(365, intval($params['data_retention_days'])));
            update_option('ht_atlas_data_retention', $days);
        }

        // PR11: MeliPayamak SMS Settings
        if (isset($params['melipayamak_username'])) {
            update_option('ht_melipayamak_username', sanitize_text_field($params['melipayamak_username']));
        }

        if (isset($params['melipayamak_password'])) {
            update_option('ht_melipayamak_password', sanitize_text_field($params['melipayamak_password']));
        }

        if (isset($params['melipayamak_from_number'])) {
            update_option('ht_melipayamak_from_number', sanitize_text_field($params['melipayamak_from_number']));
        }

        if (isset($params['melipayamak_otp_pattern'])) {
            update_option('ht_melipayamak_otp_pattern', sanitize_text_field($params['melipayamak_otp_pattern']));
        }

        if (isset($params['melipayamak_lead_notification_pattern'])) {
            update_option('ht_melipayamak_lead_notification_pattern', sanitize_text_field($params['melipayamak_lead_notification_pattern']));
        }

        if (isset($params['admin_phone_number'])) {
            update_option('ht_admin_phone_number', sanitize_text_field($params['admin_phone_number']));
        }

        if (isset($params['lead_notification_enabled'])) {
            update_option('ht_lead_notification_enabled', (bool)$params['lead_notification_enabled']);
        }

        if (isset($params['lead_hot_score_threshold'])) {
            $threshold = max(0, min(100, intval($params['lead_hot_score_threshold'])));
            update_option('ht_lead_hot_score_threshold', $threshold);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'تنظیمات با موفقیت بروزرسانی شد.',
        ], 200);
    }

    /**
     * Export PDF report (placeholder)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function export_pdf_report(\WP_REST_Request $request): \WP_REST_Response
    {
        // This would require a PDF library like TCPDF or Dompdf
        // For now, return a success message indicating the feature is planned
        
        return new \WP_REST_Response([
            'success' => false,
            'message' => 'قابلیت خروجی PDF در نسخه‌های بعدی اضافه خواهد شد.',
        ], 501);
    }

    /**
     * Export CSV report
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function export_csv_report(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $report_type = $request->get_param('report_type') ?? 'health';
        
        // Get data based on report type
        $health_response = $this->get_health_overview($request);
        $health_data = $health_response->get_data()['data'];

        // Create CSV content
        $csv_content = "Report Type,Metric,Value,Timestamp\n";
        $csv_content .= "Health Overview,Health Score," . $health_data['health_score'] . "," . current_time('mysql') . "\n";
        $csv_content .= "Health Overview,Total Sessions," . $health_data['metrics']['total_sessions'] . "," . current_time('mysql') . "\n";
        $csv_content .= "Health Overview,Total Conversions," . $health_data['metrics']['total_conversions'] . "," . current_time('mysql') . "\n";
        $csv_content .= "Health Overview,Conversion Rate," . $health_data['metrics']['conversion_rate'] . "%," . current_time('mysql') . "\n";
        $csv_content .= "Health Overview,Active Users (7d)," . $health_data['metrics']['active_users_7d'] . "," . current_time('mysql') . "\n";

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'csv_content' => $csv_content,
                'filename' => 'atlas-report-' . date('Y-m-d') . '.csv',
            ],
        ], 200);
    }
}
