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
		$enable_excerpt_rewrite = get_option( 'virastai_acr_enable_excerpt_rewrite', 0 );

		global $wpdb;

		$processed_title_count   = 0;
		$processed_content_count = 0;
		$processed_excerpt_count = 0;
		$sandbox_title_count     = 0;
		$sandbox_content_count   = 0;
		$sandbox_excerpt_count   = 0;
		$total_count             = 0;

		// Only run queries if we have valid settings.
		if ( ! empty( $selected_post_types ) && ! empty( $selected_post_statuses ) ) {
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

			// 3. Count Processed Excerpts.
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$sql_excerpt = "
				SELECT COUNT(p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type IN ($placeholders_types)
				AND p.post_status IN ($placeholders_statuses)
				AND pm.meta_key = '_virastai_acr_excerpt_generated'
			";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$processed_excerpt_count = (int) $wpdb->get_var( $wpdb->prepare( $sql_excerpt, $sql_params ) );
			// phpcs:enable

			// 4. Count Sandbox Titles.
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$sql_sb_title = "
				SELECT COUNT(p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type IN ($placeholders_types)
				AND p.post_status IN ($placeholders_statuses)
				AND pm.meta_key = '_virastai_acr_sandbox_title'
				AND pm.meta_value != ''
			";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sandbox_title_count = (int) $wpdb->get_var( $wpdb->prepare( $sql_sb_title, $sql_params ) );
			// phpcs:enable

			// 5. Count Sandbox Content.
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$sql_sb_content = "
				SELECT COUNT(p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type IN ($placeholders_types)
				AND p.post_status IN ($placeholders_statuses)
				AND pm.meta_key = '_virastai_acr_sandbox_content'
				AND pm.meta_value != ''
			";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sandbox_content_count = (int) $wpdb->get_var( $wpdb->prepare( $sql_sb_content, $sql_params ) );
			// phpcs:enable

			// 6. Count Sandbox Excerpts.
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$sql_sb_excerpt = "
				SELECT COUNT(p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type IN ($placeholders_types)
				AND p.post_status IN ($placeholders_statuses)
				AND pm.meta_key = '_virastai_acr_sandbox_excerpt'
				AND pm.meta_value != ''
			";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sandbox_excerpt_count = (int) $wpdb->get_var( $wpdb->prepare( $sql_sb_excerpt, $sql_params ) );
			// phpcs:enable

			// 7. Count Total items matching Types AND Statuses.
			foreach ( $selected_post_types as $pt ) {
				$counts = wp_count_posts( $pt );
				foreach ( $selected_post_statuses as $status ) {
					$total_count += ( isset( $counts->$status ) ? (int) $counts->$status : 0 );
				}
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
			if ( $enable_excerpt_rewrite ) {
				$total_ops_needed += $total_count;
				$total_ops_done   += $processed_excerpt_count;
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
				<?php if ( $enable_title_rewrite ) : ?>
					<div class="virastai-acr-stat-row" style="align-items: center;">
						<span class="stat-label"><?php esc_html_e( 'Processed Titles:', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
						<form method="post" style="display:inline;" id="virastai-acr-form-reset-title">
							<?php wp_nonce_field( 'virastai_acr_reset_action', 'virastai_acr_reset_nonce' ); ?>
							<button type="submit" name="virastai_acr_reset_title" class="virastai-acr-stat-delete-btn" title="<?php esc_attr_e( 'Reset Title Progress', 'virastai-auto-content-rewrite-with-ai' ); ?>">
								<span><?php echo esc_html( $processed_title_count ); ?></span>
								<span class="dashicons dashicons-trash"></span>
							</button>
						</form>
					</div>
				<?php endif; ?>
				<?php if ( $enable_content_rewrite ) : ?>
					<div class="virastai-acr-stat-row" style="align-items: center;">
						<span class="stat-label"><?php esc_html_e( 'Processed Content:', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
						<form method="post" style="display:inline;" id="virastai-acr-form-reset-content">
							<?php wp_nonce_field( 'virastai_acr_reset_action', 'virastai_acr_reset_nonce' ); ?>
							<button type="submit" name="virastai_acr_reset_content" class="virastai-acr-stat-delete-btn" title="<?php esc_attr_e( 'Reset Content Progress', 'virastai-auto-content-rewrite-with-ai' ); ?>">
								<span><?php echo esc_html( $processed_content_count ); ?></span>
								<span class="dashicons dashicons-trash"></span>
							</button>
						</form>
					</div>
				<?php endif; ?>
				<?php if ( $enable_excerpt_rewrite ) : ?>
					<div class="virastai-acr-stat-row" style="align-items: center;">
						<span class="stat-label"><?php esc_html_e( 'Processed Excerpts:', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
						<form method="post" style="display:inline;" id="virastai-acr-form-reset-excerpt">
							<?php wp_nonce_field( 'virastai_acr_reset_action', 'virastai_acr_reset_nonce' ); ?>
							<button type="submit" name="virastai_acr_reset_excerpt" class="virastai-acr-stat-delete-btn" title="<?php esc_attr_e( 'Reset Excerpt Progress', 'virastai-auto-content-rewrite-with-ai' ); ?>">
								<span><?php echo esc_html( $processed_excerpt_count ); ?></span>
								<span class="dashicons dashicons-trash"></span>
							</button>
						</form>
					</div>
				<?php endif; ?>

				<hr style="margin: 15px 0; border-top: 1px solid #e2e8f0;">
				<h3 style="font-size: 13px; font-weight: 600; color: #4b5563; margin: 0 0 10px 0;"><?php esc_html_e( 'Sandbox Drafts (Pending Review)', 'virastai-auto-content-rewrite-with-ai' ); ?></h3>
				<?php if ( $enable_title_rewrite ) : ?>
					<div class="virastai-acr-stat-row" style="align-items: center;">
						<span class="stat-label"><?php esc_html_e( 'Sandbox Titles:', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
						<form method="post" style="display:inline;" id="virastai-acr-form-clear-sandbox-title">
							<?php wp_nonce_field( 'virastai_acr_reset_action', 'virastai_acr_reset_nonce' ); ?>
							<button type="submit" name="virastai_acr_clear_sandbox_title" class="virastai-acr-stat-delete-btn" title="<?php esc_attr_e( 'Delete Sandbox Titles', 'virastai-auto-content-rewrite-with-ai' ); ?>">
								<span><?php echo esc_html( $sandbox_title_count ); ?></span>
								<span class="dashicons dashicons-trash"></span>
							</button>
						</form>
					</div>
				<?php endif; ?>
				<?php if ( $enable_content_rewrite ) : ?>
					<div class="virastai-acr-stat-row" style="align-items: center;">
						<span class="stat-label"><?php esc_html_e( 'Sandbox Content:', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
						<form method="post" style="display:inline;" id="virastai-acr-form-clear-sandbox-content">
							<?php wp_nonce_field( 'virastai_acr_reset_action', 'virastai_acr_reset_nonce' ); ?>
							<button type="submit" name="virastai_acr_clear_sandbox_content" class="virastai-acr-stat-delete-btn" title="<?php esc_attr_e( 'Delete Sandbox Content', 'virastai-auto-content-rewrite-with-ai' ); ?>">
								<span><?php echo esc_html( $sandbox_content_count ); ?></span>
								<span class="dashicons dashicons-trash"></span>
							</button>
						</form>
					</div>
				<?php endif; ?>
				<?php if ( $enable_excerpt_rewrite ) : ?>
					<div class="virastai-acr-stat-row" style="align-items: center;">
						<span class="stat-label"><?php esc_html_e( 'Sandbox Excerpts:', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
						<form method="post" style="display:inline;" id="virastai-acr-form-clear-sandbox-excerpt">
							<?php wp_nonce_field( 'virastai_acr_reset_action', 'virastai_acr_reset_nonce' ); ?>
							<button type="submit" name="virastai_acr_clear_sandbox_excerpt" class="virastai-acr-stat-delete-btn" title="<?php esc_attr_e( 'Delete Sandbox Excerpts', 'virastai-auto-content-rewrite-with-ai' ); ?>">
								<span><?php echo esc_html( $sandbox_excerpt_count ); ?></span>
								<span class="dashicons dashicons-trash"></span>
							</button>
						</form>
					</div>
				<?php endif; ?>

				<div class="virastai-acr-progress-bar-bg" style="margin-top: 15px;">
					<div class="virastai-acr-progress-bar-fill" style="width: <?php echo esc_attr( $percentage ); ?>%;"></div>
				</div>
				<p class="virastai-acr-progress-text"><?php echo esc_html( $percentage ); ?>% <?php esc_html_e( 'Complete', 'virastai-auto-content-rewrite-with-ai' ); ?></p>
			</div>
		</div>
		<?php
	}
}
