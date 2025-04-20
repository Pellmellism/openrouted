<?php
/**
 * Handles communication with the OpenRouter API.
 *
 * @since      1.0.0
 * @package    Openrouted
 * @subpackage Openrouted/includes
 */

class Openrouted_API {

    /**
     * OpenRouter API key.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_key    The OpenRouter API key.
     */
    private $api_key;

    /**
     * Available models cache.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $models_cache    Cache of available models.
     */
    private $models_cache;

    /**
     * Exhausted models tracking.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $exhausted_models    Models that have hit quota limits.
     */
    private $exhausted_models;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->api_key = get_option('openrouted_api_key', '');
        $this->models_cache = get_transient('openrouted_models');
        $this->exhausted_models = get_transient('openrouted_exhausted_models');
        
        if (!$this->exhausted_models) {
            $this->exhausted_models = array();
        }
    }

    /**
     * Fetch available models from OpenRouter API.
     *
     * @since    1.0.0
     * @return   array|WP_Error    Array of models or WP_Error on failure.
     */
    public function get_models() {
        // Return cached models if available
        if ($this->models_cache) {
            return $this->models_cache;
        }

        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenRouter API key is not set.', 'openrouted'));
        }

        $response = wp_remote_get('https://openrouter.ai/api/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            $message = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown error', 'openrouted');
            
            return new WP_Error('api_error', sprintf(__('API Error (%d): %s', 'openrouted'), $code, $message));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['data']) || !is_array($data['data'])) {
            return new WP_Error('invalid_response', __('Invalid API response format.', 'openrouted'));
        }

        // Cache models for 12 hours
        $models = $data['data'];
        set_transient('openrouted_models', $models, 12 * HOUR_IN_SECONDS);
        $this->models_cache = $models;

        return $models;
    }

    /**
     * Get a list of free vision models available for use.
     *
     * @since    1.0.0
     * @return   array|WP_Error    Array of usable models or WP_Error on failure.
     */
    public function get_free_vision_models() {
        $models = $this->get_vision_models(true);
        
        error_log('Filtered free vision models: ' . count($models));
        if (empty($models)) {
            error_log('No free vision models available!');
        } else {
            error_log('Selected free model: ' . $models[0]['id']);
        }
        
        return $models;
    }
    
    /**
     * Get a list of paid vision models available for use.
     *
     * @since    1.0.0
     * @return   array|WP_Error    Array of usable paid models or WP_Error on failure.
     */
    public function get_paid_vision_models() {
        $models = $this->get_vision_models(false);
        
        error_log('Filtered paid vision models: ' . count($models));
        if (empty($models)) {
            error_log('No paid vision models available!');
        } else {
            error_log('Selected paid model: ' . $models[0]['id']);
        }
        
        return $models;
    }
    
    /**
     * Get a list of vision models (paid or free) available for use.
     *
     * @since    1.0.0
     * @param    bool       $free_only   Whether to return only free models
     * @return   array|WP_Error          Array of usable models or WP_Error on failure.
     */
    private function get_vision_models($free_only = true) {
        $models = $this->get_models();
        
        if (is_wp_error($models)) {
            error_log('Error getting models: ' . $models->get_error_message());
            return $models;
        }
        
        error_log('Models retrieved from API: ' . count($models));
        
        $filtered_models = array();
        
        foreach ($models as $model) {
            $model_id = isset($model['id']) ? $model['id'] : 'unknown';
            $is_free = isset($model['id']) && strpos($model['id'], ':free') !== false;
            
            // Skip paid models if we only want free models
            if ($free_only && !$is_free) {
                continue;
            }
            
            // Skip free models if we want paid models
            if (!$free_only && $is_free) {
                continue;
            }
            
            // Keep high context length requirement as per original design
            $has_context = isset($model['context_length']) && $model['context_length'] >= 96000;
            $is_vision = 
                (isset($model['description']) && stripos($model['description'], 'vision') !== false) || 
                (isset($model['name']) && stripos($model['name'], 'vision') !== false) || 
                stripos($model['id'], 'vision') !== false;
            $is_exhausted = in_array($model_id, $this->exhausted_models);
            
            error_log(sprintf(
                "Model %s: free=%s, context=%s, vision=%s, exhausted=%s",
                $model_id,
                $is_free ? 'yes' : 'no',
                $has_context ? 'yes' : 'no',
                $is_vision ? 'yes' : 'no',
                $is_exhausted ? 'yes' : 'no'
            ));
            
            // Only include models that:
            // - Have an ID
            // - Match free/paid requirement
            // - Have sufficient context length
            // - Are vision models
            // - Are not exhausted
            if (
                isset($model['id']) && 
                $has_context && 
                $is_vision && 
                !$is_exhausted
            ) {
                $filtered_models[] = $model;
            }
        }
        
        // Sort models by a priority score (based on name and quality)
        usort($filtered_models, function($a, $b) {
            // Define priority models
            $priority_keywords = [
                'claude-3' => 5,
                'gpt-4' => 4,
                'gemini' => 3,
                'llama' => 2,
                'mistral' => 1
            ];
            
            $score_a = 0;
            $score_b = 0;
            
            // Calculate priority score based on model name
            foreach ($priority_keywords as $keyword => $score) {
                if (stripos($a['id'], $keyword) !== false) {
                    $score_a = $score;
                }
                if (stripos($b['id'], $keyword) !== false) {
                    $score_b = $score;
                }
            }
            
            // If scores are different, sort by score (higher first)
            if ($score_a != $score_b) {
                return $score_b - $score_a;
            }
            
            // If scores are the same, sort by context length (higher first)
            return ($b['context_length'] ?? 0) - ($a['context_length'] ?? 0);
        });
        
        return $filtered_models;
    }

    /**
     * Generate alt text for an image using OpenRouter vision models.
     *
     * @since    1.0.0
     * @param    string    $image_url      URL of the image to generate alt text for
     * @param    array     $context        Optional. Additional context about the image and site
     * @param    string    $instructions   Optional. Custom instructions for alt text generation
     * @param    string    $specific_model_id Optional. Specific model ID to use
     * @return   array|WP_Error           Array containing the response or WP_Error on failure
     */
    public function generate_alt_text($image_url, $context = array(), $instructions = '', $specific_model_id = '') {
        // Start timer for performance tracking
        $start_time = microtime(true);
        
        error_log("Starting alt text generation for image: " . $image_url);
        
        // Log image info
        $image_parts = pathinfo($image_url);
        $image_size = $this->get_remote_image_size($image_url);
        if ($image_size) {
            error_log(sprintf("Image dimensions: %dx%d, extension: %s", 
                $image_size['width'], 
                $image_size['height'],
                isset($image_parts['extension']) ? $image_parts['extension'] : 'unknown'
            ));
        } else {
            error_log("Couldn't determine image dimensions for: " . $image_url);
        }
        
        if (empty($this->api_key)) {
            error_log("API key is not set");
            return new WP_Error('no_api_key', __('OpenRouter API key is not set.', 'openrouted'));
        }
        
        // Determine which model to use
        $model_id = '';
        
        // If a specific model ID is passed, use it (this overrides settings)
        if (!empty($specific_model_id) && $specific_model_id !== 'free') {
            error_log("Using specific model ID: " . $specific_model_id);
            
            // Get all models to check if the specified model exists
            $all_models = $this->get_models();
            
            if (!is_wp_error($all_models)) {
                foreach ($all_models as $model) {
                    if (isset($model['id']) && $model['id'] === $specific_model_id) {
                        $model_id = $specific_model_id;
                        error_log("Specified model found: " . $model_id);
                        break;
                    }
                }
            }
            
            // If specified model wasn't found, fall back to free models
            if (empty($model_id)) {
                error_log("Specified model not found, falling back to free models");
                $specific_model_id = 'free';
            }
        }
        
        // If we're using free models or the specified model wasn't found
        if (empty($model_id) || $specific_model_id === 'free') {
            error_log("Fetching free vision models from OpenRouter");
            $vision_models = $this->get_free_vision_models();
            
            if (is_wp_error($vision_models)) {
                error_log("Error getting vision models: " . $vision_models->get_error_message());
                return $vision_models;
            }
            
            if (empty($vision_models)) {
                error_log("No free vision models available");
                return new WP_Error('no_models', __('No free vision models available. Check back later.', 'openrouted'));
            }
            
            // Select the first available model
            $model_id = $vision_models[0]['id'];
        }
        
        error_log("Using model: " . $model_id);
        
        // Build prompt with context
        $prompt = "Generate a descriptive, SEO-friendly alt text for this image. The alt text should accurately describe what's in the image while incorporating relevant SEO keywords naturally.\n\n";
        
        if (!empty($context['title'])) {
            $prompt .= "Image Title: " . $context['title'] . "\n";
        }
        
        if (!empty($context['caption'])) {
            $prompt .= "Image Caption: " . $context['caption'] . "\n";
        }
        
        if (!empty($context['description'])) {
            $prompt .= "Image Description: " . $context['description'] . "\n\n";
        }
        
        if (!empty($context['site'])) {
            $prompt .= "Website Context:\n" . $context['site'] . "\n\n";
        }
        
        if (!empty($context['examples'])) {
            $prompt .= "Examples of existing alt tags on this site:\n" . $context['examples'] . "\n\n";
        }
        
        if (!empty($instructions)) {
            $prompt .= "Additional Instructions: " . $instructions . "\n\n";
        }
        
        $prompt .= "Respond ONLY with the recommended alt text in plain text format, nothing else. Keep it under 125 characters.";
        
        // Prepare messages for API call
        $messages = array(
            array(
                'role' => 'system',
                'content' => "You are an expert in website accessibility and SEO best practices. Your task is to generate descriptive, SEO-friendly alt text for images. The alt text should be concise (under 125 characters) but descriptive, conveying the image's purpose and content. Include relevant keywords naturally, without keyword stuffing."
            ),
            array(
                'role' => 'user',
                'content' => array(
                    array(
                        'type' => 'text',
                        'text' => $prompt
                    ),
                    array(
                        'type' => 'image_url',
                        'image_url' => array(
                            'url' => $image_url
                        )
                    )
                )
            )
        );
        
        // Prepare request
        $request_data = array(
            'model' => $model_id,
            'messages' => $messages
        );
        
        // For logging purposes
        $initial_payload = json_encode($request_data);
        
        // Log API call details
        error_log('Making API call to OpenRouter for image: ' . $image_url);
        error_log('Using model: ' . $model_id);
        
        // Call OpenRouter API - with enhanced timeouts for cron context
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => get_site_url(),
                'X-Title'       => 'OpenRouted WP Plugin (v' . OPENROUTED_VERSION . ')',
                'User-Agent'    => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            ),
            'timeout'     => 60,     // Increase timeout to 60 seconds for cron jobs
            'redirection' => 5,      // Allow up to 5 redirects
            'sslverify'   => true,   // Verify SSL certificate
            'body'    => $initial_payload,
        ));
        
        // Calculate duration
        $duration = microtime(true) - $start_time;
        
        error_log('API call completed in ' . $duration . ' seconds');
        
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            $error_code = $response->get_error_code();
            
            error_log("OpenRouter API error: " . $error_code . " - " . $error_msg);
            
            // Add more specific logging for common errors
            if (strpos($error_msg, 'cURL error 28') !== false) {
                error_log("OpenRouter API request timed out - consider increasing timeout or checking server connectivity");
            } else if (strpos($error_msg, 'cURL error 7') !== false || strpos($error_msg, 'cURL error 6') !== false) {
                error_log("OpenRouter API DNS or connection error - check your server's DNS settings and internet connectivity");
            } else if (strpos($error_msg, 'cURL error 35') !== false) {
                error_log("OpenRouter API SSL handshake failed - check your server's SSL configuration");
            }
            
            return new WP_Error('api_error', sprintf(
                __('API request failed: %s (Code: %s)', 'openrouted'),
                $error_msg,
                $error_code
            ));
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        $data = json_decode($body, true);
        
        // Log response headers for debugging
        if (!empty($headers)) {
            $header_log = [];
            foreach ($headers->getAll() as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                if ($key === 'x-ratelimit-remaining' || 
                    $key === 'x-ratelimit-limit' || 
                    $key === 'x-request-id') {
                    $header_log[] = "$key: $value";
                }
            }
            if (!empty($header_log)) {
                error_log("OpenRouter API headers: " . implode(', ', $header_log));
            }
        }
        
        if ($code !== 200) {
            $message = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown error', 'openrouted');
            error_log('API error: HTTP ' . $code . ' - ' . $message);
            error_log('API response body: ' . $body);
            
            // Handle rate limiting (model exhausted)
            if ($code === 429) {
                error_log('Model ' . $model_id . ' is exhausted (rate limited)');
                
                // Mark this model as exhausted
                $this->exhausted_models[] = $model_id;
                set_transient('openrouted_exhausted_models', $this->exhausted_models, DAY_IN_SECONDS);
                
                // Try another model if available
                if (isset($vision_models) && count($vision_models) > 1) {
                    // Remove the exhausted model and try again with the next one
                    array_shift($vision_models);
                    $model_id = $vision_models[0]['id'];
                    error_log('Trying alternative model: ' . $model_id);
                    return $this->generate_alt_text($image_url, $context, $instructions);
                }
                
                error_log('No alternative models available');
                return new WP_Error('rate_limit', sprintf(__('Daily limit reached for model %s. Try again tomorrow.', 'openrouted'), $model_id));
            }
            
            return new WP_Error('api_error', sprintf(__('API Error (%d): %s', 'openrouted'), $code, $message));
        }
        
        // Process successful response
        if (!isset($data['choices']) || !isset($data['choices'][0]['message'])) {
            return new WP_Error('invalid_response', __('Invalid API response format.', 'openrouted'));
        }
        
        $alt_text = trim($data['choices'][0]['message']['content']);
        
        // Limit to 125 characters if needed
        if (strlen($alt_text) > 125) {
            $alt_text = substr($alt_text, 0, 122) . '...';
        }
        
        return array(
            'alt_text' => $alt_text,
            'model' => $data['model'],
            'response' => $body,
            'duration' => $duration,
            'initial_payload' => $initial_payload
        );
    }
    
    /**
     * Get the size of a remote image.
     *
     * @since    1.0.0
     * @param    string    $url    URL of the image to check
     * @return   array|false       Array with width and height or false on failure
     */
    private function get_remote_image_size($url) {
        // Try to get image size without downloading the whole file
        $headers = get_headers($url, 1);
        if ($headers === false) {
            return false;
        }
        
        // Check if URL is accessible
        if (strpos($headers[0], '200') === false) {
            return false;
        }
        
        // Try to get image size using getimagesize
        try {
            $size = getimagesize($url);
            if ($size !== false) {
                return array(
                    'width' => $size[0],
                    'height' => $size[1]
                );
            }
        } catch (Exception $e) {
            // Ignore exceptions
        }
        
        return false;
    }
}