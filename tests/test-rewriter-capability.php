<?php
/**
 * Test user capability switching in Virastai_Acr_Rewriter during cron/unauthenticated context.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );

	$test_options = array(
		'virastai_acr_enable_title_rewrite'   => 0,
		'virastai_acr_enable_content_rewrite' => 1,
		'virastai_acr_enable_excerpt_rewrite' => 0,
		'virastai_acr_openrouter_api_key'     => 'test-key',
		'virastai_acr_prompt_template'        => 'rewrite: {post_content}',
	);

	$current_user_id = 0;
	$user_switches   = array();
	$update_called_with_user = null;

	function get_option( $key, $default = false ) {
		global $test_options;
		return isset( $test_options[ $key ] ) ? $test_options[ $key ] : $default;
	}

	function get_post_meta( $id, $key, $single = true ) {
		return '';
	}

	function update_post_meta( $id, $key, $val ) {
		return true;
	}

	function delete_post_meta( $id, $key ) {
		return true;
	}

	function get_the_title( $id ) {
		return 'Sample Post Title';
	}

	function get_current_user_id() {
		global $current_user_id;
		return $current_user_id;
	}

	function wp_set_current_user( $id ) {
		global $current_user_id, $user_switches;
		$user_switches[] = $id;
		$current_user_id = $id;
		return true;
	}

	function current_user_can( $cap, $post_id ) {
		global $current_user_id;
		// User 0 (cron / unauthenticated) cannot edit posts
		return $current_user_id > 0;
	}

	function user_can( $user_id, $cap, $post_id ) {
		// Mock: user 42 has capability, user 0 does not
		return $user_id === 42 || $user_id === 99;
	}

	function get_users( $args ) {
		// Mock admin user ID 99
		return array( 99 );
	}

	function get_site_url() {
		return 'https://example.com';
	}

	function wp_json_encode( $data ) {
		return json_encode( $data );
	}

	function wp_remote_post( $url, $args ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode(
				array(
					'choices' => array(
						array(
							'message' => array(
								'content' => 'Rewritten content result',
							),
						),
					),
				)
			),
		);
	}

	function is_wp_error( $thing ) {
		return false;
	}

	function wp_remote_retrieve_response_code( $response ) {
		return 200;
	}

	function wp_remote_retrieve_body( $response ) {
		return $response['body'];
	}

	function wp_update_post( $args, $wp_error = false ) {
		global $current_user_id, $update_called_with_user;
		$update_called_with_user = $current_user_id;
		return $args['ID'];
	}
}

require_once __DIR__ . '/../includes/class-virastai-acr-rewriter.php';

$rewriter = new Virastai_Acr_Rewriter();

// --- Test 1: Typical Input - Post has valid author (ID 42) and cron runs as user 0 ---
$current_user_id = 0;
$user_switches = array();
$update_called_with_user = null;

$post = new stdClass();
$post->ID = 231;
$post->post_author = 42;
$post->post_content = 'Original Content';
$post->post_excerpt = '';

$res = $rewriter->virastai_acr_generate_content( 231, $post, 'content', true );

assert( $res === true, 'Rewrite should succeed' );
assert( $update_called_with_user === 42, "wp_update_post must be executed as post author 42, got: $update_called_with_user" );
assert( $current_user_id === 0, "Original user (0) must be restored after update, got: $current_user_id" );
assert( in_array( 42, $user_switches, true ), 'User switch to 42 must have occurred' );
assert( end( $user_switches ) === 0, 'Last user switch must restore user 0' );
echo "PASS: test_cron_user_switching_to_author\n";

// --- Test 2: Edge Case - Post has no author (0) -> should fallback to administrator (ID 99) ---
$current_user_id = 0;
$user_switches = array();
$update_called_with_user = null;

$post_no_author = new stdClass();
$post_no_author->ID = 232;
$post_no_author->post_author = 0;
$post_no_author->post_content = 'Original Content';
$post_no_author->post_excerpt = '';

$res2 = $rewriter->virastai_acr_generate_content( 232, $post_no_author, 'content', true );

assert( $res2 === true, 'Rewrite should succeed' );
assert( $update_called_with_user === 99, "wp_update_post must fallback to admin user 99, got: $update_called_with_user" );
assert( $current_user_id === 0, "Original user (0) must be restored after fallback, got: $current_user_id" );
echo "PASS: test_cron_user_switching_fallback_to_admin\n";

// --- Test 3: Failure Case - Already authenticated user with proper permission should NOT switch user ---
$current_user_id = 42;
$user_switches = array();
$update_called_with_user = null;

$res3 = $rewriter->virastai_acr_generate_content( 231, $post, 'content', true );

assert( $res3 === true, 'Rewrite should succeed' );
assert( $update_called_with_user === 42, "wp_update_post should retain authenticated user 42" );
assert( empty( $user_switches ), 'No user switching should occur when current user already has capability' );
echo "PASS: test_authenticated_user_retains_context\n";

echo "All capability switching tests passed successfully.\n";
