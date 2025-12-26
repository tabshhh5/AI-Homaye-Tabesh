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
        // Create database tables
        self::create_tables();

        // Set default options
        self::set_default_options();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create required database tables
     *
     * @return void
     */
    private static function create_tables(): void
    {
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
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
}
