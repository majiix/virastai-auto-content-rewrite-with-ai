<?php
/**
 * Class Virastai_Acr_Admin_Sidebar
 *
 * Handles the sidebar rendering for the admin settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Virastai_Acr_Admin_Sidebar {

	/**
	 * Delete cached statistics transient.
	 */
	public static function delete_stats_transient() {
		$selected_post_types = get_option( 'virastai_acr_allowed_post_types', array( 'product' ) );
		$selected_post_statuses = get_option( 'virastai_acr_allowed_post_statuses', array( 'publish' ) );
		$transient_key = 'v_stats_' . md5( wp_json_encode( $selected_post_types ) . wp_json_encode( $selected_post_statuses ) );
		delete_transient( $transient_key );
	}

	/**
	 * Render the sidebar HTML.
	 */
	public static function render() {
		// --- Logic for Statistics ---
		$selected_post_types = get_option( 'virastai_acr_allowed_post_types', array( 'product' ) );
		if ( ! is_array( $selected_post_types ) ) {
			$selected_post_types = array( 'product' );
		}

		$selected_post_statuses = get_option( 'virastai_acr_allowed_post_statuses', array( 'publish' ) );
		if ( ! is_array( $selected_post_statuses ) ) {
			$selected_post_statuses = array( 'publish' );
		}

		$enable_title_rewrite   = get_option( 'virastai_acr_enable_title_rewrite', 0 );
		$enable_content_rewrite = get_option( 'virastai_acr_enable_content_rewrite', 1 );

		global $wpdb;

		$processed_title_count   = 0;
		$processed_content_count = 0;
		$total_count             = 0;

		// Only run queries if we have valid settings.
		if ( ! empty( $selected_post_types ) && ! empty( $selected_post_statuses ) ) {
			// Generate Transient Key based on current settings.
			$transient_key = 'v_stats_' . md5( wp_json_encode( $selected_post_types ) . wp_json_encode( $selected_post_statuses ) );
			$cached        = get_transient( $transient_key );

			if ( false !== $cached ) {
				$processed_title_count   = $cached['title'];
				$processed_content_count = $cached['content'];
				$total_count             = $cached['total'];
			} else {
				$placeholders_types    = implode( ', ', array_fill( 0, count( $selected_post_types ), '%s' ) );
				$placeholders_statuses = implode( ', ', array_fill( 0, count( $selected_post_statuses ), '%s' ) );
				$sql_params            = array_merge( $selected_post_types, $selected_post_statuses );

				// 1. Count Processed Titles.
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Query structure requires dynamic placeholders for IN clause.
				$sql_title = "
					SELECT COUNT(p.ID)
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					WHERE p.post_type IN ($placeholders_types)
					AND p.post_status IN ($placeholders_statuses)
					AND pm.meta_key = '_virastai_acr_title_generated'
				";
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$processed_title_count = (int) $wpdb->get_var( $wpdb->prepare( $sql_title, $sql_params ) );
				// phpcs:enable

				// 2. Count Processed Content.
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
				$sql_content = "
					SELECT COUNT(p.ID)
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					WHERE p.post_type IN ($placeholders_types)
					AND p.post_status IN ($placeholders_statuses)
					AND pm.meta_key = '_virastai_acr_content_generated'
				";
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$processed_content_count = (int) $wpdb->get_var( $wpdb->prepare( $sql_content, $sql_params ) );
				// phpcs:enable

				// 3. Count Total items matching Types AND Statuses.
				foreach ( $selected_post_types as $pt ) {
					$counts = wp_count_posts( $pt );
					foreach ( $selected_post_statuses as $status ) {
						$total_count += ( isset( $counts->$status ) ? (int) $counts->$status : 0 );
					}
				}

				// Cache results for 5 minutes via transient.
				set_transient(
					$transient_key,
					array(
						'title'   => $processed_title_count,
						'content' => $processed_content_count,
						'total'   => $total_count,
					),
					300
				);
			}
		}

		// Calculate Total Completion Percentage.
		$total_ops_needed = 0;
		$total_ops_done   = 0;

		if ( $total_count > 0 ) {
			if ( $enable_title_rewrite ) {
				$total_ops_needed += $total_count;
				$total_ops_done   += $processed_title_count;
			}
			if ( $enable_content_rewrite ) {
				$total_ops_needed += $total_count;
				$total_ops_done   += $processed_content_count;
			}
		}

		$percentage = ( $total_ops_needed > 0 ) ? round( ( $total_ops_done / $total_ops_needed ) * 100 ) : 0;
		?>
		<div class="virastai-acr-sidebar">
			<div class="virastai-acr-container virastai-acr-card-sidebar">
				<h2><?php esc_html_e( 'Statistics', 'virastai-auto-content-rewrite-with-ai' ); ?></h2>
				<div class="virastai-acr-stat-row">
					<span class="stat-label"><?php esc_html_e( 'Selected Types:', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
					<span class="stat-value"><?php echo esc_html( implode( ', ', $selected_post_types ) ); ?></span>
				</div>
				<div class="virastai-acr-stat-row">
					<span class="stat-label"><?php esc_html_e( 'Total Posts:', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
					<span class="stat-value"><?php echo esc_html( $total_count ); ?></span>
				</div>
				<div class="virastai-acr-stat-row">
					<span class="stat-label"><?php esc_html_e( 'Processed Titles:', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
					<span class="stat-value virastai-acr-stat-success"><?php echo esc_html( $processed_title_count ); ?></span>
				</div>
				<div class="virastai-acr-stat-row">
					<span class="stat-label"><?php esc_html_e( 'Processed Content:', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
					<span class="stat-value virastai-acr-stat-success"><?php echo esc_html( $processed_content_count ); ?></span>
				</div>

				<div class="virastai-acr-progress-bar-bg">
					<div class="virastai-acr-progress-bar-fill" style="width: <?php echo esc_attr( $percentage ); ?>%;"></div>
				</div>
				<p class="virastai-acr-progress-text"><?php echo esc_html( $percentage ); ?>% <?php esc_html_e( 'Complete', 'virastai-auto-content-rewrite-with-ai' ); ?></p>
			</div>

			<div class="virastai-acr-container virastai-acr-card-sidebar virastai-acr-mt-20">
				<h2><?php esc_html_e( 'Actions', 'virastai-auto-content-rewrite-with-ai' ); ?></h2>

				<!-- Action Forms with IDs for JS targeting -->
				<form method="post" id="virastai-acr-form-reset-all">
					<?php wp_nonce_field( 'virastai_acr_reset_action', 'virastai_acr_reset_nonce' ); ?>
					<input type="submit" name="virastai_acr_reset_progress" class="button button-secondary virastai-acr-full-width virastai-acr-mb-10" value="<?php esc_attr_e( 'Reset ALL Progress', 'virastai-auto-content-rewrite-with-ai' ); ?>">
				</form>

				<div class="virastai-acr-flex-row virastai-acr-mb-10">
					<form method="post" class="virastai-acr-flex-item" id="virastai-acr-form-reset-title">
						<?php wp_nonce_field( 'virastai_acr_reset_action', 'virastai_acr_reset_nonce' ); ?>
						<input type="submit" name="virastai_acr_reset_title" class="button button-secondary virastai-acr-full-width" value="<?php esc_attr_e( 'Reset Titles', 'virastai-auto-content-rewrite-with-ai' ); ?>">
					</form>
					<form method="post" class="virastai-acr-flex-item" id="virastai-acr-form-reset-content">
						<?php wp_nonce_field( 'virastai_acr_reset_action', 'virastai_acr_reset_nonce' ); ?>
						<input type="submit" name="virastai_acr_reset_content" class="button button-secondary virastai-acr-full-width" value="<?php esc_attr_e( 'Reset Content', 'virastai-auto-content-rewrite-with-ai' ); ?>">
					</form>
				</div>

				<hr>

				<div class="virastai-acr-flex-row virastai-acr-mt-20">
					<form method="post" class="virastai-acr-flex-item" id="virastai-acr-form-clear-sandbox-title">
						<?php wp_nonce_field( 'virastai_acr_reset_action', 'virastai_acr_reset_nonce' ); ?>
						<input type="submit" name="virastai_acr_clear_sandbox_title" class="button button-secondary virastai-acr-full-width" value="<?php esc_attr_e( 'Clear Sandbox Titles', 'virastai-auto-content-rewrite-with-ai' ); ?>">
					</form>
					<form method="post" class="virastai-acr-flex-item" id="virastai-acr-form-clear-sandbox-content">
						<?php wp_nonce_field( 'virastai_acr_reset_action', 'virastai_acr_reset_nonce' ); ?>
						<input type="submit" name="virastai_acr_clear_sandbox_content" class="button button-secondary virastai-acr-full-width" value="<?php esc_attr_e( 'Clear Sandbox Content', 'virastai-auto-content-rewrite-with-ai' ); ?>">
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}
