<?php
/**
 * Class Virastai_Acr_Metabox
 *
 * Handles the display of AI-generated content in a metabox when Sandbox Mode is enabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Virastai_Acr_Metabox {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'virastai_acr_add_sandbox_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'virastai_acr_enqueue_metabox_assets' ) );

		// AJAX Actions
		add_action( 'wp_ajax_virastai_acr_regenerate_sandbox', array( $this, 'handle_ajax_regenerate' ) );
		add_action( 'wp_ajax_virastai_acr_clear_sandbox', array( $this, 'handle_ajax_clear' ) );
		add_action( 'wp_ajax_virastai_acr_apply_sandbox', array( $this, 'handle_ajax_apply' ) );
	}

	/**
	 * Enqueue scripts and styles for the metabox.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function virastai_acr_enqueue_metabox_assets( $hook ) {
		global $post;
		if ( ! $post || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'virastai-acr-metabox-css',
			VIRASTAI_ACR_URL . 'assets/css/metabox.css',
			array(),
			VIRASTAI_ACR_VERSION
		);

		wp_enqueue_script(
			'virastai-acr-metabox-js',
			VIRASTAI_ACR_URL . 'assets/js/metabox.js',
			array( 'jquery' ),
			VIRASTAI_ACR_VERSION,
			true
		);

		wp_localize_script(
			'virastai-acr-metabox-js',
			'virastaiMetabox',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'virastai_acr_metabox_action' ),
				'postId'        => $post->ID,
				'confirmClear'  => __( 'Are you sure you want to clear the generated sandbox content?', 'virastai-auto-content-rewrite-with-ai' ),
				'savePostFirst' => __( 'Please save the post first before generating, as the content is retrieved from the database.', 'virastai-auto-content-rewrite-with-ai' ),
				'confirmApply'  => __( 'Warning: This action will overwrite your current post data with the generated content. You will lose access to the old data. Are you sure you want to proceed?', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);
	}

	/**
	 * Register the metabox for allowed post types.
	 */
	public function virastai_acr_add_sandbox_metabox() {
		$sandbox_enabled = get_option( 'virastai_acr_enable_sandbox_mode', 0 );
		if ( ! $sandbox_enabled ) {
			return;
		}

		$allowed_post_types = get_option( 'virastai_acr_allowed_post_types', array( 'product' ) );
		if ( ! is_array( $allowed_post_types ) ) {
			return;
		}

		foreach ( $allowed_post_types as $post_type ) {
			add_meta_box(
				'virastai_acr_sandbox_review',
				__( 'Virastai Sandbox Review', 'virastai-auto-content-rewrite-with-ai' ),
				array( $this, 'virastai_acr_render_metabox' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the metabox content.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function virastai_acr_render_metabox( $post ) {
		$sandbox_title   = get_post_meta( $post->ID, '_virastai_acr_sandbox_title', true );
		$sandbox_content = get_post_meta( $post->ID, '_virastai_acr_sandbox_content', true );
		$sandbox_excerpt = get_post_meta( $post->ID, '_virastai_acr_sandbox_excerpt', true );

		$enable_title_rewrite   = get_option( 'virastai_acr_enable_title_rewrite', 0 );
		$enable_content_rewrite = get_option( 'virastai_acr_enable_content_rewrite', 1 );
		$enable_excerpt_rewrite = get_option( 'virastai_acr_enable_excerpt_rewrite', 0 );

		?>
		<div class="virastai-acr-metabox-wrapper" id="virastai-acr-metabox-container">

			<div class="virastai-acr-metabox-actions">
				<?php
				$enabled_count = ( $enable_title_rewrite ? 1 : 0 ) + ( $enable_content_rewrite ? 1 : 0 ) + ( $enable_excerpt_rewrite ? 1 : 0 );
				if ( $enabled_count > 1 ) :
				?>
					<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-all virastai-acr-regen-all-btn" data-type="all">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Generate All', 'virastai-auto-content-rewrite-with-ai' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( $enable_content_rewrite ) : ?>
					<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-secondary virastai-acr-regen-btn" data-type="content">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Generate Content', 'virastai-auto-content-rewrite-with-ai' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( $enable_title_rewrite ) : ?>
					<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-secondary virastai-acr-regen-btn" data-type="title">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Generate Title', 'virastai-auto-content-rewrite-with-ai' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( $enable_excerpt_rewrite ) : ?>
					<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-secondary virastai-acr-regen-btn" data-type="excerpt">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Generate Excerpt', 'virastai-auto-content-rewrite-with-ai' ); ?>
					</button>
				<?php endif; ?>

				<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-danger virastai-acr-clear-btn" style="float:right;">
					<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear Generated', 'virastai-auto-content-rewrite-with-ai' ); ?>
				</button>
				<div style="clear:both;"></div>
				<div class="virastai-acr-loader" style="display:none; margin-top:10px;">
					<span class="spinner is-active" style="float:none; margin:0;"></span> <?php esc_html_e( 'Processing...', 'virastai-auto-content-rewrite-with-ai' ); ?>
				</div>
			</div>

			<hr style="margin: 15px 0;">

			<?php if ( empty( $sandbox_title ) && empty( $sandbox_content ) && empty( $sandbox_excerpt ) ) : ?>
				<p class="virastai-acr-empty-msg"><?php esc_html_e( 'No content generated yet.', 'virastai-auto-content-rewrite-with-ai' ); ?></p>
			<?php endif; ?>

			<!-- Skeleton Loader Placeholder -->
			<div class="virastai-acr-skeleton-loader" style="display:none; margin-top:15px;">
				<div class="virastai-acr-skeleton-item title"></div>
				<div class="virastai-acr-skeleton-item line"></div>
				<div class="virastai-acr-skeleton-item line"></div>
				<div class="virastai-acr-skeleton-item line"></div>
				<div class="virastai-acr-skeleton-item line short"></div>
			</div>

			<div class="virastai-acr-sandbox-fields" <?php echo ( empty( $sandbox_title ) && empty( $sandbox_content ) && empty( $sandbox_excerpt ) ) ? 'style="display:none;"' : ''; ?>>
				<div class="virastai-acr-field-group virastai-acr-title-group" <?php echo empty( $sandbox_title ) ? 'style="display:none;"' : ''; ?>>
					<label for="virastai_acr_sandbox_title_field">
						<?php esc_html_e( 'Generated Title:', 'virastai-auto-content-rewrite-with-ai' ); ?>
					</label>
					<input type="text" id="virastai_acr_sandbox_title_field" class="large-text" value="<?php echo esc_attr( $sandbox_title ); ?>" readonly>
				</div>

				<div class="virastai-acr-field-group virastai-acr-content-group" <?php echo empty( $sandbox_content ) ? 'style="display:none;"' : ''; ?>>
					<label for="virastai_acr_sandbox_content_field">
						<?php esc_html_e( 'Generated Content:', 'virastai-auto-content-rewrite-with-ai' ); ?>
					</label>
					<textarea id="virastai_acr_sandbox_content_field" class="large-text" rows="15" readonly><?php echo esc_textarea( $sandbox_content ); ?></textarea>
				</div>

				<div class="virastai-acr-field-group virastai-acr-excerpt-group" <?php echo empty( $sandbox_excerpt ) ? 'style="display:none;"' : ''; ?>>
					<label for="virastai_acr_sandbox_excerpt_field">
						<?php esc_html_e( 'Generated Excerpt / Short Description:', 'virastai-auto-content-rewrite-with-ai' ); ?>
					</label>
					<textarea id="virastai_acr_sandbox_excerpt_field" class="large-text" rows="5" readonly><?php echo esc_textarea( $sandbox_excerpt ); ?></textarea>
				</div>

				<!-- Save and Apply Generations Button -->
				<div class="virastai-acr-metabox-apply-container" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e5e7eb; display: none;">
					<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-primary virastai-acr-apply-btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.15), 0 2px 4px -1px rgba(16, 185, 129, 0.1) !important;">
						<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Apply Generations', 'virastai-auto-content-rewrite-with-ai' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX request to regenerate content/title.
	 */
	public function handle_ajax_regenerate() {
		check_ajax_referer( 'virastai_acr_metabox_action', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$type    = isset( $_POST['regen_type'] ) ? sanitize_key( wp_unslash( $_POST['regen_type'] ) ) : '';

		if ( ! $post_id ) {
			wp_send_json_error( __( 'Invalid Post ID.', 'virastai-auto-content-rewrite-with-ai' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission denied.', 'virastai-auto-content-rewrite-with-ai' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( __( 'Post not found.', 'virastai-auto-content-rewrite-with-ai' ) );
		}

		$rewriter = new Virastai_Acr_Rewriter();

		if ( 'title' === $type ) {
			delete_post_meta( $post_id, '_virastai_acr_title_generated' );
		} elseif ( 'content' === $type ) {
			delete_post_meta( $post_id, '_virastai_acr_content_generated' );
		} elseif ( 'excerpt' === $type ) {
			delete_post_meta( $post_id, '_virastai_acr_excerpt_generated' );
		} elseif ( 'all' === $type ) {
			delete_post_meta( $post_id, '_virastai_acr_title_generated' );
			delete_post_meta( $post_id, '_virastai_acr_content_generated' );
			delete_post_meta( $post_id, '_virastai_acr_excerpt_generated' );
		}

		$result = $rewriter->virastai_acr_generate_content( $post_id, $post, $type );

		if ( $result ) {
			$new_title   = get_post_meta( $post_id, '_virastai_acr_sandbox_title', true );
			$new_content = get_post_meta( $post_id, '_virastai_acr_sandbox_content', true );
			$new_excerpt = get_post_meta( $post_id, '_virastai_acr_sandbox_excerpt', true );

			wp_send_json_success( array(
				'title'   => $new_title,
				'content' => $new_content,
				'excerpt' => $new_excerpt,
			) );
		} else {
			wp_send_json_error( __( 'Generation failed or no changes made. Check logs.', 'virastai-auto-content-rewrite-with-ai' ) );
		}
	}

	/**
	 * Handle AJAX request to clear sandbox content.
	 */
	public function handle_ajax_clear() {
		check_ajax_referer( 'virastai_acr_metabox_action', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( __( 'Invalid Post ID.', 'virastai-auto-content-rewrite-with-ai' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission denied.', 'virastai-auto-content-rewrite-with-ai' ) );
		}

		delete_post_meta( $post_id, '_virastai_acr_sandbox_title' );
		delete_post_meta( $post_id, '_virastai_acr_sandbox_content' );
		delete_post_meta( $post_id, '_virastai_acr_sandbox_excerpt' );

		// Clear completion history flags so statistics are updated/reset (excluding excerpt statistics).
		delete_post_meta( $post_id, '_virastai_acr_title_generated' );
		delete_post_meta( $post_id, '_virastai_acr_content_generated' );

		wp_send_json_success();
	}

	/**
	 * Handle AJAX request to apply sandbox content to the main post.
	 */
	public function handle_ajax_apply() {
		check_ajax_referer( 'virastai_acr_metabox_action', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( __( 'Invalid Post ID.', 'virastai-auto-content-rewrite-with-ai' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission denied.', 'virastai-auto-content-rewrite-with-ai' ) );
		}

		$sandbox_title   = get_post_meta( $post_id, '_virastai_acr_sandbox_title', true );
		$sandbox_content = get_post_meta( $post_id, '_virastai_acr_sandbox_content', true );
		$sandbox_excerpt = get_post_meta( $post_id, '_virastai_acr_sandbox_excerpt', true );

		if ( empty( $sandbox_title ) && empty( $sandbox_content ) && empty( $sandbox_excerpt ) ) {
			wp_send_json_error( __( 'No generated content found to apply.', 'virastai-auto-content-rewrite-with-ai' ) );
		}

		$post_data = array(
			'ID' => $post_id,
		);

		if ( ! empty( $sandbox_title ) ) {
			$post_data['post_title'] = $sandbox_title;
		}

		if ( ! empty( $sandbox_content ) ) {
			$post_data['post_content'] = $sandbox_content;
		}

		if ( ! empty( $sandbox_excerpt ) ) {
			$post_data['post_excerpt'] = $sandbox_excerpt;
		}

		$updated = wp_update_post( $post_data, true );

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( $updated->get_error_message() );
		}

		// After successfully applying, clear the sandbox variables
		delete_post_meta( $post_id, '_virastai_acr_sandbox_title' );
		delete_post_meta( $post_id, '_virastai_acr_sandbox_content' );
		delete_post_meta( $post_id, '_virastai_acr_sandbox_excerpt' );

		wp_send_json_success( __( 'Generations applied successfully.', 'virastai-auto-content-rewrite-with-ai' ) );
	}
}
