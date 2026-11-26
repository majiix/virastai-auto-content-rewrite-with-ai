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
	 * Custom Automation instance.
	 *
	 * @var Virastai_Acr_Custom_Automation|null
	 */
	private $custom_automation;

	/**
	 * Constructor.
	 *
	 * @param Virastai_Acr_Custom_Automation|null $custom_automation Custom automation instance.
	 */
	public function __construct( $custom_automation = null ) {
		$this->custom_automation = $custom_automation;

		add_action( 'admin_menu', array( $this, 'virastai_acr_add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'virastai_acr_register_settings' ) );
		add_action( 'admin_init', array( $this, 'virastai_acr_handle_reset_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'virastai_acr_enqueue_assets' ) );

		// AJAX Action
		add_action( 'wp_ajax_virastai_acr_refresh_logs', array( $this, 'handle_ajax_refresh_logs' ) );
		add_action( 'wp_ajax_virastai_acr_fetch_models', array( $this, 'handle_ajax_fetch_models' ) );
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

		// Build per-provider metadata for client-side switching.
		$provider_configs = array();
		foreach ( Virastai_Acr_Providers::get_providers() as $slug => $cfg ) {
			$provider_configs[ $slug ] = array(
				'label'         => $cfg['label'],
				'placeholder'   => $cfg['placeholder'],
				'requiresKey'   => ! empty( $cfg['requires_key'] ),
				'extraFields'   => isset( $cfg['extra_fields'] ) ? $cfg['extra_fields'] : array(),
				'savedKey'      => Virastai_Acr_Providers::get_api_key( $slug ),
				'savedModel'    => Virastai_Acr_Providers::get_model( $slug ),
				'curatedModels' => isset( $cfg['curated_models'] ) ? $cfg['curated_models'] : array(),
			);
		}

		// Localize script to pass translation strings to JS.
		wp_localize_script(
			'virastai-acr-admin-js',
			'virastaiData',
			array(
				'apiKeyEmpty'                => __( 'The API Key for the selected provider is empty. The plugin will not work without it. Do you want to continue?', 'virastai-auto-content-rewrite-with-ai' ),
				'confirmResetAll'            => __( 'This will reset ALL progress (Content & Titles). Are you sure?', 'virastai-auto-content-rewrite-with-ai' ),
				'confirmResetTitle'          => __( 'Reset Title progress only?', 'virastai-auto-content-rewrite-with-ai' ),
				'confirmResetContent'        => __( 'Reset Content progress only?', 'virastai-auto-content-rewrite-with-ai' ),
				'confirmClearSandboxTitle'   => __( 'Are you sure you want to delete ALL Sandbox Titles? This action cannot be undone.', 'virastai-auto-content-rewrite-with-ai' ),
				'confirmClearSandboxContent' => __( 'Are you sure you want to delete ALL Sandbox Content? This action cannot be undone.', 'virastai-auto-content-rewrite-with-ai' ),
				'refreshingModels'           => __( 'Refreshing...', 'virastai-auto-content-rewrite-with-ai' ),
				'refreshModelsLabel'         => __( 'Refresh Models', 'virastai-auto-content-rewrite-with-ai' ),
				'providerConfigs'            => $provider_configs,
				'ajaxUrl'                    => admin_url( 'admin-ajax.php' ),
				'nonce'                      => wp_create_nonce( 'virastai_acr_admin_action' ),
			)
		);
	}

	/**
	 * Handle AJAX request to refresh system logs.
	 */
	public function handle_ajax_refresh_logs() {
		check_ajax_referer( 'virastai_acr_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'virastai-auto-content-rewrite-with-ai' ) );
		}

		$paged = isset( $_POST['paged'] ) ? max( 1, intval( $_POST['paged'] ) ) : 1;
		$limit = 20;
		$offset = ( $paged - 1 ) * $limit;

		if ( class_exists( 'Virastai_Acr_Logger' ) ) {
			$logs = Virastai_Acr_Logger::get_logs( $limit, $offset );
			$total_logs = Virastai_Acr_Logger::count_logs();
			$total_pages = ceil( $total_logs / $limit );

			// Build rows HTML
			$rows_html = '';
			if ( empty( $logs ) ) {
				$rows_html .= '<tr><td colspan="4" class="virastai-acr-no-logs">' . esc_html__( 'No logs recorded yet.', 'virastai-auto-content-rewrite-with-ai' ) . '</td></tr>';
			} else {
				foreach ( $logs as $log ) {
					$type_class = 'type-' . esc_attr( strtolower( $log['type'] ) );
					$rows_html .= '<tr class="virastai-acr-log-row" data-id="' . intval( $log['id'] ) . '">';
					$rows_html .= '<td class="col-date">' . esc_html( $log['created_at'] ) . '</td>';
					$rows_html .= '<td class="col-type"><span class="log-badge ' . $type_class . '">' . esc_html( strtoupper( $log['type'] ) ) . '</span></td>';
					
					// Message details
					$message = $log['message'];
					$is_long = strlen( $message ) > 120;
					$preview = $is_long ? substr( $message, 0, 120 ) . '...' : $message;
					
					$rows_html .= '<td class="col-message">';
					$rows_html .= '<div class="log-preview">' . esc_html( $preview ) . '</div>';
					if ( $is_long ) {
						$rows_html .= '<div class="log-details" style="display:none;"><pre>' . esc_html( $message ) . '</pre></div>';
					}
					$rows_html .= '</td>';
					
					$rows_html .= '<td class="col-actions">';
					if ( $is_long ) {
						$rows_html .= '<button type="button" class="button button-small virastai-acr-toggle-log-btn">' . esc_html__( 'View Details', 'virastai-auto-content-rewrite-with-ai' ) . '</button>';
					}
					$rows_html .= '</td>';
					$rows_html .= '</tr>';
				}
			}

			// Build pagination HTML
			$pagination_html = '';
			if ( $total_pages > 1 ) {
				$pagination_html .= '<div class="virastai-acr-pagination">';
				if ( $paged > 1 ) {
					$pagination_html .= '<a href="#" class="page-numbers prev" data-page="' . ( $paged - 1 ) . '">&laquo; ' . esc_html__( 'Prev', 'virastai-auto-content-rewrite-with-ai' ) . '</a>';
				}
				
				$start = max( 1, $paged - 2 );
				$end = min( $total_pages, $paged + 2 );
				for ( $i = $start; $i <= $end; $i++ ) {
					$active_class = ( $i === $paged ) ? 'current' : '';
					$pagination_html .= '<a href="#" class="page-numbers ' . $active_class . '" data-page="' . $i . '">' . $i . '</a>';
				}

				if ( $paged < $total_pages ) {
					$pagination_html .= '<a href="#" class="page-numbers next" data-page="' . ( $paged + 1 ) . '">' . esc_html__( 'Next', 'virastai-auto-content-rewrite-with-ai' ) . ' &raquo;</a>';
				}
				$pagination_html .= '</div>';
			}

			wp_send_json_success( array(
				'rows_html'       => $rows_html,
				'pagination_html' => $pagination_html,
				'raw_logs'        => Virastai_Acr_Logger::get_logs_formatted(),
			) );
		} else {
			wp_send_json_error( __( 'Logger class is not available.', 'virastai-auto-content-rewrite-with-ai' ) );
		}
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
							isset( $_POST['virastai_acr_reset_excerpt'] ) ||
							isset( $_POST['virastai_acr_clear_sandbox_title'] ) ||
							isset( $_POST['virastai_acr_clear_sandbox_content'] ) ||
							isset( $_POST['virastai_acr_clear_sandbox_excerpt'] ) ||
							isset( $_POST['virastai_acr_clear_logs'] );

		if ( $has_reset_action ) {
			if ( ! isset( $_POST['virastai_acr_reset_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['virastai_acr_reset_nonce'] ) ), 'virastai_acr_reset_action' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'virastai-auto-content-rewrite-with-ai' ) );
			}

			$message = '';

			// Reset ALL.
			if ( isset( $_POST['virastai_acr_reset_progress'] ) ) {
				$res1 = delete_metadata( 'post', 0, '_virastai_acr_content_generated', '', true );
				$res2 = delete_metadata( 'post', 0, '_virastai_acr_title_generated', '', true );
				$res3 = delete_metadata( 'post', 0, '_virastai_acr_excerpt_generated', '', true );
				$res4 = delete_metadata( 'post', 0, '_virastai_acr_sandbox_title', '', true );
				$res5 = delete_metadata( 'post', 0, '_virastai_acr_sandbox_content', '', true );
				$res6 = delete_metadata( 'post', 0, '_virastai_acr_sandbox_excerpt', '', true );
				delete_metadata( 'post', 0, '_virastai_acr_retry_count', '', true );

				if ( $res1 || $res2 || $res3 ) {
					$message = __( 'Complete reset! Content, Title, and Excerpt progress markers removed. Sandbox data cleared.', 'virastai-auto-content-rewrite-with-ai' );
				} else {
					$message = __( 'No progress markers found to reset.', 'virastai-auto-content-rewrite-with-ai' );
				}
			}

			// Reset Title Only.
			if ( isset( $_POST['virastai_acr_reset_title'] ) ) {
				if ( delete_metadata( 'post', 0, '_virastai_acr_title_generated', '', true ) ) {
					$message = __( 'Title progress reset! Titles are marked for re-processing.', 'virastai-auto-content-rewrite-with-ai' );
				} else {
					$message = __( 'No Title progress markers found to reset.', 'virastai-auto-content-rewrite-with-ai' );
				}
			}

			// Reset Content Only.
			if ( isset( $_POST['virastai_acr_reset_content'] ) ) {
				if ( delete_metadata( 'post', 0, '_virastai_acr_content_generated', '', true ) ) {
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

			// Reset Excerpt Only.
			if ( isset( $_POST['virastai_acr_reset_excerpt'] ) ) {
				if ( delete_metadata( 'post', 0, '_virastai_acr_excerpt_generated', '', true ) ) {
					$message = __( 'Excerpt progress reset! Excerpts are marked for re-processing.', 'virastai-auto-content-rewrite-with-ai' );
				} else {
					$message = __( 'No Excerpt progress markers found to reset.', 'virastai-auto-content-rewrite-with-ai' );
				}
			}

			// Clear Sandbox Excerpt Only.
			if ( isset( $_POST['virastai_acr_clear_sandbox_excerpt'] ) ) {
				if ( delete_metadata( 'post', 0, '_virastai_acr_sandbox_excerpt', '', true ) ) {
					$message = __( 'Sandbox Excerpts cleared! The processing history was NOT reset.', 'virastai-auto-content-rewrite-with-ai' );
				} else {
					$message = __( 'No Sandbox Excerpts found to clear.', 'virastai-auto-content-rewrite-with-ai' );
				}
			}

			// Clear Logs.
			if ( isset( $_POST['virastai_acr_clear_logs'] ) ) {
				if ( class_exists( 'Virastai_Acr_Logger' ) ) {
					Virastai_Acr_Logger::clear_logs();
					$message = __( 'System logs cleared successfully.', 'virastai-auto-content-rewrite-with-ai' );
				} else {
					$message = __( 'Logger class is not available.', 'virastai-auto-content-rewrite-with-ai' );
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
		register_setting( 'virastai_acr_options_group', 'virastai_acr_ai_provider', 'sanitize_key' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_provider_api_keys', array( $this, 'virastai_acr_sanitize_provider_api_keys' ) );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_provider_models', array( $this, 'virastai_acr_sanitize_provider_models' ) );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_custom_base_url', array( $this, 'virastai_acr_sanitize_custom_base_url' ) );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_cloudflare_account_id', 'sanitize_text_field' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_openrouter_api_key', 'sanitize_text_field' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_ai_model', 'sanitize_text_field' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_enable_logging', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_log_retention', 'absint' );

		// Automation.
		register_setting( 'virastai_acr_options_group', 'virastai_acr_cron_enabled', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_cron_interval', 'sanitize_text_field' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_cron_batch_size', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_allowed_post_types', array( $this, 'virastai_acr_sanitize_array' ) );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_allowed_post_statuses', array( $this, 'virastai_acr_sanitize_array' ) );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_enable_sandbox_mode', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_allowed_roles', array( $this, 'virastai_acr_sanitize_array' ) );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_delete_on_uninstall', 'absint' );

		// Prompts (General).
		register_setting( 'virastai_acr_options_group', 'virastai_acr_base_prompt_template', 'sanitize_textarea_field' );

		// Content Options.
		register_setting( 'virastai_acr_options_group', 'virastai_acr_enable_content_rewrite', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_content_skip_incomplete', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_preserve_images', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_enable_formatting', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_prompt_template', 'sanitize_textarea_field' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_enable_excerpt_rewrite', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_excerpt_skip_incomplete', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_excerpt_prompt_template', 'sanitize_textarea_field' );

		// Title Options.
		register_setting( 'virastai_acr_options_group', 'virastai_acr_enable_title_rewrite', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_title_skip_incomplete', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_remove_title_html', 'absint' );
		register_setting( 'virastai_acr_options_group', 'virastai_acr_title_prompt_template', 'sanitize_textarea_field' );

		// --- Section: API ---
		add_settings_section( 'virastai_acr_api_section', __( 'General', 'virastai-auto-content-rewrite-with-ai' ), null, 'virastai-auto-content-rewrite-with-ai' );

		$active_provider_id = Virastai_Acr_Providers::get_active_provider_id();
		$active_provider    = Virastai_Acr_Providers::get_provider( $active_provider_id );

		add_settings_field(
			'virastai_acr_ai_provider',
			__( 'AI Provider', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_provider_select' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_api_section',
			array(
				'label_for'   => 'virastai_acr_ai_provider',
				'description' => __( 'Select the AI provider to use for content rewriting. API keys and models are saved per provider.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_provider_api_key',
			__( 'API Key', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_provider_api_key' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_api_section',
			array(
				'label_for'   => 'virastai_acr_provider_api_key',
				'placeholder' => $active_provider['placeholder'],
				'description' => __( 'Enter the API key for the selected provider. Optional for local Ollama.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_custom_base_url',
			__( 'Custom / Ollama Base URL', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_custom_base_url' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_api_section',
			array(
				'label_for'   => 'virastai_acr_custom_base_url',
				'class'       => 'virastai-acr-provider-field virastai-acr-provider-custom_ollama' . ( 'custom_ollama' === $active_provider_id ? '' : ' virastai-acr-hidden' ),
				'placeholder' => 'http://localhost:11434/v1',
				'description' => __( 'Enter an HTTPS OpenAI-compatible base URL or a local Ollama URL (e.g. http://localhost:11434/v1).', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_cloudflare_account_id',
			__( 'Cloudflare Account ID', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_cloudflare_account_id' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_api_section',
			array(
				'label_for'   => 'virastai_acr_cloudflare_account_id',
				'class'       => 'virastai-acr-provider-field virastai-acr-provider-cloudflare' . ( 'cloudflare' === $active_provider_id ? '' : ' virastai-acr-hidden' ),
				'placeholder' => 'your-cloudflare-account-id',
				'description' => __( 'Required for Cloudflare Workers AI endpoints.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_ai_model',
			__( 'Model ID', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_model_select' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_api_section',
			array(
				'label_for'   => 'virastai_acr_ai_model',
				'description' => __( 'Choose a model from the list, refresh from the provider API, or enter a custom Model ID below.', 'virastai-auto-content-rewrite-with-ai' ),
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
				'default_value' => "Title: {post_title}\n\nContent: {post_content}",
				/* translators: 1: placeholder for post title, 2: placeholder for post content */
				'description'   => sprintf( __( 'This context is sent to the API endpoint for every request. Place your placeholders here: %1$s, %2$s', 'virastai-auto-content-rewrite-with-ai' ), '<code>{post_title}</code>', '<code>{post_content}</code>' ),
			)
		);

		// --- Section: Automation ---
		add_settings_section( 'virastai_acr_automation_section', __( 'Automation', 'virastai-auto-content-rewrite-with-ai' ), null, 'virastai-auto-content-rewrite-with-ai' );

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

		add_settings_field( 'virastai_acr_allowed_post_types', __( 'Post Types', 'virastai-auto-content-rewrite-with-ai' ), array( $this, 'virastai_acr_render_field_post_types' ), 'virastai-auto-content-rewrite-with-ai', 'virastai_acr_api_section', array( 'label_for' => 'virastai_acr_allowed_post_types' ) );
		add_settings_field( 'virastai_acr_allowed_post_statuses', __( 'Post Statuses', 'virastai-auto-content-rewrite-with-ai' ), array( $this, 'virastai_acr_render_field_post_statuses' ), 'virastai-auto-content-rewrite-with-ai', 'virastai_acr_api_section', array( 'label_for' => 'virastai_acr_allowed_post_statuses' ) );

		add_settings_field(
			'virastai_acr_enable_sandbox_mode',
			__( 'Enable Sandbox Mode', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_api_section',
			array(
				'label_for'   => 'virastai_acr_enable_sandbox_mode',
				'default'     => 0,
				'description' => __( 'Safe Mode: Saves generated content to a custom field instead of overwriting live posts. Review changes in the post edit screen.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_allowed_roles',
			__( 'Allowed User Roles', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_user_roles' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_api_section',
			array(
				'label_for'   => 'virastai_acr_allowed_roles',
				'description' => __( 'Select which user roles are allowed to see and use the Sandbox metabox in the post edit screens.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_delete_on_uninstall',
			__( 'Delete Data on Uninstall', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_danger_checkbox' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_api_section',
			array(
				'label_for'   => 'virastai_acr_delete_on_uninstall',
				'default'     => 0,
				'description' => __( 'WARNING: Checking this option will permanently delete all logs, options, and sandbox generation data from the database when you delete the plugin. This action is irreversible.', 'virastai-auto-content-rewrite-with-ai' ),
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

		add_settings_field(
			'virastai_acr_enable_excerpt_rewrite',
			__( 'Rewrite Excerpt / Short Description', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_content_section',
			array(
				'label_for'   => 'virastai_acr_enable_excerpt_rewrite',
				'description' => __( 'Enable rewriting for regular post excerpts and WooCommerce product short descriptions.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_excerpt_skip_incomplete',
			__( 'Do not process if current content is empty', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_content_section',
			array(
				'label_for'   => 'virastai_acr_excerpt_skip_incomplete',
				'description' => __( 'Do not process excerpt if current post content is empty.', 'virastai-auto-content-rewrite-with-ai' ),
			)
		);

		add_settings_field(
			'virastai_acr_excerpt_prompt_template',
			__( 'Excerpt Instruction', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_textarea' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_content_section',
			array(
				'label_for'     => 'virastai_acr_excerpt_prompt_template',
				'default_value' => 'Rewrite the short description or excerpt based on the context above.',
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
			__( 'Do not process if current content is empty', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_title_section',
			array(
				'label_for'   => 'virastai_acr_title_skip_incomplete',
				'description' => __( 'Do not process title if current post content is empty.', 'virastai-auto-content-rewrite-with-ai' ),
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

		// --- Section: System Logs ---
		add_settings_section( 'virastai_acr_logs_section', __( 'Log Settings', 'virastai-auto-content-rewrite-with-ai' ), null, 'virastai-auto-content-rewrite-with-ai' );

		add_settings_field(
			'virastai_acr_enable_logging',
			__( 'Enable System Logs', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_checkbox_toggle' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_logs_section',
			array(
				'label_for'   => 'virastai_acr_enable_logging',
				'description' => __( 'Log all API requests, responses, errors, and system activity to a dedicated database table.', 'virastai-auto-content-rewrite-with-ai' ),
				'default'     => 0,
			)
		);

		add_settings_field(
			'virastai_acr_log_retention',
			__( 'Log Retention Period', 'virastai-auto-content-rewrite-with-ai' ),
			array( $this, 'virastai_acr_render_field_number' ),
			'virastai-auto-content-rewrite-with-ai',
			'virastai_acr_logs_section',
			array(
				'label_for'     => 'virastai_acr_log_retention',
				'default_value' => 7,
				'description'   => __( 'Number of days to keep logs in the database. Set to 0 to keep logs indefinitely.', 'virastai-auto-content-rewrite-with-ai' ),
				'min'           => 0,
				'max'           => 365,
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
		$option  = get_option( $args['label_for'], $default );
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
		$option = get_option( $args['label_for'], 'hourly' );
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
		$saved_types = get_option( 'virastai_acr_allowed_post_types', array( 'product' ) );

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
		$saved_statuses  = get_option( 'virastai_acr_allowed_post_statuses', array( 'publish' ) );

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
				<span class="virastai-acr-pt-label"><?php echo esc_html($status_obj->label); ?></span>
				<span class="virastai-acr-pt-name"><?php echo esc_html($status_obj->name); ?></span>
			</label>
			<?php
		}
		echo '</div>';
	}

	/**
	 * Render User Roles Checkboxes.
	 */
	public function virastai_acr_render_field_user_roles( $args ) {
		$wp_roles    = wp_roles();
		$all_roles   = $wp_roles->get_names();
		$saved_roles = get_option( 'virastai_acr_allowed_roles', array( 'administrator', 'editor' ) );

		if ( ! is_array( $saved_roles ) ) {
			$saved_roles = array();
		}

		$desc = isset( $args['description'] ) ? $args['description'] : '';

		echo '<div class="virastai-acr-checkbox-grid">';
		foreach ( $all_roles as $role_slug => $role_name ) {
			?>
			<label class="virastai-acr-checkbox-card <?php echo in_array( $role_slug, $saved_roles, true ) ? 'active' : ''; ?>">
				<input type="checkbox" name="virastai_acr_allowed_roles[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $saved_roles, true ) ); ?>>
				<span class="virastai-acr-pt-label"><?php echo esc_html( $role_name ); ?></span>
				<span class="virastai-acr-pt-name"><?php echo esc_html( $role_slug ); ?></span>
			</label>
			<?php
		}
		echo '</div>';
		if ( $desc ) {
			echo '<p class="description" style="margin-top: 10px;">' . wp_kses_post( $desc ) . '</p>';
		}
	}

	/**
	 * Render Danger Toggle Checkbox.
	 */
	public function virastai_acr_render_field_danger_checkbox( $args ) {
		$default = isset( $args['default'] ) ? $args['default'] : 0;
		$option  = get_option( $args['label_for'], $default );
		$desc    = isset( $args['description'] ) ? $args['description'] : '';
		?>
		<label class="virastai-acr-danger-checkbox-wrapper">
			<input type="checkbox" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="1" <?php checked( $option, 1 ); ?> class="virastai-acr-danger-checkbox">
			<span class="virastai-acr-danger-label-text"><?php esc_html_e( 'Delete all plugin options and data upon deletion', 'virastai-auto-content-rewrite-with-ai' ); ?></span>
		</label>
		<?php if ( $desc ) : ?>
			<p class="description" style="color: #dc2626; font-weight: 500; margin-top: 5px;"><?php echo wp_kses_post( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render masked text/password input for API Key.
	 */
	public function virastai_acr_render_field_masked_input( $args ) {
		$option      = get_option( $args['label_for'] );
		$desc        = isset( $args['description'] ) ? $args['description'] : '';
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		?>
		<div class="virastai-acr-masked-input-wrapper">
			<input type="password" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="<?php echo esc_attr( $option ); ?>" class="regular-text virastai-acr-input" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off">
			<button type="button" class="button virastai-acr-toggle-visibility-btn" title="<?php esc_attr_e( 'Toggle Visibility', 'virastai-auto-content-rewrite-with-ai' ); ?>">
				<span class="dashicons dashicons-visibility"></span>
			</button>
		</div>
		<?php if ( $desc ) : ?>
			<p class="description"><?php echo wp_kses_post( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Sanitize per-provider API keys array while preserving keys for other providers.
	 *
	 * @param mixed $input Submitted keys array.
	 * @return array<string, string>
	 */
	public function virastai_acr_sanitize_provider_api_keys( $input ) {
		$existing = get_option( 'virastai_acr_provider_api_keys', array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		if ( ! is_array( $input ) ) {
			return $existing;
		}

		$providers = Virastai_Acr_Providers::get_providers();
		foreach ( $input as $provider_id => $key ) {
			$slug = sanitize_key( $provider_id );
			if ( isset( $providers[ $slug ] ) ) {
				$existing[ $slug ] = sanitize_text_field( wp_unslash( (string) $key ) );
			}
		}

		if ( isset( $existing['openrouter'] ) ) {
			update_option( 'virastai_acr_openrouter_api_key', $existing['openrouter'] );
		}

		return $existing;
	}

	/**
	 * Sanitize per-provider models array while preserving models for other providers.
	 *
	 * @param mixed $input Submitted models array.
	 * @return array<string, string>
	 */
	public function virastai_acr_sanitize_provider_models( $input ) {
		$existing = get_option( 'virastai_acr_provider_models', array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		if ( ! is_array( $input ) ) {
			return $existing;
		}

		$providers = Virastai_Acr_Providers::get_providers();
		foreach ( $input as $provider_id => $model_id ) {
			$slug = sanitize_key( $provider_id );
			if ( isset( $providers[ $slug ] ) ) {
				$existing[ $slug ] = sanitize_text_field( wp_unslash( (string) $model_id ) );
			}
		}

		if ( isset( $existing['openrouter'] ) && '' !== $existing['openrouter'] ) {
			update_option( 'virastai_acr_ai_model', $existing['openrouter'] );
		}

		return $existing;
	}

	/**
	 * Sanitize Custom / Ollama Base URL.
	 *
	 * @param string $input Submitted URL.
	 * @return string
	 */
	public function virastai_acr_sanitize_custom_base_url( $input ) {
		$raw       = esc_url_raw( trim( wp_unslash( (string) $input ) ) );
		$validated = Virastai_Acr_Providers::validate_custom_base_url( $raw );
		if ( '' !== $raw && '' === $validated ) {
			add_settings_error(
				'virastai_acr_custom_base_url',
				'invalid_custom_base_url',
				__( 'Custom Base URL must use HTTPS, or HTTP only for localhost / 127.0.0.1 Ollama.', 'virastai-auto-content-rewrite-with-ai' )
			);
		}
		return $validated;
	}

	/**
	 * Handle AJAX request to fetch/refresh models for a provider.
	 */
	public function handle_ajax_fetch_models() {
		check_ajax_referer( 'virastai_acr_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'virastai-auto-content-rewrite-with-ai' ) );
		}

		$provider_id = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : Virastai_Acr_Providers::get_active_provider_id();
		$models      = Virastai_Acr_Providers::fetch_models( $provider_id, true );

		wp_send_json_success(
			array(
				'provider' => $provider_id,
				'models'   => $models,
			)
		);
	}

	/**
	 * Render AI Provider dropdown field.
	 */
	public function virastai_acr_render_field_provider_select( $args ) {
		$active_id = Virastai_Acr_Providers::get_active_provider_id();
		$providers = Virastai_Acr_Providers::get_providers();
		$desc      = isset( $args['description'] ) ? $args['description'] : '';
		?>
		<div class="virastai-acr-select-wrapper">
			<select name="virastai_acr_ai_provider" id="virastai_acr_ai_provider" class="virastai-acr-select">
				<?php foreach ( $providers as $slug => $cfg ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $active_id, $slug ); ?>>
						<?php echo esc_html( $cfg['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php if ( $desc ) : ?>
			<p class="description"><?php echo wp_kses_post( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render masked API Key input bound to the active provider.
	 */
	public function virastai_acr_render_field_provider_api_key( $args ) {
		$active_id   = Virastai_Acr_Providers::get_active_provider_id();
		$api_key     = Virastai_Acr_Providers::get_api_key( $active_id );
		$desc        = isset( $args['description'] ) ? $args['description'] : '';
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		?>
		<div class="virastai-acr-masked-input-wrapper">
			<input type="password" id="virastai_acr_provider_api_key" name="virastai_acr_provider_api_keys[<?php echo esc_attr( $active_id ); ?>]" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text virastai-acr-input" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off">
			<button type="button" class="button virastai-acr-toggle-visibility-btn" title="<?php esc_attr_e( 'Toggle Visibility', 'virastai-auto-content-rewrite-with-ai' ); ?>">
				<span class="dashicons dashicons-visibility"></span>
			</button>
		</div>
		<?php if ( $desc ) : ?>
			<p class="description"><?php echo wp_kses_post( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render Custom / Ollama Base URL input field.
	 */
	public function virastai_acr_render_field_custom_base_url( $args ) {
		$value       = get_option( 'virastai_acr_custom_base_url', '' );
		$desc        = isset( $args['description'] ) ? $args['description'] : '';
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		?>
		<input type="url" id="virastai_acr_custom_base_url" name="virastai_acr_custom_base_url" value="<?php echo esc_attr( $value ); ?>" class="regular-text virastai-acr-input" placeholder="<?php echo esc_attr( $placeholder ); ?>">
		<?php if ( $desc ) : ?>
			<p class="description"><?php echo wp_kses_post( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render Cloudflare Account ID input field.
	 */
	public function virastai_acr_render_field_cloudflare_account_id( $args ) {
		$value       = get_option( 'virastai_acr_cloudflare_account_id', '' );
		$desc        = isset( $args['description'] ) ? $args['description'] : '';
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		?>
		<input type="text" id="virastai_acr_cloudflare_account_id" name="virastai_acr_cloudflare_account_id" value="<?php echo esc_attr( $value ); ?>" class="regular-text virastai-acr-input" placeholder="<?php echo esc_attr( $placeholder ); ?>">
		<?php if ( $desc ) : ?>
			<p class="description"><?php echo wp_kses_post( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render model select dropdown + refresh button + custom Model ID override.
	 */
	public function virastai_acr_render_field_model_select( $args ) {
		$active_id = Virastai_Acr_Providers::get_active_provider_id();
		$option    = Virastai_Acr_Providers::get_model( $active_id );
		$models    = Virastai_Acr_Providers::fetch_models( $active_id );
		$desc      = isset( $args['description'] ) ? $args['description'] : '';

		if ( ! array_key_exists( $option, $models ) && ! empty( $option ) ) {
			$models[ $option ] = $option;
		}
		?>
		<div class="virastai-acr-model-hybrid-wrapper">
			<div class="virastai-acr-model-select-row">
				<div class="virastai-acr-select-wrapper">
					<select id="virastai_acr_ai_model_select" class="virastai-acr-select">
						<?php foreach ( $models as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $option, $val ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="button" id="virastai-acr-refresh-models-btn" class="button virastai-acr-refresh-models-btn">
					<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Refresh Models', 'virastai-auto-content-rewrite-with-ai' ); ?>
				</button>
			</div>
			<div class="virastai-acr-model-custom-row">
				<input type="text" id="virastai_acr_ai_model" name="virastai_acr_provider_models[<?php echo esc_attr( $active_id ); ?>]" value="<?php echo esc_attr( $option ); ?>" class="regular-text virastai-acr-input" placeholder="<?php esc_attr_e( 'Model ID (e.g. gpt-4o-mini, llama3.2)', 'virastai-auto-content-rewrite-with-ai' ); ?>">
			</div>
		</div>
		<?php if ( $desc ) : ?>
			<p class="description"><?php echo wp_kses_post( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render number input.
	 */
	public function virastai_acr_render_field_number( $args ) {
		$default = isset( $args['default_value'] ) ? $args['default_value'] : 5;
		$option  = get_option( $args['label_for'], $default );
		$desc    = isset( $args['description'] ) ? $args['description'] : '';
		$min     = isset( $args['min'] ) ? $args['min'] : 1;
		$max     = isset( $args['max'] ) ? $args['max'] : 50;
		?>
		<input type="number" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="<?php echo esc_attr( $option ); ?>" class="small-text virastai-acr-input" min="<?php echo esc_attr( $min ); ?>" <?php echo $max !== null ? 'max="' . esc_attr( $max ) . '"' : ''; ?>>
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
		$option  = get_option( $args['label_for'], $default );

		if ( empty( $option ) ) {
			$option = $default;
		}
		?>
		<textarea id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" rows="5" class="large-text code virastai-acr-textarea"><?php echo esc_textarea( $option ); ?></textarea>
		<?php if ( ! empty( $default ) ) : ?>
			<div class="virastai-acr-reset-wrapper">
				<button type="button" class="virastai-acr-reset-btn" data-target="<?php echo esc_attr( $args['label_for'] ); ?>" data-default="<?php echo esc_attr( $default ); ?>">
					<span class="dashicons dashicons-undo"></span> <?php esc_html_e( 'Reset to Default', 'virastai-auto-content-rewrite-with-ai' ); ?>
				</button>
			</div>
		<?php endif; ?>
		<?php if ( $desc = ( isset( $args['description'] ) ? $args['description'] : '' ) ) : ?>
			<p class="description"><?php echo wp_kses_post( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Fetch OpenRouter models and cache them for 1 hour.
	 *
	 * @return array List of models (ID => Name).
	 */
	private function virastai_acr_get_openrouter_models() {
		$cache_key = 'virastai_acr_models_cache';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$url = 'https://openrouter.ai/api/v1/models';
		if ( class_exists( 'Virastai_Acr_Logger' ) ) {
			Virastai_Acr_Logger::log( 'api_request', "GET request sent to: $url" );
		}

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			if ( class_exists( 'Virastai_Acr_Logger' ) ) {
				Virastai_Acr_Logger::log( 'error', 'Models API Error: ' . $response->get_error_message() );
			}
			return $this->virastai_acr_get_fallback_models();
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		if ( class_exists( 'Virastai_Acr_Logger' ) ) {
			$logged_body = strlen( $body ) > 1000 ? substr( $body, 0, 1000 ) . '... [body truncated, total ' . round( strlen( $body ) / 1024 ) . ' KB]' : $body;
			Virastai_Acr_Logger::log( 'api_response', "Response code: $response_code\nBody: " . $logged_body );
		}

		if ( 200 !== $response_code ) {
			return $this->virastai_acr_get_fallback_models();
		}
		$data = json_decode( $body, true );

		if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return $this->virastai_acr_get_fallback_models();
		}

		$models = array();
		foreach ( $data['data'] as $model_item ) {
			if ( isset( $model_item['id'], $model_item['name'] ) ) {
				$models[ $model_item['id'] ] = $model_item['name'] . ' (' . $model_item['id'] . ')';
			}
		}

		if ( ! empty( $models ) ) {
			asort( $models );
			set_transient( $cache_key, $models, HOUR_IN_SECONDS );
			return $models;
		}

		return $this->virastai_acr_get_fallback_models();
	}

	/**
	 * Fallback models list in case of network issue.
	 *
	 * @return array
	 */
	private function virastai_acr_get_fallback_models() {
		return array(
			'google/gemini-2.0-flash-lite-preview-02-05:free' => 'Model 2.0 Flash Lite (free)',
			'google/gemma-2-9b-it:free'                      => 'Gemma 2 9B (free)',
			'meta-llama/llama-3-8b-instruct:free'            => 'Llama 3 8B (free)',
			'mistralai/mistral-7b-instruct:free'             => 'Mistral 7B (free)',
		);
	}

	/**
	 * Render a single settings section manually for custom tab layout.
	 *
	 * @param string $page       Settings page slug.
	 * @param string $section_id Section identifier.
	 */
	private function do_settings_sections_custom( $page, $section_id ) {
		global $wp_settings_sections, $wp_settings_fields;

		if ( ! isset( $wp_settings_sections[ $page ][ $section_id ] ) ) {
			return;
		}

		$section = $wp_settings_sections[ $page ][ $section_id ];

		if ( $section['title'] ) {
			echo '<h2>' . esc_html( $section['title'] ) . "</h2>\n";
		}

		if ( $section['callback'] ) {
			call_user_func( $section['callback'], $section );
		}

		if ( ! isset( $wp_settings_fields[ $page ][ $section_id ] ) ) {
			return;
		}

		echo '<table class="form-table" role="presentation">';
		do_settings_fields( $page, $section_id );
		echo '</table>';
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
							<?php settings_fields( 'virastai_acr_options_group' ); ?>

							<!-- Tabs Nav -->
							<h2 class="nav-tab-wrapper virastai-acr-tabs-nav">
								<a href="#tab-general" class="nav-tab nav-tab-active" data-tab="general"><?php esc_html_e( 'General', 'virastai-auto-content-rewrite-with-ai' ); ?></a>
								<a href="#tab-automation" class="nav-tab" data-tab="automation"><?php esc_html_e( 'Automation', 'virastai-auto-content-rewrite-with-ai' ); ?></a>
								<a href="#tab-custom-automation" class="nav-tab" data-tab="custom-automation"><?php esc_html_e( 'Custom Automation', 'virastai-auto-content-rewrite-with-ai' ); ?></a>
								<a href="#tab-content" class="nav-tab" data-tab="content"><?php esc_html_e( 'Content Options', 'virastai-auto-content-rewrite-with-ai' ); ?></a>
								<a href="#tab-title" class="nav-tab" data-tab="title"><?php esc_html_e( 'Title Options', 'virastai-auto-content-rewrite-with-ai' ); ?></a>
								<a href="#tab-logs" class="nav-tab" data-tab="logs"><?php esc_html_e( 'System Logs', 'virastai-auto-content-rewrite-with-ai' ); ?></a>
								<a href="#tab-help" class="nav-tab" data-tab="help"><?php esc_html_e( 'Help', 'virastai-auto-content-rewrite-with-ai' ); ?></a>
							</h2>

							<!-- Tab Contents -->
							<div id="tab-general" class="virastai-acr-tab-content active">
								<?php $this->do_settings_sections_custom( 'virastai-auto-content-rewrite-with-ai', 'virastai_acr_api_section' ); ?>
							</div>

							<div id="tab-automation" class="virastai-acr-tab-content">
								<?php $this->do_settings_sections_custom( 'virastai-auto-content-rewrite-with-ai', 'virastai_acr_automation_section' ); ?>
								
								<div class="virastai-acr-automation-actions" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
									<h3 style="margin-bottom: 10px; color: #111827; font-size: 16px; font-weight: 600;"><?php esc_html_e( 'Reset Progress Actions', 'virastai-auto-content-rewrite-with-ai' ); ?></h3>
									<p class="description" style="margin-bottom: 15px;"><?php esc_html_e( 'Clear completion markers to re-run rewriting processes on posts that have already been processed.', 'virastai-auto-content-rewrite-with-ai' ); ?></p>
									
									<div style="display: flex; flex-wrap: wrap; gap: 10px;">
										<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-danger" id="virastai-acr-tab-reset-all-btn">
											<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Reset ALL Progress', 'virastai-auto-content-rewrite-with-ai' ); ?>
										</button>
									</div>
								</div>
							</div>

							<div id="tab-custom-automation" class="virastai-acr-tab-content">
								<?php
								if ( $this->custom_automation ) {
									$this->custom_automation->render_tab();
								}
								?>
							</div>

							<div id="tab-content" class="virastai-acr-tab-content">
								<?php $this->do_settings_sections_custom( 'virastai-auto-content-rewrite-with-ai', 'virastai_acr_base_prompt_section' ); ?>
								<?php $this->do_settings_sections_custom( 'virastai-auto-content-rewrite-with-ai', 'virastai_acr_content_section' ); ?>
							</div>

							<div id="tab-title" class="virastai-acr-tab-content">
								<?php $this->do_settings_sections_custom( 'virastai-auto-content-rewrite-with-ai', 'virastai_acr_title_section' ); ?>
							</div>

							<div id="tab-logs" class="virastai-acr-tab-content">
								<?php $this->do_settings_sections_custom( 'virastai-auto-content-rewrite-with-ai', 'virastai_acr_logs_section' ); ?>

								<div class="virastai-acr-logs-viewer-container">
									<div class="virastai-acr-logs-header">
										<h3><?php esc_html_e( 'Recent System Logs', 'virastai-auto-content-rewrite-with-ai' ); ?></h3>
										<div class="virastai-acr-logs-actions" style="display: flex; gap: 8px; align-items: center;">
											<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-secondary" id="virastai-acr-refresh-logs-btn">
												<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Refresh Logs', 'virastai-auto-content-rewrite-with-ai' ); ?>
											</button>
											<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-secondary" id="virastai-acr-copy-logs-btn">
												<span class="dashicons dashicons-admin-page"></span> <?php esc_html_e( 'Copy Logs', 'virastai-auto-content-rewrite-with-ai' ); ?>
											</button>
											<button type="button" class="virastai-acr-btn-2026 virastai-acr-btn-2026-danger" id="virastai-acr-clear-logs-btn">
												<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear Logs', 'virastai-auto-content-rewrite-with-ai' ); ?>
											</button>
										</div>
									</div>
									<div class="virastai-acr-logs-body">
										<div class="virastai-acr-table-wrapper">
											<table class="virastai-acr-logs-table">
												<thead>
													<tr>
														<th class="col-date"><?php esc_html_e( 'Date/Time', 'virastai-auto-content-rewrite-with-ai' ); ?></th>
														<th class="col-type"><?php esc_html_e( 'Type', 'virastai-auto-content-rewrite-with-ai' ); ?></th>
														<th class="col-message"><?php esc_html_e( 'Message', 'virastai-auto-content-rewrite-with-ai' ); ?></th>
														<th class="col-actions"><?php esc_html_e( 'Actions', 'virastai-auto-content-rewrite-with-ai' ); ?></th>
													</tr>
												</thead>
												<tbody id="virastai-acr-logs-rows">
													<!-- Rows loaded via AJAX -->
												</tbody>
											</table>
										</div>
										<div id="virastai-acr-logs-pagination" class="virastai-acr-pagination-container">
											<!-- Pagination loaded via AJAX -->
										</div>
										<!-- Hidden element to keep raw logs for copy logs functionality -->
										<textarea id="virastai-acr-logs-raw-content" style="display: none;"><?php
											if ( class_exists( 'Virastai_Acr_Logger' ) ) {
												echo esc_html( Virastai_Acr_Logger::get_logs_formatted() );
											}
										?></textarea>
									</div>
								</div>
							</div>

							<div id="tab-help" class="virastai-acr-tab-content">
								<div class="virastai-acr-help-container">
									<h2 style="margin-bottom: 20px; color: #111827; font-size: 18px; font-weight: 600;"><?php esc_html_e( 'Documentation & Help Guide', 'virastai-auto-content-rewrite-with-ai' ); ?></h2>
									
									<div class="virastai-acr-help-grid">
										<!-- Card 1: API & Model Setup -->
										<div class="virastai-acr-help-card">
											<div class="help-card-header">
												<span class="dashicons dashicons-key"></span>
												<h3><?php esc_html_e( 'API Key & Model Selection', 'virastai-auto-content-rewrite-with-ai' ); ?></h3>
											</div>
											<div class="help-card-body">
												<p><strong><?php esc_html_e( 'Getting an API Key:', 'virastai-auto-content-rewrite-with-ai' ); ?></strong></p>
												<ol>
													<li>
														<?php
														echo wp_kses_post(
															sprintf(
																/* translators: %s: Link to OpenRouter homepage */
																__( 'Visit %s and sign up for an account.', 'virastai-auto-content-rewrite-with-ai' ),
																'<a href="https://openrouter.ai/" target="_blank" rel="noopener">OpenRouter.ai</a>'
															)
														);
														?>
													</li>
													<li><?php esc_html_e( 'Navigate to the Keys section and create a new API Key.', 'virastai-auto-content-rewrite-with-ai' ); ?></li>
													<li><?php esc_html_e( 'Copy the generated key, paste it into the OpenRouter API Key field in the General tab, and save your settings.', 'virastai-auto-content-rewrite-with-ai' ); ?></li>
												</ol>
												<p style="margin-top: 15px;"><strong><?php esc_html_e( 'Selecting a Model:', 'virastai-auto-content-rewrite-with-ai' ); ?></strong></p>
												<ul>
													<li><?php esc_html_e( 'After saving a valid API Key, the plugin automatically fetches and caches all available models from OpenRouter.', 'virastai-auto-content-rewrite-with-ai' ); ?></li>
													<li><?php esc_html_e( 'Choose your preferred text generation model from the Model ID dropdown select field.', 'virastai-auto-content-rewrite-with-ai' ); ?></li>
												</ul>
											</div>
										</div>

										<!-- Card 2: Automation -->
										<div class="virastai-acr-help-card">
											<div class="help-card-header">
												<span class="dashicons dashicons-clock"></span>
												<h3><?php esc_html_e( 'Automation & Cron Engine', 'virastai-auto-content-rewrite-with-ai' ); ?></h3>
											</div>
											<div class="help-card-body">
												<p><strong><?php esc_html_e( 'How Automation Works:', 'virastai-auto-content-rewrite-with-ai' ); ?></strong></p>
												<ul>
													<li><?php esc_html_e( 'The plugin uses WordPress Cron to process content rewriting in the background.', 'virastai-auto-content-rewrite-with-ai' ); ?></li>
													<li><?php esc_html_e( 'During each cron interval, the engine queries posts matching your selected Post Types and Post Statuses that have not been processed yet.', 'virastai-auto-content-rewrite-with-ai' ); ?></li>
												</ul>
												<p style="margin-top: 15px;"><strong><?php esc_html_e( 'Key Configuration:', 'virastai-auto-content-rewrite-with-ai' ); ?></strong></p>
												<ul>
													<li><?php esc_html_e( 'Enable Automation: Toggle this switch to activate or deactivate the background cron runner.', 'virastai-auto-content-rewrite-with-ai' ); ?></li>
													<li><?php esc_html_e( 'Run Frequency: Select how often the cron job executes background query runs.', 'virastai-auto-content-rewrite-with-ai' ); ?></li>
													<li><?php esc_html_e( 'Items per Batch: Set the maximum number of posts processed in a single run to prevent server timeouts.', 'virastai-auto-content-rewrite-with-ai' ); ?></li>
												</ul>
											</div>
										</div>

										<!-- Card 3: Sandbox Mode -->
										<div class="virastai-acr-help-card">
											<div class="help-card-header">
												<span class="dashicons dashicons-visibility"></span>
												<h3><?php esc_html_e( 'Sandbox Review Mode', 'virastai-auto-content-rewrite-with-ai' ); ?></h3>
											</div>
											<div class="help-card-body">
												<p><strong><?php esc_html_e( 'What is Sandbox Mode?', 'virastai-auto-content-rewrite-with-ai' ); ?></strong></p>
												<ul>
													<li><?php esc_html_e( 'Sandbox Mode saves generated titles, content, and excerpts to preview fields instead of replacing live content directly in the database.', 'virastai-auto-content-rewrite-with-ai' ); ?></li>
													<li><?php esc_html_e( 'An interactive metabox appears on the post edit screens allowing you to review, regenerate, or copy generations.', 'virastai-auto-content-rewrite-with-ai' ); ?></li>
												</ul>
												<p style="margin-top: 15px;"><strong><?php esc_html_e( 'Review Controls:', 'virastai-auto-content-rewrite-with-ai' ); ?></strong></p>
												<ul>
													<li><?php esc_html_e( 'Allowed User Roles: Configure which roles can view and use the metabox.', 'virastai-auto-content-rewrite-with-ai' ); ?></li>
													<li><?php esc_html_e( 'Apply Generations: Overwrite the live post with the reviewed sandbox drafts.', 'virastai-auto-content-rewrite-with-ai' ); ?></li>
													<li><?php esc_html_e( 'Clear Generated: Clear the sandbox preview drafts and reset the progress markers.', 'virastai-auto-content-rewrite-with-ai' ); ?></li>
												</ul>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="virastai-acr-footer-actions">
								<button type="submit" name="submit" id="submit" class="virastai-acr-btn-2026 virastai-acr-btn-2026-primary">
									<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Settings', 'virastai-auto-content-rewrite-with-ai' ); ?>
								</button>
							</div>
						</form>
						<form method="post" id="virastai-acr-form-clear-logs" style="display: none;">
							<?php wp_nonce_field( 'virastai_acr_reset_action', 'virastai_acr_reset_nonce' ); ?>
							<input type="hidden" name="virastai_acr_clear_logs" value="1">
						</form>
						<form method="post" id="virastai-acr-form-reset-all" style="display: none;">
							<?php wp_nonce_field( 'virastai_acr_reset_action', 'virastai_acr_reset_nonce' ); ?>
							<input type="hidden" name="virastai_acr_reset_progress" value="1">
						</form>
					</div>
				</div>

				<!-- Sidebar -->
				<?php Virastai_Acr_Admin_Sidebar::render(); ?>
			</div>
		</div>
		<?php
	}
}
