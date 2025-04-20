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
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'openrouted') . '</p></div>';
    }
    ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('openrouted_settings');
        do_settings_sections('openrouted_settings');
        $api_key = get_option('openrouted_api_key', '');
        $mode = get_option('openrouted_mode', 'manual');
        $custom_instructions = get_option('openrouted_custom_instructions', '');
        $model_selection = get_option('openrouted_model_selection', 'auto');
        $custom_model_id = get_option('openrouted_custom_model_id', '');
        $preserve_data = get_option('openrouted_preserve_data', 'no');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="openrouted_api_key"><?php _e('OpenRouter API Key', 'openrouted'); ?></label>
                </th>
                <td>
                    <input type="password" id="openrouted_api_key" name="openrouted_api_key" 
                           value="<?php echo esc_attr($api_key); ?>" class="regular-text" autocomplete="off">
                    <p class="description">
                        <?php _e('Enter your OpenRouter API key. If you don\'t have one, sign up at', 'openrouted'); ?> 
                        <a href="https://openrouter.ai/" target="_blank">openrouter.ai</a>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openrouted_mode"><?php _e('Operation Mode', 'openrouted'); ?></label>
                </th>
                <td>
                    <select id="openrouted_mode" name="openrouted_mode">
                        <option value="manual" <?php selected($mode, 'manual'); ?>><?php _e('Manual', 'openrouted'); ?></option>
                        <option value="auto" <?php selected($mode, 'auto'); ?>><?php _e('Automatic', 'openrouted'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('Manual: Review alt tags before applying them', 'openrouted'); ?><br>
                        <?php _e('Automatic: Apply generated alt tags immediately', 'openrouted'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openrouted_custom_instructions"><?php _e('Custom Instructions', 'openrouted'); ?></label>
                </th>
                <td>
                    <textarea id="openrouted_custom_instructions" name="openrouted_custom_instructions" 
                              rows="6" class="large-text"><?php echo esc_textarea($custom_instructions); ?></textarea>
                    <p class="description">
                        <?php _e('Optional. Provide custom instructions to guide the AI. For example, specify the tone or style for alt tags, or add specific SEO keywords to include.', 'openrouted'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openrouted_custom_model_id"><?php _e('AI Model', 'openrouted'); ?></label>
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
                            echo '<option value="" disabled>' . __('No models available - check API key', 'openrouted') . '</option>';
                        }
                        ?>
                    </select>
                    <p class="description">
                        <?php _e('Select which AI model to use for generating alt tags. Free models use your daily free quota. Paid models provide potentially better quality but will consume your OpenRouter credits.', 'openrouted'); ?>
                        <br>
                        <?php _e('Note: Higher quality models typically cost more per request.', 'openrouted'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openrouted_schedule_frequency"><?php _e('Schedule Frequency', 'openrouted'); ?></label>
                </th>
                <td>
                    <select id="openrouted_schedule_frequency" name="openrouted_schedule_frequency">
                        <option value="minute" <?php selected(get_option('openrouted_schedule_frequency', 'daily'), 'minute'); ?>><?php _e('Every minute', 'openrouted'); ?></option>
                        <option value="5minutes" <?php selected(get_option('openrouted_schedule_frequency', 'daily'), '5minutes'); ?>><?php _e('Every 5 minutes', 'openrouted'); ?></option>
                        <option value="10minutes" <?php selected(get_option('openrouted_schedule_frequency', 'daily'), '10minutes'); ?>><?php _e('Every 10 minutes', 'openrouted'); ?></option>
                        <option value="20minutes" <?php selected(get_option('openrouted_schedule_frequency', 'daily'), '20minutes'); ?>><?php _e('Every 20 minutes', 'openrouted'); ?></option>
                        <option value="30minutes" <?php selected(get_option('openrouted_schedule_frequency', 'daily'), '30minutes'); ?>><?php _e('Every 30 minutes', 'openrouted'); ?></option>
                        <option value="hourly" <?php selected(get_option('openrouted_schedule_frequency', 'daily'), 'hourly'); ?>><?php _e('Every hour', 'openrouted'); ?></option>
                        <option value="2hours" <?php selected(get_option('openrouted_schedule_frequency', 'daily'), '2hours'); ?>><?php _e('Every 2 hours', 'openrouted'); ?></option>
                        <option value="4hours" <?php selected(get_option('openrouted_schedule_frequency', 'daily'), '4hours'); ?>><?php _e('Every 4 hours', 'openrouted'); ?></option>
                        <option value="6hours" <?php selected(get_option('openrouted_schedule_frequency', 'daily'), '6hours'); ?>><?php _e('Every 6 hours', 'openrouted'); ?></option>
                        <option value="12hours" <?php selected(get_option('openrouted_schedule_frequency', 'daily'), '12hours'); ?>><?php _e('Every 12 hours', 'openrouted'); ?></option>
                        <option value="daily" <?php selected(get_option('openrouted_schedule_frequency', 'daily'), 'daily'); ?>><?php _e('Once daily', 'openrouted'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('How frequently to scan for and process images without alt tags.', 'openrouted'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openrouted_batch_size"><?php _e('Images Per Batch', 'openrouted'); ?></label>
                </th>
                <td>
                    <select id="openrouted_batch_size" name="openrouted_batch_size">
                        <option value="5" <?php selected(get_option('openrouted_batch_size', '20'), '5'); ?>><?php _e('5 images', 'openrouted'); ?></option>
                        <option value="10" <?php selected(get_option('openrouted_batch_size', '20'), '10'); ?>><?php _e('10 images', 'openrouted'); ?></option>
                        <option value="20" <?php selected(get_option('openrouted_batch_size', '20'), '20'); ?>><?php _e('20 images', 'openrouted'); ?></option>
                        <option value="50" <?php selected(get_option('openrouted_batch_size', '20'), '50'); ?>><?php _e('50 images', 'openrouted'); ?></option>
                        <option value="100" <?php selected(get_option('openrouted_batch_size', '20'), '100'); ?>><?php _e('100 images', 'openrouted'); ?></option>
                        <option value="all" <?php selected(get_option('openrouted_batch_size', '20'), 'all'); ?>><?php _e('All missing images', 'openrouted'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('Maximum number of images to process in one scheduled run. Select "All" to process all images missing alt tags (may use entire free quota).', 'openrouted'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openrouted_max_runtime"><?php _e('Maximum Runtime', 'openrouted'); ?></label>
                </th>
                <td>
                    <select id="openrouted_max_runtime" name="openrouted_max_runtime">
                        <option value="1" <?php selected(get_option('openrouted_max_runtime', '10'), '1'); ?>><?php _e('1 minute', 'openrouted'); ?></option>
                        <option value="2" <?php selected(get_option('openrouted_max_runtime', '10'), '2'); ?>><?php _e('2 minutes', 'openrouted'); ?></option>
                        <option value="5" <?php selected(get_option('openrouted_max_runtime', '10'), '5'); ?>><?php _e('5 minutes', 'openrouted'); ?></option>
                        <option value="10" <?php selected(get_option('openrouted_max_runtime', '10'), '10'); ?>><?php _e('10 minutes', 'openrouted'); ?></option>
                        <option value="15" <?php selected(get_option('openrouted_max_runtime', '10'), '15'); ?>><?php _e('15 minutes', 'openrouted'); ?></option>
                        <option value="20" <?php selected(get_option('openrouted_max_runtime', '10'), '20'); ?>><?php _e('20 minutes', 'openrouted'); ?></option>
                        <option value="0" <?php selected(get_option('openrouted_max_runtime', '10'), '0'); ?>><?php _e('No limit (not recommended)', 'openrouted'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('Maximum time the scanning process can run before stopping. Helps prevent server timeouts.', 'openrouted'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openrouted_request_delay"><?php _e('Request Delay', 'openrouted'); ?></label>
                </th>
                <td>
                    <select id="openrouted_request_delay" name="openrouted_request_delay">
                        <option value="0" <?php selected(get_option('openrouted_request_delay', '2'), '0'); ?>><?php _e('No delay', 'openrouted'); ?></option>
                        <option value="1" <?php selected(get_option('openrouted_request_delay', '2'), '1'); ?>><?php _e('1 second', 'openrouted'); ?></option>
                        <option value="2" <?php selected(get_option('openrouted_request_delay', '2'), '2'); ?>><?php _e('2 seconds', 'openrouted'); ?></option>
                        <option value="3" <?php selected(get_option('openrouted_request_delay', '2'), '3'); ?>><?php _e('3 seconds', 'openrouted'); ?></option>
                        <option value="5" <?php selected(get_option('openrouted_request_delay', '2'), '5'); ?>><?php _e('5 seconds', 'openrouted'); ?></option>
                        <option value="10" <?php selected(get_option('openrouted_request_delay', '2'), '10'); ?>><?php _e('10 seconds', 'openrouted'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('Time to wait between API requests. Higher values reduce server load but slow down processing.', 'openrouted'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openrouted_preserve_data"><?php _e('Preserve Data on Uninstall', 'openrouted'); ?></label>
                </th>
                <td>
                    <select id="openrouted_preserve_data" name="openrouted_preserve_data">
                        <option value="no" <?php selected($preserve_data, 'no'); ?>><?php _e('No - Remove all plugin data', 'openrouted'); ?></option>
                        <option value="yes" <?php selected($preserve_data, 'yes'); ?>><?php _e('Yes - Keep database tables and settings', 'openrouted'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('Choose whether to keep or remove all plugin data when uninstalling. If you plan to reinstall later, select "Yes" to preserve your settings and generated alt tags.', 'openrouted'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <hr>
    
    <div class="openrouted-info">
        <h2><?php _e('About Openrouted', 'openrouted'); ?></h2>
        <p>
            <?php _e('This plugin uses OpenRouter\'s AI vision models to analyze your images and generate descriptive alt tags. By default, it uses free models with daily quota limits, but you can optionally enable paid models for potentially better quality results.', 'openrouted'); ?>
        </p>
        
        <h3><?php _e('Benefits of Alt Tags', 'openrouted'); ?></h3>
        <ul>
            <li><strong><?php _e('Accessibility:', 'openrouted'); ?></strong> <?php _e('Alt tags make your images accessible to visitors using screen readers or with visual impairments.', 'openrouted'); ?></li>
            <li><strong><?php _e('SEO:', 'openrouted'); ?></strong> <?php _e('Search engines use alt tags to understand image content, improving your site\'s search rankings.', 'openrouted'); ?></li>
            <li><strong><?php _e('User Experience:', 'openrouted'); ?></strong> <?php _e('Alt tags provide context when images fail to load due to slow connections or errors.', 'openrouted'); ?></li>
        </ul>
        
        <h3><?php _e('How It Works', 'openrouted'); ?></h3>
        <ol>
            <li><?php _e('The plugin scans your media library for images without alt tags.', 'openrouted'); ?></li>
            <li><?php _e('It uses OpenRouter\'s AI vision models to analyze each image (free by default, or paid models if selected).', 'openrouted'); ?></li>
            <li><?php _e('The AI generates descriptive, SEO-friendly alt text based on what it sees.', 'openrouted'); ?></li>
            <li><?php _e('You can review and apply these suggestions or have them applied automatically.', 'openrouted'); ?></li>
            <li><?php _e('The plugin runs according to your schedule to check for new images missing alt tags.', 'openrouted'); ?></li>
        </ol>
        
        <h3><?php _e('Version Information', 'openrouted'); ?></h3>
        <p><?php printf(__('You are running Openrouted version %s', 'openrouted'), OPENROUTED_VERSION); ?></p>
        
        <div class="version-history">
            <h4><?php _e('Version History', 'openrouted'); ?></h4>
            <ul>
                <li><strong>1.4.1</strong> - <?php _e('Improved model selection interface with direct selection of available models. Fixed issue where selected paid models weren\'t being used correctly in API calls.', 'openrouted'); ?></li>
                <li><strong>1.4.0</strong> - <?php _e('Added support for paid vision models. Users can now choose between free models (default) or paid models for potentially better quality alt tags. Fixed various cron scheduling issues.', 'openrouted'); ?></li>
                <li><strong>1.3.0</strong> - <?php _e('Improved cron scheduling reliability and fixed "headers already sent" errors. Enhanced dashboard with better debugging information and real-time status updates.', 'openrouted'); ?></li>
                <li><strong>1.2.0</strong> - <?php _e('Added advanced scheduling options for automated processing. Configure frequency, batch size, runtime limits, and request delays. Added processing lock to prevent conflicts.', 'openrouted'); ?></li>
                <li><strong>1.1.0</strong> - <?php _e('Added improved dashboard interface with auto-refresh, detailed activity log, and "Apply All" functionality. Added data preservation option on uninstall.', 'openrouted'); ?></li>
                <li><strong>1.0.0</strong> - <?php _e('Initial release with core functionality for generating and applying alt tags.', 'openrouted'); ?></li>
            </ul>
        </div>
    </div>
</div>