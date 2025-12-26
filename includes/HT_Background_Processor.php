<?php
/**
 * Background Processor - Heavy Task Handler
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * پردازشگر پسزمینه برای عملیات سنگین
 * استفاده از WP-Cron و Chunk Processing
 */
class HT_Background_Processor
{
    /**
     * Jobs table name
     */
    private string $jobs_table;

    /**
     * Chunk size for batch processing
     */
    private const CHUNK_SIZE = 50;

    /**
     * Max execution time per cycle (seconds)
     */
    private const MAX_EXECUTION_TIME = 20;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->jobs_table = $wpdb->prefix . 'homa_background_jobs';
    }

    /**
     * Create jobs table
     */
    public function create_table(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->jobs_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            job_type varchar(50) NOT NULL,
            job_data longtext NOT NULL,
            status varchar(20) DEFAULT 'pending',
            progress int DEFAULT 0,
            total_items int DEFAULT 0,
            result longtext,
            error_message text,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_type (job_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Queue a background job
     *
     * @param string $job_type Job type
     * @param array $job_data Job data
     * @return int|false Job ID or false on failure
     */
    public function queue_job(string $job_type, array $job_data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->jobs_table,
            [
                'job_type' => $job_type,
                'job_data' => wp_json_encode($job_data),
                'status' => 'pending',
            ],
            ['%s', '%s', '%s']
        );

        if ($result === false) {
            return false;
        }

        $job_id = (int) $wpdb->insert_id;

        // Schedule processing if not already scheduled
        if (!wp_next_scheduled('ht_process_background_jobs')) {
            wp_schedule_single_event(time() + 10, 'ht_process_background_jobs');
        }

        return $job_id;
    }

    /**
     * Process pending jobs
     */
    public function process_jobs(): void
    {
        global $wpdb;

        $start_time = time();

        // Get pending jobs
        $jobs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->jobs_table} WHERE status = %s ORDER BY created_at ASC LIMIT 10",
                'pending'
            ),
            ARRAY_A
        );

        foreach ($jobs as $job) {
            // Check if we've exceeded max execution time
            if (time() - $start_time > self::MAX_EXECUTION_TIME) {
                // Reschedule for next cycle
                wp_schedule_single_event(time() + 30, 'ht_process_background_jobs');
                break;
            }

            $this->process_job($job);
        }

        // Check if there are more jobs to process
        $pending_count = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$this->jobs_table} WHERE status = %s", 'pending')
        );

        if ($pending_count > 0) {
            // Schedule next cycle
            wp_schedule_single_event(time() + 30, 'ht_process_background_jobs');
        }
    }

    /**
     * Process a single job
     *
     * @param array $job Job data
     */
    private function process_job(array $job): void
    {
        global $wpdb;

        try {
            // Mark as processing
            $wpdb->update(
                $this->jobs_table,
                [
                    'status' => 'processing',
                    'started_at' => current_time('mysql'),
                ],
                ['id' => $job['id']],
                ['%s', '%s'],
                ['%d']
            );

            $job_data = json_decode($job['job_data'], true);
            $result = null;

            // Execute based on job type
            switch ($job['job_type']) {
                case 'index_knowledge':
                    $result = $this->process_index_knowledge($job_data, $job['id']);
                    break;

                case 'export_large':
                    $result = $this->process_large_export($job_data, $job['id']);
                    break;

                case 'optimize_database':
                    $result = $this->process_database_optimization($job_data, $job['id']);
                    break;

                case 'cleanup_logs':
                    $result = $this->process_cleanup_logs($job_data, $job['id']);
                    break;

                default:
                    throw new \Exception('Unknown job type: ' . $job['job_type']);
            }

            // Mark as completed
            $wpdb->update(
                $this->jobs_table,
                [
                    'status' => 'completed',
                    'progress' => 100,
                    'result' => wp_json_encode($result),
                    'completed_at' => current_time('mysql'),
                ],
                ['id' => $job['id']],
                ['%s', '%d', '%s', '%s'],
                ['%d']
            );

        } catch (\Exception $e) {
            // Mark as failed
            $wpdb->update(
                $this->jobs_table,
                [
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => current_time('mysql'),
                ],
                ['id' => $job['id']],
                ['%s', '%s', '%s'],
                ['%d']
            );
        }
    }

    /**
     * Process knowledge indexing job
     *
     * @param array $data Job data
     * @param int $job_id Job ID
     * @return array Result
     */
    private function process_index_knowledge(array $data, int $job_id): array
    {
        if (!class_exists('\HomayeTabesh\HT_Knowledge_Base')) {
            throw new \Exception('Knowledge base not available');
        }

        $knowledge = new HT_Knowledge_Base();
        $offset = 0;
        $indexed_count = 0;

        while (true) {
            // Get chunk of facts
            $facts = $knowledge->get_facts([
                'limit' => self::CHUNK_SIZE,
                'offset' => $offset,
            ]);

            if (empty($facts)) {
                break;
            }

            // Process each fact
            foreach ($facts as $fact) {
                // Reindex or process fact
                // This is a placeholder - actual indexing logic would go here
                $indexed_count++;
            }

            $offset += self::CHUNK_SIZE;

            // Update progress
            $this->update_job_progress($job_id, $offset, $data['total_items'] ?? $offset);

            // Small delay to prevent overload
            usleep(100000); // 0.1 second
        }

        return ['indexed_count' => $indexed_count];
    }

    /**
     * Process large export job
     *
     * @param array $data Job data
     * @param int $job_id Job ID
     * @return array Result
     */
    private function process_large_export(array $data, int $job_id): array
    {
        if (!class_exists('\HomayeTabesh\HT_Data_Exporter')) {
            throw new \Exception('Data exporter not available');
        }

        $exporter = new HT_Data_Exporter();
        
        $this->update_job_progress($job_id, 50, 100);
        
        $result = $exporter->export_knowledge(
            $data['description'] ?? 'Background export',
            $data['encrypt'] ?? false
        );

        $this->update_job_progress($job_id, 100, 100);

        return $result;
    }

    /**
     * Process database optimization job
     *
     * @param array $data Job data
     * @param int $job_id Job ID
     * @return array Result
     */
    private function process_database_optimization(array $data, int $job_id): array
    {
        if (!class_exists('\HomayeTabesh\HT_Query_Optimizer')) {
            throw new \Exception('Query optimizer not available');
        }

        $optimizer = new HT_Query_Optimizer();
        
        $this->update_job_progress($job_id, 25, 100);
        
        // Add indexes
        $optimizer->add_indexes();
        
        $this->update_job_progress($job_id, 50, 100);
        
        // Optimize tables
        $results = $optimizer->optimize_tables();
        
        $this->update_job_progress($job_id, 75, 100);
        
        // Warm up cache
        $optimizer->warmup_cache();
        
        $this->update_job_progress($job_id, 100, 100);

        return ['optimization_results' => $results];
    }

    /**
     * Process log cleanup job
     *
     * @param array $data Job data
     * @param int $job_id Job ID
     * @return array Result
     */
    private function process_cleanup_logs(array $data, int $job_id): array
    {
        if (!class_exists('\HomayeTabesh\HT_BlackBox_Logger')) {
            throw new \Exception('BlackBox logger not available');
        }

        $logger = new HT_BlackBox_Logger();
        
        $this->update_job_progress($job_id, 50, 100);
        
        $deleted = $logger->clean_old_logs();
        
        $this->update_job_progress($job_id, 100, 100);

        return ['deleted_logs' => $deleted];
    }

    /**
     * Update job progress
     *
     * @param int $job_id Job ID
     * @param int $progress Current progress
     * @param int $total Total items
     */
    private function update_job_progress(int $job_id, int $progress, int $total): void
    {
        global $wpdb;

        $percentage = $total > 0 ? (int) (($progress / $total) * 100) : 0;

        $wpdb->update(
            $this->jobs_table,
            [
                'progress' => $percentage,
                'total_items' => $total,
            ],
            ['id' => $job_id],
            ['%d', '%d'],
            ['%d']
        );
    }

    /**
     * Get job status
     *
     * @param int $job_id Job ID
     * @return array|null Job data
     */
    public function get_job_status(int $job_id): ?array
    {
        global $wpdb;

        $job = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->jobs_table} WHERE id = %d", $job_id),
            ARRAY_A
        );

        if (!$job) {
            return null;
        }

        // Decode JSON fields
        if (!empty($job['job_data'])) {
            $job['job_data'] = json_decode($job['job_data'], true);
        }
        if (!empty($job['result'])) {
            $job['result'] = json_decode($job['result'], true);
        }

        return $job;
    }

    /**
     * Get all jobs with filters
     *
     * @param array $filters Filter criteria
     * @return array Jobs
     */
    public function get_jobs(array $filters = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $values = [];

        if (!empty($filters['job_type'])) {
            $where[] = 'job_type = %s';
            $values[] = $filters['job_type'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $values[] = $filters['status'];
        }

        $limit = absint($filters['limit'] ?? 50);
        $offset = absint($filters['offset'] ?? 0);

        $where_sql = implode(' AND ', $where);
        $query = "SELECT * FROM {$this->jobs_table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = $limit;
        $values[] = $offset;

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        $jobs = $wpdb->get_results($query, ARRAY_A);

        // Decode JSON fields
        foreach ($jobs as &$job) {
            if (!empty($job['job_data'])) {
                $job['job_data'] = json_decode($job['job_data'], true);
            }
            if (!empty($job['result'])) {
                $job['result'] = json_decode($job['result'], true);
            }
        }

        return $jobs;
    }

    /**
     * Cancel a pending job
     *
     * @param int $job_id Job ID
     * @return bool Success
     */
    public function cancel_job(int $job_id): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->jobs_table,
            ['status' => 'cancelled'],
            ['id' => $job_id, 'status' => 'pending'],
            ['%s'],
            ['%d', '%s']
        );

        return $result !== false;
    }

    /**
     * Clean completed jobs older than specified days
     *
     * @param int $days Days to keep
     * @return int Number of deleted jobs
     */
    public function clean_old_jobs(int $days = 30): int
    {
        global $wpdb;

        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->jobs_table} WHERE status IN ('completed', 'failed', 'cancelled') AND created_at < %s",
                $date_threshold
            )
        );

        return (int) $deleted;
    }

    /**
     * Get statistics
     *
     * @return array Statistics
     */
    public function get_statistics(): array
    {
        global $wpdb;

        $stats = [];

        $stats['total_jobs'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->jobs_table}");
        
        $stats['by_status'] = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->jobs_table} GROUP BY status",
            ARRAY_A
        );

        $stats['by_type'] = $wpdb->get_results(
            "SELECT job_type, COUNT(*) as count FROM {$this->jobs_table} GROUP BY job_type",
            ARRAY_A
        );

        return $stats;
    }
}
