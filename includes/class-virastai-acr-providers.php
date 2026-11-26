<?php
/**
 * AI Provider Registry and Protocol Adapter.
 *
 * @package Virastai_Auto_Content_Rewrite_With_AI
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages supported AI providers, credentials, model discovery, and API request/response formatting.
 */
class Virastai_Acr_Providers {

	/**
	 * Default provider slug.
	 */
	const DEFAULT_PROVIDER = 'openrouter';

	/**
	 * Return all supported AI provider definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_providers() {
		return array(
			'openrouter'    => array(
				'label'          => 'OpenRouter',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://openrouter.ai/api/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'sk-or-v1-...',
				'default_model'  => 'google/gemini-2.0-flash-lite-preview-02-05:free',
				'curated_models' => array(
					'google/gemini-2.0-flash-lite-preview-02-05:free' => 'Gemini 2.0 Flash Lite (free)',
					'google/gemma-2-9b-it:free'                      => 'Gemma 2 9B (free)',
					'meta-llama/llama-3-8b-instruct:free'            => 'Llama 3 8B (free)',
				),
				'website'        => 'https://openrouter.ai',
			),
			'openai'        => array(
				'label'          => 'OpenAI Direct',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.openai.com/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'sk-...',
				'default_model'  => 'gpt-4o-mini',
				'curated_models' => array(
					'gpt-4o-mini'  => 'GPT-4o Mini',
					'gpt-4o'       => 'GPT-4o',
					'gpt-4.1-mini' => 'GPT-4.1 Mini',
					'gpt-4.1'      => 'GPT-4.1',
				),
				'website'        => 'https://api.openai.com',
			),
			'gemini'        => array(
				'label'          => 'Google Gemini Direct',
				'protocol'       => 'gemini_native',
				'base_url'       => 'https://generativelanguage.googleapis.com/v1beta',
				'chat_path'      => '/models/{model}:generateContent',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'AIza...',
				'default_model'  => 'gemini-2.5-flash',
				'curated_models' => array(
					'gemini-2.5-flash'      => 'Gemini 2.5 Flash',
					'gemini-2.5-pro'        => 'Gemini 2.5 Pro',
					'gemini-2.0-flash'      => 'Gemini 2.0 Flash',
					'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite',
				),
				'website'        => 'https://generativelanguage.googleapis.com',
			),
			'jina'          => array(
				'label'          => 'Jina AI',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.jina.ai/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'jina_...',
				'default_model'  => 'jina-deepsearch-v1',
				'curated_models' => array(
					'jina-deepsearch-v1' => 'Jina DeepSearch v1',
				),
				'website'        => 'https://jina.ai/',
			),
			'voyage'        => array(
				'label'          => 'Voyage AI',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.voyageai.com/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'pa-...',
				'default_model'  => 'voyage-3',
				'curated_models' => array(
					'voyage-3'      => 'Voyage 3',
					'voyage-3-lite' => 'Voyage 3 Lite',
				),
				'website'        => 'https://www.voyageai.com/',
			),
			'cohere'        => array(
				'label'          => 'Cohere',
				'protocol'       => 'cohere_native',
				'base_url'       => 'https://api.cohere.com/v2',
				'chat_path'      => '/chat',
				'models_path'    => 'https://api.cohere.com/v1/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'cohere-api-key...',
				'default_model'  => 'command-a-03-2025',
				'curated_models' => array(
					'command-a-03-2025' => 'Command A (03-2025)',
					'command-r-plus'    => 'Command R+',
					'command-r'         => 'Command R',
				),
				'website'        => 'https://api.cohere.com',
			),
			'groq'          => array(
				'label'          => 'Groq',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.groq.com/openai/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'gsk_...',
				'default_model'  => 'llama-3.3-70b-versatile',
				'curated_models' => array(
					'llama-3.3-70b-versatile' => 'Llama 3.3 70B Versatile',
					'llama-3.1-8b-instant'    => 'Llama 3.1 8B Instant',
					'mixtral-8x7b-32768'      => 'Mixtral 8x7B',
				),
				'website'        => 'https://api.groq.com',
			),
			'avalai'        => array(
				'label'          => 'AvalAI',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.avalai.ir/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'aa-...',
				'default_model'  => 'gpt-4o-mini',
				'curated_models' => array(
					'gpt-4o-mini'      => 'GPT-4o Mini',
					'gpt-4o'           => 'GPT-4o',
					'gemini-2.0-flash' => 'Gemini 2.0 Flash',
				),
				'website'        => 'https://avalai.ir/',
			),
			'gapgpt'        => array(
				'label'          => 'GapGPT',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.gapgpt.app/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'sk-...',
				'default_model'  => 'gpt-4o-mini',
				'curated_models' => array(
					'gpt-4o-mini' => 'GPT-4o Mini',
					'gpt-4o'      => 'GPT-4o',
				),
				'website'        => 'https://gapgpt.app/',
			),
			'nvidia_nim'    => array(
				'label'          => 'NVIDIA NIM',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://integrate.api.nvidia.com/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'nvapi-...',
				'default_model'  => 'meta/llama-3.1-70b-instruct',
				'curated_models' => array(
					'meta/llama-3.1-70b-instruct' => 'Llama 3.1 70B Instruct',
					'meta/llama-3.1-8b-instruct'  => 'Llama 3.1 8B Instruct',
				),
				'website'        => 'https://build.nvidia.com/',
			),
			'mistral'       => array(
				'label'          => 'Mistral AI',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.mistral.ai/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'mistral-api-key...',
				'default_model'  => 'mistral-small-latest',
				'curated_models' => array(
					'mistral-small-latest' => 'Mistral Small Latest',
					'mistral-large-latest' => 'Mistral Large Latest',
					'open-mistral-nemo'    => 'Mistral Nemo',
				),
				'website'        => 'https://api.mistral.ai',
			),
			'together'      => array(
				'label'          => 'Together AI',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.together.xyz/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'together-api-key...',
				'default_model'  => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
				'curated_models' => array(
					'meta-llama/Llama-3.3-70B-Instruct-Turbo' => 'Llama 3.3 70B Instruct Turbo',
					'Qwen/Qwen2.5-72B-Instruct-Turbo'         => 'Qwen 2.5 72B Instruct Turbo',
				),
				'website'        => 'https://www.together.ai/',
			),
			'fireworks'     => array(
				'label'          => 'Fireworks AI',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.fireworks.ai/inference/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'fw_...',
				'default_model'  => 'accounts/fireworks/models/llama-v3p3-70b-instruct',
				'curated_models' => array(
					'accounts/fireworks/models/llama-v3p3-70b-instruct' => 'Llama 3.3 70B Instruct',
					'accounts/fireworks/models/qwen2p5-72b-instruct'     => 'Qwen 2.5 72B Instruct',
				),
				'website'        => 'https://fireworks.ai/',
			),
			'twelvelabs'    => array(
				'label'          => 'TwelveLabs',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.twelvelabs.io/v1.3',
				'chat_path'      => '/chat/completions',
				'models_path'    => '',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'tlk_...',
				'default_model'  => 'pegasus-1.2',
				'curated_models' => array(
					'pegasus-1.2' => 'Pegasus 1.2',
				),
				'website'        => 'https://www.twelvelabs.io/',
			),
			'xai'           => array(
				'label'          => 'xAI (Grok)',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.x.ai/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'xai-...',
				'default_model'  => 'grok-2-latest',
				'curated_models' => array(
					'grok-2-latest' => 'Grok 2 Latest',
					'grok-3-mini'   => 'Grok 3 Mini',
					'grok-3'        => 'Grok 3',
				),
				'website'        => 'https://x.ai/',
			),
			'dashscope'     => array(
				'label'          => 'Alibaba Cloud Model Studio (Qwen DashScope)',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'sk-...',
				'default_model'  => 'qwen-plus',
				'curated_models' => array(
					'qwen-plus'  => 'Qwen Plus',
					'qwen-turbo' => 'Qwen Turbo',
					'qwen-max'   => 'Qwen Max',
				),
				'website'        => 'https://www.alibabacloud.com/',
			),
			'cloudflare'    => array(
				'label'          => 'Cloudflare Workers AI',
				'protocol'       => 'cloudflare',
				'base_url'       => 'https://api.cloudflare.com/client/v4/accounts/{account_id}/ai/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '',
				'requires_key'   => true,
				'extra_fields'   => array( 'account_id' ),
				'placeholder'    => 'cf-api-token...',
				'default_model'  => '@cf/meta/llama-3.1-8b-instruct',
				'curated_models' => array(
					'@cf/meta/llama-3.1-8b-instruct'  => 'Llama 3.1 8B Instruct',
					'@cf/meta/llama-3.3-70b-instruct-fp8-fast' => 'Llama 3.3 70B Instruct FP8 Fast',
					'@cf/qwen/qwen1.5-14b-chat-awq'   => 'Qwen 1.5 14B Chat AWQ',
				),
				'website'        => 'https://www.cloudflare.com/',
			),
			'arvancloud'    => array(
				'label'          => 'ArvanCloud AI',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.arvancloudai.ir/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'Apikey ...',
				'default_model'  => 'gpt-4o-mini',
				'curated_models' => array(
					'gpt-4o-mini' => 'GPT-4o Mini',
					'gpt-4o'      => 'GPT-4o',
				),
				'website'        => 'https://www.arvancloud.ir/',
			),
			'metisai'       => array(
				'label'          => 'MetisAI',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.metisai.ir/openai/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'tpsg-...',
				'default_model'  => 'gpt-4o-mini',
				'curated_models' => array(
					'gpt-4o-mini' => 'GPT-4o Mini',
					'gpt-4o'      => 'GPT-4o',
				),
				'website'        => 'https://metisai.ir/',
			),
			'liara'         => array(
				'label'          => 'Liara AI',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://ai.liara.ir/api/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'liara-api-key...',
				'default_model'  => 'openai/gpt-4o-mini',
				'curated_models' => array(
					'openai/gpt-4o-mini' => 'GPT-4o Mini',
					'openai/gpt-4o'      => 'GPT-4o',
				),
				'website'        => 'https://liara.ir/',
			),
			'heroai'        => array(
				'label'          => 'HeroAI',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.heroai.ir/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'sk-...',
				'default_model'  => 'gpt-4o-mini',
				'curated_models' => array(
					'gpt-4o-mini' => 'GPT-4o Mini',
					'gpt-4o'      => 'GPT-4o',
				),
				'website'        => 'https://heroai.ir/',
			),
			'talkbot'       => array(
				'label'          => 'TalkBot',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.talkbot.ir/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'sk-...',
				'default_model'  => 'gpt-4o-mini',
				'curated_models' => array(
					'gpt-4o-mini' => 'GPT-4o Mini',
					'gpt-4o'      => 'GPT-4o',
				),
				'website'        => 'https://talkbot.ir/',
			),
			'cupai'         => array(
				'label'          => 'CupAI',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.cupai.ir/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'sk-...',
				'default_model'  => 'gpt-4o-mini',
				'curated_models' => array(
					'gpt-4o-mini' => 'GPT-4o Mini',
					'gpt-4o'      => 'GPT-4o',
				),
				'website'        => 'https://cupai.ir/',
			),
			'hooshang'      => array(
				'label'          => 'Hooshang',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://hushang.cloud/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'sk-...',
				'default_model'  => 'gpt-4o-mini',
				'curated_models' => array(
					'gpt-4o-mini' => 'GPT-4o Mini',
					'gpt-4o'      => 'GPT-4o',
				),
				'website'        => 'https://hushang.cloud/',
			),
			'hormouz'       => array(
				'label'          => 'Hormouz',
				'protocol'       => 'openai_compat',
				'base_url'       => 'https://api.hormouz.net/v1',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => true,
				'extra_fields'   => array(),
				'placeholder'    => 'sk-...',
				'default_model'  => 'gpt-4o-mini',
				'curated_models' => array(
					'gpt-4o-mini' => 'GPT-4o Mini',
					'gpt-4o'      => 'GPT-4o',
				),
				'website'        => 'https://api.hormouz.net',
			),
			'custom_ollama' => array(
				'label'          => 'Custom OpenAI-Compatible Endpoint / Self-Hosted Ollama',
				'protocol'       => 'openai_compat',
				'base_url'       => '',
				'chat_path'      => '/chat/completions',
				'models_path'    => '/models',
				'requires_key'   => false,
				'extra_fields'   => array( 'custom_base_url' ),
				'placeholder'    => 'Optional API key (leave empty for local Ollama)',
				'default_model'  => 'llama3.2',
				'curated_models' => array(
					'llama3.2' => 'Llama 3.2 (Ollama)',
					'qwen2.5'  => 'Qwen 2.5 (Ollama)',
					'mistral'  => 'Mistral (Ollama)',
				),
				'website'        => '',
			),
		);
	}

	/**
	 * Get a single provider definition.
	 *
	 * @param string $provider_id Provider slug.
	 * @return array<string, mixed>
	 */
	public static function get_provider( $provider_id ) {
		$providers = self::get_providers();
		if ( isset( $providers[ $provider_id ] ) ) {
			return $providers[ $provider_id ];
		}
		return $providers[ self::DEFAULT_PROVIDER ];
	}

	/**
	 * Get the currently active provider slug.
	 *
	 * @return string
	 */
	public static function get_active_provider_id() {
		$provider_id = (string) get_option( 'virastai_acr_ai_provider', self::DEFAULT_PROVIDER );
		$providers   = self::get_providers();
		return isset( $providers[ $provider_id ] ) ? $provider_id : self::DEFAULT_PROVIDER;
	}

	/**
	 * Get API key for a provider, falling back to legacy OpenRouter option.
	 *
	 * @param string|null $provider_id Provider slug or null for active provider.
	 * @return string
	 */
	public static function get_api_key( $provider_id = null ) {
		$provider_id = $provider_id ? $provider_id : self::get_active_provider_id();
		$keys        = get_option( 'virastai_acr_provider_api_keys', array() );

		if ( is_array( $keys ) && isset( $keys[ $provider_id ] ) && '' !== trim( (string) $keys[ $provider_id ] ) ) {
			return trim( (string) $keys[ $provider_id ] );
		}

		if ( self::DEFAULT_PROVIDER === $provider_id ) {
			return trim( (string) get_option( 'virastai_acr_openrouter_api_key', '' ) );
		}

		return '';
	}

	/**
	 * Get selected Model ID for a provider, falling back to legacy option or provider default.
	 *
	 * @param string|null $provider_id Provider slug or null for active provider.
	 * @return string
	 */
	public static function get_model( $provider_id = null ) {
		$provider_id = $provider_id ? $provider_id : self::get_active_provider_id();
		$models      = get_option( 'virastai_acr_provider_models', array() );

		if ( is_array( $models ) && isset( $models[ $provider_id ] ) && '' !== trim( (string) $models[ $provider_id ] ) ) {
			return trim( (string) $models[ $provider_id ] );
		}

		if ( self::DEFAULT_PROVIDER === $provider_id ) {
			$legacy_model = trim( (string) get_option( 'virastai_acr_ai_model', '' ) );
			if ( '' !== $legacy_model ) {
				return $legacy_model;
			}
		}

		$provider = self::get_provider( $provider_id );
		return $provider['default_model'];
	}

	/**
	 * Get validated custom base URL for Custom/Ollama provider.
	 *
	 * @return string
	 */
	public static function get_custom_base_url() {
		$raw = trim( (string) get_option( 'virastai_acr_custom_base_url', '' ) );
		return self::validate_custom_base_url( $raw );
	}

	/**
	 * Get Cloudflare Account ID.
	 *
	 * @return string
	 */
	public static function get_cloudflare_account_id() {
		return trim( (string) get_option( 'virastai_acr_cloudflare_account_id', '' ) );
	}

	/**
	 * Validate a custom OpenAI-compatible or local Ollama base URL.
	 *
	 * Allows https:// URLs, or http:// only for localhost / 127.0.0.1 / [::1].
	 *
	 * @param string $url Raw URL.
	 * @return string Validated URL without trailing slash, or empty string if invalid.
	 */
	public static function validate_custom_base_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$scheme = strtolower( $parts['scheme'] );
		$host   = strtolower( $parts['host'] );

		if ( 'https' === $scheme ) {
			return untrailingslashit( $url );
		}

		if ( 'http' === $scheme && in_array( $host, array( 'localhost', '127.0.0.1', '[::1]', '::1' ), true ) ) {
			return untrailingslashit( $url );
		}

		return '';
	}

	/**
	 * Check whether the given URL is a local Ollama/localhost endpoint.
	 *
	 * @param string $url Validated URL.
	 * @return bool
	 */
	public static function is_local_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}
		return in_array( strtolower( $parts['host'] ), array( 'localhost', '127.0.0.1', '[::1]', '::1' ), true );
	}

	/**
	 * Check whether a provider has all required configuration to make API calls.
	 *
	 * @param string|null $provider_id Provider slug or null for active provider.
	 * @return bool
	 */
	public static function is_configured( $provider_id = null ) {
		$provider_id = $provider_id ? $provider_id : self::get_active_provider_id();
		$provider    = self::get_provider( $provider_id );
		$api_key     = self::get_api_key( $provider_id );

		if ( 'custom_ollama' === $provider_id ) {
			$base_url = self::get_custom_base_url();
			if ( '' === $base_url ) {
				return false;
			}
			// Require key for remote custom endpoints; optional for local Ollama.
			if ( ! self::is_local_url( $base_url ) && '' === $api_key ) {
				return false;
			}
			return true;
		}

		if ( 'cloudflare' === $provider_id && '' === self::get_cloudflare_account_id() ) {
			return false;
		}

		if ( ! empty( $provider['requires_key'] ) && '' === $api_key ) {
			return false;
		}

		return true;
	}

	/**
	 * Resolve effective base URL for a provider.
	 *
	 * @param string $provider_id Provider slug.
	 * @return string
	 */
	public static function resolve_base_url( $provider_id ) {
		if ( 'custom_ollama' === $provider_id ) {
			return self::get_custom_base_url();
		}

		$provider = self::get_provider( $provider_id );
		$base_url = $provider['base_url'];

		if ( 'cloudflare' === $provider_id ) {
			$account_id = rawurlencode( self::get_cloudflare_account_id() );
			$base_url   = str_replace( '{account_id}', $account_id, $base_url );
		}

		return untrailingslashit( $base_url );
	}

	/**
	 * Fetch models for a provider and cache for 1 hour, falling back to curated models.
	 *
	 * @param string $provider_id   Provider slug.
	 * @param bool   $force_refresh Bypass transient cache when true.
	 * @return array<string, string> Map of model_id => label.
	 */
	public static function fetch_models( $provider_id, $force_refresh = false ) {
		$provider  = self::get_provider( $provider_id );
		$curated   = isset( $provider['curated_models'] ) ? $provider['curated_models'] : array();
		$cache_key = 'virastai_acr_models_cache_' . sanitize_key( $provider_id );

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) && ! empty( $cached ) ) {
				return $cached;
			}
		}

		if ( empty( $provider['models_path'] ) ) {
			return $curated;
		}

		$base_url = self::resolve_base_url( $provider_id );
		if ( '' === $base_url && 0 !== strpos( $provider['models_path'], 'http' ) ) {
			return $curated;
		}

		$api_key = self::get_api_key( $provider_id );
		if ( '' === $api_key && self::DEFAULT_PROVIDER !== $provider_id && 'custom_ollama' !== $provider_id ) {
			return $curated;
		}

		$url = 0 === strpos( $provider['models_path'], 'http' )
			? $provider['models_path']
			: $base_url . $provider['models_path'];

		$headers = array( 'Accept' => 'application/json' );
		if ( '' !== $api_key ) {
			if ( 'gemini_native' === $provider['protocol'] ) {
				$headers['x-goog-api-key'] = $api_key;
			} else {
				$headers['Authorization'] = 'Bearer ' . $api_key;
			}
		}

		if ( class_exists( 'Virastai_Acr_Logger' ) ) {
			Virastai_Acr_Logger::log( 'api_request', "GET request sent to: $url" );
		}

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $headers,
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( class_exists( 'Virastai_Acr_Logger' ) ) {
				Virastai_Acr_Logger::log( 'error', 'Models API Error: ' . $response->get_error_message() );
			}
			return $curated;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( class_exists( 'Virastai_Acr_Logger' ) ) {
			$logged_body = strlen( $body ) > 1000 ? substr( $body, 0, 1000 ) . '... [body truncated]' : $body;
			Virastai_Acr_Logger::log( 'api_response', "Response code: $code\nBody: " . $logged_body );
		}

		if ( 200 !== $code ) {
			return $curated;
		}

		$data   = json_decode( $body, true );
		$models = self::parse_models_payload( $provider['protocol'], $data );

		if ( ! empty( $models ) ) {
			asort( $models );
			set_transient( $cache_key, $models, HOUR_IN_SECONDS );
			return $models;
		}

		return $curated;
	}

	/**
	 * Parse model listing payload according to provider protocol.
	 *
	 * @param string $protocol Provider protocol.
	 * @param mixed  $data     Decoded JSON array.
	 * @return array<string, string>
	 */
	private static function parse_models_payload( $protocol, $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}

		$models = array();

		if ( 'gemini_native' === $protocol && isset( $data['models'] ) && is_array( $data['models'] ) ) {
			foreach ( $data['models'] as $item ) {
				if ( empty( $item['name'] ) ) {
					continue;
				}
				$id           = preg_replace( '#^models/#', '', (string) $item['name'] );
				$display      = isset( $item['displayName'] ) ? $item['displayName'] : $id;
				$models[ $id ] = $display . ' (' . $id . ')';
			}
			return $models;
		}

		if ( 'cohere_native' === $protocol && isset( $data['models'] ) && is_array( $data['models'] ) ) {
			foreach ( $data['models'] as $item ) {
				if ( empty( $item['name'] ) ) {
					continue;
				}
				$id            = (string) $item['name'];
				$models[ $id ] = $id;
			}
			return $models;
		}

		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			foreach ( $data['data'] as $item ) {
				if ( empty( $item['id'] ) ) {
					continue;
				}
				$id            = (string) $item['id'];
				$label         = isset( $item['name'] ) && '' !== $item['name'] ? $item['name'] . ' (' . $id . ')' : $id;
				$models[ $id ] = $label;
			}
		}

		return $models;
	}

	/**
	 * Build an HTTP request specification for the given provider.
	 *
	 * @param string $provider_id  Provider slug.
	 * @param string $prompt       Prompt string.
	 * @param bool   $is_json_mode Whether to request JSON output.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function build_request( $provider_id, $prompt, $is_json_mode = false ) {
		$provider = self::get_provider( $provider_id );
		$api_key  = self::get_api_key( $provider_id );
		$model    = self::get_model( $provider_id );
		$base_url = self::resolve_base_url( $provider_id );

		if ( '' === $base_url ) {
			return new WP_Error( 'virastai_acr_invalid_base_url', 'Provider Base URL is missing or invalid.' );
		}

		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( 'gemini_native' === $provider['protocol'] ) {
			$url                    = $base_url . str_replace( '{model}', rawurlencode( $model ), $provider['chat_path'] );
			$headers['x-goog-api-key'] = $api_key;

			$body = array(
				'contents' => array(
					array(
						'role'  => 'user',
						'parts' => array(
							array( 'text' => $prompt ),
						),
					),
				),
			);

			if ( $is_json_mode ) {
				$body['generationConfig'] = array(
					'responseMimeType' => 'application/json',
				);
			}

			return array(
				'url'     => $url,
				'headers' => $headers,
				'body'    => $body,
			);
		}

		if ( '' !== $api_key ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		if ( 'openrouter' === $provider_id ) {
			$headers['HTTP-Referer'] = function_exists( 'get_site_url' ) ? get_site_url() : '';
			$headers['X-Title']      = 'Virastai Auto Content Rewrite With AI';
		}

		$body = array(
			'model'    => $model,
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);

		if ( $is_json_mode ) {
			$body['response_format'] = array( 'type' => 'json_object' );
		}

		return array(
			'url'     => $base_url . $provider['chat_path'],
			'headers' => $headers,
			'body'    => $body,
		);
	}

	/**
	 * Parse generated text from a provider API response body.
	 *
	 * @param string $provider_id   Provider slug.
	 * @param string $response_body Raw JSON response body.
	 * @return string|false
	 */
	public static function parse_response( $provider_id, $response_body ) {
		$provider = self::get_provider( $provider_id );
		$data     = json_decode( $response_body, true );

		if ( ! is_array( $data ) ) {
			return false;
		}

		if ( 'gemini_native' === $provider['protocol'] ) {
			if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
				return (string) $data['candidates'][0]['content']['parts'][0]['text'];
			}
			return false;
		}

		if ( 'cohere_native' === $provider['protocol'] ) {
			if ( isset( $data['message']['content'][0]['text'] ) ) {
				return (string) $data['message']['content'][0]['text'];
			}
			if ( isset( $data['text'] ) ) {
				return (string) $data['text'];
			}
			return false;
		}

		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			return (string) $data['choices'][0]['message']['content'];
		}

		return false;
	}

	/**
	 * Mask sensitive headers before writing API request logs.
	 *
	 * @param array<string, string> $headers Request headers.
	 * @return array<string, string>
	 */
	public static function mask_headers_for_log( $headers ) {
		$masked = $headers;
		foreach ( array( 'Authorization', 'x-goog-api-key' ) as $secret_header ) {
			if ( isset( $masked[ $secret_header ] ) && '' !== $masked[ $secret_header ] ) {
				$masked[ $secret_header ] = '[REDACTED]';
			}
		}
		return $masked;
	}
}
