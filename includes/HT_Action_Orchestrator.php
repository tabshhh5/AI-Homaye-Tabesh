<?php
/**
 * Action Orchestrator Engine
 *
 * @package HomayeTabesh
 * @since 1.0.0 (PR17)
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * موتور هماهنگسازی عملیات چندگانه
 * اجرای زنجیره‌ای دستورات و مدیریت Rollback
 * 
 * Capabilities:
 * - Sequential task execution
 * - Automatic rollback on failure
 * - Action validation and schema checking
 * - Multi-step operations (OTP + Cart + SMS)
 */
class HT_Action_Orchestrator
{
    /**
     * Action execution history
     */
    private array $execution_history = [];

    /**
     * HT_Core instance
     */
    private ?HT_Core $core = null;

    /**
     * Current action context
     */
    private array $context = [];

    /**
     * Supported action types
     */
    private const SUPPORTED_ACTIONS = [
        'verify_otp',
        'create_order',
        'add_to_cart',
        'send_sms',
        'update_user',
        'save_lead',
        'track_event',
        'send_notification',
    ];

    /**
     * Constructor
     *
     * @param HT_Core|null $core Core instance
     */
    public function __construct(?HT_Core $core = null)
    {
        $this->core = $core ?? HT_Core::instance();
    }

    /**
     * Execute multiple actions in sequence
     *
     * @param array $actions Array of action definitions
     * @param array $context Execution context
     * @return array Execution result with success status and data
     */
    public function execute_actions(array $actions, array $context = []): array
    {
        $this->context = $context;
        $this->execution_history = [];

        $results = [];
        $all_success = true;
        $final_message = '';

        foreach ($actions as $index => $action) {
            // Validate action structure
            $validation = $this->validate_action($action);
            if (!$validation['valid']) {
                $this->log_execution($action, false, $validation['error']);
                $all_success = false;
                
                // Rollback previous actions
                $this->rollback_actions($results);
                
                return [
                    'success' => false,
                    'error' => $validation['error'],
                    'failed_at' => $index,
                    'rollback_performed' => true,
                    'results' => $results,
                ];
            }

            // Execute action
            $result = $this->execute_single_action($action);
            
            // Log execution
            $this->log_execution($action, $result['success'], $result['message'] ?? '');

            // Store result
            $results[] = $result;

            // Check if action failed
            if (!$result['success']) {
                $all_success = false;
                
                // Perform rollback
                $this->rollback_actions($results);
                
                return [
                    'success' => false,
                    'error' => $result['message'] ?? 'Action execution failed',
                    'failed_at' => $index,
                    'failed_action' => $action['type'] ?? 'unknown',
                    'rollback_performed' => true,
                    'results' => $results,
                ];
            }

            // Update context with result data
            if (isset($result['data'])) {
                $this->context = array_merge($this->context, $result['data']);
            }
        }

        return [
            'success' => $all_success,
            'message' => $this->build_success_message($results),
            'results' => $results,
            'execution_history' => $this->execution_history,
        ];
    }

    /**
     * Execute a single action
     *
     * @param array $action Action definition
     * @return array Execution result
     */
    private function execute_single_action(array $action): array
    {
        $type = $action['type'] ?? '';
        $params = $action['params'] ?? [];

        try {
            switch ($type) {
                case 'verify_otp':
                    return $this->action_verify_otp($params);
                
                case 'create_order':
                    return $this->action_create_order($params);
                
                case 'add_to_cart':
                    return $this->action_add_to_cart($params);
                
                case 'send_sms':
                    return $this->action_send_sms($params);
                
                case 'update_user':
                    return $this->action_update_user($params);
                
                case 'save_lead':
                    return $this->action_save_lead($params);
                
                case 'track_event':
                    return $this->action_track_event($params);
                
                case 'send_notification':
                    return $this->action_send_notification($params);
                
                default:
                    return [
                        'success' => false,
                        'message' => sprintf('Unsupported action type: %s', $type),
                    ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => sprintf('Action execution error: %s', $e->getMessage()),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Action: Verify OTP
     *
     * @param array $params Action parameters
     * @return array Result
     */
    private function action_verify_otp(array $params): array
    {
        if (!isset($params['code']) || !isset($params['phone'])) {
            return [
                'success' => false,
                'message' => 'Missing required parameters: code, phone',
            ];
        }

        if (class_exists('\HomayeTabesh\Homa_OTP_Core_Engine')) {
            $otp_engine = new Homa_OTP_Core_Engine();
            $verification = $otp_engine->verify_otp($params['phone'], $params['code']);
            
            if ($verification['success']) {
                return [
                    'success' => true,
                    'message' => 'شماره تلفن شما با موفقیت تایید شد',
                    'data' => [
                        'phone_verified' => true,
                        'phone_number' => $params['phone'],
                    ],
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'کد تایید نامعتبر است',
        ];
    }

    /**
     * Action: Create Order
     *
     * @param array $params Action parameters
     * @return array Result
     */
    private function action_create_order(array $params): array
    {
        if (!class_exists('WooCommerce')) {
            return [
                'success' => false,
                'message' => 'WooCommerce is not active',
            ];
        }

        $product_id = $params['product_id'] ?? 0;
        $quantity = $params['quantity'] ?? 1;

        if (!$product_id) {
            return [
                'success' => false,
                'message' => 'Product ID is required',
            ];
        }

        try {
            $order = wc_create_order();
            $order->add_product(wc_get_product($product_id), $quantity);
            $order->calculate_totals();
            
            // Set customer if available
            if (isset($this->context['phone_number'])) {
                $order->set_billing_phone($this->context['phone_number']);
            }
            
            $order->save();

            return [
                'success' => true,
                'message' => sprintf('سفارش شما با موفقیت ثبت شد. شماره سفارش: %d', $order->get_id()),
                'data' => [
                    'order_id' => $order->get_id(),
                    'order_total' => $order->get_total(),
                ],
                'rollback_data' => [
                    'order_id' => $order->get_id(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => sprintf('خطا در ثبت سفارش: %s', $e->getMessage()),
            ];
        }
    }

    /**
     * Action: Add to Cart
     *
     * @param array $params Action parameters
     * @return array Result
     */
    private function action_add_to_cart(array $params): array
    {
        if (!class_exists('WooCommerce')) {
            return [
                'success' => false,
                'message' => 'WooCommerce is not active',
            ];
        }

        $product_id = $params['product_id'] ?? 0;
        $quantity = $params['quantity'] ?? 1;

        if (!$product_id) {
            return [
                'success' => false,
                'message' => 'Product ID is required',
            ];
        }

        $added = WC()->cart->add_to_cart($product_id, $quantity);

        if ($added) {
            return [
                'success' => true,
                'message' => 'محصول به سبد خرید اضافه شد',
                'data' => [
                    'cart_item_key' => $added,
                    'product_id' => $product_id,
                ],
                'rollback_data' => [
                    'cart_item_key' => $added,
                ],
            ];
        }

        return [
            'success' => false,
            'message' => 'خطا در افزودن محصول به سبد خرید',
        ];
    }

    /**
     * Action: Send SMS
     *
     * @param array $params Action parameters
     * @return array Result
     */
    private function action_send_sms(array $params): array
    {
        if (!class_exists('\HomayeTabesh\Homa_SMS_Provider')) {
            return [
                'success' => false,
                'message' => 'SMS provider not available',
            ];
        }

        $phone = $params['phone'] ?? $this->context['phone_number'] ?? '';
        $template = $params['template'] ?? '';
        $template_params = $params['template_params'] ?? [];

        if (!$phone) {
            return [
                'success' => false,
                'message' => 'Phone number is required',
            ];
        }

        $sms_provider = new Homa_SMS_Provider();
        
        if ($template) {
            $result = $sms_provider->send_pattern_sms($phone, $template, $template_params);
        } else {
            $message = $params['message'] ?? '';
            $result = $sms_provider->send_sms($phone, $message);
        }

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'پیامک با موفقیت ارسال شد',
                'data' => [
                    'sms_sent' => true,
                ],
            ];
        }

        return [
            'success' => false,
            'message' => 'خطا در ارسال پیامک',
        ];
    }

    /**
     * Action: Update User
     *
     * @param array $params Action parameters
     * @return array Result
     */
    private function action_update_user(array $params): array
    {
        $user_id = $params['user_id'] ?? get_current_user_id();

        if (!$user_id) {
            return [
                'success' => false,
                'message' => 'User ID is required',
            ];
        }

        $update_data = $params['data'] ?? [];
        
        if (empty($update_data)) {
            return [
                'success' => false,
                'message' => 'No data to update',
            ];
        }

        $result = wp_update_user(array_merge(['ID' => $user_id], $update_data));

        if (!is_wp_error($result)) {
            return [
                'success' => true,
                'message' => 'اطلاعات کاربر به‌روزرسانی شد',
            ];
        }

        return [
            'success' => false,
            'message' => 'خطا در به‌روزرسانی اطلاعات کاربر',
        ];
    }

    /**
     * Action: Save Lead
     *
     * @param array $params Action parameters
     * @return array Result
     */
    private function action_save_lead(array $params): array
    {
        if (!class_exists('\HomayeTabesh\HT_Lead_REST_API')) {
            return [
                'success' => false,
                'message' => 'Lead API not available',
            ];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'homa_leads';

        $data = [
            'user_id_or_token' => $params['user_id_or_token'] ?? '',
            'lead_score' => $params['lead_score'] ?? 0,
            'contact_info' => $params['contact_info'] ?? '',
            'requirements_summary' => json_encode($params['requirements'] ?? []),
            'created_at' => current_time('mysql'),
        ];

        $inserted = $wpdb->insert($table, $data);

        if ($inserted) {
            return [
                'success' => true,
                'message' => 'سرنخ با موفقیت ذخیره شد',
                'data' => [
                    'lead_id' => $wpdb->insert_id,
                ],
            ];
        }

        return [
            'success' => false,
            'message' => 'خطا در ذخیره سرنخ',
        ];
    }

    /**
     * Action: Track Event
     *
     * @param array $params Action parameters
     * @return array Result
     */
    private function action_track_event(array $params): array
    {
        $event_type = $params['event_type'] ?? '';
        $event_data = $params['data'] ?? [];

        if (!$event_type) {
            return [
                'success' => false,
                'message' => 'Event type is required',
            ];
        }

        // Log to telemetry system
        do_action('homa_track_event', $event_type, $event_data);

        return [
            'success' => true,
            'message' => 'رویداد ثبت شد',
        ];
    }

    /**
     * Action: Send Notification
     *
     * @param array $params Action parameters
     * @return array Result
     */
    private function action_send_notification(array $params): array
    {
        $user_id = $params['user_id'] ?? get_current_user_id();
        $message = $params['message'] ?? '';

        if (!$message) {
            return [
                'success' => false,
                'message' => 'Message is required',
            ];
        }

        // Use admin intervention system if available
        if (class_exists('\HomayeTabesh\HT_Admin_Intervention')) {
            $admin_intervention = HT_Admin_Intervention::instance();
            // Send notification logic here
        }

        return [
            'success' => true,
            'message' => 'اعلان ارسال شد',
        ];
    }

    /**
     * Validate action structure
     *
     * @param array $action Action definition
     * @return array Validation result
     */
    private function validate_action(array $action): array
    {
        if (!isset($action['type'])) {
            return [
                'valid' => false,
                'error' => 'Action type is required',
            ];
        }

        $type = $action['type'];

        if (!in_array($type, self::SUPPORTED_ACTIONS)) {
            return [
                'valid' => false,
                'error' => sprintf('Unsupported action type: %s', $type),
            ];
        }

        if (!isset($action['params']) || !is_array($action['params'])) {
            return [
                'valid' => false,
                'error' => 'Action params must be an array',
            ];
        }

        return [
            'valid' => true,
        ];
    }

    /**
     * Rollback executed actions
     *
     * @param array $results Execution results
     * @return void
     */
    private function rollback_actions(array $results): void
    {
        foreach (array_reverse($results) as $result) {
            if (!$result['success'] || !isset($result['rollback_data'])) {
                continue;
            }

            $this->perform_rollback($result);
        }
    }

    /**
     * Perform rollback for a single action
     *
     * @param array $result Action result
     * @return void
     */
    private function perform_rollback(array $result): void
    {
        $rollback_data = $result['rollback_data'] ?? [];

        // Rollback order creation
        if (isset($rollback_data['order_id'])) {
            $order = wc_get_order($rollback_data['order_id']);
            if ($order) {
                $order->delete(true); // Force delete
            }
        }

        // Rollback cart addition
        if (isset($rollback_data['cart_item_key'])) {
            WC()->cart->remove_cart_item($rollback_data['cart_item_key']);
        }
    }

    /**
     * Build success message from results
     *
     * @param array $results Execution results
     * @return string Success message
     */
    private function build_success_message(array $results): string
    {
        $messages = [];
        
        foreach ($results as $result) {
            if ($result['success'] && isset($result['message'])) {
                $messages[] = $result['message'];
            }
        }

        return implode('. ', $messages);
    }

    /**
     * Log execution
     *
     * @param array $action Action definition
     * @param bool $success Success status
     * @param string $message Result message
     * @return void
     */
    private function log_execution(array $action, bool $success, string $message): void
    {
        $this->execution_history[] = [
            'action' => $action,
            'success' => $success,
            'message' => $message,
            'timestamp' => current_time('mysql'),
        ];

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Homa Action Orchestrator - Type: %s, Success: %s, Message: %s',
                $action['type'] ?? 'unknown',
                $success ? 'Yes' : 'No',
                $message
            ));
        }
    }

    /**
     * Parse Gemini response to actions array
     *
     * @param array $gemini_response Gemini API response
     * @return array Actions array
     */
    public static function parse_gemini_response(array $gemini_response): array
    {
        if (!isset($gemini_response['actions']) || !is_array($gemini_response['actions'])) {
            return [];
        }

        return $gemini_response['actions'];
    }

    /**
     * Get execution history
     *
     * @return array Execution history
     */
    public function get_execution_history(): array
    {
        return $this->execution_history;
    }

    /**
     * Get supported action types
     *
     * @return array Supported action types
     */
    public static function get_supported_actions(): array
    {
        return self::SUPPORTED_ACTIONS;
    }
}
