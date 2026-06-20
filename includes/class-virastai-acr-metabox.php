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
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'virastai_acr_metabox_action' ),
				'postId'       => $post->ID,
				'confirmClear' => __( 'Are you sure you want to clear the generated sandbox content?', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);
	}

	/**
	 * Register the metabox for allowed post types.
	 */
	public function virastai_acr_add_sandbox_metabox() {
		$sandbox_enabled = Virastai_Acr_Rewriter::get_option_migrated( 'virastai_acr_enable_sandbox_mode', 'jixcrai_enable_sandbox_mode', 0 );
		if ( ! $sandbox_enabled ) {
			return;
		}

		$allowed_post_types = Virastai_Acr_Rewriter::get_option_migrated( 'virastai_acr_allowed_post_types', 'jixcrai_allowed_post_types', array( 'product' ) );
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
		$sandbox_title   = Virastai_Acr_Rewriter::get_post_meta_migrated( $post->ID, '_virastai_acr_sandbox_title', '_jixcrai_sandbox_title' );
		$sandbox_content = Virastai_Acr_Rewriter::get_post_meta_migrated( $post->ID, '_virastai_acr_sandbox_content', '_jixcrai_sandbox_content' );

		$enable_title_rewrite   = Virastai_Acr_Rewriter::get_option_migrated( 'virastai_acr_enable_title_rewrite', 'jixcrai_enable_title_rewrite', 0 );
		$enable_content_rewrite = Virastai_Acr_Rewriter::get_option_migrated( 'virastai_acr_enable_content_rewrite', 'jixcrai_enable_content_rewrite', 1 );

		?>
		<div class="virastai-acr-metabox-wrapper" id="virastai-acr-metabox-container">

			<div class="virastai-acr-metabox-actions">
				<?php if ( $enable_content_rewrite ) : ?>
					<button type="button" class="button button-small virastai-acr-regen-btn" data-type="content">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Regenerate Content', 'virastai-auto-content-rewrite-with-ai' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( $enable_title_rewrite ) : ?>
					<button type="button" class="button button-small virastai-acr-regen-btn" data-type="title">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Regenerate Title', 'virastai-auto-content-rewrite-with-ai' ); ?>
					</button>
				<?php endif; ?>

				<button type="button" class="button button-small button-link-delete virastai-acr-clear-btn" style="float:right;">
					<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear Generated', 'virastai-auto-content-rewrite-with-ai' ); ?>
				</button>
				<div style="clear:both;"></div>
				<div class="virastai-acr-loader" style="display:none; margin-top:10px;">
					<span class="spinner is-active" style="float:none; margin:0;"></span> <?php esc_html_e( 'Processing...', 'virastai-auto-content-rewrite-with-ai' ); ?>
				</div>
			</div>

			<hr style="margin: 15px 0;">

			<?php if ( empty( $sandbox_title ) && empty( $sandbox_content ) ) : ?>
				<p class="virastai-acr-empty-msg"><?php esc_html_e( 'No content generated yet.', 'virastai-auto-content-rewrite-with-ai' ); ?></p>
			<?php endif; ?>

			<div class="virastai-acr-sandbox-fields" <?php echo ( empty( $sandbox_title ) && empty( $sandbox_content ) ) ? 'style="display:none;"' : ''; ?>>
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
		}

		$result = $rewriter->virastai_acr_generate_content( $post_id, $post );

		if ( $result ) {
			$new_title   = get_post_meta( $post_id, '_virastai_acr_sandbox_title', true );
			$new_content = get_post_meta( $post_id, '_virastai_acr_sandbox_content', true );

			// Clear stats transients on updates
			Virastai_Acr_Admin_Sidebar::delete_stats_transient();

			wp_send_json_success( array(
				'title'   => $new_title,
				'content' => $new_content,
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

		wp_send_json_success();
	}
}
