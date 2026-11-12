<?php
/**
 * Class Virastai_Acr_Custom_Automation
 *
 * Handles date-range based automated content rewriting via WP-Cron with Start, Pause, and Stop controls.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Virastai_Acr_Custom_Automation {

	const EVENT_HOOK = 'virastai_acr_custom_automation_batch_event';
	const OPTION_KEY = 'virastai_acr_custom_automation_state';
	const LOCK_KEY   = 'virastai_acr_ca_lock';

	/**
	 * Rewriter instance.
	 *
	 * @var Virastai_Acr_Rewriter
	 */
	private $rewriter;

	/**
	 * Constructor.
	 *
	 * @param Virastai_Acr_Rewriter $rewriter Rewriter engine instance.
	 */
	public function __construct( Virastai_Acr_Rewriter $rewriter ) {
		$this->rewriter = $rewriter;

		add_action( self::EVENT_HOOK, array( $this, 'run_batch_process' ) );

		add_action( 'wp_ajax_virastai_acr_ca_start', array( $this, 'handle_ajax_start' ) );
		add_action( 'wp_ajax_virastai_acr_ca_pause', array( $this, 'handle_ajax_pause' ) );
		add_action( 'wp_ajax_virastai_acr_ca_stop', array( $this, 'handle_ajax_stop' ) );
		add_action( 'wp_ajax_virastai_acr_ca_status', array( $this, 'handle_ajax_status' ) );
		add_action( 'wp_ajax_virastai_acr_ca_preview_counts', array( $this, 'handle_ajax_preview_counts' ) );
		add_action( 'wp_ajax_virastai_acr_ca_retry_failed', array( $this, 'handle_ajax_retry_failed' ) );
	}

	/**
	 * Retrieve current automation state with defaults.
	 *
	 * @return array
	 */
	public function get_state() {
		$defaults = array(
			'status'                    => 'idle',
			'start_date'                => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date'                  => gmdate( 'Y-m-d' ),
			'post_types'                => array( 'post' ),
			'include_already_rewritten' => false,
			'posts_per_minute'          => 5,
			'total_posts'               => 0,
			'processed_posts'           => 0,
			'failed_posts'              => 0,
			'current_post_id'           => 0,
			'current_post_title'        => '',
			'started_at'                => 0,
			'updated_at'                => 0,
			'next_run_at'               => 0,
			'processed_ids'             => array(),
			'failed_ids'                => array(),
		);

		$saved = get_option( self::OPTION_KEY, array() );
		return is_array( $saved ) ? array_merge( $defaults, $saved ) : $defaults;
	}

	/**
	 * Save automation state to database.
	 *
	 * @param array $state State array.
	 * @return bool
	 */
	public function update_state( array $state ) {
		$state['updated_at'] = time();
		return update_option( self::OPTION_KEY, $state );
	}

	/**
	 * Build query arguments for targeting posts.
	 *
	 * @param array $params Filter criteria.
	 * @param int   $limit  Number of posts to fetch (-1 for all).
	 * @return array
	 */
	public function build_query_args( array $params, $limit = -1 ) {
		$start_time = ! empty( $params['start_date'] ) ? strtotime( $params['start_date'] ) : false;
		$start_date = ( false !== $start_time ) ? gmdate( 'Y-m-d', $start_time ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );

		$end_time = ! empty( $params['end_date'] ) ? strtotime( $params['end_date'] ) : false;
		$end_date = ( false !== $end_time ) ? gmdate( 'Y-m-d', $end_time ) : gmdate( 'Y-m-d' );
		$post_types = ! empty( $params['post_types'] ) && is_array( $params['post_types'] ) ? array_map( 'sanitize_key', $params['post_types'] ) : array( 'post' );
		$include_prev = ! empty( $params['include_already_rewritten'] );

		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => ( -1 === $limit ) ? 'ids' : '',
			'date_query'     => array(
				array(
					'after'     => $start_date . ' 00:00:00',
					'before'    => $end_date . ' 23:59:59',
					'inclusive' => true,
					'column'    => 'post_date',
				),
			),
		);

		if ( ! empty( $params['processed_ids'] ) && is_array( $params['processed_ids'] ) ) {
			$args['post__not_in'] = array_map( 'intval', $params['processed_ids'] );
		}

		if ( ! $include_prev ) {
			$enable_title   = (bool) get_option( 'virastai_acr_enable_title_rewrite', 0 );
			$enable_content = (bool) get_option( 'virastai_acr_enable_content_rewrite', 1 );
			$enable_excerpt = (bool) get_option( 'virastai_acr_enable_excerpt_rewrite', 0 );

			$meta_subqueries = array( 'relation' => 'OR' );
			if ( $enable_content ) {
				$meta_subqueries[] = array(
					'key'     => '_virastai_acr_content_generated',
					'compare' => 'NOT EXISTS',
				);
			}
			if ( $enable_title ) {
				$meta_subqueries[] = array(
					'key'     => '_virastai_acr_title_generated',
					'compare' => 'NOT EXISTS',
				);
			}
			if ( $enable_excerpt ) {
				$meta_subqueries[] = array(
					'key'     => '_virastai_acr_excerpt_generated',
					'compare' => 'NOT EXISTS',
				);
			}

			if ( count( $meta_subqueries ) > 1 ) {
				$args['meta_query'] = $meta_subqueries;
			}
		}

		return $args;
	}

	/**
	 * AJAX handler to start or resume custom automation.
	 */
	public function handle_ajax_start() {
		check_ajax_referer( 'virastai_acr_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'virastai-auto-content-rewrite-with-ai' ) ) );
		}

		$state = $this->get_state();

		// If resuming from paused state
		if ( 'paused' === $state['status'] ) {
			$state['status'] = 'running';
			$this->update_state( $state );
			$this->schedule_immediate_run();
			wp_send_json_success( array( 'state' => $state ) );
		}

		$enable_title   = (bool) get_option( 'virastai_acr_enable_title_rewrite', 0 );
		$enable_content = (bool) get_option( 'virastai_acr_enable_content_rewrite', 1 );
		$enable_excerpt = (bool) get_option( 'virastai_acr_enable_excerpt_rewrite', 0 );

		if ( ! $enable_title && ! $enable_content && ! $enable_excerpt ) {
			wp_send_json_error( array( 'message' => __( 'All rewrite options (Title, Content, Excerpt) are disabled in Settings. Please enable at least one in the Content & Title Options tab.', 'virastai-auto-content-rewrite-with-ai' ) ) );
		}

		$raw_start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$raw_end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
		$start_time     = ! empty( $raw_start_date ) ? strtotime( $raw_start_date ) : false;
		$end_time       = ! empty( $raw_end_date ) ? strtotime( $raw_end_date ) : false;
		$post_types     = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) ) : array();
		if ( empty( $post_types ) ) {
			$post_types = array( 'post' );
		}
		$include_prev     = ! empty( $_POST['include_already_rewritten'] );
		$posts_per_minute = isset( $_POST['posts_per_minute'] ) ? max( 1, min( 60, intval( $_POST['posts_per_minute'] ) ) ) : 5;

		if ( false === $start_time || false === $end_time ) {
			wp_send_json_error( array( 'message' => __( 'Please select valid start and end dates.', 'virastai-auto-content-rewrite-with-ai' ) ) );
		}

		$start_date = gmdate( 'Y-m-d', $start_time );
		$end_date   = gmdate( 'Y-m-d', $end_time );

		if ( strtotime( $start_date ) > strtotime( $end_date ) ) {
			wp_send_json_error( array( 'message' => __( 'Start date cannot be after end date.', 'virastai-auto-content-rewrite-with-ai' ) ) );
		}

		$query_args = $this->build_query_args( array(
			'start_date'                => $start_date,
			'end_date'                  => $end_date,
			'post_types'                => $post_types,
			'include_already_rewritten' => $include_prev,
			'processed_ids'             => array(),
		), -1 );

		$eligible_query = new WP_Query( $query_args );
		$total_count    = $eligible_query->found_posts;

		if ( 0 === $total_count ) {
			wp_send_json_error( array( 'message' => __( 'No eligible posts found matching your criteria.', 'virastai-auto-content-rewrite-with-ai' ) ) );
		}

		$state = array(
			'status'                    => 'running',
			'start_date'                => $start_date,
			'end_date'                  => $end_date,
			'post_types'                => $post_types,
			'include_already_rewritten' => $include_prev,
			'posts_per_minute'          => $posts_per_minute,
			'total_posts'               => $total_count,
			'processed_posts'           => 0,
			'failed_posts'              => 0,
			'current_post_id'           => 0,
			'current_post_title'        => '',
			'started_at'                => time(),
			'updated_at'                => time(),
			'next_run_at'               => 0,
			'processed_ids'             => array(),
			'failed_ids'                => array(),
		);

		$this->update_state( $state );
		$this->schedule_immediate_run();

		if ( class_exists( 'Virastai_Acr_Logger' ) ) {
			Virastai_Acr_Logger::log( 'info', sprintf( 'Custom Automation started. Target: %d posts (%s to %s)', $total_count, $start_date, $end_date ) );
		}

		wp_send_json_success( array( 'state' => $state ) );
	}

	/**
	 * AJAX handler to pause custom automation.
	 */
	public function handle_ajax_pause() {
		check_ajax_referer( 'virastai_acr_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'virastai-auto-content-rewrite-with-ai' ) ) );
		}

		$state = $this->get_state();
		$state['status']             = 'paused';
		$state['current_post_id']    = 0;
		$state['current_post_title'] = '';
		$state['next_run_at']        = 0;
		$this->update_state( $state );
		wp_clear_scheduled_hook( self::EVENT_HOOK );
		delete_transient( self::LOCK_KEY );

		if ( class_exists( 'Virastai_Acr_Logger' ) ) {
			Virastai_Acr_Logger::log( 'info', 'Custom Automation paused by user.' );
		}

		wp_send_json_success( array( 'state' => $state ) );
	}

	/**
	 * AJAX handler to stop and reset custom automation.
	 */
	public function handle_ajax_stop() {
		check_ajax_referer( 'virastai_acr_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'virastai-auto-content-rewrite-with-ai' ) ) );
		}

		$state = $this->get_state();
		$state['status']             = 'stopped';
		$state['current_post_id']    = 0;
		$state['current_post_title'] = '';
		$state['next_run_at']        = 0;
		$this->update_state( $state );
		wp_clear_scheduled_hook( self::EVENT_HOOK );
		delete_transient( self::LOCK_KEY );

		if ( class_exists( 'Virastai_Acr_Logger' ) ) {
			Virastai_Acr_Logger::log( 'info', 'Custom Automation stopped by user.' );
		}

		wp_send_json_success( array( 'state' => $state ) );
	}

	/**
	 * AJAX handler to retry failed or skipped posts.
	 */
	public function handle_ajax_retry_failed() {
		check_ajax_referer( 'virastai_acr_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'virastai-auto-content-rewrite-with-ai' ) ) );
		}

		$state = $this->get_state();
		$failed_ids = ! empty( $state['failed_ids'] ) && is_array( $state['failed_ids'] ) ? array_map( 'intval', $state['failed_ids'] ) : array();

		// Fallback query if counter exists but specific ids array is empty
		if ( empty( $failed_ids ) && ! empty( $state['failed_posts'] ) ) {
			$failed_query = new WP_Query( array(
				'post_type'      => ! empty( $state['post_types'] ) ? $state['post_types'] : array( 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_virastai_acr_content_generated',
						'value'   => array( 'failed', 'skipped' ),
						'compare' => 'IN',
					),
					array(
						'key'     => '_virastai_acr_title_generated',
						'value'   => array( 'failed', 'skipped' ),
						'compare' => 'IN',
					),
				),
			) );
			$failed_ids = $failed_query->posts;
		}

		if ( empty( $failed_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No failed or skipped posts found to retry.', 'virastai-auto-content-rewrite-with-ai' ) ) );
		}

		$keys_to_clear = array(
			'_virastai_acr_content_generated',
			'_virastai_acr_title_generated',
			'_virastai_acr_excerpt_generated',
			'_virastai_acr_retry_count',
		);

		foreach ( $failed_ids as $fid ) {
			foreach ( $keys_to_clear as $key ) {
				delete_post_meta( $fid, $key );
			}
		}

		$count = count( $failed_ids );
		$state['processed_ids']   = array_values( array_diff( (array) $state['processed_ids'], $failed_ids ) );
		$state['processed_posts'] = max( 0, (int) $state['processed_posts'] - $count );
		$state['failed_posts']    = 0;
		$state['failed_ids']      = array();

		if ( 'completed' === $state['status'] || 'stopped' === $state['status'] ) {
			$state['status'] = 'running';
		}

		if ( 'running' === $state['status'] ) {
			$state['next_run_at'] = 0;
		}

		$this->update_state( $state );

		if ( 'running' === $state['status'] ) {
			$this->schedule_immediate_run();
		}

		if ( class_exists( 'Virastai_Acr_Logger' ) ) {
			Virastai_Acr_Logger::log( 'info', sprintf( 'Queued %d failed/skipped posts for retry.', $count ) );
		}

		$percentage = $state['total_posts'] > 0 ? min( 100, round( ( $state['processed_posts'] / $state['total_posts'] ) * 100 ) ) : 0;

		wp_send_json_success( array(
			'state'      => $state,
			'percentage' => $percentage,
			'message'    => sprintf(
				/* translators: %d: number of posts queued for retry */
				__( '%d failed/skipped posts queued for retry.', 'virastai-auto-content-rewrite-with-ai' ),
				$count
			),
		) );
	}

	/**
	 * AJAX handler to poll current automation status.
	 */
	public function handle_ajax_status() {
		check_ajax_referer( 'virastai_acr_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'virastai-auto-content-rewrite-with-ai' ) ) );
		}

		$state = $this->get_state();

		// If running and not locked, check rate limit and tick batch processing
		if ( 'running' === $state['status'] && ! get_transient( self::LOCK_KEY ) ) {
			$now         = time();
			$next_run_at = ! empty( $state['next_run_at'] ) ? (int) $state['next_run_at'] : 0;

			// Allow execution if first post or if scheduled time has arrived (allow 1s tolerance)
			if ( 0 === (int) $state['processed_posts'] || $now >= ( $next_run_at - 1 ) ) {
				$this->run_batch_process( 1 );
				$state = $this->get_state();
			}
		}

		$percentage         = $state['total_posts'] > 0 ? min( 100, round( ( $state['processed_posts'] / $state['total_posts'] ) * 100 ) ) : 0;
		if ( 'completed' === $state['status'] && 0 === (int) $state['failed_posts'] && $state['processed_posts'] > 0 ) {
			$percentage = 100;
		}
		$seconds_until_next = max( 0, ( ! empty( $state['next_run_at'] ) ? (int) $state['next_run_at'] : 0 ) - time() );

		wp_send_json_success( array(
			'state'              => $state,
			'percentage'         => $percentage,
			'seconds_until_next' => $seconds_until_next,
		) );
	}

	/**
	 * Calculate eligible and already rewritten post counts for given parameters.
	 *
	 * @param string $start_date                Start date string (Y-m-d).
	 * @param string $end_date                  End date string (Y-m-d).
	 * @param array  $post_types                Target post types.
	 * @param bool   $include_already_rewritten Whether to include rewritten posts.
	 * @return array
	 */
	public function calculate_counts( $start_date, $end_date, array $post_types, $include_already_rewritten = false ) {
		$start_time = ! empty( $start_date ) ? strtotime( $start_date ) : false;
		$start_date = ( false !== $start_time ) ? gmdate( 'Y-m-d', $start_time ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );

		$end_time = ! empty( $end_date ) ? strtotime( $end_date ) : false;
		$end_date = ( false !== $end_time ) ? gmdate( 'Y-m-d', $end_time ) : gmdate( 'Y-m-d' );
		$post_types = ! empty( $post_types ) ? array_map( 'sanitize_key', $post_types ) : array( 'post' );

		if ( strtotime( $start_date ) > strtotime( $end_date ) ) {
			return array(
				'total_in_range'    => 0,
				'unrewritten'       => 0,
				'already_rewritten' => 0,
				'will_be_rewritten' => 0,
			);
		}

		$total_query = new WP_Query( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'after'     => $start_date . ' 00:00:00',
					'before'    => $end_date . ' 23:59:59',
					'inclusive' => true,
					'column'    => 'post_date',
				),
			),
		) );
		$total_in_range = (int) $total_query->found_posts;

		$enable_title   = (bool) get_option( 'virastai_acr_enable_title_rewrite', 0 );
		$enable_content = (bool) get_option( 'virastai_acr_enable_content_rewrite', 1 );
		$enable_excerpt = (bool) get_option( 'virastai_acr_enable_excerpt_rewrite', 0 );

		$meta_subqueries = array( 'relation' => 'OR' );
		if ( $enable_content ) {
			$meta_subqueries[] = array(
				'key'     => '_virastai_acr_content_generated',
				'compare' => 'NOT EXISTS',
			);
		}
		if ( $enable_title ) {
			$meta_subqueries[] = array(
				'key'     => '_virastai_acr_title_generated',
				'compare' => 'NOT EXISTS',
			);
		}
		if ( $enable_excerpt ) {
			$meta_subqueries[] = array(
				'key'     => '_virastai_acr_excerpt_generated',
				'compare' => 'NOT EXISTS',
			);
		}

		$unrewritten_count = 0;
		if ( count( $meta_subqueries ) > 1 ) {
			$unrewritten_query = new WP_Query( array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'after'     => $start_date . ' 00:00:00',
						'before'    => $end_date . ' 23:59:59',
						'inclusive' => true,
						'column'    => 'post_date',
					),
				),
				'meta_query'     => $meta_subqueries,
			) );
			$unrewritten_count = min( $total_in_range, (int) $unrewritten_query->found_posts );
		}

		$already_rewritten_count = max( 0, $total_in_range - $unrewritten_count );
		$will_be_rewritten       = $include_already_rewritten ? $total_in_range : $unrewritten_count;

		return array(
			'total_in_range'    => $total_in_range,
			'unrewritten'       => $unrewritten_count,
			'already_rewritten' => $already_rewritten_count,
			'will_be_rewritten' => $will_be_rewritten,
		);
	}

	/**
	 * AJAX handler to preview eligible and rewritten counts based on parameters.
	 */
	public function handle_ajax_preview_counts() {
		check_ajax_referer( 'virastai_acr_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'virastai-auto-content-rewrite-with-ai' ) ) );
		}

		$start_date   = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date     = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
		$post_types   = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) ) : array( 'post' );
		$include_prev = ! empty( $_POST['include_already_rewritten'] );

		$counts = $this->calculate_counts( $start_date, $end_date, $post_types, $include_prev );

		wp_send_json_success( array(
			'counts'         => $counts,
			'formatted_text' => sprintf(
				/* translators: 1: number of posts to be rewritten, 2: number of posts already rewritten */
				__( '%1$d will be rewritten. %2$d already rewritten.', 'virastai-auto-content-rewrite-with-ai' ),
				$counts['will_be_rewritten'],
				$counts['already_rewritten']
			),
		) );
	}

	/**
	 * Cron or AJAX execution callback.
	 *
	 * @param int $max_batch_size Maximum posts to process in this run (0 for auto based on posts_per_minute).
	 */
	public function run_batch_process( $max_batch_size = 0 ) {
		$state = $this->get_state();
		if ( 'running' !== $state['status'] ) {
			wp_clear_scheduled_hook( self::EVENT_HOOK );
			return;
		}

		if ( get_transient( self::LOCK_KEY ) ) {
			return;
		}

		$posts_per_minute = ! empty( $state['posts_per_minute'] ) ? max( 1, min( 60, intval( $state['posts_per_minute'] ) ) ) : 5;

		// Check rate limit if called by WP-Cron
		if ( 0 === $max_batch_size && ! empty( $state['next_run_at'] ) && time() < ( (int) $state['next_run_at'] - 1 ) ) {
			$remaining = max( 5, (int) $state['next_run_at'] - time() );
			$this->schedule_run_in( $remaining );
			return;
		}

		set_transient( self::LOCK_KEY, 1, 90 );

		$batch_size  = $max_batch_size > 0 ? (int) $max_batch_size : min( $posts_per_minute, 5 );
		$query_args  = $this->build_query_args( $state, $batch_size );
		$posts_query = new WP_Query( $query_args );
		$posts       = $posts_query->posts;

		if ( empty( $posts ) || ( $state['total_posts'] > 0 && $state['processed_posts'] >= $state['total_posts'] ) ) {
			$state['status']             = 'completed';
			$state['current_post_id']    = 0;
			$state['current_post_title'] = '';
			$state['next_run_at']        = 0;
			if ( $state['processed_posts'] < $state['total_posts'] ) {
				$state['total_posts'] = max( $state['processed_posts'] + $state['failed_posts'], $state['processed_posts'] );
			}
			$this->update_state( $state );
			delete_transient( self::LOCK_KEY );
			wp_clear_scheduled_hook( self::EVENT_HOOK );

			if ( class_exists( 'Virastai_Acr_Logger' ) ) {
				Virastai_Acr_Logger::log( 'info', sprintf( 'Custom Automation finished successfully. Total processed: %d', $state['processed_posts'] ) );
			}
			return;
		}

		$start_time      = microtime( true );
		$time_limit      = 25;
		$batch_processed = 0;
		$force           = ! empty( $state['include_already_rewritten'] );

		foreach ( $posts as $post ) {
			if ( ( microtime( true ) - $start_time ) >= $time_limit ) {
				break;
			}

			$post_id                     = is_object( $post ) ? $post->ID : (int) $post;
			$post_obj                    = is_object( $post ) ? $post : get_post( $post_id );
			$state['current_post_id']    = $post_id;
			$state['current_post_title'] = get_the_title( $post_id );
			$this->update_state( $state );

			try {
				$success = $this->rewriter->virastai_acr_generate_content( $post_id, $post_obj, '', $force );
				$state['processed_posts']++;
				$state['processed_ids'][] = $post_id;
				$batch_processed++;
				if ( ! $success ) {
					$state['failed_posts']++;
					$state['failed_ids'][] = $post_id;
					if ( class_exists( 'Virastai_Acr_Logger' ) ) {
						Virastai_Acr_Logger::log( 'warning', sprintf( 'Custom Automation: Post ID %d ("%s") was not rewritten. Check logs above for specific error or skip reason.', $post_id, get_the_title( $post_id ) ) );
					}
				}
			} catch ( Exception $e ) {
				$state['processed_posts']++;
				$state['failed_posts']++;
				$state['failed_ids'][]    = $post_id;
				$state['processed_ids'][] = $post_id;
				$batch_processed++;
				if ( class_exists( 'Virastai_Acr_Logger' ) ) {
					Virastai_Acr_Logger::log( 'error', sprintf( 'Custom Automation: Exception on Post ID %d ("%s"): %s', $post_id, get_the_title( $post_id ), $e->getMessage() ) );
				}
			}
		}

		if ( $state['total_posts'] > 0 && $state['processed_posts'] >= $state['total_posts'] ) {
			$state['status']             = 'completed';
			$state['current_post_id']    = 0;
			$state['current_post_title'] = '';
			$state['next_run_at']        = 0;
		}

		if ( 'running' === $state['status'] ) {
			$seconds_per_post  = 60 / $posts_per_minute;
			$batch_time_budget = $seconds_per_post * max( 1, $batch_processed );
			$elapsed           = microtime( true ) - $start_time;
			$delay             = max( 0, round( $batch_time_budget - $elapsed ) );

			$state['next_run_at']        = time() + $delay;
			$state['current_post_id']    = 0;
			$state['current_post_title'] = '';

			$this->update_state( $state );
			delete_transient( self::LOCK_KEY );

			$cron_delay = max( 5, $delay );
			$this->schedule_run_in( $cron_delay );
		} else {
			$this->update_state( $state );
			delete_transient( self::LOCK_KEY );
		}
	}

	/**
	 * Schedule follow up batch run in specified seconds.
	 *
	 * @param int $delay Delay in seconds.
	 */
	private function schedule_run_in( $delay = 5 ) {
		wp_clear_scheduled_hook( self::EVENT_HOOK );
		wp_schedule_single_event( time() + max( 5, (int) $delay ), self::EVENT_HOOK );
	}

	/**
	 * Schedule immediate follow up batch run.
	 */
	private function schedule_immediate_run() {
		$this->schedule_run_in( 5 );
	}

	/**
	 * Render the Custom Automation tab view.
	 */
	public function render_tab() {
		$state          = $this->get_state();
		$post_types     = get_post_types( array( 'public' => true ), 'objects' );
		$percentage     = $state['total_posts'] > 0 ? min( 100, round( ( $state['processed_posts'] / $state['total_posts'] ) * 100 ) ) : 0;
		if ( 'completed' === $state['status'] && 0 === (int) $state['failed_posts'] && $state['processed_posts'] > 0 ) {
			$percentage = 100;
		}
		$is_running     = 'running' === $state['status'];
		$is_paused      = 'paused' === $state['status'];
		$is_active      = $is_running || $is_paused;
		$initial_counts = $this->calculate_counts( $state['start_date'], $state['end_date'], (array) $state['post_types'], ! empty( $state['include_already_rewritten'] ) );
		?>
		<div class="virastai-acr-ca-container">
			<div class="virastai-acr-ca-header">
				<h2 style="font-size: 18px; font-weight: 600; margin-bottom: 6px; color: #111827;">
					<?php esc_html_e( 'Custom Automation Engine', 'virastai-auto-content-rewrite-with-ai' ); ?>
				</h2>
				<p class="description" style="margin-bottom: 20px;">
					<?php esc_html_e( 'Rewrite posts published within a specific date range automatically using WordPress Cron.', 'virastai-auto-content-rewrite-with-ai' ); ?>
				</p>
			</div>

			<!-- Status & Progress Dashboard -->
			<div class="virastai-acr-ca-card virastai-acr-ca-progress-card">
				<div class="virastai-acr-ca-status-header">
					<div class="virastai-acr-ca-status-info">
						<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
							<span class="virastai-acr-ca-status-badge badge-<?php echo esc_attr( $state['status'] ); ?>" id="virastai-acr-ca-status-badge">
								<?php echo esc_html( strtoupper( $state['status'] ) ); ?>
							</span>
							<?php if ( ! empty( $state['start_date'] ) && ! empty( $state['end_date'] ) && (int) $state['total_posts'] > 0 ) : ?>
								<span class="virastai-acr-ca-job-scope" id="virastai-acr-ca-job-scope" style="font-size: 12px; color: #6b7280;">
									<?php
									printf(
										/* translators: 1: start date, 2: end date */
										esc_html__( '(Job Scope: %1$s to %2$s)', 'virastai-auto-content-rewrite-with-ai' ),
										esc_html( $state['start_date'] ),
										esc_html( $state['end_date'] )
									);
									?>
								</span>
							<?php else : ?>
								<span class="virastai-acr-ca-job-scope" id="virastai-acr-ca-job-scope" style="font-size: 12px; color: #6b7280; display: none;"></span>
							<?php endif; ?>
						</div>
						<span class="virastai-acr-ca-active-item" id="virastai-acr-ca-active-item">
							<?php
							if ( $is_running && ! empty( $state['current_post_title'] ) ) {
								/* translators: %s: Title of the current post being processed */
								printf( esc_html__( 'Processing: %s', 'virastai-auto-content-rewrite-with-ai' ), esc_html( $state['current_post_title'] ) );
							}
							?>
						</span>
					</div>
					<div class="virastai-acr-ca-percent" id="virastai-acr-ca-percent-text">
						<?php echo esc_html( $percentage ); ?>%
					</div>
				</div>

				<div class="virastai-acr-progress-bar-track">
					<div class="virastai-acr-progress-bar-fill" id="virastai-acr-ca-progress-fill" style="width: <?php echo esc_attr( $percentage ); ?>%;"></div>
				</div>

				<div class="virastai-acr-ca-metrics-grid">
					<div class="virastai-acr-ca-metric">
						<span class="metric-label"><?php esc_html_e( 'Total Target', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
						<span class="metric-value" id="virastai-acr-ca-metric-total"><?php echo esc_html( $state['total_posts'] ); ?></span>
					</div>
					<div class="virastai-acr-ca-metric">
						<span class="metric-label"><?php esc_html_e( 'Processed', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
						<span class="metric-value text-success" id="virastai-acr-ca-metric-processed"><?php echo esc_html( $state['processed_posts'] ); ?></span>
					</div>
					<div class="virastai-acr-ca-metric virastai-acr-ca-metric-failed">
						<span class="metric-label"><?php esc_html_e( 'Failed / Skipped', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
						<div class="virastai-acr-ca-failed-row">
							<span class="metric-value text-danger" id="virastai-acr-ca-metric-failed"><?php echo esc_html( $state['failed_posts'] ); ?></span>
							<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-secondary virastai-acr-ca-retry-btn" id="virastai-acr-ca-retry-btn" <?php disabled( empty( $state['failed_posts'] ) ); ?>>
								<span class="dashicons dashicons-update"></span>
								<span class="retry-btn-text"><?php esc_html_e( 'Retry Failed', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Configuration & Control Form -->
			<div class="virastai-acr-ca-card virastai-acr-ca-controls-card">
				<h3 style="font-size: 15px; font-weight: 600; margin-bottom: 15px; color: #111827;">
					<?php esc_html_e( 'Job Parameters', 'virastai-auto-content-rewrite-with-ai' ); ?>
				</h3>

				<div class="virastai-acr-ca-form-grid">
					<div class="virastai-acr-ca-form-field">
						<label for="virastai_acr_ca_start_date"><?php esc_html_e( 'From Publication Date', 'virastai-auto-content-rewrite-with-ai' ); ?></label>
						<input type="date" id="virastai_acr_ca_start_date" class="virastai-acr-input" value="<?php echo esc_attr( $state['start_date'] ); ?>" <?php disabled( $is_active ); ?>>
					</div>
					<div class="virastai-acr-ca-form-field">
						<label for="virastai_acr_ca_end_date"><?php esc_html_e( 'To Publication Date', 'virastai-auto-content-rewrite-with-ai' ); ?></label>
						<input type="date" id="virastai_acr_ca_end_date" class="virastai-acr-input" value="<?php echo esc_attr( $state['end_date'] ); ?>" <?php disabled( $is_active ); ?>>
					</div>
				</div>

				<div class="virastai-acr-ca-form-field" style="margin-top: 15px;">
					<label for="virastai_acr_ca_posts_per_minute"><?php esc_html_e( 'Posts Per Minute', 'virastai-auto-content-rewrite-with-ai' ); ?></label>
					<input type="number" id="virastai_acr_ca_posts_per_minute" class="virastai-acr-input" min="1" max="60" value="<?php echo esc_attr( ! empty( $state['posts_per_minute'] ) ? $state['posts_per_minute'] : 5 ); ?>" <?php disabled( $is_active ); ?> style="max-width: 140px;">
					<p class="description" style="margin-top: 4px;"><?php esc_html_e( 'Specify the desired rewrite rate (1 to 60 posts per minute).', 'virastai-auto-content-rewrite-with-ai' ); ?></p>
				</div>

				<div class="virastai-acr-ca-form-field" style="margin-top: 15px;">
					<label><?php esc_html_e( 'Target Post Types', 'virastai-auto-content-rewrite-with-ai' ); ?></label>
					<div class="virastai-acr-checkbox-grid" style="margin-top: 8px;">
						<?php
						$selected_types = is_array( $state['post_types'] ) ? $state['post_types'] : array( 'post' );
						foreach ( $post_types as $pt ) :
							$checked = in_array( $pt->name, $selected_types, true );
							?>
							<label class="virastai-acr-checkbox-card <?php echo $checked ? 'active' : ''; ?>">
								<input type="checkbox" name="virastai_acr_ca_post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( $checked ); ?> <?php disabled( $is_active ); ?>>
								<span><?php echo esc_html( $pt->labels->singular_name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="virastai-acr-ca-form-field virastai-acr-ca-counts-wrapper" style="margin-top: 15px;">
					<label class="virastai-acr-inline-checkbox">
						<input type="checkbox" id="virastai_acr_ca_include_rewritten" value="1" <?php checked( ! empty( $state['include_already_rewritten'] ) ); ?> <?php disabled( $is_active ); ?>>
						<span><?php esc_html_e( 'Include already rewritten posts (overwrite existing generations)', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
					</label>
					<span class="virastai-acr-ca-counts-badge" id="virastai-acr-ca-counts-badge">
						<span class="virastai-acr-ca-counts-text">
							<?php
							printf(
								/* translators: 1: number of posts to be rewritten, 2: number of posts already rewritten */
								esc_html__( '%1$d will be rewritten. %2$d already rewritten.', 'virastai-auto-content-rewrite-with-ai' ),
								(int) $initial_counts['will_be_rewritten'],
								(int) $initial_counts['already_rewritten']
							);
							?>
						</span>
					</span>
				</div>

				<!-- Action Controls -->
				<div class="virastai-acr-ca-actions-bar" style="margin-top: 25px; display: flex; gap: 12px; align-items: center;">
					<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-primary" id="virastai-acr-ca-start-btn" <?php disabled( $is_running ); ?>>
						<span class="dashicons dashicons-controls-play"></span>
						<span class="btn-text"><?php echo $is_paused ? esc_html__( 'Resume Automation', 'virastai-auto-content-rewrite-with-ai' ) : esc_html__( 'Start Automation', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
					</button>

					<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-secondary" id="virastai-acr-ca-pause-btn" <?php disabled( ! $is_running ); ?>>
						<span class="dashicons dashicons-controls-pause"></span>
						<span class="btn-text"><?php esc_html_e( 'Pause', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
					</button>

					<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-danger" id="virastai-acr-ca-stop-btn" <?php disabled( ! $is_active ); ?>>
						<span class="dashicons dashicons-controls-forward"></span>
						<span class="btn-text"><?php esc_html_e( 'Stop', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
					</button>
				</div>
			</div>
		</div>
		<?php
	}
}
