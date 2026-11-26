<?php
/**
 * Unit tests for Virastai_Acr_Providers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );

	$test_options = array();

	function get_option( $key, $default = false ) {
		global $test_options;
		return isset( $test_options[ $key ] ) ? $test_options[ $key ] : $default;
	}

	function wp_parse_url( $url ) {
		return parse_url( $url );
	}

	function untrailingslashit( $string ) {
		return rtrim( $string, '/\\' );
	}

	function get_site_url() {
		return 'https://example.test';
	}

	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			public $code;
			public $message;
			public function __construct( $code = '', $message = '' ) {
				$this->code    = $code;
				$this->message = $message;
			}
			public function get_error_message() {
				return $this->message;
			}
		}
	}

	function is_wp_error( $thing ) {
		return ( $thing instanceof WP_Error );
	}
}

require_once __DIR__ . '/../includes/class-virastai-acr-providers.php';

// 1. All 26 providers are registered.
$providers = Virastai_Acr_Providers::get_providers();
$expected_slugs = array(
	'openrouter', 'openai', 'gemini', 'jina', 'voyage', 'cohere', 'groq',
	'avalai', 'gapgpt', 'nvidia_nim', 'mistral', 'together', 'fireworks',
	'twelvelabs', 'xai', 'dashscope', 'cloudflare', 'arvancloud', 'metisai',
	'liara', 'heroai', 'talkbot', 'cupai', 'hooshang', 'hormouz', 'custom_ollama',
);
assert( count( $providers ) === 26, 'Expected 26 providers' );
foreach ( $expected_slugs as $slug ) {
	assert( isset( $providers[ $slug ] ), "Missing provider: $slug" );
}
echo "PASS: test_all_26_providers_registered\n";

// 2. Legacy OpenRouter fallback & per-provider isolation.
$test_options = array(
	'virastai_acr_openrouter_api_key' => 'legacy-or-key',
	'virastai_acr_ai_model'           => 'legacy-or-model',
	'virastai_acr_provider_api_keys'  => array( 'openai' => 'sk-openai-123' ),
	'virastai_acr_provider_models'    => array( 'openai' => 'gpt-4o' ),
);
assert( Virastai_Acr_Providers::get_api_key( 'openrouter' ) === 'legacy-or-key', 'OpenRouter legacy key fallback failed' );
assert( Virastai_Acr_Providers::get_model( 'openrouter' ) === 'legacy-or-model', 'OpenRouter legacy model fallback failed' );
assert( Virastai_Acr_Providers::get_api_key( 'openai' ) === 'sk-openai-123', 'Per-provider OpenAI key failed' );
assert( Virastai_Acr_Providers::get_model( 'openai' ) === 'gpt-4o', 'Per-provider OpenAI model failed' );
assert( Virastai_Acr_Providers::get_api_key( 'groq' ) === '', 'Unconfigured provider should have empty key' );
echo "PASS: test_legacy_fallback_and_per_provider_isolation\n";

// 3. Custom Base URL validation (HTTPS + local Ollama vs insecure HTTP).
assert( Virastai_Acr_Providers::validate_custom_base_url( 'https://my-llm.example.com/v1/' ) === 'https://my-llm.example.com/v1', 'HTTPS URL should be valid' );
assert( Virastai_Acr_Providers::validate_custom_base_url( 'http://localhost:11434/v1/' ) === 'http://localhost:11434/v1', 'Localhost Ollama HTTP should be valid' );
assert( Virastai_Acr_Providers::validate_custom_base_url( 'http://127.0.0.1:11434/v1' ) === 'http://127.0.0.1:11434/v1', '127.0.0.1 Ollama HTTP should be valid' );
assert( Virastai_Acr_Providers::validate_custom_base_url( 'http://remote-server.com/v1' ) === '', 'Remote HTTP URL must be rejected' );
assert( Virastai_Acr_Providers::validate_custom_base_url( 'not-a-url' ) === '', 'Invalid URL must be rejected' );
echo "PASS: test_custom_base_url_validation\n";

// 4. Conditional configuration checks (Cloudflare & Custom/Ollama).
$test_options = array(
	'virastai_acr_ai_provider'       => 'cloudflare',
	'virastai_acr_provider_api_keys' => array( 'cloudflare' => 'cf-token' ),
);
assert( Virastai_Acr_Providers::is_configured( 'cloudflare' ) === false, 'Cloudflare without account_id must not be configured' );
$test_options['virastai_acr_cloudflare_account_id'] = 'acc-123';
assert( Virastai_Acr_Providers::is_configured( 'cloudflare' ) === true, 'Cloudflare with account_id and token must be configured' );

$test_options = array(
	'virastai_acr_ai_provider'    => 'custom_ollama',
	'virastai_acr_custom_base_url' => 'http://localhost:11434/v1',
);
assert( Virastai_Acr_Providers::is_configured( 'custom_ollama' ) === true, 'Local Ollama without key must be configured' );
$test_options['virastai_acr_custom_base_url'] = 'https://remote.example.com/v1';
assert( Virastai_Acr_Providers::is_configured( 'custom_ollama' ) === false, 'Remote custom endpoint without key must not be configured' );
echo "PASS: test_conditional_is_configured\n";

// 5. Request building and response parsing across protocols.
$test_options = array(
	'virastai_acr_provider_api_keys'      => array(
		'gemini'     => 'gem-key',
		'cohere'     => 'coh-key',
		'cloudflare' => 'cf-key',
	),
	'virastai_acr_provider_models'        => array(
		'gemini'     => 'gemini-2.5-flash',
		'cohere'     => 'command-a-03-2025',
		'cloudflare' => '@cf/meta/llama-3.1-8b-instruct',
	),
	'virastai_acr_cloudflare_account_id'  => 'acc-999',
);

$gem_req = Virastai_Acr_Providers::build_request( 'gemini', 'Hello', true );
assert( $gem_req['url'] === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', 'Gemini URL mismatch' );
assert( $gem_req['headers']['x-goog-api-key'] === 'gem-key', 'Gemini header mismatch' );
assert( $gem_req['body']['generationConfig']['responseMimeType'] === 'application/json', 'Gemini JSON mode mismatch' );
assert( Virastai_Acr_Providers::parse_response( 'gemini', json_encode( array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => 'Gem OK' ) ) ) ) ) ) ) ) === 'Gem OK', 'Gemini parse mismatch' );

$coh_req = Virastai_Acr_Providers::build_request( 'cohere', 'Hello', false );
assert( $coh_req['url'] === 'https://api.cohere.com/v2/chat', 'Cohere URL mismatch' );
assert( Virastai_Acr_Providers::parse_response( 'cohere', json_encode( array( 'message' => array( 'content' => array( array( 'text' => 'Cohere OK' ) ) ) ) ) ) === 'Cohere OK', 'Cohere parse mismatch' );

$cf_req = Virastai_Acr_Providers::build_request( 'cloudflare', 'Hello', false );
assert( $cf_req['url'] === 'https://api.cloudflare.com/client/v4/accounts/acc-999/ai/v1/chat/completions', 'Cloudflare URL mismatch' );
assert( Virastai_Acr_Providers::parse_response( 'cloudflare', json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'CF OK' ) ) ) ) ) ) === 'CF OK', 'Cloudflare parse mismatch' );
echo "PASS: test_protocol_request_and_response_adapters\n";

// 6. Header masking for logs.
$masked = Virastai_Acr_Providers::mask_headers_for_log( array( 'Authorization' => 'Bearer secret', 'x-goog-api-key' => 'secret2', 'Content-Type' => 'application/json' ) );
assert( $masked['Authorization'] === '[REDACTED]', 'Authorization must be redacted' );
assert( $masked['x-goog-api-key'] === '[REDACTED]', 'x-goog-api-key must be redacted' );
assert( $masked['Content-Type'] === 'application/json', 'Non-secret header must remain' );
echo "PASS: test_header_masking\n";

echo "All provider tests passed successfully.\n";
