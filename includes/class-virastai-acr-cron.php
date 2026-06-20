<?php
/**
 * Class Virastai_Acr_Cron
 *
 * Handles the background cron job for processing content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Virastai_Acr_Cron {

	/**
	 * Cron Event Name
	 */
	const EVENT_HOOK = 'virastai_acr_process_content_event';

	/**
	 * Rewriter instance.
	 *
	 * @var Virastai_Acr_Rewriter
	 */
	private $rewriter;

	/**
	 * Constructor.
	 *
	 * @param Virastai_Acr_Rewriter $rewriter The rewriter engine instance.
	 */
	public function __construct( Virastai_Acr_Rewriter $rewriter ) {
		$this->rewriter = $rewriter;

		// Register custom intervals (minutes).
		add_filter( 'cron_schedules', array( $this, 'virastai_acr_add_cron_intervals' ) );

		// Manage the schedule (Create/Update/Delete based on settings).
		add_action( 'init', array( $this, 'virastai_acr_manage_schedule' ) );

		// Hook into the cron event.
		add_action( self::EVENT_HOOK, array( $this, 'virastai_acr_run_batch_process' ) );
	}

	/**
	 * Add custom minute-based intervals to WP Cron.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function virastai_acr_add_cron_intervals( $schedules ) {
		$schedules['1min']  = array(
			'interval' => 60,
			'display'  => __( 'Every 1 Minute', 'virastai-auto-content-rewrite-with-ai' ),
		);
		$schedules['5min']  = array(
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes', 'virastai-auto-content-rewrite-with-ai' ),
		);
		$schedules['15min'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 Minutes', 'virastai-auto-content-rewrite-with-ai' ),
		);
		$schedules['30min'] = array(
			'interval' => 1800,
			'display'  => __( 'Every 30 Minutes', 'virastai-auto-content-rewrite-with-ai' ),
		);

		return $schedules;
	}

	/**
	 * Validates and reconciles the schedule based on saved settings.
	 */
	public function virastai_acr_manage_schedule() {
		$enabled    = (bool) get_option( 'virastai_acr_cron_enabled', 0 );
		$recurrence = get_option( 'virastai_acr_cron_interval', 'hourly' );

		$allowed_intervals = array( '1min', '5min', '15min', '30min', 'hourly', 'twicedaily', 'daily' );
		if ( ! in_array( $recurrence, $allowed_intervals, true ) ) {
			$recurrence = 'hourly';
		}

		$next_scheduled = wp_next_scheduled( self::EVENT_HOOK );

		if ( ! $enabled ) {
			if ( $next_scheduled ) {
				wp_clear_scheduled_hook( self::EVENT_HOOK );
			}
			return;
		}

		if ( $next_scheduled ) {
			$schedule_info = wp_get_schedule( self::EVENT_HOOK );
			if ( $schedule_info !== $recurrence ) {
				wp_clear_scheduled_hook( self::EVENT_HOOK );
				wp_schedule_event( time(), $recurrence, self::EVENT_HOOK );
			}
		} else {
			wp_schedule_event( time(), $recurrence, self::EVENT_HOOK );
		}
	}

	/**
	 * The Cron Job Callback.
	 * Processes a batch of products/posts.
	 */
	public function virastai_acr_run_batch_process() {
		$enabled = (bool) get_option( 'virastai_acr_cron_enabled', 0 );
		if ( ! $enabled ) {
			return;
		}

		$allowed_post_types    = get_option( 'virastai_acr_allowed_post_types', array( 'product' ) );
		$allowed_post_statuses = get_option( 'virastai_acr_allowed_post_statuses', array( 'publish' ) );
		$batch_size            = (int) get_option( 'virastai_acr_cron_batch_size', 5 );

		// Check what features are enabled.
		$enable_title   = get_option( 'virastai_acr_enable_title_rewrite', 0 );
		$enable_content = get_option( 'virastai_acr_enable_content_rewrite', 1 );
		$enable_excerpt = get_option( 'virastai_acr_enable_excerpt_rewrite', 0 );

		if ( ! $enable_title && ! $enable_content && ! $enable_excerpt ) {
			return; // Nothing to do.
		}

		if ( empty( $allowed_post_types ) || ! is_array( $allowed_post_types ) ) {
			return;
		}

		if ( empty( $allowed_post_statuses ) || ! is_array( $allowed_post_statuses ) ) {
			$allowed_post_statuses = array( 'publish' );
		}

		// Build Meta Query dynamically.
		$meta_query = array( 'relation' => 'OR' );

		if ( $enable_title ) {
			$meta_query[] = array(
				'key'     => '_virastai_acr_title_generated',
				'compare' => 'NOT EXISTS',
			);
		}

		if ( $enable_content ) {
			$meta_query[] = array(
				'key'     => '_virastai_acr_content_generated',
				'compare' => 'NOT EXISTS',
			);
		}

		if ( $enable_excerpt ) {
			$meta_query[] = array(
				'key'     => '_virastai_acr_excerpt_generated',
				'compare' => 'NOT EXISTS',
			);
		}

		// We order randomly to prevent getting stuck on empty content.
		$args = array(
			'post_type'      => $allowed_post_types,
			'post_status'    => $allowed_post_statuses,
			'posts_per_page' => $batch_size,
			'orderby'        => 'rand',
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $post ) {
			try {
				$this->rewriter->virastai_acr_generate_content( $post->ID, $post );
			} catch ( Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log error for debugging purposes.
				error_log( 'Virastai Cron Error ID ' . $post->ID . ': ' . $e->getMessage() );
			}
		}
	}
}
