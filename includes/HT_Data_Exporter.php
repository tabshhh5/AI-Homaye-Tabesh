<?php
/**
 * Data Exporter - Knowledge Import/Export System
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * سیستم برونبری و درونریزی دانش با قابلیت Snapshot و Encryption
 */
class HT_Data_Exporter
{
    /**
     * Knowledge base instance
     */
    private ?HT_Knowledge_Base $knowledge_base = null;

    /**
     * Snapshots table name
     */
    private string $snapshots_table;

    /**
     * Export directory
     */
    private string $export_dir;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->snapshots_table = $wpdb->prefix . 'homa_snapshots';
        
        if (class_exists('\HomayeTabesh\HT_Knowledge_Base')) {
            $this->knowledge_base = new HT_Knowledge_Base();
        }

        // Set export directory in uploads
        $upload_dir = wp_upload_dir();
        $this->export_dir = $upload_dir['basedir'] . '/homa-exports';

        // Create directory if not exists
        if (!file_exists($this->export_dir)) {
            wp_mkdir_p($this->export_dir);
            // Add .htaccess to protect exports
            file_put_contents(
                $this->export_dir . '/.htaccess',
                "deny from all\n"
            );
        }
    }

    /**
     * Create snapshots table
     */
    public function create_table(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->snapshots_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            snapshot_name varchar(255) NOT NULL,
            description text,
            file_path varchar(500) NOT NULL,
            file_size bigint(20) DEFAULT NULL,
            facts_count int DEFAULT NULL,
            created_by bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            is_auto tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY is_auto (is_auto)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Export all knowledge to JSON
     *
     * @param string $description Optional export description
     * @param bool $encrypt Whether to encrypt the export
     * @return array Export result
     */
    public function export_knowledge(string $description = '', bool $encrypt = false): array
    {
        if (!$this->knowledge_base) {
            return ['success' => false, 'message' => 'Knowledge base not available'];
        }

        try {
            // Get all knowledge facts
            $facts = $this->knowledge_base->get_facts(['limit' => 999999]);

            // Get authority overrides
            $overrides = $this->get_authority_overrides();

            // Get firewall settings
            $firewall_settings = $this->get_firewall_settings();

            // Get plugin settings
            $plugin_settings = $this->get_plugin_settings();

            // Build export data
            $export_data = [
                'homa_version' => HT_VERSION ?? '1.0.0',
                'export_date' => current_time('mysql'),
                'site_url' => get_site_url(),
                'facts_count' => count($facts),
                'knowledge_base' => $facts,
                'authority_overrides' => $overrides,
                'firewall_settings' => $firewall_settings,
                'plugin_settings' => $plugin_settings,
                'export_metadata' => [
                    'wp_version' => get_bloginfo('version'),
                    'php_version' => PHP_VERSION,
                    'description' => $description,
                ],
            ];

            // Generate filename
            $timestamp = current_time('YmdHis');
            $filename = "homa-export-{$timestamp}.json";
            
            if ($encrypt) {
                $filename = "homa-export-{$timestamp}.enc";
            }

            $file_path = $this->export_dir . '/' . $filename;

            // Encode to JSON
            $json_data = wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            // Encrypt if requested
            if ($encrypt) {
                $json_data = $this->encrypt_data($json_data);
            }

            // Save to file
            $bytes_written = file_put_contents($file_path, $json_data);

            if ($bytes_written === false) {
                return ['success' => false, 'message' => 'Failed to write export file'];
            }

            // Save snapshot record
            $snapshot_id = $this->save_snapshot_record(
                $filename,
                $description,
                $file_path,
                $bytes_written,
                count($facts)
            );

            return [
                'success' => true,
                'message' => 'Export completed successfully',
                'snapshot_id' => $snapshot_id,
                'file_path' => $file_path,
                'filename' => $filename,
                'file_size' => $bytes_written,
                'facts_count' => count($facts),
                'encrypted' => $encrypt,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Import knowledge from JSON
     *
     * @param string $file_path Path to import file
     * @param string $mode Import mode: 'merge' or 'replace'
     * @param bool $is_encrypted Whether file is encrypted
     * @return array Import result
     */
    public function import_knowledge(string $file_path, string $mode = 'merge', bool $is_encrypted = false): array
    {
        if (!$this->knowledge_base) {
            return ['success' => false, 'message' => 'Knowledge base not available'];
        }

        if (!file_exists($file_path)) {
            return ['success' => false, 'message' => 'Import file not found'];
        }

        try {
            // Create auto-snapshot before import
            $snapshot = $this->create_auto_snapshot('Before import');

            // Read file
            $json_data = file_get_contents($file_path);

            // Decrypt if needed
            if ($is_encrypted) {
                $json_data = $this->decrypt_data($json_data);
                if ($json_data === false) {
                    return ['success' => false, 'message' => 'Failed to decrypt file'];
                }
            }

            // Decode JSON
            $import_data = json_decode($json_data, true);

            if ($import_data === null) {
                return ['success' => false, 'message' => 'Invalid JSON format'];
            }

            // Validate structure
            if (!isset($import_data['knowledge_base'])) {
                return ['success' => false, 'message' => 'Invalid export structure'];
            }

            $imported_count = 0;
            $skipped_count = 0;
            $updated_count = 0;

            // Import knowledge facts
            foreach ($import_data['knowledge_base'] as $fact) {
                if ($mode === 'replace') {
                    // Check if fact exists
                    $existing = $this->knowledge_base->get_fact_by_key($fact['fact_key']);
                    if ($existing) {
                        // Update existing
                        $this->knowledge_base->update_fact($existing['id'], [
                            'fact_value' => $fact['fact_value'],
                            'category' => $fact['category'],
                            'is_active' => $fact['is_active'] ?? 1,
                        ]);
                        $updated_count++;
                    } else {
                        // Add new
                        $this->knowledge_base->add_fact(
                            $fact['fact_key'],
                            $fact['fact_value'],
                            $fact['category'] ?? 'imported'
                        );
                        $imported_count++;
                    }
                } else {
                    // Merge mode: only add if not exists
                    $existing = $this->knowledge_base->get_fact_by_key($fact['fact_key']);
                    if (!$existing) {
                        $this->knowledge_base->add_fact(
                            $fact['fact_key'],
                            $fact['fact_value'],
                            $fact['category'] ?? 'imported'
                        );
                        $imported_count++;
                    } else {
                        $skipped_count++;
                    }
                }
            }

            // Import authority overrides if present
            if (!empty($import_data['authority_overrides']) && $mode === 'replace') {
                $this->import_authority_overrides($import_data['authority_overrides']);
            }

            // Invalidate caches
            if (class_exists('\HomayeTabesh\HT_Query_Optimizer')) {
                $optimizer = new HT_Query_Optimizer();
                $optimizer->invalidate_knowledge_cache();
            }

            return [
                'success' => true,
                'message' => 'Import completed successfully',
                'imported_count' => $imported_count,
                'updated_count' => $updated_count,
                'skipped_count' => $skipped_count,
                'snapshot_id' => $snapshot['snapshot_id'] ?? null,
                'mode' => $mode,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create automatic snapshot
     *
     * @param string $description Snapshot description
     * @return array Snapshot result
     */
    public function create_auto_snapshot(string $description = 'Auto snapshot'): array
    {
        $result = $this->export_knowledge($description, false);
        
        if ($result['success']) {
            global $wpdb;
            // Mark as auto snapshot
            $wpdb->update(
                $this->snapshots_table,
                ['is_auto' => 1],
                ['id' => $result['snapshot_id']],
                ['%d'],
                ['%d']
            );
        }

        return $result;
    }

    /**
     * Restore from snapshot
     *
     * @param int $snapshot_id Snapshot ID
     * @return array Restore result
     */
    public function restore_snapshot(int $snapshot_id): array
    {
        global $wpdb;

        // Get snapshot record
        $snapshot = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->snapshots_table} WHERE id = %d", $snapshot_id),
            ARRAY_A
        );

        if (!$snapshot) {
            return ['success' => false, 'message' => 'Snapshot not found'];
        }

        // Check if file exists
        if (!file_exists($snapshot['file_path'])) {
            return ['success' => false, 'message' => 'Snapshot file not found'];
        }

        // Import with replace mode
        return $this->import_knowledge($snapshot['file_path'], 'replace', false);
    }

    /**
     * Get all snapshots
     *
     * @param array $filters Filter criteria
     * @return array Snapshots
     */
    public function get_snapshots(array $filters = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $values = [];

        if (isset($filters['is_auto'])) {
            $where[] = 'is_auto = %d';
            $values[] = $filters['is_auto'] ? 1 : 0;
        }

        $limit = absint($filters['limit'] ?? 50);
        $offset = absint($filters['offset'] ?? 0);

        $where_sql = implode(' AND ', $where);
        $query = "SELECT * FROM {$this->snapshots_table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = $limit;
        $values[] = $offset;

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Delete snapshot
     *
     * @param int $snapshot_id Snapshot ID
     * @return bool Success
     */
    public function delete_snapshot(int $snapshot_id): bool
    {
        global $wpdb;

        // Get snapshot record
        $snapshot = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->snapshots_table} WHERE id = %d", $snapshot_id),
            ARRAY_A
        );

        if (!$snapshot) {
            return false;
        }

        // Delete file
        if (file_exists($snapshot['file_path'])) {
            unlink($snapshot['file_path']);
        }

        // Delete record
        $result = $wpdb->delete(
            $this->snapshots_table,
            ['id' => $snapshot_id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Save snapshot record to database
     */
    private function save_snapshot_record(string $filename, string $description, string $file_path, int $file_size, int $facts_count): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->snapshots_table,
            [
                'snapshot_name' => $filename,
                'description' => $description,
                'file_path' => $file_path,
                'file_size' => $file_size,
                'facts_count' => $facts_count,
                'created_by' => get_current_user_id(),
            ],
            ['%s', '%s', '%s', '%d', '%d', '%d']
        );

        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * Get authority overrides for export
     */
    private function get_authority_overrides(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homa_authority_overrides';
        
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$table_exists) {
            return [];
        }

        return $wpdb->get_results("SELECT * FROM {$table} WHERE is_active = 1", ARRAY_A) ?: [];
    }

    /**
     * Get firewall settings for export
     */
    private function get_firewall_settings(): array
    {
        return [
            'blocked_patterns' => get_option('ht_firewall_patterns', []),
            'threat_threshold' => get_option('ht_threat_threshold', 80),
            'auto_block' => get_option('ht_firewall_auto_block', true),
        ];
    }

    /**
     * Get plugin settings for export
     */
    private function get_plugin_settings(): array
    {
        return [
            'gemini_model' => get_option('ht_gemini_model', 'gemini-2.0-flash-exp'),
            'support_phone' => get_option('ht_support_phone', ''),
            'support_email' => get_option('ht_support_email', ''),
            'persona_name' => get_option('ht_persona_name', 'هما'),
        ];
    }

    /**
     * Import authority overrides
     */
    private function import_authority_overrides(array $overrides): void
    {
        if (!class_exists('\HomayeTabesh\HT_Authority_Manager')) {
            return;
        }

        $authority = new HT_Authority_Manager();
        foreach ($overrides as $override) {
            $authority->set_manual_override(
                $override['override_key'],
                $override['override_value'],
                $override['reason'] ?? ''
            );
        }
    }

    /**
     * Encrypt data
     * 
     * Note: This implementation uses basic AES-256-CBC encryption.
     * For enhanced security in production, consider using authenticated encryption
     * (AEAD modes) or adding HMAC verification.
     */
    private function encrypt_data(string $data): string
    {
        // TODO: Add HMAC authentication to prevent padding oracle attacks
        // Use WordPress secret key as encryption key
        $key = substr(wp_salt('auth'), 0, 32);
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        // Prepend IV to encrypted data
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data
     * 
     * Note: This implementation uses basic AES-256-CBC decryption.
     * For enhanced security in production, consider using authenticated encryption
     * (AEAD modes) or adding HMAC verification.
     */
    private function decrypt_data(string $data): string|false
    {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $key = substr(wp_salt('auth'), 0, 32);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Clean old auto snapshots (keep last 10)
     */
    public function clean_old_snapshots(): int
    {
        global $wpdb;

        // Get auto snapshots older than the 10 most recent
        $old_snapshots = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, file_path FROM {$this->snapshots_table} 
                 WHERE is_auto = 1 
                 ORDER BY created_at DESC 
                 LIMIT 999999 OFFSET %d",
                10
            ),
            ARRAY_A
        );

        $deleted_count = 0;
        foreach ($old_snapshots as $snapshot) {
            if ($this->delete_snapshot($snapshot['id'])) {
                $deleted_count++;
            }
        }

        return $deleted_count;
    }
}
