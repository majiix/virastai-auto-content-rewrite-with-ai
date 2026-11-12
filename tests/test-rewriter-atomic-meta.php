<?php
/**
 * Test atomic metadata persistence in Virastai_Acr_Rewriter.
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

	$saved_meta = array();
	$update_should_fail = false;

	function get_option( $key, $default = false ) {
		global $test_options;
		return isset( $test_options[ $key ] ) ? $test_options[ $key ] : $default;
	}

	function get_post_meta( $id, $key, $single = true ) {
		global $saved_meta;
		return isset( $saved_meta[ $id ][ $key ] ) ? $saved_meta[ $id ][ $key ] : '';
	}

	function update_post_meta( $id, $key, $val ) {
		global $saved_meta;
		$saved_meta[ $id ][ $key ] = $val;
		return true;
	}

	function delete_post_meta( $id, $key ) {
		global $saved_meta;
		unset( $saved_meta[ $id ][ $key ] );
		return true;
	}

	function get_the_title( $id ) {
		return 'Post ' . $id;
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
								'content' => 'Rewritten content text',
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
		global $update_should_fail;
		if ( $update_should_fail ) {
			return 0; // Simulate update failure (firewall block or error)
		}
		return $args['ID'];
	}
}

require_once __DIR__ . '/../includes/class-virastai-acr-rewriter.php';

$rewriter = new Virastai_Acr_Rewriter();

// Case 1: Failure case - wp_update_post fails.
$update_should_fail = true;
$saved_meta = array();

$post1 = new stdClass();
$post1->ID = 1001;
$post1->post_author = 1;
$post1->post_content = 'Original Content';
$post1->post_excerpt = '';

$res_fail = $rewriter->virastai_acr_generate_content( 1001, $post1, 'content', true );
assert( $res_fail === false, 'Rewrite should report false when update fails' );
assert( empty( $saved_meta[1001]['_virastai_acr_content_generated'] ), 'Meta should NOT be saved as generated when update fails' );
echo "PASS: test_meta_not_saved_on_update_failure\n";

// Case 2: Success case - wp_update_post succeeds.
$update_should_fail = false;
$saved_meta = array();

$res_success = $rewriter->virastai_acr_generate_content( 1001, $post1, 'content', true );
assert( $res_success === true, 'Rewrite should report true when update succeeds' );
assert( isset( $saved_meta[1001]['_virastai_acr_content_generated'] ) && $saved_meta[1001]['_virastai_acr_content_generated'] === '2', 'Meta should be saved when update succeeds' );
echo "PASS: test_meta_saved_on_update_success\n";

echo "All atomic metadata tests passed successfully.\n";
