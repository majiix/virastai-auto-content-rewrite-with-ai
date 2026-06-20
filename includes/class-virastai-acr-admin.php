<?php
/**
 * Class Virastai_Acr_Admin
 *
 * Handles the admin settings page and asset loading.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Virastai_Acr_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'virastai_acr_add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'virastai_acr_register_settings' ) );
		add_action( 'admin_init', array( $this, 'virastai_acr_handle_reset_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'virastai_acr_enqueue_assets' ) );
	}

	/**
	 * Enqueue CSS and JS for the admin area.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function virastai_acr_enqueue_assets( $hook ) {
		if ( 'settings_page_virastai-auto-content-rewrite-with-ai' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'virastai-acr-admin-css',
			VIRASTAI_ACR_URL . 'assets/css/admin.css',
			array(),
			VIRASTAI_ACR_VERSION
		);

		wp_enqueue_script(
			'virastai-acr-admin-js',
			VIRASTAI_ACR_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			VIRASTAI_ACR_VERSION,
			true
		);

		// Localize script to pass translation strings to JS.
		wp_localize_script(
			'virastai-acr-admin-js',
			'virastaiData',
			array(
				'apiKeyEmpty'                => __( 'The OpenRouter API Key is empty. The plugin will not work without it. Do you want to continue?', 'virastai-auto-content-rewrite-with-ai' ),
				'confirmResetAll'            => __( 'This will reset ALL progress (Content & Titles). Are you sure?', 'virastai-auto-content-rewrite-with-ai' ),
				'confirmResetTitle'          => __( 'Reset Title progress only?', 'virastai-auto-content-rewrite-with-ai' ),
				'confirmResetContent'        => __( 'Reset Content progress only?', 'virastai-auto-content-rewrite-with-ai' ),
				'confirmClearSandboxTitle'   => __( 'Are you sure you want to delete ALL Sandbox Titles? This action cannot be undone.', 'virastai-auto-content-rewrite-with-ai' ),
				'confirmClearSandboxContent' => __( 'Are you sure you want to delete ALL Sandbox Content? This action cannot be undone.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);
	}

	/**
	 * Handle the Reset Progress Actions with CSRF protection.
	 */
	public function virastai_acr_handle_reset_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if any reset action was requested.
		$has_reset_action = isset( $_POST['virastai_acr_reset_progress'] ) ||
							isset( $_POST['virastai_acr_reset_title'] ) ||
							isset( $_POST['virastai_acr_reset_content'] ) ||
							isset( $_POST['virastai_acr_clear_sandbox_title'] ) ||
							isset( $_POST['virastai_acr_clear_sandbox_content'] );

		if ( $has_reset_action ) {
			if ( ! isset( $_POST['virastai_acr_reset_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['virastai_acr_reset_nonce'] ) ), 'virastai_acr_reset_action' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'virastai-auto-content-rewrite-with-ai' ) );
			}

			$message = '';

			// Reset ALL.
			if ( isset( $_POST['virastai_acr_reset_progress'] ) ) {
				$res1 = delete_metadata( 'post', 0, '_virastai_acr_content_generated', '', true );
				$res2 = delete_metadata( 'post', 0, '_virastai_acr_title_generated', '', true );
				$res3 = delete_metadata( 'post', 0, '_virastai_acr_sandbox_title', '', true );
				$res4 = delete_metadata( 'post', 0, '_virastai_acr_sandbox_content', '', true );

				// Clean transient statistics cache
				delete_transient( 'virastai_acr_stats_cache' );

				if ( $res1 || $res2 ) {
					$message = __( 'Complete reset! Content and Title progress markers removed. Sandbox data cleared.', 'virastai-auto-content-rewrite-with-ai' );
				} else {
					$message = __( 'No progress markers found to reset.', 'virastai-auto-content-rewrite-with-ai' );
				}
			}

			// Reset Title Only.
			if ( isset( $_POST['virastai_acr_reset_title'] ) ) {
				if ( delete_metadata( 'post', 0, '_virastai_acr_title_generated', '', true ) ) {
					delete_transient( 'virastai_acr_stats_cache' );
					$message = __( 'Title progress reset! Titles are marked for re-processing.', 'virastai-auto-content-rewrite-with-ai' );
				} else {
					$message = __( 'No Title progress markers found to reset.', 'virastai-auto-content-rewrite-with-ai' );
				}
			}

			// Reset Content Only.
			if ( isset( $_POST['virastai_acr_reset_content'] ) ) {
				if ( delete_metadata( 'post', 0, '_virastai_acr_content_generated', '', true ) ) {
					delete_transient( 'virastai_acr_stats_cache' );
					$message = __( 'Content progress reset! Posts are marked for content re-processing.', 'virastai-auto-content-rewrite-with-ai' );
				} else {
					$message = __( 'No Content progress markers found to reset.', 'virastai-auto-content-rewrite-with-ai' );
				}
			}

			// Clear Sandbox Title Only.
			if ( isset( $_POST['virastai_acr_clear_sandbox_title'] ) ) {
				if ( delete_metadata( 'post', 0, '_virastai_acr_sandbox_title', '', true ) ) {
					$message = __( 'Sandbox Titles cleared! The processing history was NOT reset.', 'virastai-auto-content-rewrite-with-ai' );
				} else {
					$message = __( 'No Sandbox Titles found to clear.', 'virastai-auto-content-rewrite-with-ai' );
				}
			}

			// Clear Sandbox Content Only.
			if ( isset( $_POST['virastai_acr_clear_sandbox_content'] ) ) {
				if ( delete_metadata( 'post', 0, '_virastai_acr_sandbox_content', '', true ) ) {
					$message = __( 'Sandbox Content cleared! The processing history was NOT reset.', 'virastai-auto-content-rewrite-with-ai' );
				} else {
					$message = __( 'No Sandbox Content found to clear.', 'virastai-auto-content-rewrite-with-ai' );
				}
			}

			if ( ! empty( $message ) ) {
				add_settings_error( 'virastai_acr_messages', 'virastai_acr_reset_success', $message, 'success' );
			}
		}
	}

	/**
	 * Add options page to the Settings menu.
	 */
	public function virastai_acr_add_settings_page() {
		add_options_page(
			__( 'Virastai Auto Content Rewrite', 'virastai-auto-content-rewrite-with-ai' ),
			__( 'Virastai Rewrite', 'virastai-auto-content-rewrite-with-ai' ),
			'manage_options',
			'virastai-auto-content-rewrite-with-ai',
			array( $this, 'virastai_acr_render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function virastai_acr_register_settings() {
		// API & System.
		register_setting( 'virastai_acr_options_group', 'virastai_acr_openrouter_api_key', 'sanitize_text_field' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_ai_model', 'sanitize_text_field' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_enable_logging', 'absint' );

		// Automation.
		register_setting( 'virastai_acr_options_group', 'virastai_acr_cron_enabled', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_cron_interval', 'sanitize_text_field' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_cron_batch_size', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_allowed_post_types', array( $this, 'virastai_acr_sanitize_array' ) );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_allowed_post_statuses', array( $this, 'virastai_acr_sanitize_array' ) );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_enable_sandbox_mode', 'absint' );

		// Prompts (General).
		register_setting( 'virastai_acr_options_group', 'virastai_acr_base_prompt_template', 'sanitize_textarea_field' );

		// Content Options.
		register_setting( 'virastai_acr_options_group', 'virastai_acr_enable_content_rewrite', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_content_skip_incomplete', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_preserve_images', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_enable_formatting', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_prompt_template', 'sanitize_textarea_field' );

		// Title Options.
		register_setting( 'virastai_acr_options_group', 'virastai_acr_enable_title_rewrite', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_title_skip_incomplete', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_remove_title_html', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_title_prompt_template', 'sanitize_textarea_field' );

		// --- Section: API ---
		add_settings_section( 'virastai_acr_api_section', __( 'API Configuration', 'virastai-auto-content-rewrite-with-ai' ), null, 'virastai-auto-content-rewrite-with-ai' );

		add_settings_field(
			'virastai_acr_openrouter_api_key',
			__( 'OpenRouter API Key', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_input' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_api_section',
			array(
				'label_for'   => 'virastai_acr_openrouter_api_key',
				'placeholder' => 'sk-or-v1-...',
			)
		);

		add_settings_field(
			'virastai_acr_ai_model',
			__( 'AI Model ID', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_input' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_api_section',
			array(
				'label_for'   => 'virastai_acr_ai_model',
				'description' => __( 'E.g., google/gemini-2.0-flash-lite-preview-02-05:free', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_enable_logging',
			__( 'Enable Logging', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_api_section',
			array(
				'label_for'   => 'virastai_acr_enable_logging',
				'description' => __( 'Log external endpoint API errors and activities to the WordPress debug log.', 'virastai-auto-content-rewrite-with-ai' ),
				'default'     => 0,
			)
		);

		// --- Section: Base Context ---
		add_settings_section( 'virastai_acr_base_prompt_section', __( 'Base Prompt (Context)', 'virastai-auto-content-rewrite-with-ai' ), null, 'virastai-auto-content-rewrite-with-ai' );
		add_settings_field(
			'virastai_acr_base_prompt_template',
			__( 'Base Context', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_textarea' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_base_prompt_section',
			array(
				'label_for'     => 'virastai_acr_base_prompt_template',
				'default_value' => "Product: {product_title}\n\nContent: {post_content}",
				/* translators: 1: placeholder for product title, 2: placeholder for post content */
				'description'   => sprintf( __( 'This context is sent to the API endpoint for every request. Place your placeholders here: %1$s, %2$s', 'virastai-auto-content-rewrite-with-ai' ), '<code>{product_title}</code>', '<code>{post_content}</code>' ),
			)
		);

		// --- Section: Automation ---
		add_settings_section( 'virastai_acr_automation_section', __( 'Automation Settings', 'virastai-auto-content-rewrite-with-ai' ), null, 'virastai-auto-content-rewrite-with-ai' );

		add_settings_field(
			'virastai_acr_cron_enabled',
			__( 'Enable Automation', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_automation_section',
			array(
				'label_for'   => 'virastai_acr_cron_enabled',
				'default'     => 0,
				'description' => __( 'Process content automatically in the background.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field( 'virastai_acr_cron_interval', __( 'Run Frequency', 'virastai-auto-content-rewrite-with-ai' ), array( $this, 'virastai_acr_render_field_select_interval' ), 'virastai-auto-content-rewrite-with-ai', 'virastai_acr_automation_section', array( 'label_for' => 'virastai_acr_cron_interval' ) );

		add_settings_field(
			'virastai_acr_cron_batch_size',
			__( 'Items per Batch', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_number' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_automation_section',
			array(
				'label_for'   => 'virastai_acr_cron_batch_size',
				'description' => __( 'How many posts to rewrite per run.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field( 'virastai_acr_allowed_post_types', __( 'Post Types', 'virastai-auto-content-rewrite-with-ai' ), array( $this, 'virastai_acr_render_field_post_types' ), 'virastai-auto-content-rewrite-with-ai', 'virastai_acr_automation_section', array( 'label_for' => 'virastai_acr_allowed_post_types' ) );
		add_settings_field( 'virastai_acr_allowed_post_statuses', __( 'Post Statuses', 'virastai-auto-content-rewrite-with-ai' ), array( $this, 'virastai_acr_render_field_post_statuses' ), 'virastai-auto-content-rewrite-with-ai', 'virastai_acr_automation_section', array( 'label_for' => 'virastai_acr_allowed_post_statuses' ) );

		add_settings_field(
			'virastai_acr_enable_sandbox_mode',
			__( 'Enable Sandbox Mode', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_automation_section',
			array(
				'label_for'   => 'virastai_acr_enable_sandbox_mode',
				'default'     => 0,
				'description' => __( 'Safe Mode: Saves generated content to a custom field instead of overwriting live posts. Review changes in the post edit screen.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		// --- Section: Content Options ---
		add_settings_section( 'virastai_acr_content_section', __( 'Content (Body) Options', 'virastai-auto-content-rewrite-with-ai' ), null, 'virastai-auto-content-rewrite-with-ai' );

		add_settings_field(
			'virastai_acr_enable_content_rewrite',
			__( 'Rewrite Content', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_content_section',
			array(
				'label_for'   => 'virastai_acr_enable_content_rewrite',
				'description' => __( 'Enable rewriting for the post body/content.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_content_skip_incomplete',
			__( 'Skip Incomplete Posts', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_content_section',
			array(
				'label_for'   => 'virastai_acr_content_skip_incomplete',
				'description' => __( 'Do not process if current Body content is empty.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_preserve_images',
			__( 'Preserve Images', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_content_section',
			array(
				'label_for'   => 'virastai_acr_preserve_images',
				'description' => __( 'Keep existing images and append them to the end of the new content.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_enable_formatting',
			__( 'Enable Formatting', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_content_section',
			array(
				'label_for'   => 'virastai_acr_enable_formatting',
				'description' => __( 'Convert Markdown syntax to clean HTML tags.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_prompt_template',
			__( 'Content Instruction', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_textarea' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_content_section',
			array(
				'label_for'     => 'virastai_acr_prompt_template',
				'default_value' => 'Rewrite the content based on the context above.',
				'description'   => __( 'Instructions for the API endpoint. This is appended to the Base Context.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		// --- Section: Title Options ---
		add_settings_section( 'virastai_acr_title_section', __( 'Title Options', 'virastai-auto-content-rewrite-with-ai' ), null, 'virastai-auto-content-rewrite-with-ai' );

		add_settings_field(
			'virastai_acr_enable_title_rewrite',
			__( 'Rewrite Title', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_title_section',
			array(
				'label_for'   => 'virastai_acr_enable_title_rewrite',
				'default'     => 0,
				'description' => __( 'Enable rewriting for the post title.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_title_skip_incomplete',
			__( 'Skip Incomplete Titles', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_title_section',
			array(
				'label_for'   => 'virastai_acr_title_skip_incomplete',
				'description' => __( 'Do not process if current Title is empty.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_remove_title_html',
			__( 'Clean Title HTML', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_title_section',
			array(
				'label_for'   => 'virastai_acr_remove_title_html',
				'description' => __( 'Remove all HTML and markdown formatting from the generated title.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_title_prompt_template',
			__( 'Title Instruction', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_textarea' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_title_section',
			array(
				'label_for'     => 'virastai_acr_title_prompt_template',
				'default_value' => 'Generate a catchy product title based on the context above.',
				'description'   => __( 'Instructions for the API endpoint. This is appended to the Base Context.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);
	}

	/**
	 * Sanitize array input.
	 */
	public function virastai_acr_sanitize_array( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		return array_map( 'sanitize_text_field', $input );
	}

	/**
	 * Render Toggle Switch Checkbox.
	 */
	public function virastai_acr_render_field_checkbox_toggle( $args ) {
		$default = isset( $args['default'] ) ? $args['default'] : 1;
		$option  = Virastai_Acr_Rewriter::get_option_migrated( $args['label_for'], str_replace( 'virastai_acr_', 'jixcrai_', $args['label_for'] ), $default );
		$desc    = isset( $args['description'] ) ? $args['description'] : '';
		?>
		<label class="virastai-acr-switch">
			<input type="checkbox" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="1" <?php checked( 1, $option ); ?> />
			<span class="virastai-acr-slider round"></span>
		</label>
		<p class="description virastai-acr-toggle-description"><?php echo wp_kses_post( $desc ); ?></p>
		<?php
	}

	/**
	 * Render Interval Select.
	 */
	public function virastai_acr_render_field_select_interval( $args ) {
		$option = Virastai_Acr_Rewriter::get_option_migrated( $args['label_for'], str_replace( 'virastai_acr_', 'jixcrai_', $args['label_for'] ), 'hourly' );
		$intervals = array(
			'1min'       => __( 'Every 1 Minute', 'virastai-auto-content-rewrite-with-ai' ),
			'5min'       => __( 'Every 5 Minutes', 'virastai-auto-content-rewrite-with-ai' ),
			'15min'      => __( 'Every 15 Minutes', 'virastai-auto-content-rewrite-with-ai' ),
			'30min'      => __( 'Every 30 Minutes', 'virastai-auto-content-rewrite-with-ai' ),
			'hourly'     => __( 'Hourly', 'virastai-auto-content-rewrite-with-ai' ),
			'twicedaily' => __( 'Twice Daily', 'virastai-auto-content-rewrite-with-ai' ),
			'daily'      => __( 'Daily', 'virastai-auto-content-rewrite-with-ai' ),
		);
		?>
		<div class="virastai-acr-select-wrapper">
			<select name="<?php echo esc_attr( $args['label_for'] ); ?>">
				<?php foreach ( $intervals as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $option, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/**
	 * Render Post Types Checkboxes.
	 */
	public function virastai_acr_render_field_post_types() {
		$post_types  = get_post_types( array( 'public' => true ), 'objects' );
		$saved_types = Virastai_Acr_Rewriter::get_option_migrated( 'virastai_acr_allowed_post_types', 'jixcrai_allowed_post_types', array( 'product' ) );

		if ( ! is_array( $saved_types ) ) {
			$saved_types = array();
		}

		echo '<div class="virastai-acr-checkbox-grid">';
		foreach ( $post_types as $pt ) {
			if ( 'attachment' === $pt->name ) {
				continue;
			}
			?>
			<label class="virastai-acr-checkbox-card <?php echo in_array( $pt->name, $saved_types, true ) ? 'active' : ''; ?>">
				<input type="checkbox" name="virastai_acr_allowed_post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $saved_types, true ) ); ?>>
				<span class="virastai-acr-pt-label"><?php echo esc_html( $pt->label ); ?></span>
				<span class="virastai-acr-pt-name"><?php echo esc_html( $pt->name ); ?></span>
			</label>
			<?php
		}
		echo '</div>';
	}

	/**
	 * Render Post Statuses.
	 */
	public function virastai_acr_render_field_post_statuses() {
		$target_statuses = array( 'publish', 'draft' );
		$saved_statuses  = Virastai_Acr_Rewriter::get_option_migrated( 'virastai_acr_allowed_post_statuses', 'jixcrai_allowed_post_statuses', array( 'publish' ) );

		if ( ! is_array( $saved_statuses ) ) {
			$saved_statuses = array();
		}

		echo '<div class="virastai-acr-checkbox-grid">';
		foreach ( $target_statuses as $status_slug ) {
			$status_obj = get_post_status_object( $status_slug );
			if ( ! $status_obj ) {
				continue;
			}
			?>
			<label class="virastai-acr-checkbox-card <?php echo in_array( $status_obj->name, $saved_statuses, true ) ? 'active' : ''; ?>">
				<input type="checkbox" name="virastai_acr_allowed_post_statuses[]" value="<?php echo esc_attr( $status_obj->name ); ?>" <?php checked( in_array( $status_obj->name, $saved_statuses, true ) ); ?>>
				<span class="virastai-acr-pt-label"><?php echo esc_html( $status_obj->label ); ?></span>
				<span class="virastai-acr-pt-name"><?php echo esc_html( $status_obj->name ); ?></span>
			</label>
			<?php
		}
		echo '</div>';
	}

	/**
	 * Render text input.
	 */
	public function virastai_acr_render_field_input( $args ) {
		$option      = Virastai_Acr_Rewriter::get_option_migrated( $args['label_for'], str_replace( 'virastai_acr_', 'jixcrai_', $args['label_for'] ) );
		$desc        = isset( $args['description'] ) ? $args['description'] : '';
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		?>
		<input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="<?php echo esc_attr( $option ); ?>" class="regular-text virastai-acr-input" placeholder="<?php echo esc_attr( $placeholder ); ?>">
		<?php if ( $desc ) : ?>
			<p class="description"><?php echo wp_kses_post( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render number input.
	 */
	public function virastai_acr_render_field_number( $args ) {
		$option = Virastai_Acr_Rewriter::get_option_migrated( $args['label_for'], str_replace( 'virastai_acr_', 'jixcrai_', $args['label_for'] ), 5 );
		$desc   = isset( $args['description'] ) ? $args['description'] : '';
		?>
		<input type="number" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="<?php echo esc_attr( $option ); ?>" class="small-text virastai-acr-input" min="1" max="50">
		<?php if ( $desc ) : ?>
			<p class="description"><?php echo wp_kses_post( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render textarea.
	 */
	public function virastai_acr_render_field_textarea( $args ) {
		$default = isset( $args['default_value'] ) ? $args['default_value'] : '';
		// Fix legacy option lookup
		$legacy_key = str_replace( 'virastai_acr_', 'jixcrai_', $args['label_for'] );
		if ( 'virastai_acr_title_skip_incomplete' === $args['label_for'] ) {
			$legacy_key = 'jixcrai_skip_incomplete';
		}
		$option = Virastai_Acr_Rewriter::get_option_migrated( $args['label_for'], $legacy_key, $default );

		if ( 'virastai_acr_base_prompt_template' === $args['label_for'] && empty( $option ) ) {
			$option = $default;
		}
		?>
		<textarea id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" rows="5" class="large-text code virastai-acr-textarea"><?php echo esc_textarea( $option ); ?></textarea>
		<?php if ( $desc = ( isset( $args['description'] ) ? $args['description'] : '' ) ) : ?>
			<p class="description"><?php echo wp_kses_post( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the settings page HTML.
	 */
	public function virastai_acr_render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap virastai-acr-settings-wrapper">
			<div class="virastai-acr-header">
				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			</div>

			<div class="virastai-acr-layout">
				<!-- Main Settings -->
				<div class="virastai-acr-main">
					<div class="virastai-acr-container">
						<form action="options.php" method="post" id="virastai-acr-settings-form">
							<?php
							settings_fields( 'virastai_acr_options_group' );
							do_settings_sections( 'virastai-auto-content-rewrite-with-ai' );
							?>
							<div class="virastai-acr-footer-actions">
								<?php submit_button( __( 'Save Settings', 'virastai-auto-content-rewrite-with-ai' ), 'primary large', 'submit', false ); ?>
							</div>
						</form>
					</div>
				</div>

				<!-- Sidebar (Loaded from external class) -->
				<?php Virastai_Acr_Admin_Sidebar::render(); ?>
			</div>
		</div>
		<?php
	}
}
