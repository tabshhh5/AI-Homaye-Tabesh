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
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
}
