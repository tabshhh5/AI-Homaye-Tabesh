<?php
/**
 * Health Check REST API
 * Provides comprehensive health check endpoints for plugin monitoring
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * REST API endpoint for plugin health checks
 */
class HT_Health_Check_API
{
    /**
     * Register REST API endpoints
     *
     * @return void
     */
    public function register_endpoints(): void
    {
        try {
            // Health check endpoint - public for monitoring tools
            register_rest_route('homaye/v1', '/health', [
                'methods' => 'GET',
                'callback' => [$this, 'health_check'],
                'permission_callback' => '__return_true', // Public endpoint
            ]);

            // Detailed health check - requires admin privileges
            register_rest_route('homaye/v1', '/health/detailed', [
                'methods' => 'GET',
                'callback' => [$this, 'detailed_health_check'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ]);

            // API endpoints status - requires admin privileges
            register_rest_route('homaye/v1', '/health/endpoints', [
                'methods' => 'GET',
                'callback' => [$this, 'check_endpoints'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ]);

            // Error reporting endpoint - for frontend error tracking
            register_rest_route('homaye/v1', '/error-report', [
                'methods' => 'POST',
                'callback' => [$this, 'report_error'],
                'permission_callback' => function () {
                    // Allow all logged-in users to report errors for better diagnostics
                    return is_user_logged_in();
                },
            ]);

        } catch (\Throwable $e) {
            HT_Error_Handler::log_exception($e, 'health_api_registration');
        }
    }

    /**
     * Basic health check endpoint
     * Returns simple status for uptime monitoring
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public function health_check(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            global $wpdb;

            // Check database connectivity
            $db_check = $wpdb->get_var("SELECT 1");
            
            // Check critical table existence
            $critical_tables = [
                'homaye_persona_scores',
                'homa_sessions',
            ];
            
            $tables_ok = true;
            foreach ($critical_tables as $table) {
                $table_name = $wpdb->prefix . $table;
                // Table name is from trusted source (wpdb->prefix + hardcoded table name)
                // SHOW TABLES doesn't support prepared statement placeholders for table names
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
                    $tables_ok = false;
                    break;
                }
            }

            $status = 'healthy';
            if (!$db_check || !$tables_ok) {
                $status = 'degraded';
            }

            return new \WP_REST_Response([
                'status' => $status,
                'timestamp' => current_time('mysql'),
                'version' => HT_VERSION,
                'database' => $db_check ? 'ok' : 'error',
                'tables' => $tables_ok ? 'ok' : 'missing',
            ], 200);

        } catch (\Throwable $e) {
            HT_Error_Handler::log_exception($e, 'health_check_endpoint');
            
            return new \WP_REST_Response([
                'status' => 'error',
                'message' => 'Health check failed',
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Detailed health check with comprehensive diagnostics
     * Requires admin privileges
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public function detailed_health_check(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            if (!class_exists('\HomayeTabesh\HT_Activator')) {
                return new \WP_REST_Response([
                    'status' => 'error',
                    'message' => 'Activator class not found',
                ], 500);
            }

            $report = HT_Activator::run_health_check();
            
            return new \WP_REST_Response($report, 200);

        } catch (\Throwable $e) {
            HT_Error_Handler::log_exception($e, 'detailed_health_check');
            
            return new \WP_REST_Response([
                'status' => 'error',
                'message' => 'Detailed health check failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check status of all registered REST API endpoints
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public function check_endpoints(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $expected_endpoints = [
                '/homaye/v1/health' => [
                    'description' => 'Basic health check',
                    'public' => true,
                ],
                '/homaye/v1/health/detailed' => [
                    'description' => 'Detailed health diagnostics',
                    'public' => false,
                ],
                '/homaye/v1/health/endpoints' => [
                    'description' => 'API endpoints status',
                    'public' => false,
                ],
                '/homaye/v1/chat' => [
                    'description' => 'AI chat interface',
                    'public' => false,
                ],
                '/homaye/v1/telemetry' => [
                    'description' => 'User behavior tracking',
                    'public' => false,
                ],
                '/homaye/v1/lead' => [
                    'description' => 'Lead management',
                    'public' => false,
                ],
                '/homaye/v1/vault/store' => [
                    'description' => 'Omni-Store vault storage',
                    'public' => false,
                ],
                '/homaye/v1/atlas/insights' => [
                    'description' => 'Atlas analytics insights',
                    'public' => false,
                ],
            ];

            $rest_server = rest_get_server();
            $registered_routes = $rest_server->get_routes();
            
            $endpoint_status = [];
            foreach ($expected_endpoints as $route => $details) {
                $is_registered = isset($registered_routes[$route]);
                $endpoint_status[] = [
                    'route' => $route,
                    'description' => $details['description'],
                    'public' => $details['public'],
                    'registered' => $is_registered,
                    'status' => $is_registered ? 'active' : 'missing',
                ];
            }

            $active_count = count(array_filter($endpoint_status, function($ep) {
                return $ep['registered'];
            }));
            $total_count = count($endpoint_status);

            return new \WP_REST_Response([
                'status' => 'ok',
                'summary' => [
                    'active' => $active_count,
                    'total' => $total_count,
                    'percentage' => round(($active_count / $total_count) * 100, 2),
                ],
                'endpoints' => $endpoint_status,
                'timestamp' => current_time('mysql'),
            ], 200);

        } catch (\Throwable $e) {
            HT_Error_Handler::log_exception($e, 'check_endpoints');
            
            return new \WP_REST_Response([
                'status' => 'error',
                'message' => 'Endpoint check failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Report frontend error
     * Stores JavaScript errors for admin review
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public function report_error(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $error = $request->get_param('error');
            $context = $request->get_param('context');

            if (empty($error)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Error data is required',
                ], 400);
            }

            // Log to WordPress error log
            $error_message = sprintf(
                '[Homa Frontend Error] %s | URL: %s | User: %s',
                $error['message'] ?? 'Unknown error',
                $context['url'] ?? 'unknown',
                get_current_user_id() ?: 'guest'
            );

            HT_Error_Handler::log_error($error_message, 'frontend_error');

            // Store in transient for admin dashboard (keep last 50 errors)
            $frontend_errors = get_transient('homa_frontend_errors') ?: [];
            
            $frontend_errors[] = [
                'error' => $error,
                'context' => $context,
                'user_id' => get_current_user_id(),
                'timestamp' => current_time('mysql'),
            ];

            // Keep only last 50 errors
            if (count($frontend_errors) > 50) {
                $frontend_errors = array_slice($frontend_errors, -50);
            }

            set_transient('homa_frontend_errors', $frontend_errors, WEEK_IN_SECONDS);

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Error reported successfully',
            ], 200);

        } catch (\Throwable $e) {
            HT_Error_Handler::log_exception($e, 'error_reporting');
            
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to report error',
            ], 500);
        }
    }
}
