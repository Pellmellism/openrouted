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
     * @var      array|null    $models_cache    Cache of available models. Null if not fetched yet.
     */
    private $models_cache = null;

    /**
     * Exhausted models tracking.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $exhausted_models    Models that have hit quota limits in the current request lifecycle or stored transient.
     */
    private $exhausted_models;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->api_key = get_option('openrouted_api_key', '');
        $this->exhausted_models = get_transient('openrouted_exhausted_models');

        if (!is_array($this->exhausted_models)) {
            $this->exhausted_models = array();
        }
    }

    /**
     * Fetch available models from OpenRouter API.
     * Caches the result in a transient and instance variable.
     *
     * @since    1.0.0
     * @param    bool $force_refresh Whether to ignore the cache and fetch fresh data.
     * @return   array|WP_Error      Array of models or WP_Error on failure.
     */
    public function get_models($force_refresh = false) {
        // Return instance cache if available and not forcing refresh.
        if (!$force_refresh && $this->models_cache !== null) {
            return $this->models_cache;
        }

        // Return transient cache if available and not forcing refresh.
        if (!$force_refresh) {
            $cached_models = get_transient('openrouted_models');
            if ($cached_models !== false) {
                $this->models_cache = $cached_models;
                return $this->models_cache;
            }
        }

        // Check for API key.
        if (empty($this->api_key)) {
            $this->models_cache = new WP_Error('no_api_key', __('OpenRouter API key is not set.', 'openrouted'));
            return $this->models_cache;
        }

        // Fetch models from the API.
        $response = wp_remote_get('https://openrouter.ai/api/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url') . ' (OpenRouted Plugin/' . OPENROUTED_VERSION . ')',
            ),
            'timeout' => 30,
        ));

        // Handle WP_Error during request.
        if (is_wp_error($response)) {
            $this->models_cache = $response;
            return $this->models_cache;
        }

        // Handle API response errors.
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $message = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown error', 'openrouted');
            $this->models_cache = new WP_Error(
                'api_error',
                sprintf(
                    /* translators: 1: HTTP status code, 2: Error message */
                    __('API Error (%1$d): %2$s', 'openrouted'),
                    $code,
                    $message
                )
            );
            return $this->models_cache;
        }

        // Validate response format.
        if (!isset($data['data']) || !is_array($data['data'])) {
            $this->models_cache = new WP_Error('invalid_response', __('Invalid API response format.', 'openrouted'));
            return $this->models_cache;
        }

        // Cache models for 12 hours and store in instance variable.
        $models = $data['data'];
        set_transient('openrouted_models', $models, 12 * HOUR_IN_SECONDS);
        $this->models_cache = $models;

        return $models;
    }

    /**
     * Get a list of free vision models available for use.
     * Filters models based on criteria and sorts by priority.
     *
     * @since    1.0.0
     * @param    bool $force_refresh Whether to force refresh the model list.
     * @return   array|WP_Error      Array of usable models or WP_Error on failure.
     */
    public function get_free_vision_models($force_refresh = false) {
        $models = $this->get_vision_models(true, $force_refresh);
        return $models;
    }

    /**
     * Get a list of paid vision models available for use.
     * Filters models based on criteria and sorts by priority.
     *
     * @since    1.0.0
     * @param    bool $force_refresh Whether to force refresh the model list.
     * @return   array|WP_Error      Array of usable paid models or WP_Error on failure.
     */
    public function get_paid_vision_models($force_refresh = false) {
        $models = $this->get_vision_models(false, $force_refresh);
        return $models;
    }

    /**
     * Get a list of vision models (paid or free) available for use.
     * Filters all models based on vision capability, context length, and exhaustion status.
     *
     * @since    1.0.0
     * @param    bool       $free_only       Whether to return only free models.
     * @param    bool       $force_refresh   Whether to force refresh the model list.
     * @return   array|WP_Error              Array of usable models or WP_Error on failure.
     */
    private function get_vision_models($free_only = true, $force_refresh = false) {
        $all_models = $this->get_models($force_refresh);

        if (is_wp_error($all_models)) {
            return $all_models;
        }

        $filtered_models = array();
        $current_exhausted = $this->exhausted_models; // Use current list

        foreach ($all_models as $model) {
            // Basic validation.
            if (!isset($model['id']) || !is_string($model['id'])) continue;
            $model_id = $model['id'];

            // Check if model matches free/paid requirement.
            $is_free = strpos($model_id, ':free') !== false;
            if ($free_only !== $is_free) {
                continue;
            }

            // Check context length (optional requirement, kept from original).
            // Note: 96k context might be unnecessarily high for alt text generation.
            // Consider lowering or removing this if it excludes useful models.
            $has_sufficient_context = isset($model['context_length']) && is_numeric($model['context_length']) && $model['context_length'] >= 96000;

            // Check if it's a vision model.
            $is_vision = (isset($model['description']) && stripos($model['description'], 'vision') !== false) ||
                         (isset($model['name']) && stripos($model['name'], 'vision') !== false) ||
                         (stripos($model_id, 'vision') !== false);

            // Check if the model is marked as exhausted.
            $is_exhausted = in_array($model_id, $current_exhausted, true);

            // Add to filtered list if it meets all criteria.
            if ($has_sufficient_context && $is_vision && !$is_exhausted) {
                $filtered_models[] = $model;
            }
        }

        // Sort models by priority (e.g., prefer specific models, then by context length).
        usort($filtered_models, array($this, 'sort_models_by_priority'));

        return $filtered_models;
    }

    /**
     * Sorting callback function for vision models.
     * Prioritizes specific models, then context length.
     *
     * @since 1.0.0
     * @param array $a First model for comparison.
     * @param array $b Second model for comparison.
     * @return int Comparison result (-1, 0, 1).
     */
    private function sort_models_by_priority($a, $b) {
        // Define priority keywords and their scores (higher is better).
        $priority_keywords = [
            'claude-3\.5' => 10, // Highest priority for newer models
            'claude-3'   => 9,
            'gpt-4o'     => 8, // Newer GPT-4 variant
            'gpt-4'      => 7,
            'gemini'     => 6,
            'llama'      => 5,
            'mistral'    => 4,
        ];

        $score_a = 0;
        $score_b = 0;

        // Calculate priority score based on model ID matching keywords.
        foreach ($priority_keywords as $keyword => $score) {
            if (preg_match('/' . $keyword . '/i', $a['id'])) {
                $score_a = max($score_a, $score);
            }
            if (preg_match('/' . $keyword . '/i', $b['id'])) {
                $score_b = max($score_b, $score);
            }
        }

        // If scores are different, sort by score (descending).
        if ($score_a !== $score_b) {
            return $score_b - $score_a;
        }

        // If scores are the same, sort by context length (descending).
        $context_a = isset($a['context_length']) && is_numeric($a['context_length']) ? $a['context_length'] : 0;
        $context_b = isset($b['context_length']) && is_numeric($b['context_length']) ? $b['context_length'] : 0;
        return $context_b - $context_a;
    }

    /**
     * Generate alt text for an image using OpenRouter vision models.
     * Selects the appropriate model based on settings or availability.
     * Handles API errors, rate limits, and retries with alternative models if necessary.
     *
     * @since    1.0.0
     * @param    string    $image_url          URL of the image to generate alt text for.
     * @param    array     $context            Optional. Additional context (title, caption, site info).
     * @param    string    $instructions       Optional. Custom instructions for alt text generation.
     * @param    string    $specific_model_id  Optional. Specific model ID selected in settings.
     * @return   array|WP_Error               Array containing the response or WP_Error on failure.
     */
    public function generate_alt_text($image_url, $context = array(), $instructions = '', $specific_model_id = '') {
        $start_time = microtime(true);

        // Check API key.
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenRouter API key is not set.', 'openrouted'));
        }

        // --- Model Selection Logic ---
        $models_to_try = array();
        $current_model_id = '';

        // 1. Use specific model ID if provided and valid.
        if (!empty($specific_model_id) && $specific_model_id !== 'free') {
            $all_models = $this->get_models(); // Don't force refresh here, use cache if available.
            $model_found = false;
            if (!is_wp_error($all_models)) {
                foreach ($all_models as $model) {
                    if (isset($model['id']) && $model['id'] === $specific_model_id) {
                         // Check if it's also a vision model (important consistency check).
                        $is_vision = (isset($model['description']) && stripos($model['description'], 'vision') !== false) ||
                                     (isset($model['name']) && stripos($model['name'], 'vision') !== false) ||
                                     (stripos($model['id'], 'vision') !== false);
                        if ($is_vision) {
                            $models_to_try[] = $model; // Add the specific model first.
                            $model_found = true;
                        }
                        break;
                    }
                }
            }
        }

        // 2. If no specific valid model, or if 'free' was selected, get the list of free models.
        if (empty($models_to_try) || $specific_model_id === 'free') {
            $free_vision_models = $this->get_free_vision_models(); // Uses cache by default.

            if (is_wp_error($free_vision_models)) {
                return $free_vision_models; // Cannot proceed without models.
            }
            if (empty($free_vision_models)) {
                return new WP_Error('no_models', __('No free vision models available. Check back later or select a paid model.', 'openrouted'));
            }
            // Add free models to the list to try (after specific, if any).
            $models_to_try = array_merge($models_to_try, $free_vision_models);
             // Remove duplicates if specific model was also free
            $models_to_try = array_map("unserialize", array_unique(array_map("serialize", $models_to_try)));
        }

        // Check if we have any models left to try after filtering/selection.
        if (empty($models_to_try)) {
             return new WP_Error('no_suitable_models', __('No suitable vision models found based on current settings and availability.', 'openrouted'));
        }

        // --- Prompt Construction ---
        $system_prompt = "You are an expert in website accessibility and SEO best practices. Your task is to generate descriptive, SEO-friendly alt text for images. The alt text should be concise (under 125 characters) but descriptive, conveying the image's purpose and content. Include relevant keywords naturally, without keyword stuffing.";
        $user_prompt_text = "Generate a descriptive, SEO-friendly alt text for this image. The alt text should accurately describe what's in the image while incorporating relevant SEO keywords naturally if appropriate.\n\n";

        // Add context if available.
        if (!empty($context['title'])) $user_prompt_text .= "Image Title: " . $context['title'] . "\n";
        if (!empty($context['caption'])) $user_prompt_text .= "Image Caption: " . $context['caption'] . "\n";
        if (!empty($context['description'])) $user_prompt_text .= "Image Description: " . $context['description'] . "\n";
        if (!empty($context['site'])) $user_prompt_text .= "\nWebsite Context:\n" . $context['site'] . "\n";
        // if (!empty($context['examples'])) $user_prompt_text .= "\nExamples of existing alt tags on this site:\n" . $context['examples'] . "\n";
        if (!empty($instructions)) $user_prompt_text .= "\nAdditional Instructions: " . $instructions . "\n";

        $user_prompt_text .= "\nRespond ONLY with the recommended alt text in plain text format, nothing else. Keep it under 125 characters.";

        // --- API Call Loop ---
        $last_error = null;

        foreach ($models_to_try as $model_info) {
            $current_model_id = $model_info['id'];

            // Check if this model was marked exhausted *during this request sequence*
            if (in_array($current_model_id, $this->exhausted_models, true)) {
                continue;
            }

            // Prepare API request payload.
            $messages = array(
                array('role' => 'system', 'content' => $system_prompt),
                array(
                    'role' => 'user',
                    'content' => array(
                        array('type' => 'text', 'text' => $user_prompt_text),
                        array('type' => 'image_url', 'image_url' => array('url' => $image_url)),
                    )
                )
            );

            $request_data = array(
                'model' => $current_model_id,
                'messages' => $messages,
                'max_tokens' => 100, // Limit output tokens slightly above expected length.
            );
            $request_body = wp_json_encode($request_data);
            if ($request_body === false) {
                 $last_error = new WP_Error('json_encode_error', __('Failed to prepare API request.', 'openrouted'));
                 continue; // Try next model
            }

            // Make the API call.
            $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => home_url(), // Use home_url() for site URL.
                    'X-Title'       => 'OpenRouted WP Plugin (v' . OPENROUTED_VERSION . ')',
                    'User-Agent'    => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url') . ' (OpenRouted Plugin/' . OPENROUTED_VERSION . ')',
                ),
                'timeout'     => 60, // Increased timeout.
                'redirection' => 5,
                'sslverify'   => true,
                'body'        => $request_body,
            ));

            $duration = microtime(true) - $start_time;

            // Handle WP_Error during request.
            if (is_wp_error($response)) {
                $error_msg = $response->get_error_message();
                $error_code = $response->get_error_code();
                // Store the error and try the next model.
                $last_error = new WP_Error(
                    'api_request_failed',
                     sprintf(
                        /* translators: 1: Error message string, 2: Error code string */
                        __('API request failed: %1$s (Code: %2$s)', 'openrouted'),
                        $error_msg,
                        $error_code
                    )
                );
                continue; // Try next model
            }

            // Process the response.
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            // Handle non-200 responses.
            if ($code !== 200) {
                $message = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown API error', 'openrouted');

                // Handle Rate Limiting (429).
                if ($code === 429) {
                    $this->exhausted_models[] = $current_model_id;
                    // Update transient *immediately* so subsequent calls in same run know.
                    set_transient('openrouted_exhausted_models', array_unique($this->exhausted_models), DAY_IN_SECONDS);
                    $last_error = new WP_Error(
                        'rate_limit',
                        sprintf(
                            /* translators: %s: The ID of the rate-limited AI model */
                            __('Daily limit likely reached for model %s. Trying alternatives or check back later.', 'openrouted'),
                            $current_model_id
                        )
                    );
                    continue; // Try next model
                } else {
                    // Other API errors.
                     $last_error = new WP_Error(
                        'api_response_error',
                         sprintf(
                            /* translators: 1: HTTP status code, 2: Error message */
                            __('API Error (%1$d): %2$s', 'openrouted'),
                            $code,
                            $message
                         )
                    );
                    continue; // Try next model
                }
            }

            // --- Success Case ---
            if (!isset($data['choices'][0]['message']['content'])) {
                $last_error = new WP_Error('invalid_response_format', __('Invalid API response format.', 'openrouted'));
                continue; // Try next model, maybe it gives a better response.
            }

            $alt_text = trim($data['choices'][0]['message']['content']);
            // Remove potential quotation marks often added by models.
            $alt_text = trim($alt_text, '"\'');

            // Enforce character limit (slightly more flexible).
            if (mb_strlen($alt_text) > 125) {
                $alt_text = mb_substr($alt_text, 0, 122) . '...';
            }

            // Return successful result.
            return array(
                'alt_text'        => $alt_text,
                'model'           => isset($data['model']) ? $data['model'] : $current_model_id, // Use model returned by API if available.
                'response'        => $body, // Raw JSON response body.
                'duration'        => $duration,
                'initial_payload' => $request_body, // Store the request payload sent.
            );
        } // End foreach model loop

        // If loop finishes without success, return the last error encountered.
        return $last_error ? $last_error : new WP_Error('no_successful_model', __('Failed to generate alt text after trying available models.', 'openrouted'));
    }

    /**
     * Get the size (width, height) of a remote image without downloading the full file if possible.
     * Uses getimagesize() which might download part of the file.
     *
     * @since    1.0.0
     * @param    string    $url    URL of the image to check.
     * @return   array|false       Array with 'width' and 'height' keys or false on failure.
     */
    private function get_remote_image_size($url) {
        // Input validation.
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        try {
            // getimagesize can fetch remote files.
            $size = @getimagesize($url);
            if ($size !== false && isset($size[0]) && isset($size[1])) {
                return array(
                    'width' => $size[0],
                    'height' => $size[1]
                );
            }
        } catch (Exception $e) {
            // Fall through, return false.
        }

        return false;
    }
}