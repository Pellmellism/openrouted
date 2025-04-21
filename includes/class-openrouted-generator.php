<?php
/**
 * Handles alt‑tag generation.
 *
 * @since      1.0.0
 * @package    Openrouted
 * @subpackage Openrouted/includes
 */

class Openrouted_Generator {

	/**
	 * API handler instance.
	 *
	 * @since 1.0.0
	 * @var   Openrouted_API
	 */
	private $api;

	/**
	 * Plugin operation mode (manual / auto).
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private $mode;

	/**
	 * User‑supplied custom instructions.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private $custom_instructions;

	/**
	 * Images processed per run.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private $batch_size;

	/**
	 * Max runtime in minutes.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private $max_runtime;

	/**
	 * Delay between API requests (seconds).
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private $request_delay;

	/**
	 * Model‑selection mode.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private $model_selection;

	/**
	 * Explicit model ID to use.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private $custom_model_id;

	/**
	 * Cache‑group name.
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'openrouted';

	/**
	 * Constructor: sets defaults from options.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->api                 = new Openrouted_API();
		$this->mode                = get_option( 'openrouted_mode', 'manual' );
		$this->custom_instructions = get_option( 'openrouted_custom_instructions', '' );
		$this->batch_size          = get_option( 'openrouted_batch_size', '20' );
		$this->max_runtime         = get_option( 'openrouted_max_runtime', '10' );
		$this->request_delay       = get_option( 'openrouted_request_delay', '2' );
		$this->model_selection     = get_option( 'openrouted_model_selection', 'auto' );
		$this->custom_model_id     = get_option( 'openrouted_custom_model_id', '' );
	}

	/**
	 * Debug logger (only active when WP_DEBUG true).
	 *
	 * @param string $message Text to log.
	 */
	private function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated debug logging.
			error_log( $message );
		}
	}

	/* -------------------------------------------------------------------------
	 *  PUBLIC — CRON + PROCESSING
	 * ---------------------------------------------------------------------- */

	/**
	 * Main cron task – scans for images without alt text.
	 *
	 * @since 1.0.0
	 */
	public function daily_check() {
		$this->log_error( 'OpenRouted: Cron job started at ' . gmdate( 'Y-m-d H:i:s' ) );
		update_option( 'openrouted_cron_last_attempt', time() );

		// Abort if another instance is already running.
		$lock = get_transient( 'openrouted_process_lock' );
		if ( $lock ) {
			$this->log_error( 'OpenRouted: Process already running since ' . gmdate( 'Y-m-d H:i:s', $lock ) . ', aborting this run' );

			// Clear stale lock (>30 min).
			if ( time() - $lock > 30 * MINUTE_IN_SECONDS ) {
				$this->log_error( 'OpenRouted: Found stale process lock, clearing it' );
				delete_transient( 'openrouted_process_lock' );
			}

			update_option(
				'openrouted_cron_status',
				array(
					'timestamp' => time(),
					'status'    => 'skipped',
					'message'   => 'Process already running since ' . human_time_diff( $lock, time() ) . ' ago',
				)
			);

			return;
		}

		// Set lock for up to 30 min.
		set_transient( 'openrouted_process_lock', time(), 30 * MINUTE_IN_SECONDS );

		try {
			$api_key = get_option( 'openrouted_api_key', '' );
			if ( empty( $api_key ) ) {
				$this->log_error( 'OpenRouted: No API key set, aborting cron job' );
				update_option(
					'openrouted_cron_status',
					array(
						'timestamp' => time(),
						'status'    => 'error',
						'message'   => 'No API key configured',
					)
				);
				return;
			}

			update_option(
				'openrouted_cron_status',
				array(
					'timestamp' => time(),
					'status'    => 'running',
					'message'   => 'Cron job in progress...',
				)
			);

			$this->log_error( 'OpenRouted: Fetching vision model from OpenRouter API' );
			$this->log_error( 'OpenRouted: Custom model ID: ' . $this->custom_model_id );

			$models = array();

			if ( 'free' === $this->custom_model_id ) {
				$this->log_error( 'OpenRouted: Using free vision models' );
				$models = $this->api->get_free_vision_models();
			} elseif ( ! empty( $this->custom_model_id ) ) {
				$this->log_error( 'OpenRouted: Using specific model ID: ' . $this->custom_model_id );
				$all_models = $this->api->get_models();

				if ( ! is_wp_error( $all_models ) ) {
					$custom_model_exists = false;
					foreach ( $all_models as $model ) {
						if ( isset( $model['id'] ) && $model['id'] === $this->custom_model_id ) {
							$custom_model_exists = true;
							$this->log_error( 'OpenRouted: Specified model found in available models' );
							$models = array( $model );
							break;
						}
					}
					if ( ! $custom_model_exists ) {
						$this->log_error( 'OpenRouted: Specified model ID not found, falling back to free models' );
						$models = $this->api->get_free_vision_models();
					}
				}
			} else {
				$this->log_error( 'OpenRouted: No model specified, defaulting to free models' );
				$models = $this->api->get_free_vision_models();
			}

			if ( is_wp_error( $models ) ) {
				$error_message = $models->get_error_message();
				$this->log_error( 'OpenRouted: API error – ' . $error_message );
				update_option(
					'openrouted_cron_status',
					array(
						'timestamp' => time(),
						'status'    => 'error',
						'message'   => 'API error: ' . $error_message,
					)
				);
				return;
			}

			if ( empty( $models ) ) {
				$this->log_error( 'OpenRouted: No vision models available' );
				update_option(
					'openrouted_cron_status',
					array(
						'timestamp' => time(),
						'status'    => 'error',
						'message'   => 'No vision models available from OpenRouter',
					)
				);
				return;
			}

			$this->log_error( 'OpenRouted: Selected model: ' . $models[0]['id'] );

			$limit = $this->batch_size;
			$limit = ( 'all' === $limit ) ? -1 : intval( $limit );

			$this->log_error( 'OpenRouted: Starting scan with batch size ' . ( -1 === $limit ? 'all' : $limit ) );

			$results = $this->scan_missing_alt_tags( $limit );

			update_option(
				'openrouted_last_check',
				array(
					'timestamp' => current_time( 'timestamp' ),
					'results'   => $results,
				)
			);

			$message = sprintf(
				/* translators: %1$d: alt tags generated, %2$d: images processed, %3$d: total images found */
				__( 'Generated %1$d alt tags from %2$d processed images (found %3$d total)', 'openrouted' ),
				$results['generated'],
				$results['processed'],
				$results['found']
			);

			$this->log_error(
				"OpenRouted: Successfully completed scheduled check – {$message} in {$results['runtime']} minutes"
			);

			update_option(
				'openrouted_cron_status',
				array(
					'timestamp' => time(),
					'status'    => 'completed',
					'message'   => $message,
					'results'   => $results,
				)
			);
		} catch ( Exception $e ) {
			$this->log_error( 'OpenRouted: Exception in cron job – ' . $e->getMessage() );
			update_option(
				'openrouted_cron_status',
				array(
					'timestamp' => time(),
					'status'    => 'error',
					'message'   => 'Exception: ' . $e->getMessage(),
				)
			);
		} finally {
			delete_transient( 'openrouted_process_lock' );
			$this->log_error( 'OpenRouted: Cron job completed at ' . gmdate( 'Y-m-d H:i:s' ) );
		}
	}

	/**
	 * Performs the actual scan & generation loop.
	 *
	 * @param int $limit Max images to process (‑1 = all).
	 * @return array     Stats about the run.
	 */
	public function scan_missing_alt_tags( $limit = 10 ) {
		global $wpdb;
		$found             = 0;
		$processed         = 0;
		$generated         = 0;
		$skipped           = 0;
		$already_processed = 0;

		$start_time          = time();
		$max_runtime_seconds = max( 1, intval( $this->max_runtime ) ) * 60;

		$this->log_error( "OpenRouted: Starting scan with max runtime of {$this->max_runtime} minutes" );

		$posts_per_page = ( -1 === $limit ) ? 100 : $limit;

		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => $posts_per_page,
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_wp_attachment_image_alt',
					'value'   => '',
					'compare' => '=',
				),
			),
			'orderby'        => 'rand',
		);

		$query            = new WP_Query( $args );
		$found            = $query->found_posts;
		$total_to_process = ( -1 === $limit ) ? $found : min( $found, $limit );

		$this->log_error( "OpenRouted: Found $found images without alt tags, will process up to $total_to_process" );

		if ( $found > 0 ) {
			$offset   = 0;
			$continue = true;

			while ( $continue && ( $processed < $total_to_process ) ) {
				if ( $offset > 0 ) {
					$args['offset'] = $offset;
					$query          = new WP_Query( $args );
					if ( empty( $query->posts ) ) {
						break;
					}
				}

				foreach ( $query->posts as $image ) {
					if ( ( time() - $start_time ) > $max_runtime_seconds ) {
						$this->log_error( "OpenRouted: Reached maximum runtime of {$this->max_runtime} minutes" );
						$continue = false;
						break;
					}
					if ( $processed >= $total_to_process ) {
						$continue = false;
						break;
					}
					$processed++;

					$existing_pending = $this->get_alt_tag_by_image_id( $image->ID, 'pending' );
					$existing_applied = $this->get_alt_tag_by_image_id( $image->ID, 'applied' );

					if ( $existing_pending ) {
						$this->log_error( "OpenRouted: Skipping image {$image->ID} – already has pending alt tag" );
						$skipped++;
						continue;
					}

					if ( $existing_applied ) {
						$this->log_error( "OpenRouted: Skipping image {$image->ID} – already has applied alt tag" );
						$already_processed++;
						continue;
					}

					$current_alt = get_post_meta( $image->ID, '_wp_attachment_image_alt', true );
					if ( ! empty( $current_alt ) ) {
						$this->log_error( "OpenRouted: Skipping image {$image->ID} – already has alt text: {$current_alt}" );
						$already_processed++;
						continue;
					}

					$result = $this->generate_alt_tag_for_image( $image->ID );

					$this->log_error(
						'Alt tag generation result for image ID ' . $image->ID . ': ' .
						( is_wp_error( $result ) ? $result->get_error_message() : 'Success' )
					);

					if ( ! is_wp_error( $result ) ) {
						$generated++;

						if ( 'auto' === $this->mode ) {
							$this->apply_alt_tag( $result['id'] );
						}

						$delay = intval( $this->request_delay );
						if ( $delay > 0 ) {
							$this->log_error( "OpenRouted: Waiting for {$delay} seconds before next request" );
							sleep( $delay );
						}
					} else {
						$this->log_error( 'Alt tag generation error: ' . $result->get_error_message() );
					}
				}

				$offset += $posts_per_page;
			}
		}

		$total_runtime   = time() - $start_time;
		$runtime_minutes = round( $total_runtime / 60, 1 );
		$remaining       = $found - $generated - $already_processed;

		$this->log_error( "OpenRouted: Completed scanning in $runtime_minutes minutes." );
		$this->log_error( "OpenRouted: Summary – Found: $found, Processed: $processed, Generated: $generated" );
		$this->log_error( "OpenRouted: Summary – Skipped: $skipped, Already processed: $already_processed, Remaining: $remaining" );

		return array(
			'found'             => $found,
			'processed'         => $processed,
			'generated'         => $generated,
			'skipped'           => $skipped,
			'already_processed' => $already_processed,
			'remaining'         => $remaining,
			'runtime'           => $runtime_minutes,
		);
	}

	/* -------------------------------------------------------------------------
	 *  PUBLIC — SINGLE‑IMAGE GENERATION
	 * ---------------------------------------------------------------------- */

	/**
	 * Generates an alt tag for a single image via API.
	 *
	 * @param int $image_id Attachment ID.
	 * @return array|WP_Error
	 */
	public function generate_alt_tag_for_image( $image_id ) {
		$image = get_post( $image_id );

		if ( ! $image || 'attachment' !== $image->post_type || 0 !== strpos( $image->post_mime_type, 'image' ) ) {
			return new WP_Error( 'invalid_image', __( 'Not a valid image attachment.', 'openrouted' ) );
		}

		$image_url         = wp_get_attachment_url( $image_id );
		$image_title       = $image->post_title;
		$image_caption     = $image->post_excerpt;
		$image_description = $image->post_content;

		$context = array(
			'title'       => $image_title,
			'caption'     => $image_caption,
			'description' => $image_description,
			'site'        => $this->get_site_context(),
			'examples'    => $this->get_example_alt_tags(),
		);

		$this->log_error( 'OpenRouted: Using model for image ' . $image_id . ': ' . $this->custom_model_id );
		$response = $this->api->generate_alt_text( $image_url, $context, $this->custom_instructions, $this->custom_model_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->save_alt_tag(
			$image_id,
			$response['alt_text'],
			$response['response'],
			$response['duration'],
			isset( $response['initial_payload'] ) ? $response['initial_payload'] : ''
		);
	}

	/* -------------------------------------------------------------------------
	 *  DATABASE HELPERS  (INSERT / UPDATE)
	 * ---------------------------------------------------------------------- */

	/**
	 * Persists a generated alt tag.
	 *
	 * @param int    $image_id Attachment ID.
	 * @param string $alt_text Generated alt text.
	 * @param string $response Raw API response.
	 * @param float  $duration Seconds taken.
	 * @param string $initial_payload Original payload (optional).
	 * @return array|WP_Error
	 */
	public function save_alt_tag( $image_id, $alt_text, $response = '', $duration = 0, $initial_payload = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'openrouted';

		// Try to get from cache first (unlikely in this case but good practice)
		$cache_key = 'alt_tag_insert_' . md5( serialize( array( $image_id, $alt_text ) ) );
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		
		if ( false !== $cached ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Using caching
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'image_id'        => $image_id,
				'alt_text'        => $alt_text,
				'status'          => 'pending',
				'response'        => $response,
				'duration'        => $duration,
				'initial_payload' => $initial_payload,
			),
			array( '%d', '%s', '%s', '%s', '%f', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'db_insert_error', __( 'Could not insert alt tag record into the database.', 'openrouted' ) );
		}

		// Prepare result data
		$result = array(
			'id'              => $wpdb->insert_id,
			'image_id'        => $image_id,
			'alt_text'        => $alt_text,
			'status'          => 'pending',
			'timestamp'       => current_time( 'mysql' ),
			'initial_payload' => $initial_payload,
		);
		
		// Cache the result
		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );

		// Clear caches related to this image / global counts.
		wp_cache_delete( "alt_tag_by_image_{$image_id}", self::CACHE_GROUP );
		wp_cache_delete( 'alt_tag_counts', self::CACHE_GROUP );

		return $result;
	}

	/**
	 * Applies a pending alt tag to the attachment meta.
	 *
	 * @param int $id Alt‑tag DB row ID.
	 * @return bool|WP_Error
	 */
	public function apply_alt_tag( $id ) {
		global $wpdb;

		// Get the alt tag from the existing get_alt_tag method which uses caching
		$alt_tag = $this->get_alt_tag( $id );

		if ( is_wp_error( $alt_tag ) || ! $alt_tag ) {
			return new WP_Error( 'not_found', __( 'Alt tag record not found.', 'openrouted' ) );
		}

		$updated = update_post_meta(
			$alt_tag->image_id,
			'_wp_attachment_image_alt',
			sanitize_text_field( $alt_tag->alt_text )
		);

		if ( $updated || get_post_meta( $alt_tag->image_id, '_wp_attachment_image_alt', true ) === $alt_tag->alt_text ) {
			$table_name = $wpdb->prefix . 'openrouted';
			
			// Create a cache key for the update operation
			$update_cache_key = 'alt_tag_update_' . $id;
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Using caching
			$result = $wpdb->update(
				$table_name,
				array(
					'status'            => 'applied',
					'applied_timestamp' => current_time( 'mysql' ),
				),
				array( 'id' => $id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			// Invalidate caches.
			wp_cache_delete( "alt_tag_{$id}", self::CACHE_GROUP );
			wp_cache_delete( "alt_tag_by_image_{$alt_tag->image_id}", self::CACHE_GROUP );
			wp_cache_delete( 'alt_tag_counts', self::CACHE_GROUP );
			wp_cache_delete( $update_cache_key, self::CACHE_GROUP );

			return true;
		}

		return new WP_Error( 'meta_update_failed', __( 'Could not update image alt text.', 'openrouted' ) );
	}

	/**
	 * Soft‑deletes (rejects) an alt‑tag row.
	 *
	 * @param int $id Row ID.
	 * @return bool|WP_Error
	 */
	public function delete_alt_tag( $id ) {
		global $wpdb;

		// First get the alt tag to cache the image_id for invalidation
		$alt_tag = $this->get_alt_tag( $id );
		
		// If we can't find the alt tag, return an error
		if ( is_wp_error( $alt_tag ) || ! $alt_tag ) {
			return new WP_Error( 'not_found', __( 'Alt tag record not found.', 'openrouted' ) );
		}
		
		$table_name = $wpdb->prefix . 'openrouted';
		
		// Create a cache key for the delete operation
		$delete_cache_key = 'alt_tag_delete_' . $id;
		$cached_result = wp_cache_get( $delete_cache_key, self::CACHE_GROUP );
		
		if ( false !== $cached_result ) {
			return $cached_result;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Using caching
		$result = $wpdb->update(
			$table_name,
			array( 'status' => 'rejected' ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			$error = new WP_Error( 'db_update_error', __( 'Could not update alt tag status to rejected.', 'openrouted' ) );
			wp_cache_set( $delete_cache_key, $error, self::CACHE_GROUP, MINUTE_IN_SECONDS );
			return $error;
		}

		// Invalidate caches.
		wp_cache_delete( "alt_tag_{$id}", self::CACHE_GROUP );
		wp_cache_delete( "alt_tag_by_image_{$alt_tag->image_id}", self::CACHE_GROUP );
		wp_cache_delete( 'alt_tag_counts', self::CACHE_GROUP );
		
		// Cache the success result briefly
		wp_cache_set( $delete_cache_key, true, self::CACHE_GROUP, MINUTE_IN_SECONDS );

		return true;
	}

	/* -------------------------------------------------------------------------
	 *  DATABASE HELPERS  (READ)
	 * ---------------------------------------------------------------------- */

	/**
	 * Retrieves a single alt‑tag record.
	 *
	 * @param int $id Row ID.
	 * @return object|WP_Error
	 */
	public function get_alt_tag( $id ) {
		$cache_key = "alt_tag_{$id}";
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'openrouted';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$tag = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}openrouted WHERE id = %d",
				$id
			)
		);

		if ( ! $tag ) {
			return new WP_Error( 'not_found', __( 'Alt tag record not found.', 'openrouted' ) );
		}

		wp_cache_set( $cache_key, $tag, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );
		return $tag;
	}

	/**
	 * Retrieves the latest alt‑tag record for a given image.
	 *
	 * @param int         $image_id Attachment ID.
	 * @param string|null $status   Filter by status.
	 */
	public function get_alt_tag_by_image_id( $image_id, $status = null ) {
		$cache_key = "alt_tag_by_image_{$image_id}_" . ( $status ? $status : 'all' );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'openrouted';

		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}openrouted WHERE image_id = %d AND status = %s ORDER BY id DESC LIMIT 1",
					$image_id,
					$status
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}openrouted WHERE image_id = %d ORDER BY id DESC LIMIT 1",
					$image_id
				)
			);
		}

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );
		return $result;
	}

	/**
	 * Returns multiple alt‑tag rows.
	 *
	 * @param string|null $status Status filter.
	 * @param int         $limit  Rows per page.
	 * @param int         $offset Offset.
	 */
	public function get_alt_tags( $status = null, $limit = 50, $offset = 0 ) {
		$cache_key = 'alt_tags_' . md5( serialize( array( $status, $limit, $offset ) ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'openrouted';

		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}openrouted WHERE status = %s ORDER BY timestamp DESC LIMIT %d OFFSET %d",
					$status,
					$limit,
					$offset
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}openrouted ORDER BY timestamp DESC LIMIT %d OFFSET %d",
					$limit,
					$offset
				)
			);
		}

		wp_cache_set( $cache_key, $results, self::CACHE_GROUP, 2 * MINUTE_IN_SECONDS );
		return $results;
	}

	/**
	 * Counts alt‑tags grouped by status.
	 *
	 * @return array
	 */
	public function count_alt_tags() {
		$cached = wp_cache_get( 'alt_tag_counts', self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'openrouted';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$counts = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$wpdb->prefix}openrouted GROUP BY status",
			OBJECT_K
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}openrouted");

		$result = array(
			'total'    => $total,
			'pending'  => 0,
			'applied'  => 0,
			'rejected' => 0,
		);

		foreach ( $counts as $status => $data ) {
			$result[ $status ] = (int) $data->count;
		}

		wp_cache_set( 'alt_tag_counts', $result, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );
		return $result;
	}

	/**
	 * Helper: convenience wrapper for pagination.
	 */
	public function get_more_alt_tags( $status, $offset = 0, $limit = 10 ) {
		return $this->get_alt_tags( $status, $limit, $offset );
	}

	/**
	 * Helper: count rows by status only.
	 */
	public function count_alt_tags_by_status( $status ) {
		$counts = $this->count_alt_tags();
		return isset( $counts[ $status ] ) ? $counts[ $status ] : 0;
	}

	/* -------------------------------------------------------------------------
	 *  ACTIVITY LOG
	 * ---------------------------------------------------------------------- */

	/**
	 * Builds a readable activity log array.
	 */
	public function get_activity_log( $limit = 50 ) {
		$cache_key = "activity_log_{$limit}";
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'openrouted';

		$activities = array();

		// ---------------- Generated ----------------.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$generated = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, image_id, alt_text, timestamp, response
				 FROM {$wpdb->prefix}openrouted
				 ORDER BY timestamp DESC
				 LIMIT %d",
				$limit
			)
		);

		foreach ( $generated as $entry ) {
			$model = '';
			if ( ! empty( $entry->response ) ) {
				$data = json_decode( $entry->response, true );
				if ( isset( $data['model'] ) ) {
					$model = $data['model'];
				}
			}
			$image_title = '';
			if ( $entry->image_id ) {
				$image = get_post( $entry->image_id );
				if ( $image ) {
					$image_title = $image->post_title;
				}
			}

			$details = sprintf(
				/* translators: %1$s: image title, %2$s: model name, %3$s: truncated alt text */
				__( 'Alt tag for image "%1$s" using model %2$s: "%3$s"', 'openrouted' ),
				$image_title,
				$model,
				wp_trim_words( $entry->alt_text, 10, '...' )
			);
			$activities[] = array(
				'time'    => $entry->timestamp,
				'action'  => 'Generated',
				'details' => $details,
			);
		}

		// ---------------- Applied ----------------.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$applied = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, image_id, alt_text, applied_timestamp
				 FROM {$wpdb->prefix}openrouted
				 WHERE status = 'applied'
				 AND applied_timestamp IS NOT NULL
				 ORDER BY applied_timestamp DESC
				 LIMIT %d",
				$limit
			)
		);

		foreach ( $applied as $entry ) {
			$image_title = '';
			if ( $entry->image_id ) {
				$image = get_post( $entry->image_id );
				if ( $image ) {
					$image_title = $image->post_title;
				}
			}

			$details = sprintf(
				/* translators: %1$s: image title, %2$s: truncated alt text */
				__( 'Alt tag for image "%1$s": "%2$s"', 'openrouted' ),
				$image_title,
				wp_trim_words( $entry->alt_text, 10, '...' )
			);
			$activities[] = array(
				'time'    => $entry->applied_timestamp,
				'action'  => 'Applied',
				'details' => $details,
			);
		}

		// ---------------- Rejected ----------------.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rejected = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, image_id, alt_text, timestamp
				 FROM {$wpdb->prefix}openrouted
				 WHERE status = 'rejected'
				 ORDER BY timestamp DESC
				 LIMIT %d",
				$limit
			)
		);

		foreach ( $rejected as $entry ) {
			$image_title = '';
			if ( $entry->image_id ) {
				$image = get_post( $entry->image_id );
				if ( $image ) {
					$image_title = $image->post_title;
				}
			}

			$details = sprintf(
				/* translators: %1$s: image title, %2$s: truncated alt text */
				__( 'Alt tag for image "%1$s": "%2$s"', 'openrouted' ),
				$image_title,
				wp_trim_words( $entry->alt_text, 10, '...' )
			);
			$activities[] = array(
				'time'    => $entry->timestamp,
				'action'  => 'Rejected',
				'details' => $details,
			);
		}

		usort(
			$activities,
			function ( $a, $b ) {
				return strtotime( $b['time'] ) - strtotime( $a['time'] );
			}
		);

		$activities = array_slice( $activities, 0, $limit );
		wp_cache_set( $cache_key, $activities, self::CACHE_GROUP, 2 * MINUTE_IN_SECONDS );

		return $activities;
	}

	/* -------------------------------------------------------------------------
	 *  PROMPT HELPERS
	 * ---------------------------------------------------------------------- */

	/**
	 * Pulls 5 example alt‑tags from existing images for prompting.
	 */
	private function get_example_alt_tags() {
		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => 5,
			'meta_query'     => array(
				array(
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_wp_attachment_image_alt',
					'value'   => '',
					'compare' => '!=',
				),
			),
		);

		$query    = new WP_Query( $args );
		$examples = '';

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $image ) {
				$alt_text = get_post_meta( $image->ID, '_wp_attachment_image_alt', true );
				if ( ! empty( $alt_text ) ) {
					$examples .= '- ' . $image->post_title . ': "' . $alt_text . '"' . "\n";
				}
			}
		}

		if ( empty( $examples ) ) {
			$examples = __( 'No existing alt tags found on this site. Create alt text that is descriptive, concise, and SEO‑friendly.', 'openrouted' );
		}

		return $examples;
	}

	/**
	 * Builds minimal site context for prompts.
	 *
	 * @return string
	 */
	private function get_site_context() {
		$context  = 'Site Name: ' . get_bloginfo( 'name' ) . "\n";
		$context .= 'Site Description: ' . get_bloginfo( 'description' ) . "\n\n";

		$recent_posts = get_posts(
			array(
				'posts_per_page' => 3,
				'post_status'    => 'publish',
			)
		);

		if ( ! empty( $recent_posts ) ) {
			$context .= "Recent Content Samples:\n";
			foreach ( $recent_posts as $post ) {
				$context .= '- Title: ' . $post->post_title . "\n";
				$context .= '  Excerpt: ' . ( empty( $post->post_excerpt ) ? wp_trim_words( $post->post_content, 30 ) : $post->post_excerpt ) . "\n\n";
			}
		}

		$categories = get_categories(
			array(
				'hide_empty' => true,
				'number'     => 5,
			)
		);

		if ( ! empty( $categories ) ) {
			$context .= "Site Categories:\n";
			$context .= implode( ', ', wp_list_pluck( $categories, 'name' ) ) . "\n\n";
		}

		return $context;
	}
}