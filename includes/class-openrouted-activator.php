<?php
/**
 * Fired during plugin activation.
 *
 * @since      1.0.0
 * @package    Openrouted
 * @subpackage Openrouted/includes
 */

class Openrouted_Activator {

    /**
     * Creates necessary database tables.
     *
     * @since    1.0.0
     */
    public static function activate() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'openrouted';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            image_id bigint(20) NOT NULL,
            alt_text text NOT NULL,
            status varchar(20) DEFAULT 'pending' NOT NULL,
            applied_timestamp datetime DEFAULT NULL,
            initial_payload text,
            response text,
            duration float,
            PRIMARY KEY  (id),
            KEY image_id (image_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Add default options
        add_option('openrouted_api_key', '');
        add_option('openrouted_mode', 'manual');
        add_option('openrouted_custom_instructions', '');
        add_option('openrouted_preserve_data', 'no');
        
        // Add advanced scheduling options
        add_option('openrouted_schedule_frequency', 'daily');
        add_option('openrouted_batch_size', '20');
        add_option('openrouted_max_runtime', '10');
        add_option('openrouted_request_delay', '2');
        
        // First clear any existing schedules to avoid duplicates
        $timestamp = wp_next_scheduled('openrouted_daily_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'openrouted_daily_check');
        }
        
        // Force clear all hooks with this name (more thorough approach)
        wp_clear_scheduled_hook('openrouted_daily_check');
        
        // Schedule cron job based on frequency setting
        $frequency = get_option('openrouted_schedule_frequency', 'daily');
        $start_time = time();
        
        // Schedule the recurring event
        wp_schedule_event($start_time, $frequency, 'openrouted_daily_check');
    }
}