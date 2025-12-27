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
    private static function create_tables(): void
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
                user_identifier varchar(100) NOT NULL,
                threat_score int(11) DEFAULT 0,
                last_threat_type varchar(50) DEFAULT NULL,
                blocked_attempts int(11) DEFAULT 0,
                last_activity datetime DEFAULT CURRENT_TIMESTAMP,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
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
                metadata json DEFAULT NULL,
                last_scanned datetime DEFAULT CURRENT_TIMESTAMP,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY plugin_slug (plugin_slug),
                KEY is_active (is_active),
                KEY last_scanned (last_scanned)
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
            'homaye_security_scores',
            'homaye_security_events',
            'homaye_indexed_pages',
            'homaye_monitored_plugins',
            'homa_otp',
            'homa_translations',
        ];
        
        $missing_tables = [];
        
        // Check which tables are missing
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
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
                
                return true;
            } catch (\Throwable $e) {
                \HomayeTabesh\HT_Error_Handler::log_exception($e, 'database_self_healing');
                return false;
            }
        }
        
        return true;
    }
}
