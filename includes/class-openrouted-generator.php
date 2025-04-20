<?php
/**
 * Handles alt tag generation.
 *
 * @since      1.0.0
 * @package    Openrouted
 * @subpackage Openrouted/includes
 */

class Openrouted_Generator {

    /**
     * API handler instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Openrouted_API    $api    The API handler instance.
     */
    private $api;

    /**
     * Plugin operation mode.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $mode    Plugin operation mode (manual, auto).
     */
    private $mode;

    /**
     * Custom instructions for AI prompts.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $custom_instructions    User-provided custom instructions.
     */
    private $custom_instructions;
    
    /**
     * Batch size for alt tag generation.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $batch_size    Number of images to process per run.
     */
    private $batch_size;
    
    /**
     * Maximum runtime for processing.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $max_runtime    Maximum runtime in minutes.
     */
    private $max_runtime;
    
    /**
     * Delay between API requests.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $request_delay    Delay between requests in seconds.
     */
    private $request_delay;

    /**
     * Custom model selection
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $model_selection    Model selection preference ('auto', 'paid')
     */
    private $model_selection;
    
    /**
     * Custom model ID
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $custom_model_id    User-specified model ID to use
     */
    private $custom_model_id;
    
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->api = new Openrouted_API();
        $this->mode = get_option('openrouted_mode', 'manual');
        $this->custom_instructions = get_option('openrouted_custom_instructions', '');
        $this->batch_size = get_option('openrouted_batch_size', '20');
        $this->max_runtime = get_option('openrouted_max_runtime', '10');
        $this->request_delay = get_option('openrouted_request_delay', '2');
        $this->model_selection = get_option('openrouted_model_selection', 'auto');
        $this->custom_model_id = get_option('openrouted_custom_model_id', '');
    }

    /**
     * Scheduled task to check for images without alt tags.
     * Run based on the user-selected frequency.
     *
     * @since    1.0.0
     */
    public function daily_check() {
        error_log('OpenRouted: Cron job started at ' . date('Y-m-d H:i:s'));
        update_option('openrouted_cron_last_attempt', time());
        
        // Check if the process is already running (locking mechanism)
        $lock = get_transient('openrouted_process_lock');
        if ($lock) {
            error_log('OpenRouted: Process already running since ' . date('Y-m-d H:i:s', $lock) . ', aborting this run');
            
            // If lock is older than 30 minutes, force clear it (it might be stale)
            if (time() - $lock > 30 * MINUTE_IN_SECONDS) {
                error_log('OpenRouted: Found stale process lock, clearing it');
                delete_transient('openrouted_process_lock');
            }
            
            update_option('openrouted_cron_status', array(
                'timestamp' => time(),
                'status' => 'skipped',
                'message' => 'Process already running since ' . human_time_diff($lock, time()) . ' ago'
            ));
            
            return; // Another process is already running
        }
        
        // Set a lock for 30 minutes (worst case scenario)
        set_transient('openrouted_process_lock', time(), 30 * MINUTE_IN_SECONDS);
        
        try {
            // Log and check for API key
            $api_key = get_option('openrouted_api_key', '');
            if (empty($api_key)) {
                error_log('OpenRouted: No API key set, aborting cron job');
                update_option('openrouted_cron_status', array(
                    'timestamp' => time(),
                    'status' => 'error',
                    'message' => 'No API key configured'
                ));
                return;
            }
            
            // Save status for dashboard display
            update_option('openrouted_cron_status', array(
                'timestamp' => time(),
                'status' => 'running',
                'message' => 'Cron job in progress...'
            ));
            
            // Get vision models based on settings
            error_log('OpenRouted: Fetching vision model from OpenRouter API');
            error_log('OpenRouted: Custom model ID: ' . $this->custom_model_id);
            
            // Determine which models to use based on settings
            $models = array();
            
            // The custom_model_id now holds one of the following values:
            // - "free" = use best free model
            // - a specific model ID = use that model
            if ($this->custom_model_id === 'free') {
                // Use free models
                error_log('OpenRouted: Using free vision models');
                $models = $this->api->get_free_vision_models();
            } else if (!empty($this->custom_model_id)) {
                // Use specified model ID
                error_log('OpenRouted: Using specific model ID: ' . $this->custom_model_id);
                
                // Get all models to check if the specified model exists
                $all_models = $this->api->get_models();
                
                if (!is_wp_error($all_models)) {
                    $custom_model_exists = false;
                    
                    foreach ($all_models as $model) {
                        if (isset($model['id']) && $model['id'] === $this->custom_model_id) {
                            $custom_model_exists = true;
                            error_log('OpenRouted: Specified model found in available models');
                            
                            // Create a custom models array with just this model
                            $models = array($model);
                            break;
                        }
                    }
                    
                    if (!$custom_model_exists) {
                        error_log('OpenRouted: Specified model ID not found, falling back to free models');
                        $models = $this->api->get_free_vision_models();
                    }
                }
            } else {
                // Default to free models if no selection is made
                error_log('OpenRouted: No model specified, defaulting to free models');
                $models = $this->api->get_free_vision_models();
            }
            
            if (is_wp_error($models)) {
                $error_message = $models->get_error_message();
                error_log('OpenRouted: API error - ' . $error_message);
                update_option('openrouted_cron_status', array(
                    'timestamp' => time(),
                    'status' => 'error',
                    'message' => 'API error: ' . $error_message
                ));
                return;
            }
            
            if (empty($models)) {
                error_log('OpenRouted: No vision models available');
                update_option('openrouted_cron_status', array(
                    'timestamp' => time(),
                    'status' => 'error',
                    'message' => 'No vision models available from OpenRouter'
                ));
                return;
            }
            
            error_log('OpenRouted: Selected model: ' . $models[0]['id']);
            
            // Determine batch size from settings
            $limit = $this->batch_size;
            if ($limit === 'all') {
                $limit = -1; // -1 means process all
            } else {
                $limit = intval($limit);
            }
            
            error_log('OpenRouted: Starting scan with batch size ' . ($limit === -1 ? 'all' : $limit));
            
            // Scan for images without alt tags using the configured limit
            $results = $this->scan_missing_alt_tags($limit);
            
            // Store a log of the execution
            update_option('openrouted_last_check', array(
                'timestamp' => current_time('timestamp'),
                'results' => $results
            ));
            
            error_log('OpenRouted: Successfully completed scheduled check - found ' . $results['found'] . ' images, processed ' . $results['processed'] . ', generated ' . $results['generated'] . ' alt tags in ' . $results['runtime'] . ' minutes');
            
            update_option('openrouted_cron_status', array(
                'timestamp' => time(),
                'status' => 'completed',
                'message' => sprintf(
                    'Generated %d alt tags from %d processed images (found %d total)',
                    $results['generated'],
                    $results['processed'],
                    $results['found']
                ),
                'results' => $results
            ));
        } catch (Exception $e) {
            // Catch any unexpected exceptions
            error_log('OpenRouted: Exception in cron job - ' . $e->getMessage());
            update_option('openrouted_cron_status', array(
                'timestamp' => time(),
                'status' => 'error',
                'message' => 'Exception: ' . $e->getMessage()
            ));
        } finally {
            // Always release the lock
            delete_transient('openrouted_process_lock');
            error_log('OpenRouted: Cron job completed at ' . date('Y-m-d H:i:s'));
        }
    }

    /**
     * Scan for images without alt tags.
     *
     * @since    1.0.0
     * @param    int       $limit    Optional. Maximum number of images to process. Use -1 for all.
     * @return   array               Results with count of found issues and generated alt tags.
     */
    public function scan_missing_alt_tags($limit = 10) {
        global $wpdb;
        $found = 0;
        $processed = 0;
        $generated = 0;
        $skipped = 0;
        $already_processed = 0;
        
        // Start time to track execution duration
        $start_time = time();
        $max_runtime_seconds = intval($this->max_runtime) * 60;
        
        // If max_runtime is 0, set a very large number
        if ($max_runtime_seconds <= 0) {
            $max_runtime_seconds = 24 * 60 * 60; // 24 hours (effectively no limit)
        }
        
        error_log("OpenRouted: Starting scan with max runtime of {$this->max_runtime} minutes");
        
        // If limit is -1, process all images
        $posts_per_page = ($limit === -1) ? 100 : $limit; // Process in chunks of 100 if all
        
        // Query for images without alt text
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $posts_per_page,
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
            ),
            // Randomize the order to get different images each run
            'orderby' => 'rand'
        );
        
        $query = new WP_Query($args);
        $found = $query->found_posts;
        $total_to_process = ($limit === -1) ? $found : min($found, $limit);
        
        error_log("OpenRouted: Found $found images without alt tags, will process up to $total_to_process");
        
        if ($found > 0) {
            // Get site content samples for context
            $site_context = $this->get_site_context();
            
            // Get existing alt tags for examples
            $example_alt_tags = $this->get_example_alt_tags();
            
            // Process images
            $offset = 0;
            $continue = true;
            
            while ($continue && ($processed < $total_to_process)) {
                if ($offset > 0) {
                    // If we need more images, run another query with offset
                    $args['offset'] = $offset;
                    $query = new WP_Query($args);
                    
                    if (empty($query->posts)) {
                        break; // No more images to process
                    }
                }
                
                foreach ($query->posts as $image) {
                    // Check if we're out of time
                    if ((time() - $start_time) > $max_runtime_seconds) {
                        error_log("OpenRouted: Reached maximum runtime of {$this->max_runtime} minutes");
                        $continue = false;
                        break;
                    }
                    
                    // Check if we've processed enough images
                    if ($processed >= $total_to_process) {
                        $continue = false;
                        break;
                    }
                    
                    $processed++;
                    
                    // Skip if we already have any recommendation for this image (pending or applied)
                    $existing_pending = $this->get_alt_tag_by_image_id($image->ID, 'pending');
                    $existing_applied = $this->get_alt_tag_by_image_id($image->ID, 'applied');
                    
                    if ($existing_pending) {
                        error_log("OpenRouted: Skipping image {$image->ID} - already has pending alt tag");
                        $skipped++;
                        continue;
                    }
                    
                    if ($existing_applied) {
                        error_log("OpenRouted: Skipping image {$image->ID} - already has applied alt tag");
                        $already_processed++;
                        continue;
                    }
                    
                    // Check if the image already has an alt tag in WordPress metadata
                    $current_alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
                    if (!empty($current_alt)) {
                        error_log("OpenRouted: Skipping image {$image->ID} - already has alt text: {$current_alt}");
                        $already_processed++;
                        continue;
                    }
                    
                    // Generate alt tag
                    $result = $this->generate_alt_tag_for_image($image->ID);
                    
                    // Debug - log the result
                    error_log('Alt tag generation result for image ID ' . $image->ID . ': ' . (is_wp_error($result) ? $result->get_error_message() : 'Success'));
                    
                    if (!is_wp_error($result)) {
                        $generated++;
                        
                        // If in auto mode, apply immediately
                        if ($this->mode === 'auto') {
                            $this->apply_alt_tag($result['id']);
                        }
                        
                        // Add delay between requests if configured
                        $delay = intval($this->request_delay);
                        if ($delay > 0) {
                            error_log("OpenRouted: Waiting for {$delay} seconds before next request");
                            sleep($delay);
                        }
                    } else {
                        // Log the error
                        error_log('Alt tag generation error: ' . $result->get_error_message());
                    }
                }
                
                // Prepare for next batch if needed
                $offset += $posts_per_page;
            }
        }
        
        // Calculate runtime
        $total_runtime = time() - $start_time;
        $runtime_minutes = round($total_runtime / 60, 1);
        
        // Calculate remaining that still need processing
        $remaining = $found - $generated - $already_processed;
        
        error_log("OpenRouted: Completed scanning in $runtime_minutes minutes.");
        error_log("OpenRouted: Summary - Found: $found, Processed: $processed, Generated: $generated");
        error_log("OpenRouted: Summary - Skipped: $skipped, Already processed: $already_processed, Remaining: $remaining");
        
        return array(
            'found' => $found,
            'processed' => $processed,
            'generated' => $generated,
            'skipped' => $skipped,
            'already_processed' => $already_processed,
            'remaining' => $remaining,
            'runtime' => $runtime_minutes
        );
    }

    /**
     * Generate alt tag for a specific image.
     *
     * @since    1.0.0
     * @param    int       $image_id    The image attachment ID.
     * @return   array|WP_Error         The generated alt tag data or error.
     */
    public function generate_alt_tag_for_image($image_id) {
        $image = get_post($image_id);
        
        if (!$image || $image->post_type !== 'attachment' || strpos($image->post_mime_type, 'image') !== 0) {
            return new WP_Error('invalid_image', __('Not a valid image attachment.', 'openrouted'));
        }
        
        // Get image details
        $image_url = wp_get_attachment_url($image_id);
        $image_title = $image->post_title;
        $image_caption = $image->post_excerpt;
        $image_description = $image->post_content;
        
        // Get site context
        $context = array(
            'title' => $image_title,
            'caption' => $image_caption,
            'description' => $image_description,
            'site' => $this->get_site_context(),
            'examples' => $this->get_example_alt_tags()
        );
        
        // Call the API with the selected model
        error_log('OpenRouted: Using model for image ' . $image_id . ': ' . $this->custom_model_id);
        $response = $this->api->generate_alt_text($image_url, $context, $this->custom_instructions, $this->custom_model_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Store the generated alt tag
        return $this->save_alt_tag(
            $image_id, 
            $response['alt_text'], 
            $response['response'], 
            $response['duration'], 
            isset($response['initial_payload']) ? $response['initial_payload'] : ''
        );
    }

    /**
     * Save generated alt tag to database.
     *
     * @since    1.0.0
     * @param    int       $image_id       Image attachment ID.
     * @param    string    $alt_text       Generated alt text.
     * @param    string    $response       API response for reference.
     * @param    float     $duration       API call duration.
     * @param    string    $initial_payload The initial API request payload (optional).
     * @return   array                     Saved alt tag data.
     */
    public function save_alt_tag($image_id, $alt_text, $response = '', $duration = 0, $initial_payload = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'openrouted';
        
        $wpdb->insert(
            $table_name,
            array(
                'image_id' => $image_id,
                'alt_text' => $alt_text,
                'status' => 'pending',
                'response' => $response,
                'duration' => $duration,
                'initial_payload' => $initial_payload
            ),
            array('%d', '%s', '%s', '%s', '%f', '%s')
        );
        
        $id = $wpdb->insert_id;
        
        return array(
            'id' => $id,
            'image_id' => $image_id,
            'alt_text' => $alt_text,
            'status' => 'pending',
            'timestamp' => current_time('mysql'),
            'initial_payload' => $initial_payload
        );
    }

    /**
     * Apply alt tag to image.
     *
     * @since    1.0.0
     * @param    int       $id    Alt tag record ID.
     * @return   bool             True on success, false on failure.
     */
    public function apply_alt_tag($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'openrouted';
        
        // Get the alt tag record
        $alt_tag = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        
        if (!$alt_tag) {
            return false;
        }
        
        // Update the image's alt text
        $updated = update_post_meta($alt_tag->image_id, '_wp_attachment_image_alt', sanitize_text_field($alt_tag->alt_text));
        
        if ($updated) {
            // Update the alt tag status
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'applied',
                    'applied_timestamp' => current_time('mysql')
                ),
                array('id' => $id),
                array('%s', '%s'),
                array('%d')
            );
            
            return true;
        }
        
        return false;
    }

    /**
     * Get alt tag by ID.
     *
     * @since    1.0.0
     * @param    int       $id    Alt tag record ID.
     * @return   object|null      Alt tag record or null if not found.
     */
    public function get_alt_tag($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'openrouted';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }

    /**
     * Get alt tag by image ID and status.
     *
     * @since    1.0.0
     * @param    int       $image_id    Image attachment ID.
     * @param    string    $status      Optional. Filter by status.
     * @return   object|null            Alt tag record or null if not found.
     */
    public function get_alt_tag_by_image_id($image_id, $status = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'openrouted';
        
        if ($status) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE image_id = %d AND status = %s ORDER BY id DESC LIMIT 1",
                $image_id, $status
            ));
        } else {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE image_id = %d ORDER BY id DESC LIMIT 1",
                $image_id
            ));
        }
    }

    /**
     * Get all alt tags with pagination and filtering.
     *
     * @since    1.0.0
     * @param    string    $status     Optional. Filter by status (pending, applied, rejected).
     * @param    int       $limit      Optional. Number of records to retrieve.
     * @param    int       $offset     Optional. Offset for pagination.
     * @return   array                 Array of alt tag objects.
     */
    public function get_alt_tags($status = null, $limit = 50, $offset = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'openrouted';
        
        if ($status) {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE status = %s ORDER BY timestamp DESC LIMIT %d OFFSET %d",
                $status, $limit, $offset
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT %d OFFSET %d",
                $limit, $offset
            );
        }
        
        return $wpdb->get_results($query);
    }

    /**
     * Count alt tags by status.
     *
     * @since    1.0.0
     * @return   array    Counts indexed by status.
     */
    public function count_alt_tags() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'openrouted';
        
        $counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $table_name GROUP BY status",
            OBJECT_K
        );
        
        $result = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
            'pending' => 0,
            'applied' => 0,
            'rejected' => 0
        );
        
        foreach ($counts as $status => $data) {
            $result[$status] = intval($data->count);
        }
        
        return $result;
    }

    /**
     * Delete alt tag (mark as rejected).
     *
     * @since    1.0.0
     * @param    int       $id    Alt tag record ID.
     * @return   bool            True on success, false on failure.
     */
    public function delete_alt_tag($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'openrouted';
        
        return $wpdb->update(
            $table_name,
            array('status' => 'rejected'),
            array('id' => $id),
            array('%s'),
            array('%d')
        ) !== false;
    }
    
    /**
     * Get more alt tags for pagination.
     *
     * @since    1.0.0
     * @param    string    $status     Status of alt tags to retrieve (pending, applied, rejected)
     * @param    int       $offset     Offset for pagination
     * @param    int       $limit      Maximum number of records to retrieve
     * @return   array                 Array of alt tag objects
     */
    public function get_more_alt_tags($status, $offset = 0, $limit = 10) {
        return $this->get_alt_tags($status, $limit, $offset);
    }
    
    /**
     * Count alt tags by specific status.
     *
     * @since    1.0.0
     * @param    string    $status     Status to count
     * @return   int                   Number of alt tags with the specified status
     */
    public function count_alt_tags_by_status($status) {
        $counts = $this->count_alt_tags();
        return isset($counts[$status]) ? $counts[$status] : 0;
    }
    
    /**
     * Get activity log.
     *
     * @since    1.0.0
     * @param    int       $limit      Maximum number of records to retrieve
     * @return   array                 Activity log entries
     */
    public function get_activity_log($limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'openrouted';
        
        // Get recent activities
        $activities = array();
        
        // Get recently generated alt tags
        $generated = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, image_id, alt_text, timestamp, response 
                FROM $table_name 
                ORDER BY timestamp DESC 
                LIMIT %d",
                $limit
            )
        );
        
        foreach ($generated as $entry) {
            // Extract model information
            $model = '';
            if (!empty($entry->response)) {
                $response_data = json_decode($entry->response, true);
                if (isset($response_data['model'])) {
                    $model = $response_data['model'];
                }
            }
            
            $image_title = '';
            if ($entry->image_id) {
                $image = get_post($entry->image_id);
                if ($image) {
                    $image_title = $image->post_title;
                }
            }
            
            $activities[] = array(
                'time' => $entry->timestamp,
                'action' => 'Generated',
                'details' => sprintf(
                    __('Alt tag for image "%s" using model %s: "%s"', 'openrouted'),
                    $image_title,
                    $model,
                    wp_trim_words($entry->alt_text, 10, '...')
                )
            );
        }
        
        // Get recently applied alt tags
        $applied = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, image_id, alt_text, applied_timestamp 
                FROM $table_name 
                WHERE status = 'applied' AND applied_timestamp IS NOT NULL
                ORDER BY applied_timestamp DESC 
                LIMIT %d",
                $limit
            )
        );
        
        foreach ($applied as $entry) {
            $image_title = '';
            if ($entry->image_id) {
                $image = get_post($entry->image_id);
                if ($image) {
                    $image_title = $image->post_title;
                }
            }
            
            $activities[] = array(
                'time' => $entry->applied_timestamp,
                'action' => 'Applied',
                'details' => sprintf(
                    __('Alt tag for image "%s": "%s"', 'openrouted'),
                    $image_title,
                    wp_trim_words($entry->alt_text, 10, '...')
                )
            );
        }
        
        // Get recently rejected alt tags
        $rejected = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, image_id, alt_text, timestamp 
                FROM $table_name 
                WHERE status = 'rejected'
                ORDER BY timestamp DESC 
                LIMIT %d",
                $limit
            )
        );
        
        foreach ($rejected as $entry) {
            $image_title = '';
            if ($entry->image_id) {
                $image = get_post($entry->image_id);
                if ($image) {
                    $image_title = $image->post_title;
                }
            }
            
            $activities[] = array(
                'time' => $entry->timestamp,
                'action' => 'Rejected',
                'details' => sprintf(
                    __('Alt tag for image "%s": "%s"', 'openrouted'),
                    $image_title,
                    wp_trim_words($entry->alt_text, 10, '...')
                )
            );
        }
        
        // Sort all activities by timestamp in descending order
        usort($activities, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
        
        // Limit to the specified number
        return array_slice($activities, 0, $limit);
    }

    /**
     * Get example alt tags from the site.
     *
     * @since    1.0.0
     * @return   string    Examples of existing alt tags.
     */
    private function get_example_alt_tags() {
        // Query for images with alt text
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => 5,
            'meta_query' => array(
                array(
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '!='
                )
            )
        );
        
        $query = new WP_Query($args);
        $examples = '';
        
        if ($query->have_posts()) {
            foreach ($query->posts as $image) {
                $alt_text = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
                if (!empty($alt_text)) {
                    $examples .= "- " . $image->post_title . ": \"" . $alt_text . "\"\n";
                }
            }
        }
        
        if (empty($examples)) {
            $examples = "No existing alt tags found on this site. Create alt text that is descriptive, concise, and SEO-friendly.";
        }
        
        return $examples;
    }

    /**
     * Get site content for context.
     *
     * @since    1.0.0
     * @return   string    Site content samples for context.
     */
    private function get_site_context() {
        $context = "Site Name: " . get_bloginfo('name') . "\n";
        $context .= "Site Description: " . get_bloginfo('description') . "\n\n";
        
        // Get recent posts
        $recent_posts = get_posts(array(
            'posts_per_page' => 3,
            'post_status' => 'publish'
        ));
        
        if (!empty($recent_posts)) {
            $context .= "Recent Content Samples:\n";
            
            foreach ($recent_posts as $post) {
                $context .= "- Title: " . $post->post_title . "\n";
                $context .= "  Excerpt: " . (empty($post->post_excerpt) ? wp_trim_words($post->post_content, 30) : $post->post_excerpt) . "\n\n";
            }
        }
        
        // Get active categories
        $categories = get_categories(array(
            'hide_empty' => true,
            'number' => 5
        ));
        
        if (!empty($categories)) {
            $context .= "Site Categories:\n";
            $category_names = array();
            
            foreach ($categories as $category) {
                $category_names[] = $category->name;
            }
            
            $context .= implode(', ', $category_names) . "\n\n";
        }
        
        return $context;
    }
}