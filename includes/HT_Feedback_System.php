<?php
/**
 * User Feedback System
 *
 * @package HomayeTabesh
 * @since 1.0.0 (PR17)
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * سیستم بازخورد هوشمند کاربران
 * ثبت لایک/دیسلایک و ارسال به صف بررسی مدیر
 * 
 * Features:
 * - Like/Dislike feedback collection
 * - Detailed error reporting
 * - Admin review queue
 * - Security score integration
 */
class HT_Feedback_System
{
    /**
     * Database table name
     */
    private string $table_name;

    /**
     * Minimum security score for feedback submission
     */
    private const MIN_SECURITY_SCORE = 50;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'homa_feedback';
    }

    /**
     * Submit feedback
     *
     * @param array $feedback_data Feedback data
     * @return array Result
     */
    public function submit_feedback(array $feedback_data): array
    {
        // Validate user eligibility
        $eligibility = $this->check_user_eligibility();
        if (!$eligibility['eligible']) {
            return [
                'success' => false,
                'message' => $eligibility['reason'],
            ];
        }

        // Validate feedback data
        $validation = $this->validate_feedback($feedback_data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['error'],
            ];
        }

        // Insert feedback
        $result = $this->insert_feedback($feedback_data);

        if ($result) {
            // Trigger notification if negative feedback
            if ($feedback_data['rating'] === 'dislike') {
                $this->notify_admin_negative_feedback($result);
            }

            return [
                'success' => true,
                'message' => 'بازخورد شما ثبت شد. از همکاری شما سپاسگزاریم',
                'feedback_id' => $result,
            ];
        }

        return [
            'success' => false,
            'message' => 'خطا در ثبت بازخورد',
        ];
    }

    /**
     * Check if user is eligible to submit feedback
     *
     * @return array Eligibility result
     */
    private function check_user_eligibility(): array
    {
        // Check if user is logged in
        $user_id = get_current_user_id();
        
        // For logged-in users, always allow
        if ($user_id > 0) {
            return [
                'eligible' => true,
                'user_type' => 'logged_in',
            ];
        }

        // For guest users, check security score
        if (class_exists('\HomayeTabesh\HT_User_Behavior_Tracker')) {
            $tracker = new HT_User_Behavior_Tracker();
            $score = $tracker->get_security_score();

            if ($score < self::MIN_SECURITY_SCORE) {
                return [
                    'eligible' => false,
                    'reason' => 'امتیاز امنیتی شما برای ثبت بازخورد کافی نیست',
                ];
            }
        }

        return [
            'eligible' => true,
            'user_type' => 'guest',
        ];
    }

    /**
     * Validate feedback data
     *
     * @param array $feedback_data Feedback data
     * @return array Validation result
     */
    private function validate_feedback(array $feedback_data): array
    {
        if (!isset($feedback_data['rating']) || !in_array($feedback_data['rating'], ['like', 'dislike'])) {
            return [
                'valid' => false,
                'error' => 'نوع بازخورد نامعتبر است',
            ];
        }

        if (!isset($feedback_data['response_text'])) {
            return [
                'valid' => false,
                'error' => 'متن پاسخ الزامی است',
            ];
        }

        if (!isset($feedback_data['conversation_id'])) {
            return [
                'valid' => false,
                'error' => 'شناسه مکالمه الزامی است',
            ];
        }

        return [
            'valid' => true,
        ];
    }

    /**
     * Insert feedback into database
     *
     * @param array $feedback_data Feedback data
     * @return int|false Feedback ID or false
     */
    private function insert_feedback(array $feedback_data): int|false
    {
        global $wpdb;

        $user_id = get_current_user_id();
        $user_identifier = $user_id > 0 ? 'user_' . $user_id : $this->get_guest_identifier();

        $data = [
            'user_id' => $user_id > 0 ? $user_id : null,
            'user_identifier' => $user_identifier,
            'conversation_id' => $feedback_data['conversation_id'] ?? '',
            'rating' => $feedback_data['rating'],
            'response_text' => $feedback_data['response_text'],
            'user_prompt' => $feedback_data['user_prompt'] ?? '',
            'error_details' => $feedback_data['error_details'] ?? '',
            'facts_used' => json_encode($feedback_data['facts_used'] ?? []),
            'context_data' => json_encode($feedback_data['context_data'] ?? []),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ];

        $inserted = $wpdb->insert(
            $this->table_name,
            $data,
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get guest user identifier
     *
     * @return string Guest identifier
     */
    private function get_guest_identifier(): string
    {
        if (class_exists('\HomayeTabesh\HT_User_Behavior_Tracker')) {
            $tracker = new HT_User_Behavior_Tracker();
            return $tracker->get_user_fingerprint();
        }

        return 'guest_' . $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get feedback queue for admin review
     *
     * @param array $filters Filters
     * @param int $page Page number
     * @param int $per_page Items per page
     * @return array Feedback items
     */
    public function get_review_queue(array $filters = [], int $page = 1, int $per_page = 20): array
    {
        global $wpdb;

        $where = ['1=1'];
        $where_values = [];

        // Filter by status
        if (isset($filters['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $filters['status'];
        } else {
            $where[] = 'status = %s';
            $where_values[] = 'pending';
        }

        // Filter by rating
        if (isset($filters['rating'])) {
            $where[] = 'rating = %s';
            $where_values[] = $filters['rating'];
        }

        // Filter by date range
        if (isset($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $where_values[] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $where_values[] = $filters['date_to'];
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($page - 1) * $per_page;

        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $total_query = $wpdb->prepare($total_query, $where_values);
        }
        $total = $wpdb->get_var($total_query);

        // Get items
        $items_query = "SELECT * FROM {$this->table_name} 
                        WHERE {$where_clause} 
                        ORDER BY created_at DESC 
                        LIMIT %d OFFSET %d";
        
        $where_values[] = $per_page;
        $where_values[] = $offset;
        
        $items_query = $wpdb->prepare($items_query, $where_values);
        $items = $wpdb->get_results($items_query, ARRAY_A);

        // Decode JSON fields
        foreach ($items as &$item) {
            $item['facts_used'] = json_decode($item['facts_used'], true);
            $item['context_data'] = json_decode($item['context_data'], true);
        }

        return [
            'items' => $items,
            'total' => (int)$total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ];
    }

    /**
     * Update feedback status
     *
     * @param int $feedback_id Feedback ID
     * @param string $status New status
     * @param string $admin_notes Admin notes
     * @return bool Success
     */
    public function update_feedback_status(int $feedback_id, string $status, string $admin_notes = ''): bool
    {
        global $wpdb;

        $allowed_statuses = ['pending', 'reviewed', 'resolved', 'dismissed'];
        
        if (!in_array($status, $allowed_statuses)) {
            return false;
        }

        $data = [
            'status' => $status,
            'reviewed_at' => current_time('mysql'),
            'reviewer_id' => get_current_user_id(),
        ];

        if ($admin_notes) {
            $data['admin_notes'] = $admin_notes;
        }

        $result = $wpdb->update(
            $this->table_name,
            $data,
            ['id' => $feedback_id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );

        if ($result !== false) {
            do_action('homa_feedback_status_updated', $feedback_id, $status, $admin_notes);
            return true;
        }

        return false;
    }

    /**
     * Get feedback by ID
     *
     * @param int $feedback_id Feedback ID
     * @return array|null Feedback data
     */
    public function get_feedback(int $feedback_id): ?array
    {
        global $wpdb;

        $feedback = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $feedback_id
            ),
            ARRAY_A
        );

        if ($feedback) {
            $feedback['facts_used'] = json_decode($feedback['facts_used'], true);
            $feedback['context_data'] = json_decode($feedback['context_data'], true);
            return $feedback;
        }

        return null;
    }

    /**
     * Get feedback statistics
     *
     * @param array $filters Filters
     * @return array Statistics
     */
    public function get_statistics(array $filters = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $where_values = [];

        if (isset($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $where_values[] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $where_values[] = $filters['date_to'];
        }

        $where_clause = implode(' AND ', $where);

        // Total feedback
        $total_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $total_query = $wpdb->prepare($total_query, $where_values);
        }
        $total = $wpdb->get_var($total_query);

        // Likes and dislikes
        $rating_query = "SELECT rating, COUNT(*) as count 
                         FROM {$this->table_name} 
                         WHERE {$where_clause} 
                         GROUP BY rating";
        if (!empty($where_values)) {
            $rating_query = $wpdb->prepare($rating_query, $where_values);
        }
        $ratings = $wpdb->get_results($rating_query, ARRAY_A);

        $likes = 0;
        $dislikes = 0;
        foreach ($ratings as $rating) {
            if ($rating['rating'] === 'like') {
                $likes = (int)$rating['count'];
            } elseif ($rating['rating'] === 'dislike') {
                $dislikes = (int)$rating['count'];
            }
        }

        // Status breakdown
        $status_query = "SELECT status, COUNT(*) as count 
                         FROM {$this->table_name} 
                         WHERE {$where_clause} 
                         GROUP BY status";
        if (!empty($where_values)) {
            $status_query = $wpdb->prepare($status_query, $where_values);
        }
        $statuses = $wpdb->get_results($status_query, ARRAY_A);

        $status_breakdown = [];
        foreach ($statuses as $status) {
            $status_breakdown[$status['status']] = (int)$status['count'];
        }

        return [
            'total' => (int)$total,
            'likes' => $likes,
            'dislikes' => $dislikes,
            'satisfaction_rate' => $total > 0 ? round(($likes / $total) * 100, 2) : 0,
            'status_breakdown' => $status_breakdown,
        ];
    }

    /**
     * Notify admin about negative feedback
     *
     * @param int $feedback_id Feedback ID
     * @return void
     */
    private function notify_admin_negative_feedback(int $feedback_id): void
    {
        // Send notification to admin
        $feedback = $this->get_feedback($feedback_id);
        
        if (!$feedback) {
            return;
        }

        // Use admin intervention system if available
        if (class_exists('\HomayeTabesh\HT_Admin_Intervention')) {
            $admin_intervention = HT_Admin_Intervention::instance();
            
            $message = sprintf(
                'بازخورد منفی جدید: %s',
                $feedback['error_details'] ?: 'بررسی نیاز است'
            );
            
            // Send to all admins
            $admins = get_users(['role' => 'administrator']);
            foreach ($admins as $admin) {
                // Notification logic here
            }
        }

        do_action('homa_negative_feedback_received', $feedback_id, $feedback);
    }

    /**
     * Create database table
     *
     * @return void
     */
    public function create_table(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            user_identifier varchar(255) NOT NULL,
            conversation_id varchar(100) NOT NULL,
            rating varchar(20) NOT NULL,
            response_text text NOT NULL,
            user_prompt text,
            error_details text,
            facts_used json DEFAULT NULL,
            context_data json DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            admin_notes text,
            reviewer_id bigint(20) DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY user_identifier (user_identifier),
            KEY conversation_id (conversation_id),
            KEY rating (rating),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Delete old feedback
     *
     * @param int $days Days to keep
     * @return int Number of deleted items
     */
    public function cleanup_old_feedback(int $days = 90): int
    {
        global $wpdb;

        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} 
                 WHERE created_at < %s AND status = 'resolved'",
                $date
            )
        );

        return $deleted ?: 0;
    }
}
