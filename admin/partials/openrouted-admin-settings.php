<?php
/**
 * Settings admin view.
 *
 * @since      1.0.0
 * @package    Openrouted
 * @subpackage Openrouted/admin/partials
 */
?>

<div class="wrap openrouted-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php
    // Display settings updated message
    if (
        isset($_GET['settings-updated']) && 
        $_GET['settings-updated'] == 'true' && 
        isset($_REQUEST['_wpnonce']) && 
        wp_verify_nonce(sanitize_key($_REQUEST['_wpnonce']), 'openrouted-options')
    ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'openrouted') . '</p></div>';
    }
    ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('openrouted_settings');
        do_settings_sections('openrouted_settings');
        $api_key             = get_option('openrouted_api_key', '');
        $mode                = get_option('openrouted_mode', 'manual');
        $custom_instructions = get_option('openrouted_custom_instructions', '');
        $model_selection     = get_option('openrouted_model_selection', 'auto');
        $custom_model_id     = get_option('openrouted_custom_model_id', '');
        $preserve_data       = get_option('openrouted_preserve_data', 'no');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="openrouted_api_key"><?php esc_html_e('OpenRouter API Key', 'openrouted'); ?></label>
                </th>
                <td>
                    <input type="password" id="openrouted_api_key" name="openrouted_api_key"
                           value="<?php echo esc_attr($api_key); ?>" class="regular-text" autocomplete="off">
                    <p class="description">
                        <?php esc_html_e('Enter your OpenRouter API key. If you don\'t have one, sign up at', 'openrouted'); ?>
                        <a href="https://openrouter.ai/" target="_blank">openrouter.ai</a>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openrouted_mode"><?php esc_html_e('Operation Mode', 'openrouted'); ?></label>
                </th>
                <td>
                    <select id="openrouted_mode" name="openrouted_mode">
                        <option value="manual" <?php selected($mode, 'manual'); ?>><?php esc_html_e('Manual', 'openrouted'); ?></option>
                        <option value="auto" <?php selected($mode, 'auto'); ?>><?php esc_html_e('Automatic', 'openrouted'); ?></option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Manual: Review alt tags before applying them', 'openrouted'); ?><br>
                        <?php esc_html_e('Automatic: Apply generated alt tags immediately', 'openrouted'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openrouted_custom_instructions"><?php esc_html_e('Custom Instructions', 'openrouted'); ?></label>
                </th>
                <td>
                    <textarea id="openrouted_custom_instructions" name="openrouted_custom_instructions"
                              rows="6" class="large-text"><?php echo esc_textarea($custom_instructions); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Optional. Provide custom instructions to tailor alt text generation — for example, restrict length, include specific words, or add specific SEO keywords to include.', 'openrouted'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openrouted_custom_model_id"><?php esc_html_e('AI Model', 'openrouted'); ?></label>
                </th>
                <td>
                    <select id="openrouted_custom_model_id" name="openrouted_custom_model_id">
                        <?php
                        // Display available models if API key is set
                        if (!empty($available_models)) {
                            foreach ($available_models as $model_id => $model_name) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($model_id),
                                    selected($custom_model_id, $model_id, false),
                                    esc_html($model_name)
                                );
                            }
                        } else {
                            // If no API key or no models available
                            echo '<option value="" disabled>' . esc_html__('No models available — check API key', 'openrouted') . '</option>';
                        }
                        ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Select which AI model to use (recommended: auto for best quality but will consume your OpenRouter credits).', 'openrouted'); ?><br>
                        <?php esc_html_e('Note: Higher quality models typically cost more per request.', 'openrouted'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openrouted_schedule_frequency"><?php esc_html_e('Schedule Frequency', 'openrouted'); ?></label>
                </th>
                <td>
                    <select id="openrouted_schedule_frequency" name="openrouted_schedule_frequency">
                        <option value="minute" <?php selected($schedule_frequency, 'minute'); ?>><?php esc_html_e('Every minute', 'openrouted'); ?></option>
                        <option value="hourly" <?php selected($schedule_frequency, 'hourly'); ?>><?php esc_html_e('Hourly', 'openrouted'); ?></option>
                        <option value="daily" <?php selected($schedule_frequency, 'daily'); ?>><?php esc_html_e('Daily', 'openrouted'); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openrouted_preserve_data"><?php esc_html_e('Preserve Data on Uninstall', 'openrouted'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="openrouted_preserve_data" name="openrouted_preserve_data" value="yes" <?php checked($preserve_data, 'yes'); ?>>
                    <label for="openrouted_preserve_data"><?php esc_html_e('Keep all generated alt tags and settings when plugin is uninstalled', 'openrouted'); ?></label>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
        
    </form>

    <h3><?php esc_html_e('How It Works', 'openrouted'); ?></h3>
    <ol>
        <li><?php esc_html_e('The plugin scans your media library for images without alt tags.', 'openrouted'); ?></li>
        <li><?php esc_html_e('It uses OpenRouter\'s AI vision model to analyze images (free by default, or paid models if selected).', 'openrouted'); ?></li>
        <li><?php esc_html_e('The AI generates descriptive, SEO-friendly alt text based on what it sees.', 'openrouted'); ?></li>
        <li><?php esc_html_e('You can review and apply these suggestions manually or have them applied automatically.', 'openrouted'); ?></li>
        <li><?php esc_html_e('The plugin runs according to your schedule and will periodically check for new images missing alt tags.', 'openrouted'); ?></li>
    </ol>

    <h3><?php esc_html_e('Version Information', 'openrouted'); ?></h3>
    <p>
        <?php
        /* translators: %1$s: plugin version number. */
        printf( esc_html__( 'Openrouted version %1$s', 'openrouted' ), esc_html( OPENROUTED_VERSION ) );
        ?>
    </p>
    
    <div class="version-history">
        <h4><?php esc_html_e('Version History', 'openrouted'); ?></h4>
        <ul>
            <li><strong>1.4.1</strong> - <?php esc_html_e('Improved handling of pagination and cron jobs; fixed bug where alt text wasn\'t being used correctly in API calls.', 'openrouted'); ?></li>
            <li><strong>1.4.0</strong> - <?php esc_html_e('Added custom model support; fixed various cron scheduling issues.', 'openrouted'); ?></li>
            <li><strong>1.3.0</strong> - <?php esc_html_e('Improved caching and rate limit fallback; added real-time status updates.', 'openrouted'); ?></li>
            <li><strong>1.2.0</strong> - <?php esc_html_e('Added queue system and processing lock to prevent conflicts.', 'openrouted'); ?></li>
            <li><strong>1.1.0</strong> - <?php esc_html_e('Added performance improvements and data preservation option on uninstall.', 'openrouted'); ?></li>
            <li><strong>1.0.0</strong> - <?php esc_html_e('Initial release with core functionality for generating and applying alt tags.', 'openrouted'); ?></li>
        </ul>
    </div>
</div>