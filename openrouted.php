<?php
/**
 * Plugin Name: OpenRouted
 * Plugin URI: https://github.com/Pellmellism/openrouted
 * Description: Automatically generate image alt tags using OpenRouter's AI vision models (free or paid). Uses advanced AI vision to create SEO-friendly alt text for your images.
 * Version: 1.0.0
 * Author: openrouted.com
 * Author URI: https://openrouted.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: openrouted
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('OPENROUTED_VERSION', '1.0.0');
define('OPENROUTED_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OPENROUTED_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OPENROUTED_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_openrouted() {
    require_once OPENROUTED_PLUGIN_DIR . 'includes/class-openrouted-activator.php';
    Openrouted_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_openrouted() {
    require_once OPENROUTED_PLUGIN_DIR . 'includes/class-openrouted-deactivator.php';
    Openrouted_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_openrouted');
register_deactivation_hook(__FILE__, 'deactivate_openrouted');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once OPENROUTED_PLUGIN_DIR . 'includes/class-openrouted.php';

/**
 * Begins execution of the plugin.
 */
function run_openrouted() {
    $plugin = new Openrouted();
    $plugin->run();
}
run_openrouted();