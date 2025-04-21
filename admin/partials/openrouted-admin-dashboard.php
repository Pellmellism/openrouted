<?php
/**
 * Dashboard admin view.
 *
 * @since      1.0.0
 * @package    OpenRouter_Alt_Tags
 * @subpackage OpenRouter_Alt_Tags/admin/partials
 */
?>

<div class="wrap openrouted-dashboard">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (empty($api_key)): ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('Please set your OpenRouter API key in the Settings tab to start using this plugin.', 'openrouted'); ?> 
               <a href="<?php echo esc_url(admin_url('admin.php?page=openrouted-settings')); ?>"><?php esc_html_e('Go to Settings', 'openrouted'); ?></a>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="openrouted-dashboard-grid">
        <div class="openrouter-dashboard-card">
            <h2><?php esc_html_e('Alt Tags Summary', 'openrouted'); ?></h2>
            
            <div class="alt-tags-stats">
                <div class="stat-item">
                    <span class="stat-count"><?php echo intval($missing_alt_tags); ?></span>
                    <span class="stat-label"><?php esc_html_e('Images Missing Alt Tags', 'openrouted'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-count"><?php echo isset($counts['pending']) ? intval($counts['pending']) : 0; ?></span>
                    <span class="stat-label"><?php esc_html_e('Pending', 'openrouted'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-count"><?php echo isset($counts['applied']) ? intval($counts['applied']) : 0; ?></span>
                    <span class="stat-label"><?php esc_html_e('Applied', 'openrouted'); ?></span>
                </div>
            </div>
            
            <hr>
            
            <div class="alt-tags-tabs">
                <div class="tab-navigation">
                    <button class="tab-button active" data-tab="pending"><?php esc_html_e('Pending', 'openrouted'); ?> <span class="count">(<?php echo isset($counts['pending']) ? intval($counts['pending']) : 0; ?>)</span></button>
                    <button class="tab-button" data-tab="applied"><?php esc_html_e('Applied', 'openrouted'); ?> <span class="count">(<?php echo isset($counts['applied']) ? intval($counts['applied']) : 0; ?>)</span></button>
                    <button class="tab-button" data-tab="logs"><?php esc_html_e('Activity Log', 'openrouted'); ?></button>
                </div>
                
                <!-- Pending Tab -->
                <div class="tab-content active" id="pending-tab">
                    <?php 
                    // Get all pending alt tags instead of just 10
                    $pending_alt_tags = $this->generator->get_alt_tags('pending', 100);
                    if (!empty($pending_alt_tags)): 
                    ?>
                        <div class="table-container" id="pending-table-container">
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Image', 'openrouted'); ?></th>
                                        <th><?php esc_html_e('Suggested Alt Text', 'openrouted'); ?></th>
                                        <th><?php esc_html_e('Generated', 'openrouted'); ?></th>
                                        <th><?php esc_html_e('Model', 'openrouted'); ?></th>
                                        <th><?php esc_html_e('Actions', 'openrouted'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_alt_tags as $alt_tag): 
                                        // Extract model from response if available
                                        $model = '';
                                        if (!empty($alt_tag->response)) {
                                            $response_data = json_decode($alt_tag->response, true);
                                            if (isset($response_data['model'])) {
                                                $model = $response_data['model'];
                                                // Extract just the model name, not the full path
                                                if (strpos($model, '/') !== false) {
                                                    $model = explode('/', $model)[1];
                                                }
                                            }
                                        }
                                    ?>
                                        <tr data-id="<?php echo esc_attr($alt_tag->id); ?>">
                                            <td>
                                                <?php 
                                                $thumb_url = wp_get_attachment_image_url($alt_tag->image_id, 'thumbnail');
                                                if ($thumb_url) {
                                                    echo '<img src="' . esc_url($thumb_url) . '" width="60" height="60" alt="">';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo esc_html($alt_tag->alt_text); ?></td>
                                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($alt_tag->timestamp))); ?></td>
                                            <td><?php echo esc_html($model); ?></td>
                                            <td>
                                                <button class="button button-primary apply-dashboard-alt-tag" data-id="<?php echo esc_attr($alt_tag->id); ?>"><?php esc_html_e('Apply', 'openrouted'); ?></button><br>
                                                <button class="button delete-dashboard-alt-tag" data-id="<?php echo esc_attr($alt_tag->id); ?>"><?php esc_html_e('Reject', 'openrouted'); ?></button><br>
                                                <button class="button view-details" data-id="<?php echo esc_attr($alt_tag->id); ?>"><?php esc_html_e('Details', 'openrouted'); ?></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($pending_alt_tags) >= 100): ?>
                            <p class="openrouter-dashboard-action">
                                <button class="button load-more-pending"><?php esc_html_e('Load More', 'openrouted'); ?></button>
                            </p>
                        <?php endif; ?>
                        
                        <p class="openrouter-dashboard-action">
                            <button class="button button-primary apply-all-pending" id="apply-all-pending"><?php esc_html_e('Apply All Pending Alt Tags', 'openrouted'); ?></button>
                        </p>
                    <?php else: ?>
                        <p><?php esc_html_e('No pending alt tags.', 'openrouted'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Applied Tab -->
                <div class="tab-content" id="applied-tab">
                    <?php 
                    $applied_alt_tags = $this->generator->get_alt_tags('applied', 50);
                    if (!empty($applied_alt_tags)): 
                    ?>
                        <div class="table-container" id="applied-table-container">
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Image', 'openrouted'); ?></th>
                                        <th><?php esc_html_e('Alt Text', 'openrouted'); ?></th>
                                        <th><?php esc_html_e('Generated', 'openrouted'); ?></th>
                                        <th><?php esc_html_e('Applied', 'openrouted'); ?></th>
                                        <th><?php esc_html_e('Model', 'openrouted'); ?></th>
                                        <th><?php esc_html_e('Actions', 'openrouted'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applied_alt_tags as $alt_tag): 
                                        // Extract model from response if available
                                        $model = '';
                                        if (!empty($alt_tag->response)) {
                                            $response_data = json_decode($alt_tag->response, true);
                                            if (isset($response_data['model'])) {
                                                $model = $response_data['model'];
                                                // Extract just the model name, not the full path
                                                if (strpos($model, '/') !== false) {
                                                    $model = explode('/', $model)[1];
                                                }
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                $thumb_url = wp_get_attachment_image_url($alt_tag->image_id, 'thumbnail');
                                                if ($thumb_url) {
                                                    echo '<img src="' . esc_url($thumb_url) . '" width="60" height="60" alt="">';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo esc_html($alt_tag->alt_text); ?></td>
                                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($alt_tag->timestamp))); ?></td>
                                            <td><?php echo !empty($alt_tag->applied_timestamp) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($alt_tag->applied_timestamp))) : ''; ?></td>
                                            <td><?php echo esc_html($model); ?></td>
                                            <td>
                                                <button class="button view-details" data-id="<?php echo esc_attr($alt_tag->id); ?>"><?php esc_html_e('Details', 'openrouted'); ?></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($applied_alt_tags) >= 50): ?>
                            <p class="openrouter-dashboard-action">
                                <button class="button load-more-applied"><?php esc_html_e('Load More', 'openrouted'); ?></button>
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p><?php esc_html_e('No applied alt tags.', 'openrouted'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Activity Log Tab -->
                <div class="tab-content" id="logs-tab">
                    <div id="activity-log-container">
                        <p><?php esc_html_e('Loading activity log...', 'openrouted'); ?></p>
                    </div>
                    
                    <p class="openrouter-dashboard-action">
                        <button class="button refresh-log"><?php esc_html_e('Refresh Log', 'openrouted'); ?></button>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="openrouter-dashboard-card">
            <h2><?php esc_html_e('Available Models', 'openrouted'); ?></h2>
            
            <?php if (is_wp_error($models)): ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($models->get_error_message()); ?></p>
                </div>
            <?php elseif (empty($models)): ?>
                <p><?php esc_html_e('No free vision models available. Check your API key or try again later.', 'openrouted'); ?></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Model', 'openrouted'); ?></th>
                            <th><?php esc_html_e('Context Length', 'openrouted'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($models as $model): ?>
                            <tr>
                                <td><?php echo esc_html($model['name'] ?? $model['id']); ?></td>
                                <td><?php echo number_format($model['context_length'] ?? 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <hr>
            
            <h3><?php esc_html_e('Scheduled Runs', 'openrouted'); ?></h3>
            
            <?php 
            // Get the next scheduled run time using WordPress's built-in function
            $next_run = wp_next_scheduled('openrouted_daily_check');
            
            // Also check if we have stored schedule info (from the last update)
            $schedule_info = get_option('openrouted_last_schedule_update', array());
            
            // If we have stored info and no next run, use the stored next run
            if (empty($next_run) && !empty($schedule_info['next_run'])) {
                $next_run = $schedule_info['next_run'];
                // Logging removed for production
            } else if ($next_run) {
                // Logging removed for production
            } else {
                // Logging removed for production
            }
            
            // If no schedule found at all, try to fix it
            if (empty($next_run)) {
                // Get current frequency setting
                $current_frequency = get_option('openrouted_schedule_frequency', 'daily');
                
                // Create a new admin instance and update the schedule
                $admin = new Openrouted_Admin();
                $admin->update_cron_schedule($current_frequency, $current_frequency);
                
                // Try again
                $next_run = wp_next_scheduled('openrouted_daily_check');
                // Logging removed for production
            }
            
            // Get all settings
            $frequency = get_option('openrouted_schedule_frequency', 'daily');
            $batch_size = get_option('openrouted_batch_size', '20');
            $max_runtime = get_option('openrouted_max_runtime', '10');
            $request_delay = get_option('openrouted_request_delay', '2');
            
            // Check if process is currently running
            $process_lock = get_transient('openrouted_process_lock');
            
            // Display details about the scheduled jobs
            ?>
            <table class="widefat striped" style="margin-bottom: 20px;">
                <tr>
                    <th><?php esc_html_e('Next Scheduled Run:', 'openrouted'); ?></th>
                    <td>
                        <?php 
                        // Get cron status information
                        $cron_status = get_option('openrouted_cron_status', array());
                        $cron_last_attempt = get_option('openrouted_cron_last_attempt', 0);
                        ?>
                        
                        <?php if ($process_lock): ?>
                            <span style="color: #46b450; font-weight: bold;">
                                <?php esc_html_e('Process is currently running!', 'openrouted'); ?>
                            </span>
                            <br>
                            <?php 
                            printf(
                                /* translators: %1$s: Date and time of process start, %2$s: Time elapsed since process started */
                                esc_html__('Started at: %1$s (%2$s ago)', 'openrouted'),
                                esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $process_lock)),
                                esc_html(human_time_diff($process_lock, time()))
                            ); ?>
                        <?php elseif ($next_run): ?>
                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run)); ?>
                            (<?php echo esc_html(human_time_diff($next_run, time())); ?> <?php esc_html_e('from now', 'openrouted'); ?>)
                        <?php else: ?>
                            <?php esc_html_e('Not scheduled', 'openrouted'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th><?php esc_html_e('Last Cron Status:', 'openrouted'); ?></th>
                    <td>
                        <?php if (!empty($cron_status)): ?>
                            <?php
                            $status_color = '';
                            $status_icon = '';
                            
                            switch ($cron_status['status'] ?? '') {
                                case 'running':
                                    $status_color = '#0073aa'; // Blue
                                    $status_icon = '⚙️';
                                    break;
                                case 'completed':
                                    $status_color = '#46b450'; // Green
                                    $status_icon = '✅';
                                    break;
                                case 'error':
                                    $status_color = '#dc3232'; // Red
                                    $status_icon = '❌';
                                    break;
                                case 'skipped':
                                    $status_color = '#ffb900'; // Yellow
                                    $status_icon = '⏭️';
                                    break;
                                default:
                                    $status_color = '#666';
                                    $status_icon = '❓';
                            }
                            ?>
                            
                            <span style="color: <?php echo esc_attr($status_color); ?>; font-weight: bold;">
                                <?php echo esc_html($status_icon); ?> <?php echo esc_html(ucfirst($cron_status['status'] ?? 'Unknown')); ?>
                            </span>
                            
                            <?php if (!empty($cron_status['timestamp'])): ?>
                                <br>
                                <span class="description">
                                    <?php 
                                    printf(
                                        /* translators: %1$s: Date and time of the last update, %2$s: Time elapsed since the last update */
                                        esc_html__('Last update: %1$s (%2$s ago)', 'openrouted'),
                                        esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $cron_status['timestamp'])),
                                        esc_html(human_time_diff($cron_status['timestamp'], time()))
                                    ); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($cron_status['message'])): ?>
                                <br>
                                <span class="description"><?php echo esc_html($cron_status['message']); ?></span>
                            <?php endif; ?>
                        <?php elseif ($cron_last_attempt): ?>
                            <span class="description">
                                <?php 
                                printf(
                                    /* translators: %1$s: Date and time of the last attempt, %2$s: Time elapsed since the last attempt */
                                    esc_html__('Last attempt: %1$s (%2$s ago)', 'openrouted'),
                                    esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $cron_last_attempt)),
                                    esc_html(human_time_diff($cron_last_attempt, time()))
                                ); ?>
                            </span>
                        <?php else: ?>
                            <span class="description"><?php esc_html_e('No cron job has run yet', 'openrouted'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Frequency:', 'openrouted'); ?></th>
                    <td>
                        <?php 
                        $schedules = wp_get_schedules();
                        echo isset($schedules[$frequency]['display']) ? esc_html($schedules[$frequency]['display']) : esc_html($frequency);
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Images Per Run:', 'openrouted'); ?></th>
                    <td>
                        <?php echo $batch_size === 'all' ? esc_html__('All missing images', 'openrouted') : esc_html($batch_size); ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Max Runtime:', 'openrouted'); ?></th>
                    <td>
                        <?php 
                        if ($max_runtime == '0') {
                            esc_html_e('No limit', 'openrouted');
                        } else {
                            /* translators: %s: Number of minutes */
                            printf(esc_html(_n('%s minute', '%s minutes', intval($max_runtime), 'openrouted')), esc_html($max_runtime));
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Request Delay:', 'openrouted'); ?></th>
                    <td>
                        <?php 
                        if ($request_delay == '0') {
                            esc_html_e('No delay', 'openrouted');
                        } else {
                            /* translators: %s: Number of seconds */
                            printf(esc_html(_n('%s second', '%s seconds', intval($request_delay), 'openrouted')), esc_html($request_delay));
                        }
                        ?>
                    </td>
                </tr>
                <?php if (!$process_lock): ?>
                <tr>
                    <th><?php esc_html_e('Manual Control:', 'openrouted'); ?></th>
                    <td>
                        <button type="button" id="run-cron-now" class="button button-secondary">
                            <?php esc_html_e('Run Scheduled Check Now', 'openrouted'); ?>
                        </button>
                        <span class="spinner" style="float: none; margin: 0 0 0 5px;"></span>
                        <div class="cron-result" style="margin-top: 5px;"></div>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Troubleshooting:', 'openrouted'); ?></th>
                    <td>
                        <button type="button" id="refresh-cron-schedule" class="button">
                            <?php esc_html_e('Refresh Cron Schedule', 'openrouted'); ?>
                        </button>
                        <span class="spinner" style="float: none; margin: 0 0 0 5px;"></span>
                        <div class="refresh-result" style="margin-top: 5px;"></div>
                        <p class="description"><?php esc_html_e('If the scheduled time seems incorrect, click to refresh the schedule.', 'openrouted'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Advanced Info:', 'openrouted'); ?></th>
                    <td>
                        <p class="description">
                            <?php esc_html_e('Real-time information about the WordPress cron system and scheduling.', 'openrouted'); ?>
                        </p>
                        
                        <?php 
                        // Display cron details for debugging
                        global $wp_version;
                        $cron_array = _get_cron_array();
                        $has_openrouted_cron = false;
                        ?>
                        
                        <div style="margin-top: 10px; font-size: 12px;">
                            <p><strong><?php esc_html_e('Debug Info:', 'openrouted'); ?></strong></p>
                            <ul style="margin-left: 15px; list-style-type: disc;">
                                <li><?php 
                                /* translators: %s: WordPress version number */
                                echo esc_html(sprintf(__('WordPress Version: %s', 'openrouted'), $wp_version)); ?></li>
                                <li><?php 
                                /* translators: %s: Current date and time */
                                echo esc_html(sprintf(__('Current Time: %s', 'openrouted'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), time()))); ?></li>
                                <li><?php 
                                /* translators: %s: Selected schedule frequency */
                                echo esc_html(sprintf(__('Selected Schedule: %s', 'openrouted'), $frequency)); ?></li>
                                <li><?php 
                                /* translators: %s: Next scheduled date and time or "Not scheduled" message */
                                echo esc_html(sprintf(__('Timestamp from wp_next_scheduled(): %s', 'openrouted'), 
                                    wp_next_scheduled('openrouted_daily_check') ? 
                                    esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), wp_next_scheduled('openrouted_daily_check'))) : 
                                    esc_html__('Not scheduled', 'openrouted'))); ?>
                                </li>
                                <li>
                                    <?php 
                                    if (is_array($cron_array)) {
                                        foreach ($cron_array as $timestamp => $cron) {
                                            if (isset($cron['openrouted_daily_check'])) {
                                                /* translators: %s: Date and time */
                                                echo esc_html(sprintf(__('Found in cron array at: %s', 'openrouted'), 
                                                    esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp))));
                                                $has_openrouted_cron = true;
                                                break;
                                            }
                                        }
                                        if (!$has_openrouted_cron) {
                                            echo esc_html__('Not found in cron array', 'openrouted');
                                        }
                                    } else {
                                        echo esc_html__('Cron array is empty or invalid', 'openrouted');
                                    }
                                    ?>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            
            <h3><?php esc_html_e('Last Check', 'openrouted'); ?></h3>
            
            <?php if (empty($last_check) || empty($last_check['timestamp'])): ?>
                <p><?php esc_html_e('No checks have been run yet.', 'openrouted'); ?></p>
            <?php else: ?>
                <p>
                    <strong><?php esc_html_e('Date:', 'openrouted'); ?></strong> 
                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_check['timestamp'])); ?>
                </p>
                
                <?php if (!empty($last_check['results'])): ?>
                    <p>
                        <strong><?php esc_html_e('Results:', 'openrouted'); ?></strong><br>
                        <?php 
                        echo esc_html(sprintf(
                            /* translators: %1$d: Number of images without alt tags found, %2$d: Number of images processed, %3$d: Number of alt tags successfully generated */
                            esc_html__('Found %1$d images without alt tags, processed %2$d, generated %3$d new alt tags.', 'openrouted'),
                            $last_check['results']['found'],
                            $last_check['results']['processed'],
                            $last_check['results']['generated']
                        )); 
                        
                        // Show skipped and already processed counts if available
                        if (isset($last_check['results']['skipped']) || isset($last_check['results']['already_processed'])) {
                            echo '<br>' . esc_html(sprintf(
                                /* translators: %1$d: Number of images skipped because they are already pending, %2$d: Number of images skipped because they already have alt tags */
                                esc_html__('Skipped %1$d (already pending), %2$d already have alt tags.', 'openrouted'),
                                $last_check['results']['skipped'] ?? 0,
                                $last_check['results']['already_processed'] ?? 0
                            ));
                        }
                        
                        // Show remaining count if available
                        if (isset($last_check['results']['remaining'])) {
                            echo '<br>' . esc_html(sprintf(
                                /* translators: %d: Number of images still remaining to be processed */
                                esc_html__('Images remaining to process: %d', 'openrouted'),
                                $last_check['results']['remaining']
                            ));
                        }
                        
                        if (!empty($last_check['results']['runtime'])) {
                            echo '<br>' . esc_html(sprintf(
                                /* translators: %s: Total processing time in minutes */
                                esc_html__('Processing time: %s minutes', 'openrouted'),
                                $last_check['results']['runtime']
                            ));
                        }
                        ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="openrouter-dashboard-card full-width">
            <h2><?php esc_html_e('Generate Alt Tags', 'openrouted'); ?></h2>
            
            <?php if (empty($api_key)): ?>
                <p><?php esc_html_e('Please set your API key in the settings to enable alt tag generation.', 'openrouted'); ?></p>
            <?php else: ?>
                <div class="generate-controls">
                    <p><?php esc_html_e('Generate alt tags for images that are missing them.', 'openrouted'); ?></p>
                    
                    <div class="limit-input">
                        <label for="generate-limit"><?php esc_html_e('Number of images to process:', 'openrouted'); ?></label>
                        <select id="generate-limit">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    
                    <button id="run-bulk-generator" class="button button-primary"><?php esc_html_e('Generate Alt Tags Now', 'openrouted'); ?></button>
                    
                    <div id="generator-results" class="generator-results" style="display: none;">
                        <h3><?php esc_html_e('Results', 'openrouted'); ?></h3>
                        <div id="generator-message"></div>
                    </div>
                </div>
                
                <hr>
                
                <div class="plugin-info">
                    <h3><?php esc_html_e('How Alt Tag Generation Works', 'openrouted'); ?></h3>
                    <p><?php esc_html_e('This plugin uses OpenRouter\'s free vision AI models to analyze your images and generate descriptive alt tags. The process works as follows:', 'openrouted'); ?></p>
                    
                    <ol>
                        <li><?php esc_html_e('The plugin identifies images without alt tags in your Media Library.', 'openrouted'); ?></li>
                        <li><?php esc_html_e('It sends each image to OpenRouter\'s AI vision models for analysis.', 'openrouted'); ?></li>
                        <li><?php esc_html_e('The AI generates descriptive alt text based on the image content and your site context.', 'openrouted'); ?></li>
                        <li><?php esc_html_e('You can review and apply the generated alt tags in your Media Library or on this dashboard.', 'openrouted'); ?></li>
                    </ol>
                    
                    <p><?php esc_html_e('The plugin can also run automatically daily to check for new images without alt tags.', 'openrouted'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Details Modal -->
    <div id="alt-tag-details-modal" class="alt-tag-modal" style="display: none;">
        <div class="alt-tag-modal-content">
            <span class="alt-tag-modal-close">&times;</span>
            <h2><?php esc_html_e('Alt Tag Generation Details', 'openrouted'); ?></h2>
            
            <div class="alt-tag-details-content">
                <div class="alt-tag-details-section">
                    <h3><?php esc_html_e('Image', 'openrouted'); ?></h3>
                    <div id="modal-image"></div>
                </div>
                
                <div class="alt-tag-details-section">
                    <h3><?php esc_html_e('Generated Alt Text', 'openrouted'); ?></h3>
                    <div id="modal-alt-text"></div>
                </div>
                
                <div class="alt-tag-details-section">
                    <h3><?php esc_html_e('Generated with', 'openrouted'); ?></h3>
                    <div id="modal-model"></div>
                </div>
                
                <div class="alt-tag-details-tabs">
                    <div class="tab-navigation">
                        <button class="tab-button active" data-tab="request"><?php esc_html_e('Request', 'openrouted'); ?></button>
                        <button class="tab-button" data-tab="response"><?php esc_html_e('Response', 'openrouted'); ?></button>
                    </div>
                    
                    <div class="tab-content active" id="request-tab">
                        <div id="modal-request">
                            <pre class="code-block"></pre>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="response-tab">
                        <div id="modal-response">
                            <pre class="code-block"></pre>
                        </div>
                    </div>
                </div>
                
                <div class="alt-tag-details-section">
                    <h3><?php esc_html_e('Processing Time', 'openrouted'); ?></h3>
                    <div id="modal-duration"></div>
                </div>
                
                <div class="alt-tag-details-section">
                    <h3><?php esc_html_e('Status', 'openrouted'); ?></h3>
                    <div id="modal-status"></div>
                </div>
            </div>
        </div>
    </div>
</div>