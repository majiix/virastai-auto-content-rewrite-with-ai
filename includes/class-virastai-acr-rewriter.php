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
	 * Rewrites product content using OpenRouter API.
	 *
	 * @param int     $product_id Product ID.
	 * @param WP_Post $post       Post object.
	 * @return bool True if at least one update occurred, false otherwise.
	 */
	public function virastai_acr_generate_content( $product_id, $post, $target_type = '' ) {
		// --- 1. Load Settings ---
		$enable_title_rewrite     = get_option( 'virastai_acr_enable_title_rewrite', 0 );
		$enable_content_rewrite   = get_option( 'virastai_acr_enable_content_rewrite', 1 );
		$enable_excerpt_rewrite   = get_option( 'virastai_acr_enable_excerpt_rewrite', 0 );
		$sandbox_mode_enabled     = get_option( 'virastai_acr_enable_sandbox_mode', 0 );

		// Skip settings.
		$skip_incomplete_title   = get_option( 'virastai_acr_title_skip_incomplete', 1 );
		$skip_incomplete_content = get_option( 'virastai_acr_content_skip_incomplete', 1 );
		$skip_incomplete_excerpt = get_option( 'virastai_acr_excerpt_skip_incomplete', 1 );

		// Formatting settings.
		$preserve_images   = get_option( 'virastai_acr_preserve_images', 1 );
		$enable_formatting = get_option( 'virastai_acr_enable_formatting', 1 );
		$remove_title_html = get_option( 'virastai_acr_remove_title_html', 1 );

		// Prompts.
		$base_prompt_tpl     = get_option( 'virastai_acr_base_prompt_template', "Title: {post_title}\n\nContent: {post_content}" );
		$title_prompt_inst   = get_option( 'virastai_acr_title_prompt_template', 'Generate a catchy product title based on the context above.' );
		$content_prompt_inst = get_option( 'virastai_acr_prompt_template', 'Rewrite the content based on the context above.' );
		$excerpt_prompt_inst = get_option( 'virastai_acr_excerpt_prompt_template', 'Rewrite the short description or excerpt based on the context above.' );

		$current_title   = get_the_title( $product_id );
		$current_content = $post->post_content;
		$current_excerpt = $post->post_excerpt;

		// Prepare Base Context.
		$base_context = str_replace(
			array( '{post_title}', '{post_content}' ),
			array( $current_title, $current_content ),
			$base_prompt_tpl
		);

		$update_args = array( 'ID' => $product_id );
		$has_changes = false;

		// --- 2. Determine what needs processing ---
		$title_already_done   = get_post_meta( $product_id, '_virastai_acr_title_generated', true );
		$content_already_done = get_post_meta( $product_id, '_virastai_acr_content_generated', true );
		$excerpt_already_done = get_post_meta( $product_id, '_virastai_acr_excerpt_generated', true );

		$process_title   = false;
		$process_content = false;
		$process_excerpt = false;

		if ( empty( $target_type ) || 'all' === $target_type ) {
			$process_title   = $enable_title_rewrite && ! $title_already_done;
			$process_content = $enable_content_rewrite && ! $content_already_done;
			$process_excerpt = $enable_excerpt_rewrite && ! $excerpt_already_done;
		} else {
			if ( 'title' === $target_type && $enable_title_rewrite ) {
				$process_title = true;
			}
			if ( 'content' === $target_type && $enable_content_rewrite ) {
				$process_content = true;
			}
			if ( 'excerpt' === $target_type && $enable_excerpt_rewrite ) {
				$process_excerpt = true;
			}
		}

		// Validation for Title.
		if ( $process_title && $skip_incomplete_title && empty( trim( $current_content ) ) ) {
			$process_title = false;
			$this->log( "Skipping Title for ID $product_id: Content is empty." );
		}

		// Validation for Content.
		if ( $process_content && $skip_incomplete_content && empty( trim( $current_content ) ) ) {
			$process_content = false;
			$this->log( "Skipping Content for ID $product_id: Content is empty." );
		}

		// Validation for Excerpt.
		if ( $process_excerpt && $skip_incomplete_excerpt && empty( trim( $current_content ) ) ) {
			$process_excerpt = false;
			$this->log( "Skipping Excerpt for ID $product_id: Content is empty." );
		}

		$process_count = ( $process_title ? 1 : 0 ) + ( $process_content ? 1 : 0 ) + ( $process_excerpt ? 1 : 0 );
		if ( 0 === $process_count ) {
			return false;
		}

		// Prepare images for re-insertion later.
		$existing_images = '';
		if ( $preserve_images ) {
			preg_match_all( '/<img[^>]+>/i', $current_content, $images );
			$existing_images = isset( $images[0] ) ? implode( "\n", $images[0] ) : '';
		}

		// --- 3. Combined JSON Path (Single API Call for multiple fields) ---
		if ( $process_count > 1 ) {
			// Construct Combined Prompt.
			$instructions  = "Instructions:\n";
			$required_keys = array();
			$idx           = 1;

			if ( $process_title ) {
				$instructions   .= $idx . ". Title Task: " . $title_prompt_inst . "\n";
				$required_keys[] = "'title'";
				$idx++;
			}
			if ( $process_content ) {
				$instructions   .= $idx . ". Content Task: " . $content_prompt_inst . "\n";
				$required_keys[] = "'content'";
				$idx++;
			}
			if ( $process_excerpt ) {
				$instructions   .= $idx . ". Excerpt Task: " . $excerpt_prompt_inst . "\n";
				$required_keys[] = "'excerpt'";
				$idx++;
			}

			$instructions .= "\nIMPORTANT: You MUST return the result as a valid JSON object with exactly these keys: " . implode( ', ', $required_keys ) . ". Output ONLY the raw JSON object. Do NOT include any introductory text, thinking blocks, conversational filler, or explanations in the response.";

			$full_prompt   = $base_context . "\n\n---\n\n" . $instructions;
			$response_text = $this->virastai_acr_call_api( $full_prompt, true );

			if ( $response_text ) {
				// Clean potential Markdown wrapping from the response.
				$cleaned_response = preg_replace( '/^```json\s*|\s*```$/s', '', $response_text );
				$json_data        = json_decode( $cleaned_response, true );

				if ( json_last_error() === JSON_ERROR_NONE && is_array( $json_data ) ) {
					// Handle Title.
					if ( $process_title && isset( $json_data['title'] ) ) {
						$new_title = $this->virastai_acr_clean_title( $json_data['title'], $remove_title_html );

						if ( $sandbox_mode_enabled ) {
							update_post_meta( $product_id, '_virastai_acr_sandbox_title', $new_title );
						} else {
							$update_args['post_title'] = $new_title;
						}
						update_post_meta( $product_id, '_virastai_acr_title_generated', '1' );
						$has_changes = true;
						$this->log( "Generated Title for ID $product_id" . ( $sandbox_mode_enabled ? ' [SANDBOX]' : '' ) );
					}

					// Handle Content.
					if ( $process_content && isset( $json_data['content'] ) ) {
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
						$this->log( "Generated Content for ID $product_id" . ( $sandbox_mode_enabled ? ' [SANDBOX]' : '' ) );
					}

					// Handle Excerpt.
					if ( $process_excerpt && isset( $json_data['excerpt'] ) ) {
						$new_excerpt = $json_data['excerpt'];
						if ( $enable_formatting ) {
							$new_excerpt = $this->virastai_acr_apply_formatting( $new_excerpt );
						}

						if ( $sandbox_mode_enabled ) {
							update_post_meta( $product_id, '_virastai_acr_sandbox_excerpt', $new_excerpt );
						} else {
							$update_args['post_excerpt'] = $new_excerpt;
						}
						update_post_meta( $product_id, '_virastai_acr_excerpt_generated', '3' );
						$has_changes = true;
						$this->log( "Generated Excerpt for ID $product_id" . ( $sandbox_mode_enabled ? ' [SANDBOX]' : '' ) );
					}

				} else {
					$this->log( "JSON Decode Error for ID $product_id. Response snippet: " . substr( $response_text, 0, 150 ) );
				}
			}
		} else {
			// --- 4. Fallback: Sequential/Single Paths ---

			// Title Only Path.
			if ( $process_title && ! empty( $title_prompt_inst ) ) {
				$full_prompt = $base_context . "\n\n---\n\n" . $title_prompt_inst . "\n\nIMPORTANT: Output ONLY the title text. Do NOT wrap the title in quotes, introductory text, explanations, or thinking blocks. Output nothing else.";
				$new_title   = $this->virastai_acr_call_api( $full_prompt, false );

				if ( $new_title ) {
					$new_title = $this->virastai_acr_clean_title( $new_title, $remove_title_html );

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
				$full_prompt = $base_context . "\n\n---\n\n" . $content_prompt_inst . "\n\nIMPORTANT: Output ONLY the rewritten content text. Do NOT include introductory text, explanations, or thinking blocks. Output nothing else.";
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

			// Excerpt Only Path.
			if ( $process_excerpt && ! empty( $excerpt_prompt_inst ) ) {
				$full_prompt = $base_context . "\n\n---\n\n" . $excerpt_prompt_inst . "\n\nIMPORTANT: Output ONLY the rewritten excerpt text. Do NOT include introductory text, explanations, or thinking blocks. Output nothing else.";
				$new_excerpt = $this->virastai_acr_call_api( $full_prompt, false );

				if ( $new_excerpt ) {
					if ( $enable_formatting ) {
						$new_excerpt = $this->virastai_acr_apply_formatting( $new_excerpt );
					}

					if ( $sandbox_mode_enabled ) {
						update_post_meta( $product_id, '_virastai_acr_sandbox_excerpt', $new_excerpt );
					} else {
						$update_args['post_excerpt'] = $new_excerpt;
					}
					$has_changes = true;

					update_post_meta( $product_id, '_virastai_acr_excerpt_generated', '3' );
					$this->log( "Generated Excerpt for ID $product_id" . ( $sandbox_mode_enabled ? ' [SANDBOX]' : '' ) );
				}
			}
		}

		// --- 5. Final Update ---
		if ( $has_changes && ! $sandbox_mode_enabled ) {
			if ( count( $update_args ) > 1 ) {
				$result = wp_update_post( $update_args );

				if ( is_wp_error( $result ) ) {
					$this->log( "Error updating post $product_id: " . $result->get_error_message(), 'error' );
					return false;
				}
			}
			return true;
		}

		return $has_changes; // Return true if sandbox meta was updated.
	}

	/**
	 * Clean generated title by stripping HTML and markdown indicators.
	 *
	 * @param string $title       Raw generated title text.
	 * @param bool   $remove_html Whether to strip tags and format indicators.
	 * @return string Cleaned title text.
	 */
	private function virastai_acr_clean_title( $title, $remove_html ) {
		if ( $remove_html ) {
			$title = wp_strip_all_tags( $title );
			$title = preg_replace( '/([#*`_~\[\]\(\)])/', '', $title );
		}
		return trim( $title, "\"' \t\n\r" );
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
		$api_key = get_option( 'virastai_acr_openrouter_api_key', '' );
		$model   = get_option( 'virastai_acr_ai_model', 'google/gemini-2.0-flash-lite-preview-02-05:free' );

		$api_key = trim( $api_key );

		if ( empty( $api_key ) ) {
			$this->log( 'API Key is missing.', 'error' );
			return false;
		}

		if ( empty( self::API_ENDPOINT ) ) {
			$this->log( 'CRITICAL: API Endpoint Constant is empty.', 'error' );
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

		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
			'HTTP-Referer'  => get_site_url(),
			'X-Title'       => 'Virastai Auto Content Rewrite With AI',
		);
		$request_args = array(
			'headers' => $headers,
			'body'    => wp_json_encode( $request_body ),
			'timeout' => 45,
		);

		$this->log(
			sprintf(
				"POST request sent to: %s\nHeaders: %s\nBody: %s",
				self::API_ENDPOINT,
				wp_json_encode( $headers ),
				$request_args['body']
			),
			'api_request'
		);

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 );
		}

		$response = wp_remote_post( self::API_ENDPOINT, $request_args );

		if ( is_wp_error( $response ) ) {
			$this->log( 'API Error: ' . $response->get_error_message(), 'error' );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		$this->log(
			sprintf(
				"Response code: %d\nBody: %s",
				$response_code,
				$response_body
			),
			'api_response'
		);

		if ( 200 !== $response_code ) {
			$this->log( "API returned $response_code - $response_body", 'error' );
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
	 * @param string $type    The type of log.
	 */
	private function log( $message, $type = 'info' ) {
		if ( class_exists( 'Virastai_Acr_Logger' ) ) {
			Virastai_Acr_Logger::log( $type, $message );
		}
	}
}
