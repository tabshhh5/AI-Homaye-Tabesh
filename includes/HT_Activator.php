<?php
/**
 * Plugin Activator
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * Handles plugin activation
 */
class HT_Activator
{
    /**
     * Run activation procedures
     *
     * @return void
     */
    public static function activate(): void
    {
        try {
            // Create database tables
            self::create_tables();

            // Set default options
            self::set_default_options();

            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Run health check and store results for display on next admin page load
            $health_report = self::run_health_check();
            set_transient('homa_activation_health_report', $health_report, 300); // 5 minutes
            
            \HomayeTabesh\HT_Error_Handler::log_error('Plugin activated successfully', 'activation');
        } catch (\Throwable $e) {
            \HomayeTabesh\HT_Error_Handler::log_exception($e, 'activation');
            throw $e; // Re-throw to show error to user
        }
    }

    /**
     * Create required database tables
     *
     * @return void
     */
    public static function create_tables(): void
    {
        try {
            global $wpdb;

            $charset_collate = $wpdb->get_charset_collate();
            $table_name = $wpdb->prefix . 'homaye_persona_scores';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_identifier varchar(255) NOT NULL,
                persona_type varchar(50) NOT NULL,
                score int(11) NOT NULL DEFAULT 0,
                session_data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY user_identifier (user_identifier),
                KEY persona_type (persona_type)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            // Create telemetry events table
            $table_name = $wpdb->prefix . 'homaye_telemetry_events';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_identifier varchar(255) NOT NULL,
                event_type varchar(50) NOT NULL,
                element_class varchar(255),
                element_data longtext,
                timestamp datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY user_identifier (user_identifier),
                KEY event_type (event_type),
                KEY timestamp (timestamp)
            ) $charset_collate;";

            dbDelta($sql);

            // Create conversion sessions table (PR5)
            $table_name = $wpdb->prefix . 'homaye_conversion_sessions';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_identifier varchar(255) NOT NULL,
                session_data longtext NOT NULL,
                form_completion int(11) DEFAULT 0,
                cart_value decimal(10,2) DEFAULT 0.00,
                conversion_status varchar(50) DEFAULT 'in_progress',
                order_id bigint(20) UNSIGNED DEFAULT 0,
                last_activity datetime DEFAULT CURRENT_TIMESTAMP,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                completed_at datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY user_identifier (user_identifier),
                KEY conversion_status (conversion_status),
                KEY last_activity (last_activity)
            ) $charset_collate;";

            dbDelta($sql);

            // Create Omni-Store vault table (PR7)
            $table_name = $wpdb->prefix . 'homa_vault';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) DEFAULT NULL,
                session_token varchar(100) NOT NULL,
                context_key varchar(50) NOT NULL,
                context_value json,
                ai_insight text,
                updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY session_token (session_token),
                KEY context_key (context_key),
                KEY updated_at (updated_at)
            ) $charset_collate;";

            dbDelta($sql);

            // Create Omni-Store sessions table (PR7)
            $table_name = $wpdb->prefix . 'homa_sessions';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                session_token varchar(100) NOT NULL,
                last_url text,
                form_snapshot json DEFAULT NULL,
                chat_summary text,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                expires_at datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY session_token (session_token),
                KEY updated_at (updated_at),
                KEY expires_at (expires_at)
            ) $charset_collate;";

            dbDelta($sql);

            // Create Omni-Store user interests table (PR7)
            $table_name = $wpdb->prefix . 'homa_user_interests';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id_or_token varchar(100) NOT NULL,
                category_slug varchar(50) NOT NULL,
                interest_score int(11) DEFAULT 0,
                source_referral varchar(50) DEFAULT 'organic',
                last_interaction timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY user_category (user_id_or_token, category_slug),
                KEY user_id_or_token (user_id_or_token),
                KEY category_slug (category_slug),
                KEY interest_score (interest_score)
            ) $charset_collate;";

            dbDelta($sql);

            // Create Smart Lead Conversion table (PR11)
            $table_name = $wpdb->prefix . 'homa_leads';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) DEFAULT NULL,
                user_id_or_token varchar(100) NOT NULL,
                lead_score int(11) DEFAULT 0,
                lead_status varchar(50) DEFAULT 'new',
                requirements_summary json DEFAULT NULL,
                contact_info varchar(100) DEFAULT NULL,
                contact_name varchar(100) DEFAULT NULL,
                source_referral varchar(50) DEFAULT 'organic',
                draft_order_id bigint(20) DEFAULT NULL,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY user_id_or_token (user_id_or_token),
                KEY lead_score (lead_score),
                KEY lead_status (lead_status),
                KEY created_at (created_at)
            ) $charset_collate;";

            dbDelta($sql);

            // Create Legacy Leads table (for backward compatibility)
            // Note: This maintains compatibility with older versions that used homaye_leads
            // The new table homa_leads (created above) is the primary table going forward
            $table_name = $wpdb->prefix . 'homaye_leads';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) DEFAULT NULL,
                user_identifier varchar(100) NOT NULL,
                lead_score int(11) DEFAULT 0,
                lead_status varchar(50) DEFAULT 'new',
                contact_info varchar(100) DEFAULT NULL,
                contact_name varchar(100) DEFAULT NULL,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY user_identifier (user_identifier),
                KEY lead_score (lead_score),
                KEY created_at (created_at)
            ) $charset_collate;";

            dbDelta($sql);

            // Create AI Requests Analytics table (PR21)
            $table_name = $wpdb->prefix . 'homaye_ai_requests';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                request_type varchar(50) NOT NULL,
                user_identifier varchar(100) DEFAULT NULL,
                prompt_text text DEFAULT NULL,
                response_text text DEFAULT NULL,
                tokens_used int(11) DEFAULT 0,
                latency_ms int(11) DEFAULT 0,
                status varchar(20) DEFAULT 'success',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY request_type (request_type),
                KEY user_identifier (user_identifier),
                KEY status (status),
                KEY created_at (created_at)
            ) $charset_collate;";

            dbDelta($sql);

            // Create Knowledge Base table (PR21)
            $table_name = $wpdb->prefix . 'homaye_knowledge';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                fact_key varchar(100) NOT NULL,
                fact_value text NOT NULL,
                fact_category varchar(50) DEFAULT 'general',
                authority_level int(11) DEFAULT 0,
                source varchar(100) DEFAULT 'system',
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY fact_key (fact_key),
                KEY fact_category (fact_category),
                KEY is_active (is_active),
                KEY authority_level (authority_level)
            ) $charset_collate;";

            dbDelta($sql);

            // Create Security Scores table (PR21)
            $table_name = $wpdb->prefix . 'homaye_security_scores';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) DEFAULT NULL,
                user_identifier varchar(100) NOT NULL,
                threat_score int(11) DEFAULT 0,
                last_threat_type varchar(50) DEFAULT NULL,
                blocked_attempts int(11) DEFAULT 0,
                last_activity datetime DEFAULT CURRENT_TIMESTAMP,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                UNIQUE KEY user_identifier (user_identifier),
                KEY threat_score (threat_score),
                KEY last_activity (last_activity)
            ) $charset_collate;";

            dbDelta($sql);

            // Create Security Events table (for security incident tracking)
            $table_name = $wpdb->prefix . 'homaye_security_events';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_identifier varchar(100) NOT NULL,
                event_type varchar(50) NOT NULL,
                event_description text DEFAULT NULL,
                severity varchar(20) DEFAULT 'low',
                ip_address varchar(45) DEFAULT NULL,
                user_agent text DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY user_identifier (user_identifier),
                KEY event_type (event_type),
                KEY severity (severity),
                KEY created_at (created_at)
            ) $charset_collate;";

            dbDelta($sql);

            // Create Indexed Pages table (for content indexing)
            $table_name = $wpdb->prefix . 'homaye_indexed_pages';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                page_id bigint(20) NOT NULL,
                page_title text NOT NULL,
                page_content longtext DEFAULT NULL,
                page_url varchar(255) DEFAULT NULL,
                indexed_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY page_id (page_id),
                KEY indexed_at (indexed_at),
                KEY updated_at (updated_at)
            ) $charset_collate;";

            dbDelta($sql);

            // Create OTP verification table (PR11)
            $table_name = $wpdb->prefix . 'homa_otp';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                phone_number varchar(20) NOT NULL,
                otp_code varchar(10) NOT NULL,
                session_token varchar(100) DEFAULT NULL,
                attempts int(11) DEFAULT 0,
                is_verified tinyint(1) DEFAULT 0,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                expires_at timestamp DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY phone_number (phone_number),
                KEY otp_code (otp_code),
                KEY expires_at (expires_at)
            ) $charset_collate;";

            dbDelta($sql);

            // Create Translation Cache table (PR14)
            $table_name = $wpdb->prefix . 'homa_translations';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                text_hash varchar(64) NOT NULL,
                original_text varchar(1000) NOT NULL,
                translated_text text NOT NULL,
                lang varchar(5) NOT NULL DEFAULT 'ar',
                is_valid tinyint(1) DEFAULT 1,
                use_count int(11) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                last_used datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY text_hash_lang (text_hash, lang),
                KEY lang_valid (lang, is_valid),
                KEY use_count (use_count),
                KEY last_used (last_used)
            ) $charset_collate;";

            dbDelta($sql);

            // Create Monitored Plugins table (for plugin metadata tracking)
            $table_name = $wpdb->prefix . 'homaye_monitored_plugins';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                plugin_slug varchar(255) NOT NULL,
                plugin_name varchar(255) NOT NULL,
                plugin_version varchar(50) DEFAULT NULL,
                is_active tinyint(1) DEFAULT 1,
                is_monitored tinyint(1) DEFAULT 0,
                metadata json DEFAULT NULL,
                last_scanned datetime DEFAULT CURRENT_TIMESTAMP,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY plugin_slug (plugin_slug),
                KEY is_active (is_active),
                KEY is_monitored (is_monitored),
                KEY last_scanned (last_scanned)
            ) $charset_collate;";

            dbDelta($sql);

            // Create Knowledge Facts table (for console analytics)
            // Note: Both 'fact' and 'fact_key'/'fact_value' columns exist for backward compatibility
            // - 'fact' is the main column used by queries (text content)
            // - 'fact_key'/'fact_value' are legacy columns kept for data migration
            $table_name = $wpdb->prefix . 'homaye_knowledge_facts';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                fact text NOT NULL,
                category varchar(50) DEFAULT 'general',
                fact_key varchar(100) DEFAULT NULL,
                fact_value text DEFAULT NULL,
                authority_level int(11) DEFAULT 0,
                source varchar(100) DEFAULT 'system',
                is_active tinyint(1) DEFAULT 1,
                verified tinyint(1) DEFAULT 0,
                tags text DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY category (category),
                KEY fact_key (fact_key),
                KEY is_active (is_active),
                KEY verified (verified),
                KEY authority_level (authority_level)
            ) $charset_collate;";

            dbDelta($sql);

            // Create IP Blacklist table (for WAF engine)
            $table_name = $wpdb->prefix . 'homaye_blacklist';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                ip_address varchar(45) NOT NULL,
                reason varchar(255) DEFAULT NULL,
                threat_type varchar(50) DEFAULT NULL,
                blocked_at datetime DEFAULT CURRENT_TIMESTAMP,
                expires_at datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY ip_address (ip_address),
                KEY blocked_at (blocked_at),
                KEY expires_at (expires_at)
            ) $charset_collate;";

            dbDelta($sql);

            // Create User Behavior Tracking table (with current_score column)
            $table_name = $wpdb->prefix . 'homaye_user_behavior';

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_identifier varchar(255) NOT NULL,
                ip_address varchar(45) NOT NULL,
                fingerprint varchar(64) DEFAULT NULL,
                event_type varchar(50) NOT NULL,
                event_data text,
                penalty_points int(11) DEFAULT 0,
                current_score int(11) DEFAULT 100,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY user_identifier (user_identifier),
                KEY ip_address (ip_address),
                KEY event_type (event_type),
                KEY current_score (current_score),
                KEY created_at (created_at)
            ) $charset_collate;";

            dbDelta($sql);

            // Create Authority Manager overrides table (PR17)
            if (class_exists('\HomayeTabesh\HT_Authority_Manager')) {
                $authority_manager = new HT_Authority_Manager();
                $authority_manager->create_table();
            }

            // Create Feedback System table (PR17)
            if (class_exists('\HomayeTabesh\HT_Feedback_System')) {
                $feedback_system = new HT_Feedback_System();
                $feedback_system->create_table();
            }

            // Create BlackBox Logger table (PR18)
            if (class_exists('\HomayeTabesh\HT_BlackBox_Logger')) {
                $logger = new HT_BlackBox_Logger();
                $logger->create_table();
            }

            // Create Fallback Engine leads table (PR18)
            if (class_exists('\HomayeTabesh\HT_Fallback_Engine')) {
                $engine = new HT_Fallback_Engine();
                $engine->create_table();
            }

            // Create Data Exporter snapshots table (PR18)
            if (class_exists('\HomayeTabesh\HT_Data_Exporter')) {
                $exporter = new HT_Data_Exporter();
                $exporter->create_table();
            }

            // Create Background Processor jobs table (PR18)
            if (class_exists('\HomayeTabesh\HT_Background_Processor')) {
                $processor = new HT_Background_Processor();
                $processor->create_table();
            }

            // Create Auto Cleanup reports table (PR18)
            if (class_exists('\HomayeTabesh\HT_Auto_Cleanup')) {
                $cleanup = new HT_Auto_Cleanup();
                $cleanup->create_table();
            }

            // Add indexes for performance (PR18)
            if (class_exists('\HomayeTabesh\HT_Query_Optimizer')) {
                $optimizer = new HT_Query_Optimizer();
                $optimizer->add_indexes();
            }
            
            // Set database version for future migrations
            update_option('homa_db_version', HT_VERSION);
            update_option('homa_db_last_update', current_time('mysql'));
            
            \HomayeTabesh\HT_Error_Handler::log_error('Database tables created successfully', 'activation');
        } catch (\Throwable $e) {
            \HomayeTabesh\HT_Error_Handler::log_exception($e, 'activation_create_tables');
            throw $e; // Re-throw to show error to user
        }
    }

    /**
     * Set default plugin options
     *
     * @return void
     */
    private static function set_default_options(): void
    {
        $defaults = [
            'ht_gemini_api_key' => '',
            'ht_tracking_enabled' => true,
            'ht_divi_integration' => true,
            'ht_min_score_threshold' => 50,
            // MeliPayamak settings (PR11)
            'ht_melipayamak_username' => '',
            'ht_melipayamak_password' => '',
            'ht_melipayamak_from_number' => '',
            'ht_melipayamak_otp_pattern' => '',
            'ht_melipayamak_lead_notification_pattern' => '',
            'ht_admin_phone_number' => '',
            'ht_lead_notification_enabled' => true,
            'ht_lead_hot_score_threshold' => 70,
            // Smart Diplomacy settings (PR14)
            'ht_translation_enabled' => true,
            'ht_arabic_countries' => HT_GeoLocation_Service::get_default_arabic_countries(),
            'ht_show_translation_popup' => true,
            'ht_auto_translate_arabic_visitors' => false,
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Check and repair database tables (Self-Healing)
     * This method can be called from admin_init to ensure tables exist
     *
     * @return bool True if all tables exist or were created successfully
     */
    public static function check_and_repair_database(): bool
    {
        global $wpdb;
        
        // List of critical tables that must exist
        $required_tables = [
            'homaye_persona_scores',
            'homaye_telemetry_events',
            'homaye_conversion_sessions',
            'homa_vault',
            'homa_sessions',
            'homa_user_interests',
            'homa_leads',
            'homaye_leads',
            'homaye_ai_requests',
            'homaye_knowledge',
            'homaye_knowledge_facts',
            'homaye_security_scores',
            'homaye_security_events',
            'homaye_indexed_pages',
            'homaye_monitored_plugins',
            'homaye_blacklist',
            'homaye_user_behavior',
            'homa_otp',
            'homa_translations',
        ];
        
        $missing_tables = [];
        
        // Check which tables are missing
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            // Table name is from trusted source (wpdb->prefix + hardcoded table name)
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
                $missing_tables[] = $table;
            }
        }
        
        // If any tables are missing, recreate all tables
        if (!empty($missing_tables)) {
            try {
                \HomayeTabesh\HT_Error_Handler::log_error(
                    'Self-healing: Missing tables detected: ' . implode(', ', $missing_tables),
                    'database_repair'
                );
                
                // Call create_tables to rebuild all tables
                self::create_tables();
                
                // Store repair info for admin notice
                set_transient('homa_db_repairs_made', [
                    'tables' => $missing_tables,
                    'columns' => [],
                ], 60); // 1 minute
                
                return true;
            } catch (\Throwable $e) {
                \HomayeTabesh\HT_Error_Handler::log_exception($e, 'database_self_healing');
                return false;
            }
        }
        
        // Also check for missing columns in existing tables
        $added_columns = self::check_and_add_missing_columns();
        
        // If columns were added, store info for admin notice
        if (!empty($added_columns)) {
            set_transient('homa_db_repairs_made', [
                'tables' => [],
                'columns' => $added_columns,
            ], 60); // 1 minute
        }
        
        return true;
    }

    /**
     * Check and add missing columns to existing tables
     * This handles schema updates without dropping tables
     *
     * @return array List of added columns
     */
    private static function check_and_add_missing_columns(): array
    {
        global $wpdb;
        
        $added_columns = [];
        
        try {
            // Define expected columns for each table with comprehensive schema
            $table_columns = [
                'homaye_monitored_plugins' => [
                    'is_monitored' => 'tinyint(1) DEFAULT 0',
                    'is_active' => 'tinyint(1) DEFAULT 1',
                    'plugin_version' => 'varchar(50) DEFAULT NULL',
                    'metadata' => 'json DEFAULT NULL',
                ],
                'homaye_knowledge_facts' => [
                    'verified' => 'tinyint(1) DEFAULT 0',
                    'category' => 'varchar(50) DEFAULT \'general\'',
                    'fact' => 'text DEFAULT NULL',
                    'is_active' => 'tinyint(1) DEFAULT 1',
                    'tags' => 'text DEFAULT NULL',
                ],
                'homaye_knowledge' => [
                    'fact_category' => 'varchar(50) DEFAULT \'general\'',
                    'is_active' => 'tinyint(1) DEFAULT 1',
                ],
                'homaye_security_scores' => [
                    'user_id' => 'bigint(20) DEFAULT NULL',
                    'threat_score' => 'int(11) DEFAULT 0',
                ],
                'homaye_user_behavior' => [
                    'current_score' => 'int(11) DEFAULT 100',
                ],
                'homa_otp' => [
                    'is_verified' => 'tinyint(1) DEFAULT 0',
                ],
                'homa_leads' => [
                    'lead_status' => 'varchar(50) DEFAULT \'new\'',
                ],
                'homaye_leads' => [
                    'lead_status' => 'varchar(50) DEFAULT \'new\'',
                ],
                'homaye_conversion_sessions' => [
                    'conversion_status' => 'varchar(50) DEFAULT \'in_progress\'',
                ],
                'homaye_ai_requests' => [
                    'status' => 'varchar(20) DEFAULT \'success\'',
                ],
                'homa_user_interests' => [
                    'interest_score' => 'int(11) DEFAULT 0',
                    'category_slug' => 'varchar(50) NOT NULL',
                ],
            ];
            
            foreach ($table_columns as $table => $columns) {
                $table_name = $wpdb->prefix . $table;
                
                // Check if table exists - use prepare for consistency
                $check_table = $wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
                if ($wpdb->get_var($check_table) != $table_name) {
                    continue; // Skip if table doesn't exist
                }
                
                foreach ($columns as $column => $definition) {
                    // Check if column exists
                    $column_exists = $wpdb->get_results(
                        $wpdb->prepare(
                            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
                            $column
                        )
                    );
                    
                    if (empty($column_exists)) {
                        // Add missing column
                        // Note: ALTER TABLE cannot use prepare() as it doesn't support
                        // placeholders for table/column names. Table name is from internal
                        // config only, not user input.
                        $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `{$column}` {$definition}");
                        
                        $added_columns[] = "{$table}.{$column}";
                        
                        \HomayeTabesh\HT_Error_Handler::log_error(
                            "Self-healing: Added missing column '{$column}' to table '{$table}'",
                            'database_repair'
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            \HomayeTabesh\HT_Error_Handler::log_exception($e, 'database_column_repair');
        }
        
        return $added_columns;
    }

    /**
     * Ensure all required tables exist
     * This method checks table existence using SHOW TABLES and creates them if missing
     * Called during settings load to ensure database integrity
     *
     * @return void
     */
    public static function ensure_tables_exist(): void
    {
        try {
            global $wpdb;
            
            // Get list of plugin tables using two separate queries for efficiency
            $homaye_tables = $wpdb->get_col($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($wpdb->prefix . 'homaye_') . '%'));
            $homa_tables = $wpdb->get_col($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($wpdb->prefix . 'homa_') . '%'));
            $existing_tables = array_merge($homaye_tables, $homa_tables);
            
            // Define required tables
            $required_tables = [
                $wpdb->prefix . 'homaye_persona_scores',
                $wpdb->prefix . 'homaye_telemetry_events',
                $wpdb->prefix . 'homaye_conversion_sessions',
                $wpdb->prefix . 'homa_vault',
                $wpdb->prefix . 'homa_sessions',
                $wpdb->prefix . 'homa_user_interests',
                $wpdb->prefix . 'homa_leads',
                $wpdb->prefix . 'homaye_leads',
                $wpdb->prefix . 'homaye_ai_requests',
                $wpdb->prefix . 'homaye_knowledge',
                $wpdb->prefix . 'homaye_security_scores',
                $wpdb->prefix . 'homaye_security_events',
            ];
            
            // Check which tables are missing
            $missing_tables = array_diff($required_tables, $existing_tables);
            
            if (!empty($missing_tables)) {
                // Tables are missing, recreate them
                \HomayeTabesh\HT_Error_Handler::log_error(
                    'Missing database tables detected: ' . implode(', ', $missing_tables) . '. Recreating...',
                    'database_integrity'
                );
                
                // Call create_tables to recreate missing tables
                self::create_tables();
            }
        } catch (\Throwable $e) {
            \HomayeTabesh\HT_Error_Handler::log_exception($e, 'ensure_tables_exist');
        }
    }

    /**
     * Comprehensive health check for plugin dependencies and database
     * Returns detailed report for admin display
     *
     * @return array Health check results with status and messages
     */
    public static function run_health_check(): array
    {
        $report = [
            'status' => 'healthy',
            'checks' => [],
            'errors' => [],
            'warnings' => [],
            'recommendations' => [],
        ];

        try {
            // Check PHP version
            $php_version = PHP_VERSION;
            $required_php = '8.2';
            if (version_compare($php_version, $required_php, '>=')) {
                $report['checks'][] = [
                    'name' => 'PHP Version',
                    'status' => 'pass',
                    'message' => "PHP {$php_version} (Required: {$required_php}+)",
                ];
            } else {
                $report['status'] = 'critical';
                $report['errors'][] = "PHP version {$php_version} is too old. Required: {$required_php}+";
                $report['checks'][] = [
                    'name' => 'PHP Version',
                    'status' => 'fail',
                    'message' => "PHP {$php_version} - Requires upgrade to {$required_php}+",
                ];
            }

            // Check WordPress version
            global $wp_version;
            $required_wp = '6.0';
            if (version_compare($wp_version, $required_wp, '>=')) {
                $report['checks'][] = [
                    'name' => 'WordPress Version',
                    'status' => 'pass',
                    'message' => "WordPress {$wp_version} (Required: {$required_wp}+)",
                ];
            } else {
                $report['status'] = 'critical';
                $report['errors'][] = "WordPress version {$wp_version} is too old. Required: {$required_wp}+";
                $report['checks'][] = [
                    'name' => 'WordPress Version',
                    'status' => 'fail',
                    'message' => "WordPress {$wp_version} - Requires upgrade to {$required_wp}+",
                ];
            }

            // Check database tables
            global $wpdb;
            $required_tables = [
                'homaye_persona_scores',
                'homaye_telemetry_events',
                'homaye_conversion_sessions',
                'homa_vault',
                'homa_sessions',
                'homa_user_interests',
                'homa_leads',
                'homaye_ai_requests',
                'homaye_knowledge',
                'homaye_security_scores',
                'homaye_monitored_plugins',
            ];

            $missing_tables = [];
            foreach ($required_tables as $table) {
                $table_name = $wpdb->prefix . $table;
                // Table name is from trusted source (wpdb->prefix + hardcoded table name)
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
                    $missing_tables[] = $table;
                }
            }

            if (empty($missing_tables)) {
                $report['checks'][] = [
                    'name' => 'Database Tables',
                    'status' => 'pass',
                    'message' => 'All required tables exist',
                ];
            } else {
                if ($report['status'] === 'healthy') {
                    $report['status'] = 'warning';
                }
                $report['warnings'][] = 'Missing tables: ' . implode(', ', $missing_tables);
                $report['checks'][] = [
                    'name' => 'Database Tables',
                    'status' => 'warning',
                    'message' => 'Missing ' . count($missing_tables) . ' tables - will be auto-created',
                ];
            }

            // Check WooCommerce (optional but recommended)
            if (class_exists('WooCommerce')) {
                $report['checks'][] = [
                    'name' => 'WooCommerce',
                    'status' => 'pass',
                    'message' => 'WooCommerce is active',
                ];
            } else {
                if ($report['status'] === 'healthy') {
                    $report['status'] = 'warning';
                }
                $report['warnings'][] = 'WooCommerce not detected - some features will be limited';
                $report['recommendations'][] = 'Install WooCommerce for full e-commerce features';
                $report['checks'][] = [
                    'name' => 'WooCommerce',
                    'status' => 'warning',
                    'message' => 'Not installed - e-commerce features limited',
                ];
            }

            // Check Gemini API key
            $api_key = get_option('ht_gemini_api_key', '');
            if (!empty($api_key)) {
                $report['checks'][] = [
                    'name' => 'Gemini API Key',
                    'status' => 'pass',
                    'message' => 'API key configured',
                ];
            } else {
                if ($report['status'] === 'healthy') {
                    $report['status'] = 'warning';
                }
                $report['warnings'][] = 'Gemini API key not configured';
                $report['recommendations'][] = 'Configure Gemini API key in plugin settings';
                $report['checks'][] = [
                    'name' => 'Gemini API Key',
                    'status' => 'warning',
                    'message' => 'Not configured - AI features disabled',
                ];
            }

            // Check write permissions
            $upload_dir = wp_upload_dir();
            if (wp_is_writable($upload_dir['basedir'])) {
                $report['checks'][] = [
                    'name' => 'File Permissions',
                    'status' => 'pass',
                    'message' => 'Upload directory is writable',
                ];
            } else {
                if ($report['status'] !== 'critical') {
                    $report['status'] = 'warning';
                }
                $report['warnings'][] = 'Upload directory is not writable';
                $report['checks'][] = [
                    'name' => 'File Permissions',
                    'status' => 'warning',
                    'message' => 'Upload directory not writable - may affect file operations',
                ];
            }

            // Check REST API availability
            $rest_url = rest_url('homaye/v1/health');
            $report['checks'][] = [
                'name' => 'REST API',
                'status' => 'info',
                'message' => 'Endpoints will be registered on next request',
            ];

        } catch (\Throwable $e) {
            $report['status'] = 'error';
            $report['errors'][] = 'Health check failed: ' . $e->getMessage();
            \HomayeTabesh\HT_Error_Handler::log_exception($e, 'health_check');
        }

        return $report;
    }

    /**
     * Display health check report as admin notice
     *
     * @param array $report Health check report from run_health_check()
     * @return void
     */
    public static function display_health_report(array $report): void
    {
        $status_classes = [
            'healthy' => 'notice-success',
            'warning' => 'notice-warning',
            'critical' => 'notice-error',
            'error' => 'notice-error',
        ];

        $status_class = $status_classes[$report['status']] ?? 'notice-info';
        $status_icons = [
            'healthy' => '✅',
            'warning' => '⚠️',
            'critical' => '❌',
            'error' => '❌',
        ];
        $status_icon = $status_icons[$report['status']] ?? 'ℹ️';

        echo '<div class="notice ' . esc_attr($status_class) . '">';
        echo '<h3>' . esc_html($status_icon) . ' ' . esc_html__('همای تابش - گزارش سلامت افزونه', 'homaye-tabesh') . '</h3>';

        if (!empty($report['errors'])) {
            echo '<h4>' . esc_html__('خطاهای بحرانی:', 'homaye-tabesh') . '</h4>';
            echo '<ul>';
            foreach ($report['errors'] as $error) {
                echo '<li style="color: #dc3232;">❌ ' . esc_html($error) . '</li>';
            }
            echo '</ul>';
        }

        if (!empty($report['warnings'])) {
            echo '<h4>' . esc_html__('هشدارها:', 'homaye-tabesh') . '</h4>';
            echo '<ul>';
            foreach ($report['warnings'] as $warning) {
                echo '<li style="color: #f0ad4e;">⚠️ ' . esc_html($warning) . '</li>';
            }
            echo '</ul>';
        }

        if (!empty($report['recommendations'])) {
            echo '<h4>' . esc_html__('توصیه‌ها:', 'homaye-tabesh') . '</h4>';
            echo '<ul>';
            foreach ($report['recommendations'] as $rec) {
                echo '<li>💡 ' . esc_html($rec) . '</li>';
            }
            echo '</ul>';
        }

        // Show summary of checks
        echo '<details style="margin-top: 10px;">';
        echo '<summary style="cursor: pointer;"><strong>' . esc_html__('جزئیات بررسی سلامت', 'homaye-tabesh') . '</strong></summary>';
        echo '<ul style="margin-top: 10px;">';
        foreach ($report['checks'] as $check) {
            $check_icon = $check['status'] === 'pass' ? '✅' : ($check['status'] === 'fail' ? '❌' : '⚠️');
            echo '<li>' . esc_html($check_icon) . ' <strong>' . esc_html($check['name']) . ':</strong> ' . esc_html($check['message']) . '</li>';
        }
        echo '</ul>';
        echo '</details>';

        echo '</div>';
    }
}
