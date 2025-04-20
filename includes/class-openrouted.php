<?php
/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    Openrouted
 * @subpackage Openrouted/includes
 */

class Openrouted {

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Openrouted_Loader    $loader    Maintains and registers all hooks.
     */
    protected $loader;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_cron_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        /**
         * The class responsible for orchestrating the actions and filters.
         */
        require_once OPENROUTED_PLUGIN_DIR . 'includes/class-openrouted-loader.php';

        /**
         * The class responsible for defining all admin-specific functionality.
         */
        require_once OPENROUTED_PLUGIN_DIR . 'admin/class-openrouted-admin.php';

        /**
         * The class responsible for OpenRouter API communication.
         */
        require_once OPENROUTED_PLUGIN_DIR . 'includes/class-openrouted-api.php';

        /**
         * The class responsible for alt tags generation.
         */
        require_once OPENROUTED_PLUGIN_DIR . 'includes/class-openrouted-generator.php';

        $this->loader = new Openrouted_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new Openrouted_Admin();

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        
        // Add media page hooks
        $this->loader->add_filter('attachment_fields_to_edit', $plugin_admin, 'add_alt_tag_button', 10, 2);
        
        // Register AJAX handlers
        $plugin_admin->register_ajax_actions();
    }

    /**
     * Register all of the hooks related to cron jobs.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_cron_hooks() {
        // Add custom cron schedules
        $this->loader->add_filter('cron_schedules', $this, 'add_custom_cron_intervals');
        
        // Register the generator hook
        $generator = new Openrouted_Generator();
        $this->loader->add_action('openrouted_daily_check', $generator, 'daily_check');
    }
    
    /**
     * Add custom cron schedule intervals.
     *
     * @since    1.0.0
     * @param    array    $schedules    Current cron schedules.
     * @return   array                  Modified cron schedules.
     */
    public function add_custom_cron_intervals($schedules) {
        // Minutes-based intervals
        $schedules['minute'] = array(
            'interval' => 60,
            'display' => __('Every Minute', 'openrouted')
        );
        
        $schedules['5minutes'] = array(
            'interval' => 5 * 60,
            'display' => __('Every 5 Minutes', 'openrouted')
        );
        
        $schedules['10minutes'] = array(
            'interval' => 10 * 60,
            'display' => __('Every 10 Minutes', 'openrouted')
        );
        
        $schedules['20minutes'] = array(
            'interval' => 20 * 60,
            'display' => __('Every 20 Minutes', 'openrouted')
        );
        
        $schedules['30minutes'] = array(
            'interval' => 30 * 60,
            'display' => __('Every 30 Minutes', 'openrouted')
        );
        
        // Hours-based intervals
        $schedules['2hours'] = array(
            'interval' => 2 * 60 * 60,
            'display' => __('Every 2 Hours', 'openrouted')
        );
        
        $schedules['4hours'] = array(
            'interval' => 4 * 60 * 60,
            'display' => __('Every 4 Hours', 'openrouted')
        );
        
        $schedules['6hours'] = array(
            'interval' => 6 * 60 * 60,
            'display' => __('Every 6 Hours', 'openrouted')
        );
        
        $schedules['12hours'] = array(
            'interval' => 12 * 60 * 60,
            'display' => __('Every 12 Hours', 'openrouted')
        );
        
        return $schedules;
    }

    /**
     * Run the loader to execute all the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }
}