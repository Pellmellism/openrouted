<?php
/**
 * Fired during plugin deactivation.
 *
 * @since      1.0.0
 * @package    Openrouted
 * @subpackage Openrouted/includes
 */

class Openrouted_Deactivator {

    /**
     * Cleans up when plugin is deactivated.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('openrouted_daily_check');
        
        // Note: We don't remove tables or options during deactivation
        // to preserve user data. Use uninstall.php for complete removal.
    }
}