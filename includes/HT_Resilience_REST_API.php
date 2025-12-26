<?php
/**
 * Resilience REST API - PR18 Endpoints
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * REST API برای مدیریت سیستم تابآوری و انتقال دانش
 */
class HT_Resilience_REST_API
{
    /**
     * Register REST API endpoints
     */
    public function register_endpoints(): void
    {
        // Logs endpoints
        register_rest_route('homaye-tabesh/v1', '/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_logs'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/logs/statistics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_log_statistics'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        // Fallback endpoints
        register_rest_route('homaye-tabesh/v1', '/fallback/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_fallback_status'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/fallback/leads', [
            'methods' => 'GET',
            'callback' => [$this, 'get_offline_leads'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/fallback/lead/(?P<id>\d+)/contact', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_lead_contacted'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/fallback/force-online', [
            'methods' => 'POST',
            'callback' => [$this, 'force_online_mode'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        // Lead collection endpoint (public)
        register_rest_route('homaye-tabesh/v1', '/offline/collect-lead', [
            'methods' => 'POST',
            'callback' => [$this, 'collect_lead'],
            'permission_callback' => [$this, 'lead_collection_permission_check'],
        ]);

        // Cache endpoints
        register_rest_route('homaye-tabesh/v1', '/cache/statistics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_cache_statistics'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/cache/clear', [
            'methods' => 'POST',
            'callback' => [$this, 'clear_cache'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/cache/warmup', [
            'methods' => 'POST',
            'callback' => [$this, 'warmup_cache'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        // Snapshot endpoints
        register_rest_route('homaye-tabesh/v1', '/snapshots', [
            'methods' => 'GET',
            'callback' => [$this, 'get_snapshots'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/snapshots/export', [
            'methods' => 'POST',
            'callback' => [$this, 'export_knowledge'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/snapshots/(?P<id>\d+)/restore', [
            'methods' => 'POST',
            'callback' => [$this, 'restore_snapshot'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/snapshots/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_snapshot'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/snapshots/import', [
            'methods' => 'POST',
            'callback' => [$this, 'import_knowledge'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        // Background jobs endpoints
        register_rest_route('homaye-tabesh/v1', '/jobs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_jobs'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/jobs/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_job_status'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/jobs/queue', [
            'methods' => 'POST',
            'callback' => [$this, 'queue_job'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/jobs/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'cancel_job'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        // Cleanup endpoints
        register_rest_route('homaye-tabesh/v1', '/cleanup/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'run_cleanup_analysis'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/cleanup/reports', [
            'methods' => 'GET',
            'callback' => [$this, 'get_cleanup_reports'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);

        register_rest_route('homaye-tabesh/v1', '/cleanup/(?P<id>\d+)/auto-fix', [
            'methods' => 'POST',
            'callback' => [$this, 'auto_fix_issues'],
            'permission_callback' => [$this, 'admin_permission_check'],
        ]);
    }

    /**
     * Get logs
     */
    public function get_logs(\WP_REST_Request $request): \WP_REST_Response
    {
        $logger = new HT_BlackBox_Logger();
        
        $filters = [
            'log_type' => $request->get_param('log_type'),
            'status' => $request->get_param('status'),
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0,
        ];

        $logs = $logger->get_logs($filters);

        return rest_ensure_response([
            'success' => true,
            'logs' => $logs,
            'count' => count($logs),
        ]);
    }

    /**
     * Get log statistics
     */
    public function get_log_statistics(): \WP_REST_Response
    {
        $logger = new HT_BlackBox_Logger();
        $stats = $logger->get_statistics();

        return rest_ensure_response([
            'success' => true,
            'statistics' => $stats,
        ]);
    }

    /**
     * Get fallback status
     */
    public function get_fallback_status(): \WP_REST_Response
    {
        $engine = new HT_Fallback_Engine();
        $stats = $engine->get_statistics();

        return rest_ensure_response([
            'success' => true,
            'fallback_status' => $stats,
        ]);
    }

    /**
     * Get offline leads
     */
    public function get_offline_leads(\WP_REST_Request $request): \WP_REST_Response
    {
        $engine = new HT_Fallback_Engine();
        
        $filters = [
            'contacted' => $request->get_param('contacted'),
            'limit' => $request->get_param('limit') ?? 50,
        ];

        $leads = $engine->get_leads($filters);

        return rest_ensure_response([
            'success' => true,
            'leads' => $leads,
        ]);
    }

    /**
     * Mark lead as contacted
     */
    public function mark_lead_contacted(\WP_REST_Request $request): \WP_REST_Response
    {
        $engine = new HT_Fallback_Engine();
        $lead_id = (int) $request->get_param('id');
        $notes = sanitize_textarea_field($request->get_param('notes') ?? '');

        $result = $engine->mark_lead_contacted($lead_id, $notes);

        return rest_ensure_response([
            'success' => $result,
            'message' => $result ? 'Lead marked as contacted' : 'Failed to update lead',
        ]);
    }

    /**
     * Force online mode
     */
    public function force_online_mode(): \WP_REST_Response
    {
        $engine = new HT_Fallback_Engine();
        $engine->force_online_mode();

        return rest_ensure_response([
            'success' => true,
            'message' => 'System forced to online mode',
        ]);
    }

    /**
     * Collect lead (public endpoint)
     */
    public function collect_lead(\WP_REST_Request $request): \WP_REST_Response
    {
        $engine = new HT_Fallback_Engine();

        $lead_data = [
            'full_name' => sanitize_text_field($request->get_param('full_name')),
            'phone' => sanitize_text_field($request->get_param('phone')),
            'email' => sanitize_email($request->get_param('email')),
            'message' => sanitize_textarea_field($request->get_param('message')),
        ];

        $lead_id = $engine->save_lead($lead_data);

        if ($lead_id) {
            return rest_ensure_response([
                'success' => true,
                'message' => 'اطلاعات شما با موفقیت ثبت شد. همکاران ما در اولین فرصت با شما تماس خواهند گرفت.',
                'lead_id' => $lead_id,
            ]);
        }

        return rest_ensure_response([
            'success' => false,
            'message' => 'خطا در ثبت اطلاعات. لطفاً دوباره تلاش کنید.',
        ]);
    }

    /**
     * Get cache statistics
     */
    public function get_cache_statistics(): \WP_REST_Response
    {
        $optimizer = new HT_Query_Optimizer();
        $stats = $optimizer->get_cache_statistics();

        return rest_ensure_response([
            'success' => true,
            'statistics' => $stats,
        ]);
    }

    /**
     * Clear cache
     */
    public function clear_cache(): \WP_REST_Response
    {
        $optimizer = new HT_Query_Optimizer();
        $optimizer->clear_all_caches();

        return rest_ensure_response([
            'success' => true,
            'message' => 'All caches cleared successfully',
        ]);
    }

    /**
     * Warmup cache
     */
    public function warmup_cache(): \WP_REST_Response
    {
        $optimizer = new HT_Query_Optimizer();
        $optimizer->warmup_cache();

        return rest_ensure_response([
            'success' => true,
            'message' => 'Cache warmed up successfully',
        ]);
    }

    /**
     * Get snapshots
     */
    public function get_snapshots(\WP_REST_Request $request): \WP_REST_Response
    {
        $exporter = new HT_Data_Exporter();
        
        $filters = [
            'is_auto' => $request->get_param('is_auto'),
            'limit' => $request->get_param('limit') ?? 50,
        ];

        $snapshots = $exporter->get_snapshots($filters);

        return rest_ensure_response([
            'success' => true,
            'snapshots' => $snapshots,
        ]);
    }

    /**
     * Export knowledge
     */
    public function export_knowledge(\WP_REST_Request $request): \WP_REST_Response
    {
        $exporter = new HT_Data_Exporter();
        
        $description = sanitize_text_field($request->get_param('description') ?? '');
        $encrypt = (bool) $request->get_param('encrypt');

        $result = $exporter->export_knowledge($description, $encrypt);

        return rest_ensure_response($result);
    }

    /**
     * Restore snapshot
     */
    public function restore_snapshot(\WP_REST_Request $request): \WP_REST_Response
    {
        $exporter = new HT_Data_Exporter();
        $snapshot_id = (int) $request->get_param('id');

        $result = $exporter->restore_snapshot($snapshot_id);

        return rest_ensure_response($result);
    }

    /**
     * Delete snapshot
     */
    public function delete_snapshot(\WP_REST_Request $request): \WP_REST_Response
    {
        $exporter = new HT_Data_Exporter();
        $snapshot_id = (int) $request->get_param('id');

        $success = $exporter->delete_snapshot($snapshot_id);

        return rest_ensure_response([
            'success' => $success,
            'message' => $success ? 'Snapshot deleted' : 'Failed to delete snapshot',
        ]);
    }

    /**
     * Import knowledge
     */
    public function import_knowledge(\WP_REST_Request $request): \WP_REST_Response
    {
        // Handle file upload
        if (!isset($_FILES['file'])) {
            return rest_ensure_response([
                'success' => false,
                'message' => 'No file uploaded',
            ]);
        }

        $file = $_FILES['file'];
        $mode = sanitize_text_field($request->get_param('mode') ?? 'merge');
        $is_encrypted = (bool) $request->get_param('is_encrypted');

        $exporter = new HT_Data_Exporter();
        $result = $exporter->import_knowledge($file['tmp_name'], $mode, $is_encrypted);

        return rest_ensure_response($result);
    }

    /**
     * Get jobs
     */
    public function get_jobs(\WP_REST_Request $request): \WP_REST_Response
    {
        $processor = new HT_Background_Processor();
        
        $filters = [
            'job_type' => $request->get_param('job_type'),
            'status' => $request->get_param('status'),
            'limit' => $request->get_param('limit') ?? 50,
        ];

        $jobs = $processor->get_jobs($filters);

        return rest_ensure_response([
            'success' => true,
            'jobs' => $jobs,
        ]);
    }

    /**
     * Get job status
     */
    public function get_job_status(\WP_REST_Request $request): \WP_REST_Response
    {
        $processor = new HT_Background_Processor();
        $job_id = (int) $request->get_param('id');

        $job = $processor->get_job_status($job_id);

        if ($job) {
            return rest_ensure_response([
                'success' => true,
                'job' => $job,
            ]);
        }

        return rest_ensure_response([
            'success' => false,
            'message' => 'Job not found',
        ]);
    }

    /**
     * Queue job
     */
    public function queue_job(\WP_REST_Request $request): \WP_REST_Response
    {
        $processor = new HT_Background_Processor();
        
        $job_type = sanitize_text_field($request->get_param('job_type'));
        $job_data = $request->get_param('job_data') ?? [];

        $job_id = $processor->queue_job($job_type, $job_data);

        if ($job_id) {
            return rest_ensure_response([
                'success' => true,
                'message' => 'Job queued successfully',
                'job_id' => $job_id,
            ]);
        }

        return rest_ensure_response([
            'success' => false,
            'message' => 'Failed to queue job',
        ]);
    }

    /**
     * Cancel job
     */
    public function cancel_job(\WP_REST_Request $request): \WP_REST_Response
    {
        $processor = new HT_Background_Processor();
        $job_id = (int) $request->get_param('id');

        $success = $processor->cancel_job($job_id);

        return rest_ensure_response([
            'success' => $success,
            'message' => $success ? 'Job cancelled' : 'Failed to cancel job',
        ]);
    }

    /**
     * Run cleanup analysis
     */
    public function run_cleanup_analysis(): \WP_REST_Response
    {
        $cleanup = new HT_Auto_Cleanup();
        $result = $cleanup->run_analysis();

        return rest_ensure_response([
            'success' => true,
            'analysis' => $result,
        ]);
    }

    /**
     * Get cleanup reports
     */
    public function get_cleanup_reports(\WP_REST_Request $request): \WP_REST_Response
    {
        $cleanup = new HT_Auto_Cleanup();
        
        $filters = [
            'status' => $request->get_param('status'),
            'limit' => $request->get_param('limit') ?? 20,
        ];

        $reports = $cleanup->get_reports($filters);

        return rest_ensure_response([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    /**
     * Auto-fix issues
     */
    public function auto_fix_issues(\WP_REST_Request $request): \WP_REST_Response
    {
        $cleanup = new HT_Auto_Cleanup();
        $report_id = (int) $request->get_param('id');

        $result = $cleanup->auto_fix($report_id);

        return rest_ensure_response($result);
    }

    /**
     * Check admin permission
     */
    public function admin_permission_check(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Permission check for lead collection (rate limited)
     */
    public function lead_collection_permission_check(): bool
    {
        // Check for basic referrer validation
        $referrer = wp_get_referer();
        $site_url = get_site_url();
        
        // Must come from same site
        if (!$referrer || strpos($referrer, $site_url) !== 0) {
            return false;
        }

        // Rate limiting: max 3 submissions per IP per hour
        $ip = $this->get_client_ip();
        $transient_key = 'ht_lead_rate_' . md5($ip);
        $count = (int) get_transient($transient_key);
        
        if ($count >= 3) {
            return false;
        }
        
        set_transient($transient_key, $count + 1, HOUR_IN_SECONDS);
        return true;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }

        return 'unknown';
    }
}
