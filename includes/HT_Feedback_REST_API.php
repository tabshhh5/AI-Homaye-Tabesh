<?php
/**
 * Feedback REST API
 *
 * @package HomayeTabesh
 * @since 1.0.0 (PR17)
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * API برای سیستم بازخورد
 */
class HT_Feedback_REST_API
{
    /**
     * API namespace
     */
    private const NAMESPACE = 'homaye-tabesh/v1';

    /**
     * Feedback system instance
     */
    private HT_Feedback_System $feedback_system;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->feedback_system = new HT_Feedback_System();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes
     *
     * @return void
     */
    public function register_routes(): void
    {
        // Submit feedback
        register_rest_route(self::NAMESPACE, '/feedback', [
            'methods' => 'POST',
            'callback' => [$this, 'submit_feedback'],
            'permission_callback' => '__return_true',
        ]);

        // Get review queue (Admin only)
        register_rest_route(self::NAMESPACE, '/feedback/queue', [
            'methods' => 'GET',
            'callback' => [$this, 'get_review_queue'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        // Get single feedback (Admin only)
        register_rest_route(self::NAMESPACE, '/feedback/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_feedback'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        // Update feedback status (Admin only)
        register_rest_route(self::NAMESPACE, '/feedback/(?P<id>\d+)/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_status'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        // Get feedback statistics (Admin only)
        register_rest_route(self::NAMESPACE, '/feedback/statistics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_statistics'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);
    }

    /**
     * Submit feedback endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function submit_feedback(\WP_REST_Request $request): \WP_REST_Response
    {
        $feedback_data = [
            'rating' => sanitize_text_field($request->get_param('rating')),
            'response_text' => sanitize_textarea_field($request->get_param('response_text')),
            'user_prompt' => sanitize_textarea_field($request->get_param('user_prompt')),
            'conversation_id' => sanitize_text_field($request->get_param('conversation_id')),
            'error_details' => sanitize_textarea_field($request->get_param('error_details')),
            'facts_used' => $request->get_param('facts_used') ?: [],
            'context_data' => $request->get_param('context_data') ?: [],
        ];

        $result = $this->feedback_system->submit_feedback($feedback_data);

        return new \WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get review queue endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function get_review_queue(\WP_REST_Request $request): \WP_REST_Response
    {
        $filters = [
            'status' => $request->get_param('status'),
            'rating' => $request->get_param('rating'),
            'date_from' => $request->get_param('date_from'),
            'date_to' => $request->get_param('date_to'),
        ];

        $page = (int)$request->get_param('page') ?: 1;
        $per_page = (int)$request->get_param('per_page') ?: 20;

        $result = $this->feedback_system->get_review_queue($filters, $page, $per_page);

        return new \WP_REST_Response($result, 200);
    }

    /**
     * Get single feedback endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function get_feedback(\WP_REST_Request $request): \WP_REST_Response
    {
        $feedback_id = (int)$request->get_param('id');
        $feedback = $this->feedback_system->get_feedback($feedback_id);

        if (!$feedback) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Feedback not found',
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $feedback,
        ], 200);
    }

    /**
     * Update feedback status endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function update_status(\WP_REST_Request $request): \WP_REST_Response
    {
        $feedback_id = (int)$request->get_param('id');
        $status = sanitize_text_field($request->get_param('status'));
        $admin_notes = sanitize_textarea_field($request->get_param('admin_notes'));

        $result = $this->feedback_system->update_feedback_status($feedback_id, $status, $admin_notes);

        if ($result) {
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Feedback status updated',
            ], 200);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Failed to update feedback status',
        ], 400);
    }

    /**
     * Get statistics endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function get_statistics(\WP_REST_Request $request): \WP_REST_Response
    {
        $filters = [
            'date_from' => $request->get_param('date_from'),
            'date_to' => $request->get_param('date_to'),
        ];

        $result = $this->feedback_system->get_statistics($filters);

        return new \WP_REST_Response([
            'success' => true,
            'data' => $result,
        ], 200);
    }

    /**
     * Admin permission check
     *
     * @return bool Has permission
     */
    public function admin_permission_check(): bool
    {
        return current_user_can('manage_options');
    }
}
