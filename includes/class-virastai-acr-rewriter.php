<?php
/**
 * Class Virastai_Acr_Rewriter
 *
 * Handles the core logic for communicating with the API and processing content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Virastai_Acr_Rewriter {

	/**
	 * OpenRouter API Endpoint.
	 */
	const API_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

	/**
	 * Helper to get option with on-the-fly migration from old jixcrai option keys.
	 *
	 * @param string $new_key New option name.
	 * @param string $old_key Old option name.
	 * @param mixed  $default Default value.
	 * @return mixed Option value.
	 */
	public static function get_option_migrated( $new_key, $old_key, $default = false ) {
		$value = get_option( $new_key, 'NOT_SET' );
		if ( 'NOT_SET' === $value ) {
			$old_value = get_option( $old_key, 'NOT_SET' );
			if ( 'NOT_SET' !== $old_value ) {
				update_option( $new_key, $old_value );
				return $old_value;
			}
			return $default;
		}
		return $value;
	}

	/**
	 * Helper to get post metadata with on-the-fly migration from old jixcrai keys.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $new_key New meta key.
	 * @param string $old_key Old meta key.
	 * @return string Meta value.
	 */
	public static function get_post_meta_migrated( $post_id, $new_key, $old_key ) {
		$metadata = get_post_meta( $post_id, $new_key );
		if ( empty( $metadata ) ) {
			$old_metadata = get_post_meta( $post_id, $old_key );
			if ( ! empty( $old_metadata ) ) {
				$value = $old_metadata[0];
				update_post_meta( $post_id, $new_key, $value );
				delete_post_meta( $post_id, $old_key );
				return $value;
			}
			return '';
		}
		return $metadata[0];
	}

	/**
	 * Rewrites product content using OpenRouter API.
	 *
	 * @param int     $product_id Product ID.
	 * @param WP_Post $post       Post object.
	 * @return bool True if at least one update occurred, false otherwise.
	 */
	public function virastai_acr_generate_content( $product_id, $post ) {
		// --- 1. Load Settings (with migration check) ---
		$enable_title_rewrite   = self::get_option_migrated( 'virastai_acr_enable_title_rewrite', 'jixcrai_enable_title_rewrite', 0 );
		$enable_content_rewrite = self::get_option_migrated( 'virastai_acr_enable_content_rewrite', 'jixcrai_enable_content_rewrite', 1 );
		$sandbox_mode_enabled   = self::get_option_migrated( 'virastai_acr_enable_sandbox_mode', 'jixcrai_enable_sandbox_mode', 0 );

		// Skip settings.
		$skip_incomplete_title   = self::get_option_migrated( 'virastai_acr_title_skip_incomplete', 'jixcrai_skip_incomplete', 1 );
		$skip_incomplete_content = self::get_option_migrated( 'virastai_acr_content_skip_incomplete', 'jixcrai_content_skip_incomplete', 1 );

		// Formatting settings.
		$preserve_images   = self::get_option_migrated( 'virastai_acr_preserve_images', 'jixcrai_preserve_images', 1 );
		$enable_formatting = self::get_option_migrated( 'virastai_acr_enable_formatting', 'jixcrai_enable_formatting', 1 );
		$remove_title_html = self::get_option_migrated( 'virastai_acr_remove_title_html', 'jixcrai_remove_title_html', 1 );

		// Prompts.
		$base_prompt_tpl     = self::get_option_migrated( 'virastai_acr_base_prompt_template', 'jixcrai_base_prompt_template', "Product: {product_title}\n\nContent: {post_content}" );
		$title_prompt_inst   = self::get_option_migrated( 'virastai_acr_title_prompt_template', 'jixcrai_title_prompt_template', 'Generate a catchy product title based on the context above.' );
		$content_prompt_inst = self::get_option_migrated( 'virastai_acr_prompt_template', 'jixcrai_prompt_template', 'Rewrite the content based on the context above.' );

		$current_title   = get_the_title( $product_id );
		$current_content = $post->post_content;

		// Prepare Base Context.
		$base_context = str_replace(
			array( '{product_title}', '{post_content}' ),
			array( $current_title, $current_content ),
			$base_prompt_tpl
		);

		$update_args = array( 'ID' => $product_id );
		$has_changes = false;

		// --- 2. Determine what needs processing ---
		$title_already_done   = self::get_post_meta_migrated( $product_id, '_virastai_acr_title_generated', '_jixcrai_title_generated' );
		$content_already_done = self::get_post_meta_migrated( $product_id, '_virastai_acr_content_generated', '_jixcrai_content_generated' );

		$process_title   = $enable_title_rewrite && ! $title_already_done;
		$process_content = $enable_content_rewrite && ! $content_already_done;

		// Validation for Title.
		if ( $process_title && $skip_incomplete_title && empty( trim( $current_title ) ) ) {
			$process_title = false;
			$this->log( "Skipping Title for ID $product_id: Title is empty." );
		}

		// Validation for Content.
		if ( $process_content && $skip_incomplete_content && empty( trim( $current_content ) ) ) {
			$process_content = false;
			$this->log( "Skipping Content for ID $product_id: Content is empty." );
		}

		// --- 3. Combined JSON Path (Single API Call) ---
		if ( $process_title && $process_content ) {

			// Prepare images for re-insertion later.
			$existing_images = '';
			if ( $preserve_images ) {
				preg_match_all( '/<img[^>]+>/i', $current_content, $images );
				$existing_images = isset( $images[0] ) ? implode( "\n", $images[0] ) : '';
			}

			// Construct Combined Prompt.
			$instructions  = "Instructions:\n";
			$instructions .= "1. Title Task: " . $title_prompt_inst . "\n";
			$instructions .= "2. Content Task: " . $content_prompt_inst . "\n";
			$instructions .= "\nIMPORTANT: You MUST return the result as a valid JSON object with exactly two keys: 'title' and 'content'.";

			$full_prompt = $base_context . "\n\n---\n\n" . $instructions;

			$response_text = $this->virastai_acr_call_api( $full_prompt, true );

			if ( $response_text ) {
				// Clean potential Markdown wrapping from the response.
				$cleaned_response = preg_replace( '/^```json\s*|\s*```$/s', '', $response_text );
				$json_data        = json_decode( $cleaned_response, true );

				if ( json_last_error() === JSON_ERROR_NONE && isset( $json_data['title'], $json_data['content'] ) ) {

					// Handle Title.
					$new_title = $json_data['title'];
					if ( $remove_title_html ) {
						// Strip HTML tags.
						$new_title = wp_strip_all_tags( $new_title );
						// Strip Markdown syntax (headers, bold, italics, code blocks, etc).
						$new_title = preg_replace( '/([#*`_~\[\]\(\)])/', '', $new_title );
					}
					$new_title = trim( $new_title, '"\'' );

					if ( $sandbox_mode_enabled ) {
						update_post_meta( $product_id, '_virastai_acr_sandbox_title', $new_title );
					} else {
						$update_args['post_title'] = $new_title;
					}
					update_post_meta( $product_id, '_virastai_acr_title_generated', '1' );

					// Handle Content.
					$new_content = $json_data['content'];
					if ( $enable_formatting ) {
						$new_content = $this->virastai_acr_apply_formatting( $new_content );
					}
					// Append images.
					if ( $preserve_images && ! empty( $existing_images ) ) {
						$new_content = $new_content . "\n\n" . $existing_images;
					}

					if ( $sandbox_mode_enabled ) {
						update_post_meta( $product_id, '_virastai_acr_sandbox_content', $new_content );
					} else {
						$update_args['post_content'] = $new_content;
					}
					update_post_meta( $product_id, '_virastai_acr_content_generated', '2' );

					$has_changes = true;
					$this->log( "Generated Title AND Content (JSON) for ID $product_id" . ( $sandbox_mode_enabled ? ' [SANDBOX]' : '' ) );

				} else {
					$this->log( "JSON Decode Error for ID $product_id. Response snippet: " . substr( $response_text, 0, 150 ) );
				}
			}
		} else {
			// --- 4. Fallback: Sequential/Single Paths ---

			// Title Only Path.
			if ( $process_title && ! empty( $title_prompt_inst ) ) {
				$full_prompt = $base_context . "\n\n---\n\n" . $title_prompt_inst;
				$new_title   = $this->virastai_acr_call_api( $full_prompt, false );

				if ( $new_title ) {
					if ( $remove_title_html ) {
						// Strip HTML tags.
						$new_title = wp_strip_all_tags( $new_title );
						// Strip Markdown syntax (headers, bold, italics, code blocks, etc).
						$new_title = preg_replace( '/([#*`_~\[\]\(\)])/', '', $new_title );
					}
					$new_title = trim( $new_title, '"\'' );

					if ( $sandbox_mode_enabled ) {
						update_post_meta( $product_id, '_virastai_acr_sandbox_title', $new_title );
					} else {
						$update_args['post_title'] = $new_title;
					}
					$has_changes = true;

					update_post_meta( $product_id, '_virastai_acr_title_generated', '1' );
					$this->log( "Generated Title for ID $product_id" . ( $sandbox_mode_enabled ? ' [SANDBOX]' : '' ) );
				}
			}

			// Content Only Path.
			if ( $process_content && ! empty( $content_prompt_inst ) ) {
				$existing_images = '';
				if ( $preserve_images ) {
					preg_match_all( '/<img[^>]+>/i', $current_content, $images );
					$existing_images = isset( $images[0] ) ? implode( "\n", $images[0] ) : '';
				}

				$full_prompt = $base_context . "\n\n---\n\n" . $content_prompt_inst;
				$new_content = $this->virastai_acr_call_api( $full_prompt, false );

				if ( $new_content ) {
					if ( $enable_formatting ) {
						$new_content = $this->virastai_acr_apply_formatting( $new_content );
					}

					if ( $preserve_images && ! empty( $existing_images ) ) {
						$new_content = $new_content . "\n\n" . $existing_images;
					}

					if ( $sandbox_mode_enabled ) {
						update_post_meta( $product_id, '_virastai_acr_sandbox_content', $new_content );
					} else {
						$update_args['post_content'] = $new_content;
					}
					$has_changes = true;

					update_post_meta( $product_id, '_virastai_acr_content_generated', '2' );
					$this->log( "Generated Content for ID $product_id" . ( $sandbox_mode_enabled ? ' [SANDBOX]' : '' ) );
				}
			}
		}

		// --- 5. Final Update ---
		if ( $has_changes && ! $sandbox_mode_enabled ) {
			if ( count( $update_args ) > 1 ) {
				$result = wp_update_post( $update_args );

				if ( is_wp_error( $result ) ) {
					$this->log( "Error updating post $product_id: " . $result->get_error_message() );
					return false;
				}
			}
			return true;
		}

		return $has_changes; // Return true if sandbox meta was updated.
	}

	/**
	 * Helper to apply basic formatting (Markdown-like to HTML).
	 *
	 * @param string $content Raw content.
	 * @return string Formatted content.
	 */
	private function virastai_acr_apply_formatting( $content ) {
		// Format Headers.
		$content = preg_replace_callback(
			'/^(#{1,6})\s+(.+)$/m',
			function ( $matches ) {
				$level = strlen( $matches[1] );
				return "<h{$level}>" . trim( $matches[2] ) . "</h{$level}>";
			},
			$content
		);

		// Format Bold.
		$content = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content );

		// Format Italic.
		$content = preg_replace( '/\*([^*]+)\*/', '<em>$1</em>', $content );
		$content = preg_replace( '/_([^_]+)_/', '<em>$1</em>', $content );

		return $content;
	}

	/**
	 * Private helper to call OpenRouter API.
	 *
	 * @param string $prompt       The prompt to send.
	 * @param bool   $is_json_mode Whether to request a JSON response format.
	 * @return string|false Generated text or false on failure.
	 */
	private function virastai_acr_call_api( $prompt, $is_json_mode = false ) {
		$api_key = self::get_option_migrated( 'virastai_acr_openrouter_api_key', 'jixcrai_openrouter_api_key', '' );
		$model   = self::get_option_migrated( 'virastai_acr_ai_model', 'jixcrai_ai_model', 'google/gemini-2.0-flash-lite-preview-02-05:free' );

		$api_key = trim( $api_key );

		if ( empty( $api_key ) ) {
			$this->log( 'API Key is missing.' );
			return false;
		}

		if ( empty( self::API_ENDPOINT ) ) {
			$this->log( 'CRITICAL: API Endpoint Constant is empty.' );
			return false;
		}

		$request_body = array(
			'model'    => $model,
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);

		if ( $is_json_mode ) {
			$request_body['response_format'] = array( 'type' => 'json_object' );
		}

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
					'HTTP-Referer'  => get_site_url(),
					'X-Title'       => 'Virastai Auto Content Rewrite With AI',
				),
				'body'    => wp_json_encode( $request_body ),
				'timeout' => 45,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'API Error: ' . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$this->log( "API returned $response_code - $response_body" );
			return false;
		}

		$data = json_decode( $response_body, true );

		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			return $data['choices'][0]['message']['content'];
		}

		return false;
	}

	/**
	 * Logging Utility.
	 *
	 * @param string $message The message to log.
	 */
	private function log( $message ) {
		$logging_enabled = self::get_option_migrated( 'virastai_acr_enable_logging', 'jixcrai_enable_logging', 0 );
		if ( $logging_enabled ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log error for debugging purposes.
			error_log( '[VirastaiACR] ' . $message );
		}
	}
}
