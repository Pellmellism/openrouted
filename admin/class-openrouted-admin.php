<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    Openrouted
 * @subpackage Openrouted/admin
 */

class Openrouted_Admin {

    /**
     * Alt tags generator instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Openrouted_Generator    $generator    Generator instance.
     */
    private $generator;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->generator = new Openrouted_Generator();
        
        // Register early action for handling cron refresh
        add_action('admin_init', array($this, 'handle_cron_refresh'), 5);
    }
    
    /**
     * Handle cron refresh action early in the WordPress initialization.
     * This ensures we redirect before any content is sent to the browser.
     *
     * @since    1.0.0
     */
    public function handle_cron_refresh() {
        if (isset($_GET['page']) && $_GET['page'] === 'openrouted' && 
            isset($_GET['refresh_cron']) && current_user_can('manage_options') &&
            isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'openrouted_refresh_cron')) {
            
            // Get current frequency
            $frequency = get_option('openrouted_schedule_frequency', 'daily');
            
            // Force update the cron schedule with the same frequency to refresh it
            $this->update_cron_schedule($frequency, $frequency);
            
            // Redirect back without the query parameter to prevent multiple refreshes
            wp_redirect(remove_query_arg('refresh_cron'));
            exit;
        }
    }
    
    /**
     * Register AJAX actions for the plugin.
     * 
     * @since    1.0.0
     */
    public function register_ajax_actions() {
        // Register existing AJAX actions
        add_action('wp_ajax_openrouted_generate_alt_tag', array($this, 'generate_alt_tag'));
        add_action('wp_ajax_openrouted_apply_alt_tag', array($this, 'apply_alt_tag'));
        add_action('wp_ajax_openrouted_delete_alt_tag', array($this, 'delete_alt_tag'));
        add_action('wp_ajax_openrouted_run_bulk_generator', array($this, 'run_bulk_generator'));
        
        // Register new AJAX actions for enhanced UI
        add_action('wp_ajax_openrouted_load_more_alt_tags', array($this, 'load_more_alt_tags'));
        add_action('wp_ajax_openrouted_get_alt_tag_details', array($this, 'get_alt_tag_details'));
        add_action('wp_ajax_openrouted_get_activity_log', array($this, 'get_activity_log'));
        add_action('wp_ajax_openrouted_get_counts', array($this, 'get_counts'));
        
        // Register scheduling management AJAX actions
        add_action('wp_ajax_openrouted_run_scheduled_check', array($this, 'run_scheduled_check'));
        add_action('wp_ajax_openrouted_refresh_cron_schedule', array($this, 'refresh_cron_schedule'));
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'openrouted',
            OPENROUTED_PLUGIN_URL . 'admin/css/openrouted-admin.css',
            array(),
            OPENROUTED_VERSION,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'openrouted',
            OPENROUTED_PLUGIN_URL . 'admin/js/openrouted-admin.js',
            array('jquery'),
            OPENROUTED_VERSION,
            false
        );
        
        wp_localize_script('openrouted', 'openrouted_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('openrouted_nonce')
        ));
    }

    /**
     * Add menu items for the plugin.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {
        // Add main menu item
        add_menu_page(
            __('OpenRouted', 'openrouted'),
            __('Alt Tags AI', 'openrouted'),
            'manage_options',
            'openrouted',
            array($this, 'display_plugin_admin_dashboard'),
            'dashicons-format-image',
            25
        );
        
        // Add submenu items
        add_submenu_page(
            'openrouted',
            __('Dashboard', 'openrouted'),
            __('Dashboard', 'openrouted'),
            'manage_options',
            'openrouted',
            array($this, 'display_plugin_admin_dashboard')
        );
        
        add_submenu_page(
            'openrouted',
            __('Settings', 'openrouted'),
            __('Settings', 'openrouted'),
            'manage_options',
            'openrouted-settings',
            array($this, 'display_plugin_admin_settings')
        );
    }

    /**
     * Sanitize operation mode setting.
     *
     * @since    1.0.0
     * @param    string    $value    The value to sanitize.
     * @return   string              The sanitized value.
     */
    public function sanitize_mode($value) {
        $valid_values = array('manual', 'auto');
        if (!in_array($value, $valid_values)) {
            return 'manual';
        }
        return $value;
    }

    /**
     * Sanitize schedule frequency setting.
     *
     * @since    1.0.0
     * @param    string    $value    The value to sanitize.
     * @return   string              The sanitized value.
     */
    public function sanitize_schedule_frequency($value) {
        $valid_values = array('minute', '5minutes', '10minutes', '20minutes', '30minutes', 
                             'hourly', '2hours', '4hours', '6hours', '12hours', 'daily');
        if (!in_array($value, $valid_values)) {
            return 'daily';
        }
        return $value;
    }

    /**
     * Sanitize batch size setting.
     *
     * @since    1.0.0
     * @param    string    $value    The value to sanitize.
     * @return   string              The sanitized value.
     */
    public function sanitize_batch_size($value) {
        $valid_values = array('5', '10', '20', '50', '100', 'all');
        if (!in_array($value, $valid_values)) {
            return '20';
        }
        return $value;
    }

    /**
     * Sanitize max runtime setting.
     *
     * @since    1.0.0
     * @param    string    $value    The value to sanitize.
     * @return   string              The sanitized value.
     */
    public function sanitize_max_runtime($value) {
        $valid_values = array('0', '1', '2', '5', '10', '15', '20');
        if (!in_array($value, $valid_values)) {
            return '10';
        }
        return $value;
    }

    /**
     * Sanitize request delay setting.
     *
     * @since    1.0.0
     * @param    string    $value    The value to sanitize.
     * @return   string              The sanitized value.
     */
    public function sanitize_request_delay($value) {
        $valid_values = array('0', '1', '2', '3', '5', '10');
        if (!in_array($value, $valid_values)) {
            return '2';
        }
        return $value;
    }

    /**
     * Sanitize model selection setting.
     *
     * @since    1.0.0
     * @param    string    $value    The value to sanitize.
     * @return   string              The sanitized value.
     */
    public function sanitize_model_selection($value) {
        $valid_values = array('auto', 'paid');
        if (!in_array($value, $valid_values)) {
            return 'auto';
        }
        return $value;
    }

    /**
     * Sanitize preserve data setting.
     *
     * @since    1.0.0
     * @param    string    $value    The value to sanitize.
     * @return   string              The sanitized value.
     */
    public function sanitize_preserve_data($value) {
        $valid_values = array('yes', 'no');
        if (!in_array($value, $valid_values)) {
            return 'no';
        }
        return $value;
    }

    /**
     * Sanitize API key setting.
     *
     * @since    1.0.0
     * @param    string    $value    The value to sanitize.
     * @return   string              The sanitized value.
     */
    public function sanitize_api_key($value) {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize custom instructions setting.
     *
     * @since    1.0.0
     * @param    string    $value    The value to sanitize.
     * @return   string              The sanitized value.
     */
    public function sanitize_custom_instructions($value) {
        return wp_kses_post($value);
    }

    /**
     * Sanitize custom model ID setting.
     *
     * @since    1.0.0
     * @param    string    $value    The value to sanitize.
     * @return   string              The sanitized value.
     */
    public function sanitize_custom_model_id($value) {
        return sanitize_text_field($value);
    }

    /**
     * Register settings for the plugin and handle settings updates.
     * 
     * @since    1.0.0
     */
    public function register_settings() {
        // Listen for settings changes
        add_action('update_option_openrouted_schedule_frequency', array($this, 'update_cron_schedule'), 10, 2);
        
        // API key setting
        register_setting(
            'openrouted_settings',
            'openrouted_api_key',
            'sanitize_text_field'
        );
        
        // Operation mode setting
        register_setting(
            'openrouted_settings',
            'openrouted_mode',
            'sanitize_text_field'
        );
        
        // Custom instructions setting
        register_setting(
            'openrouted_settings',
            'openrouted_custom_instructions',
            'wp_kses_post'
        );
        
        // Schedule frequency setting
        register_setting(
            'openrouted_settings',
            'openrouted_schedule_frequency',
            'sanitize_text_field'
        );
        
        // Batch size setting
        register_setting(
            'openrouted_settings',
            'openrouted_batch_size',
            'sanitize_text_field'
        );
        
        // Max runtime setting
        register_setting(
            'openrouted_settings',
            'openrouted_max_runtime',
            'sanitize_text_field'
        );
        
        // Request delay setting
        register_setting(
            'openrouted_settings',
            'openrouted_request_delay',
            'sanitize_text_field'
        );
        
        // Model selection setting
        register_setting(
            'openrouted_settings',
            'openrouted_model_selection',
            'sanitize_text_field'
        );
        
        // Custom model ID setting
        register_setting(
            'openrouted_settings',
            'openrouted_custom_model_id',
            'sanitize_text_field'
        );
        
        // Preserve data setting
        register_setting(
            'openrouted_settings',
            'openrouted_preserve_data',
            'sanitize_text_field'
        );
        
        // Add filters to validate the sanitized values
        add_filter('pre_update_option_openrouted_mode', array($this, 'validate_mode'), 10, 2);
        add_filter('pre_update_option_openrouted_schedule_frequency', array($this, 'validate_schedule_frequency'), 10, 2);
        add_filter('pre_update_option_openrouted_batch_size', array($this, 'validate_batch_size'), 10, 2);
        add_filter('pre_update_option_openrouted_max_runtime', array($this, 'validate_max_runtime'), 10, 2);
        add_filter('pre_update_option_openrouted_request_delay', array($this, 'validate_request_delay'), 10, 2);
        add_filter('pre_update_option_openrouted_model_selection', array($this, 'validate_model_selection'), 10, 2);
        add_filter('pre_update_option_openrouted_preserve_data', array($this, 'validate_preserve_data'), 10, 2);
    }
    
    /**
     * Validates mode after sanitization
     *
     * @since    1.0.0
     * @param    string    $value     The sanitized value
     * @param    string    $old_value The old value
     * @return   string    The validated value
     */
    public function validate_mode($value, $old_value) {
        return $this->sanitize_mode($value, $old_value);
    }
    
    /**
     * Validates schedule frequency after sanitization
     *
     * @since    1.0.0
     * @param    string    $value     The sanitized value
     * @param    string    $old_value The old value
     * @return   string    The validated value
     */
    public function validate_schedule_frequency($value, $old_value) {
        return $this->sanitize_schedule_frequency($value, $old_value);
    }
    
    /**
     * Validates batch size after sanitization
     *
     * @since    1.0.0
     * @param    string    $value     The sanitized value
     * @param    string    $old_value The old value
     * @return   string    The validated value
     */
    public function validate_batch_size($value, $old_value) {
        return $this->sanitize_batch_size($value, $old_value);
    }
    
    /**
     * Validates max runtime after sanitization
     *
     * @since    1.0.0
     * @param    string    $value     The sanitized value
     * @param    string    $old_value The old value
     * @return   string    The validated value
     */
    public function validate_max_runtime($value, $old_value) {
        return $this->sanitize_max_runtime($value, $old_value);
    }
    
    /**
     * Validates request delay after sanitization
     *
     * @since    1.0.0
     * @param    string    $value     The sanitized value
     * @param    string    $old_value The old value
     * @return   string    The validated value
     */
    public function validate_request_delay($value, $old_value) {
        return $this->sanitize_request_delay($value, $old_value);
    }
    
    /**
     * Validates model selection after sanitization
     *
     * @since    1.0.0
     * @param    string    $value     The sanitized value
     * @param    string    $old_value The old value
     * @return   string    The validated value
     */
    public function validate_model_selection($value, $old_value) {
        return $this->sanitize_model_selection($value, $old_value);
    }
    
    /**
     * Validates preserve data after sanitization
     *
     * @since    1.0.0
     * @param    string    $value     The sanitized value
     * @param    string    $old_value The old value
     * @return   string    The validated value
     */
    public function validate_preserve_data($value, $old_value) {
        return $this->sanitize_preserve_data($value, $old_value);
    }

    /**
     * Display the dashboard page.
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_dashboard() {
        
        // Get API info
        $api = new Openrouted_API();
        $api_key = get_option('openrouted_api_key', '');
        $models = array();
        
        if (!empty($api_key)) {
            $models = $api->get_free_vision_models();
        }
        
        // Get alt tag counts
        $counts = $this->generator->count_alt_tags();
        
        // Get images without alt tags count
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '='
                )
            )
        );
        
        $query = new WP_Query($args);
        $missing_alt_tags = $query->found_posts;
        
        // Get last check info
        $last_check = get_option('openrouted_last_check', array());
        
        include_once OPENROUTED_PLUGIN_DIR . 'admin/partials/openrouted-admin-dashboard.php';
    }

    /**
     * Display the settings page.
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_settings() {
        // Get available models for dropdown
        $available_models = array();
        
        // Get API key
        $api_key = get_option('openrouted_api_key', '');
        
        if (!empty($api_key)) {
            // Instantiate the API class to get models
            require_once OPENROUTED_PLUGIN_DIR . 'includes/class-openrouted-api.php';
            $api = new Openrouted_API();
            
            // Get free vision models as default option
            $free_models = $api->get_free_vision_models();
            if (!is_wp_error($free_models) && !empty($free_models)) {
                // Add a single "Free models" option that represents using the best free model
                $available_models['free'] = 'Free models (uses daily quota)';
            }
            
            // Get paid vision models
            $paid_models = $api->get_paid_vision_models();
            
            // Format models for dropdown
            if (!is_wp_error($paid_models) && !empty($paid_models)) {
                foreach ($paid_models as $model) {
                    if (isset($model['id'])) {
                        // Create a friendly display name
                        $base_name = isset($model['name']) ? $model['name'] : $model['id'];
                        
                        // Extract model name without version numbers
                        $clean_name = preg_replace('/:vision.*$/', '', $base_name);
                        $clean_name = preg_replace('/-\d+.*$/', '', $clean_name);
                        
                        // Capitalize and format nicely
                        $display_name = ucwords(str_replace(['-', '_'], ' ', $clean_name));
                        
                        // Add "Paid" indicator
                        $display_name = $display_name . ' (Paid)';
                        
                        $available_models[$model['id']] = $display_name;
                    }
                }
            }
        }
        
        // Pass the models to the settings page
        include_once OPENROUTED_PLUGIN_DIR . 'admin/partials/openrouted-admin-settings.php';
    }
    
    /**
     * Updates the cron schedule when frequency setting is changed.
     *
     * @since    1.0.0
     * @param    string    $old_value    The old option value.
     * @param    string    $new_value    The new option value.
     */
    public function update_cron_schedule($old_value, $new_value) {
        global $wp_version;
        
        // A simpler and more reliable approach to cron scheduling
        
        // 1. First, clear the hook entirely
        wp_clear_scheduled_hook('openrouted_daily_check');
        
        // 2. Force WordPress to recalculate schedules
        delete_transient('doing_cron');
        
        // 3. Calculate the next run time
        $schedule_time = time();
        if (in_array($new_value, array('minute', '5minutes'))) {
            // For very frequent schedules, add a small buffer
            $schedule_time += 15; 
        }
        
        // 4. Schedule the event with a simple call
        $result = wp_schedule_event($schedule_time, $new_value, 'openrouted_daily_check');
        
        if ($result === false) {
            // Try direct insertion into cron option
            $schedules = wp_get_schedules();
            $interval = isset($schedules[$new_value]['interval']) ? $schedules[$new_value]['interval'] : 86400;
            
            $crons = get_option('cron', array());
            $hook_hash = md5(serialize(array()));
            
            $crons[$schedule_time]['openrouted_daily_check'][$hook_hash] = array(
                'schedule' => $new_value,
                'interval' => $interval,
                'args' => array()
            );
            
            update_option('cron', $crons);
        }
        
        // 5. Also add a one-time event for immediate execution (except for daily)
        if ($new_value !== 'daily' && $new_value !== '12hours') {
            $immediate_time = time() + 30;
            wp_schedule_single_event($immediate_time, 'openrouted_daily_check');
        }
        
        // 6. Verify the schedule
        $next_run = wp_next_scheduled('openrouted_daily_check');
        
        // 7. Store the schedule info for dashboard display
        update_option('openrouted_last_schedule_update', array(
            'timestamp' => time(),
            'frequency' => $new_value,
            'next_run' => $next_run
        ));
        
        // 8. Return whether scheduling was successful
        return ($next_run !== false);
    }

    /**
     * Add generate alt tag button to media edit screen.
     *
     * @since    1.0.0
     * @param    array     $form_fields    An array of attachment form fields.
     * @param    WP_Post   $post           The WP_Post attachment object.
     * @return   array                     The modified form fields.
     */
    public function add_alt_tag_button($form_fields, $post) {
        // Only for images
        if (strpos($post->post_mime_type, 'image') !== 0) {
            return $form_fields;
        }
        
        // Check for API key
        $api_key = get_option('openrouted_api_key', '');
        if (empty($api_key)) {
            return $form_fields;
        }
        
        // Check if this image already has an alt tag
        $alt_text = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
        
        // Check if we have a pending generated alt tag
        $alt_tag = $this->generator->get_alt_tag_by_image_id($post->ID, 'pending');
        
        $html = '<div class="openrouted-controls">';
        
        if ($alt_tag) {
            // We have a pending alt tag suggestion
            $html .= '<div class="openrouted-suggestion">';
            $html .= '<p><strong>' . __('AI Suggestion:', 'openrouted') . '</strong> ' . esc_html($alt_tag->alt_text) . '</p>';
            $html .= '<button type="button" class="button apply-alt-tag" data-id="' . esc_attr($alt_tag->id) . '">' . __('Apply', 'openrouted') . '</button> ';
            $html .= '<button type="button" class="button delete-alt-tag" data-id="' . esc_attr($alt_tag->id) . '">' . __('Reject', 'openrouted') . '</button>';
            $html .= '</div>';
        } else {
            // No pending suggestion, show generate button
            $html .= '<button type="button" class="button generate-alt-tag" data-id="' . esc_attr($post->ID) . '">' . __('Generate with AI', 'openrouted') . '</button>';
            $html .= '<span class="spinner"></span>';
            $html .= '<div class="generate-result"></div>';
        }
        
        $html .= '</div>';
        
        // Add our button after the alt text field
        $form_fields['alt']['label'] .= $html;
        
        return $form_fields;
    }

    /**
     * AJAX handler for generating alt tag.
     *
     * @since    1.0.0
     */
    public function generate_alt_tag() {
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'openrouted')));
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'openrouted_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'openrouted')));
        }
        
        // Get image ID
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        
        if ($image_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid image ID.', 'openrouted')));
        }
        
        // Generate alt tag
        $result = $this->generator->generate_alt_tag_for_image($image_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                'id' => $result['id'],
                'alt_text' => $result['alt_text'],
                'message' => __('Alt tag generated successfully.', 'openrouted')
            ));
        }
    }

    /**
     * AJAX handler for applying alt tag.
     *
     * @since    1.0.0
     */
    public function apply_alt_tag() {
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'openrouted')));
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'openrouted_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'openrouted')));
        }
        
        // Get alt tag ID
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            wp_send_json_error(array('message' => __('Invalid alt tag ID.', 'openrouted')));
        }
        
        // Apply alt tag
        $result = $this->generator->apply_alt_tag($id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Alt tag applied successfully.', 'openrouted')));
        } else {
            wp_send_json_error(array('message' => __('Failed to apply alt tag.', 'openrouted')));
        }
    }

    /**
     * AJAX handler for deleting alt tag.
     *
     * @since    1.0.0
     */
    public function delete_alt_tag() {
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'openrouted')));
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'openrouted_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'openrouted')));
        }
        
        // Get alt tag ID
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            wp_send_json_error(array('message' => __('Invalid alt tag ID.', 'openrouted')));
        }
        
        // Delete alt tag
        $result = $this->generator->delete_alt_tag($id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Alt tag rejected.', 'openrouted')));
        } else {
            wp_send_json_error(array('message' => __('Failed to reject alt tag.', 'openrouted')));
        }
    }

    /**
     * AJAX handler for running bulk generator.
     *
     * @since    1.0.0
     */
    public function run_bulk_generator() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'openrouted')));
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'openrouted_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'openrouted')));
        }
        
        // Get limit
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        
        // Run the scan
        $results = $this->generator->scan_missing_alt_tags($limit);
        
        wp_send_json_success(array(
            'message' => sprintf(
                /* translators: %1$d: images without alt tags, %2$d: processed images, %3$d: new alt tags generated */
                __('Found %1$d images without alt tags, processed %2$d, and generated %3$d new alt tags.', 'openrouted'),
                $results['found'],
                $results['processed'],
                $results['generated']
            ),
            'data' => $results
        ));
    }
    
    /**
     * AJAX handler for loading more alt tags.
     *
     * @since    1.0.0
     */
    public function load_more_alt_tags() {
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'openrouted')));
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'openrouted_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'openrouted')));
        }
        
        // Get parameters
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'pending';
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = 10; // Number of items to load each time
        
        // Get alt tags
        $alt_tags = $this->generator->get_more_alt_tags($status, $offset, $limit);
        
        // Check if there are more to load
        $total_count = $this->generator->count_alt_tags_by_status($status);
        $has_more = ($offset + $limit) < $total_count;
        
        // Format response data
        $formatted_alt_tags = array();
        
        foreach ($alt_tags as $alt_tag) {
            // Get image info
            $thumb_url = '';
            $edit_url = '';
            if ($alt_tag->image_id) {
                $thumb_url = wp_get_attachment_image_url($alt_tag->image_id, 'thumbnail');
                $edit_url = get_edit_post_link($alt_tag->image_id);
            }
            
            // Extract model information
            $model = '';
            if (!empty($alt_tag->response)) {
                $response_data = json_decode($alt_tag->response, true);
                if (isset($response_data['model'])) {
                    $model = $response_data['model'];
                }
            }
            
            $formatted_alt_tags[] = array(
                'id' => $alt_tag->id,
                'image_id' => $alt_tag->image_id,
                'alt_text' => $alt_tag->alt_text,
                'status' => $alt_tag->status,
                'timestamp' => $alt_tag->timestamp,
                'applied_timestamp' => $alt_tag->applied_timestamp,
                'thumb_url' => $thumb_url,
                'edit_url' => $edit_url,
                'model' => $model
            );
        }
        
        wp_send_json_success(array(
            'alt_tags' => $formatted_alt_tags,
            'has_more' => $has_more
        ));
    }
    
    /**
     * AJAX handler for getting alt tag details.
     *
     * @since    1.0.0
     */
    public function get_alt_tag_details() {
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'openrouted')));
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'openrouted_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'openrouted')));
        }
        
        // Get alt tag ID
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            wp_send_json_error(array('message' => __('Invalid alt tag ID.', 'openrouted')));
        }
        
        // Get alt tag details
        $alt_tag = $this->generator->get_alt_tag($id);
        
        if (!$alt_tag) {
            wp_send_json_error(array('message' => __('Alt tag not found.', 'openrouted')));
        }
        
        // Get image URL
        $thumb_url = '';
        $image_url = '';
        if ($alt_tag->image_id) {
            $thumb_url = wp_get_attachment_image_url($alt_tag->image_id, 'thumbnail');
            $image_url = wp_get_attachment_image_url($alt_tag->image_id, 'medium');
        }
        
        // Extract model from response if available
        $model = '';
        if (!empty($alt_tag->response)) {
            $response_data = json_decode($alt_tag->response, true);
            if (isset($response_data['model'])) {
                $model = $response_data['model'];
            }
        }
        
        $details = array(
            'id' => $alt_tag->id,
            'image_id' => $alt_tag->image_id,
            'alt_text' => $alt_tag->alt_text,
            'status' => $alt_tag->status,
            'timestamp' => $alt_tag->timestamp,
            'applied_timestamp' => $alt_tag->applied_timestamp,
            'image_url' => $image_url,
            'thumb_url' => $thumb_url,
            'model' => $model,
            'initial_payload' => $alt_tag->initial_payload,
            'response' => $alt_tag->response,
            'duration' => $alt_tag->duration
        );
        
        wp_send_json_success(array('details' => $details));
    }
    
    /**
     * AJAX handler for getting activity log.
     *
     * @since    1.0.0
     */
    public function get_activity_log() {
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'openrouted')));
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'openrouted_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'openrouted')));
        }
        
        // Get activity log
        $log = $this->generator->get_activity_log(50); // Limit to the last 50 entries
        
        wp_send_json_success(array('log' => $log));
    }
    
    /**
     * AJAX handler for getting alt tag counts.
     *
     * @since    1.0.0
     */
    public function get_counts() {
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'openrouted')));
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'openrouted_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'openrouted')));
        }
        
        // Get counts
        $counts = $this->generator->count_alt_tags();
        
        wp_send_json_success(array('counts' => $counts));
    }
    
    /**
     * AJAX handler for running the scheduled check manually.
     * 
     * @since    1.0.0
     */
    public function run_scheduled_check() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'openrouted')));
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'openrouted_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'openrouted')));
        }
        
        // Check if a process is already running
        if (get_transient('openrouted_process_lock')) {
            wp_send_json_error(array('message' => __('Process is already running. Please wait until it completes.', 'openrouted')));
            return;
        }
        
        // Run the cron job directly
        do_action('openrouted_daily_check');
        
        // Check if the process is now running (should be)
        if (get_transient('openrouted_process_lock')) {
            wp_send_json_success(array('message' => __('Scheduled check has started running.', 'openrouted')));
        } else {
            // If process didn't start (unusual), run it in the background
            wp_schedule_single_event(time(), 'openrouted_daily_check');
            wp_send_json_success(array('message' => __('Scheduled check has been triggered.', 'openrouted')));
        }
    }
    
    /**
     * AJAX handler for refreshing the cron schedule.
     * 
     * @since    1.0.0
     */
    public function refresh_cron_schedule() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'openrouted')));
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'openrouted_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'openrouted')));
        }
        
        // Get current frequency setting
        $frequency = get_option('openrouted_schedule_frequency', 'daily');
        
        // Force update the cron schedule
        $result = $this->update_cron_schedule($frequency, $frequency);
        
        // Get the newly scheduled time
        $next_run = wp_next_scheduled('openrouted_daily_check');
        
        if ($next_run) {
            $formatted_time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run);
            $time_diff = human_time_diff(time(), $next_run);
            
            wp_send_json_success(array(
                'message' => sprintf(
                    /* translators: %1$s: formatted next run time, %2$s: time from now */
                    __('Cron schedule refreshed! Next run: %1$s (%2$s from now)', 'openrouted'),
                    $formatted_time,
                    $time_diff
                ),
                'next_run' => $next_run,
                'formatted_time' => $formatted_time,
                'time_diff' => $time_diff
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to refresh cron schedule. Please check server logs.', 'openrouted')));
        }
    }
}