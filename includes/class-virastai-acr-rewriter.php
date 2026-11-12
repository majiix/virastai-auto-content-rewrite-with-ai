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
	public function virastai_acr_generate_content( $product_id, $post = null, $target_type = '', $force = false ) {
		if ( ! $post ) {
			$post = get_post( $product_id );
		}
		if ( ! $post ) {
			return false;
		}

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

		$title_is_done   = in_array( $title_already_done, array( '1', '2', '3' ), true );
		$content_is_done = in_array( $content_already_done, array( '1', '2', '3' ), true );
		$excerpt_is_done = in_array( $excerpt_already_done, array( '1', '2', '3' ), true );

		$process_title   = false;
		$process_content = false;
		$process_excerpt = false;

		if ( empty( $target_type ) || 'all' === $target_type ) {
			$process_title   = $enable_title_rewrite && ( $force || ! $title_is_done );
			$process_content = $enable_content_rewrite && ( $force || ! $content_is_done );
			$process_excerpt = $enable_excerpt_rewrite && ( $force || ! $excerpt_is_done );
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
			update_post_meta( $product_id, '_virastai_acr_title_generated', 'skipped' );
			$this->log( "Skipping Title for ID $product_id: Content is empty.", 'warning' );
		}

		// Validation for Content.
		if ( $process_content && $skip_incomplete_content && empty( trim( $current_content ) ) ) {
			$process_content = false;
			update_post_meta( $product_id, '_virastai_acr_content_generated', 'skipped' );
			$this->log( "Skipping Content for ID $product_id: Content is empty.", 'warning' );
		}

		// Validation for Excerpt.
		if ( $process_excerpt && $skip_incomplete_excerpt && empty( trim( $current_content ) ) ) {
			$process_excerpt = false;
			update_post_meta( $product_id, '_virastai_acr_excerpt_generated', 'skipped' );
			$this->log( "Skipping Excerpt for ID $product_id: Content is empty.", 'warning' );
		}

		$process_count = ( $process_title ? 1 : 0 ) + ( $process_content ? 1 : 0 ) + ( $process_excerpt ? 1 : 0 );
		if ( 0 === $process_count ) {
			$reasons = array();
			if ( ! $enable_title_rewrite && ! $enable_content_rewrite && ! $enable_excerpt_rewrite ) {
				$reasons[] = 'All rewrite options (Title, Content, Excerpt) are disabled in settings.';
			} else {
				if ( empty( trim( $current_content ) ) ) {
					$reasons[] = 'Post content is empty and Skip Incomplete is active.';
				}
				if ( $title_is_done && $content_is_done ) {
					$reasons[] = 'All enabled sections for this post were already marked as generated.';
				}
			}
			$reason_str = ! empty( $reasons ) ? implode( ' ', $reasons ) : 'No eligible sections to rewrite.';
			$this->log( sprintf( 'Post ID %d ("%s") skipped: %s', $product_id, $current_title, $reason_str ), 'warning' );
			return false;
		}

		// Prepare images for re-insertion later.
		$existing_images = '';
		if ( $preserve_images ) {
			preg_match_all( '/<img[^>]+>/i', $current_content, $images );
			$existing_images = isset( $images[0] ) ? implode( "\n", $images[0] ) : '';
		}

		// --- 3. Combined JSON Path (Single API Call for multiple fields) ---
		$staged_meta = array();

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
							update_post_meta( $product_id, '_virastai_acr_title_generated', '1' );
						} else {
							$update_args['post_title'] = $new_title;
							$staged_meta['_virastai_acr_title_generated'] = '1';
						}
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
							update_post_meta( $product_id, '_virastai_acr_content_generated', '2' );
						} else {
							$update_args['post_content'] = $new_content;
							$staged_meta['_virastai_acr_content_generated'] = '2';
						}
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
							update_post_meta( $product_id, '_virastai_acr_excerpt_generated', '3' );
						} else {
							$update_args['post_excerpt'] = $new_excerpt;
							$staged_meta['_virastai_acr_excerpt_generated'] = '3';
						}
						$has_changes = true;
						$this->log( "Generated Excerpt for ID $product_id" . ( $sandbox_mode_enabled ? ' [SANDBOX]' : '' ) );
					}

				} else {
					$this->log( "JSON Decode Error for ID $product_id. Response snippet: " . substr( $response_text, 0, 150 ), 'error' );
				}
			} else {
				$this->log( "Failed to obtain AI response for combined prompt on Post ID $product_id.", 'error' );
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
						update_post_meta( $product_id, '_virastai_acr_title_generated', '1' );
					} else {
						$update_args['post_title'] = $new_title;
						$staged_meta['_virastai_acr_title_generated'] = '1';
					}
					$has_changes = true;
					$this->log( "Generated Title for ID $product_id" . ( $sandbox_mode_enabled ? ' [SANDBOX]' : '' ) );
				} else {
					$this->log( "Failed to obtain AI response for Title on Post ID $product_id.", 'error' );
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
						update_post_meta( $product_id, '_virastai_acr_content_generated', '2' );
					} else {
						$update_args['post_content'] = $new_content;
						$staged_meta['_virastai_acr_content_generated'] = '2';
					}
					$has_changes = true;
					$this->log( "Generated Content for ID $product_id" . ( $sandbox_mode_enabled ? ' [SANDBOX]' : '' ) );
				} else {
					$this->log( "Failed to obtain AI response for Content on Post ID $product_id.", 'error' );
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
						update_post_meta( $product_id, '_virastai_acr_excerpt_generated', '3' );
					} else {
						$update_args['post_excerpt'] = $new_excerpt;
						$staged_meta['_virastai_acr_excerpt_generated'] = '3';
					}
					$has_changes = true;
					$this->log( "Generated Excerpt for ID $product_id" . ( $sandbox_mode_enabled ? ' [SANDBOX]' : '' ) );
				} else {
					$this->log( "Failed to obtain AI response for Excerpt on Post ID $product_id.", 'error' );
				}
			}
		}

		// --- 5. Final Update ---
		if ( $has_changes ) {
			delete_post_meta( $product_id, '_virastai_acr_retry_count' );
			if ( ! $sandbox_mode_enabled ) {
				if ( count( $update_args ) > 1 ) {
					$original_user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
					$switched_user    = false;

					if ( function_exists( 'current_user_can' ) && ! current_user_can( 'edit_post', $product_id ) ) {
						$target_user_id = 0;
						if ( ! empty( $post->post_author ) && function_exists( 'user_can' ) && user_can( (int) $post->post_author, 'edit_post', $product_id ) ) {
							$target_user_id = (int) $post->post_author;
						} else {
							$admins = function_exists( 'get_users' ) ? get_users(
								array(
									'role'   => 'administrator',
									'number' => 1,
									'fields' => 'ID',
								)
							) : array();
							if ( ! empty( $admins ) ) {
								$target_user_id = (int) $admins[0];
							}
						}

						if ( $target_user_id > 0 && function_exists( 'wp_set_current_user' ) ) {
							wp_set_current_user( $target_user_id );
							$switched_user = true;
						}
					}

					try {
						$result = wp_update_post( $update_args, true );
					} finally {
						if ( $switched_user && function_exists( 'wp_set_current_user' ) ) {
							wp_set_current_user( $original_user_id );
						}
					}

					if ( is_wp_error( $result ) || 0 === $result ) {
						$error_msg = is_wp_error( $result ) ? $result->get_error_message() : 'wp_update_post failed';
						$this->log( "Error updating post $product_id: " . $error_msg, 'error' );
						return false;
					}

					// Update generation status meta ONLY AFTER post is successfully saved in database
					foreach ( $staged_meta as $m_key => $m_val ) {
						update_post_meta( $product_id, $m_key, $m_val );
					}
				}
				return true;
			}
			return $has_changes; // Return true if sandbox meta was updated.
		}

		// Handle Failures & Retries.
		if ( $process_count > 0 && ! $has_changes ) {
			$retry_count = (int) get_post_meta( $product_id, '_virastai_acr_retry_count', true );
			$retry_count++;
			if ( $retry_count >= 3 ) {
				if ( $process_title ) {
					update_post_meta( $product_id, '_virastai_acr_title_generated', 'failed' );
				}
				if ( $process_content ) {
					update_post_meta( $product_id, '_virastai_acr_content_generated', 'failed' );
				}
				if ( $process_excerpt ) {
					update_post_meta( $product_id, '_virastai_acr_excerpt_generated', 'failed' );
				}
				delete_post_meta( $product_id, '_virastai_acr_retry_count' );
				$this->log( "Permanently failed processing for ID $product_id after 3 attempts.", 'error' );
			} else {
				update_post_meta( $product_id, '_virastai_acr_retry_count', $retry_count );
				$this->log( "Failed processing for ID $product_id. Attempt $retry_count of 3.", 'warning' );
			}
		}

		return false;
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
			$this->log( 'API Key is missing. Please configure your OpenRouter API Key in settings.', 'error' );
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
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Extended execution time is required for API requests.
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

		$error_detail = 'No content found in API response.';
		if ( isset( $data['error']['message'] ) ) {
			$error_detail = 'Provider error: ' . $data['error']['message'];
		} elseif ( empty( $response_body ) ) {
			$error_detail = 'Received empty response body from API.';
		} elseif ( is_string( $response_body ) ) {
			$error_detail = 'Payload snippet: ' . substr( $response_body, 0, 150 );
		}
		$this->log( 'API Response parsing failed. ' . $error_detail, 'error' );

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
