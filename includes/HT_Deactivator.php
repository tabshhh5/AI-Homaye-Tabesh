<?php
/**
 * Plugin Deactivator
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * Handles plugin deactivation
 */
class HT_Deactivator
{
    /**
     * Run deactivation procedures
     *
     * @return void
     */
    public static function deactivate(): void
    {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Clear any scheduled events
        wp_clear_scheduled_hook('ht_cleanup_old_events');
    }
}
