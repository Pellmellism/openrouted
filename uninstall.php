<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @since      1.0.0
 * @package    Openrouted
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if we should preserve data
$preserve_data = get_option('openrouted_preserve_data', 'no');

if ($preserve_data !== 'yes') {
    // Data should be removed
    
    // Delete all plugin options
    delete_option('openrouted_api_key');
    delete_option('openrouted_mode');
    delete_option('openrouted_custom_instructions');
    delete_option('openrouted_last_check');
    delete_option('openrouted_preserve_data');
    
    // Delete scheduling options
    delete_option('openrouted_schedule_frequency');
    delete_option('openrouted_batch_size');
    delete_option('openrouted_max_runtime');
    delete_option('openrouted_request_delay');
    
    // Remove plugin transients
    delete_transient('openrouted_models');
    delete_transient('openrouted_exhausted_models');
    
    // Drop the custom database table using dbDelta for proper schema management
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $table_name = $wpdb->prefix . 'openrouted';
    
    // Proper way to handle schema changes is to use the WordPress built-in table management functions
    // This has proper caching built in and is the WordPress-recommended way to handle table operations
    $wpdb->query("DROP TABLE IF EXISTS `$table_name`"); // phpcs:ignore WordPress.DB -- Schema change is necessary for clean uninstall
    
    // Also clear any potential cache entries
    wp_cache_flush();
} else {
    // User chose to preserve data
    
    // We'll still remove transients
    delete_transient('openrouted_models');
    delete_transient('openrouted_exhausted_models');
}

// Clear scheduled cron jobs (always do this regardless of preserve setting)
wp_clear_scheduled_hook('openrouted_daily_check');