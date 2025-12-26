<?php
/**
 * Lead Management REST API
 *
 * @package HomayeTabesh
 * @since PR11
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * REST API برای مدیریت لیدها و فرآیند تبدیل
 */
class HT_Lead_REST_API
{
    /**
     * Lead Scoring Algorithm
     */
    private HT_Lead_Scoring_Algorithm $scoring;

    /**
     * WooCommerce Draft Bridge
     */
    private HT_WooCommerce_Draft_Bridge $draft_bridge;

    /**
     * OTP Core Engine
     */
    private Homa_OTP_Core_Engine $otp_engine;

    /**
     * Sales Notification Service
     */
    private HT_Sales_Notification_Service $notification_service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->scoring = new HT_Lead_Scoring_Algorithm();
        $this->draft_bridge = new HT_WooCommerce_Draft_Bridge();
        $this->otp_engine = new Homa_OTP_Core_Engine();
        $this->notification_service = new HT_Sales_Notification_Service();
    }

    /**
     * ثبت REST API endpoints
     */
    public function register_endpoints(): void
    {
        // ارسال کد OTP
        register_rest_route('homa/v1', '/otp/send', [
            'methods' => 'POST',
            'callback' => [$this, 'send_otp'],
            'permission_callback' => '__return_true',
            'args' => [
                'phone_number' => [
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function($param) {
                        return Homa_SMS_Provider::validate_iranian_phone($param);
                    }
                ],
            ],
        ]);

        // تایید کد OTP
        register_rest_route('homa/v1', '/otp/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'verify_otp'],
            'permission_callback' => '__return_true',
            'args' => [
                'phone_number' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'otp_code' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        // ثبت لید جدید
        register_rest_route('homa/v1', '/leads', [
            'methods' => 'POST',
            'callback' => [$this, 'create_lead'],
            'permission_callback' => '__return_true',
            'args' => [
                'user_id_or_token' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'contact_info' => [
                    'required' => false,
                    'type' => 'string',
                ],
            ],
        ]);

        // دریافت اطلاعات لید
        register_rest_route('homa/v1', '/leads/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_lead'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
            ],
        ]);

        // لیست تمام لیدها (برای داشبورد Atlas)
        register_rest_route('homa/v1', '/leads', [
            'methods' => 'GET',
            'callback' => [$this, 'list_leads'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // به‌روزرسانی لید
        register_rest_route('homa/v1', '/leads/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_lead'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // ایجاد سفارش پیش‌نویس
        register_rest_route('homa/v1', '/leads/(?P<id>\d+)/draft-order', [
            'methods' => 'POST',
            'callback' => [$this, 'create_draft_order'],
            'permission_callback' => '__return_true',
        ]);

        // محاسبه امتیاز لید
        register_rest_route('homa/v1', '/leads/calculate-score', [
            'methods' => 'POST',
            'callback' => [$this, 'calculate_lead_score'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * ارسال کد OTP
     */
    public function send_otp(\WP_REST_Request $request): \WP_REST_Response
    {
        $phone_number = $request->get_param('phone_number');
        $session_token = $request->get_param('session_token');

        $result = $this->otp_engine->send_otp($phone_number, $session_token);

        return new \WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * تایید کد OTP و ثبت نام/لاگین
     */
    public function verify_otp(\WP_REST_Request $request): \WP_REST_Response
    {
        $phone_number = $request->get_param('phone_number');
        $otp_code = $request->get_param('otp_code');
        $user_data = $request->get_param('user_data') ?: [];

        // تایید کد
        $verify_result = $this->otp_engine->verify_otp($phone_number, $otp_code);

        if (!$verify_result['success']) {
            return new \WP_REST_Response($verify_result, 400);
        }

        // ثبت نام یا لاگین خودکار
        $auth_result = $this->otp_engine->register_or_login_user($phone_number, $user_data);

        return new \WP_REST_Response(array_merge($verify_result, $auth_result), 200);
    }

    /**
     * ایجاد لید جدید
     */
    public function create_lead(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $data = [
            'user_id_or_token' => $request->get_param('user_id_or_token'),
            'contact_info' => $request->get_param('contact_info'),
            'contact_name' => $request->get_param('contact_name'),
            'requirements_summary' => $request->get_param('requirements_summary'),
            'source_referral' => $request->get_param('source_referral') ?: 'organic',
        ];

        // محاسبه امتیاز لید
        $score_params = [
            'source_referral' => $data['source_referral'],
            'volume' => $request->get_param('volume') ?: 0,
            'product_type' => $request->get_param('product_type') ?: '',
            'engagement' => $request->get_param('engagement') ?: [],
            'contact_info' => $data['contact_info'],
            'contact_name' => $data['contact_name'],
            'requirements_summary' => $data['requirements_summary'],
            'decision_time' => $request->get_param('decision_time') ?: 0,
        ];

        $lead_score = HT_Lead_Scoring_Algorithm::calculate_score($score_params);
        $lead_status = HT_Lead_Scoring_Algorithm::get_lead_status($lead_score);

        $data['lead_score'] = $lead_score;
        $data['lead_status'] = $lead_status;

        // تبدیل requirements_summary به JSON
        if (is_array($data['requirements_summary'])) {
            $data['requirements_summary'] = json_encode($data['requirements_summary'], JSON_UNESCAPED_UNICODE);
        }

        // ثبت در دیتابیس
        $table_name = $wpdb->prefix . 'homa_leads';
        
        $inserted = $wpdb->insert(
            $table_name,
            $data,
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );

        if (!$inserted) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'خطا در ذخیره‌سازی لید'
            ], 500);
        }

        $lead_id = $wpdb->insert_id;

        // ارسال نوتیفیکیشن برای لیدهای Hot
        if (HT_Lead_Scoring_Algorithm::needs_immediate_notification($lead_score)) {
            $data['id'] = $lead_id;
            $this->notification_service->notify_new_lead($data);
        }

        return new \WP_REST_Response([
            'success' => true,
            'lead_id' => $lead_id,
            'lead_score' => $lead_score,
            'lead_status' => $lead_status,
            'message' => 'لید با موفقیت ثبت شد'
        ], 201);
    }

    /**
     * دریافت اطلاعات یک لید
     */
    public function get_lead(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $lead_id = $request->get_param('id');
        $table_name = $wpdb->prefix . 'homa_leads';

        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $lead_id
        ), ARRAY_A);

        if (!$lead) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'لید یافت نشد'
            ], 404);
        }

        // تبدیل JSON requirements_summary
        if (!empty($lead['requirements_summary'])) {
            $lead['requirements_summary'] = json_decode($lead['requirements_summary'], true);
        }

        return new \WP_REST_Response($lead, 200);
    }

    /**
     * لیست تمام لیدها
     */
    public function list_leads(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homa_leads';
        $per_page = $request->get_param('per_page') ?: 20;
        $page = $request->get_param('page') ?: 1;
        $offset = ($page - 1) * $per_page;
        $status = $request->get_param('status');

        $where = '';
        $where_params = [];

        if ($status) {
            $where = 'WHERE lead_status = %s';
            $where_params[] = $status;
        }

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} {$where}");

        $query = "SELECT * FROM {$table_name} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $where_params[] = $per_page;
        $where_params[] = $offset;

        $leads = $wpdb->get_results($wpdb->prepare($query, ...$where_params), ARRAY_A);

        // تبدیل JSON fields
        foreach ($leads as &$lead) {
            if (!empty($lead['requirements_summary'])) {
                $lead['requirements_summary'] = json_decode($lead['requirements_summary'], true);
            }
        }

        return new \WP_REST_Response([
            'leads' => $leads,
            'total' => (int) $total,
            'page' => $page,
            'per_page' => $per_page,
            'pages' => ceil($total / $per_page),
        ], 200);
    }

    /**
     * به‌روزرسانی لید
     */
    public function update_lead(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $lead_id = $request->get_param('id');
        $table_name = $wpdb->prefix . 'homa_leads';

        $update_data = [];
        $update_format = [];

        $allowed_fields = ['lead_status', 'contact_info', 'contact_name', 'requirements_summary'];

        foreach ($allowed_fields as $field) {
            if ($request->has_param($field)) {
                $value = $request->get_param($field);
                
                if ($field === 'requirements_summary' && is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                
                $update_data[$field] = $value;
                $update_format[] = '%s';
            }
        }

        if (empty($update_data)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'هیچ داده‌ای برای به‌روزرسانی ارسال نشده'
            ], 400);
        }

        $updated = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $lead_id],
            $update_format,
            ['%d']
        );

        if ($updated === false) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'خطا در به‌روزرسانی لید'
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'لید با موفقیت به‌روزرسانی شد'
        ], 200);
    }

    /**
     * ایجاد سفارش پیش‌نویس
     */
    public function create_draft_order(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $lead_id = $request->get_param('id');
        $table_name = $wpdb->prefix . 'homa_leads';

        // دریافت اطلاعات لید
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $lead_id
        ), ARRAY_A);

        if (!$lead) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'لید یافت نشد'
            ], 404);
        }

        // آماده‌سازی داده برای سفارش
        $chat_data = [
            'user_id' => $lead['user_id'],
            'contact_name' => $lead['contact_name'],
            'contact_info' => $lead['contact_info'],
            'requirements' => json_decode($lead['requirements_summary'], true),
            'lead_score' => $lead['lead_score'],
            'source_referral' => $lead['source_referral'],
            'products' => $request->get_param('products') ?: [],
        ];

        // ایجاد سفارش پیش‌نویس
        $order_id = $this->draft_bridge->create_draft_order($chat_data);

        if (!$order_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'خطا در ایجاد سفارش پیش‌نویس'
            ], 500);
        }

        // به‌روزرسانی لید با شناسه سفارش
        $wpdb->update(
            $table_name,
            ['draft_order_id' => $order_id],
            ['id' => $lead_id],
            ['%d'],
            ['%d']
        );

        return new \WP_REST_Response([
            'success' => true,
            'order_id' => $order_id,
            'message' => 'سفارش پیش‌نویس با موفقیت ایجاد شد'
        ], 201);
    }

    /**
     * محاسبه امتیاز لید
     */
    public function calculate_lead_score(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        $score = HT_Lead_Scoring_Algorithm::calculate_score($params);
        $status = HT_Lead_Scoring_Algorithm::get_lead_status($score);

        return new \WP_REST_Response([
            'score' => $score,
            'status' => $status,
            'needs_notification' => HT_Lead_Scoring_Algorithm::needs_immediate_notification($score),
        ], 200);
    }

    /**
     * بررسی دسترسی مدیر
     */
    public function check_admin_permission(): bool
    {
        return current_user_can('manage_options');
    }
}
